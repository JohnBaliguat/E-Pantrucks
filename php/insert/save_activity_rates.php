<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_rate_fuel_schema.php";
require_once __DIR__ . "/../helpers/billing_activities.php";

header("Content-Type: application/json; charset=utf-8");
ensure_rate_fuel_schema($conn);

$customerKey = trim((string) ($_POST["customer"] ?? ""));
if ($customerKey === "") {
    echo json_encode(["success" => false, "message" => "Missing customer."]);
    exit();
}

$payload = json_decode((string) ($_POST["rates"] ?? ""), true);
if (!is_array($payload)) {
    echo json_encode(["success" => false, "message" => "Invalid payload."]);
    exit();
}

$validCodes = billing_activity_rate_codes();
$num = static function ($v): float {
    $v = trim((string) $v);
    return ($v !== "" && is_numeric($v)) ? (float) $v : 0.0;
};

try {
    $conn->beginTransaction();
    $nextId = ((int) $conn->query("SELECT COALESCE(MAX(id), 0) FROM activity_rate")->fetchColumn());
    $upsert = $conn->prepare(
        "INSERT INTO activity_rate (id, customer_key, activity_code, rate, free_hours, material_code)
         VALUES (?, ?, ?, ?, ?, ?)
         ON CONFLICT (customer_key, activity_code)
         DO UPDATE SET rate = EXCLUDED.rate, free_hours = EXCLUDED.free_hours, material_code = EXCLUDED.material_code"
    );
    foreach ($payload as $code => $vals) {
        if (!in_array($code, $validCodes, true) || !is_array($vals)) {
            continue;
        }
        $rate = $num($vals["rate"] ?? 0);
        $freeHours = array_key_exists("free_hours", $vals) && trim((string) $vals["free_hours"]) !== ""
            ? $num($vals["free_hours"]) : 48.0;
        $materialCode = trim((string) ($vals["material_code"] ?? ""));
        // Skip empty rows (no rate, default free-hours, no material code) — a fully
        // cleared row is removed.
        if ($rate <= 0 && $freeHours == 48.0 && $materialCode === "") {
            $conn->prepare("DELETE FROM activity_rate WHERE customer_key = ? AND activity_code = ?")
                 ->execute([$customerKey, $code]);
            continue;
        }
        $nextId++;
        $upsert->execute([$nextId, $customerKey, $code, $rate, $freeHours, $materialCode]);
    }
    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(["success" => false, "message" => "Save failed: " . $e->getMessage()]);
    exit();
}

echo json_encode(["success" => true, "message" => "Activity rates saved."]);
exit();
?>
