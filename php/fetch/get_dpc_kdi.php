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

$sql = "SELECT entry_id, entry_type, segment, activity, waybill_date, waybill, evita_farmind, driver, \"driver_idNumber\", departure, arrival, truck, tr, ph, remarks, dpc_date, \"13_body\", \"13_cover\", \"13_pads\", \"18_body\", \"18_cover\", \"18_pads\", \"13_total\", \"18_total\", other_body, other_cover, other_pads, other_total, total_load, fgtr_no FROM operations WHERE entry_id = ?";

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
