<?php

require_once __DIR__ . "/billing_customers.php";
require_once __DIR__ . "/box_banana_customers.php";
require_once __DIR__ . "/dict_shuttling.php";
require_once __DIR__ . "/dict_industrial_waste.php";
require_once __DIR__ . "/abc_kds.php";
require_once __DIR__ . "/dry_van.php";

/**
 * The single Billing page now covers every customer. Two pricing pipelines:
 *   - "sap"    : the original SAP ZPSO billing (flat rate_code + forex) defined
 *                in billing_customers_config() (e.g. Sumifru Containerized).
 *   - "matrix" : fuel-rate-matrix priced customers from box_banana_customers_config()
 *                (the ABC/TDC breakbulk customers). Output is the same SAP layout.
 *
 * Sumifru is the same customer in both, so the box-banana "sumifru" entry (which
 * just points back at sumifru_containerized) is omitted here to avoid a duplicate.
 *
 * Returns [ key => ['label' => string, 'pipeline' => 'sap'|'matrix'] ].
 */
function unified_billing_customers(): array
{
    $out = [];

    foreach (billing_customers_config() as $key => $cfg) {
        // Sumifru is containerized bananas — same Box Bananas group as the rest.
        $out[$key] = ["label" => $cfg["label"] ?? $key, "pipeline" => "sap", "group" => "Box Bananas"];
    }

    foreach (box_banana_customers_config() as $key => $cfg) {
        // Skip the Sumifru box-banana alias — sumifru_containerized already covers it.
        if ($key === "sumifru" || box_banana_matrix_key($key, $cfg) === "sumifru_containerized") {
            continue;
        }
        if (!isset($out[$key])) {
            // Matrix customers are grouped under Box Bananas. The built-in ones are now
            // DB-seeded (custom=true) but stay Box Bananas via their builtin flag; only
            // genuinely user-added customers (custom without builtin) get "Other Customers".
            $group = (!empty($cfg["custom"]) && empty($cfg["builtin"])) ? "Other Customers" : "Box Bananas";
            $out[$key] = ["label" => $cfg["label"] ?? $key, "pipeline" => "matrix", "group" => $group];
        }
    }

    // DICT Van Shuttling: its own pipeline — priced from the Container Hustling
    // entries, one file per lane (see dict_shuttling.php).
    $shuttling = dict_shuttling_config();
    $out[$shuttling["key"]] = [
        "label" => $shuttling["label"],
        "pipeline" => "shuttling",
        "group" => "DICT Hustling",
    ];

    // DICT Industrial Waste / Garbage — its own by-trip pipeline (BEHIND / WATERFALL),
    // priced flat per route with 12% VAT on the PDF (see dict_industrial_waste.php).
    $waste = industrial_waste_config();
    $out[$waste["key"]] = [
        "label" => $waste["label"],
        "pipeline" => "industrial_waste",
        "group" => "DICT Hustling",
    ];

    // ABC KDs (Hauling KD Cartons) — priced from each customer's own KDs matrix.
    foreach (abc_kds_config() as $key => $cfg) {
        $out[$key] = ["label" => $cfg["label"], "pipeline" => "kds", "group" => "ABC KDs"];
    }

    // Dry Vans (dry container hauling) — flat rate per trip from the `rates` table.
    foreach (dry_van_config() as $key => $cfg) {
        $out[$key] = ["label" => $cfg["label"], "pipeline" => "dryvan", "group" => "Dry Vans"];
    }

    return $out;
}

/**
 * Display order for the Billing page's customer dropdown <optgroup>s. Groups with
 * no customers are simply skipped, so "ABC KDs" appears once those are added.
 */
function unified_billing_group_order(): array
{
    return ["Box Bananas", "DICT Hustling", "ABC KDs", "Dry Vans", "Other Customers"];
}

/** Customers keyed by group, in unified_billing_group_order(). */
function unified_billing_customers_grouped(): array
{
    $grouped = [];
    foreach (unified_billing_customers() as $key => $cfg) {
        $grouped[$cfg["group"] ?? "Other"][$key] = $cfg;
    }

    $ordered = [];
    foreach (unified_billing_group_order() as $group) {
        if (!empty($grouped[$group])) {
            $ordered[$group] = $grouped[$group];
            unset($grouped[$group]);
        }
    }
    // Any group not in the known order still shows, after the known ones.
    foreach ($grouped as $group => $items) {
        $ordered[$group] = $items;
    }

    return $ordered;
}

/**
 * The RAW export's free-text customer filter for a billing customer key, or null
 * when the RAW export does not apply.
 *
 * The RAW "for billing" export is reefer RV-ENTRY only, so it is offered for the
 * Box Bananas group (sap + matrix pipelines) and is meaningless for DICT Hustling
 * (OTHERS ENTRY) / ABC KDs (DPC_KDs ENTRY). RAW filters on
 * customer_ph/shipper/operations_ph text, so each customer maps to its config's
 * `customer_match` (e.g. sumifru_containerized -> "SUMIFRU").
 */
function unified_billing_raw_match(string $key): ?string
{
    $pipeline = unified_billing_pipeline($key);
    if ($pipeline !== "sap" && $pipeline !== "matrix") {
        return null;
    }
    $customer = $pipeline === "sap" ? billing_customer($key) : box_banana_customer($key);
    $match = trim((string) ($customer["customer_match"] ?? ""));
    return $match !== "" ? $match : null;
}

/** [customer_key => raw customer_match] for every customer the RAW export covers. */
function unified_billing_raw_matches(): array
{
    $out = [];
    foreach (array_keys(unified_billing_customers()) as $key) {
        $match = unified_billing_raw_match($key);
        if ($match !== null) {
            $out[$key] = $match;
        }
    }
    return $out;
}

/** Which pipeline prices a given customer key, or "" if unknown. */
function unified_billing_pipeline(string $key): string
{
    if ($key === dict_shuttling_config()["key"]) {
        return "shuttling";
    }
    if ($key === industrial_waste_config()["key"]) {
        return "industrial_waste";
    }
    if (abc_kds_customer($key) !== null) {
        return "kds";
    }
    if (dry_van_customer($key) !== null) {
        return "dryvan";
    }
    if (array_key_exists($key, billing_customers_config())) {
        return "sap";
    }
    if ($key !== "sumifru" && box_banana_customer($key) !== null) {
        return "matrix";
    }
    return "";
}
