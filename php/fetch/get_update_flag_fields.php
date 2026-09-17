<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_data_update_flags_schema.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header("Content-Type: application/json; charset=utf-8");
ensure_data_update_flags_schema($conn);

$encoderId = trim((string) ($_SESSION["user_idNumber"] ?? ""));
if ($encoderId === "") {
    echo json_encode(["success" => false, "message" => "Your session does not have an encoder ID."]);
    exit;
}

$flagId = (int) ($_GET["flag_id"] ?? 0);
if ($flagId <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid update flag."]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT f.entry_id, f.remarks, f.field_notes, f.status
     FROM data_update_flags f
     INNER JOIN operations o ON o.entry_id = f.entry_id
     WHERE f.flag_id = ? AND o.created_by = ?
     LIMIT 1"
);
$stmt->execute([$flagId, $encoderId]);
$flag = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$flag) {
    echo json_encode(["success" => false, "message" => "Update flag not found."]);
    exit;
}

echo json_encode([
    "success" => true,
    "entry_id" => (int) $flag["entry_id"],
    "remarks" => (string) ($flag["remarks"] ?? ""),
    "field_notes" => json_decode((string) ($flag["field_notes"] ?? "[]"), true) ?: [],
    "status" => (string) ($flag["status"] ?? "open"),
]);
