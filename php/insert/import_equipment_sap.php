<?php
require_once __DIR__ . '/../helpers/json_error_guard.php';
include '../config/config.php';
require_once __DIR__ . '/../helpers/master_settings.php';
require_once __DIR__ . '/../helpers/db_value.php';
require_once __DIR__ . '/../helpers/xlsx_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$response = ['success' => false, 'message' => '', 'imported' => 0, 'updated' => 0, 'errors' => []];
if (!isset($_SESSION['user_idNumber'])) {
    $response['message'] = 'Unauthorized.';
    echo json_encode($response);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['xlsx_file']) || $_FILES['xlsx_file']['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = 'Select an Excel (.xlsx) file to import.';
    echo json_encode($response);
    exit;
}
if (strtolower(pathinfo($_FILES['xlsx_file']['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
    $response['message'] = 'Only .xlsx files are accepted.';
    echo json_encode($response);
    exit;
}

ensure_sku_route_schema($conn);
$rows = xlsx_read_rows($_FILES['xlsx_file']['tmp_name']);
if (count($rows) < 4) {
    $response['message'] = 'The workbook has no equipment data rows.';
    echo json_encode($response);
    exit;
}

$normalizeUnit = static function ($value): string {
    $value = strtoupper(trim((string) $value));
    if ($value !== '' && ctype_digit($value)) {
        $value = ltrim($value, '0');
        return $value === '' ? '0' : $value;
    }
    return $value;
};

// The supplied workbook has four side-by-side UNIT / SAP CODE / STAT_IO blocks.
$blocks = [
    ['type' => 'PM', 'unit' => 0, 'sap' => 1, 'description' => 2],
    ['type' => 'TR', 'unit' => 4, 'sap' => 5, 'description' => 6],
    ['type' => 'RV', 'unit' => 8, 'sap' => 9, 'description' => 10],
    ['type' => 'GS', 'unit' => 12, 'sap' => 13, 'description' => 14],
];

$existing = [];
foreach ($conn->query('SELECT id, equipment_type, unit_no, sap_equipment_code, description FROM equipment_sap') as $item) {
    $key = strtoupper(trim((string) $item['equipment_type'])) . '|' . $normalizeUnit($item['unit_no']);
    if (!isset($existing[$key])) $existing[$key] = $item;
}

$nextId = (int) $conn->query('SELECT COALESCE(MAX(id), 0) + 1 FROM equipment_sap')->fetchColumn();
$insert = $conn->prepare('INSERT INTO equipment_sap (id, equipment_type, unit_no, sap_equipment_code, description) VALUES (?, ?, ?, ?, ?)');
$update = $conn->prepare('UPDATE equipment_sap SET unit_no = ?, sap_equipment_code = ?, description = ? WHERE id = ?');
$seen = [];

foreach (array_slice($rows, 3) as $rowOffset => $row) {
    foreach ($blocks as $block) {
        $unit = trim((string) ($row[$block['unit']] ?? ''));
        $sap = trim((string) ($row[$block['sap']] ?? ''));
        $description = trim((string) ($row[$block['description']] ?? ''));
        if ($unit === '' && $sap === '' && $description === '') continue;
        if ($unit === '' || $sap === '') {
            $response['errors'][] = 'Row ' . ($rowOffset + 4) . ' (' . $block['type'] . '): Unit No and SAP Code are required.';
            continue;
        }

        $key = $block['type'] . '|' . $normalizeUnit($unit);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;

        try {
            if (isset($existing[$key])) {
                $current = $existing[$key];
                if ((string) $current['sap_equipment_code'] !== $sap || (string) $current['description'] !== $description || (string) $current['unit_no'] !== $unit) {
                    $update->execute(db_nullable_all([$unit, $sap, $description, $current['id']]));
                    $response['updated']++;
                }
                continue;
            }
            $insert->execute(db_nullable_all([$nextId++, $block['type'], $unit, $sap, $description]));
            $response['imported']++;
        } catch (PDOException $e) {
            $response['errors'][] = 'Row ' . ($rowOffset + 4) . ' (' . $block['type'] . '): ' . $e->getMessage();
        }
    }
}

$changed = $response['imported'] + $response['updated'];
$response['success'] = $changed > 0;
$response['message'] = $changed > 0
    ? 'Imported ' . $response['imported'] . ' new and updated ' . $response['updated'] . ' equipment SAP code(s).'
    : 'No equipment SAP codes were imported.';
echo json_encode($response);
