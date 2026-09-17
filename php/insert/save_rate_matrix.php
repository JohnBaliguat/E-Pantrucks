<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_rate_fuel_schema.php";
require_once __DIR__ . "/../helpers/rate_matrix_storage.php";

header("Content-Type: application/json; charset=utf-8");
ensure_rate_fuel_schema($conn);

$customerKey = trim((string) ($_POST["customer"] ?? ""));
if ($customerKey === "") {
    echo json_encode(["success" => false, "message" => "Missing customer."]);
    exit();
}

$payload = json_decode((string) ($_POST["matrix"] ?? ""), true);
if (!is_array($payload)) {
    echo json_encode(["success" => false, "message" => "Invalid matrix payload."]);
    exit();
}

// Accept either { lanes: [...] } or a bare list of lane rows.
$lanes = is_array($payload["lanes"] ?? null) ? $payload["lanes"] : $payload;
if (!is_array($lanes)) {
    $lanes = [];
}

try {
    $saved = rate_matrix_replace_lanes($conn, $customerKey, $lanes);
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Save failed: " . $e->getMessage()]);
    exit();
}

echo json_encode([
    "success" => true,
    "message" => sprintf("Saved %d rate line(s).", $saved),
]);
exit();
?>
