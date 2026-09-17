<?php
session_start();
include "php/config/config.php";
require_once "php/helpers/activity_log.php";

if (isset($_SESSION["user_id"])) {
    activity_log_write($conn, [
        "activity_type" => "logout",
        "activity_label" => "User logged out",
    ]);
}

session_destroy();
header("Location: login");
exit;
?>
