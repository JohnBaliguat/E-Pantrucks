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

$invoiceActivity = (string) ($row["activity"] ?? "hauling");
// Which activity's CSV to download; falls back to the invoice's own activity.
$activityCode = trim((string) ($_GET["activity"] ?? ""));
if ($activityCode === "" || billing_activity($activityCode) === null) {
    $activityCode = $invoiceActivity;
}
$reference = trim((string) ($row["reference"] ?? ""));
$sanitize = static fn(string $s): string => preg_replace('/[\/\\\\:*?"<>|]+/', "_", $s);
// Downloaded file is named WITHOUT the Month(Year) (e.g. "Sumifru Reefer Vans - 1.csv");
// the stored Reference / table / SAP column keep the month — see billing_reference_basename().
$refName = billing_reference_basename($reference);

// ---- Fast path: the invoice's OWN activity -> serve the stored .csv sibling. ----
if ($activityCode === $invoiceActivity) {
    $projectRoot = dirname(__DIR__, 2);
    $csvRelative = preg_replace('/\.xlsx$/i', ".csv", (string) $row["file_path"]);
    $absolute = $projectRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, (string) $csvRelative);
    if (is_file($absolute)) {
        $csvName = $refName !== ""
            ? $sanitize($refName) . ".csv"
            : preg_replace('/\.xlsx$/i', ".csv", (string) $row["file_name"]);
        if (!str_ends_with(strtolower($csvName), ".csv")) {
            $csvName .= ".csv";
        }
        header("Content-Type: text/csv; charset=utf-8");
        header('Content-Disposition: attachment; filename="' . $csvName . '"');
        header("Content-Length: " . (string) filesize($absolute));
        header("Cache-Control: max-age=0");
        readfile($absolute);
        exit();
    }
    // stored file missing -> fall through and rebuild below
}

// ---- Rebuild the SAP rows for the requested activity from the invoice's trips + locked
// charges (all per-trip pipelines: matrix/SAP hauling, ABC KDs, Dry Vans, activities). ----
try {
    $built = billing_rebuild_invoice_sap($conn, $row, $id, $activityCode);
} catch (RuntimeException $e) {
    $actDef = billing_activity($activityCode);
    $short = $actDef["short"] ?? $activityCode;
    $msg = [
        "unsupported" => "This billing type cannot be rebuilt into a CSV (its lines aggregate several trips).",
        "no_entries" => "This invoice has no trips to build a CSV from.",
        "unknown_customer" => "Unknown customer configuration.",
        "out_of_range" => "This invoice's trips are no longer in the billed date range — regenerate it.",
        "no_rows" => "No chargeable " . $short . " lines for this invoice.",
    ][$e->getMessage()] ?? "Could not build the CSV for this invoice.";
    $fail(404, $msg);
} catch (Throwable $e) {
    $fail(500, "CSV build failed: " . $e->getMessage());
}

$actDef = billing_activity($activityCode);
$suffix = (!empty($actDef["short"]) && $activityCode !== "hauling") ? " - " . $actDef["short"] : "";
$csvName = $sanitize(($refName !== "" ? $refName : "billing") . $suffix) . ".csv";

$tmp = tempnam(sys_get_temp_dir(), "sapcsv");
billing_write_csv($built["rows"], $built["total"], $tmp);
header("Content-Type: text/csv; charset=utf-8");
header('Content-Disposition: attachment; filename="' . $csvName . '"');
header("Content-Length: " . (string) filesize($tmp));
header("Cache-Control: max-age=0");
readfile($tmp);
@unlink($tmp);
exit();
