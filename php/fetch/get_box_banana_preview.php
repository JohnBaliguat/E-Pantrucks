<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/box_banana_customers.php";
require_once __DIR__ . "/../helpers/build_box_banana_billing.php";
require_once __DIR__ . "/../helpers/ensure_box_banana_schema.php";
require_once __DIR__ . "/../helpers/ensure_rate_fuel_schema.php";
require_once __DIR__ . "/../helpers/fuel_rate_engine.php";

header("Content-Type: application/json; charset=utf-8");
ensure_box_banana_schema($conn);
ensure_rate_fuel_schema($conn);

$customerKey = trim((string) ($_GET["customer"] ?? ""));
$dateFrom = trim((string) ($_GET["date_from"] ?? ""));
$dateTo = trim((string) ($_GET["date_to"] ?? ""));
$includeBilled = !empty($_GET["include_billed"]) && $_GET["include_billed"] !== "0";

$customer = box_banana_customer($customerKey);
if ($customer === null) {
    echo json_encode(["success" => false, "message" => "Unknown customer."]);
    exit();
}

if (
    $dateFrom === "" || $dateTo === "" ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ||
    $dateFrom > $dateTo
) {
    echo json_encode(["success" => false, "message" => "Invalid date range."]);
    exit();
}

$excludeEntryIds = [];
if (!$includeBilled) {
    $excludeEntryIds = array_map(
        "intval",
        $conn->query("SELECT DISTINCT entry_id FROM box_banana_statement_entries")->fetchAll(PDO::FETCH_COLUMN)
    );
}

try {
    $entries = box_banana_fetch_entries($conn, $customer, $dateFrom, $dateTo, $excludeEntryIds);
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Preview failed: " . $e->getMessage()]);
    exit();
}

// Flat fallback rate (used when no rate matrix is configured).
$flatRate = box_banana_rate($conn, (string) ($customer["rate_code"] ?? ""));

// Forex: USD customers (e.g. Sumifru) convert via the fuel-matrix dollar
// conversion, like the SAP customer billing; PHP customers use 1.
$currency = strtoupper((string) ($customer["document_currency"] ?? "PHP"));
$forex = $currency === "USD" ? billing_forex_rate($conn, $dateTo, $customerKey) : 1.0;
if ($currency === "USD" && $forex <= 0) {
    echo json_encode(["success" => false, "message" => "No Dollar Conversion (forex) set for USD billing. Add it on a Fuel Price row in Master Data."]);
    exit();
}
if ($forex <= 0) {
    $forex = 1.0;
}

$reference = ($customer["reference_prefix"] ?? $customer["label"]) . " - preview";
$built = box_banana_build_sap_rows($conn, $entries, $customer, $customerKey, $forex, $reference, $flatRate);

// SAP ZPSO columns — identical to the Customer Billing page.
$labels = billing_column_labels();
$columns = [];
foreach ($labels as $index => $label) {
    $columns[] = ["key" => "col_" . ($index + 1), "label" => $label !== "" ? $label : "PER UNIT"];
}

$formatCell = static function (array $cell): string {
    [$type, $value] = $cell;
    if ($value === null || $value === "") {
        return "";
    }
    if ($type === "dt") {
        $timestamp = (int) round((((float) $value) - 25569) * 86400);
        return $timestamp > 0 ? gmdate("Y-m-d", $timestamp) : "";
    }
    if ($type === "n") {
        return rtrim(rtrim(number_format((float) $value, 3, ".", ""), "0"), ".");
    }
    return billing_norm($value);
};

$records = [];
foreach ($built["rows"] as $index => $row) {
    $entryId = (int) ($built["entry_ids"][$index] ?? 0);
    $cells = [];
    foreach ($row as $cellIndex => $cell) {
        $cells[] = [
            "key" => $columns[$cellIndex]["key"] ?? ("col_" . ($cellIndex + 1)),
            "value" => $formatCell($cell),
        ];
    }
    $records[] = ["entry_id" => $entryId, "cells" => $cells];
}

echo json_encode([
    "success" => true,
    "columns" => $columns,
    "records" => $records,
    "count" => count($records),
    "rate" => $flatRate,
    "rate_code" => $customer["rate_code"] ?? "",
    "currency" => $currency,
    "forex" => $forex,
    "priced_via_matrix" => !empty($built["priced_via_matrix"]),
    "total" => $built["total"],
]);
exit();
?>
