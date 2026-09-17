<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/box_banana_customers.php";
require_once __DIR__ . "/../helpers/build_box_banana_billing.php";
require_once __DIR__ . "/../helpers/ensure_box_banana_schema.php";
require_once __DIR__ . "/../helpers/ensure_rate_fuel_schema.php";
require_once __DIR__ . "/../helpers/fuel_rate_engine.php";

date_default_timezone_set("Asia/Manila");
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header("Content-Type: application/json; charset=utf-8");
ensure_box_banana_schema($conn);
ensure_rate_fuel_schema($conn);

$customerKey = trim((string) ($_POST["customer"] ?? ""));
$dateFrom = trim((string) ($_POST["date_from"] ?? ""));
$dateTo = trim((string) ($_POST["date_to"] ?? ""));
$includeBilled = !empty($_POST["include_billed"]) && $_POST["include_billed"] !== "0";
$selectedEntryIds = array_values(array_filter(array_map(
    "intval",
    preg_split('/\s*,\s*/', (string) ($_POST["selected_entry_ids"] ?? ""), -1, PREG_SPLIT_NO_EMPTY)
), static fn(int $id): bool => $id > 0));
$entryOrder = array_values(array_filter(array_map(
    "intval",
    preg_split('/\s*,\s*/', (string) ($_POST["entry_order"] ?? ""), -1, PREG_SPLIT_NO_EMPTY)
), static fn(int $id): bool => $id > 0));

$customer = box_banana_customer($customerKey);
if ($customer === null) {
    echo json_encode(["success" => false, "message" => "Unknown customer."]);
    exit();
}
if (
    $dateFrom === "" || $dateTo === "" ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ||
    $dateFrom > $dateTo
) {
    echo json_encode(["success" => false, "message" => "Invalid date range."]);
    exit();
}

$flatRate = box_banana_rate($conn, (string) ($customer["rate_code"] ?? ""));

$currency = strtoupper((string) ($customer["document_currency"] ?? "PHP"));
$forex = $currency === "USD" ? billing_forex_rate($conn, $dateTo, $customerKey) : 1.0;
if ($currency === "USD" && $forex <= 0) {
    echo json_encode(["success" => false, "message" => "No Dollar Conversion (forex) set for USD billing. Add it on a Fuel Price row in Master Data."]);
    exit();
}
if ($forex <= 0) {
    $forex = 1.0;
}

$excludeEntryIds = [];
if (!$includeBilled) {
    $excludeEntryIds = array_map(
        "intval",
        $conn->query("SELECT DISTINCT entry_id FROM box_banana_statement_entries")->fetchAll(PDO::FETCH_COLUMN)
    );
}

try {
    $entries = box_banana_fetch_entries($conn, $customer, $dateFrom, $dateTo, $excludeEntryIds);
    $entries = box_banana_apply_selection($entries, $selectedEntryIds, $entryOrder);
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Build failed: " . $e->getMessage()]);
    exit();
}

if (count($entries) === 0) {
    $msg = $includeBilled
        ? "No trips match the selected customer and date range."
        : "No unbilled trips match the selected customer and date range. Tick \"Include already-billed\" to re-generate.";
    echo json_encode(["success" => false, "message" => $msg]);
    exit();
}

// Per-customer statement sequence number for the Reference label.
$seqStmt = $conn->prepare("SELECT COUNT(*) FROM box_banana_statements WHERE customer_key = ? AND status <> 'deleted'");
$seqStmt->execute([$customerKey]);
$sequence = ((int) $seqStmt->fetchColumn()) + 1;
$reference = ($customer["reference_prefix"] ?? $customer["label"]) . " - " . $sequence;

$built = box_banana_build_sap_rows($conn, $entries, $customer, $customerKey, $forex, $reference, $flatRate);

$slug = preg_replace('/[^A-Za-z0-9_-]+/', "_", $customer["label"]);
$fileName = sprintf("BB_%s_%s_%s_%s.xlsx", $slug, $dateFrom, $dateTo, bin2hex(random_bytes(4)));

$storageDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "box_banana";
if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
    echo json_encode(["success" => false, "message" => "Storage directory could not be created."]);
    exit();
}
$absolutePath = $storageDir . DIRECTORY_SEPARATOR . $fileName;
$relativePath = "storage/box_banana/" . $fileName;

try {
    // Same SAP ZPSO writer the Customer Billing page uses.
    billing_write_xlsx($built["rows"], $built["total"], $absolutePath);
} catch (Throwable $e) {
    @unlink($absolutePath);
    echo json_encode(["success" => false, "message" => "Build failed: " . $e->getMessage()]);
    exit();
}

$requestedBy = $_SESSION["user_idNumber"] ?? ($_SESSION["user_name"] ?? "system");
$now = date("Y-m-d H:i:s");

try {
    $conn->beginTransaction();
    $insertSql = '
        INSERT INTO box_banana_statements (
            statement_id, customer_key, customer_label, reference, date_from, date_to,
            total_amount, total_boxes, file_name, file_path, file_size_bytes, line_count,
            requested_by, requested_at, status
        ) VALUES (
            COALESCE((SELECT MAX(statement_id) FROM box_banana_statements), 0) + 1,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        ) RETURNING statement_id
    ';
    $stmt = $conn->prepare($insertSql);
    $stmt->execute([
        $customerKey,
        $customer["label"],
        $reference,
        $dateFrom,
        $dateTo,
        $built["total"],
        0,
        $fileName,
        $relativePath,
        (int) filesize($absolutePath),
        count($built["rows"]),
        $requestedBy,
        $now,
        "ready",
    ]);
    $statementId = (int) $stmt->fetchColumn();

    if (!empty($built["entry_ids"])) {
        $entryInsert = $conn->prepare(
            "INSERT INTO box_banana_statement_entries (statement_id, entry_id) VALUES (?, ?) ON CONFLICT DO NOTHING"
        );
        foreach ($built["entry_ids"] as $entryId) {
            $entryInsert->execute([$statementId, (int) $entryId]);
        }
        // Lock the per-entry PHP peso charge computed at generation time.
        $chargeByEntry = $built["charge_by_entry"] ?? [];
        if (!empty($chargeByEntry)) {
            $chargeUpd = $conn->prepare(
                "UPDATE box_banana_statement_entries SET rate_charge = ? WHERE statement_id = ? AND entry_id = ?"
            );
            foreach ($chargeByEntry as $eid => $charge) {
                if (is_numeric($charge)) {
                    $chargeUpd->execute([round((float) $charge, 2), $statementId, (int) $eid]);
                }
            }
        }
    }
    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    @unlink($absolutePath);
    echo json_encode(["success" => false, "message" => "Could not record statement: " . $e->getMessage()]);
    exit();
}

echo json_encode([
    "success" => true,
    "message" => sprintf("Generated statement with %d trip(s), total amount %s.", count($built["rows"]), number_format($built["total_amount"], 2)),
    "invoice" => [
        "statement_id" => $statementId,
        "reference" => $reference,
        "file_name" => $fileName,
        "line_count" => count($built["rows"]),
        "download_url" => "php/fetch/download_box_banana.php?id=" . $statementId,
    ],
]);
exit();
?>
