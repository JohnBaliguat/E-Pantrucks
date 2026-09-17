<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/dashboard_matrix_revenue.php";

header("Content-Type: application/json; charset=utf-8");
date_default_timezone_set("Asia/Manila");

$isDate = static fn($v): bool => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1;

$customerKey = trim((string) ($_GET["customer"] ?? ""));
$from = $isDate($_GET["from"] ?? null) ? $_GET["from"] : date("Y-m-01");
$to = $isDate($_GET["to"] ?? null) ? $_GET["to"] : date("Y-m-d");
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
if ($customerKey === "") {
    echo json_encode(["success" => false, "message" => "Missing customer."]);
    exit();
}

try {
    $detail = dashboard_customer_trip_detail($conn, $customerKey, $from, $to);
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Failed to load trips: " . $e->getMessage()]);
    exit();
}

echo json_encode([
    "success" => true,
    "customer" => $customerKey,
    "label" => $detail["label"],
    "range" => ["from" => $from, "to" => $to],
    "rows" => $detail["rows"],
    "summary" => $detail["summary"],
]);
exit();
