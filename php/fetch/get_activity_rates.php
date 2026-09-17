<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_rate_fuel_schema.php";
require_once __DIR__ . "/../helpers/rate_matrix_customers.php";
require_once __DIR__ . "/../helpers/billing_activities.php";

header("Content-Type: application/json; charset=utf-8");
ensure_rate_fuel_schema($conn);

$customers = rate_matrix_customers($conn);
$activities = [];
foreach (billing_activity_rate_codes() as $code) {
    $a = billing_activity($code);
    $activities[] = ["code" => $code, "label" => $a["label"] ?? $code, "short" => $a["short"] ?? $code, "basis" => $a["basis"] ?? ""];
}

$customerKey = trim((string) ($_GET["customer"] ?? ""));
$rates = [];
if ($customerKey !== "") {
    $stmt = $conn->prepare("SELECT activity_code, rate, free_hours, material_code FROM activity_rate WHERE customer_key = ?");
    $stmt->execute([$customerKey]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rates[$r["activity_code"]] = [
            "rate" => $r["rate"],
            "free_hours" => $r["free_hours"],
            "material_code" => $r["material_code"] ?? "",
        ];
    }
}

echo json_encode([
    "success" => true,
    "customers" => $customers,
    "activities" => $activities,
    "customer" => $customerKey,
    "rates" => $rates,
]);
exit();
?>
