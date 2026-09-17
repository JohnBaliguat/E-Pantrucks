<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/custom_billing_customers.php";
require_once __DIR__ . "/../helpers/customer_sap_codes.php";
require_once __DIR__ . "/../helpers/box_banana_customers.php";

header("Content-Type: application/json; charset=utf-8");
ensure_custom_customer_schema($conn);

// Ensure the built-in Box Banana customers are seeded into the DB so they appear in
// the editable list (rename / VAT) alongside user-added ones.
seed_box_banana_builtins($conn, box_banana_builtin_customers());

// Existing user-defined customers (raw stored columns, for the edit form).
$rows = [];
try {
    $rows = $conn->query(
        "SELECT * FROM billing_custom_customer ORDER BY label"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rows = [];
}

// Material Code + Profit Center pickers, same sources as the SAP Codes tab.
$options = customer_sap_option_lists($conn);

// Segments already present in operations, to help finance match trip selection.
$segments = [];
try {
    $sg = $conn->query(
        "SELECT DISTINCT segment FROM operations
         WHERE TRIM(COALESCE(segment, '')) <> ''
         ORDER BY segment"
    )->fetchAll(PDO::FETCH_COLUMN);
    $segments = array_map("strval", $sg);
} catch (Throwable $e) {
    $segments = [];
}

echo json_encode([
    "success" => true,
    "customers" => $rows,
    "defaults" => custom_customer_defaults(),
    "materials" => $options["materials"],
    "profit_centers" => $options["profit_centers"],
    "segments" => $segments,
]);
exit();
