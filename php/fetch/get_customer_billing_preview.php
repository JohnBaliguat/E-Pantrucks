<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/billing_customers.php";
require_once __DIR__ . "/../helpers/build_customer_billing.php";
require_once __DIR__ . "/../helpers/ensure_customer_billing_schema.php";
require_once __DIR__ . "/../helpers/unified_billing.php";
require_once __DIR__ . "/../helpers/build_box_banana_billing.php";
require_once __DIR__ . "/../helpers/build_billing_pdf.php";
require_once __DIR__ . "/../helpers/ensure_rate_fuel_schema.php";
require_once __DIR__ . "/../helpers/fuel_rate_engine.php";
require_once __DIR__ . "/../helpers/billing_activities.php";
require_once __DIR__ . "/../helpers/build_activity_billing.php";
require_once __DIR__ . "/../helpers/sumifru_rate.php";
require_once __DIR__ . "/../helpers/dict_shuttling.php";
require_once __DIR__ . "/../helpers/abc_kds.php";
require_once __DIR__ . "/../helpers/ensure_data_update_flags_schema.php";
require_once __DIR__ . "/../helpers/build_raw_billing.php";
require_once __DIR__ . "/../helpers/customer_sap_codes.php";

header("Content-Type: application/json; charset=utf-8");
ensure_customer_billing_schema($conn);
ensure_data_update_flags_schema($conn);

// Records already flagged for correction — the preview is where billing raises
// these (the retired Records page used to own this control).
$flaggedMap = [];
foreach ($conn->query("SELECT entry_id, remarks FROM data_update_flags WHERE status = 'open'")->fetchAll(PDO::FETCH_ASSOC) as $fr) {
    $flaggedMap[(int) $fr["entry_id"]] = (string) ($fr["remarks"] ?? "");
}

/**
 * The Billing page preview shows EACH CUSTOMER'S OWN per-trip record detail (the
 * same fields its PANABO statement prints) — not the SAP upload cells, and not
 * the wide RAW reefer layout. Every pipeline therefore answers with the same
 * shape:
 *
 *   columns : [ {key, label}, ... ]          ordered detail columns
 *   records : [ {entry_id, cells:[{key,value}]}, ... ]
 *   count, total, selectable, notes
 *
 * `selectable: false` means a download line aggregates several trips, so the rows
 * cannot be individually excluded (DICT shuttling groups by truck+trailer).
 *
 * `view=raw` overrides all of that and answers with the wide reefer-RV RAW layout
 * instead — the rows the RAW export would write. RV customers only.
 */

/**
 * Equipment-rental charges (Chassis / Genset / Container Van / Fuel) broken down PER LANE
 * for the Rate summary — one extra amount column per activity, aligned with each lane's
 * Subtotal. Shown ONLY when the customer rents equipment (at least one rental Activity Rate
 * is configured). Charges cover exactly the given trips (the hauling preview's entry ids)
 * and are attributed to a lane using $entryKeyMap (entry_id -> lane key), the SAME grouping
 * box_banana_rate_summary() uses for its lines.
 *
 * Returns:
 *   [
 *     "activities" => [ {code, short, label}, ... ]   // which columns to render (in order)
 *     "by_lane"    => [ laneKey => [ code => charge ] ]
 *     "totals"     => [ code => grand total ]
 *   ]
 * Empty arrays for non-matrix / non-rental customers.
 */
