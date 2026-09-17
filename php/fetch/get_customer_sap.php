<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/unified_billing.php";
require_once __DIR__ . "/../helpers/billing_customers.php";
require_once __DIR__ . "/../helpers/box_banana_customers.php";
require_once __DIR__ . "/../helpers/abc_kds.php";
require_once __DIR__ . "/../helpers/dict_shuttling.php";
require_once __DIR__ . "/../helpers/customer_sap_codes.php";

header("Content-Type: application/json; charset=utf-8");
ensure_customer_sap_schema($conn);

require_once __DIR__ . "/../helpers/custom_billing_customers.php";

$overrides = customer_sap_overrides($conn);
$groups = function_exists("unified_billing_customers_grouped") ? unified_billing_customers_grouped() : [];

// DB-backed customers (Box Banana built-ins seeded + user-added). Drives the VAT flag
// and whether a row is editable/deletable from this tab. Seed first so built-ins are
// flagged on the very first load, independent of the grouping call above.
$seeded = seed_box_banana_builtins($conn, box_banana_builtin_customers());
$customRows = custom_billing_customers_config($conn, $seeded > 0);

$rows = [];
if (!empty($groups)) {
    foreach ($groups as $groupLabel => $members) {
        foreach ($members as $key => $cfg) {
            $rows[] = ["key" => $key, "label" => $cfg["label"] ?? $key, "group" => $groupLabel];
        }
    }
} else {
    foreach (unified_billing_customers() as $key => $cfg) {
        $rows[] = ["key" => $key, "label" => $cfg["label"] ?? $key, "group" => ""];
    }
}

// Effective value = stored override (non-empty) else the code-config default. `stored`
// is what finance actually typed (drives the input); `default` is the code fallback
// (shown as a placeholder so blanks reveal what SAP would use if left empty).
$values = [];
foreach ($rows as $r) {
    $key = $r["key"];
    $default = billing_customer_sap_default($key);
    $hasRow = isset($overrides[$key]);
    $stored = $overrides[$key] ?? ["sold_to" => "", "material_code" => "", "profit_center" => "", "affiliate" => false];
    $custom = $customRows[$key] ?? null;
    $values[$key] = [
        "stored" => $stored,
        "default" => $default,
        // Effective affiliate = stored row's flag if a row exists, else the config default.
        "affiliate" => $hasRow ? (bool) $stored["affiliate"] : (bool) $default["affiliate"],
        // VAT flag + provenance. `custom` = editable/DB-backed here; `builtin` = seeded
        // Box Banana customer (rename/VAT only, not deletable). `vat_editable` mirrors
        // custom since only DB-backed (Box Banana) rows can carry the flag today.
        "is_vat" => $custom ? !empty($custom["is_vat"]) : false,
        "custom" => $custom !== null,
        "builtin" => $custom ? !empty($custom["builtin"]) : false,
        "vat_editable" => $custom !== null,
    ];
}

$options = customer_sap_option_lists($conn);

echo json_encode([
    "success" => true,
    "customers" => $rows,
    "values" => $values,
    "materials" => $options["materials"],
    "profit_centers" => $options["profit_centers"],
]);
exit();
