<?php
include "../config/config.php";
session_start();
date_default_timezone_set("Asia/Manila");
// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

function validate($data){
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Handle Change Password
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change-password') {
    $response = ['success' => false, 'message' => ''];

    $user_id = $_SESSION['user_id'];
    $currentPassword = $_POST['currentPassword'] ?? '';
    $newPassword = $_POST['newPassword'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    if(empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $response['message'] = "All password fields are required";
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    if(strlen($newPassword) < 8) {
        $response['message'] = "New password must be at least 8 characters long";
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    if($newPassword !== $confirmPassword) {
        $response['message'] = "New passwords do not match";
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Get current password from database
    $sql = 'SELECT user_pass FROM "user" WHERE user_id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$row) {
        $response['message'] = "User not found";
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Verify current password
    if(!password_verify($currentPassword, $row['user_pass'])) {
        $response['message'] = "Current password is incorrect";
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password
    $update_sql = 'UPDATE "user" SET user_pass = ? WHERE user_id = ?';
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->execute([$hashedPassword, $user_id]);

    $response['success'] = true;
    $response['message'] = "Password changed successfully";

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>
