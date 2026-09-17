<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_customer_billing_schema.php";

date_default_timezone_set("Asia/Manila");
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header("Content-Type: application/json; charset=utf-8");
ensure_customer_billing_schema($conn);

// Edit ONE invoice entry's locked peso charge. Marks it manual_charge and stamps
// who/when. Only touches the DB value — the already-downloaded file is unchanged.
$invoiceId = (int) ($_POST["invoice_id"] ?? 0);
$entryId = (int) ($_POST["entry_id"] ?? 0);
$raw = $_POST["rate_charge"] ?? "";

if ($invoiceId <= 0 || $entryId <= 0) {
    echo json_encode(["success" => false, "message" => "Missing invoice or entry id."]);
    exit();
}
if (!is_numeric($raw) || (float) $raw < 0) {
    echo json_encode(["success" => false, "message" => "Enter a valid charge (0 or more)."]);
    exit();
}
$charge = round((float) $raw, 2);

$chk = $conn->prepare("SELECT status FROM billing_invoices WHERE invoice_id = ?");
$chk->execute([$invoiceId]);
$status = $chk->fetchColumn();
if ($status === false || $status === "deleted") {
    echo json_encode(["success" => false, "message" => "Invoice not found."]);
    exit();
}

$by = $_SESSION["user_idNumber"] ?? ($_SESSION["user_name"] ?? "system");
$upd = $conn->prepare(
    "UPDATE billing_invoice_entries
     SET rate_charge = ?, manual_charge = TRUE, charge_updated_at = NOW(), charge_updated_by = ?
     WHERE invoice_id = ? AND entry_id = ?"
);
$upd->execute([$charge, $by, $invoiceId, $entryId]);
if ($upd->rowCount() === 0) {
    echo json_encode(["success" => false, "message" => "That entry is not part of this invoice."]);
    exit();
}

$tot = $conn->prepare("SELECT COALESCE(SUM(rate_charge), 0) FROM billing_invoice_entries WHERE invoice_id = ?");
$tot->execute([$invoiceId]);
$total = (float) $tot->fetchColumn();

echo json_encode([
    "success" => true,
    "message" => "Charge updated.",
    "rate_charge" => $charge,
    "total" => round($total, 2),
]);
exit();
