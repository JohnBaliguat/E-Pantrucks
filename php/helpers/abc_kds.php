<?php

require_once __DIR__ . "/build_customer_billing.php";
require_once __DIR__ . "/fuel_rate_engine.php";

/**
 * ABC KDs billing ("Hauling KD Cartons") — SAP ZPSO + PANABO statement.
 *
 * Source: `operations` entry_type "DPC_KDs & OPM ENTRY", one customer per
 * `billing_sku` ("DPC KDS-ABC Pantukan" / "-ABC Cateel" / "-ABC DonMar" /
 * "-ABC Lupon"). Date = waybill_date.
 *
 * Output: the shared 30-column SAP layout (billing_column_labels /
 * billing_write_xlsx), ONE LINE PER TRIP with Order Quantity = 1, Price = the
 * trip's rate and Amount = the same value. PHP billing, no forex.
 *
 * PRICING: the rate comes from the FUEL RATE MATRIX (rate_lane/band/cell) under
 * this customer's own `matrix_key` — KDs is a different activity from the
 * breakbulk hauling, so it must NOT reuse the abc_pantukan / abc_cateel hauling
 * keys. No KDs matrix is loaded yet, so every trip currently resolves to 0 and
 * the endpoints report that plainly rather than emitting a bogus file.
 *
 * Profit centers are the authoritative values from the `profit_center` master
 * table (HAUL KDS ABC PANT / DONMAR / CATEEL / LUPON).
 */
function abc_kds_config(): array
{
    $common = [
        "order_type" => "ZPSO",
        "sales_org" => "3200",
        "distribution_channel" => "30",
        "division" => "31",
        "customer_tax_class" => "0",
        "document_currency" => "PHP",
        "billed_services" => "Hauling KD Cartons",
        "material_code" => "FS00000003",
        "sales_unit" => "TRP",
        "condition_type" => "PR00",
        "condition_unit" => "1",
        "fuel_source" => "",
        "pdf_activity" => "HAULING OF KD CARTONS",
        // KD hauling rate = the customer's ABC BOX-BANANA lane rate MINUS this discount
        // (user rule 2026-09-14). `box_banana_key` + `box_banana_dcode` name the source lane
        // (KD trips carry no origin/destination, so the lane is fixed per customer).
        "kds_discount" => 1000.0,
        "shunting_rate" => 0.0, // ABC Pantukan overrides this (a SHUNTING trip = its own line)
    ];

    return [
        "abc_pantukan_kds" => array_merge($common, [
            "label" => "ABC Pantukan KDs",
            "matrix_key" => "abc_pantukan_kds",
            "billing_sku" => "DPC KDS-ABC Pantukan",
            "box_banana_key" => "abc_pantukan",
            "box_banana_dcode" => "DICT", // ABC Pantukan DICT lane − 1000 = 16,959 (verified vs statement)
            // A SHUNTING move (remarks contain SHUNTING) on an ABC Pantukan trip bills its own
            // ₱500 line on this statement.
            "shunting_rate" => 500.0,
            "shunting_ph_match" => "PANTUKAN",
            "sold_to" => "IC2100",
            "profit_center" => "3200040030",
            "route" => "570",
            "reference_prefix" => "ABC Pantukan KDs",
            "charge_to" => "ANFLO BANANA CORPORATION - PANTUKAN",
            "pdf_destination" => "DAPACOR to PANTUKAN",
        ]),
        "abc_donmar_kds" => array_merge($common, [
            "label" => "ABC DonMar KDs",
            "matrix_key" => "abc_donmar_kds",
            "billing_sku" => "DPC KDS-ABC DonMar",
            "box_banana_key" => "abc_donmar",
            "box_banana_dcode" => "DICT", // ⚠️ confirm the source lane with finance
            "sold_to" => "IC2101",
            "profit_center" => "3200040040",
            "route" => "572",
            "reference_prefix" => "ABC DonMar KDs",
            "charge_to" => "ANFLO BANANA CORPORATION - DONMAR",
            "pdf_destination" => "DAPACOR to DONMAR",
        ]),
        "abc_cateel_kds" => array_merge($common, [
            "label" => "ABC Cateel KDs",
            "matrix_key" => "abc_cateel_kds",
            "billing_sku" => "DPC KDS-ABC Cateel",
            "box_banana_key" => "abc_cateel",
            "box_banana_dcode" => "CMVP", // ABC Cateel's DICT Port lane; ⚠️ confirm with finance
            "sold_to" => "IC2103",
            "profit_center" => "3200040050",
            "route" => "573",
            "reference_prefix" => "ABC Cateel KDs",
            "charge_to" => "ABC - CATEEL",
            "pdf_destination" => "DAPACOR to CATEEL",
        ]),
        // Lupon: no template was supplied — profit center is confirmed from the
        // profit_center master table, but sold_to and route are UNKNOWN and must
        // not be guessed (the sequence gaps IC2102 / 571 are only a hunch).
        "abc_lupon_kds" => array_merge($common, [
            "label" => "ABC Lupon KDs",
            "matrix_key" => "abc_lupon_kds",
            "billing_sku" => "DPC KDS-ABC Lupon",
            "box_banana_key" => "dole_asia_lupon",
            "box_banana_dcode" => "", // single-lane customer; ⚠️ confirm with finance
            "sold_to" => "",
            "profit_center" => "3200040060",
            "route" => "",
            "reference_prefix" => "ABC Lupon KDs",
            "charge_to" => "ANFLO BANANA CORPORATION - LUPON",
            "pdf_destination" => "DAPACOR to LUPON",
        ]),
    ];
}

