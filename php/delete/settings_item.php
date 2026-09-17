<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . '/../helpers/master_settings.php';

header('Content-Type: application/json; charset=utf-8');

$entity = trim((string) ($_POST['entity'] ?? ''));
$definition = master_settings_entity($entity);

if ($definition === null) {
    echo json_encode(['success' => false, 'message' => 'Invalid settings entity.']);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid record ID.']);
    exit;
}

$sql = sprintf(
    'DELETE FROM "%s" WHERE "%s" = ?',
    $definition['table'],
    $definition['primary_key']
);

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => $definition['label'] . ' could not be deleted: ' . $e->getMessage(),
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => $definition['label'] . ' deleted successfully.',
]);
exit;
?>
