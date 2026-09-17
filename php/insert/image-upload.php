<?php
include "../config/config.php";
session_start();

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

// Handle Image Upload
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profileImage'])) {
    $response = ['success' => false, 'message' => ''];

    $user_id = $_SESSION['user_id'];
    $file = $_FILES['profileImage'];

    // Allowed file types
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB

    // Validate file
    if(empty($file['tmp_name'])) {
        $response['message'] = "No file uploaded";
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    if(!in_array($file['type'], $allowed_types)) {
        $response['message'] = "Invalid file type. Only JPG, PNG, GIF, and WebP are allowed";
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    if($file['size'] > $max_size) {
        $response['message'] = "File is too large. Maximum size is 5MB";
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Create upload directory if not exists
    $upload_dir = "../../assets/uploads/profiles/";
    if(!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
    $filepath = $upload_dir . $filename;

    // Move uploaded file
    if(move_uploaded_file($file['tmp_name'], $filepath)) {
        // Update user record with image path
        $image_path = 'assets/uploads/profiles/' . $filename;
        $update_sql = 'UPDATE "user" SET user_image = ? WHERE user_id = ?';
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([$image_path, $user_id]);

        $_SESSION['user_image'] = $image_path;
        $response['success'] = true;
        $response['message'] = "Profile image uploaded successfully";
        $response['image_path'] = $image_path;
    } else {
        $response['message'] = "Error uploading file. Please try again";
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>
