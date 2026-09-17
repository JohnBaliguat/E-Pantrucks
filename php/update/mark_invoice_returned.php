<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_customer_billing_schema.php";

date_default_timezone_set("Asia/Manila");
header("Content-Type: application/json; charset=utf-8");
ensure_customer_billing_schema($conn);

$invoiceId = (int) ($_POST["id"] ?? 0);
$returned = !empty($_POST["returned"]) && $_POST["returned"] !== "0";
$remarks = trim((string) ($_POST["remarks"] ?? ""));

if ($invoiceId <= 0) {
    echo json_encode(["success" => false, "message" => "Missing invoice id."]);
    exit();
}

$stmt = $conn->prepare(
    "UPDATE billing_invoices
     SET returned_at = " . ($returned ? "NOW()" : "NULL") . ",
         returned_remarks = ?
     WHERE invoice_id = ? AND status <> 'deleted'"
);
$stmt->execute([$returned ? ($remarks !== "" ? $remarks : null) : null, $invoiceId]);

if ($stmt->rowCount() === 0) {
    echo json_encode(["success" => false, "message" => "Invoice not found."]);
    exit();
}

echo json_encode([
    "success" => true,
    "message" => $returned ? "Marked as returned by customer." : "Cleared the returned marker.",
]);
exit();
?>
