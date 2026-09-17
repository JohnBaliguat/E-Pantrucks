<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/app_settings.php";

header("Content-Type: application/json; charset=utf-8");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Admin-only: this controls what every user sees in Billing.
if (($_SESSION["user_type"] ?? "") !== "Admin") {
    echo json_encode(["success" => false, "message" => "Admins only."]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
    exit();
}

$raw = $_POST["hide_invoice_excel_buttons"] ?? "0";
$hide = (!empty($raw) && $raw !== "0" && strtolower((string) $raw) !== "false") ? "1" : "0";

try {
    app_setting_set($conn, "hide_invoice_excel_buttons", $hide);
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Save failed: " . $e->getMessage()]);
    exit();
}

echo json_encode([
    "success" => true,
    "message" => "Settings saved.",
    "hide_invoice_excel_buttons" => $hide === "1",
]);
exit();
?>
