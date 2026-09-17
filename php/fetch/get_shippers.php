<?php
include "../config/config.php";

$query = "SELECT DISTINCT sku_shipper_segment FROM sku WHERE sku_shipper_segment IS NOT NULL AND sku_shipper_segment != '' ORDER BY sku_shipper_segment ASC";
$stmt = $conn->query($query);

$shippers = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $shippers[] = [
        'shipper' => $row['sku_shipper_segment']
    ];
}

header('Content-Type: application/json');
echo json_encode($shippers);
?>
