<?php
include "../config/config.php";

header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT unit_id, unit_name, unit_std, unit_model, unit_cluster
        FROM units
        WHERE unit_name LIKE 'GS%'
        ORDER BY unit_name ASC";
$stmt = $conn->query($sql);

$units = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $units[] = [
        'unit_id' => (int) $row['unit_id'],
        'unit_name' => $row['unit_name'],
        'unit_std' => $row['unit_std'],
        'unit_model' => $row['unit_model'],
        'unit_cluster' => $row['unit_cluster'],
    ];
}

echo json_encode($units);
