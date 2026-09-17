<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_transmittals_schema.php";
header("Content-Type: application/json; charset=utf-8");
ensure_transmittals_schema($conn);

$id = (int) ($_POST["id"] ?? 0);
if ($id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid transmittal id."]);
    exit();
}

$stmt = $conn->prepare("SELECT file_path FROM transmittals WHERE transmittal_id = ? LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(["success" => false, "message" => "Transmittal not found."]);
    exit();
}

$projectRoot = dirname(__DIR__, 2);
$absolute = $projectRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, (string) $row["file_path"]);
if (is_file($absolute)) {
    @unlink($absolute);
}

try {
    $conn->beginTransaction();
    $conn->prepare("DELETE FROM transmittal_entries WHERE transmittal_id = ?")->execute([$id]);
    $conn->prepare("DELETE FROM transmittals WHERE transmittal_id = ?")->execute([$id]);
    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(["success" => false, "message" => "Delete failed: " . $e->getMessage()]);
    exit();
}

echo json_encode(["success" => true, "message" => "Transmittal deleted."]);
exit();
?>