/**
 * The KD hauling rate for a trip: the customer's ABC box-banana lane rate on the trip date,
 * MINUS the KDs discount (user rule 2026-09-14). Returns 0 when the source lane doesn't
 * price (so the line is skipped, matching the old behaviour).
 */
function abc_kds_rate(PDO $conn, array $customer, string $tripDate): float
{
    $bbKey = trim((string) ($customer["box_banana_key"] ?? ""));
    if ($bbKey === "" || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tripDate)) {
        return 0.0;
    }
    $dcode = (string) ($customer["box_banana_dcode"] ?? "");
    $resolved = resolve_lane_rate($conn, $bbKey, $dcode, $dcode, $tripDate, "");
    if (empty($resolved["matched"])) {
        return 0.0;
    }
    $rate = (float) $resolved["rate"] - (float) ($customer["kds_discount"] ?? 0);
    return $rate > 0 ? round($rate) : 0.0;
}

/** A SHUNTING move (its own ₱500 line) — identified by "SHUNTING" anywhere in remarks. */
function abc_kds_is_shunting(array $entry): bool
{
    return stripos((string) ($entry["remarks"] ?? ""), "SHUNTING") !== false;
}

function abc_kds_customer(string $key): ?array
{
    return abc_kds_config()[$key] ?? null;
}

/** [key => label] for the Billing page dropdown. */
function abc_kds_customers(): array
{
    $out = [];
    foreach (abc_kds_config() as $key => $cfg) {
        $out[$key] = $cfg["label"];
    }
    return $out;
}

/** SAP header fields this customer still needs before it can be billed. */
function abc_kds_missing_config(array $customer): array
{
    $missing = [];
    foreach (["sold_to" => "Sold-To", "route" => "Route"] as $field => $label) {
        if (trim((string) ($customer[$field] ?? "")) === "") {
            $missing[] = $label;
        }
    }
    return $missing;
}

/** Fetch this KDs customer's trips for a date range (by waybill_date). */
function abc_kds_fetch(PDO $conn, array $customer, string $dateFrom, string $dateTo, array $excludeEntryIds = []): array
{
    if (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ||
        $dateFrom > $dateTo
    ) {
        throw new RuntimeException("Invalid date range.");
    }

    // KD-carton trips (this customer's SKU). When the customer bills SHUNTING moves (ABC
    // Pantukan), also pull its trips whose remarks contain SHUNTING — they become ₱500 lines.
    $shuntingRate = (float) ($customer["shunting_rate"] ?? 0);
    $phMatch = strtoupper(trim((string) ($customer["shunting_ph_match"] ?? "")));
    if ($shuntingRate > 0 && $phMatch !== "") {
        $where = "((billing_sku = ?)
                   OR (remarks ILIKE '%SHUNTING%'
                       AND UPPER(COALESCE(NULLIF(customer_ph,''), NULLIF(ph,''), '')) LIKE ?))
                  AND NULLIF(waybill_date::text,'')::date BETWEEN ? AND ?";
        $params = [(string) $customer["billing_sku"], '%' . $phMatch . '%', $dateFrom, $dateTo];
    } else {
        $where = "billing_sku = ? AND NULLIF(waybill_date::text,'')::date BETWEEN ? AND ?";
        $params = [(string) $customer["billing_sku"], $dateFrom, $dateTo];
    }

    $excludeIds = array_values(array_unique(array_filter(array_map("intval", $excludeEntryIds), fn($v) => $v > 0)));
    if (!empty($excludeIds)) {
        $where .= " AND entry_id NOT IN (" . implode(",", array_fill(0, count($excludeIds), "?")) . ")";
        foreach ($excludeIds as $id) {
            $params[] = $id;
        }
    }

    $stmt = $conn->prepare(
        // "13_total"/"18_total" start with a digit — must stay quoted.
        "SELECT entry_id, waybill, waybill_date::text AS trip_date, truck, tr, driver,
                total_load, \"13_total\", \"18_total\", other_total, fgtr_no, ph, remarks, billing_sku
         FROM operations
         WHERE $where
         ORDER BY NULLIF(waybill_date::text,'')::date ASC, entry_id ASC"
    );
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Does this KDs customer have a rate matrix loaded? (legacy — KDs now prices off box-banana) */
function abc_kds_has_matrix(PDO $conn, array $customer): bool
{
    $stmt = $conn->prepare("SELECT COUNT(*) FROM rate_lane WHERE customer_key = ? AND active = TRUE");
    $stmt->execute([(string) $customer["matrix_key"]]);
    return ((int) $stmt->fetchColumn()) > 0;
}

