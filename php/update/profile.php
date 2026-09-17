<?php
define("DISABLE_AUTO_ACTIVITY_LOG", true);
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

function profile_full_name(array $row): string
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

function build_profile_change_set(array $before, array $after): array
{
    $map = [
        "Full name" => [profile_full_name($before), profile_full_name($after)],
        "First name" => [$before["user_fname"] ?? "", $after["user_fname"] ?? ""],
        "Middle name" => [$before["user_mname"] ?? "", $after["user_mname"] ?? ""],
        "Last name" => [$before["user_lname"] ?? "", $after["user_lname"] ?? ""],
        "Username" => [$before["user_name"] ?? "", $after["user_name"] ?? ""],
        "ID number" => [$before["user_idNumber"] ?? "", $after["user_idNumber"] ?? ""],
        "Email" => [$before["user_email"] ?? "", $after["user_email"] ?? ""],
        "Contact" => [$before["user_contact"] ?? "", $after["user_contact"] ?? ""],
        "Address" => [$before["user_address"] ?? "", $after["user_address"] ?? ""],
        "City" => [$before["user_city"] ?? "", $after["user_city"] ?? ""],
        "Country" => [$before["user_country"] ?? "", $after["user_country"] ?? ""],
        "Bio" => [$before["user_bio"] ?? "", $after["user_bio"] ?? ""],
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

// Get current user profile
if($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get-profile') {
    $user_id = $_SESSION['user_id'];

    $sql = 'SELECT user_id, "user_idNumber", user_name, user_fname, user_lname, user_mname, user_email, user_type, user_image, "user_accountStat", user_code, user_address, user_city, user_country, user_bio, user_contact FROM "user" WHERE user_id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if($user) {
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    exit();
}

// Update personal info
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update-personal') {
    $response = ['success' => false, 'message' => ''];

    $user_id = $_SESSION['user_id'];
    $fname = validate($_POST['firstName'] ?? '');
    $lname = validate($_POST['lastName'] ?? '');
    $mname = validate($_POST['middleName'] ?? '');
    $username = validate($_POST['username'] ?? '');
    $idNumber = validate($_POST['idNumber'] ?? '');
    $email = validate($_POST['email'] ?? '');
    $contact = validate($_POST['contact'] ?? '');
    $address = validate($_POST['address'] ?? '');
    $city = validate($_POST['city'] ?? '');
    $country = validate($_POST['country'] ?? '');
    $bio = validate($_POST['bio'] ?? '');

    if(empty($fname) || empty($lname) || empty($username) || empty($idNumber) || empty($email)) {
        $response['message'] = "First name, last name, username, ID number, and email are required";
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Check if email, username, or id number is already used by another user
    $check_sql = 'SELECT user_id FROM "user" WHERE (user_email = ? OR user_name = ? OR "user_idNumber" = ?) AND user_id != ?';
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute([$email, $username, $idNumber, $user_id]);

    if($check_stmt->fetch() !== false) {
        $response['message'] = "Email, username, or ID number is already in use";
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    $before_stmt = $conn->prepare('SELECT user_id, "user_idNumber", user_name, user_fname, user_lname, user_mname, user_email, user_address, user_city, user_country, user_bio, user_contact FROM "user" WHERE user_id = ?');
    $before_stmt->execute([$user_id]);
    $before_user = $before_stmt->fetch(PDO::FETCH_ASSOC);

    // Update user profile
    $update_sql = 'UPDATE "user" SET "user_idNumber" = ?, user_name = ?, user_fname = ?, user_lname = ?, user_mname = ?, user_email = ?, user_address = ?, user_city = ?, user_country = ?, user_bio = ?, user_contact = ? WHERE user_id = ?';
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->execute([$idNumber, $username, $fname, $lname, $mname, $email, $address, $city, $country, $bio, $contact, $user_id]);

    // Update session with new values
    $_SESSION['user_name'] = trim($fname . ' ' . $lname);
    $_SESSION['user_email'] = $email;
    $_SESSION['user_idNumber'] = $idNumber;

    if ($before_user !== false) {
        $after_user = [
            "user_idNumber" => $idNumber,
            "user_name" => $username,
            "user_fname" => $fname,
            "user_lname" => $lname,
            "user_mname" => $mname,
            "user_email" => $email,
            "user_address" => $address,
            "user_city" => $city,
            "user_country" => $country,
            "user_bio" => $bio,
            "user_contact" => $contact,
        ];
        $changes = build_profile_change_set($before_user, $after_user);
        $oldFullName = profile_full_name($before_user);
        $newFullName = profile_full_name($after_user);

        activity_log_write($conn, [
            "activity_type" => "profile_update",
            "activity_label" => $oldFullName !== $newFullName
                ? 'Updated personal information from ' . $oldFullName . ' to ' . $newFullName
                : 'Updated personal information of ' . ($newFullName !== '' ? $newFullName : $username),
            "context_summary" => 'user_id=' . $user_id . '; changes=' . count($changes),
            "details_json" => json_encode([
                "entity" => "profile",
                "target_user_id" => (int) $user_id,
                "target_before" => $before_user,
                "target_after" => $after_user,
                "changes" => $changes,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    $response['success'] = true;
    $response['message'] = "Personal information updated successfully";

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>
