<?php
include "../config/config.php";
date_default_timezone_set("Asia/Manila");
function validate($data){
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add-user') {
    $response = ['success' => false, 'message' => ''];

    $fname = validate($_POST['firstName'] ?? '');
    $lname = validate($_POST['lastName'] ?? '');
    $mname = validate($_POST['middleName'] ?? '');
    $email = validate($_POST['email'] ?? '');
    $username = validate($_POST['username'] ?? '');
    $idNumber = validate($_POST['idNumber'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $user_type = validate($_POST['role'] ?? '');
    $accountStat = validate($_POST['status'] ?? 'Active');
    $user_code = rand(100000, 999999);

    if(empty($fname) || empty($lname) || empty($email) || empty($username) || empty($idNumber) || empty($password)) {
        $response['message'] = "All fields are required";
    } elseif($password !== $confirmPassword) {
        $response['message'] = "Passwords do not match";
    } else {
        $check_stmt = $conn->prepare('SELECT user_id FROM "user" WHERE user_name = ? OR "user_idNumber" = ?');
        $check_stmt->execute([$username, $idNumber]);

        if($check_stmt->fetch() !== false) {
            $response['message'] = "Username or ID Number already exists";
        } else {
            try {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Compute next user_id since the column isn't auto-incrementing.
                $next_id_stmt = $conn->query('SELECT COALESCE(MAX(user_id), 0) + 1 AS next_id FROM "user"');
                $next_id_row = $next_id_stmt->fetch(PDO::FETCH_ASSOC);
                $next_user_id = (int) ($next_id_row['next_id'] ?? 1);

                $insert_stmt = $conn->prepare('INSERT INTO "user" (user_id, user_name, user_fname, user_lname, user_mname, user_email, user_pass, user_type, "user_accountStat", user_code, "user_idNumber") VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                $insert_stmt->execute([$next_user_id, $username, $fname, $lname, $mname, $email, $hashedPassword, $user_type, $accountStat, $user_code, $idNumber]);

                $response['success'] = true;
                $response['message'] = "User added successfully";
            } catch (PDOException $e) {
                $response['success'] = false;
                $response['message'] = "Database error: " . $e->getMessage();
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>
