<?php
include "../config/config.php";
require_once __DIR__ . '/../helpers/master_settings.php';
require_once __DIR__ . '/../helpers/ensure_rate_fuel_schema.php';

header('Content-Type: application/json; charset=utf-8');
ensure_rate_fuel_schema($conn);
ensure_master_settings_customer_tag($conn);
ensure_sku_route_schema($conn);

$config = master_settings_config();
$response = [
    'success' => true,
    'data' => [],
];

foreach ($config as $entity => $definition) {
    $selectFields = array_merge([$definition['primary_key']], array_keys($definition['fields']));
    $fieldSql = implode(', ', array_map(static function ($field) {
        return '"' . $field . '"';
    }, $selectFields));

    $sql = sprintf(
        'SELECT %s FROM "%s" ORDER BY %s',
        $fieldSql,
        $definition['table'],
        $definition['default_sort']
    );

    $stmt = $conn->query($sql);
    $rows = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rows[] = $row;
    }

    $response['data'][$entity] = $rows;
}

echo json_encode($response);
exit;
?>
