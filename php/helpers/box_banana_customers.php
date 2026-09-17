<?php

require_once __DIR__ . "/billing_customers.php";
require_once __DIR__ . "/custom_billing_customers.php";

/**
 * Per-customer configuration for the Box Bananas billing.
 *
 * The generated Excel uses the SAME SAP ZPSO upload format as the Customer
 * Billing page (see build_customer_billing.php) — one line per trip — so each
 * customer carries the SAP header fields that writer needs. Trip selection is
 * by `shipper` (the operations customer column) and the trip date; the per-line
 * Price is resolved through the fuel-based rate matrix (see fuel_rate_engine).
 *
 * Sumifru note: "Sumifru - Box Bananas" and "Sumifru Containerized" are the
 * SAME customer, so the Sumifru entry below is built from the existing
 * billing_customers_config()["sumifru_containerized"] and shares its
 * matrix_key — it is configured and priced once, not twice.
 *
 * Field meanings
 *   label             - dropdown label on the Box Bananas page
 *   matrix_key        - customer_key used for the rate matrix + dedupe (defaults
 *                       to the array key; Sumifru points at the containerized key)
 *   fuel_source       - fuel_price supplier column (e.g. "seaoil"); "" = common/avg
 *   reference_prefix  - label used for the Reference column / batch name
 *   entry_type/segment/customer_match - trip selection (shipper match)
 *   SAP header fields - order_type, sales_org, distribution_channel, division,
 *                       sold_to, customer_tax_class, document_currency,
 *                       billed_services, material_code, sales_unit,
 *                       condition_type, condition_unit, profit_center, route
 *
 * The ABC/TDC SAP codes below are placeholders (document_currency PHP, blank SAP
 * codes) pending the real SAP master values from finance; Sumifru is complete.
 */
/**
 * The shared field wrapper for a Box Banana customer entry: sensible activity/entry-type
 * defaults + the common SAP header defaults, with the caller's fields layered on top.
 * Extracted so both the built-in definitions and DB-added customers are shaped the same.
 */
function box_banana_make_customer(array $overrides): array
{
    $activity = "Hauling Containerized Bananas";
    $sapDefaults = [
        "order_type" => "ZPSO",
        "sales_org" => "3200",
        "distribution_channel" => "20",
        "division" => "31",
        "sold_to" => "",
        "customer_tax_class" => "0",
        "document_currency" => "PHP",
        "billed_services" => "Hauling Containerized Bananas",
        "material_code" => "",
        "sales_unit" => "TRP",
        "condition_type" => "PR00",
        "condition_unit" => "1",
        "profit_center" => "",
        "route" => "",
    ];
    return array_merge([
        "activity" => $activity,
        "entry_type" => "RV ENTRY",
        "segment" => "",
        "rate_basis" => "trip",
        "fuel_source" => "",
        "default_boxes" => null,
        "is_vat" => false,
    ], $sapDefaults, $overrides);
}

/**
 * The code-defined Box Banana customers (the single source of pipeline wiring — PDF
 * layout, matrix key, forex, hidden columns, …). These are seeded into the DB by
 * box_banana_customers_config() so the live list is database-driven, but their non-editable
 * pipeline fields are always backfilled from here so nothing can regress.
 */
