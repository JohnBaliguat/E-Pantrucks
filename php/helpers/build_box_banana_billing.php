<?php

require_once __DIR__ . "/build_customer_billing.php";
require_once __DIR__ . "/box_banana_customers.php";
// sku_route_sap_no() lives in build_customer_billing.php (shared by all SAP builders).

/**
 * Builder for the Box Bananas (breakbulk banana hauling) billing statement.
 * Produces a single-sheet workbook that reproduces the per-customer statement
 * page seen in the templates (e.g. the "ABC-PAN BB" sheet):
 *
 *   PANABO TRUCKING SERVICES, INC.
 *   Prk. 09 A. O. Floirendo 8105 City of Panabo Davao del Norte Philippines
 *   TIN: VAT Reg.: 000-982-500-000
 *
 *   CHARGE TO:      <customer>
 *   ACTIVITY:       HAULING OF BREAKBULK BANANAS
 *   DESTINATION:    <destination>
 *   REFERENCE NO.:  <reference>
 *   PERIOD COVERED: <from>  to  <to>
 *
 *   DATE | TRIP RECEIPT | TRUCK | CHASSIS | DCODE | DRIVER | BOXES | TRIPS | AMOUNT
 *   ...one row per trip...
 *                                                          TOTAL | <boxes> | <trips> | <amount>
 *
 * Public API:
 *   box_banana_columns(): array
 *   box_banana_rate(PDO, string $rateCode): float
 *   box_banana_fetch_entries(PDO, array $customer, $from, $to, array $exclude = []): array
 *   box_banana_apply_selection(array $entries, array $selected = [], array $order = []): array
 *   box_banana_build_rows(array $entries, array $customer, float $rate): array
 *   box_banana_write_xlsx(array $built, array $customer, $reference, $from, $to, string $outPath): void
 */

function box_banana_norm($value): string
{
    return trim((string) ($value ?? ""));
}

/** Strip everything except digits, e.g. "PM642" -> "642". Falls back to trimmed. */
function box_banana_digits($value): string
{
    $text = box_banana_norm($value);
    if ($text === "") {
        return "";
    }
    $digits = preg_replace('/\D+/', "", $text);
    return $digits !== "" ? $digits : $text;
}

function box_banana_first_non_empty(...$values): string
{
    foreach ($values as $value) {
        $text = box_banana_norm($value);
        if ($text !== "") {
            return $text;
        }
    }
    return "";
}

function box_banana_serial_date(?string $date): ?float
{
    $date = box_banana_norm($date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $date) || str_starts_with($date, "0000-")) {
        return null;
    }
    $timestamp = strtotime(substr($date, 0, 10) . " 00:00:00 UTC");
    if ($timestamp === false) {
        return null;
    }
    $serial = ($timestamp / 86400) + 25569;
    return $serial > 0 ? $serial : null;
}

/** Ordered statement table columns: [key => header label]. */
function box_banana_columns(): array
{
    return [
        "date" => "DATE",
        "trip_receipt" => "TRIP RECEIPT",
        "truck" => "TRUCK",
        "chassis" => "CHASSIS",
        "dcode" => "DCODE",
        "driver" => "DRIVER",
        "boxes" => "BOXES",
        "trips" => "TRIPS",
        "amount" => "AMOUNT",
    ];
}

/** Which columns are numeric / date (everything else is text). */
function box_banana_column_types(): array
{
    return [
        "date" => "date",
        "boxes" => "number",
        "trips" => "number",
        "amount" => "number",
    ];
}

/** PHP rate (per trip or per box) for the customer's rate_code. */
function box_banana_rate(PDO $conn, string $rateCode): float
{
    $rateCode = trim($rateCode);
    if ($rateCode === "") {
        return 0.0;
    }
    $stmt = $conn->prepare("SELECT NULLIF(rate, '')::numeric FROM rates WHERE rate_code = ? LIMIT 1");
    $stmt->execute([$rateCode]);
    $value = $stmt->fetchColumn();
    return is_numeric($value) ? (float) $value : 0.0;
}

