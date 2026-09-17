<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/box_banana_customers.php";
require_once __DIR__ . "/../helpers/build_box_banana_billing.php";
require_once __DIR__ . "/../helpers/ensure_box_banana_schema.php";
require_once __DIR__ . "/../helpers/ensure_rate_fuel_schema.php";
require_once __DIR__ . "/../helpers/fuel_rate_engine.php";

date_default_timezone_set("Asia/Manila");
header("Content-Type: application/json; charset=utf-8");
ensure_box_banana_schema($conn);
ensure_rate_fuel_schema($conn);

// Rebuild an existing Box Bananas statement's stored SAP file from the LOCKED /
// hand-edited per-entry charges, overwriting the same file in place.
$statementId = (int) ($_POST["id"] ?? $_POST["statement_id"] ?? 0);
if ($statementId <= 0) {
    echo json_encode(["success" => false, "message" => "Missing statement id."]);
    exit();
}

$row = $conn->prepare(
    "SELECT customer_key, reference, date_from, date_to, file_path, status
     FROM box_banana_statements WHERE statement_id = ? AND status <> 'deleted'"
);
$row->execute([$statementId]);
$statement = $row->fetch(PDO::FETCH_ASSOC);
if (!$statement) {
    echo json_encode(["success" => false, "message" => "Statement not found."]);
    exit();
}

$customerKey = (string) $statement["customer_key"];
$customer = box_banana_customer($customerKey);
if ($customer === null) {
    echo json_encode(["success" => false, "message" => "Unknown customer."]);
    exit();
}

$dateFrom = (string) $statement["date_from"];
$dateTo = (string) $statement["date_to"];
$reference = (string) $statement["reference"];

$currency = strtoupper((string) ($customer["document_currency"] ?? "PHP"));
$forex = $currency === "USD" ? billing_forex_rate($conn, $dateTo, $customerKey) : 1.0;
if ($forex <= 0) {
    $forex = 1.0;
}
$flatRate = box_banana_rate($conn, (string) ($customer["rate_code"] ?? ""));

$ce = $conn->prepare("SELECT entry_id, rate_charge FROM box_banana_statement_entries WHERE statement_id = ? ORDER BY entry_id");
$ce->execute([$statementId]);
$storedCharges = [];
foreach ($ce->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $storedCharges[(int) $r["entry_id"]] = $r["rate_charge"];
}
if (empty($storedCharges)) {
    echo json_encode(["success" => false, "message" => "This statement has no entries to rebuild."]);
    exit();
}
$storedIds = array_keys($storedCharges);

try {
    $allEntries = box_banana_fetch_entries($conn, $customer, $dateFrom, $dateTo, []);
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Rebuild failed: " . $e->getMessage()]);
    exit();
}
$byId = [];
foreach ($allEntries as $e) {
    $byId[(int) ($e["entry_id"] ?? 0)] = $e;
}
$entries = [];
foreach ($storedIds as $eid) {
    if (isset($byId[$eid])) {
        $entries[] = $byId[$eid];
    }
}
if (empty($entries)) {
    echo json_encode([
        "success" => false,
        "message" => "Could not re-load this statement's trips (they may have been edited out of the billed date range).",
    ]);
    exit();
}

$chargeOverrideByEntry = [];
foreach ($storedCharges as $eid => $charge) {
    if (is_numeric($charge)) {
        $chargeOverrideByEntry[(int) $eid] = (float) $charge;
    }
}

$built = box_banana_build_sap_rows($conn, $entries, $customer, $customerKey, $forex, $reference, $flatRate, null, [], $chargeOverrideByEntry);
if (empty($built["rows"])) {
    echo json_encode(["success" => false, "message" => "The rebuild produced no billable lines."]);
    exit();
}

$projectRoot = dirname(__DIR__, 2);
$absolutePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, (string) $statement["file_path"]);
$dir = dirname($absolutePath);
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    echo json_encode(["success" => false, "message" => "Storage directory is unavailable."]);
    exit();
}
try {
    billing_write_xlsx($built["rows"], $built["total"], $absolutePath);
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Rebuild failed: " . $e->getMessage()]);
    exit();
}

try {
    clearstatcache(true, $absolutePath);
    $conn->beginTransaction();
    $conn->prepare("UPDATE box_banana_statements SET line_count = ?, file_size_bytes = ?, total_amount = ?, status = 'ready' WHERE statement_id = ?")
        ->execute([count($built["rows"]), (int) filesize($absolutePath), $built["total"], $statementId]);
    $chargeUpd = $conn->prepare("UPDATE box_banana_statement_entries SET rate_charge = ? WHERE statement_id = ? AND entry_id = ?");
    foreach (($built["charge_by_entry"] ?? []) as $eid => $charge) {
        if (is_numeric($charge)) {
            $chargeUpd->execute([round((float) $charge, 2), $statementId, (int) $eid]);
        }
    }
    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(["success" => false, "message" => "File rebuilt, but the statement record could not be updated: " . $e->getMessage()]);
    exit();
}

echo json_encode([
    "success" => true,
    "message" => sprintf("Rebuilt the file from the stored charges — %d line(s).", count($built["rows"])),
    "line_count" => count($built["rows"]),
    "total" => $built["total"],
]);
exit();