function preview_equipment_rental_by_lane(PDO $conn, string $pipeline, array $customer, string $customerKey, array $entryIds, string $dateTo, array $entryKeyMap): array
{
    $empty = ["activities" => [], "by_lane" => [], "totals" => [], "consumption_by_lane" => [], "consumption_totals" => []];
    if ($pipeline !== "matrix" || empty($entryIds)) {
        return $empty;
    }
    $hasEquipmentRental = false;
    foreach (["chassis", "genset", "container_van"] as $rentalCode) {
        if (activity_rate_lookup($conn, $customerKey, $rentalCode)["rate"] > 0) {
            $hasEquipmentRental = true;
            break;
        }
    }
    if (!$hasEquipmentRental) {
        return $empty;
    }

    $currency = strtoupper((string) ($customer["document_currency"] ?? "PHP"));
    $forex = $currency === "USD" ? billing_forex_rate($conn, $dateTo, $customerKey) : 1.0;
    if ($forex <= 0) {
        $forex = 1.0;
    }
    $entries = activity_fetch_entries_by_ids($conn, $entryIds);
    $activities = [];
    $byLane = [];
    $totals = [];
    $consumptionByLane = [];
    $consumptionTotals = [];
    foreach (billing_activity_rate_codes() as $activityCode) {
        try {
            $built = activity_build($conn, $entries, $customer, $customerKey, $activityCode, $forex, "preview", 0.0);
        } catch (Throwable $e) {
            continue;
        }
        // Skip activities with nothing to bill (no configured rate and no charge).
        if (!$built["priced"] && $built["total"] <= 0) {
            continue;
        }
        $def = billing_activity($activityCode);
        // Rental activities carry a per-hour Rate + Free Hours; fuel is priced per litre.
        $rateCfg = activity_rate_lookup($conn, $customerKey, $activityCode);
        $basis = (string) ($def["basis"] ?? "rental");   // "rental" | "fuel"
        $activities[] = [
            "code" => $activityCode,
            "short" => (string) ($def["short"] ?? $activityCode),
            "label" => (string) ($built["label"] ?? ($def["label"] ?? $activityCode)),
            "kind" => $basis === "fuel" ? "fuel" : "rental",
            "rate" => round((float) $rateCfg["rate"], 2),
            "free_hours" => round((float) $rateCfg["free_hours"], 2),
        ];
        $total = 0.0;
        foreach (($built["charge_by_entry"] ?? []) as $eid => $charge) {
            $laneKey = $entryKeyMap[(int) $eid] ?? null;
            if ($laneKey === null) {
                continue; // unmatched/unpriced trip — not attributable to a lane
            }
            $byLane[$laneKey][$activityCode] = round(($byLane[$laneKey][$activityCode] ?? 0.0) + (float) $charge, 2);
            $total += (float) $charge;
        }
        $totals[$activityCode] = round($total, 2);

        // Fuel: also aggregate litres consumed per lane (drives Consumption + effective Price/L).
        if ($basis === "fuel") {
            $consTotal = 0.0;
            foreach (($built["consumption_by_entry"] ?? []) as $eid => $litres) {
                $laneKey = $entryKeyMap[(int) $eid] ?? null;
                if ($laneKey === null) {
                    continue;
                }
                $consumptionByLane[$laneKey][$activityCode] = round(($consumptionByLane[$laneKey][$activityCode] ?? 0.0) + (float) $litres, 2);
                $consTotal += (float) $litres;
            }
            $consumptionTotals[$activityCode] = round($consTotal, 2);
        }
    }
    if (empty($activities)) {
        return $empty;
    }
    return [
        "activities" => $activities,
        "by_lane" => $byLane,
        "totals" => $totals,
        "consumption_by_lane" => $consumptionByLane,
        "consumption_totals" => $consumptionTotals,
    ];
}

$customerKey = trim((string) ($_GET["customer"] ?? ""));
$activityCode = trim((string) ($_GET["activity"] ?? "hauling")) ?: "hauling";
$dateFrom = trim((string) ($_GET["date_from"] ?? ""));
$dateTo = trim((string) ($_GET["date_to"] ?? ""));
$includeBilled = !empty($_GET["include_billed"]) && $_GET["include_billed"] !== "0";

$pipeline = unified_billing_pipeline($customerKey);
if ($pipeline === "") {
    echo json_encode(["success" => false, "message" => "Unknown customer."]);
    exit();
}

/** Uniform validation for the date range. */
$validRange = static function (string $from, string $to): bool {
    return $from !== "" && $to !== ""
        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)
        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)
        && $from <= $to;
};

/**
 * Shape a [key => label] column map plus assoc detail rows into the JSON contract.
 * $entryIds is positional against $detailRows; pass an empty array (or set
 * $selectable false) when a row has no single owning trip.
 */
