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

$sql = "SELECT entry_id, entry_type, segment, activity, waybill_date, others_date, cargo_date, dpc_date, pullout_location_arrival_date, production_date, finished_loading_date, created_date, waybill, truck, driver, \"driver_idNumber\", tr, gs, operations_ph, customer_ph, load_quantity_weight, unit_of_measure, kms, deliver_from, delivered_to, remarks FROM operations WHERE entry_id = ?";

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
