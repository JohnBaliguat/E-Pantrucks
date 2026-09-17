<?php
include "../config/config.php";
require_once __DIR__ . "/../helpers/operations_status.php";

header("Content-Type: application/json");
$routeByType = operations_route_by_type();

// The set of entry types this page monitors.
$monitoredTypes = "'CARGO TRUCK ENTRY', 'DPC_KDs & OPM ENTRY', 'OTHERS ENTRY', 'RV ENTRY', 'DRY VAN ENTRY'";

/**
 * Conditional fetch to cut Supabase egress: this endpoint is polled every ~30s.
 * We first compute a cheap "version" of the monitored set (row count + latest
 * change stamps — catches inserts, edits, and deletes). If the client already
 * has that version, we return a ~30-byte "unchanged" response instead of the
 * full ~1.6 MB payload, so idle polls transfer almost nothing.
 */
$versionRow = $conn->query(
    "SELECT COUNT(*) AS c, COALESCE(MAX(modified_date)::text, '') AS mm, COALESCE(MAX(created_date)::text, '') AS cm
     FROM operations WHERE entry_type IN ($monitoredTypes)"
)->fetch(PDO::FETCH_ASSOC);
$version = ($versionRow["c"] ?? "0") . "|" . ($versionRow["mm"] ?? "") . "|" . ($versionRow["cm"] ?? "");

$clientVersion = trim((string) ($_GET["v"] ?? ""));
if ($clientVersion !== "" && $clientVersion === $version) {
    echo json_encode([
        "success" => true,
        "unchanged" => true,
        "version" => $version,
        "generated_at" => date("c"),
    ]);
    exit();
}

$statusColumns = operations_status_select_columns_sql();
$sql = "SELECT
    {$statusColumns},
    remarks,
    created_date,
    modified_date
FROM operations
WHERE entry_type IN ($monitoredTypes)
ORDER BY
    CASE WHEN TRIM(COALESCE(waybill, '')) = '' THEN 1 ELSE 0 END ASC,
    waybill ASC,
    entry_id DESC";

$stmt = $conn->query($sql);

$records = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $normalizedType = operations_normalize_entry_type($row["entry_type"] ?? "");
    $missingFields = operations_missing_fields($row);
    $waybills = array_values(array_unique(array_filter([
        trim((string) ($row["waybill"] ?? "")),
        trim((string) ($row["waybill_empty"] ?? "")),
    ], fn($value) => $value !== "")));
    $drivers = array_values(array_unique(array_filter([
        trim((string) ($row["driver"] ?? "")),
        trim((string) ($row["driver_return"] ?? "")),
    ], fn($value) => $value !== "")));
    $vanParts = array_values(array_unique(array_filter([
        trim((string) ($row["van_alpha"] ?? "")),
        trim((string) ($row["van_number"] ?? "")),
        trim((string) ($row["van_name"] ?? "")),
    ], fn($value) => $value !== "")));

    $records[] = [
        "entry_id" => (int) $row["entry_id"],
        "entry_type" => $row["entry_type"],
        "waybills" => $waybills,
        "drivers" => $drivers,
        "van" => implode(" ", $vanParts),
        "remarks" => $row["remarks"],
        "created_date" => $row["created_date"],
        "modified_date" => $row["modified_date"],
        "route" => $routeByType[$normalizedType] ?? "entry",
        "is_complete" => count($missingFields) === 0,
        "missing_count" => count($missingFields),
        "missing_fields" => $missingFields,
    ];
}

echo json_encode([
    "success" => true,
    "records" => $records,
    "version" => $version,
    "generated_at" => date("c"),
]);
exit();
?>
