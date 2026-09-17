<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_box_banana_schema.php";
header("Content-Type: application/json; charset=utf-8");
ensure_box_banana_schema($conn);

$id = (int) ($_POST["id"] ?? 0);
if ($id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid statement id."]);
    exit();
}

$stmt = $conn->prepare("SELECT file_path FROM box_banana_statements WHERE statement_id = ? LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo json_encode(["success" => false, "message" => "Statement not found."]);
    exit();
}

$projectRoot = dirname(__DIR__, 2);
$absolute = $projectRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, (string) $row["file_path"]);
if (is_file($absolute)) {
    @unlink($absolute);
}

try {
    $conn->beginTransaction();
    // Entries cascade on delete, which frees those trips to be billed again.
    $conn->prepare("DELETE FROM box_banana_statement_entries WHERE statement_id = ?")->execute([$id]);
    $conn->prepare("DELETE FROM box_banana_statements WHERE statement_id = ?")->execute([$id]);
    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(["success" => false, "message" => "Delete failed: " . $e->getMessage()]);
    exit();
}

echo json_encode(["success" => true, "message" => "Statement deleted. Its trips can be billed again."]);
exit();
?>
