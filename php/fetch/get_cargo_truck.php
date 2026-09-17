<?php
include "../config/config.php";

$response = ['success' => false, 'message' => '', 'record' => null];

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    $response['message'] = 'Invalid record ID.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$sql = "SELECT entry_id, entry_type, segment, activity, waybill_date, waybill, truck, driver, \"driver_idNumber\", customer_ph, outside, compound, total_trips, operations, deliver_from, delivered_to, remarks, cargo_date FROM operations WHERE entry_id = ?";

$stmt = $conn->prepare($sql);
$stmt->execute([$id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if ($record !== false) {
    $response['success'] = true;
    $response['record'] = $record;
} else {
    $response['message'] = 'Record not found.';
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
