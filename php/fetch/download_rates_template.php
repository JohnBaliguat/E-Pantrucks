<?php

require_once __DIR__ . "/../helpers/xlsx_helper.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION["user_idNumber"])) {
    http_response_code(403);
    exit("Unauthorized");
}

$headers = [
    "Origin",
    "Packing House",
    "Port of Destination",
    "Loc. Code",
    "Rate Code",
    "Rate",
];

$xlsx = xlsx_create($headers);
if ($xlsx === "") {
    http_response_code(500);
    exit("Failed to generate template.");
}

header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"rates_template.xlsx\"");
header("Content-Length: " . strlen($xlsx));
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

echo $xlsx;
exit();
