<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/app_settings.php";

header("Content-Type: application/json; charset=utf-8");

echo json_encode([
    "success" => true,
    "hide_invoice_excel_buttons" => app_setting_bool($conn, "hide_invoice_excel_buttons"),
]);
exit();
?>
