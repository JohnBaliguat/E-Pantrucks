<?php
include "../config/config.php";

header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT location_id, location_name FROM location ORDER BY location_name ASC";
$stmt = $conn->query($sql);

$rows = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $rows[] = [
        'location_id' => (int) $row['location_id'],
        'location_name' => $row['location_name'],
    ];
}

echo json_encode($rows);