function box_banana_builtin_customers(): array
{
    // Default ACTIVITY label; each customer sets its own below so they can differ
    // per-customer (an entry that omits "activity" falls back to this).
    $activity = "Hauling Containerized Bananas";

    // Common SAP defaults for the domestic TADECO breakbulk customers (PHP).
    $sapDefaults = [
        "order_type" => "ZPSO",
        "sales_org" => "3200",
        "distribution_channel" => "20",
        "division" => "31",
        "sold_to" => "",
        "customer_tax_class" => "0",
        "document_currency" => "PHP",
        // SAP "Billed Services" column — all Box Banana customers bill under the same
        // service name (user request 2026-07-16); Sumifru already uses this via its
        // containerized config.
        "billed_services" => "Hauling Containerized Bananas",
        "material_code" => "",
        "sales_unit" => "TRP",
        "condition_type" => "PR00",
        "condition_unit" => "1",
        "profit_center" => "",
        "route" => "",
    ];

    $make = static function (array $overrides) use ($activity, $sapDefaults): array {
        return array_merge([
            "activity" => $activity,
            "entry_type" => "RV ENTRY",
            "segment" => "",
            "rate_basis" => "trip",
            "fuel_source" => "",
            "default_boxes" => null,
        ], $sapDefaults, $overrides);
    };

    // Sumifru: same customer as the containerized SAP billing — reuse its config.
    $sumifru = billing_customers_config()["sumifru_containerized"] ?? [];
    $sumifru = array_merge($sumifru, [
        "label" => "Sumifru",
        "matrix_key" => "sumifru_containerized",
        "fuel_source" => "",
        "entry_type" => "RV ENTRY",
        "segment" => "",
        "customer_match" => "SUMIFRU",
        "rate_basis" => "trip",
        "default_boxes" => null,
        // reference_prefix already present from the containerized config
    ]);

    $config = [
        "abc_pantukan" => $make([
            "label" => "ABC - Pantukan",
            "activity" => "Hauling Containerized Bananas",
            "matrix_key" => "abc_pantukan",
            "charge_to" => "ABC - PANTUKAN",
            "destination" => "TADECO TO PANTUKAN",
            // Statement header route approved for ABC Pantukan billing.
            "pdf_destination" => "PANTUKAN to DICT | SUMIFRU WHARF",
            // Keep the Pantukan hauling statement focused on its route and
            // billing amount; Origin and Packing Station are not required.
            "pdf_hide_columns" => ["origin", "packing"],
            "reference_prefix" => "ABC Pantukan Box Bananas",
            "customer_match" => "PANTUKAN",
        ]),
        "abc_cateel" => $make([
            "label" => "ABC - Cateel",
            "activity" => "Hauling Containerized Bananas",
            "matrix_key" => "abc_cateel",
            "charge_to" => "ABC - CATEEL",
            "destination" => "TADECO TO CATEEL",
            // Fixed approved statement route header for the PANABO PDF (overrides the
            // legacy "<PH> to <destination>" so the header reads exactly this).
            "pdf_destination" => "CATEEL TO DICT WHARF",
            "pdf_hide_columns" => ["origin", "packing"],
            "reference_prefix" => "ABC Cateel Box Bananas",
            "customer_match" => "CATEEL",
        ]),
        "abc_donmar" => $make([
            "label" => "ABC - Don Marcelino",
            "activity" => "Hauling Containerized Bananas",
            "matrix_key" => "abc_donmar",
            "charge_to" => "ABC - DON MARCELINO",
            "destination" => "TADECO TO DON MARCELINO",
            "pdf_hide_columns" => ["origin", "packing"],
            "reference_prefix" => "ABC Don Marcelino Box Bananas",
            "customer_match" => "DONMAR",
        ]),
        // Dole Asia has two types, split by Packing House: ABC Lupon (ph "LUPON",
        // shipper "ABC Lupon") and TDC (the rest — shipper "Dole", ph PHxx). Both bill in
        // USD with a per-trip Dollar Conversion forex, like Sumifru (the PHP fuel-matrix
        // rate is divided by the forex; condition_unit/scale auto-derive from the currency).
        // SAP sold_to/material_code/profit_center left blank pending finance's master values.
        "dole_asia_lupon" => $make([
            "label" => "ABC Lupon - Dole Asia",
            "activity" => "Hauling Containerized Bananas",
            "matrix_key" => "dole_asia_lupon",
            "charge_to" => "DOLE ASIA HOLDINGS PTE. LTD. - LUPON",
            "bill_to_name" => "DOLE ASIA HOLDINGS PTE. LTD. - LUPON",
            "pdf_activity" => "HAULING OF CONTAINERIZED BANANAS",
            "pdf_origin" => "ABC LUPON",
            "destination" => "DOLE CY",
            // Statement header route approved for ABC Lupon billing.
            "pdf_destination" => "ABC LUPON TO DOLE CY/PANABO WHARF",
            "reference_prefix" => "Dole Asia Lupon Reefer Vans",
            "pdf_layout" => "dole_asia",
            // ABC Lupon Dole = packed at Lupon (shipper "ABC Lupon" == ph "LUPON").
            "customer_match" => "Lupon",
            "document_currency" => "USD",
        ]),
        "dole_asia_tdc" => $make([
            "label" => "TDC - Dole Asia",
            "activity" => "Hauling Containerized Bananas",
            "matrix_key" => "dole_asia_tdc",
            "charge_to" => "DOLE ASIA HOLDINGS PTE. LTD. - TDC",
            "bill_to_name" => "DOLE ASIA HOLDINGS PTE. LTD. - TDC",
            "pdf_activity" => "HAULING OF CONTAINERIZED BANANAS",
            "pdf_origin" => "TDC PACKING STATION",
            "destination" => "DOLE CY",
            "reference_prefix" => "Dole Asia TDC Reefer Vans",
            "pdf_layout" => "dole_asia",
            // TDC Dole = the rest of the Dole trips (shipper "Dole", ph PHxx).
            "customer_match" => "Dole",
            "document_currency" => "USD",
        ]),
        "tdc_goodfarmer" => $make([
            "label" => "TDC - Goodfarmer",
            "activity" => "Hauling Containerized Bananas",
            "matrix_key" => "tdc_goodfarmer",
            "charge_to" => "TADECO, INC.",
            "destination" => "TADECO TO GOODFARMER",
            // PDF DESTINATION header: "<pdf_origin> to <distinct delivered ports>".
            "pdf_origin" => "TDC Packing Station",
            "reference_prefix" => "TDC Goodfarmer Box Bananas",
            "customer_match" => "GOOD FARMER",
            // Hide the ORIGIN column on the PANABO PDF statement (per finance); the
            // remaining columns reflow across the table width (see build_billing_pdf.php).
            "pdf_hide_columns" => ["origin"],
        ]),
        "tdc_farmind" => $make([
            "label" => "TDC - Farmind",
            "activity" => "Hauling Containerized Bananas",
            "matrix_key" => "tdc_farmind",
            "charge_to" => "TADECO, INC.",
            "destination" => "TADECO TO FARMIND",
            "pdf_origin" => "TDC Packing Station",
            "reference_prefix" => "TDC Farmind Box Bananas",
            "customer_match" => "FARMIND",
        ]),
        "sumifru" => $sumifru,
    ];

    return $config;
}

