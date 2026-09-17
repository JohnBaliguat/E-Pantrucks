<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . '/../helpers/master_settings.php';
require_once __DIR__ . '/../helpers/db_value.php';
require_once __DIR__ . '/../helpers/ensure_rate_fuel_schema.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set("Asia/Manila");
ensure_rate_fuel_schema($conn);
ensure_master_settings_customer_tag($conn);
ensure_sku_route_schema($conn);
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

$validation = master_settings_validate_payload($entity, $_POST);
if (!$validation['valid']) {
    echo json_encode(['success' => false, 'message' => $validation['message']]);
    exit;
}

$values = $validation['values'];
$fields = array_keys($values);
$assignments = implode(', ', array_map(static function ($field) {
    return '"' . $field . '" = ?';
}, $fields));

$sql = sprintf(
    'UPDATE "%s" SET %s WHERE "%s" = ?',
    $definition['table'],
    $assignments,
    $definition['primary_key']
);

$bindValues = db_nullable_all(array_values($values));
$bindValues[] = $id;

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($bindValues);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => $definition['label'] . ' could not be updated: ' . $e->getMessage(),
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => $definition['label'] . ' updated successfully.',
]);
exit;
?>
