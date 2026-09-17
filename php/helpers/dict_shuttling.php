<?php

require_once __DIR__ . "/build_customer_billing.php";

/**
 * DICT Van Shuttling ("Shuttling Empty Vans") SAP ZPSO billing.
 *
 * Source data: `operations` rows whose billing_sku is the Container Hustling
 * series for DICT (entry_type OTHERS ENTRY). Each row records a truck + trailer
 * and a TRIP COUNT (load_quantity_weight, uom TRIPS) delivered to a destination.
 *
 * Output: the same 30-column SAP layout as the other customers
 * (billing_column_labels / billing_write_xlsx), but one line per
 * (truck, trailer) pair with:
 *   Order Quantity = summed trips,  Price = the lane rate,  Amount = qty x price.
 * There is no forex (PHP billing) and no Alternate Quantity — matching the
 * "5DICT Van Shuttling (<LANE>).xlsx" templates.
 *
 * Each lane is its own statement/file (own Reference, Route and Price).
 */
function dict_shuttling_config(): array
{
    return [
        "key" => "dict_van_shuttling",
        "label" => "DICT Van Shuttling",
        // Trip selection
        "billing_sku_match" => "Hustling",
        "customer_match" => "DICT",
        // SAP header (identical across all four lane templates)
        "order_type" => "ZPSO",
        "sales_org" => "3200",
        "distribution_channel" => "30",
        "division" => "31",
        "sold_to" => "IC3100",
        "customer_tax_class" => "1",
        "document_currency" => "PHP",
        "billed_services" => "Shuttling Empty Vans",
        "material_code" => "FS00000006",
        "sales_unit" => "TRP",
        "condition_type" => "PR00",
        "condition_unit" => "1",
        "profit_center" => "3200070010",
        // PANABO statement (PDF) header — see the "06. DICT 2026.xlsx" D1/ED1/
        // EE1/DD1 sheets, which are the printed statement per lane.
        "charge_to" => "DAVAO INTERNATIONAL CONTAINER TERMINAL, INC.",
        "pdf_activity" => "SHUTTLING OF EMPTY CONTAINER VANS",
        // Lanes: each generates its own file. `pdf_destination` is the wording the
        // statement uses for that lane.
        "lanes" => [
            "ecd_ecd"   => ["label" => "ECD TO ECD",   "route" => "597", "price" => 350.0,  "pdf_destination" => "ECD to ECD"],
            "dict_xray" => ["label" => "DICT TO XRAY", "route" => "598", "price" => 1000.0, "pdf_destination" => "DICT to X-RAY"],
            "ecd_dict"  => ["label" => "ECD TO DICT",  "route" => "599", "price" => 500.0,  "pdf_destination" => "ECD - DICT"],
            "dict_ecd"  => ["label" => "DICT TO ECD",  "route" => "600", "price" => 500.0,  "pdf_destination" => "DICT - ECD"],
        ],
    ];
}

/**
 * Editable flat rate for a route (lane), from the Rate Matrix. Finance can add/update a
 * `rate_lane` row per route (customer_key = the service key, dcode = the lane key, base_rate
 * = the flat per-trip price, no fuel columns → charged flat). Falls back to $default (the
 * built-in config price) when no row exists, so behaviour is unchanged until a rate is set.
 * Date-versioned: the newest row effective on $rateDate wins (blank date = newest overall).
 * Memoized per (customerKey|laneKey|date).
 */
function dict_route_rate(?PDO $conn, string $customerKey, string $laneKey, float $default, string $rateDate = ""): float
{
    if ($conn === null) {
        return $default;
    }
    static $cache = [];
    $date = substr(trim($rateDate), 0, 10);
    $ck = $customerKey . "|" . $laneKey . "|" . $date;
    if (array_key_exists($ck, $cache)) {
        return $cache[$ck];
    }
    try {
        $sql = "SELECT base_rate FROM rate_lane
                WHERE customer_key = ? AND active = TRUE AND UPPER(TRIM(dcode)) = UPPER(TRIM(?))
                  AND COALESCE(NULLIF(TRIM(base_rate::text),''),'0') <> '0'";
        $params = [$customerKey, $laneKey];
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $sql .= " AND (effective_from IS NULL OR effective_from <= ?) AND (effective_to IS NULL OR effective_to >= ?)";
            $params[] = $date;
            $params[] = $date;
        }
        $sql .= " ORDER BY effective_from DESC NULLS LAST, lane_id DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $v = $stmt->fetchColumn();
        $rate = ($v !== false && $v !== null && is_numeric($v)) ? (float) $v : $default;
    } catch (Throwable $e) {
        $rate = $default;
    }
    return $cache[$ck] = $rate;
}

