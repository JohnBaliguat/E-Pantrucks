<?php

require_once __DIR__ . "/../helpers/xlsx_helper.php";
require_once __DIR__ . "/../helpers/ensure_rate_fuel_schema.php";
require_once __DIR__ . "/../helpers/rate_matrix_customers.php";
include "../config/config.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION["user_idNumber"])) {
    http_response_code(403);
    exit("Unauthorized");
}

ensure_rate_fuel_schema($conn);

$customerKey = trim((string) ($_GET["customer"] ?? ""));
if ($customerKey === "") {
    http_response_code(400);
    exit("Missing customer.");
}

$customers = rate_matrix_customers($conn);
if (!isset($customers[$customerKey])) {
    http_response_code(400);
    exit("Invalid customer.");
}

// One row per lane; the rate is computed from the formula, so no cell grid.
$headers = [
    "Effective From",
    "Effective To",
    "Segment",
    "Origin",
    "Packing House",
    "Destination",
    "DCode",
    "Base Rate",
    "Pump Price",
    "Price Movement",
    "Active",
];

$laneStmt = $conn->prepare(
    "SELECT effective_from, effective_to, segment, origin, packing_house, destination, dcode, base_rate,
            pump_price, price_movement, active
     FROM rate_lane
     WHERE customer_key = ?
     ORDER BY sort_order ASC, lane_id ASC"
);
$laneStmt->execute([$customerKey]);
$lanes = $laneStmt->fetchAll(PDO::FETCH_ASSOC);

$rows = [];
foreach ($lanes as $lane) {
    $rows[] = [
        (string) ($lane["effective_from"] ?? ""),
        (string) ($lane["effective_to"] ?? ""),
        (string) ($lane["segment"] ?? ""),
        (string) ($lane["origin"] ?? ""),
        (string) ($lane["packing_house"] ?? ""),
        (string) ($lane["destination"] ?? ""),
        (string) ($lane["dcode"] ?? ""),
        (string) ($lane["base_rate"] ?? ""),
        (string) ($lane["pump_price"] ?? ""),
        (string) ($lane["price_movement"] ?? ""),
        (($lane["active"] ?? true) ? "Yes" : "No"),
    ];
}
if (empty($rows)) {
    $rows[] = array_fill(0, count($headers), "");
}

$xlsx = xlsx_create_with_rows($headers, $rows);
if ($xlsx === "") {
    http_response_code(500);
    exit("Failed to generate template.");
}

$safeCustomer = preg_replace('/[^A-Za-z0-9_-]+/', '_', $customerKey);
$filename = "fuel_rate_matrix_" . $safeCustomer . "_template.xlsx";

header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
header("Content-Length: " . strlen($xlsx));
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

echo $xlsx;
exit();
