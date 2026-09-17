<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_customer_billing_schema.php";
header("Content-Type: application/json; charset=utf-8");
ensure_customer_billing_schema($conn);

$id = (int) ($_POST["id"] ?? 0);
if ($id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid invoice id."]);
    exit();
}

$stmt = $conn->prepare("SELECT file_path FROM billing_invoices WHERE invoice_id = ? LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo json_encode(["success" => false, "message" => "Invoice not found."]);
    exit();
}

$projectRoot = dirname(__DIR__, 2);
$absolute = $projectRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, (string) $row["file_path"]);
if (is_file($absolute)) {
    @unlink($absolute);
}
// Remove the sibling CSV, if one was generated.
$csvAbsolute = preg_replace('/\.xlsx$/i', ".csv", $absolute);
if ($csvAbsolute !== null && $csvAbsolute !== $absolute && is_file($csvAbsolute)) {
    @unlink($csvAbsolute);
}

try {
    $conn->beginTransaction();
    // Entries cascade on delete, which frees those trips to be billed again.
    $conn->prepare("DELETE FROM billing_invoice_entries WHERE invoice_id = ?")->execute([$id]);
    $conn->prepare("DELETE FROM billing_invoices WHERE invoice_id = ?")->execute([$id]);
    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(["success" => false, "message" => "Delete failed: " . $e->getMessage()]);
    exit();
}

echo json_encode(["success" => true, "message" => "Invoice deleted. Its trips can be billed again."]);
exit();
?>
