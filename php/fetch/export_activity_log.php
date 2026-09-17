<?php
include "../config/config.php";
require_once "../helpers/xlsx_helper.php";
require_once "../helpers/activity_log_view.php";

$filters = activity_log_filters_from_input($_GET);
[$where, $params] = activity_log_build_where($filters);

$sql = 'SELECT activity_id, created_at, user_name, user_id_number, user_type, activity_type, activity_label,
               request_method, route_name, ip_address, device_name, browser_name, os_name,
               client_device_name, context_summary, details_json
        FROM user_activity_log';
if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC, activity_id DESC';

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$headers = [
    "ID",
    "Timestamp",
    "User",
    "ID Number",
    "User Type",
    "Activity Type",
    "Activity",
    "Details",
    "Method",
    "Route",
    "IP Address",
    "Device",
    "Browser",
    "OS",
];

$excelRows = [];
foreach ($rows as $row) {
    $excelRows[] = [
        (string) ($row["activity_id"] ?? ""),
        (string) ($row["created_at"] ?? ""),
        (string) ($row["user_name"] ?? ""),
        (string) ($row["user_id_number"] ?? ""),
        (string) ($row["user_type"] ?? ""),
        activity_log_type_label($row),
        (string) ($row["activity_label"] ?? ""),
        activity_log_human_details($row),
        (string) ($row["request_method"] ?? ""),
        (string) ($row["route_name"] ?? ""),
        (string) ($row["ip_address"] ?? ""),
        (string) ($row["device_name"] ?? ""),
        (string) ($row["browser_name"] ?? ""),
        (string) ($row["os_name"] ?? ""),
    ];
}

$xlsx = xlsx_create_with_rows($headers, $excelRows);
if ($xlsx === "") {
    http_response_code(500);
    echo "Failed to generate activity log export.";
    exit();
}

$fileNameParts = ["activity-log"];
if ($filters["date_from"] !== "") {
    $fileNameParts[] = $filters["date_from"];
}
if ($filters["date_to"] !== "") {
    $fileNameParts[] = $filters["date_to"];
}
$fileName = implode("_", $fileNameParts) . ".xlsx";

header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header("Content-Length: " . strlen($xlsx));
echo $xlsx;
exit();
