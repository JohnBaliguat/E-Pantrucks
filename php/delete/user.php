<?php
include "../config/config.php";

function validate($data){
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Handle Delete User
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete-user') {
    $response = ['success' => false, 'message' => ''];

    $user_id = validate($_POST['userId'] ?? '');

    if(empty($user_id)) {
        $response['message'] = "User ID is required";
    } else {
        $delete_sql = 'DELETE FROM "user" WHERE user_id = ?';
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->execute([$user_id]);

        $response['success'] = true;
        $response['message'] = "User deleted successfully";
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>
