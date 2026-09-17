<?php

require_once __DIR__ . "/../helpers/master_settings.php";
require_once __DIR__ . "/../helpers/xlsx_helper.php";
require_once __DIR__ . "/../helpers/ensure_rate_fuel_schema.php";
include "../config/config.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION["user_idNumber"])) {
    http_response_code(403);
    exit("Unauthorized");
}

ensure_rate_fuel_schema($conn);

$entity = trim((string) ($_GET["entity"] ?? ""));
$allowed = [
    "fuel_price" => [
        "filename" => "fuel_price_template.xlsx",
        "fields" => [
            "effective_from",
            "effective_to",
            "updated_date",
            "petron",
            "shell",
            "caltex",
            "phoenix",
            "flying_v",
            "seaoil",
            "jetti",
            "my_gas",
            "independent",
            "common_price",
            "customer_keys",
        ],
    ],
    "forex_rate" => [
        "filename" => "forex_rate_template.xlsx",
        "fields" => [
            "effective_from",
            "effective_to",
            "updated_date",
            "rate",
            "customer_keys",
        ],
    ],
    "service_material" => [
        "filename" => "service_material_template.xlsx",
        "fields" => [
            "revenue_stream",
            "material_code",
            "material_description",
            "rate_type",
            "rate",
            "tax_class",
            "profit_center",
            "account_assignment",
            "gen_item_category_group",
            "item_category_group2",
        ],
    ],
    "profit_center" => [
        "filename" => "profit_center_template.xlsx",
        "fields" => [
            "profit_center_code",
            "controlling_area",
            "valid_from_date",
            "valid_to_date",
            "name",
            "long_text",
            "person_responsible",
            "department",
            "profit_center_group",
            "segment",
        ],
    ],
];

if (!isset($allowed[$entity])) {
    http_response_code(400);
    exit("Invalid entity.");
}

$definition = master_settings_entity($entity);
if ($definition === null) {
    http_response_code(400);
    exit("Invalid entity definition.");
}

$headers = [];
foreach ($allowed[$entity]["fields"] as $field) {
    $headers[] = $definition["fields"][$field]["label"] ?? $field;
}

$xlsx = xlsx_create($headers);
if ($xlsx === "") {
    http_response_code(500);
    exit("Failed to generate template.");
}

header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"" . $allowed[$entity]["filename"] . "\"");
header("Content-Length: " . strlen($xlsx));
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

echo $xlsx;
exit();
