<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/custom_billing_customers.php";

header("Content-Type: application/json; charset=utf-8");
ensure_custom_customer_schema($conn);

$key = trim((string) ($_POST["customer_key"] ?? ""));
if ($key === "") {
    echo json_encode(["success" => false, "message" => "No customer specified."]);
    exit();
}

// Only user-added customers can be deleted here.
$cfg = custom_billing_customers_config($conn);
if (!array_key_exists($key, $cfg)) {
    echo json_encode(["success" => false, "message" => "\"$key\" is not a user-added customer."]);
    exit();
}
// Built-in Box Banana customers are seeded into the DB but must not be removed — their
// pipeline wiring lives in code. Finance may rename them or toggle VAT, not delete them.
if (!empty($cfg[$key]["builtin"])) {
    echo json_encode(["success" => false, "message" => "\"" . ($cfg[$key]["label"] ?? $key) . "\" is a built-in customer and cannot be deleted."]);
    exit();
}

// Keep history intact: refuse to delete a customer that already has invoices.
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM billing_invoices WHERE customer_key = ? AND status <> 'deleted'");
    $stmt->execute([$key]);
    $count = (int) $stmt->fetchColumn();
    if ($count > 0) {
        echo json_encode([
            "success" => false,
            "message" => "Cannot delete — this customer has $count invoice(s). Delete or return those first.",
        ]);
        exit();
    }
} catch (Throwable $e) {
    // If the invoices table is unavailable, fall through to the delete.
}

try {
    $conn->prepare("DELETE FROM billing_custom_customer WHERE customer_key = ?")->execute([$key]);
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Delete failed: " . $e->getMessage()]);
    exit();
}

echo json_encode(["success" => true, "message" => "Customer removed."]);
exit();
