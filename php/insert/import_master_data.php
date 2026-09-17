<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/master_settings.php";
require_once __DIR__ . "/../helpers/db_value.php";
require_once __DIR__ . "/../helpers/xlsx_helper.php";
require_once __DIR__ . "/../helpers/ensure_rate_fuel_schema.php";

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

ensure_rate_fuel_schema($conn);

$entity = trim((string) ($_POST["entity"] ?? ""));
$entityFieldMap = [
    "fuel_price" => [
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
    "forex_rate" => [
        "effective_from",
        "effective_to",
        "updated_date",
        "rate",
        "customer_keys",
    ],
    "service_material" => [
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
    "profit_center" => [
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
];

if (!isset($entityFieldMap[$entity])) {
    $response["message"] = "Invalid entity.";
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

$definition = master_settings_entity($entity);
if ($definition === null) {
    $response["message"] = "Entity settings are unavailable.";
    echo json_encode($response);
    exit();
}

$normalizeHeader = static function ($value): string {
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim((string) $value, '_');
};

$normalizeDate = static function ($raw): string {
    $raw = trim((string) $raw);
    if ($raw === "") {
        return "";
    }
    if (ctype_digit($raw)) {
        $serial = (int) $raw;
        if ($serial >= 1 && $serial <= 73050) {
            if ($serial >= 60) {
                $serial--;
            }
            return gmdate("Y-m-d", ($serial - 25569) * 86400);
        }
    }
    $parts = explode("/", $raw);
    if (count($parts) < 2 || count($parts) > 3) {
        return $raw;
    }
    $month = str_pad(trim($parts[0]), 2, "0", STR_PAD_LEFT);
    $day = str_pad(trim($parts[1]), 2, "0", STR_PAD_LEFT);
    $year = isset($parts[2]) ? trim($parts[2]) : date("Y");
    if (strlen($year) === 2) {
        $year = "20" . $year;
    }
    if (!is_numeric($month) || !is_numeric($day) || !is_numeric($year)) {
        return $raw;
    }
    return $year . "-" . $month . "-" . $day;
};

$headerRow = array_shift($rows);
$headerMap = [];
foreach ($headerRow as $index => $header) {
    $headerMap[$normalizeHeader($header)] = $index;
}

$indexes = [];
foreach ($entityFieldMap[$entity] as $field) {
    $label = $definition["fields"][$field]["label"] ?? $field;
    $normalizedCandidates = [
        $normalizeHeader($field),
        $normalizeHeader($label),
    ];
    $index = null;
    foreach ($normalizedCandidates as $candidate) {
        if (array_key_exists($candidate, $headerMap)) {
            $index = $headerMap[$candidate];
            break;
        }
    }
    if ($index === null) {
        $response["message"] = "Template columns are invalid. Please use the downloaded template.";
        echo json_encode($response);
        exit();
    }
    $indexes[$field] = $index;
}

$pk = $definition["primary_key"];
$table = $definition["table"];
$stmt = null;

foreach ($rows as $rowNumber => $row) {
    $payload = [];
    foreach ($entityFieldMap[$entity] as $field) {
        $value = trim((string) ($row[$indexes[$field]] ?? ""));
        if (in_array($field, ["effective_from", "effective_to", "updated_date", "effective_date", "valid_from_date", "valid_to_date"], true)) {
            $value = $normalizeDate($value);
        }
        $payload[$field] = $value;
    }

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

    $validation = master_settings_validate_payload($entity, $payload);
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
        if ($stmt !== null) {
            $stmt->closeCursor();
        }
        $response["errors"][] = "Row " . ($rowNumber + 2) . ": " . $e->getMessage();
    }
}

$response["success"] = $response["imported"] > 0;
$response["message"] = $response["imported"] > 0
    ? "Imported " . $response["imported"] . " row(s)."
    : "No rows were imported.";

echo json_encode($response);
exit();
