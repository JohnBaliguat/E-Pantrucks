<?php

require_once __DIR__ . "/activity_log.php";

function setUserSession(array $user): void
{
    $_SESSION["user_id"] = $user["user_id"];
    $_SESSION["user_idNumber"] = $user["user_idNumber"] ?? null;
    $_SESSION["user_type"] = $user["user_type"];
    $_SESSION["user_name"] = $user["user_name"];
    $_SESSION["user_email"] = $user["user_email"];
    $_SESSION["user_image"] = $user["user_image"];
}

function logUserLogin(PDO $conn, array $user): void
{
    activity_log_write($conn, [
        "user_id" => $user["user_id"] ?? null,
        "user_id_number" => $user["user_idNumber"] ?? "",
        "user_name" => $user["user_name"] ?? "",
        "user_type" => $user["user_type"] ?? "",
        "activity_type" => "login",
        "activity_label" => "Successful login",
    ]);
}

function logFailedLogin(PDO $conn, string $username): void
{
    activity_log_write($conn, [
        "user_name" => $username,
        "activity_type" => "login_failed",
        "activity_label" => "Failed login attempt",
        "context_summary" => $username !== "" ? "username=" . substr($username, 0, 120) : "",
    ]);
}
