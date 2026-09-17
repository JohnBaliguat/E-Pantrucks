<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_data_update_flags_schema.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header("Content-Type: application/json; charset=utf-8");
ensure_data_update_flags_schema($conn);

// Given a set of entry_ids (the records the billing preview currently shows as
// flagged), return the subset that still has an OPEN flag. The preview uses the
// difference to auto-clear the flag icon on records that were resolved elsewhere
// (on the For Update page) without needing a full re-preview.
$raw = trim((string) ($_GET["entry_ids"] ?? ""));
$ids = array_values(array_unique(array_filter(array_map(
    static fn($v) => (int) trim($v),
    $raw === "" ? [] : explode(",", $raw)
), static fn($v) => $v > 0)));

if (empty($ids)) {
    echo json_encode(["success" => true, "open_entry_ids" => []]);
    exit();
}

$placeholders = implode(",", array_fill(0, count($ids), "?"));
$stmt = $conn->prepare(
    "SELECT DISTINCT entry_id
     FROM data_update_flags
     WHERE status = 'open' AND entry_id IN ($placeholders)"
);
$stmt->execute($ids);

$open = array_map(static fn($r) => (int) $r["entry_id"], $stmt->fetchAll(PDO::FETCH_ASSOC));

echo json_encode(["success" => true, "open_entry_ids" => $open]);
exit();
?>
