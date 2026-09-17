<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_transmittals_schema.php";
date_default_timezone_set("Asia/Manila");
header("Content-Type: application/json; charset=utf-8");

ensure_transmittals_schema($conn);

// Mark transmittals whose file is gone or whose expiry passed as 'expired'.
// Cheap to do on each list call; no scheduled job needed.
$conn->exec("
    UPDATE transmittals
    SET status = 'expired'
    WHERE status = 'ready' AND expires_at < NOW()
");

$rows = $conn->query("
    SELECT transmittal_id, label, entry_type, date_from, date_to, customer_filter,
           include_transmitted, file_name, file_path, file_size_bytes, record_count,
           requested_by, requested_at, expires_at, status
    FROM transmittals
    WHERE status <> 'deleted'
    ORDER BY requested_at DESC, transmittal_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$transmittals = [];
$projectRoot = dirname(__DIR__, 2);

foreach ($rows as $row) {
    $absolute = $projectRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $row["file_path"]);
    $fileExists = is_file($absolute);
    $status = $row["status"];
    if ($status === "ready" && !$fileExists) {
        $status = "missing";
    }
    $transmittals[] = [
        "transmittal_id" => (int) $row["transmittal_id"],
        "label" => $row["label"],
        "entry_type" => $row["entry_type"],
        "date_from" => $row["date_from"],
        "date_to" => $row["date_to"],
        "customer_filter" => $row["customer_filter"],
        "include_transmitted" => in_array(strtolower((string) $row["include_transmitted"]), ["t", "true", "1"], true),
        "file_name" => $row["file_name"],
        "file_size_bytes" => (int) $row["file_size_bytes"],
        "record_count" => (int) $row["record_count"],
        "requested_by" => $row["requested_by"],
        "requested_at" => $row["requested_at"],
        "expires_at" => $row["expires_at"],
        "status" => $status,
    ];
}

echo json_encode(["success" => true, "transmittals" => $transmittals]);
exit();
?>
