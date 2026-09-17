<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . '/../helpers/master_settings.php';
require_once __DIR__ . '/../helpers/db_value.php';
require_once __DIR__ . '/../helpers/ensure_rate_fuel_schema.php';
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json; charset=utf-8');
ensure_rate_fuel_schema($conn);
ensure_master_settings_customer_tag($conn);
ensure_sku_route_schema($conn);

$entity = trim((string) ($_POST['entity'] ?? ''));
$definition = master_settings_entity($entity);

if ($definition === null) {
    echo json_encode(['success' => false, 'message' => 'Invalid settings entity.']);
    exit;
}

$validation = master_settings_validate_payload($entity, $_POST);
if (!$validation['valid']) {
    echo json_encode(['success' => false, 'message' => $validation['message']]);
    exit;
}

$values = $validation['values'];
$fields = array_keys($values);
$pk = $definition['primary_key'];
$table = $definition['table'];

$columnList = array_map(static function ($field) {
    return '"' . $field . '"';
}, $fields);
array_unshift($columnList, '"' . $pk . '"');
$columnsSql = implode(', ', $columnList);

$placeholders = array_fill(0, count($fields), '?');
array_unshift(
    $placeholders,
    sprintf('COALESCE((SELECT MAX("%s") FROM "%s"), 0) + 1', $pk, $table)
);
$placeholdersSql = implode(', ', $placeholders);

$sql = sprintf(
    'INSERT INTO "%s" (%s) VALUES (%s) RETURNING "%s"',
    $table,
    $columnsSql,
    $placeholdersSql,
    $pk
);

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute(db_nullable_all(array_values($values)));
    $newId = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => $definition['label'] . ' could not be saved: ' . $e->getMessage(),
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => $definition['label'] . ' added successfully.',
    'id' => $newId,
]);
exit;
?>