/** Fetch the operations rows that belong to this Box Bananas customer. */
function box_banana_fetch_entries(PDO $conn, array $customer, string $dateFrom, string $dateTo, array $excludeEntryIds = []): array
{
    if (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ||
        $dateFrom > $dateTo
    ) {
        throw new RuntimeException("Invalid date range.");
    }

    // The billing trip/hauling date = the Trip Receipt (waybill) date, falling back to the
    // van's DELIVERY DEPARTURE date when waybill_date is blank (user rule 2026-08-12, for all
    // Box Banana + Sumifru billing). A trip with neither date is not selected until finance
    // fills one in.
    $tripDateExpr = "COALESCE(waybill_date, loaded_van_delivery_departure_date)";

    $params = [$dateFrom, $dateTo];
    $where = "$tripDateExpr BETWEEN ? AND ?";

    if (!empty($customer["entry_type"])) {
        $where .= " AND entry_type = ?";
        $params[] = $customer["entry_type"];
    }
    if (!empty($customer["segment"])) {
        $where .= " AND segment = ?";
        $params[] = $customer["segment"];
    }
    if (!empty($customer["customer_match"])) {
        $where .= " AND (customer_ph ILIKE ? OR shipper ILIKE ? OR operations_ph ILIKE ?)";
        $like = "%" . $customer["customer_match"] . "%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $excludeIds = array_values(array_unique(array_filter(array_map("intval", $excludeEntryIds), fn($v) => $v > 0)));
    if (!empty($excludeIds)) {
        $where .= " AND entry_id NOT IN (" . implode(",", array_fill(0, count($excludeIds), "?")) . ")";
        foreach ($excludeIds as $id) {
            $params[] = $id;
        }
    }

    // van_alpha / van_number / load_description are not used by the SAP layout but
    // are part of the Sumifru statement detail (see billing_pdf_detail_rows).
    $sql = "SELECT entry_id, waybill, ($tripDateExpr)::text AS trip_date,
                   prime_mover, delivered_by_prime_mover, truck, tr, gs, driver,
                   van_alpha, van_number, destination, delivered_to, empty_pullout_location,
                   total_load, load_description, load_quantity_weight, total_trips, billing_sku
            FROM operations
            WHERE $where
            ORDER BY $tripDateExpr ASC, waybill ASC, entry_id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Apply the user's include list and manual row order to fetched entries. */
function box_banana_apply_selection(array $entries, array $selectedEntryIds = [], array $entryOrder = []): array
{
    $selectedMap = [];
    foreach ($selectedEntryIds as $entryId) {
        $entryId = (int) $entryId;
        if ($entryId > 0) {
            $selectedMap[$entryId] = true;
        }
    }

    if (!empty($selectedMap)) {
        $entries = array_values(array_filter($entries, static function (array $entry) use ($selectedMap): bool {
            $entryId = (int) ($entry["entry_id"] ?? 0);
            return $entryId > 0 && isset($selectedMap[$entryId]);
        }));
    }

    $orderMap = [];
    $position = 0;
    foreach ($entryOrder as $entryId) {
        $entryId = (int) $entryId;
        if ($entryId > 0 && !isset($orderMap[$entryId])) {
            $orderMap[$entryId] = $position++;
        }
    }

    if (!empty($orderMap)) {
        usort($entries, static function (array $a, array $b) use ($orderMap): int {
            $aId = (int) ($a["entry_id"] ?? 0);
            $bId = (int) ($b["entry_id"] ?? 0);
            $aPos = $orderMap[$aId] ?? PHP_INT_MAX;
            $bPos = $orderMap[$bId] ?? PHP_INT_MAX;
            return $aPos === $bPos ? ($aId <=> $bId) : ($aPos <=> $bPos);
        });
    }

    return array_values($entries);
}

/**
 * Build typed statement rows from operations entries.
 * Returns ['rows' => [...], 'entry_ids' => [...], 'total_boxes', 'total_trips',
 *          'total_amount', 'priced_via_matrix' (bool)].
 * Each row is an ordered list of [type, value] cells matching box_banana_columns().
 *
 * Pricing: when $conn + $customerKey are supplied and the customer has a rate
 * matrix (rate_lane rows), each trip's AMOUNT is priced through the fuel-based
 * engine — the lane base rate stepped up by the fuel band in effect on the trip
 * date, using the customer's fuel source. When no matrix exists, it falls back
 * to the flat $rate (rate_code from master data), preserving prior behaviour.
 */
function box_banana_build_rows(
    array $entries,
    array $customer,
    float $rate,
    ?PDO $conn = null,
    string $customerKey = ""
): array {
    $rateBasis = ($customer["rate_basis"] ?? "trip") === "box" ? "box" : "trip";
    $defaultBoxes = isset($customer["default_boxes"]) && is_numeric($customer["default_boxes"])
        ? (float) $customer["default_boxes"]
        : null;
    $fuelSource = (string) ($customer["fuel_source"] ?? "");

    // Detect whether a rate matrix exists for this customer (engine pricing).
    $matrixKey = box_banana_matrix_key($customerKey, $customer);
    $useMatrix = false;
    if ($conn !== null && $matrixKey !== "" && function_exists("resolve_lane_rate")) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM rate_lane WHERE customer_key = ? AND active = TRUE");
        $stmt->execute([$matrixKey]);
        $useMatrix = ((int) $stmt->fetchColumn()) > 0;
    }

    $rows = [];
    $entryIds = [];
    $totalBoxes = 0.0;
    $totalTrips = 0.0;
    $totalAmount = 0.0;

    foreach ($entries as $e) {
        $entryIds[] = (int) $e["entry_id"];

        $tripDate = box_banana_norm($e["trip_date"] ?? "");
        $date = box_banana_serial_date($tripDate);

        $boxesRaw = box_banana_first_non_empty($e["total_load"] ?? "", $e["load_quantity_weight"] ?? "");
        $boxes = is_numeric($boxesRaw) ? (float) $boxesRaw : $defaultBoxes;

        $trips = is_numeric($e["total_trips"] ?? null) ? (float) $e["total_trips"] : 1.0;
        if ($trips <= 0) {
            $trips = 1.0;
        }

        $dcode = box_banana_first_non_empty($e["destination"] ?? "", $e["delivered_to"] ?? "");

        // Resolve the per-trip rate: fuel matrix when available, else flat rate.
        $tripRate = $rate;
        if ($useMatrix && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tripDate)) {
            $resolved = resolve_lane_rate($conn, $matrixKey, $dcode, $dcode, $tripDate, $fuelSource);
            if (!empty($resolved["matched"])) {
                $tripRate = (float) $resolved["rate"];
            }
        }

        $amount = $rateBasis === "box"
            ? ($boxes !== null ? $tripRate * $boxes : 0.0)
            : $tripRate * $trips;

        if ($boxes !== null) {
            $totalBoxes += $boxes;
        }
        $totalTrips += $trips;
        $totalAmount += $amount;

        $rows[] = [
            ["date", $date],
            ["t", box_banana_norm($e["waybill"] ?? "")],
            ["t", box_banana_digits(box_banana_first_non_empty($e["delivered_by_prime_mover"] ?? "", $e["truck"] ?? ""))],
            ["t", billing_trailer_label($e["tr"] ?? "")],
            ["t", $dcode],
            ["t", box_banana_norm($e["driver"] ?? "")],
            ["n", $boxes],
            ["n", $trips],
            ["n", round($amount, 2)],
        ];
    }

    return [
        "rows" => $rows,
        "entry_ids" => $entryIds,
        "total_boxes" => round($totalBoxes, 2),
        "total_trips" => round($totalTrips, 2),
        "total_amount" => round($totalAmount, 2),
        "priced_via_matrix" => $useMatrix,
    ];
}

