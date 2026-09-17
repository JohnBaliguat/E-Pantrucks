<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_data_update_flags_schema.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header("Content-Type: application/json; charset=utf-8");
ensure_data_update_flags_schema($conn);

// Mark the current user's resolved-flag notifications as seen (clears the badge).
// Admins acknowledge every resolved flag, matching their all-records view.
$me = (string) ($_SESSION["user_idNumber"] ?? ($_SESSION["user_name"] ?? ""));
$isAdmin = ucfirst(strtolower((string) ($_SESSION["user_type"] ?? ""))) === "Admin";
if (!$isAdmin && $me === "") {
    echo json_encode(["success" => true]);
    exit();
}

try {
    if ($isAdmin) {
        $conn->prepare(
            "UPDATE data_update_flags SET flagger_seen = TRUE
             WHERE status = 'resolved' AND flagger_seen IS NOT TRUE"
        )->execute();
    } else {
        $conn->prepare(
            "UPDATE data_update_flags SET flagger_seen = TRUE
             WHERE status = 'resolved' AND flagged_by = ? AND flagger_seen IS NOT TRUE"
        )->execute([$me]);
    }
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
    exit();
}

echo json_encode(["success" => true]);
exit();
?>
