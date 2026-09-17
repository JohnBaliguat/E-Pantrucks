<?php
// This file should be included at the top of protected pages

// Check if session is not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?route=login");
    exit();
}

// Include database configuration
include_once "php/config/config.php";
?>
