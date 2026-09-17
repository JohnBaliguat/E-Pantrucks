<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_rate_fuel_schema.php";
require_once __DIR__ . "/../helpers/rate_matrix_customers.php";
require_once __DIR__ . "/../helpers/rate_matrix_storage.php";
require_once __DIR__ . "/../helpers/xlsx_helper.php";

header("Content-Type: application/json; charset=utf-8");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$response = ["success" => false, "message" => "", "lanes" => 0];

if (!isset($_SESSION["user_idNumber"])) {
    $response["message"] = "Unauthorized.";
    echo json_encode($response);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $response["message"] = "Invalid request.";
    echo json_encode($response);
    exit();
}

ensure_rate_fuel_schema($conn);

$customerKey = trim((string) ($_POST["customer"] ?? ""));
if ($customerKey === "") {
    $response["message"] = "Missing customer.";
    echo json_encode($response);
    exit();
}

$customers = rate_matrix_customers($conn);
if (!isset($customers[$customerKey])) {
    $response["message"] = "Invalid customer.";
    echo json_encode($response);
    exit();
}

if (!isset($_FILES["xlsx_file"]) || $_FILES["xlsx_file"]["error"] !== UPLOAD_ERR_OK) {
    $response["message"] = "No file uploaded or upload error.";
    echo json_encode($response);
    exit();
}

$ext = strtolower(pathinfo($_FILES["xlsx_file"]["name"], PATHINFO_EXTENSION));
if ($ext !== "xlsx") {
    $response["message"] = "Only .xlsx files are accepted.";
    echo json_encode($response);
    exit();
}

$rows = xlsx_read_rows($_FILES["xlsx_file"]["tmp_name"]);
if (count($rows) < 2) {
    $response["message"] = "The file has no data rows.";
    echo json_encode($response);
    exit();
}

$normalizeHeader = static function ($value): string {
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim((string) $value, '_');
};

$headerRow = array_shift($rows);
$headerMap = [];
foreach ($headerRow as $index => $header) {
    $headerMap[$normalizeHeader($header)] = $index;
}

// column key => accepted header aliases
$columns = [
    "effective_from" => ["effective_from", "effective", "from", "date"],
    "effective_to" => ["effective_to", "to"],
    "segment" => ["segment"],
    "origin" => ["origin"],
    "packing_house" => ["packing_house", "packing", "packinghouse", "packing_station"],
    "destination" => ["destination"],
    "dcode" => ["dcode", "d_code"],
    "base_rate" => ["base_rate", "base"],
    "pump_price" => ["pump_price", "pump"],
    "price_movement" => ["price_movement", "movement_step", "step", "movement"],
    "active" => ["active"],
];

$colIndex = [];
foreach ($columns as $key => $accepts) {
    foreach ($accepts as $candidate) {
        if (array_key_exists($candidate, $headerMap)) {
            $colIndex[$key] = $headerMap[$candidate];
            break;
        }
    }
}
// Minimum required columns to build a lane.
foreach (["destination", "base_rate"] as $must) {
    if (!isset($colIndex[$must])) {
        $response["message"] = "Template columns are invalid. Please use the downloaded matrix template.";
        echo json_encode($response);
        exit();
    }
}

$get = static function (array $row, ?int $idx): string {
    return $idx === null ? "" : trim((string) ($row[$idx] ?? ""));
};

$lanes = [];
foreach ($rows as $rowNumber => $row) {
    $segment = $get($row, $colIndex["segment"] ?? null);
    $origin = $get($row, $colIndex["origin"] ?? null);
    $packingHouse = $get($row, $colIndex["packing_house"] ?? null);
    $destination = $get($row, $colIndex["destination"] ?? null);
    $dcode = $get($row, $colIndex["dcode"] ?? null);
    $baseRateRaw = $get($row, $colIndex["base_rate"] ?? null);
    $effectiveFrom = $get($row, $colIndex["effective_from"] ?? null);
    $effectiveTo = $get($row, $colIndex["effective_to"] ?? null);
    $pumpRaw = $get($row, $colIndex["pump_price"] ?? null);
    $stepRaw = $get($row, $colIndex["price_movement"] ?? null);
    $activeRaw = $get($row, $colIndex["active"] ?? null);

    if ($segment === "" && $origin === "" && $packingHouse === "" && $destination === "" && $dcode === "" && $baseRateRaw === "") {
        continue; // blank row
    }
    if ($destination === "" && $dcode === "") {
        $response["message"] = "Import failed: row " . ($rowNumber + 2) . " needs a Destination or DCode.";
        echo json_encode($response);
        exit();
    }

    $active = $activeRaw === "" ? true : !in_array(strtolower($activeRaw), ["no", "false", "0", "n"], true);

    $lanes[] = [
        "effective_from" => $effectiveFrom,
        "effective_to" => $effectiveTo,
        "segment" => $segment,
        "origin" => $origin,
        "packing_house" => $packingHouse,
        "destination" => $destination,
        "dcode" => $dcode,
        "base_rate" => rate_matrix_number($baseRateRaw),
        "pump_price" => rate_matrix_number($pumpRaw),
        "price_movement" => rate_matrix_number($stepRaw),
        "active" => $active,
    ];
}

if (empty($lanes)) {
    $response["message"] = "No valid rate rows found in the file.";
    echo json_encode($response);
    exit();
}

try {
    $saved = rate_matrix_replace_lanes($conn, $customerKey, $lanes);
} catch (Throwable $e) {
    $response["message"] = "Import failed: " . $e->getMessage();
    echo json_encode($response);
    exit();
}

$response["success"] = true;
$response["lanes"] = $saved;
$response["message"] = sprintf("Imported %d rate line(s).", $saved);

echo json_encode($response);
exit();