$respond = static function (array $columnMap, array $detailRows, array $entryIds, array $extra = []) use ($flaggedMap): void {
    $columns = [];
    foreach ($columnMap as $key => $label) {
        $columns[] = ["key" => $key, "label" => $label];
    }

    $manualForexMap = array_fill_keys(array_map("intval", $extra["manual_forex_entry_ids"] ?? []), true);
    // entry_id => rate-summary line key, so the page can recount each lane's trips and
    // subtotal from the rows that stay ticked. Consumed here, not echoed to the client.
    $summaryKeyMap = $extra["summary_key_by_entry"] ?? [];
    unset($extra["summary_key_by_entry"]);
    $records = [];
    foreach ($detailRows as $index => $detail) {
        $cells = [];
        foreach ($columnMap as $key => $label) {
            $cells[] = ["key" => $key, "value" => (string) ($detail[$key] ?? "")];
        }
        $entryId = (int) ($entryIds[$index] ?? 0);
        $records[] = [
            "entry_id" => $entryId,
            "cells" => $cells,
            "flagged" => isset($flaggedMap[$entryId]),
            "flag_remarks" => $flaggedMap[$entryId] ?? "",
            "manual_forex_needed" => isset($manualForexMap[$entryId]),
            "summary_line" => (string) ($summaryKeyMap[$entryId] ?? ""),
        ];
    }

    echo json_encode(array_merge([
        "success" => true,
        "columns" => $columns,
        "records" => $records,
        "count" => count($records),
        "selectable" => true,
        "notes" => [],
    ], $extra));
};

/** Entry IDs that need a user-provided USD forex rate for their trip date. */
$manualForexEntryIds = static function (array $entries, array $customer) use ($conn, $customerKey): array {
    if (strtoupper((string) ($customer["document_currency"] ?? "PHP")) !== "USD") {
        return [];
    }
    $ids = [];
    foreach ($entries as $entry) {
        $tripDate = substr((string) ($entry["trip_date"] ?? ""), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tripDate) || billing_forex_rate($conn, $tripDate, $customerKey) <= 0) {
            $ids[] = (int) ($entry["entry_id"] ?? 0);
        }
    }
    return array_values(array_filter(array_unique($ids)));
};

/**
 * Statement-level currency + forex for the rate-summary footer, resolved the same
 * way the generated invoice/PDF do (single rate at $dateTo for USD customers). The
 * preview page uses this to print the "Total charges in Peso / FOREX / Total in
 * Dollar" totals under the Rate summary table.
 */
$summaryForex = static function (array $customer) use ($conn, $customerKey, $dateTo): array {
    $currency = strtoupper((string) ($customer["document_currency"] ?? "PHP"));
    $forex = $currency === "USD" ? billing_forex_rate($conn, $dateTo, $customerKey) : 1.0;
    if ($forex <= 0) {
        $forex = 1.0;
    }
    return ["currency" => $currency, "forex" => $forex, "show_usd" => $currency === "USD"];
};

/**
 * Rate-summary lane label as Origin → Packing House → Destination, taken from the
 * customer config. Blank when no route parts are set — the caller then falls back
 * to its "Flat rate (CODE)" label. Matrix/Sumifru build their route per lane row;
 * this covers the flat-rate customers so every Rate summary reads the same way.
 */
$routeLabel = static function (array $customer): string {
    $parts = array_filter([
        trim((string) ($customer["origin"] ?? "")),
        trim((string) ($customer["packing_station"] ?? "")),
        trim((string) ($customer["port_of_loading"] ?? "")),
    ]);
    return implode(" → ", $parts);
};

/**
 * ---- RAW layout view -------------------------------------------------------
 * The RAW COLUMNS over the SAP pipeline's OWN records: one preview row per line
 * the SAP download will bill, so the two always agree.
 *
 * The entry set is fetched exactly as the matrix/sap branches below do (so a preview
 * row == an invoice line), and only the DISPLAY is RAW-shaped via
 * raw_billing_fetch_rows_by_ids(), keyed by those entry_ids. Do NOT re-introduce a
 * separate RAW query here: an earlier version did, and its different date/segment/
 * ledger filters made the preview disagree with the invoice (40 previewed vs 20 billed).
 */
