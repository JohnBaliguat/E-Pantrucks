<?php
include "../config/config.php";

header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT trailer_id, trailer_name FROM trailer ORDER BY trailer_name ASC";
$stmt = $conn->query($sql);

$trailers = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $trailers[] = [
        'trailer_id' => (int) $row['trailer_id'],
        'trailer_name' => $row['trailer_name'],
    ];
}

echo json_encode($trailers);
