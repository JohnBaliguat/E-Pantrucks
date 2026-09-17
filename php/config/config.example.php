<?php
// Copy this file to config.php and fill in your real credentials.
// config.php is git-ignored and must never be committed.
$dsn      = 'pgsql:host=YOUR_HOST;port=5432;dbname=postgres';
$db_user  = 'YOUR_DB_USER';
$db_pass  = 'YOUR_DB_PASSWORD';

try {
    $conn = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    require_once __DIR__ . "/../helpers/activity_log.php";
    if (!defined("DISABLE_AUTO_ACTIVITY_LOG")) {
        activity_log_auto_request($conn);
    }
} catch (PDOException $e) {
    echo 'Database not connected: ' . $e->getMessage();
    exit();
}
?>
