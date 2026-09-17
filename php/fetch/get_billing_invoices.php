<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_customer_billing_schema.php";
require_once __DIR__ . "/../helpers/unified_billing.php";
date_default_timezone_set("Asia/Manila");
header("Content-Type: application/json; charset=utf-8");
ensure_customer_billing_schema($conn);

$rows = $conn->query("
    SELECT invoice_id, customer_key, customer_label, reference, document_no, date_from, date_to,
           COALESCE(NULLIF(forex_rate, 0), (
               SELECT MAX(forex_rate) FROM billing_invoice_entry_forex f WHERE f.invoice_id = billing_invoices.invoice_id
           )) AS forex_rate, file_name, file_path, file_size_bytes, line_count,
           requested_by, requested_at, status, returned_at, returned_remarks
    FROM billing_invoices
    WHERE status <> 'deleted'
    ORDER BY requested_at DESC, invoice_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$projectRoot = dirname(__DIR__, 2);
$invoices = [];
foreach ($rows as $row) {
    $absolute = $projectRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $row["file_path"]);
    $status = $row["status"];
    if ($status === "ready" && !is_file($absolute)) {
        $status = "missing";
    }
    $invoices[] = [
        "invoice_id" => (int) $row["invoice_id"],
        "document_no" => $row["document_no"],
        "customer_label" => $row["customer_label"],
        "reference" => $row["reference"],
        "returned_at" => $row["returned_at"],
        "returned_remarks" => $row["returned_remarks"],
        "date_from" => $row["date_from"],
        "date_to" => $row["date_to"],
        "forex_rate" => $row["forex_rate"],
        "file_name" => $row["file_name"],
        "file_size_bytes" => (int) $row["file_size_bytes"],
        "line_count" => (int) $row["line_count"],
        "requested_by" => $row["requested_by"],
        "requested_at" => $row["requested_at"],
        "status" => $status,
        "pipeline" => unified_billing_pipeline((string) $row["customer_key"]),
        // RV (reefer) invoices can render the wide RAW layout; DICT/KDs cannot.
        "raw_capable" => unified_billing_raw_match((string) $row["customer_key"]) !== null,
    ];
}

echo json_encode(["success" => true, "invoices" => $invoices]);
exit();
?>
