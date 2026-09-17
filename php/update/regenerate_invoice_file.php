<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_customer_billing_schema.php";
require_once __DIR__ . "/../helpers/ensure_rate_fuel_schema.php";
require_once __DIR__ . "/../helpers/billing_invoice_rebuild.php";

date_default_timezone_set("Asia/Manila");
header("Content-Type: application/json; charset=utf-8");
ensure_customer_billing_schema($conn);
ensure_rate_fuel_schema($conn);

// Rebuild an existing invoice's stored SAP file (xlsx + csv) from the LOCKED /
// hand-edited per-entry charges, overwriting the same file in place — so a
// downloaded file matches the charges shown in the Charges modal.
//
// Covers every per-trip pipeline: matrix + SAP hauling, ABC KDs, Dry Vans, and the
// non-hauling activities. DICT shuttling is aggregate (many trips per line) and is
// not regenerated here.
$invoiceId = (int) ($_POST["id"] ?? $_POST["invoice_id"] ?? 0);
if ($invoiceId <= 0) {
    echo json_encode(["success" => false, "message" => "Missing invoice id."]);
    exit();
}

$inv = $conn->prepare(
    "SELECT customer_key, reference, date_from, date_to, forex_rate, file_name, file_path, status, activity, document_date
     FROM billing_invoices WHERE invoice_id = ? AND status <> 'deleted'"
);
$inv->execute([$invoiceId]);
$invoice = $inv->fetch(PDO::FETCH_ASSOC);
if (!$invoice) {
    echo json_encode(["success" => false, "message" => "Invoice not found."]);
    exit();
}

// Rebuild the typed SAP rows from the invoice's stored trips + locked charges, via the
// shared helper (same logic the download endpoints use when a stored file is missing).
try {
    $built = billing_rebuild_invoice_sap($conn, $invoice, $invoiceId);
} catch (RuntimeException $e) {
    $message = [
        "unsupported" => "File regeneration is not available for this billing type (DICT shuttling lines aggregate several trips). It keeps its original file.",
        "no_entries" => "This invoice has no entries to rebuild.",
        "unknown_customer" => "Unknown customer.",
        "out_of_range" => "Could not re-load this invoice's trips (they may have been edited out of the billed date range).",
        "no_rows" => "The rebuild produced no billable lines.",
    ][$e->getMessage()] ?? "Rebuild failed: " . $e->getMessage();
    echo json_encode(["success" => false, "message" => $message]);
    exit();
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Rebuild failed: " . $e->getMessage()]);
    exit();
}

$sapRows = $built["rows"];
$total = (float) ($built["total"] ?? 0);
$builtCharges = $built["charge_by_entry"] ?? [];

// Overwrite the SAME stored file (xlsx + csv sibling).
$projectRoot = dirname(__DIR__, 2);
$absolutePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, (string) $invoice["file_path"]);
$dir = dirname($absolutePath);
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    echo json_encode(["success" => false, "message" => "Storage directory is unavailable."]);
    exit();
}
try {
    billing_write_xlsx($sapRows, $total, $absolutePath);
    billing_write_csv_sibling($sapRows, $total, $absolutePath);
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Rebuild failed: " . $e->getMessage()]);
    exit();
}

// Keep the invoice row + stored charges consistent with the rebuilt file.
try {
    clearstatcache(true, $absolutePath);
    $conn->beginTransaction();
    $conn->prepare("UPDATE billing_invoices SET line_count = ?, file_size_bytes = ?, status = 'ready' WHERE invoice_id = ?")
        ->execute([count($sapRows), (int) filesize($absolutePath), $invoiceId]);
    $chargeUpd = $conn->prepare("UPDATE billing_invoice_entries SET rate_charge = ? WHERE invoice_id = ? AND entry_id = ?");
    foreach ($builtCharges as $eid => $charge) {
        if (is_numeric($charge)) {
            $chargeUpd->execute([round((float) $charge, 2), $invoiceId, (int) $eid]);
        }
    }
    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(["success" => false, "message" => "File rebuilt, but the invoice record could not be updated: " . $e->getMessage()]);
    exit();
}

echo json_encode([
    "success" => true,
    "message" => sprintf("Rebuilt the file from the stored charges — %d line(s).", count($sapRows)),
    "line_count" => count($sapRows),
    "total" => $total,
]);
exit();