if (trim((string) ($_GET["view"] ?? "")) === "raw" && $activityCode === "hauling") {
    if (unified_billing_raw_match($customerKey) === null) {
        // The layout is reefer-specific (genset, EIR, van alpha/number), so it is
        // blank for the OTHERS ENTRY / DPC_KDs customers.
        echo json_encode(["success" => false, "message" => "The RAW layout covers the reefer (RV) customers only."]);
        exit();
    }
    if (!$validRange($dateFrom, $dateTo)) {
        echo json_encode(["success" => false, "message" => "Invalid date range."]);
        exit();
    }

    // Same exclusion the SAP download applies, so previewed rows == billed lines.
    $rawExclude = [];
    if (!$includeBilled) {
        $rawExclude = array_map("intval", $conn->query(
            "SELECT DISTINCT entry_id FROM billing_invoice_entries"
        )->fetchAll(PDO::FETCH_COLUMN));
    }

    $rawCustomer = unified_billing_pipeline($customerKey) === "matrix"
        ? box_banana_customer($customerKey)
        : billing_customer($customerKey);

    try {
        $sapEntries = box_banana_fetch_entries($conn, $rawCustomer, $dateFrom, $dateTo, $rawExclude);
        $rawRows = raw_billing_fetch_rows_by_ids(
            $conn,
            array_map(static fn(array $e): int => (int) $e["entry_id"], $sapEntries)
        );
    } catch (Throwable $e) {
        echo json_encode(["success" => false, "message" => "Preview failed: " . $e->getMessage()]);
        exit();
    }

    $rawDisplay = raw_billing_display_rows($rawRows);

    $rawNotes = [];
    if (count($rawDisplay["rows"]) > 0) {
        $rawNotes[] = "RAW record detail — one row per line the SAP download will bill. Untick a row to leave it out of the invoice.";
        $rawNotes[] = "START2, END3, RUNTIME HR:MN, TIME, FUEL, VAN VOLUME and DOLLAR CONVERTION have no source field yet — they are blank in the RAW export too.";
        $rawNotes[] = "RATE CHARGES stays in PHP and is not dollar converted; DOLLAR CONVERTION is shown separately for reference.";
    }

    // Rate-usage summary for the RAW view too — what matrix rate each group of trips billed
    // at (same helpers as the detail views), so it shows above the RAW table.
    $rawRateSummary = ["lines" => [], "unmatched" => 0, "fuel_source" => ""];
    try {
        if ($pipeline === "matrix") {
            $rawFlat = box_banana_rate($conn, (string) ($rawCustomer["rate_code"] ?? ""));
            $rawRateSummary = box_banana_rate_summary($conn, $sapEntries, $rawCustomer, $customerKey, $rawFlat);
        } elseif (($rawCustomer["pricing"] ?? "") === "lane_tier") {
            $rawRateSummary = sumifru_rate_summary($conn, $sapEntries);
        } else {
            $rawFlat = billing_customer_rate($conn, (string) ($rawCustomer["rate_code"] ?? ""));
            if ($rawFlat > 0 && count($sapEntries) > 0) {
                $rawRateSummary["lines"][] = [
                    "key" => "flat",
                    "lane" => $routeLabel($rawCustomer) !== "" ? $routeLabel($rawCustomer) : ("Flat rate" . (($rawCustomer["rate_code"] ?? "") !== "" ? " (" . $rawCustomer["rate_code"] . ")" : "")),
                    "dcode" => "", "base_rate" => $rawFlat, "pump_price" => null, "price_movement" => null,
                    "fuel_price" => null, "rate" => $rawFlat,
                    "trips" => count($sapEntries), "subtotal" => round($rawFlat * count($sapEntries), 2),
                ];
                $rawRateSummary["entry_keys"] = array_fill_keys(
                    array_map(static fn(array $e): int => (int) ($e["entry_id"] ?? 0), $sapEntries),
                    "flat"
                );
            }
        }
    } catch (Throwable $e) {
        // best-effort — a summary failure must not break the preview
    }

    // Per-lane equipment-rental charges (Chassis / Genset / Container Van / Fuel), shown as
    // extra amount columns in the Rate summary — only for matrix customers that rent
    // equipment. Same trips as above, attributed to lanes by the summary's entry_keys.
    $rawRental = preview_equipment_rental_by_lane(
        $conn,
        $pipeline,
        $rawCustomer,
        $customerKey,
        array_map(static fn(array $e): int => (int) ($e["entry_id"] ?? 0), $sapEntries),
        $dateTo,
        $rawRateSummary["entry_keys"] ?? []
    );
    if (!empty($rawRental["activities"])) {
        $rawRateSummary["rental_activities"] = $rawRental["activities"];
        $rawRateSummary["rental_by_lane"] = $rawRental["by_lane"];
        $rawRateSummary["rental_totals"] = $rawRental["totals"];
        $rawRateSummary["rental_consumption_by_lane"] = $rawRental["consumption_by_lane"];
        $rawRateSummary["rental_consumption_totals"] = $rawRental["consumption_totals"];
    }

    // Each row IS one SAP line (rows are the SAP entry set in billing order), so the
    // selection maps straight to the download — untick to exclude, drag to reorder.
    $respond(raw_billing_preview_columns(), $rawDisplay["rows"], $rawDisplay["entry_ids"], [
        "view" => "raw",
        "total" => $rawDisplay["total"],
        "notes" => $rawNotes,
        "rate_summary" => array_merge($rawRateSummary, $summaryForex($rawCustomer)),
        "summary_key_by_entry" => $rawRateSummary["entry_keys"] ?? [],
        "manual_forex_entry_ids" => $manualForexEntryIds($sapEntries, $rawCustomer),
    ]);
    exit();
}

