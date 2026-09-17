<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/build_records_xlsx.php";
require_once __DIR__ . "/../helpers/db_value.php";
require_once __DIR__ . "/../helpers/ensure_transmittals_schema.php";

date_default_timezone_set("Asia/Manila");
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");

ensure_transmittals_schema($conn);

$dateFrom = trim((string) ($_POST["date_from"] ?? ""));
$dateTo = trim((string) ($_POST["date_to"] ?? ""));
$transmittalDate = trim((string) ($_POST["transmittal_date"] ?? ""));
$entryType = trim((string) ($_POST["entry_type"] ?? ""));
$customer = trim((string) ($_POST["customer"] ?? ""));
$createdBy = trim((string) ($_POST["created_by"] ?? ""));
$includeTransmitted = !empty($_POST["include_transmitted"]) && $_POST["include_transmitted"] !== "0";

// Optional user-arranged order of entry_ids (comma-separated). Ids not present
// in the result are ignored; result rows not listed fall back to default order.
$entryOrderRaw = trim((string) ($_POST["entry_order"] ?? ""));
$entryOrder = $entryOrderRaw !== ""
    ? array_values(array_filter(array_map("intval", explode(",", $entryOrderRaw)), fn($v) => $v > 0))
    : [];

if (
    $dateFrom === "" ||
    $dateTo === "" ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $transmittalDate) ||
    $dateFrom > $dateTo
) {
    echo json_encode(["success" => false, "message" => "Invalid date range."]);
    exit();
}

// Build the list of entry_ids to EXCLUDE (already transmitted) unless the
// user opted to include them.
$excludeEntryIds = [];
if (!$includeTransmitted) {
    $excludeStmt = $conn->query("SELECT DISTINCT entry_id FROM transmittal_entries");
    $excludeEntryIds = array_map("intval", $excludeStmt->fetchAll(PDO::FETCH_COLUMN));
}

// Build a label and a unique file name.
$entryTypeLabel = strtoupper($entryType) === "ALL" || $entryType === ""
    ? "All Entries"
    : $entryType;

$labelSuffix = $createdBy !== "" ? sprintf(" · Encoded by %s", $createdBy) : "";
$label = sprintf("Transmittal — %s (%s to %s)%s", $entryTypeLabel, $dateFrom, $dateTo, $labelSuffix);

$fileSlug = preg_replace('/[^A-Za-z0-9_-]+/', '_', $entryTypeLabel);
$fileName = sprintf(
    "transmittal_%s_%s_%s_%s.xlsx",
    $fileSlug,
    $dateFrom,
    $dateTo,
    bin2hex(random_bytes(4))
);

$storageDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "transmittals";
if (!is_dir($storageDir)) {
    if (!mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
        echo json_encode(["success" => false, "message" => "Storage directory could not be created."]);
        exit();
    }
}

$absolutePath = $storageDir . DIRECTORY_SEPARATOR . $fileName;
$relativePath = "storage/transmittals/" . $fileName;

try {
    $meta = build_records_xlsx(
        $conn,
        $dateFrom,
        $dateTo,
        $entryType,
        $customer,
        $excludeEntryIds,
        $absolutePath,
        $createdBy,
        $entryOrder,
        $transmittalDate
    );
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Build failed: " . $e->getMessage()]);
    exit();
}

if ($meta["record_count"] === 0) {
    @unlink($absolutePath);
    $msg = $includeTransmitted
        ? "No records match the selected filters."
        : "No untransmitted records match the selected filters. Tick \"Include already-transmitted\" to re-export.";
    echo json_encode(["success" => false, "message" => $msg]);
    exit();
}

$nowExpr = date("Y-m-d H:i:s");
$expiresExpr = date("Y-m-d H:i:s", strtotime("+6 months"));
$requestedBy = $_SESSION["user_idNumber"] ?? ($_SESSION["user_name"] ?? "system");

try {
    $conn->beginTransaction();

    // Insert transmittal row with manual PK (no sequence on this DB).
    $insertSql = '
        INSERT INTO transmittals (
            transmittal_id, label, entry_type, date_from, date_to, customer_filter,
            include_transmitted, file_name, file_path, file_size_bytes, record_count,
            requested_by, requested_at, expires_at, status
        ) VALUES (
            COALESCE((SELECT MAX(transmittal_id) FROM transmittals), 0) + 1,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        ) RETURNING transmittal_id
    ';

    $stmt = $conn->prepare($insertSql);
    $stmt->execute([
        $label,
        $entryType !== "" ? $entryType : null,
        $dateFrom,
        $dateTo,
        $customer !== "" ? $customer : null,
        $includeTransmitted ? "true" : "false",
        $fileName,
        $relativePath,
        $meta["file_size"],
        $meta["record_count"],
        $requestedBy,
        $nowExpr,
        $expiresExpr,
        "ready",
    ]);
    $transmittalId = (int) $stmt->fetchColumn();

    if (!empty($meta["entry_ids"])) {
        $entryInsert = $conn->prepare(
            "INSERT INTO transmittal_entries (transmittal_id, entry_id) VALUES (?, ?) ON CONFLICT DO NOTHING"
        );
        foreach ($meta["entry_ids"] as $entryId) {
            $entryInsert->execute([$transmittalId, (int) $entryId]);
        }
    }

    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    @unlink($absolutePath);
    echo json_encode([
        "success" => false,
        "message" => "Could not record transmittal: " . $e->getMessage(),
    ]);
    exit();
}

echo json_encode([
    "success" => true,
    "message" => "Transmittal generated.",
    "transmittal" => [
        "transmittal_id" => $transmittalId,
        "label" => $label,
        "file_name" => $fileName,
        "file_size_bytes" => $meta["file_size"],
        "record_count" => $meta["record_count"],
        "requested_at" => $nowExpr,
        "expires_at" => $expiresExpr,
        "status" => "ready",
    ],
]);
exit();
?>