/**
 * Resolve the per-trip rate for a customer + lane on a given date. Prefers the
 * fuel rate matrix (keyed by the customer's matrix_key); falls back to $flatRate.
 */
function box_banana_trip_rate(
    PDO $conn,
    string $matrixKey,
    string $fuelSource,
    string $dcode,
    string $tripDate,
    float $flatRate
): float {
    if ($matrixKey !== "" && function_exists("resolve_lane_rate")
        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tripDate)) {
        $resolved = resolve_lane_rate($conn, $matrixKey, $dcode, $dcode, $tripDate, $fuelSource);
        if (!empty($resolved["matched"])) {
            return (float) $resolved["rate"];
        }
    }
    return $flatRate;
}

/**
 * Build the Box Bananas billing in the SAP ZPSO upload format — the SAME layout
 * the Customer Billing page produces (build_customer_billing.php). One line per
 * trip; the per-line PHP rate comes from the fuel rate matrix (or the flat
 * rate_code), and the Price is that rate converted by $forex * condition_unit.
 *
 * Returns ['rows', 'entry_ids', 'total', 'priced_via_matrix'] where rows are the
 * 30-cell typed rows expected by billing_write_xlsx().
 */
function box_banana_build_sap_rows(
    PDO $conn,
    array $entries,
    array $customer,
    string $customerKey,
    float $forex,
    string $reference,
    float $flatRate = 0.0,
    ?callable $rateResolver = null,
    array $manualForexByEntry = [],
    array $chargeOverrideByEntry = []
): array {
    $matrixKey = box_banana_matrix_key($customerKey, $customer);
    $fuelSource = (string) ($customer["fuel_source"] ?? "");
    // Condition Unit column + price scale are assigned by document currency:
    // USD → 1000 (price per 1000), PHP → 0 shown / ×1 priced (flat).
    $currency = (string) ($customer["document_currency"] ?? "PHP");
    $isUsd = strtoupper(trim($currency)) === "USD";
    $conditionUnit = billing_sap_hauling_condition_unit($currency); // USD 1000 / PHP 1 (display only)
    $scale = billing_sap_price_scale($currency);

    // A custom resolver (e.g. Sumifru lane+tier) takes precedence over the matrix.
    $useMatrix = false;
    if ($rateResolver === null && function_exists("resolve_lane_rate") && $matrixKey !== "") {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM rate_lane WHERE customer_key = ? AND active = TRUE");
        $stmt->execute([$matrixKey]);
        $useMatrix = ((int) $stmt->fetchColumn()) > 0;
    }

    $docSerial = billing_document_serial();

    $rows = [];
    $entryIds = [];
    $chargeByEntry = [];
    $item = 0;
    $total = 0.0;

    foreach ($entries as $e) {
        $item += 10;
        $entryIds[] = (int) $e["entry_id"];

        $tripDate = billing_norm($e["trip_date"] ?? "");
        $dcode = box_banana_first_non_empty($e["destination"] ?? "", $e["delivered_to"] ?? "");
        if ($rateResolver !== null) {
            $rate = (float) $rateResolver($e, $tripDate, $dcode);
        } elseif ($useMatrix) {
            $rate = box_banana_trip_rate($conn, $matrixKey, $fuelSource, $dcode, $tripDate, $flatRate);
        } else {
            $rate = $flatRate;
        }
        // Regeneration: a stored/edited charge for this entry overrides the computed
        // rate, so the rebuilt file reflects exactly the locked (or hand-edited) amount.
        $entryKeyId = (int) ($e["entry_id"] ?? 0);
        if (array_key_exists($entryKeyId, $chargeOverrideByEntry) && is_numeric($chargeOverrideByEntry[$entryKeyId])) {
            $rate = (float) $chargeOverrideByEntry[$entryKeyId];
        }
        // Lock the PHP peso charge for this trip (the rate summary's per-line amount).
        $chargeByEntry[$entryKeyId] = round($rate, 2);

        // Forex is effective per trip date. A USD trip with no covering rate must
        // stay blank instead of being converted with an old or future rate.
        $manualForex = (float) ($manualForexByEntry[(int) ($e["entry_id"] ?? 0)] ?? 0);
        $lineForex = $isUsd ? ($manualForex > 0 ? $manualForex : billing_forex_rate($conn, substr($tripDate, 0, 10), $customerKey)) : 1.0;
        $hasForex = !$isUsd || $lineForex > 0;
        $price = $hasForex ? ($rate / $lineForex) * $scale : 0.0;
        $perUnit = $hasForex ? $rate / $lineForex : 0.0;
        if ($hasForex) {
            $total += $perUnit;
        }

        $rows[] = [
            ["t", $customer["order_type"] ?? ""],
            ["t", $customer["sales_org"] ?? ""],
            ["t", $customer["distribution_channel"] ?? ""],
            ["t", $customer["division"] ?? ""],
            ["dt", $docSerial],
            ["dt", $docSerial],
            ["t", $customer["sold_to"] ?? ""],
            ["t", $customer["customer_tax_class"] ?? ""],
            ["t", $customer["document_currency"] ?? ""],
            ["t", $reference],
            ["t", ""],
            ["t", $customer["billed_services"] ?? ""],
            ["n", $item],
            ["t", $customer["material_code"] ?? ""],
            ["n", 1],
            ["t", $customer["sales_unit"] ?? ""],
            billing_load_cell($e),
            ["t", $customer["condition_type"] ?? ""],
            $hasForex ? ["n", round($price, 2)] : ["t", ""],
            ["n", $conditionUnit],
            ["t", $customer["document_currency"] ?? ""],
            $isUsd && $hasForex ? ["n", round($lineForex, 3)] : ["t", ""],
            ["t", $customer["profit_center"] ?? ""],
            // PM / TR / GS = the unit's SAP Equipment code (Master Data → Equipment SAP
            // Code), else the raw unit digits.
            ["n3", billing_sap_three_digits(equipment_sap_code($conn, "PM", billing_first_non_empty_bb($e["delivered_by_prime_mover"] ?? "", $e["truck"] ?? "")))],
            ["t", billing_sap_hauling_activity($currency)],   // Activity: PHP → P01, USD → blank
            // Route resolved from the trip's ACTUAL geography (pull-out + delivered region +
            // the SKU's PH) so a mistagged SKU can't send it to the wrong port; falls back to
            // the stored-SKU route, then the per-customer route, when the geography is unmapped.
            ["n3", billing_sap_three_digits(
                trip_route_sap_no(
                    $conn,
                    (string) ($e["empty_pullout_location"] ?? ""),
                    box_banana_first_non_empty($e["delivered_to"] ?? "", $e["destination"] ?? ""),
                    (string) ($e["billing_sku"] ?? "")
                )
                ?: sku_route_sap_no($conn, (string) ($e["billing_sku"] ?? ""))
                ?: ($customer["route"] ?? "")
            )],
            ["n3", billing_sap_three_digits(equipment_sap_code($conn, "TR", billing_first_non_empty_bb($e["tr"] ?? "", $e["waybill"] ?? "")))],
            ["t", ""],
            ["n3", billing_sap_three_digits(equipment_sap_code($conn, "GS", $e["gs"] ?? ""))],
            ["t", ""],                              // AD (per-unit helper) — intentionally blank
        ];
    }

    return [
        "rows" => $rows,
        "entry_ids" => $entryIds,
        "charge_by_entry" => $chargeByEntry,
        "total" => round($total, 2),
        "priced_via_matrix" => $useMatrix,
    ];
}