// ---- ABC KDs: one statement line per trip, rate from this customer's KDs matrix ----
if ($pipeline === "kds") {
    if (!$validRange($dateFrom, $dateTo)) {
        echo json_encode(["success" => false, "message" => "Invalid date range."]);
        exit();
    }
    $kds = abc_kds_customer($customerKey);

    $excludeEntryIds = [];
    if (!$includeBilled) {
        $excludeEntryIds = array_map("intval", $conn->query(
            "SELECT DISTINCT entry_id FROM billing_invoice_entries"
        )->fetchAll(PDO::FETCH_COLUMN));
    }

    try {
        $entries = abc_kds_fetch($conn, $kds, $dateFrom, $dateTo, $excludeEntryIds);
        $detail = abc_kds_detail_rows($conn, $entries, $kds);
    } catch (Throwable $e) {
        echo json_encode(["success" => false, "message" => "Preview failed: " . $e->getMessage()]);
        exit();
    }

    // Be explicit about why nothing priced, instead of an empty table.
    $notes = [];
    if (!abc_kds_has_source_matrix($conn, $kds)) {
        $notes[] = "No box-banana rate matrix is loaded for the KDs source ('"
            . ($kds["box_banana_key"] ?? "") . "') — KDs prices at that lane's rate minus "
            . number_format((float) ($kds["kds_discount"] ?? 0)) . ". Add it in Master Data → Rate Matrix.";
    }
    if ($missing = abc_kds_missing_config($kds)) {
        $notes[] = "Missing SAP config for " . $kds["label"] . ": " . implode(" and ", $missing) . ".";
    }
    $unpriced = count($entries) - count($detail["rows"]);
    if (count($entries) === 0) {
        $notes[] = "No KD trips found for this date range (billing_sku '" . $kds["billing_sku"] . "').";
    } elseif ($unpriced > 0) {
        $notes[] = $unpriced . " trip(s) could not be priced.";
    }

    if (count($detail["rows"]) === 0) {
        echo json_encode([
            "success" => false,
            "message" => implode(" ", $notes ?: ["Nothing to bill for this range."]),
            "notes" => $notes,
            "trips_found" => count($entries),
        ]);
        exit();
    }

    $respond(abc_kds_pdf_columns(), $detail["rows"], $detail["entry_ids"], [
        "total" => $detail["total"],
        "trips_found" => count($entries),
        "unpriced" => $unpriced,
        "notes" => $notes,
    ]);
    exit();
}

// ---- Dry Vans: one statement line per trip, flat rate per trip ----
if ($pipeline === "dryvan") {
    if (!$validRange($dateFrom, $dateTo)) {
        echo json_encode(["success" => false, "message" => "Invalid date range."]);
        exit();
    }
    $dv = billing_customer_apply_sap_overrides($conn, $customerKey, dry_van_customer($customerKey) ?? []);
    $rate = dry_van_rate($conn, $dv);

    $excludeEntryIds = [];
    if (!$includeBilled) {
        $excludeEntryIds = array_map("intval", $conn->query(
            "SELECT DISTINCT entry_id FROM billing_invoice_entries"
        )->fetchAll(PDO::FETCH_COLUMN));
    }

    try {
        $entries = dry_van_fetch($conn, $dv, $dateFrom, $dateTo, $excludeEntryIds);
        $rateByEntry = dry_van_rates_by_entry($conn, $dv, $entries, $customerKey);
        $detail = dry_van_detail_rows($entries, $dv, $rate, $rateByEntry);
    } catch (Throwable $e) {
        echo json_encode(["success" => false, "message" => "Preview failed: " . $e->getMessage()]);
        exit();
    }

    $notes = [];
    if (dry_van_is_matrix($dv)) {
        $unpriced = count(array_filter($rateByEntry, static fn($r) => (float) $r <= 0));
        if ($unpriced > 0) {
            $notes[] = $unpriced . " of " . count($entries) . " trip(s) matched no Rate Matrix lane (priced 0 / skipped) — check "
                . $dv["label"] . "'s lanes (Port Pull-Out / Warehouse / Return Empty) in Master Data → Rate Matrix.";
        }
    } elseif ($rate <= 0) {
        $notes[] = "No flat rate set for " . $dv["label"] . " — add rate code '" . ($dv["rate_code"] ?? "") . "' in Master Data → Rates.";
    }
    if ($missing = dry_van_missing_config($dv)) {
        $notes[] = "Missing SAP config for " . $dv["label"] . ": " . implode(" and ", $missing) . ".";
    }
    if (count($entries) === 0) {
        $notes[] = "No dry van trips found for this date range (customer '" . $dv["customer_match"] . "').";
    }

    if (count($detail["rows"]) === 0) {
        echo json_encode([
            "success" => false,
            "message" => implode(" ", $notes ?: ["Nothing to bill for this range."]),
            "notes" => $notes,
            "trips_found" => count($entries),
        ]);
        exit();
    }

    $rateSummary = dry_van_rate_summary($conn, $entries, $dv, $customerKey);

    $respond(dry_van_pdf_columns(), $detail["rows"], $detail["entry_ids"], [
        "total" => $detail["total"],
        "trips_found" => count($entries),
        "notes" => $notes,
        "rate_summary" => array_merge($rateSummary, $summaryForex($dv)),
        "summary_key_by_entry" => $rateSummary["entry_keys"] ?? [],
    ]);
    exit();
}

