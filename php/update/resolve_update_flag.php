<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_data_update_flags_schema.php";

date_default_timezone_set("Asia/Manila");
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header("Content-Type: application/json; charset=utf-8");
ensure_data_update_flags_schema($conn);

$flagId = (int) ($_POST["flag_id"] ?? 0);
if ($flagId <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid flag id."]);
    exit();
}

$resolvedBy = $_SESSION["user_idNumber"] ?? ($_SESSION["user_name"] ?? "system");
$encoderId = trim((string) ($_SESSION["user_idNumber"] ?? ""));
$isAdmin = ucfirst(strtolower((string) ($_SESSION["user_type"] ?? ""))) === "Admin";
if (!$isAdmin && $encoderId === "") {
    echo json_encode(["success" => false, "message" => "Your session does not have an encoder ID."]);
    exit();
}

try {
    if ($isAdmin) {
        // Admins can resolve any open flag, regardless of who encoded the record.
        $stmt = $conn->prepare(
            "UPDATE data_update_flags SET status = 'resolved', resolved_by = ?, resolved_at = NOW()
             WHERE flag_id = ? AND status = 'open'"
        );
        $stmt->execute([$resolvedBy, $flagId]);
    } else {
        $stmt = $conn->prepare(
            "UPDATE data_update_flags SET status = 'resolved', resolved_by = ?, resolved_at = NOW()
             WHERE flag_id = ?
               AND status = 'open'
               AND EXISTS (
                   SELECT 1 FROM operations o
                   WHERE o.entry_id = data_update_flags.entry_id
                     AND (o.created_by = ? OR o.created_by = 'system')
               )"
        );
        $stmt->execute([$resolvedBy, $flagId, $encoderId]);
    }
    if ($stmt->rowCount() === 0) {
        echo json_encode(["success" => false, "message" => "Flag not found or already resolved."]);
        exit();
    }
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Resolve failed: " . $e->getMessage()]);
    exit();
}

echo json_encode(["success" => true, "message" => "Marked as resolved."]);
exit();
?>
