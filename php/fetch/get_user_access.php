<?php
include "../config/config.php";

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    $response = ['success' => false, 'pages' => []];

    $conn->exec("CREATE TABLE IF NOT EXISTS user_access (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL,
        page_name VARCHAR(50) NOT NULL,
        CONSTRAINT unique_user_page UNIQUE (user_id, page_name)
    )");

    $stmt = $conn->prepare("SELECT page_name FROM user_access WHERE user_id = ?");
    if ($stmt) {
        $stmt->execute([$user_id]);
        $pages = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pages[] = $row['page_name'];
        }
        $response['success'] = true;
        $response['pages'] = $pages;
    } else {
        $response['message'] = "Query failed";
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>
