<?php

require_once __DIR__ . "/build_customer_billing.php";
require_once __DIR__ . "/dict_shuttling.php"; // dict_route_rate(), dict_shuttling_trips(), dict_shuttling_pdf_columns()

/**
 * DICT Industrial Waste / Garbage hauling — SAP ZPSO billing.
 *
 * Source: `operations` rows whose billing_sku is the "Industrial Waste/Garbage" series
 * for DICT (entry_type OTHERS ENTRY, ph DICT). Flat, per-trip pricing by DESTINATION
 * (BEHIND / WATERFALL) — no fuel escalation. One SAP line per (truck, trailer) with
 * Order Quantity = summed trips × the route's flat rate (like DICT Van Shuttling), plus
 * a 12% VAT line on the PANABO PDF statement (PDF-only; SAP amounts stay VAT-exclusive).
 *
 * Rates are editable in the Rate Matrix (customer_key = dict_industrial_waste, dcode =
 * lane key, base_rate = flat price); the config prices below are the fallback defaults.
 *
 * NOTE: the SAP header codes (sold_to / material_code / profit_center) and per-route SAP
 * numbers are PLACEHOLDERS pending finance — set them in Master Data → Customer SAP Codes
 * / SKU Routes. The PDF (rates + VAT) is correct regardless.
 */
function industrial_waste_config(): array
{
    return [
        "key" => "dict_industrial_waste",
        "label" => "DICT Industrial Waste",
        "billing_sku_match" => "Industrial Waste",
        "customer_match" => "DICT",
        // SAP header (placeholders pending finance)
        "order_type" => "ZPSO",
        "sales_org" => "3200",
        "distribution_channel" => "30",
        "division" => "31",
        "sold_to" => "IC3100",
        "customer_tax_class" => "1",
        "document_currency" => "PHP",
        "billed_services" => "Hauling of Industrial Waste / Garbage",
        "material_code" => "FS00000006",
        "sales_unit" => "TRP",
        "condition_type" => "PR00",
        "condition_unit" => "1",
        "profit_center" => "3200070010",
        // PANABO statement (PDF) header
        "charge_to" => "DAVAO INTERNATIONAL CONTAINER TERMINAL, INC.",
        "pdf_activity" => "HAULING OF GARBAGE",
        // ONE combined statement for both routes; the DELIVERY AREA column distinguishes them.
        "pdf_destination" => "DICT TO BEHIND THE CLOUDS|WATERFALL",
        // 12% VAT shown on the PDF statement only (SAP amounts stay VAT-exclusive).
        "is_vat" => true,
        // Routes carry the flat rate + the DELIVERY AREA label shown on the statement.
        "lanes" => [
            "behind"    => ["label" => "BEHIND",    "area" => "Behind",    "route" => "", "price" => 8000.0],
            "waterfall" => ["label" => "WATERFALL", "area" => "Waterfall", "route" => "", "price" => 8500.0],
        ],
    ];
}

/** PANABO statement columns — combined both routes, with a DELIVERY AREA column. */
function industrial_waste_pdf_columns(): array
{
    return [
        "no" => "NO.",
        "date" => "DATE",
        "trip_receipt" => "TRIP RECEIPT",
        "truck" => "TRUCK",
        "delivery_area" => "DELIVERY AREA",
        "trips" => "TRIPS",
        "rate" => "RATE",
        "amount" => "AMOUNT",
    ];
}

function industrial_waste_lane_options(): array
{
    $out = [];
    foreach (industrial_waste_config()["lanes"] as $key => $lane) {
        $out[$key] = $lane["label"];
    }
    return $out;
}

/**
 * Which route a garbage-hauling row belongs to, by its delivered-to destination:
 *   ... BEHIND ...    -> behind
 *   ... WATERFALL ... -> waterfall
 * anything else -> null (not billable — no matching route on the rate sheet).
 */
