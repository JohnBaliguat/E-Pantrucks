<?php
define("DISABLE_AUTO_ACTIVITY_LOG", true);
include "../config/config.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set("Asia/Manila");
function validate($data){
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function user_full_name(array $row): string
{
    $name = trim(
        implode(" ", array_filter([
            $row["user_fname"] ?? "",
            $row["user_mname"] ?? "",
            $row["user_lname"] ?? "",
        ]))
    );

    if ($name !== "") {
        return preg_replace('/\s+/', ' ', $name) ?: $name;
    }

    return trim((string) ($row["user_name"] ?? ""));
}

function build_user_change_set(array $before, array $after): array
{
    $map = [
        "Full name" => [user_full_name($before), user_full_name($after)],
        "First name" => [$before["user_fname"] ?? "", $after["user_fname"] ?? ""],
        "Middle name" => [$before["user_mname"] ?? "", $after["user_mname"] ?? ""],
        "Last name" => [$before["user_lname"] ?? "", $after["user_lname"] ?? ""],
        "Username" => [$before["user_name"] ?? "", $after["user_name"] ?? ""],
        "ID number" => [$before["user_idNumber"] ?? "", $after["user_idNumber"] ?? ""],
        "Email" => [$before["user_email"] ?? "", $after["user_email"] ?? ""],
        "Role" => [$before["user_type"] ?? "", $after["user_type"] ?? ""],
        "Status" => [$before["user_accountStat"] ?? "", $after["user_accountStat"] ?? ""],
    ];

    $changes = [];
    foreach ($map as $label => [$old, $new]) {
        $old = trim((string) $old);
        $new = trim((string) $new);
        if ($old === $new) {
            continue;
        }
        $changes[$label] = [
            "from" => $old,
            "to" => $new,
        ];
    }

    return $changes;
}

// Handle Update User
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update-user') {
    $response = ['success' => false, 'message' => ''];

    $user_id = validate($_POST['userId'] ?? '');
    $fname = validate($_POST['firstName'] ?? '');
    $lname = validate($_POST['lastName'] ?? '');
    $mname = validate($_POST['middleName'] ?? '');
    $username = validate($_POST['username'] ?? '');
    $idNumber = validate($_POST['idNumber'] ?? '');
    $email = validate($_POST['email'] ?? '');
    $user_type = validate($_POST['role'] ?? '');
    $accountStat = validate($_POST['status'] ?? 'Active');

    if(empty($user_id) || empty($fname) || empty($lname) || empty($username) || empty($idNumber) || empty($email)) {
        $response['message'] = "All fields are required";
    } else {
        $existing_stmt = $conn->prepare('SELECT user_id, user_name, user_fname, user_lname, user_mname, user_email, user_type, "user_accountStat", "user_idNumber" FROM "user" WHERE user_id = ?');
        $existing_stmt->execute([$user_id]);
        $existing_user = $existing_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_user === false) {
            $response['message'] = "User not found";
        } else {
        $check_sql = 'SELECT user_id FROM "user" WHERE (user_name = ? OR "user_idNumber" = ?) AND user_id != ?';
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([$username, $idNumber, $user_id]);

        if($check_stmt->fetch() !== false) {
            $response['message'] = "Username or ID Number already exists";
        } else {
            $update_sql = 'UPDATE "user" SET user_name = ?, user_fname = ?, user_lname = ?, user_mname = ?, user_email = ?, user_type = ?, "user_accountStat" = ?, "user_idNumber" = ? WHERE user_id = ?';
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->execute([$username, $fname, $lname, $mname, $email, $user_type, $accountStat, $idNumber, $user_id]);

            $updated_user = [
                "user_name" => $username,
                "user_fname" => $fname,
                "user_lname" => $lname,
                "user_mname" => $mname,
                "user_email" => $email,
                "user_type" => $user_type,
                "user_accountStat" => $accountStat,
                "user_idNumber" => $idNumber,
            ];
            $changes = build_user_change_set($existing_user, $updated_user);
            $oldFullName = user_full_name($existing_user);
            $newFullName = user_full_name($updated_user);

            activity_log_write($conn, [
                "activity_type" => "user_update",
                "activity_label" => $oldFullName !== $newFullName
                    ? 'Updated user information of ' . $oldFullName . ' to ' . $newFullName
                    : 'Updated user information of ' . ($newFullName !== '' ? $newFullName : $username),
                "context_summary" => 'user_id=' . $user_id . '; updated_by=' . activity_log_string($_SESSION["user_name"] ?? "") . '; changes=' . count($changes),
                "details_json" => json_encode([
                    "entity" => "user",
                    "target_user_id" => (int) $user_id,
                    "target_before" => $existing_user,
                    "target_after" => $updated_user,
                    "changes" => $changes,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);

            $response['success'] = true;
            $response['message'] = "User updated successfully";
        }
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Handle Admin Reset User Password
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset-user-password') {
    $response = ['success' => false, 'message' => ''];

    $user_id     = intval($_POST['userId'] ?? 0);
    $newPassword = $_POST['newPassword'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    if($user_id <= 0) {
        $response['message'] = "Invalid user.";
    } elseif(empty($newPassword) || empty($confirmPassword)) {
        $response['message'] = "Both password fields are required.";
    } elseif(strlen($newPassword) < 8) {
        $response['message'] = "Password must be at least 8 characters.";
    } elseif($newPassword !== $confirmPassword) {
        $response['message'] = "Passwords do not match.";
    } else {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE "user" SET user_pass = ? WHERE user_id = ?');
        $stmt->execute([$hashed, $user_id]);
        if($stmt->rowCount() > 0) {
            $user_stmt = $conn->prepare('SELECT user_name, user_fname, user_lname, user_mname, "user_idNumber" FROM "user" WHERE user_id = ?');
            $user_stmt->execute([$user_id]);
            $target_user = $user_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            activity_log_write($conn, [
                "activity_type" => "user_password_reset",
                "activity_label" => 'Reset password for ' . (user_full_name($target_user) ?: ($target_user["user_name"] ?? ("User #" . $user_id))),
                "context_summary" => 'user_id=' . $user_id . '; updated_by=' . activity_log_string($_SESSION["user_name"] ?? ""),
                "details_json" => json_encode([
                    "entity" => "user_password",
                    "target_user_id" => $user_id,
                    "target_user_name" => user_full_name($target_user),
                    "target_username" => $target_user["user_name"] ?? "",
                    "target_id_number" => $target_user["user_idNumber"] ?? "",
                    "performed_by" => $_SESSION["user_name"] ?? "",
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);

            $response['success'] = true;
            $response['message'] = "Password updated successfully.";
        } else {
            $response['message'] = "User not found or password unchanged.";
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>
