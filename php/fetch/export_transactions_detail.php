<?php
include "../config/config.php";
require_once __DIR__ . "/../helpers/build_transactions_detail_xlsx.php";

$dateFrom = trim((string) ($_GET["date_from"] ?? ""));
$dateTo = trim((string) ($_GET["date_to"] ?? ""));
$entryType = trim((string) ($_GET["entry_type"] ?? ""));
$customer = trim((string) ($_GET["customer"] ?? ""));

if (
    $dateFrom === "" ||
    $dateTo === "" ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ||
    $dateFrom > $dateTo
) {
    http_response_code(400);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Invalid date range.";
    exit();
}

$temporaryPath = tempnam(sys_get_temp_dir(), "txn_detail_");
if ($temporaryPath === false) {
    http_response_code(500);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Failed to create temporary export file.";
    exit();
}

$exportPath = $temporaryPath . ".xlsx";
@unlink($temporaryPath);

try {
    build_transactions_detail_xlsx($conn, $dateFrom, $dateTo, $entryType, $customer, $exportPath);
} catch (Throwable $e) {
    @unlink($exportPath);
    http_response_code(500);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Export failed: " . $e->getMessage();
    exit();
}

$entryTypeLabel = strtoupper($entryType) === "ALL" || $entryType === ""
    ? "ALL"
    : preg_replace('/[^A-Za-z0-9_-]+/', '_', $entryType);
$filename = sprintf("transactions_detail_%s_%s_%s.xlsx", $entryTypeLabel, $dateFrom, $dateTo);

header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header('Content-Disposition: attachment; filename="' . $filename . '"');
header("Content-Length: " . (string) filesize($exportPath));
header("Cache-Control: max-age=0");

readfile($exportPath);
@unlink($exportPath);
exit();
?>