function industrial_waste_lane(array $entry): ?string
{
    $to = strtoupper(billing_norm($entry["delivered_to"] ?? ""));
    if ($to === "") {
        return null;
    }
    if (strpos($to, "BEHIND") !== false) {
        return "behind";
    }
    if (strpos($to, "WATERFALL") !== false) {
        return "waterfall";
    }
    return null;
}

/** Fetch the industrial-waste rows for a date range (by waybill_date). */
function industrial_waste_fetch(PDO $conn, string $dateFrom, string $dateTo, array $excludeEntryIds = []): array
{
    if (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ||
        $dateFrom > $dateTo
    ) {
        throw new RuntimeException("Invalid date range.");
    }
    $cfg = industrial_waste_config();

    $params = ["%" . $cfg["billing_sku_match"] . "%", $cfg["customer_match"], $dateFrom, $dateTo];
    $where = "billing_sku ILIKE ?
              AND UPPER(COALESCE(NULLIF(customer_ph,''), NULLIF(ph,''), NULLIF(operations_ph,''), '')) = ?
              AND NULLIF(waybill_date::text,'')::date BETWEEN ? AND ?";

    $excludeIds = array_values(array_unique(array_filter(array_map("intval", $excludeEntryIds), fn($v) => $v > 0)));
    if (!empty($excludeIds)) {
        $where .= " AND entry_id NOT IN (" . implode(",", array_fill(0, count($excludeIds), "?")) . ")";
        foreach ($excludeIds as $id) {
            $params[] = $id;
        }
    }

    $stmt = $conn->prepare(
        "SELECT entry_id, waybill, waybill_date::text AS trip_date, truck, tr, driver,
                load_quantity_weight, unit_of_measure, deliver_from, delivered_to, remarks, billing_sku
         FROM operations
         WHERE $where
         ORDER BY truck ASC, tr ASC, NULLIF(waybill_date::text,'')::date ASC, entry_id ASC"
    );
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Fetch industrial-waste rows for a specific set of entry ids (for the PDF rebuild). */
function industrial_waste_fetch_by_ids(PDO $conn, array $entryIds): array
{
    return dict_shuttling_fetch_by_ids($conn, $entryIds); // same columns/query
}

/**
 * PANABO statement rows — ONE combined statement covering BOTH routes, per trip (one line
 * per trip receipt) with a DELIVERY AREA (Behind / Waterfall) column and a running number.
 * Returns ['rows','entry_ids','total'].
 */
function industrial_waste_detail_rows(array $entries, ?PDO $conn = null, string $rateDate = ""): array
{
    $cfg = industrial_waste_config();
    $rows = [];
    $entryIds = [];
    $total = 0.0;
    $no = 0;

    foreach ($entries as $e) {
        $laneKey = industrial_waste_lane($e);
        if ($laneKey === null || !isset($cfg["lanes"][$laneKey])) {
            continue;
        }
        $lane = $cfg["lanes"][$laneKey];
        $qty = dict_shuttling_trips($e);
        if ($qty <= 0) {
            continue;
        }
        $price = dict_route_rate($conn, $cfg["key"], $laneKey, (float) $lane["price"], $rateDate);
        $amount = round($qty * $price, 2);
        $total += $amount;
        $entryIds[] = (int) $e["entry_id"];
        $no++;

        $date = substr(billing_norm($e["trip_date"] ?? ""), 0, 10);
        $ts = $date !== "" ? strtotime($date) : false;

        $rows[] = [
            "no" => $no . ".]",
            "date" => $ts ? date("m/d/Y", $ts) : "",
            "trip_receipt" => billing_norm($e["waybill"] ?? ""),
            "truck" => billing_digits($e["truck"] ?? ""),
            "delivery_area" => $lane["area"],
            "trips" => rtrim(rtrim(number_format($qty, 2, ".", ","), "0"), "."),
            "rate" => number_format($price, 2, ".", ","),
            "amount" => number_format($amount, 2, ".", ","),
        ];
    }

    return [
        "rows" => $rows,
        "entry_ids" => $entryIds,
        "total" => round($total, 2),
    ];
}

/**
 * Build the SAP rows — ONE combined file covering BOTH routes, one line per
 * (route, truck, trailer) with the summed trip count (price differs by route, so the
 * route is part of the grouping key). PHP, no forex. Returns
 * ['rows','entry_ids','total','reference'].
 */
function industrial_waste_build(array $entries, ?string $reference = null, ?array $cfgOverride = null, ?PDO $conn = null, string $rateDate = ""): array
{
    $cfg = $cfgOverride ?? industrial_waste_config();
    $reference = $reference !== null && trim($reference) !== "" ? trim($reference) : $cfg["label"];

    $docSerial = billing_document_serial();

    $groups = [];
    foreach ($entries as $e) {
        $laneKey = industrial_waste_lane($e);
        if ($laneKey === null || !isset($cfg["lanes"][$laneKey])) {
            continue;
        }
        $trips = dict_shuttling_trips($e);
        if ($trips <= 0) {
            continue;
        }
        $truck = billing_digits($e["truck"] ?? "");
        $tr = billing_trailer_label($e["tr"] ?? "");
        $key = $laneKey . "|" . $truck . "|" . $tr;
        if (!isset($groups[$key])) {
            $groups[$key] = ["lane" => $laneKey, "truck" => $truck, "tr" => $tr, "trips" => 0.0, "ids" => [], "sku" => (string) ($e["billing_sku"] ?? "")];
        }
        $groups[$key]["trips"] += $trips;
        $groups[$key]["ids"][] = (int) $e["entry_id"];
    }

    $rows = [];
    $entryIds = [];
    $item = 0;
    $total = 0.0;

    foreach ($groups as $g) {
        $lane = $cfg["lanes"][$g["lane"]];
        $price = dict_route_rate($conn, $cfg["key"], $g["lane"], (float) $lane["price"], $rateDate);
        $item += 10;
        $qty = round($g["trips"], 2);
        $amount = round($qty * $price, 2);
        $total += $amount;
        foreach ($g["ids"] as $id) {
            $entryIds[] = $id;
        }
        $rows[] = [
            ["t", $cfg["order_type"]],
            ["t", $cfg["sales_org"]],
            ["t", $cfg["distribution_channel"]],
            ["t", $cfg["division"]],
            ["dt", $docSerial],
            ["dt", $docSerial],
            ["t", $cfg["sold_to"]],
            ["t", $cfg["customer_tax_class"]],
            ["t", $cfg["document_currency"]],
            ["t", $reference],
            ["t", ""],                          // Vessel Visit
            ["t", $cfg["billed_services"]],
            ["n", $item],
            ["t", $cfg["material_code"]],
            ["n", $qty],                        // Order Quantity = summed trips
            ["t", $cfg["sales_unit"]],
            ["t", ""],                          // Alternate Quantity
            ["t", $cfg["condition_type"]],
            ["n", round($price, 2)],            // Price = route rate
            ["n", billing_sap_condition_unit($cfg["document_currency"] ?? "PHP")],
            ["t", $cfg["document_currency"]],
            ["t", ""],                          // Forex Rate (PHP billing)
            ["t", $cfg["profit_center"]],
            ["n3", billing_sap_three_digits($conn !== null ? equipment_sap_code($conn, "PM", (string) $g["truck"]) : $g["truck"])],
            ["t", ""],                          // Activity
            ["n3", billing_sap_three_digits(($conn !== null ? sku_route_sap_no($conn, (string) ($g["sku"] ?? "")) : "") ?: $lane["route"])],
            ["n3", billing_sap_three_digits($conn !== null ? equipment_sap_code($conn, "TR", (string) $g["tr"]) : $g["tr"])],
            ["t", ""],                          // RV
            ["t", ""],                          // GS
            ["t", ""],                          // AD
        ];
    }

    return [
        "rows" => $rows,
        "entry_ids" => array_values(array_unique($entryIds)),
        "total" => round($total, 2),
        "reference" => $reference,
    ];
}
