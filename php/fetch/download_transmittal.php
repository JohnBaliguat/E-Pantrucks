<?php
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_transmittals_schema.php";
ensure_transmittals_schema($conn);

$id = (int) ($_GET["id"] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Missing or invalid transmittal id.";
    exit();
}

$stmt = $conn->prepare("
    SELECT file_name, file_path, file_size_bytes, expires_at, status
    FROM transmittals
    WHERE transmittal_id = ? AND status <> 'deleted'
    LIMIT 1
");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Transmittal not found.";
    exit();
}

if (strtotime((string) $row["expires_at"]) < time()) {
    http_response_code(410);
    header("Content-Type: text/plain; charset=utf-8");
    echo "This transmittal has expired.";
    exit();
}

$projectRoot = dirname(__DIR__, 2);
$absolute = $projectRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, (string) $row["file_path"]);

if (!is_file($absolute)) {
    http_response_code(404);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Transmittal file is missing.";
    exit();
}

header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header('Content-Disposition: attachment; filename="' . $row["file_name"] . '"');
header("Content-Length: " . (string) filesize($absolute));
header("Cache-Control: max-age=0");
readfile($absolute);
exit();
?>
