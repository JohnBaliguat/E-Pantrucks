<?php
include "../config/config.php";

$sql = "SELECT unit_name
        FROM units
        WHERE unit_name NOT LIKE 'GS%'
        ORDER BY unit_name ASC";
$stmt = $conn->query($sql);

$trailers = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $trailers[] = $row['unit_name'];
}

header('Content-Type: application/json');
echo json_encode($trailers);