/**
 * Per-trip detail rows for the matrix (Box Bananas) pipeline — one assoc row per
 * trip keyed by box_banana_columns(), i.e. this customer's OWN statement fields.
 *
 * Pricing mirrors box_banana_build_sap_rows() exactly (same matrix lookup / flat
 * fallback / optional resolver), so the preview and the generated file agree.
 *
 * Returns ['columns','rows','entry_ids','total','priced_via_matrix'].
 */
function box_banana_detail_rows(
    PDO $conn,
    array $entries,
    array $customer,
    string $customerKey,
    float $flatRate = 0.0,
    ?callable $rateResolver = null
): array {
    $matrixKey = box_banana_matrix_key($customerKey, $customer);
    $fuelSource = (string) ($customer["fuel_source"] ?? "");
    $rateBasis = ($customer["rate_basis"] ?? "trip") === "box" ? "box" : "trip";
    $defaultBoxes = isset($customer["default_boxes"]) && is_numeric($customer["default_boxes"])
        ? (float) $customer["default_boxes"]
        : null;

    $useMatrix = false;
    if ($rateResolver === null && function_exists("resolve_lane_rate") && $matrixKey !== "") {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM rate_lane WHERE customer_key = ? AND active = TRUE");
        $stmt->execute([$matrixKey]);
        $useMatrix = ((int) $stmt->fetchColumn()) > 0;
    }

    $rows = [];
    $entryIds = [];
    $total = 0.0;

    foreach ($entries as $e) {
        $tripDate = box_banana_norm($e["trip_date"] ?? "");
        $dcode = box_banana_first_non_empty($e["destination"] ?? "", $e["delivered_to"] ?? "");

        if ($rateResolver !== null) {
            $rate = (float) $rateResolver($e, $tripDate, $dcode);
        } elseif ($useMatrix) {
            $rate = box_banana_trip_rate($conn, $matrixKey, $fuelSource, $dcode, $tripDate, $flatRate);
        } else {
            $rate = $flatRate;
        }

        $boxesRaw = box_banana_first_non_empty($e["total_load"] ?? "", $e["load_quantity_weight"] ?? "");
        $boxes = is_numeric($boxesRaw) ? (float) $boxesRaw : $defaultBoxes;

        $trips = is_numeric($e["total_trips"] ?? null) ? (float) $e["total_trips"] : 1.0;
        if ($trips <= 0) {
            $trips = 1.0;
        }

        $amount = $rateBasis === "box"
            ? ($boxes !== null ? $rate * $boxes : 0.0)
            : $rate * $trips;
        $total += $amount;
        $entryIds[] = (int) $e["entry_id"];

        $ts = preg_match('/^\d{4}-\d{2}-\d{2}/', $tripDate) ? strtotime(substr($tripDate, 0, 10)) : false;

        $rows[] = [
            "date" => $ts ? date("m/d/Y", $ts) : "",
            "trip_receipt" => box_banana_norm($e["waybill"] ?? ""),
            "truck" => box_banana_digits(box_banana_first_non_empty($e["delivered_by_prime_mover"] ?? "", $e["truck"] ?? "")),
            "chassis" => billing_trailer_label($e["tr"] ?? ""),
            "dcode" => $dcode,
            "driver" => box_banana_norm($e["driver"] ?? ""),
            "boxes" => $boxes !== null ? number_format($boxes, 0, ".", ",") : "",
            "trips" => rtrim(rtrim(number_format($trips, 2, ".", ""), "0"), "."),
            "amount" => number_format(round($amount, 2), 2, ".", ","),
        ];
    }

    return [
        "columns" => box_banana_columns(),
        "rows" => $rows,
        "entry_ids" => $entryIds,
        "total" => round($total, 2),
        "priced_via_matrix" => $useMatrix,
    ];
}