/**
 * Can this KDs customer be priced? KDs now derives its rate from the ABC BOX-BANANA source
 * matrix (box_banana_key) minus the discount, so pricing needs that source to have active
 * lanes. Returns true when it does.
 */
function abc_kds_has_source_matrix(PDO $conn, array $customer): bool
{
    $key = trim((string) ($customer["box_banana_key"] ?? ""));
    if ($key === "") {
        return false;
    }
    $stmt = $conn->prepare("SELECT COUNT(*) FROM rate_lane WHERE customer_key = ? AND active = TRUE");
    $stmt->execute([$key]);
    return ((int) $stmt->fetchColumn()) > 0;
}

/**
 * Build the SAP rows — one line per trip, Order Quantity 1, Price from the
 * matrix. Returns ['rows','entry_ids','total','priced','unpriced'].
 * `unpriced` counts trips the matrix could not price (rate 0).
 */
function abc_kds_build(PDO $conn, array $entries, array $customer, string $reference, array $chargeOverrideByEntry = []): array
{
    $docSerial = billing_document_serial();
    $shuntingRate = (float) ($customer["shunting_rate"] ?? 0);

    $rows = [];
    $entryIds = [];
    $chargeByEntry = [];
    $item = 0;
    $total = 0.0;
    $unpriced = 0;

    foreach ($entries as $e) {
        $tripDate = substr(billing_norm($e["trip_date"] ?? ""), 0, 10);

        // SHUNTING move -> its own flat ₱500 line; otherwise the KD hauling rate =
        // the customer's ABC box-banana lane rate on the trip date minus the discount.
        $rate = ($shuntingRate > 0 && abc_kds_is_shunting($e))
            ? $shuntingRate
            : abc_kds_rate($conn, $customer, $tripDate);
        // Regeneration: a stored/edited charge overrides the matrix rate and forces
        // the line to be emitted even if the matrix no longer prices it.
        $eid = (int) ($e["entry_id"] ?? 0);
        $hasOverride = array_key_exists($eid, $chargeOverrideByEntry) && is_numeric($chargeOverrideByEntry[$eid]);
        if ($hasOverride) {
            $rate = (float) $chargeOverrideByEntry[$eid];
        }
        if ($rate <= 0 && !$hasOverride) {
            $unpriced++;
            continue; // never emit a zero-priced SAP line
        }

        $item += 10;
        $entryIds[] = $eid;
        $chargeByEntry[$eid] = round($rate, 2);
        $total += $rate;

        $rows[] = [
            ["t", $customer["order_type"]],
            ["t", $customer["sales_org"]],
            ["t", $customer["distribution_channel"]],
            ["t", $customer["division"]],
            ["dt", $docSerial],
            ["dt", $docSerial],
            ["t", $customer["sold_to"]],
            ["t", $customer["customer_tax_class"]],
            ["t", $customer["document_currency"]],
            ["t", $reference],
            ["t", ""],                       // Vessel Visit
            ["t", $customer["billed_services"]],
            ["n", $item],
            ["t", $customer["material_code"]],
            ["n", 1],                        // Order Quantity = 1 per trip
            ["t", $customer["sales_unit"]],
            billing_load_cell($e),              // Alternate Quantity = recorded load
            ["t", $customer["condition_type"]],
            ["n", round($rate, 0)],          // Price = the trip's matrix rate
            ["n", billing_sap_condition_unit($customer["document_currency"] ?? "PHP")], // USD 1000 / PHP 0
            ["t", $customer["document_currency"]],
            ["t", ""],                       // Forex Rate (PHP billing)
            ["t", $customer["profit_center"]],
            // PM / TR = the unit's SAP Equipment code (Master Data → Equipment SAP Code), else digits.
            ["t", equipment_sap_code($conn, "PM", $e["truck"] ?? "")],
            ["t", ""],                       // Activity
            // Route = the SKU's SAP Assigned No (Master Data → SKU Routes), else config route.
            ["t", sku_route_sap_no($conn, (string) ($e["billing_sku"] ?? "")) ?: $customer["route"]],
            ["t", equipment_sap_code($conn, "TR", $e["tr"] ?? "")],
            ["t", ""],                       // RV
            ["t", ""],                       // GS (blank for KDs)
            ["t", ""],                       // AD (helper) — intentionally blank
        ];
    }

    return [
        "rows" => $rows,
        "entry_ids" => $entryIds,
        "charge_by_entry" => $chargeByEntry,
        "total" => round($total, 2),
        "priced" => trim((string) ($customer["box_banana_key"] ?? "")) !== "",
        "unpriced" => $unpriced,
    ];
}

