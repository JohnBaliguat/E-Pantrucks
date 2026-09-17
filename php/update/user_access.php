<?php
include "../config/config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update-access') {
    $response = ['success' => false, 'message' => ''];

    $user_id = intval($_POST['user_id'] ?? 0);
    $pages = $_POST['pages'] ?? [];

    if ($user_id <= 0) {
        $response['message'] = "Invalid user.";
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    $allowed_pages = ['dashboard', 'entry', 'monitoring', 'records', 'transmittals', 'for-update'];

    // Ensure table exists
    $conn->exec("CREATE TABLE IF NOT EXISTS user_access (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL,
        page_name VARCHAR(50) NOT NULL,
        CONSTRAINT unique_user_page UNIQUE (user_id, page_name)
    )");

    // Remove existing access records for this user
    $del_stmt = $conn->prepare("DELETE FROM user_access WHERE user_id = ?");
    $del_stmt->execute([$user_id]);

    // Insert newly granted pages
    $ins_stmt = $conn->prepare("INSERT INTO user_access (user_id, page_name) VALUES (?, ?)");
    foreach ($pages as $page) {
        $page = trim($page);
        if (in_array($page, $allowed_pages)) {
            $ins_stmt->execute([$user_id, $page]);
        }
    }

    $response['success'] = true;
    $response['message'] = "Access updated successfully.";

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>
