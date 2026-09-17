<?php

require_once __DIR__ . "/box_banana_customers.php";
require_once __DIR__ . "/billing_customers.php";
require_once __DIR__ . "/dry_van.php";

/**
 * The customers that can have a fuel-based rate matrix. Combines the
 * Box Bananas customers, the SAP customer-billing customers, and any
 * customer_key already present in rate_lane (so manually-added keys persist).
 * Returns [ key => label ].
 */
function rate_matrix_customers(PDO $conn): array
{
    $list = [];

    // SAP customer-billing customers first (Sumifru lives here, keyed
    // sumifru_containerized — the Box Bananas Sumifru shares this key).
    foreach (billing_customers_config() as $key => $cfg) {
        $list[$key] = ($cfg["label"] ?? $key) . " (Customer Billing)";
    }
    // Box Bananas customers, keyed by their matrix_key so Sumifru is not
    // duplicated (its matrix_key resolves to sumifru_containerized).
    foreach (box_banana_customers_config() as $key => $cfg) {
        $matrixKey = box_banana_matrix_key($key, $cfg);
        if (!isset($list[$matrixKey])) {
            $list[$matrixKey] = ($cfg["label"] ?? $key) . " (Box Bananas)";
        }
    }

    // Dry Van customers priced through the fuel Rate Matrix (rate_basis "matrix"),
    // e.g. CITIHARDWARE — so finance can enter their lanes here.
    if (function_exists("dry_van_config")) {
        // Friendly labels for SHARED dry-van matrices (several customers price off one key),
        // so the dropdown reads e.g. "Short Haul (Dry Vans)" instead of the first member's name.
        $sharedMatrixLabels = [
            "dryvan_shorthaul_shared" => "Short Haul",
        ];
        foreach (dry_van_config() as $key => $cfg) {
            $matrixKey = trim((string) ($cfg["matrix_key"] ?? $key)) ?: $key;
            if (($cfg["rate_basis"] ?? "") === "matrix" && !isset($list[$matrixKey])) {
                $label = $sharedMatrixLabels[$matrixKey] ?? ($cfg["label"] ?? $key);
                $list[$matrixKey] = $label . " (Dry Vans)";
            }
        }
    }

    // Flat, per-trip DICT services (no fuel) — their route rates are editable here.
    // Only Route + Base Rate apply (the grid hides the fuel columns for these keys).
    if (!isset($list["dict_van_shuttling"])) {
        $list["dict_van_shuttling"] = "DICT Van Shuttling (Flat / by trip)";
    }
    if (!isset($list["dict_industrial_waste"])) {
        $list["dict_industrial_waste"] = "DICT Industrial Waste (Flat / by trip)";
    }

    try {
        $existing = $conn->query("SELECT DISTINCT customer_key FROM rate_lane")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($existing as $key) {
            if ($key !== "" && !isset($list[$key])) {
                $list[$key] = $key;
            }
        }
    } catch (Throwable $e) {
        // table may not exist yet; ignore
    }

    return $list;
}
