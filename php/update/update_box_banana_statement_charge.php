<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_box_banana_schema.php";

date_default_timezone_set("Asia/Manila");
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header("Content-Type: application/json; charset=utf-8");
ensure_box_banana_schema($conn);

// Edit ONE Box Bananas statement entry's locked peso charge. DB value only — the
// already-downloaded file is unchanged. Mirrors update_invoice_entry_charge.php.
$statementId = (int) ($_POST["invoice_id"] ?? $_POST["statement_id"] ?? 0);
$entryId = (int) ($_POST["entry_id"] ?? 0);
$raw = $_POST["rate_charge"] ?? "";

if ($statementId <= 0 || $entryId <= 0) {
    echo json_encode(["success" => false, "message" => "Missing statement or entry id."]);
    exit();
}
if (!is_numeric($raw) || (float) $raw < 0) {
    echo json_encode(["success" => false, "message" => "Enter a valid charge (0 or more)."]);
    exit();
}
$charge = round((float) $raw, 2);

$chk = $conn->prepare("SELECT status FROM box_banana_statements WHERE statement_id = ?");
$chk->execute([$statementId]);
$status = $chk->fetchColumn();
if ($status === false || $status === "deleted") {
    echo json_encode(["success" => false, "message" => "Statement not found."]);
    exit();
}

$by = $_SESSION["user_idNumber"] ?? ($_SESSION["user_name"] ?? "system");
$upd = $conn->prepare(
    "UPDATE box_banana_statement_entries
     SET rate_charge = ?, manual_charge = TRUE, charge_updated_at = NOW(), charge_updated_by = ?
     WHERE statement_id = ? AND entry_id = ?"
);
$upd->execute([$charge, $by, $statementId, $entryId]);
if ($upd->rowCount() === 0) {
    echo json_encode(["success" => false, "message" => "That entry is not part of this statement."]);
    exit();
}

$tot = $conn->prepare("SELECT COALESCE(SUM(rate_charge), 0) FROM box_banana_statement_entries WHERE statement_id = ?");
$tot->execute([$statementId]);
$total = (float) $tot->fetchColumn();

echo json_encode([
    "success" => true,
    "message" => "Charge updated.",
    "rate_charge" => $charge,
    "total" => round($total, 2),
]);
exit();
