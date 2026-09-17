<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/master_settings.php";
require_once __DIR__ . "/../helpers/db_value.php";
require_once __DIR__ . "/../helpers/xlsx_helper.php";

date_default_timezone_set("Asia/Manila");
header("Content-Type: application/json; charset=utf-8");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$response = ["success" => false, "message" => "", "imported" => 0, "errors" => []];

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

$requiredMap = [
    "origin" => ["origin"],
    "packing_house" => ["packing_house"],
    "port_of_destination" => ["port_of_destination"],
    "loc_code" => ["loc_code", "loc_code_"],
    "rate_code" => ["rate_code"],
    "rate" => ["rate"],
];

$getIndex = static function (array $candidates) use ($headerMap): ?int {
    foreach ($candidates as $candidate) {
        if (array_key_exists($candidate, $headerMap)) {
            return $headerMap[$candidate];
        }
    }
    return null;
};

$indexes = [];
foreach ($requiredMap as $field => $candidates) {
    $index = $getIndex($candidates);
    if ($index === null) {
        $response["message"] = "Template columns are invalid. Please use the downloaded rates template.";
        echo json_encode($response);
        exit();
    }
    $indexes[$field] = $index;
}

$definition = master_settings_entity("rates");
if ($definition === null) {
    $response["message"] = "Rates settings are unavailable.";
    echo json_encode($response);
    exit();
}

$pk = $definition["primary_key"];
$table = $definition["table"];
$stmt = null;

foreach ($rows as $rowNumber => $row) {
    $payload = [
        "origin" => trim((string) ($row[$indexes["origin"]] ?? "")),
        "packing_house" => trim((string) ($row[$indexes["packing_house"]] ?? "")),
        "port_of_destination" => trim((string) ($row[$indexes["port_of_destination"]] ?? "")),
        "loc_code" => trim((string) ($row[$indexes["loc_code"]] ?? "")),
        "rate_code" => trim((string) ($row[$indexes["rate_code"]] ?? "")),
        "rate" => trim((string) ($row[$indexes["rate"]] ?? "")),
    ];

    $allBlank = true;
    foreach ($payload as $value) {
        if ($value !== "") {
            $allBlank = false;
            break;
        }
    }
    if ($allBlank) {
        continue;
    }

    $validation = master_settings_validate_payload("rates", $payload);
    if (!$validation["valid"]) {
        $response["errors"][] = "Row " . ($rowNumber + 2) . ": " . $validation["message"];
        continue;
    }

    if ($stmt === null) {
        $fields = array_keys($validation["values"]);
        $columnList = array_map(static function ($field) {
            return '"' . $field . '"';
        }, $fields);
        array_unshift($columnList, '"' . $pk . '"');
        $columnsSql = implode(', ', $columnList);

        $placeholders = array_fill(0, count($fields), '?');
        array_unshift(
            $placeholders,
            sprintf('COALESCE((SELECT MAX("%s") FROM "%s"), 0) + 1', $pk, $table)
        );
        $placeholdersSql = implode(', ', $placeholders);

        $sql = sprintf(
            'INSERT INTO "%s" (%s) VALUES (%s) RETURNING "%s"',
            $table,
            $columnsSql,
            $placeholdersSql,
            $pk
        );

        $stmt = $conn->prepare($sql);
    }

    try {
        $stmt->execute(db_nullable_all(array_values($validation["values"])));
        $stmt->fetchColumn();
        $stmt->closeCursor();
        $response["imported"]++;
    } catch (PDOException $e) {
        $stmt->closeCursor();
        $response["errors"][] = "Row " . ($rowNumber + 2) . ": " . $e->getMessage();
    }
}

$response["success"] = $response["imported"] > 0;
$response["message"] = $response["imported"] > 0
    ? "Imported " . $response["imported"] . " rate row(s)."
    : "No rate rows were imported.";

echo json_encode($response);
exit();
