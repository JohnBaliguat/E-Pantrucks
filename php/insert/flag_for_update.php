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

$entryId = (int) ($_POST["entry_id"] ?? 0);
$remarks = trim((string) ($_POST["remarks"] ?? ""));
$fieldNotes = json_decode((string) ($_POST["field_notes"] ?? "[]"), true);
if (!is_array($fieldNotes)) {
    $fieldNotes = [];
}
$fieldNotes = array_values(array_filter(array_map(static function ($note) {
    if (!is_array($note)) return null;
    $field = trim((string) ($note["field"] ?? ""));
    $message = trim((string) ($note["message"] ?? ""));
    return $field !== "" && $message !== "" ? ["field" => $field, "message" => $message] : null;
}, $fieldNotes)));
if ($entryId <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid entry id."]);
    exit();
}
if ($remarks === "") {
    echo json_encode(["success" => false, "message" => "Please enter a remark describing what needs updating."]);
    exit();
}

// Pull the record's type/segment so the queue can route to the right entry form.
$row = $conn->prepare("SELECT entry_type, segment FROM operations WHERE entry_id = ? LIMIT 1");
$row->execute([$entryId]);
$op = $row->fetch(PDO::FETCH_ASSOC);
if (!$op) {
    echo json_encode(["success" => false, "message" => "Record not found."]);
    exit();
}

$flaggedBy = $_SESSION["user_idNumber"] ?? ($_SESSION["user_name"] ?? "system");

try {
    // If an open flag already exists for this record, update its remark instead
    // of stacking duplicates.
    $existing = $conn->prepare("SELECT flag_id FROM data_update_flags WHERE entry_id = ? AND status = 'open' LIMIT 1");
    $existing->execute([$entryId]);
    $existingId = $existing->fetchColumn();

    if ($existingId) {
        $conn->prepare("UPDATE data_update_flags SET remarks = ?, field_notes = ?::jsonb, flagged_by = ?, flagged_at = NOW() WHERE flag_id = ?")
             ->execute([$remarks, json_encode($fieldNotes), $flaggedBy, (int) $existingId]);
        $flagId = (int) $existingId;
        $message = "Flag updated.";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO data_update_flags (flag_id, entry_id, entry_type, segment, remarks, field_notes, status, flagged_by)
             VALUES (COALESCE((SELECT MAX(flag_id) FROM data_update_flags), 0) + 1, ?, ?, ?, ?, ?::jsonb, 'open', ?)
             RETURNING flag_id"
        );
        $stmt->execute([$entryId, $op["entry_type"] ?? "", $op["segment"] ?? "", $remarks, json_encode($fieldNotes), $flaggedBy]);
        $flagId = (int) $stmt->fetchColumn();
        $message = "Flagged for update.";
    }
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Could not save flag: " . $e->getMessage()]);
    exit();
}

echo json_encode(["success" => true, "message" => $message, "flag_id" => $flagId]);
exit();
?>
