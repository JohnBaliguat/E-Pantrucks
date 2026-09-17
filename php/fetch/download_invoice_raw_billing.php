<?php
/**
 * Download the RAW billing (.xlsx) for a generated SAP invoice.
 *
 * The invoice stored the SAP ZPSO file; this re-derives the WIDE reefer-RV RAW
 * layout for the SAME trips (the invoice's own entry_ids) and streams it. It does
 * NOT touch the RAW export ledger (raw_billing_batches) — this is a view of an
 * existing invoice, not a new RAW batch. RV (reefer) invoices only; DICT/KDs have
 * no RAW representation.
 */
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_customer_billing_schema.php";
require_once __DIR__ . "/../helpers/unified_billing.php";
require_once __DIR__ . "/../helpers/build_box_banana_billing.php";
require_once __DIR__ . "/../helpers/build_raw_billing.php";

ensure_customer_billing_schema($conn);

$fail = static function (int $code, string $msg): void {
    http_response_code($code);
    header("Content-Type: text/plain; charset=utf-8");
    echo $msg;
    exit();
};

$id = (int) ($_GET["id"] ?? 0);
if ($id <= 0) {
    $fail(400, "Missing or invalid invoice id.");
}

$stmt = $conn->prepare(
    "SELECT customer_key, reference, date_from, date_to, file_name
     FROM billing_invoices WHERE invoice_id = ? AND status <> 'deleted' LIMIT 1"
);
$stmt->execute([$id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$invoice) {
    $fail(404, "Invoice not found.");
}

if (unified_billing_raw_match((string) $invoice["customer_key"]) === null) {
    // Layout is reefer-specific (genset, EIR, van alpha/number).
    $fail(400, "The RAW billing format is available for the reefer (RV) customers only.");
}

// The invoice's own trips, in trip-date order (billing_invoice_entries has no
// stored line order — the RAW xlsx re-banners by customer/PC anyway).
$stmt = $conn->prepare(
    "SELECT e.entry_id
     FROM billing_invoice_entries e
     JOIN operations o ON o.entry_id = e.entry_id
     WHERE e.invoice_id = ?
     ORDER BY " . raw_billing_trip_date_expr() . " ASC, e.entry_id ASC"
);
$stmt->execute([$id]);
$entryIds = array_map("intval", $stmt->fetchAll(PDO::FETCH_COLUMN));

if (empty($entryIds)) {
    $fail(404, "This invoice has no stored trips to export. Re-generate it, then try again.");
}

$manualForexByEntry = [];
$manualStmt = $conn->prepare("SELECT entry_id, forex_rate FROM billing_invoice_entry_forex WHERE invoice_id = ?");
$manualStmt->execute([$id]);
foreach ($manualStmt->fetchAll(PDO::FETCH_ASSOC) as $manualRow) {
    $manualForexByEntry[(int) $manualRow["entry_id"]] = (float) $manualRow["forex_rate"];
}

// LOCKED / hand-edited charges so the RAW export matches the Charges modal + SAP file.
$chargeOverrideByEntry = [];
$chargeStmt = $conn->prepare("SELECT entry_id, rate_charge FROM billing_invoice_entries WHERE invoice_id = ?");
$chargeStmt->execute([$id]);
foreach ($chargeStmt->fetchAll(PDO::FETCH_ASSOC) as $chargeRow) {
    if (is_numeric($chargeRow["rate_charge"])) {
        $chargeOverrideByEntry[(int) $chargeRow["entry_id"]] = (float) $chargeRow["rate_charge"];
    }
}

try {
    $rows = raw_billing_fetch_rows_by_ids($conn, $entryIds, $manualForexByEntry, $chargeOverrideByEntry);
} catch (Throwable $e) {
    $fail(500, "RAW build failed: " . $e->getMessage());
}

// Name the RAW file after the SAP one so the pair is obvious in Downloads.
$base = preg_replace('/\.xlsx$/i', "", (string) $invoice["file_name"]);
if ($base === "" || $base === null) {
    $base = "Invoice_" . $id;
}
$downloadName = $base . "_RAW.xlsx";

$tmp = tempnam(sys_get_temp_dir(), "rawinv_") . ".xlsx";
try {
    raw_billing_write_xlsx($rows, $tmp, (string) $invoice["date_from"], (string) $invoice["date_to"]);

    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header("Content-Length: " . (string) filesize($tmp));
    header("Cache-Control: max-age=0");
    readfile($tmp);
} catch (Throwable $e) {
    $fail(500, "RAW export failed: " . $e->getMessage());
} finally {
    if (is_file($tmp)) {
        @unlink($tmp);
    }
}
exit();
