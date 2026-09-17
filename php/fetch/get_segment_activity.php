<?php
include "../config/config.php";

$query = "SELECT DISTINCT id, segment, activity, \"baseRate\", additional, \"totalRates\" FROM trip_rates ORDER BY segment ASC, activity ASC";

$stmt = $conn->query($query);

if (!$stmt) {
    echo json_encode([]);
    exit();
}

$data = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $data[] = [
        'id' => $row['id'],
        'segment' => $row['segment'],
        'activity' => $row['activity'],
        'baseRate' => $row['baseRate'],
        'additional' => $row['additional'],
        'totalRates' => $row['totalRates']
    ];
}

header('Content-Type: application/json');
echo json_encode($data);
?>
