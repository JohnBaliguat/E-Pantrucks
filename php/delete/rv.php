<?php
include "../config/config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete-rv') {
    $response = ['success' => false, 'message' => ''];
    $data_id = intval($_POST['data_id'] ?? 0);

    if ($data_id <= 0) {
        $response['message'] = 'Invalid record.';
    } else {
        $stmt = $conn->prepare('DELETE FROM operations WHERE entry_id = ?');
        $stmt->execute([$data_id]);
        $response['success'] = true;
        $response['message'] = 'Record deleted.';
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>
