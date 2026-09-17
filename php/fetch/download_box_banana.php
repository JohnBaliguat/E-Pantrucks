<?php
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_box_banana_schema.php";
ensure_box_banana_schema($conn);

$id = (int) ($_GET["id"] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Missing or invalid statement id.";
    exit();
}

$stmt = $conn->prepare("SELECT file_name, file_path, status FROM box_banana_statements WHERE statement_id = ? AND status <> 'deleted' LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Statement not found.";
    exit();
}

$projectRoot = dirname(__DIR__, 2);
$absolute = $projectRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, (string) $row["file_path"]);
if (!is_file($absolute)) {
    http_response_code(404);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Statement file is missing.";
    exit();
}

header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header('Content-Disposition: attachment; filename="' . $row["file_name"] . '"');
header("Content-Length: " . (string) filesize($absolute));
header("Cache-Control: max-age=0");
readfile($absolute);
exit();
?>