/**
 * Rate-usage summary for the fuel-matrix pipeline: group the previewed trips by
 * (lane, fuel price) and show how each charged rate was derived from the lane's
 * formula (base rate stepped up by the fuel movement). Lets finance see WHAT rate
 * each trip billed at and why, without reading every row.
 *
 * Returns [ 'lines' => [ {lane, dcode, base_rate, pump_price, price_movement,
 * fuel_price, rate, trips, subtotal} ... ], 'unmatched' => int,
 * 'fuel_source' => string ], lines sorted by subtotal desc.
 */
function box_banana_rate_summary(
    PDO $conn,
    array $entries,
    array $customer,
    string $customerKey,
    float $flatRate
): array {
    $matrixKey = box_banana_matrix_key($customerKey, $customer);
    $fuelSource = (string) ($customer["fuel_source"] ?? "");
    $rateBasis = ($customer["rate_basis"] ?? "trip") === "box" ? "box" : "trip";
    $defaultBoxes = isset($customer["default_boxes"]) && is_numeric($customer["default_boxes"])
        ? (float) $customer["default_boxes"]
        : null;

    $useMatrix = false;
    if ($matrixKey !== "" && function_exists("resolve_lane_rate")) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM rate_lane WHERE customer_key = ? AND active = TRUE");
        $stmt->execute([$matrixKey]);
        $useMatrix = ((int) $stmt->fetchColumn()) > 0;
    }

    // Lane detail (label + formula params) for enriching each summary line.
    $laneInfo = [];
    if ($useMatrix) {
        $stmt = $conn->prepare(
            "SELECT lane_id, segment, origin, packing_house, destination, dcode, base_rate,
                    pump_price, price_movement
             FROM rate_lane WHERE customer_key = ?"
        );
        $stmt->execute([$matrixKey]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $laneInfo[(int) $l["lane_id"]] = $l;
        }
    }

    $groups = [];
    $entryKeys = [];
    $unmatched = 0;
    foreach ($entries as $e) {
        $tripDate = box_banana_norm($e["trip_date"] ?? "");
        $dcode = box_banana_first_non_empty($e["destination"] ?? "", $e["delivered_to"] ?? "");
        $boxesRaw = box_banana_first_non_empty($e["total_load"] ?? "", $e["load_quantity_weight"] ?? "");
        $boxes = is_numeric($boxesRaw) ? (float) $boxesRaw : $defaultBoxes;
        $trips = is_numeric($e["total_trips"] ?? null) ? (float) $e["total_trips"] : 1.0;
        if ($trips <= 0) {
            $trips = 1.0;
        }
        $qty = $rateBasis === "box" ? (float) ($boxes ?? 0) : $trips;

        $rate = $flatRate;
        $matched = $flatRate > 0;
        $lane = null;
        $fuel = null;
        if ($useMatrix && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tripDate)) {
            $r = resolve_lane_rate($conn, $matrixKey, $dcode, $dcode, $tripDate, $fuelSource);
            if (!empty($r["matched"])) {
                $rate = (float) $r["rate"];
                $matched = true;
                $lane = $laneInfo[(int) $r["lane_id"]] ?? null;
                $fuel = $r["fuel_price"] !== null ? (float) $r["fuel_price"] : null;
            } else {
                $matched = false;
            }
        }
        if (!$matched) {
            $unmatched++;
            continue;
        }

        // One line per distinct (lane, charged rate). When formula params exist a
        // different fuel price yields a different rate (own line); when they don't, the
        // rate is constant across fuels, so those trips collapse and fuel shows "varies".
        $laneId = $lane !== null ? (int) $lane["lane_id"] : 0;
        $key = $laneId . "|" . number_format($rate, 2, ".", "");
        $entryKeys[(int) ($e["entry_id"] ?? 0)] = $key;
        if (!isset($groups[$key])) {
            $origin = trim((string) ($lane["origin"] ?? ""));
            $ph = trim((string) ($lane["packing_house"] ?? ""));
            $dest = trim((string) ($lane["destination"] ?? ""));
            $seg = trim((string) ($lane["segment"] ?? ""));
            // Route label: Origin -> Packing House -> Destination (skip blanks).
            $route = implode(" → ", array_filter([$origin, $ph, $dest]));
            $label = trim(implode(" / ", array_filter([$seg, $route])));
            if ($label === "") {
                $label = trim((string) ($lane["dcode"] ?? $dcode)) ?: "Flat rate";
            }
            $groups[$key] = [
                "key" => $key,
                "lane" => $label,
                "dcode" => (string) ($lane["dcode"] ?? $dcode),
                "base_rate" => (float) ($lane["base_rate"] ?? $rate),
                "pump_price" => $lane ? fuel_rate_num($lane["pump_price"] ?? null) : null,
                "price_movement" => $lane ? fuel_rate_num($lane["price_movement"] ?? null) : null,
                "fuel_price" => $fuel,
                "fuel_varies" => false,
                "rate" => $rate,
                "trips" => 0,
                "subtotal" => 0.0,
            ];
        } elseif ($fuel !== null && $groups[$key]["fuel_price"] !== null
            && abs($fuel - (float) $groups[$key]["fuel_price"]) > 0.0001) {
            $groups[$key]["fuel_varies"] = true;
        }
        // TRIPS counts actual trips (sum of each entry's total_trips), not records, so
        // SUBTOTAL (= rate × trips for trip-based pricing) always reconciles with the
        // charged rate × trips shown. A record with total_trips > 1 would otherwise make
        // the trips column understate the money.
        $groups[$key]["trips"] += $trips;
        $groups[$key]["subtotal"] += $rate * $qty;
    }

    $lines = array_values($groups);
    foreach ($lines as &$ln) {
        $ln["subtotal"] = round($ln["subtotal"], 2);
        if (!empty($ln["fuel_varies"])) {
            $ln["fuel_price"] = null; // rate held across fuels -> don't imply a single price
        }
        unset($ln["fuel_varies"]);
    }
    unset($ln);
    usort($lines, static fn($a, $b) => $b["subtotal"] <=> $a["subtotal"]);

    return ["lines" => $lines, "unmatched" => $unmatched, "fuel_source" => $fuelSource, "entry_keys" => $entryKeys];
}