/** PANABO statement columns (DATE ... NET AMOUNT) — matches the template sheets. */
function dict_shuttling_pdf_columns(): array
{
    // Approved statement layout (per the SAP "DICT Shuttling summary" PDF): no Trailer /
    // D'Code / Driver columns.
    return [
        "date" => "DATE",
        "trip_receipt" => "TRIP RECEIPT",
        "truck" => "TRUCK",
        "qty" => "QTY.",
        "rate" => "RATE",
        "amount" => "NET AMOUNT",
    ];
}

/** Fetch shuttling rows for a specific set of entry ids (for the PDF rebuild). */
function dict_shuttling_fetch_by_ids(PDO $conn, array $entryIds): array
{
    $ids = array_values(array_unique(array_filter(array_map("intval", $entryIds), fn($v) => $v > 0)));
    if (empty($ids)) {
        return [];
    }
    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $stmt = $conn->prepare(
        "SELECT entry_id, waybill, waybill_date::text AS trip_date, truck, tr, driver,
                load_quantity_weight, unit_of_measure, deliver_from, delivered_to, remarks, billing_sku
         FROM operations
         WHERE entry_id IN ($placeholders)
         ORDER BY NULLIF(waybill_date::text,'')::date ASC, entry_id ASC"
    );
    $stmt->execute($ids);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * PANABO statement rows for ONE lane — unlike the SAP file these are PER TRIP
 * (one line per trip receipt), matching the template sheets.
 * Returns ['rows','entry_ids','total','lane'].
 */
function dict_shuttling_detail_rows(array $entries, string $laneKey, ?PDO $conn = null, string $rateDate = ""): array
{
    $cfg = dict_shuttling_config();
    $lane = $cfg["lanes"][$laneKey] ?? null;
    if ($lane === null) {
        throw new RuntimeException("Unknown DICT shuttling lane: $laneKey");
    }
    // Rate from the Rate Matrix (editable) with the config price as fallback.
    $price = dict_route_rate($conn, $cfg["key"], $laneKey, (float) $lane["price"], $rateDate);

    $rows = [];
    $entryIds = [];
    $total = 0.0;

    foreach ($entries as $e) {
        if (dict_shuttling_lane($e) !== $laneKey) {
            continue;
        }
        $qty = dict_shuttling_trips($e);
        if ($qty <= 0) {
            continue;
        }
        $amount = round($qty * $price, 2);
        $total += $amount;
        $entryIds[] = (int) $e["entry_id"];

        $date = substr(billing_norm($e["trip_date"] ?? ""), 0, 10);
        $ts = $date !== "" ? strtotime($date) : false;

        $rows[] = [
            "date" => $ts ? date("m/d/Y", $ts) : "",
            "trip_receipt" => billing_norm($e["waybill"] ?? ""),
            "truck" => billing_digits($e["truck"] ?? ""),
            "trailer" => billing_trailer_label($e["tr"] ?? ""),
            "dcode" => "",
            "driver" => billing_norm($e["driver"] ?? ""),
            "qty" => rtrim(rtrim(number_format($qty, 2, ".", ","), "0"), "."),
            "rate" => number_format($price, 2, ".", ","),
            "amount" => number_format($amount, 2, ".", ","),
        ];
    }

    return [
        "rows" => $rows,
        "entry_ids" => $entryIds,
        "total" => round($total, 2),
        "lane" => $lane,
    ];
}

function dict_shuttling_lane_options(): array
{
    $out = [];
    foreach (dict_shuttling_config()["lanes"] as $key => $lane) {
        $out[$key] = $lane["label"];
    }
    return $out;
}

/**
 * Normalise a shuttling location to its lane side: "ECD", "DICT" or "" (other).
 * The data uses both the yard and bare forms ("ECD CY"/"ECD", "DICT CY"/"DICT").
 */
function dict_shuttling_side(?string $location): string
{
    $text = strtoupper(billing_norm($location));
    if ($text === "") {
        return "";
    }
    if (strpos($text, "DICT") !== false) {
        return "DICT";
    }
    if (strpos($text, "ECD") !== false) {
        return "ECD";
    }
    return ""; // INSPECTION / NESTFARM / MECHANICAL SHOP etc.
}

/**
 * Which lane a shuttling row belongs to, or null when it can't be billed.
 *
 * Both sides of the lane ARE recorded: `deliver_from` = origin, `delivered_to` =
 * destination (both populated on every hustling row). An XRAY run is flagged in
 * `remarks` and is always DICT-side.
 *
 *   remarks XRAY   -> DICT TO XRAY
 *   ECD  -> ECD    -> ECD TO ECD
 *   DICT -> ECD    -> DICT TO ECD
 *   ECD  -> DICT   -> ECD TO DICT
 *   anything else (INSPECTION / NESTFARM / DICT->DICT without an XRAY remark)
 *                  -> null (not billed — no matching lane on the rate sheet)
 */
function dict_shuttling_lane(array $entry): ?string
{
    $remarks = strtoupper(billing_norm($entry["remarks"] ?? ""));
    if (strpos($remarks, "XRAY") !== false) {
        return "dict_xray";
    }

    $from = dict_shuttling_side($entry["deliver_from"] ?? "");
    $to = dict_shuttling_side($entry["delivered_to"] ?? "");
    if ($from === "" || $to === "") {
        return null;
    }
    if ($from === "ECD" && $to === "ECD") {
        return "ecd_ecd";
    }
    if ($from === "DICT" && $to === "ECD") {
        return "dict_ecd";
    }
    if ($from === "ECD" && $to === "DICT") {
        return "ecd_dict";
    }
    return null; // DICT -> DICT is only billable as the XRAY lane
}

/** Fetch the DICT shuttling rows for a date range (by waybill_date). */
function dict_shuttling_fetch(PDO $conn, string $dateFrom, string $dateTo, array $excludeEntryIds = []): array
{
    if (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ||
        $dateFrom > $dateTo
    ) {
        throw new RuntimeException("Invalid date range.");
    }
    $cfg = dict_shuttling_config();

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

/** Trip count on a shuttling row (load_quantity_weight, uom TRIPS). */
function dict_shuttling_trips(array $entry): float
{
    $raw = billing_norm($entry["load_quantity_weight"] ?? "");
    return is_numeric($raw) ? (float) $raw : 0.0;
}

/**
 * Build the SAP rows for ONE lane: one line per (truck, trailer) with the summed
 * trip count. Returns ['rows','entry_ids','total','lane','reference'].
 */
function dict_shuttling_build(array $entries, string $laneKey, ?string $reference = null, ?array $cfgOverride = null, ?PDO $conn = null, string $rateDate = ""): array
{
    // $cfgOverride lets the caller pass a config already overlaid with the Master Data
    // SAP codes (Sold-To / Material / Profit Center); falls back to the built-in config.
    $cfg = $cfgOverride ?? dict_shuttling_config();
    $lane = $cfg["lanes"][$laneKey] ?? null;
    if ($lane === null) {
        throw new RuntimeException("Unknown DICT shuttling lane: $laneKey");
    }
    $reference = $reference !== null && trim($reference) !== ""
        ? trim($reference)
        : sprintf("%s (%s)", $cfg["label"], $lane["label"]);

    $docSerial = billing_document_serial();
    // Rate from the Rate Matrix (editable) with the config price as fallback.
    $price = dict_route_rate($conn, $cfg["key"], $laneKey, (float) $lane["price"], $rateDate);

    // Group this lane's rows by (truck, trailer), summing trips.
    $groups = [];
    foreach ($entries as $e) {
        if (dict_shuttling_lane($e) !== $laneKey) {
            continue;
        }
        $trips = dict_shuttling_trips($e);
        if ($trips <= 0) {
            continue;
        }
        $truck = billing_digits($e["truck"] ?? "");
        $tr = billing_trailer_label($e["tr"] ?? "");
        $key = $truck . "|" . $tr;
        if (!isset($groups[$key])) {
            $groups[$key] = ["truck" => $truck, "tr" => $tr, "trips" => 0.0, "ids" => [], "sku" => (string) ($e["billing_sku"] ?? "")];
        }
        $groups[$key]["trips"] += $trips;
        $groups[$key]["ids"][] = (int) $e["entry_id"];
    }

    $rows = [];
    $entryIds = [];
    $item = 0;
    $total = 0.0;

    foreach ($groups as $g) {
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
            ["t", ""],                          // Alternate Quantity (blank in template)
            ["t", $cfg["condition_type"]],
            ["n", round($price, 2)],            // Price = lane rate
            ["n", billing_sap_condition_unit($cfg["document_currency"] ?? "PHP")], // USD 1000 / PHP 0
            ["t", $cfg["document_currency"]],
            ["t", ""],                          // Forex Rate (blank — PHP billing)
            ["t", $cfg["profit_center"]],
            // PM / TR = the unit's SAP Equipment code (Master Data → Equipment SAP Code), else digits.
            ["n3", billing_sap_three_digits($conn !== null ? equipment_sap_code($conn, "PM", (string) $g["truck"]) : $g["truck"])], // Equipment No.
            ["t", ""],                          // Activity
            // Route = the SKU's SAP Assigned No (Master Data → SKU Routes), else the lane route.
            ["n3", billing_sap_three_digits(($conn !== null ? sku_route_sap_no($conn, (string) ($g["sku"] ?? "")) : "") ?: $lane["route"])],
            ["n3", billing_sap_three_digits($conn !== null ? equipment_sap_code($conn, "TR", (string) $g["tr"]) : $g["tr"])], // TR
            ["t", ""],                          // RV
            ["t", ""],                          // GS
            ["t", ""],                          // AD (helper) — intentionally blank
        ];
    }

    return [
        "rows" => $rows,
        "entry_ids" => array_values(array_unique($entryIds)),
        "total" => round($total, 2),
        "lane" => $lane,
        "reference" => $reference,
    ];
}