/**
 * Per-customer configuration for the Box Bananas billing, database-driven.
 *
 * The customer LIST now lives in the DB (billing_custom_customer): the built-in
 * customers above are seeded on first read, and finance adds new ones (or edits the
 * name / VAT flag) from Master Data → Customer SAP Codes — no code change needed.
 *
 * For a seeded built-in, the code definition is authoritative for pipeline wiring
 * (PDF layout, matrix key, forex, hidden columns) — only the label and the VAT flag
 * are taken from the DB. Brand-new DB customers are priced through the fuel Rate Matrix
 * (resolve_lane_rate), the same as the built-ins. If the DB is unavailable the code
 * definitions are returned as-is, so billing never breaks.
 */
function box_banana_customers_config(): array
{
    $builtins = box_banana_builtin_customers();

    global $conn;
    if (!($conn instanceof PDO)) {
        return $builtins;
    }

    // Seed the built-ins into the DB (idempotent), then read the live list back.
    $seeded = seed_box_banana_builtins($conn, $builtins);
    $dbRows = custom_billing_customers_config($conn, $seeded > 0);
    if (empty($dbRows)) {
        return $builtins;
    }

    $config = [];
    foreach ($dbRows as $key => $cfg) {
        if (isset($builtins[$key])) {
            // Built-in: keep code wiring; let the DB drive the display name + VAT flag.
            $merged = $builtins[$key];
            $label = trim((string) ($cfg["label"] ?? ""));
            if ($label !== "") {
                $merged["label"] = $label;
            }
            $merged["is_vat"] = !empty($cfg["is_vat"]);
            $merged["custom"] = true;
            $merged["builtin"] = true;
            $config[$key] = $merged;
        } else {
            // User-added customer: shape it like a built-in entry (defaults + its fields).
            $config[$key] = box_banana_make_customer($cfg);
        }
    }

    // Safety net: a built-in that somehow isn't in the DB yet still appears.
    foreach ($builtins as $key => $b) {
        if (!isset($config[$key])) {
            $config[$key] = $b;
        }
    }

    return $config;
}

function box_banana_customer(string $key): ?array
{
    $config = box_banana_customers_config();
    return $config[$key] ?? null;
}

/** The rate-matrix customer key for a box-banana customer (Sumifru shares the containerized one). */
function box_banana_matrix_key(string $key, ?array $customer = null): string
{
    $customer = $customer ?? box_banana_customer($key);
    $matrixKey = trim((string) ($customer["matrix_key"] ?? ""));
    return $matrixKey !== "" ? $matrixKey : $key;
}