/** Small local first-non-empty (build_customer_billing has no public equivalent). */
function billing_first_non_empty_bb(...$values): string
{
    foreach ($values as $v) {
        $t = trim((string) ($v ?? ""));
        if ($t !== "") {
            return $t;
        }
    }
    return "";
}

// ---------------------------------------------------------------------------
// XLSX writer (from scratch, inline strings) — single statement sheet
// ---------------------------------------------------------------------------

function box_banana_col_letter(int $index): string
{
    $letters = "";
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $letters = chr(65 + $mod) . $letters;
        $index = (int) (($index - $mod) / 26);
    }
    return $letters;
}

function box_banana_xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, "UTF-8");
}

/**
 * Emit one cell. $type: "t" text | "n" number | "date" | "h1" bold title text |
 * "lbl" bold label text | "th" header text. $styleOverride lets callers pick a style.
 */
function box_banana_cell(string $ref, array $cell, int $style): string
{
    [$type, $value] = $cell;
    if ($type === "n" || $type === "date") {
        if ($value === null || $value === "") {
            return '<c r="' . $ref . '" s="' . $style . '"/>';
        }
        $num = rtrim(rtrim(number_format((float) $value, 8, ".", ""), "0"), ".");
        if ($num === "" || $num === "-") {
            $num = "0";
        }
        return '<c r="' . $ref . '" s="' . $style . '"><v>' . $num . "</v></c>";
    }
    $text = box_banana_norm($value);
    if ($text === "") {
        return '<c r="' . $ref . '" s="' . $style . '"/>';
    }
    return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">'
        . box_banana_xml_escape($text) . "</t></is></c>";
}