/**
 * PANABO statement columns — matches the KDS-PANTUKAN / KDS-DONMAR / KD'S-CATEEL
 * sheets. (The sheets carry two code columns, "CODE" and a second lowercase
 * "code"; both are blank in every sample row, so one is kept here.)
 */
function abc_kds_pdf_columns(): array
{
    return [
        "date" => "DATE",
        "trip_receipt" => "TRIP RECEIPT",
        "truck" => "TRUCK",
        "trailer" => "TRAILER",
        "code" => "CODE",
        "driver" => "DRIVER",
        "trips" => "TRIPS",
        "charge" => "HAULING CHARGES",
    ];
}

/** Fetch KDs rows for a specific set of entry ids (for the PDF rebuild). */
function abc_kds_fetch_by_ids(PDO $conn, array $entryIds): array
{
    $ids = array_values(array_unique(array_filter(array_map("intval", $entryIds), fn($v) => $v > 0)));
    if (empty($ids)) {
        return [];
    }
    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $stmt = $conn->prepare(
        "SELECT entry_id, waybill, waybill_date::text AS trip_date, truck, tr, driver,
                total_load, other_total, fgtr_no, ph, remarks, billing_sku
         FROM operations
         WHERE entry_id IN ($placeholders)
         ORDER BY NULLIF(waybill_date::text,'')::date ASC, entry_id ASC"
    );
    $stmt->execute($ids);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * PANABO statement rows — one line per trip (TRIPS = 1), charge = the trip's
 * matrix rate. Mirrors the SAP lines 1:1 like the template sheets do.
 * Returns ['rows','entry_ids','total'].
 */
function abc_kds_detail_rows(PDO $conn, array $entries, array $customer): array
{
    $shuntingRate = (float) ($customer["shunting_rate"] ?? 0);

    $rows = [];
    $entryIds = [];
    $total = 0.0;

    foreach ($entries as $e) {
        $tripDate = substr(billing_norm($e["trip_date"] ?? ""), 0, 10);

        $isShunting = $shuntingRate > 0 && abc_kds_is_shunting($e);
        $rate = $isShunting ? $shuntingRate : abc_kds_rate($conn, $customer, $tripDate);
        if ($rate <= 0) {
            continue; // keep the statement consistent with the SAP file
        }

        $total += $rate;
        $entryIds[] = (int) $e["entry_id"];
        $ts = $tripDate !== "" ? strtotime($tripDate) : false;

        $rows[] = [
            "date" => $ts ? date("m/d/Y", $ts) : "",
            "trip_receipt" => billing_norm($e["waybill"] ?? ""),
            "truck" => billing_digits($e["truck"] ?? ""),
            "trailer" => billing_trailer_label($e["tr"] ?? ""),
            "code" => $isShunting ? "SHUNTING" : "",
            "driver" => billing_norm($e["driver"] ?? ""),
            "trips" => "1",
            "charge" => number_format($rate, 2, ".", ","),
        ];
    }

    return ["rows" => $rows, "entry_ids" => $entryIds, "total" => round($total, 2)];
}
