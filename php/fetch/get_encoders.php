<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
header("Content-Type: application/json; charset=utf-8");

// Return every user in the user master, no filtering. The dropdown shows
// everybody so you can always pick an encoder regardless of operations data.
$users = $conn->query('
    SELECT user_id, "user_idNumber", user_fname, user_lname, user_name, user_type
    FROM "user"
    WHERE user_type IS NULL OR LOWER(user_type) <> \'manager\'
    ORDER BY COALESCE(user_fname, user_name, \'\') ASC
')->fetchAll(PDO::FETCH_ASSOC);

$encoders = [];
foreach ($users as $row) {
    // Prefer user_idNumber as the filter value (that's what's stored in
    // operations.created_by). Fall back to user_name if blank.
    $id = trim((string) ($row["user_idNumber"] ?? ""));
    if ($id === "") {
        $id = trim((string) ($row["user_name"] ?? ""));
    }
    if ($id === "") {
        continue;
    }
    $name = trim(($row["user_fname"] ?? "") . " " . ($row["user_lname"] ?? ""));
    if ($name === "") {
        $name = (string) ($row["user_name"] ?? $id);
    }
    $encoders[] = [
        "id" => $id,
        "label" => $name . " (" . $id . ")",
        "name" => $name,
    ];
}

echo json_encode([
    "success" => true,
    "encoders" => $encoders,
    "debug" => [
        "raw_user_rows" => count($users),
        "encoder_count" => count($encoders),
    ],
]);
exit;
?>