function box_banana_write_xlsx(array $built, array $customer, string $reference, string $dateFrom, string $dateTo, string $outputPath): void
{
    if (!class_exists("ZipArchive")) {
        throw new RuntimeException("ZipArchive extension is required.");
    }

    $columns = box_banana_columns();
    $columnKeys = array_keys($columns);
    $colCount = count($columnKeys);
    $lastCol = box_banana_col_letter($colCount);
    $company = box_banana_company_header();

    // Style indices (see styles.xml):
    //   0 default, 1 title(bold 12), 2 sub(10), 3 label(bold), 4 value,
    //   5 header(bold,fill,border,center), 6 date(border,center),
    //   7 number(border,center), 8 text(border), 9 total(bold,border)
    $sheetRows = [];
    $merges = [];
    $rowNum = 0;

    $put = static function (int $rn, array $cells) use (&$sheetRows): void {
        $sheetRows[] = '<row r="' . $rn . '">' . implode("", $cells) . "</row>";
    };

    // Company header (rows 1..3), merged across the table width.
    foreach ($company as $line) {
        $rowNum++;
        $style = $rowNum === 1 ? 1 : 2;
        $put($rowNum, [box_banana_cell("A" . $rowNum, ["t", $line], $style)]);
        $merges[] = "A" . $rowNum . ":" . $lastCol . $rowNum;
    }

    // Spacer
    $rowNum++;

    // Statement meta block: label in A, value spanning B..end.
    $meta = [
        ["CHARGE TO:", $customer["charge_to"] ?? ""],
        ["ACTIVITY:", $customer["activity"] ?? ""],
        ["DESTINATION:", $customer["destination"] ?? ""],
        ["REFERENCE NO.:", $reference],
        ["PERIOD COVERED:", $dateFrom . "  to  " . $dateTo],
    ];
    foreach ($meta as [$label, $value]) {
        $rowNum++;
        $put($rowNum, [
            box_banana_cell("A" . $rowNum, ["lbl", $label], 3),
            box_banana_cell("B" . $rowNum, ["t", $value], 4),
        ]);
        $merges[] = "B" . $rowNum . ":" . $lastCol . $rowNum;
    }

    // Spacer
    $rowNum++;

    // Column header row
    $rowNum++;
    $headerCells = [];
    $colIndex = 0;
    foreach ($columns as $key => $label) {
        $colIndex++;
        $ref = box_banana_col_letter($colIndex) . $rowNum;
        $headerCells[] = box_banana_cell($ref, ["th", $label], 5);
    }
    $put($rowNum, $headerCells);
    $headerRowNum = $rowNum;

    // Data rows
    $types = box_banana_column_types();
    foreach ($built["rows"] as $row) {
        $rowNum++;
        $cells = [];
        $colIndex = 0;
        foreach ($columnKeys as $i => $key) {
            $colIndex++;
            $ref = box_banana_col_letter($colIndex) . $rowNum;
            $cell = $row[$i] ?? ["t", ""];
            $cellType = $cell[0];
            if ($cellType === "date") {
                $style = 6;
            } elseif ($cellType === "n") {
                $style = 7;
            } else {
                $style = 8;
            }
            $cells[] = box_banana_cell($ref, $cell, $style);
        }
        $put($rowNum, $cells);
    }

    // Total row: label under DRIVER, then boxes/trips/amount totals.
    $rowNum++;
    $boxesCol = array_search("boxes", $columnKeys, true) + 1;
    $tripsCol = array_search("trips", $columnKeys, true) + 1;
    $amountCol = array_search("amount", $columnKeys, true) + 1;
    $totalCells = [];
    $labelCol = $boxesCol - 1;
    $totalCells[] = box_banana_cell(box_banana_col_letter($labelCol) . $rowNum, ["t", "TOTAL"], 9);
    $totalCells[] = box_banana_cell(box_banana_col_letter($boxesCol) . $rowNum, ["n", $built["total_boxes"]], 9);
    $totalCells[] = box_banana_cell(box_banana_col_letter($tripsCol) . $rowNum, ["n", $built["total_trips"]], 9);
    $totalCells[] = box_banana_cell(box_banana_col_letter($amountCol) . $rowNum, ["n", $built["total_amount"]], 9);
    $put($rowNum, $totalCells);

    $dimension = "A1:" . $lastCol . $rowNum;

    // Column widths
    $widths = [
        "date" => 12, "trip_receipt" => 14, "truck" => 9, "chassis" => 10,
        "dcode" => 16, "driver" => 22, "boxes" => 9, "trips" => 8, "amount" => 14,
    ];
    $colsXml = "<cols>";
    $colIndex = 0;
    foreach ($columnKeys as $key) {
        $colIndex++;
        $w = $widths[$key] ?? 12;
        $colsXml .= '<col min="' . $colIndex . '" max="' . $colIndex . '" width="' . $w . '" customWidth="1"/>';
    }
    $colsXml .= "</cols>";

    $mergeXml = "";
    if (!empty($merges)) {
        $mergeXml = '<mergeCells count="' . count($merges) . '">';
        foreach ($merges as $merge) {
            $mergeXml .= '<mergeCell ref="' . $merge . '"/>';
        }
        $mergeXml .= "</mergeCells>";
    }

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="' . $dimension . '"/>'
        . '<sheetViews><sheetView tabSelected="1" workbookViewId="0">'
        . '<pane ySplit="' . $headerRowNum . '" topLeftCell="A' . ($headerRowNum + 1) . '" activePane="bottomLeft" state="frozen"/>'
        . '</sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . $colsXml
        . "<sheetData>" . implode("", $sheetRows) . "</sheetData>"
        . $mergeXml
        . '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
        . "</worksheet>";

    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="2">'
        . '<numFmt numFmtId="166" formatCode="mm/dd/yyyy"/>'
        . '<numFmt numFmtId="168" formatCode="#,##0.00"/>'
        . "</numFmts>"
        . '<fonts count="4">'
        . '<font><sz val="10"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="12"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="10"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        . "</fonts>"
        . '<fills count="3">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF1D6F42"/><bgColor indexed="64"/></patternFill></fill>'
        . "</fills>"
        . '<borders count="2">'
        . "<border><left/><right/><top/><bottom/><diagonal/></border>"
        . '<border><left style="thin"><color rgb="FFBFBFBF"/></left><right style="thin"><color rgb="FFBFBFBF"/></right>'
        . '<top style="thin"><color rgb="FFBFBFBF"/></top><bottom style="thin"><color rgb="FFBFBFBF"/></bottom><diagonal/></border>'
        . "</borders>"
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="10">'
        // 0 default
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        // 1 title (bold 12)
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
        // 2 sub (10)
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
        // 3 label (bold)
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
        // 4 value
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
        // 5 header (bold white on green, border, center)
        . '<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        // 6 date (border, center)
        . '<xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>'
        // 7 number (border, center, thousands)
        . '<xf numFmtId="168" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>'
        // 8 text (border)
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
        // 9 total (bold, border, thousands)
        . '<xf numFmtId="168" fontId="2" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>'
        . "</cellXfs>"
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . "</styleSheet>";

    $sheetTitle = box_banana_xml_escape(mb_substr(($customer["charge_to"] ?? "BB"), 0, 28));
    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . $sheetTitle . '" sheetId="1" r:id="rId1"/></sheets></workbook>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . "</Relationships>";

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . "</Types>";

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . "</Relationships>";

    @unlink($outputPath);
    $zip = new ZipArchive();
    if ($zip->open($outputPath, ZipArchive::CREATE) !== true) {
        throw new RuntimeException("Failed to create xlsx file.");
    }
    $zip->addFromString("[Content_Types].xml", $contentTypes);
    $zip->addFromString("_rels/.rels", $rootRels);
    $zip->addFromString("xl/workbook.xml", $workbookXml);
    $zip->addFromString("xl/_rels/workbook.xml.rels", $workbookRels);
    $zip->addFromString("xl/styles.xml", $stylesXml);
    $zip->addFromString("xl/worksheets/sheet1.xml", $sheetXml);
    $zip->close();
}
