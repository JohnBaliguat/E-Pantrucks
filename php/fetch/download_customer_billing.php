<?php
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_customer_billing_schema.php";
require_once __DIR__ . "/../helpers/ensure_rate_fuel_schema.php";
require_once __DIR__ . "/../helpers/billing_invoice_rebuild.php";
require_once __DIR__ . "/../helpers/billing_document.php"; // billing_reference_basename()
ensure_customer_billing_schema($conn);
ensure_rate_fuel_schema($conn);

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

$stmt = $conn->prepare("SELECT customer_key, file_name, file_path, reference, date_from, date_to, forex_rate, document_date,
                        COALESCE(activity, 'hauling') AS activity, status
                        FROM billing_invoices WHERE invoice_id = ? AND status <> 'deleted' LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    $fail(404, "Invoice not found.");
}

// ---- Fast path: serve the stored .xlsx when it is still on disk. ----
$projectRoot = dirname(__DIR__, 2);
$absolute = $projectRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, (string) $row["file_path"]);
if (is_file($absolute)) {
    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header('Content-Disposition: attachment; filename="' . $row["file_name"] . '"');
    header("Content-Length: " . (string) filesize($absolute));
    header("Cache-Control: max-age=0");
    readfile($absolute);
    exit();
}

// ---- Stored file missing -> rebuild the xlsx from the invoice's saved trips + locked
// charges (all per-trip pipelines: matrix/SAP hauling, ABC KDs, Dry Vans, activities),
// so a lost/deleted file can still be downloaded. Mirrors the CSV endpoint. ----
try {
    $built = billing_rebuild_invoice_sap($conn, $row, $id);
} catch (RuntimeException $e) {
    $msg = [
        "unsupported" => "Invoice file is missing and this billing type cannot be rebuilt (its lines aggregate several trips).",
        "no_entries" => "This invoice's file is missing and it has no trips to rebuild from.",
        "unknown_customer" => "Invoice file is missing and its customer configuration is unknown.",
        "out_of_range" => "Invoice file is missing and its trips are no longer in the billed date range — regenerate it.",
        "no_rows" => "Invoice file is missing and the rebuild produced no billable lines.",
    ][$e->getMessage()] ?? "Invoice file is missing and could not be rebuilt.";
    $fail(404, $msg);
} catch (Throwable $e) {
    $fail(500, "Invoice file is missing and the rebuild failed: " . $e->getMessage());
}

$reference = trim((string) ($row["reference"] ?? ""));
$sanitize = static fn(string $s): string => preg_replace('/[\/\\\\:*?"<>|]+/', "_", $s);
$xlsxName = (string) ($row["file_name"] ?? "");
if ($xlsxName === "") {
    // Downloaded file name drops the Month(Year); the stored reference keeps it.
    $refName = billing_reference_basename($reference);
    $xlsxName = $sanitize($refName !== "" ? $refName : "billing") . ".xlsx";
}
if (!str_ends_with(strtolower($xlsxName), ".xlsx")) {
    $xlsxName .= ".xlsx";
}

$tmp = tempnam(sys_get_temp_dir(), "sapxlsx");
billing_write_xlsx($built["rows"], $built["total"], $tmp);
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header('Content-Disposition: attachment; filename="' . $xlsxName . '"');
header("Content-Length: " . (string) filesize($tmp));
header("Cache-Control: max-age=0");
readfile($tmp);
@unlink($tmp);
exit();
