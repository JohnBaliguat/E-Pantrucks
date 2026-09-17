<?php

/**
 * Per-customer SAP master codes (Sold-To, Material Code, Profit Center) that finance
 * maintains in Master Data instead of hardcoding in the customer config arrays. The
 * code configs (box_banana_customers.php, billing_customers.php, abc_kds.php,
 * dict_shuttling.php) leave these blank where finance hasn't supplied them; a row
 * here overrides the config value (non-empty only) when the SAP file is generated.
 *
 * Table is keyed by the unified billing customer_key, one row per customer.
 */

function ensure_customer_sap_schema(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS billing_customer_sap (
            customer_key   TEXT PRIMARY KEY,
            sold_to        TEXT NOT NULL DEFAULT '',
            material_code  TEXT NOT NULL DEFAULT '',
            profit_center  TEXT NOT NULL DEFAULT '',
            affiliate      BOOLEAN NOT NULL DEFAULT FALSE,
            updated_at     TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )"
    );
    // Affiliate column added after the table shipped — add it to existing installs.
    $conn->exec("ALTER TABLE billing_customer_sap ADD COLUMN IF NOT EXISTS affiliate BOOLEAN NOT NULL DEFAULT FALSE");
    $done = true;
}

/**
 * SAP Distribution Channel for an affiliate flag: affiliate customers bill under 30,
 * everyone else under 20.
 */
function billing_sap_distribution_channel(bool $affiliate): string
{
    return $affiliate ? "30" : "20";
}

/**
 * The fields this tab overrides. Sold-To is free text; Material Code and Profit Center
 * are PICKED per customer from the Service Materials / Profit Center master tabs (the
 * tab renders them as dropdowns sourced from those tables). The Affiliate flag is
 * handled separately (drives Distribution Channel).
 */
function customer_sap_fields(): array
{
    return ["sold_to", "material_code", "profit_center"];
}

/**
 * Option lists for the Material Code + Profit Center dropdowns, sourced from the
 * master-data tables so per-customer picks stay in sync with those tabs.
 * Returns ['materials' => [{code,label}], 'profit_centers' => [{code,label}]].
 */
function customer_sap_option_lists(PDO $conn): array
{
    $materials = [];
    try {
        $rows = $conn->query(
            "SELECT DISTINCT material_code, MIN(material_description) AS descr
             FROM service_material
             WHERE COALESCE(material_code,'') <> ''
             GROUP BY material_code
             ORDER BY material_code"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $code = (string) $r["material_code"];
            $descr = trim((string) ($r["descr"] ?? ""));
            $materials[] = ["code" => $code, "label" => $descr !== "" ? "$code — $descr" : $code];
        }
    } catch (Throwable $e) {
        // Table may not exist on a fresh install — leave the list empty.
    }

    $profitCenters = [];
    try {
        $rows = $conn->query(
            "SELECT profit_center_code, name FROM profit_center
             WHERE COALESCE(profit_center_code,'') <> ''
             ORDER BY profit_center_code"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $code = (string) $r["profit_center_code"];
            $name = trim((string) ($r["name"] ?? ""));
            $profitCenters[] = ["code" => $code, "label" => $name !== "" ? "$code — $name" : $code];
        }
    } catch (Throwable $e) {
        // Table may not exist — leave empty.
    }

    return ["materials" => $materials, "profit_centers" => $profitCenters];
}

/** [customer_key => [sold_to, material_code, profit_center, affiliate]] per stored row. */
function customer_sap_overrides(PDO $conn): array
{
    ensure_customer_sap_schema($conn);
    $out = [];
    $rows = $conn->query(
        "SELECT customer_key, sold_to, material_code, profit_center, affiliate FROM billing_customer_sap"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $out[(string) $r["customer_key"]] = [
            "sold_to" => (string) $r["sold_to"],
            "material_code" => (string) $r["material_code"],
            "profit_center" => (string) $r["profit_center"],
            "affiliate" => (bool) $r["affiliate"],
        ];
    }
    return $out;
}

/**
 * Overlay a customer's stored SAP overrides onto its config array. Only NON-EMPTY
 * stored values replace the config, so a blank field falls back to the code default
 * (e.g. Sumifru keeps its built-in values unless finance sets a different one).
 */
function billing_customer_apply_sap_overrides(PDO $conn, string $customerKey, array $customer): array
{
    ensure_customer_sap_schema($conn);
    $stmt = $conn->prepare(
        "SELECT sold_to, material_code, profit_center, affiliate FROM billing_customer_sap WHERE customer_key = ?"
    );
    $stmt->execute([$customerKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return $customer;
    }
    foreach (customer_sap_fields() as $field) {
        $value = trim((string) ($row[$field] ?? ""));
        if ($value !== "") {
            $customer[$field] = $value;
        }
    }
    // Affiliate flag drives the SAP Distribution Channel (30 affiliate / 20 not). A stored
    // row is authoritative for this even when the code fields are left blank.
    $customer["distribution_channel"] = billing_sap_distribution_channel((bool) $row["affiliate"]);
    return $customer;
}

/**
 * The code-config default for a customer's three SAP fields (before any override),
 * resolved from whichever pipeline owns the key. Used by the Master Data editor to
 * show finance what value is currently in effect / used as a placeholder.
 *
 * Requires the pipeline config helpers to be loaded by the caller.
 */
function billing_customer_sap_default(string $customerKey): array
{
    $blank = ["sold_to" => "", "material_code" => "", "profit_center" => "", "affiliate" => false];
    $pipeline = function_exists("unified_billing_pipeline") ? unified_billing_pipeline($customerKey) : "";

    $config = null;
    if ($pipeline === "matrix" && function_exists("box_banana_customer")) {
        $config = box_banana_customer($customerKey);
    } elseif ($pipeline === "sap" && function_exists("billing_customer")) {
        $config = billing_customer($customerKey);
    } elseif ($pipeline === "kds" && function_exists("abc_kds_customer")) {
        $config = abc_kds_customer($customerKey);
    } elseif ($pipeline === "shuttling" && function_exists("dict_shuttling_config")) {
        $config = dict_shuttling_config();
    }

    if (!is_array($config)) {
        return $blank + ["affiliate" => false];
    }
    return [
        "sold_to" => (string) ($config["sold_to"] ?? ""),
        "material_code" => (string) ($config["material_code"] ?? ""),
        "profit_center" => (string) ($config["profit_center"] ?? ""),
        // Default affiliate status inferred from the config's Distribution Channel (30 = affiliate).
        "affiliate" => trim((string) ($config["distribution_channel"] ?? "")) === "30",
    ];
}