// ---- DICT Van Shuttling: per-trip statement detail for the selected lane ----
if ($pipeline === "shuttling") {
    if (!$validRange($dateFrom, $dateTo)) {
        echo json_encode(["success" => false, "message" => "Invalid date range."]);
        exit();
    }
    $laneKey = trim((string) ($_GET["lane"] ?? ""));
    $laneOptions = dict_shuttling_lane_options();
    if ($laneKey === "" || !isset($laneOptions[$laneKey])) {
        echo json_encode([
            "success" => false,
            "message" => "Select a shuttling lane.",
            "lanes" => $laneOptions,
        ]);
        exit();
    }

    $excludeEntryIds = [];
    if (!$includeBilled) {
        $stmt = $conn->prepare(
            "SELECT DISTINCT e.entry_id FROM billing_invoice_entries e
             JOIN billing_invoices i ON i.invoice_id = e.invoice_id
             WHERE i.customer_key = ? AND i.activity = ? AND i.status <> 'deleted'"
        );
        $stmt->execute([$customerKey, $laneKey]);
        $excludeEntryIds = array_map("intval", $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    try {
        $entries = dict_shuttling_fetch($conn, $dateFrom, $dateTo, $excludeEntryIds);
        $detail = dict_shuttling_detail_rows($entries, $laneKey, $conn, $dateTo);
    } catch (Throwable $e) {
        echo json_encode(["success" => false, "message" => "Preview failed: " . $e->getMessage()]);
        exit();
    }

    // The rows below are per trip, but each SAP line aggregates every trip of a
    // (truck, trailer) pair — so individual rows cannot be excluded and generate
    // ignores any selection.
    $respond(dict_shuttling_pdf_columns(), $detail["rows"], $detail["entry_ids"], [
        "lanes" => $laneOptions,
        "lane" => $laneKey,
        "lane_label" => $detail["lane"]["label"],
        "total" => $detail["total"],
        "selectable" => false,
        "notes" => ["Each SAP line groups this lane's trips by truck + trailer, so rows cannot be excluded individually."],
    ]);
    exit();
}

// ---- DICT Industrial Waste / Garbage: ONE combined statement (both routes). ----
if ($pipeline === "industrial_waste") {
    if (!$validRange($dateFrom, $dateTo)) {
        echo json_encode(["success" => false, "message" => "Invalid date range."]);
        exit();
    }

    $excludeEntryIds = [];
    if (!$includeBilled) {
        // One combined billing per period, so exclude trips already in ANY garbage invoice.
        $stmt = $conn->prepare(
            "SELECT DISTINCT e.entry_id FROM billing_invoice_entries e
             JOIN billing_invoices i ON i.invoice_id = e.invoice_id
             WHERE i.customer_key = ? AND i.status <> 'deleted'"
        );
        $stmt->execute([$customerKey]);
        $excludeEntryIds = array_map("intval", $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    try {
        $entries = industrial_waste_fetch($conn, $dateFrom, $dateTo, $excludeEntryIds);
        $detail = industrial_waste_detail_rows($entries, $conn, $dateTo);
    } catch (Throwable $e) {
        echo json_encode(["success" => false, "message" => "Preview failed: " . $e->getMessage()]);
        exit();
    }

    $respond(industrial_waste_pdf_columns(), $detail["rows"], $detail["entry_ids"], [
        "total" => $detail["total"],
        "selectable" => false,
        "notes" => [
            "Both routes (Behind / Waterfall) bill on one statement; the SAP lines group each route's trips by truck + trailer, so rows cannot be excluded individually.",
            "A 12% VAT line is added on the PDF statement (the SAP amounts stay VAT-exclusive).",
        ],
    ]);
    exit();
}

$customer = $pipeline === "matrix" ? box_banana_customer($customerKey) : billing_customer($customerKey);
// Apply the customer's SAP overrides (Distribution Channel/Material/Profit Center/Sold-To)
// so the activity build matches the hauling statement and the generated SAP file.
if (is_array($customer) && function_exists("billing_customer_apply_sap_overrides")) {
    $customer = billing_customer_apply_sap_overrides($conn, $customerKey, $customer);
}

// ---- Non-hauling activity preview (chassis / genset / container_van / fuel) ----
if ($activityCode !== "hauling" && billing_activity($activityCode) !== null) {
    if ($pipeline !== "matrix") {
        echo json_encode(["success" => false, "message" => "Activity billing is available for the breakbulk (matrix) customers. Sumifru uses Hauling only for now."]);
        exit();
    }
    if (!$validRange($dateFrom, $dateTo)) {
        echo json_encode(["success" => false, "message" => "Invalid date range."]);
        exit();
    }

    $excludeEntryIds = [];
    if (!$includeBilled) {
        $excludeEntryIds = array_map("intval", $conn->query(
            "SELECT DISTINCT entry_id FROM billing_invoice_entries e JOIN billing_invoices i ON i.invoice_id = e.invoice_id WHERE i.activity = " . $conn->quote($activityCode)
        )->fetchAll(PDO::FETCH_COLUMN));
    }

    try {
        $entries = activity_fetch_entries($conn, $customer, $dateFrom, $dateTo, $excludeEntryIds);
    } catch (Throwable $e) {
        echo json_encode(["success" => false, "message" => "Preview failed: " . $e->getMessage()]);
        exit();
    }

    $currency = strtoupper((string) ($customer["document_currency"] ?? "PHP"));
    $forex = $currency === "USD" ? billing_forex_rate($conn, $dateTo, $customerKey) : 1.0;
    if ($forex <= 0) {
        $forex = 1.0;
    }
    $built = activity_build($conn, $entries, $customer, $customerKey, $activityCode, $forex, "preview", 0.0);

    $respond($built["columns"], $built["detail_rows"], $built["entry_ids"], [
        "activity" => $activityCode,
        "total" => $built["total"],
        "priced" => $built["priced"],
    ]);
    exit();
}

if (!$validRange($dateFrom, $dateTo)) {
    echo json_encode(["success" => false, "message" => "Invalid date range."]);
    exit();
}

$excludeEntryIds = [];
if (!$includeBilled) {
    $excludeEntryIds = array_map(
        "intval",
        $conn->query(
            "SELECT DISTINCT e.entry_id
             FROM billing_invoice_entries e
             JOIN billing_invoices i ON i.invoice_id = e.invoice_id
             WHERE i.status <> 'deleted' AND i.returned_at IS NULL"
        )->fetchAll(PDO::FETCH_COLUMN)
    );
}

// ---- Matrix pipeline (ABC/TDC breakbulk customers) ----
if ($pipeline === "matrix") {
    try {
        $entries = box_banana_fetch_entries($conn, $customer, $dateFrom, $dateTo, $excludeEntryIds);
    } catch (Throwable $e) {
        echo json_encode(["success" => false, "message" => "Preview failed: " . $e->getMessage()]);
        exit();
    }

    $flatRate = box_banana_rate($conn, (string) ($customer["rate_code"] ?? ""));
    $detail = box_banana_detail_rows($conn, $entries, $customer, $customerKey, $flatRate);

    $notes = [];
    if (!$detail["priced_via_matrix"] && $flatRate <= 0 && count($detail["rows"]) > 0) {
        $notes[] = "No rate matrix and no flat rate for this customer — every line prices 0.";
    }

    $matrixSummary = array_merge(box_banana_rate_summary($conn, $entries, $customer, $customerKey, $flatRate), $summaryForex($customer));

    // Per-lane equipment-rental charges (Chassis / Genset / Container Van / Fuel) for
    // customers that rent equipment — extra amount columns beside each lane's Subtotal.
    $matrixRental = preview_equipment_rental_by_lane($conn, $pipeline, $customer, $customerKey, $detail["entry_ids"], $dateTo, $matrixSummary["entry_keys"] ?? []);
    if (!empty($matrixRental["activities"])) {
        $matrixSummary["rental_activities"] = $matrixRental["activities"];
        $matrixSummary["rental_by_lane"] = $matrixRental["by_lane"];
        $matrixSummary["rental_totals"] = $matrixRental["totals"];
        $matrixSummary["rental_consumption_by_lane"] = $matrixRental["consumption_by_lane"];
        $matrixSummary["rental_consumption_totals"] = $matrixRental["consumption_totals"];
    }

    $respond($detail["columns"], $detail["rows"], $detail["entry_ids"], [
        "total" => $detail["total"],
        "priced" => $detail["priced_via_matrix"] || $flatRate > 0,
        "notes" => $notes,
        "rate_summary" => $matrixSummary,
        "summary_key_by_entry" => $matrixSummary["entry_keys"] ?? [],
        "manual_forex_entry_ids" => $manualForexEntryIds($entries, $customer),
    ]);
    exit();
}

// ---- SAP pipeline (Sumifru) — its own reefer statement detail ----
try {
    $entries = box_banana_fetch_entries($conn, $customer, $dateFrom, $dateTo, $excludeEntryIds);
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Preview failed: " . $e->getMessage()]);
    exit();
}

// Sumifru prices per lane + fuel tier; any other SAP customer uses its flat rate_code.
$rateSummary = null;
if (($customer["pricing"] ?? "") === "lane_tier") {
    $resolver = static function (array $e, string $tripDate, string $dcode) use ($conn): float {
        return sumifru_resolve_rate($conn, $e, $tripDate)["rate"];
    };
    $flatRate = 0.0;
    $rateSummary = sumifru_rate_summary($conn, $entries);
} else {
    $resolver = null;
    $flatRate = billing_customer_rate($conn, (string) ($customer["rate_code"] ?? ""));
    if ($flatRate <= 0) {
        echo json_encode(["success" => false, "message" => "No rate set for rate code '" . $customer["rate_code"] . "'. Add it in Master Data -> Rates."]);
        exit();
    }
    // Flat-rate SAP customer: a single summary line (rate code x every trip).
    $rateSummary = [
        "lines" => count($entries) > 0 ? [[
            "key" => "flat",
            "lane" => $routeLabel($customer) !== "" ? $routeLabel($customer) : ("Flat rate" . (($customer["rate_code"] ?? "") !== "" ? " (" . $customer["rate_code"] . ")" : "")),
            "dcode" => "",
            "base_rate" => $flatRate,
            "pump_price" => null,
            "price_movement" => null,
            "fuel_price" => null,
            "rate" => $flatRate,
            "trips" => count($entries),
            "subtotal" => round($flatRate * count($entries), 2),
        ]] : [],
        "unmatched" => 0,
        "fuel_source" => "",
        "entry_keys" => array_fill_keys(
            array_map(static fn(array $e): int => (int) ($e["entry_id"] ?? 0), $entries),
            "flat"
        ),
    ];
}

$ratesByEntryId = [];
$total = 0.0;
foreach ($entries as $e) {
    $tripDate = billing_norm($e["trip_date"] ?? "");
    $dcode = box_banana_first_non_empty($e["destination"] ?? "", $e["delivered_to"] ?? "");
    $rate = $resolver !== null ? (float) $resolver($e, $tripDate, $dcode) : $flatRate;
    $ratesByEntryId[(int) $e["entry_id"]] = $rate;
    $total += $rate;
}

$detail = billing_pdf_detail_rows($entries, $customer, $flatRate, $ratesByEntryId);

$notes = [];
if (count($detail["rows"]) > 0 && $total <= 0) {
    $notes[] = "Every trip priced 0 — check the rate matrix / fuel period tier for this customer.";
}

$respond(billing_pdf_columns(), $detail["rows"], $detail["entry_ids"], [
    "total" => round($total, 2),
    "notes" => $notes,
    "rate_summary" => array_merge($rateSummary, $summaryForex($customer)),
    "summary_key_by_entry" => $rateSummary["entry_keys"] ?? [],
    "manual_forex_entry_ids" => $manualForexEntryIds($entries, $customer),
]);
exit();
?>
