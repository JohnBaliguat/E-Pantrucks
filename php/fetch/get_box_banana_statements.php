<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_box_banana_schema.php";
date_default_timezone_set("Asia/Manila");
header("Content-Type: application/json; charset=utf-8");
ensure_box_banana_schema($conn);

$rows = $conn->query("
    SELECT statement_id, customer_key, customer_label, reference, date_from, date_to,
           total_amount, total_boxes, file_name, file_path, file_size_bytes, line_count,
           requested_by, requested_at, status
    FROM box_banana_statements
    WHERE status <> 'deleted'
    ORDER BY requested_at DESC, statement_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$projectRoot = dirname(__DIR__, 2);
$statements = [];
foreach ($rows as $row) {
    $absolute = $projectRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $row["file_path"]);
    $status = $row["status"];
    if ($status === "ready" && !is_file($absolute)) {
        $status = "missing";
    }
    $statements[] = [
        "statement_id" => (int) $row["statement_id"],
        "customer_label" => $row["customer_label"],
        "reference" => $row["reference"],
        "date_from" => $row["date_from"],
        "date_to" => $row["date_to"],
        "total_amount" => $row["total_amount"],
        "total_boxes" => $row["total_boxes"],
        "file_name" => $row["file_name"],
        "file_size_bytes" => (int) $row["file_size_bytes"],
        "line_count" => (int) $row["line_count"],
        "requested_by" => $row["requested_by"],
        "requested_at" => $row["requested_at"],
        "status" => $status,
    ];
}

echo json_encode(["success" => true, "statements" => $statements]);
exit();
?>
