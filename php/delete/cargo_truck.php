<?php
include "../config/config.php";

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'delete-cargo-truck') {
    $response['message'] = 'Invalid request.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$data_id = intval($_POST['data_id'] ?? 0);
if ($data_id <= 0) {
    $response['message'] = 'Invalid record.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$stmt = $conn->prepare('DELETE FROM operations WHERE entry_id = ?');
$stmt->execute([$data_id]);
$response['success'] = true;
$response['message'] = 'Cargo Truck record deleted.';

header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
