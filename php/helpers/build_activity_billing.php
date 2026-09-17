<?php

require_once __DIR__ . "/build_customer_billing.php";
require_once __DIR__ . "/build_box_banana_billing.php";
require_once __DIR__ . "/billing_activities.php";
require_once __DIR__ . "/box_banana_customers.php";

/**
 * Builds a single billing activity (hauling / chassis / genset / container_van /
 * fuel) for a customer + date range. Each activity is its own statement: a SAP
 * ZPSO line set (Excel) plus detail rows for the PANABO PDF.
 *
 *   hauling : per-trip rate from the fuel rate matrix
 *   rental  : charge = max(0, total_hours - free_hours) x activity hourly rate
 *             (chassis / genset / container_van), hours from the genset
 *             withdrawn -> delivered window
 *   fuel    : charge = fuel consumption (liters, `refueled`) x diesel price/liter
 *             (fuel_price, customer fuel source) on the trip date
 *
 * Public API:
 *   activity_rate_lookup(PDO, customerKey, activityCode): array{rate,free_hours}
 *   activity_fetch_entries(PDO, customer, from, to, exclude): array
 *   activity_build(PDO, entries, customer, customerKey, activityCode, forex, reference, flatRate): array
 */

function activity_rate_lookup(PDO $conn, string $customerKey, string $activityCode): array
{
    $matrixKey = box_banana_matrix_key($customerKey);
    $stmt = $conn->prepare(
        "SELECT rate, free_hours, material_code FROM activity_rate
         WHERE customer_key IN (?, ?) AND activity_code = ?
         ORDER BY CASE WHEN customer_key = ? THEN 0 ELSE 1 END LIMIT 1"
    );
    $stmt->execute([$customerKey, $matrixKey, $activityCode, $customerKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return [
        "rate" => is_numeric($row["rate"] ?? null) ? (float) $row["rate"] : 0.0,
        "free_hours" => is_numeric($row["free_hours"] ?? null) ? (float) $row["free_hours"] : 48.0,
        "material_code" => trim((string) ($row["material_code"] ?? "")),
    ];
}

function activity_fetch_entries(PDO $conn, array $customer, string $dateFrom, string $dateTo, array $excludeEntryIds = []): array
{
    if (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ||
        $dateFrom > $dateTo
    ) {
        throw new RuntimeException("Invalid date range.");
    }

    // Trip date = the billing trip date (COALESCE(waybill_date, delivery_departure)), the same
    // basis box_banana_fetch_entries uses, so the rental/fuel statement's rows, DATE column and
    // period selection line up with the hauling statement.
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

    $sql = "SELECT entry_id, waybill, ($tripDateExpr)::text AS trip_date,
                   prime_mover, delivered_by_prime_mover, truck, tr, gs, ecs, driver,
                   van_alpha, van_number, destination, delivered_to, empty_pullout_location,
                   total_load, total_trips, billing_sku,
                   genset_start_date, genset_start_time, genset_end_date, genset_end_time,
                   pullout_location_departure_date, pullout_location_departure_time,
                   end_uploading_date, end_uploading_time,
                   genset_hr_meter_start, genset_hr_meter_end, genset_hr_meter, defect_hubo, refueled
            FROM operations
            WHERE $where
            ORDER BY $tripDateExpr ASC, waybill ASC, entry_id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Fetch the activity fields for a specific set of entry ids (for PDF rebuild). */
function activity_fetch_entries_by_ids(PDO $conn, array $entryIds): array
{
    $ids = array_values(array_unique(array_filter(array_map("intval", $entryIds), fn($v) => $v > 0)));
    if (empty($ids)) {
        return [];
    }
    // Trip date = the billing trip date (COALESCE(waybill_date, delivery_departure)), the same
    // basis box_banana_fetch_entries uses, so the rental/fuel statement's rows, DATE column and
    // period selection line up with the hauling statement.
    $tripDateExpr = "COALESCE(waybill_date, loaded_van_delivery_departure_date)";
    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $sql = "SELECT entry_id, waybill, ($tripDateExpr)::text AS trip_date,
                   prime_mover, delivered_by_prime_mover, truck, tr, gs, ecs, driver,
                   van_alpha, van_number, destination, delivered_to, empty_pullout_location,
                   total_load, total_trips, billing_sku,
                   genset_start_date, genset_start_time, genset_end_date, genset_end_time,
                   pullout_location_departure_date, pullout_location_departure_time,
                   end_uploading_date, end_uploading_time,
                   genset_hr_meter_start, genset_hr_meter_end, genset_hr_meter, defect_hubo, refueled
            FROM operations
            WHERE entry_id IN ($placeholders)
            ORDER BY $tripDateExpr ASC, waybill ASC, entry_id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($ids);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Total hours between a start and end date+time; null when either is missing/invalid. */
function activity_hours(?string $startDate, ?string $startTime, ?string $endDate, ?string $endTime): ?float
{
    $startDate = trim((string) $startDate);
    $endDate = trim((string) $endDate);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}/', $endDate)) {
        return null;
    }
    $s = strtotime(substr($startDate, 0, 10) . " " . (trim((string) $startTime) ?: "00:00:00") . " UTC");
    $e = strtotime(substr($endDate, 0, 10) . " " . (trim((string) $endTime) ?: "00:00:00") . " UTC");
    if ($s === false || $e === false || $e < $s) {
        return null;
    }
    return round(($e - $s) / 3600, 2);
}

function activity_fmt_dt(?string $date, ?string $time): string
{
    $date = trim((string) $date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
        return "";
    }
    $ts = strtotime(substr($date, 0, 10) . " " . (trim((string) $time) ?: "00:00:00"));
    return $ts ? date("m/d/Y h:i A", $ts) : "";
}

/** Display configured free hours cleanly in PDF/preview headings (e.g. 24, 24.5). */
function activity_free_hours_label(float $hours): string
{
    $hours = round(max(0.0, $hours), 2);
    return abs($hours - round($hours)) < 0.000001
        ? (string) (int) round($hours)
        : rtrim(rtrim(number_format($hours, 2, ".", ","), "0"), ".");
}

/** True when an RV entry was saved with the DEFECT HUBO switch enabled. */
function activity_defect_hubo($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    return in_array(strtolower(trim((string) $value)), ["1", "true", "t", "yes", "on"], true);
}

/**
 * Compute a single SAP ZPSO row for an activity line. Mirrors the box-banana
 * SAP layout (30 cells) but with the activity's billed-services + charge.
 */
function activity_sap_row(PDO $conn, array $customer, int $item, string $reference, float $charge, float $forex, string $billedServices, array $loadCell, $pm, $trChassis, $gs, ?float $docSerial, string $billingSku = "", string $pulloutLocation = "", string $deliveredLocation = "", float $quantity = 1.0, ?float $unitPrice = null, ?int $conditionUnitOverride = null, ?string $salesUnit = null, bool $blankTr = false, bool $blankGs = false): array
{
    // Keep the SAP header fields aligned with Hauling. In particular, an Activity
    // Rate's material code is for rate setup only; the SAP upload must use the
    // customer's Hauling Material Code.
    $materialCode = (string) ($customer["material_code"] ?? "");
    // Per-activity Sales Unit (rentals STD, fuel L) wins over the customer's sales_unit.
    $salesUnit = ($salesUnit !== null && trim($salesUnit) !== "")
        ? $salesUnit
        : (string) ($customer["sales_unit"] ?? "");
    // Condition unit + price scale come from the document currency, exactly like the
    // hauling/Sumifru builder: USD → 1000 (per-1000), PHP → 0 shown / ×1 priced.
    // Chassis passes a conditionUnitOverride (PHP → 1) so it matches the SAP template.
    $currency = (string) ($customer["document_currency"] ?? "PHP");
    $isUsd = strtoupper(trim($currency)) === "USD";
    $conditionUnit = $conditionUnitOverride ?? billing_sap_condition_unit($currency);
    $scale = billing_sap_price_scale($currency);              // price multiplier: USD 1000 / PHP 1
    // Price is normally the whole charge (Order Quantity 1). When a per-unit rate is
    // given (chassis: rate/hour), Price = that unit rate and Order Quantity = the hours,
    // so SAP's Qty × Price reconciles to the charge.
    $priceBase = $unitPrice ?? $charge;
    $price = $forex > 0 ? ($priceBase / $forex) * $scale : $priceBase * $scale;
    return [
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
        ["t", $billedServices],
        ["n", $item],
        ["t", $materialCode],
        ["n", round($quantity, 2)],
        ["t", $salesUnit],
        $loadCell,
        ["t", $customer["condition_type"] ?? ""],
        ["n", round($price, 2)],
        ["n", $conditionUnit],
        ["t", $customer["document_currency"] ?? ""],
        // Forex Rate: only USD (forex) customers show a rate; PHP / non-forex lines leave it
        // blank, matching Hauling (box_banana_build_sap_rows) and the other PHP pipelines.
        $isUsd ? ["n", round($forex, 3)] : ["t", ""],
        ["t", $customer["profit_center"] ?? ""],
        // PM is intentionally BLANK on every equipment-rental / activity SAP line (finance
        // template): these charges carry only their own equipment (TR/GS), not the prime
        // mover. (Hauling keeps PM via box_banana_build_sap_rows.)
        ["t", ""],
        ["t", ""],
        // Route is intentionally BLANK on the Chassis / Genset / Container Van / Fuel SAP
        // lines (finance request): these equipment/fuel charges are not routed like Hauling.
        ["t", ""],
        // TR (chassis) blanked for genset/fuel; GS (genset) blanked for chassis — each
        // activity only carries its own equipment code.
        $blankTr ? ["t", ""] : ["t", equipment_sap_code($conn, "TR", box_banana_first_non_empty($trChassis, ""))],
        ["t", ""],
        $blankGs ? ["t", ""] : ["t", equipment_sap_code($conn, "GS", $gs)],
        ["t", ""],                              // AD (per-unit helper) — blank, like hauling
    ];
}

/**
 * Build an activity for a set of entries. Returns:
 *   ['sap_rows', 'detail_rows', 'entry_ids', 'total', 'columns', 'priced']
 * detail_rows are assoc arrays keyed by the activity's column keys (for PDF/preview).
 */
function activity_build(PDO $conn, array $entries, array $customer, string $customerKey, string $activityCode, float $forex, string $reference, float $flatRate = 0.0, array $chargeOverrideByEntry = []): array
{
    $activity = billing_activity($activityCode);
    if ($activity === null) {
        throw new RuntimeException("Unknown activity.");
    }
    $basis = $activity["basis"];
    $columns = $activity["columns"];
    $matrixKey = box_banana_matrix_key($customerKey, $customer);
    $fuelSource = (string) ($customer["fuel_source"] ?? "");
    $docSerial = billing_document_serial();

    // Rental / fuel rate config.
    $rateCfg = ["rate" => 0.0, "free_hours" => 48.0, "material_code" => ""];
    if ($basis === "rental" || $basis === "fuel") {
        $rateCfg = activity_rate_lookup($conn, $customerKey, $activityCode);
    }
    $activityLabel = $activity["label"];
    if ($basis === "rental") {
        $freeHoursLabel = activity_free_hours_label((float) $rateCfg["free_hours"]);
        // The charge calculation and every customer-facing header must use the
        // same configurable free-hours value from Master Data.
        $columns["excess_hours"] = "EXCESS OF " . $freeHoursLabel . " HOURS";
        $activityLabel = str_replace("48 HOURS", $freeHoursLabel . " HOURS", $activityLabel);
    }
    $hasMatrix = false;
    if ($basis === "trip" && function_exists("resolve_lane_rate") && $matrixKey !== "") {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM rate_lane WHERE customer_key = ? AND active = TRUE");
        $stmt->execute([$matrixKey]);
        $hasMatrix = ((int) $stmt->fetchColumn()) > 0;
    }

    $sapRows = [];
    $detailRows = [];
    $entryIds = [];
    $chargeByEntry = [];
    $consumptionByEntry = [];        // fuel only: litres consumed per entry (for lane summaries)
    $item = 0;
    $total = 0.0;

    foreach ($entries as $e) {
        $tripDate = box_banana_norm($e["trip_date"] ?? "");
        $dcode = box_banana_first_non_empty($e["destination"] ?? "", $e["delivered_to"] ?? "");
        $van = trim(box_banana_norm($e["van_alpha"] ?? "") . " " . box_banana_norm($e["van_number"] ?? ""));
        $charge = 0.0;
        $rowConsumption = null;      // set in the fuel branch below
        $detail = [];

        if ($basis === "trip") {
            $rate = $flatRate;
            if ($hasMatrix && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tripDate)) {
                $resolved = resolve_lane_rate($conn, $matrixKey, $dcode, $dcode, $tripDate, $fuelSource);
                if (!empty($resolved["matched"])) {
                    $rate = (float) $resolved["rate"];
                }
            }
            $trips = is_numeric($e["total_trips"] ?? null) && (float) $e["total_trips"] > 0 ? (float) $e["total_trips"] : 1.0;
            $charge = $rate * $trips;
            $detail = [
                "date" => activity_date_only($tripDate),
                "trip_receipt" => box_banana_norm($e["waybill"] ?? ""),
                "truck" => billing_digits(box_banana_first_non_empty($e["delivered_by_prime_mover"] ?? "", $e["truck"] ?? "")),
                "trailer" => billing_trailer_label($e["tr"] ?? ""),
                "genset" => billing_digits($e["gs"] ?? ""),
                "van" => $van,
                "route" => $dcode,
                "boxes" => box_banana_norm($e["total_load"] ?? ""),
                "rate" => number_format($rate, 2, ".", ","),
                "charge" => number_format($charge, 2, ".", ","),
            ];
        } elseif ($basis === "rental") {
            // ALL rentals (Chassis, Genset, Container Van) bill the Equipment Rental window =
            // Withdrawal (pull-out departure) → END OF UNLOADING, matching the RAW export's
            // EQUIPMENT RENTAL START/END columns. (Genset previously used the genset run window;
            // per user it now matches Chassis.)
            $rentStartDate = $e["pullout_location_departure_date"] ?? "";
            $rentStartTime = $e["pullout_location_departure_time"] ?? "";
            $rentEndDate = $e["end_uploading_date"] ?? "";
            $rentEndTime = $e["end_uploading_time"] ?? "";
            $hours = activity_hours($rentStartDate, $rentStartTime, $rentEndDate, $rentEndTime);
            $excess = ($hours !== null) ? max(0.0, $hours - $rateCfg["free_hours"]) : 0.0;
            $charge = round($excess * $rateCfg["rate"], 2);
            $detail = [
                "date" => activity_date_only($tripDate),
                "ecs" => box_banana_norm($e["ecs"] ?? ""),
                "truck" => billing_digits(box_banana_first_non_empty($e["delivered_by_prime_mover"] ?? "", $e["truck"] ?? "")),
                "chassis" => billing_trailer_label($e["tr"] ?? ""),
                "genset" => billing_digits($e["gs"] ?? ""),
                "van" => $van,
                "withdrawn" => activity_fmt_dt($rentStartDate, $rentStartTime),
                "delivered" => activity_fmt_dt($rentEndDate, $rentEndTime),
                "total_hours" => $hours !== null ? number_format($hours, 2, ".", ",") : "",
                "excess_hours" => number_format($excess, 2, ".", ","),
                "rate" => number_format($rateCfg["rate"], 2, ".", ","),
                "charge" => number_format($charge, 2, ".", ","),
            ];
        } else { // fuel
            // FUEL CONSUMPTION = genset RUNTIME × 5 L/hr, and FUEL CHARGES = consumption ×
            // price/liter. Runtime is the HUBO-meter runtime (end − start), falling back to
            // genset_hr_meter; for ABC Box Bananas / Dole Asia with a defective HUBO it uses the
            // actual genset run window (GS start → end) instead.
            $usesDefectHuboFuelRule = str_starts_with($customerKey, "abc_") || str_starts_with($customerKey, "dole_asia");
            $defectHubo = $usesDefectHuboFuelRule && activity_defect_hubo($e["defect_hubo"] ?? null);
            $actualRuntime = activity_hours(
                $e["genset_start_date"] ?? "",
                $e["genset_start_time"] ?? "",
                $e["genset_end_date"] ?? "",
                $e["genset_end_time"] ?? ""
            );
            $huboStart = is_numeric($e["genset_hr_meter_start"] ?? null) ? (float) $e["genset_hr_meter_start"] : null;
            $huboEnd = is_numeric($e["genset_hr_meter_end"] ?? null) ? (float) $e["genset_hr_meter_end"] : null;
            $runtime = $defectHubo
                ? $actualRuntime
                : (($huboStart !== null && $huboEnd !== null && $huboEnd >= $huboStart)
                ? round($huboEnd - $huboStart, 2)
                : (is_numeric($e["genset_hr_meter"] ?? null) ? (float) $e["genset_hr_meter"] : null));
            // Consumption is always Runtime × 5 L/hr (was the entered refueled quantity).
            $consumption = $runtime !== null ? round($runtime * 5, 2) : 0.0;
            $rowConsumption = $consumption;
            // PRICE/LITER = the rate matrix fuel for this trip's lane/date (the pinned
            // monthly_fuel_average when set, otherwise the effective fuel_price) — the SAME fuel
            // the hauling rate uses — falling back to the plain fuel_price lookup if no lane matches.
            $fuelDay = substr($tripDate, 0, 10);
            $matrixFuel = null;
            if (function_exists("resolve_lane_rate") && $matrixKey !== "" && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fuelDay)) {
                $rf = resolve_lane_rate($conn, $matrixKey, $dcode, $dcode, $fuelDay, $fuelSource);
                if (!empty($rf["matched"]) && $rf["fuel_price"] !== null) {
                    $matrixFuel = (float) $rf["fuel_price"];
                }
            }
            $priceLiter = $matrixFuel ?? (fuel_value_for_source(fuel_price_for_customer_date($conn, $matrixKey, $fuelDay), $fuelSource) ?? 0.0);
            $charge = round($consumption * $priceLiter, 2);
            $detail = [
                "date" => activity_date_only($tripDate),
                "ecs" => box_banana_norm($e["ecs"] ?? ""),
                "chassis" => billing_trailer_label($e["tr"] ?? ""),
                "genset" => billing_digits($e["gs"] ?? ""),
                "alpha" => box_banana_norm($e["van_alpha"] ?? ""),
                "number" => box_banana_norm($e["van_number"] ?? ""),
                "hubo_start" => $huboStart !== null ? number_format($huboStart, 2, ".", ",") : "",
                "hubo_end" => $huboEnd !== null ? number_format($huboEnd, 2, ".", ",") : "",
                "runtime" => $runtime !== null ? number_format($runtime, 2, ".", ",") : "",
                "consumption" => $consumption > 0 ? number_format($consumption, 2, ".", ",") : "",
                "price_liter" => number_format($priceLiter, 2, ".", ","),
                "charge" => number_format($charge, 2, ".", ","),
            ];
        }

        // Regeneration: a stored/edited charge overrides the computed amount and
        // forces the line to be emitted even when there is no detention / fuel data.
        $eid = (int) ($e["entry_id"] ?? 0);
        $hasOverride = array_key_exists($eid, $chargeOverrideByEntry) && is_numeric($chargeOverrideByEntry[$eid]);
        if ($hasOverride) {
            $charge = (float) $chargeOverrideByEntry[$eid];
            $detail["charge"] = number_format($charge, 2, ".", ",");
        }
        // The statement lists EVERY trip (like the hauling statement): a trip within the free
        // hours (or with no fuel) simply shows a 0 charge. The SAP ZPSO export still omits those
        // 0-amount lines so finance never uploads empty charges.
        $chargeable = $basis === "trip" || $charge > 0;

        $entryIds[] = $eid;
        $chargeByEntry[$eid] = round($charge, 2);
        if ($rowConsumption !== null) {
            $consumptionByEntry[$eid] = round($rowConsumption, 2);
        }
        $total += $charge;
        $detailRows[] = $detail;
        if ($chargeable) {
            $item += 10;
            // SAP template (finance): Billed Services = the customer's hauling service line,
            // Order Quantity = the billable measure, Price = the per-unit rate, Condition
            // Unit = 1 for PHP, and a per-activity Sales Unit (rentals STD, fuel L).
            //   chassis/genset (rental): Qty = billable hours, Price = per-hour rate.
            //   fuel:                    Qty = litres consumed, Price = price/litre.
            // Container van + hauling keep the default Qty 1 / Price = charge.
            $sapBilled = $activityLabel;
            $sapQuantity = 1.0;
            $sapUnitPrice = null;
            $sapConditionUnit = null;
            $sapSalesUnit = null;
            $sapService = trim((string) ($customer["billed_services"] ?? "")) !== ""
                ? (string) $customer["billed_services"]
                : $activityLabel;
            $custCurrency = (string) ($customer["document_currency"] ?? "PHP");
            $activitySalesUnit = (string) ($activity["sap_sales_unit"] ?? "");
            if (in_array($activityCode, ["chassis", "genset"], true)) {
                $sapBilled = $sapService;
                $sapConditionUnit = billing_sap_hauling_condition_unit($custCurrency);
                $sapSalesUnit = $activitySalesUnit;
                $rentalRate = (float) $rateCfg["rate"];
                if ($rentalRate > 0) {
                    $sapUnitPrice = $rentalRate;
                    // Quantity = billable hours; derived from the charge so Qty × Price
                    // still reconciles when the charge was manually overridden/locked.
                    $sapQuantity = round($charge / $rentalRate, 2);
                }
            } elseif ($activityCode === "fuel") {
                $sapBilled = $sapService;
                $sapConditionUnit = billing_sap_hauling_condition_unit($custCurrency);
                $sapSalesUnit = $activitySalesUnit;
                if ($priceLiter > 0) {
                    $sapUnitPrice = $priceLiter;
                    // Quantity = litres consumed; derived from the charge so Qty × Price
                    // reconciles under locked/overridden charges.
                    $sapQuantity = round($charge / $priceLiter, 2);
                }
            }
            $sapRows[] = activity_sap_row(
                $conn,
                $customer,
                $item,
                $reference,
                $charge,
                $forex,
                $sapBilled,
                billing_load_cell($e),
                box_banana_first_non_empty($e["delivered_by_prime_mover"] ?? "", $e["truck"] ?? ""),
                $e["tr"] ?? "",
                $e["gs"] ?? "",
                $docSerial,
                (string) ($e["billing_sku"] ?? ""),
                (string) ($e["empty_pullout_location"] ?? ""),
                box_banana_first_non_empty($e["delivered_to"] ?? "", $e["destination"] ?? ""),
                $sapQuantity,
                $sapUnitPrice,
                $sapConditionUnit,
                $sapSalesUnit,
                // Chassis carries no genset (GS blank); genset/fuel carry no chassis (TR blank).
                in_array($activityCode, ["genset", "fuel"], true),
                $activityCode === "chassis"
            );
        }
    }

    return [
        "sap_rows" => $sapRows,
        "detail_rows" => $detailRows,
        "entry_ids" => $entryIds,
        "charge_by_entry" => $chargeByEntry,
        "consumption_by_entry" => $consumptionByEntry,
        "total" => round($total, 2),
        "columns" => $columns,
        "label" => $activityLabel,
        "priced" => $basis === "trip" ? $hasMatrix : ($rateCfg["rate"] > 0 || $basis === "fuel"),
    ];
}

function activity_date_only(string $tripDate): string
{
    $tripDate = substr(trim($tripDate), 0, 10);
    $ts = strtotime($tripDate);
    return $ts ? date("m/d/Y", $ts) : "";
}
