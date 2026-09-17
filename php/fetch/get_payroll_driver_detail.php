<?php
include "../config/config.php";

header("Content-Type: application/json; charset=utf-8");

function payroll_normalize_text($value): string
{
    return trim((string) $value);
}

function payroll_normalize_driver_name($value): string
{
    $value = payroll_normalize_text($value);
    return $value === '' ? 'No Driver' : $value;
}

function payroll_normalize_driver_id($value): string
{
    $value = payroll_normalize_text($value);
    return $value === '' ? '-' : $value;
}

function payroll_normalize_amount($value): float
{
    $value = payroll_normalize_text($value);
    return $value === '' ? 0.0 : (float) $value;
}

function payroll_same_driver(array $row, string $secondaryNameField, string $secondaryIdField): bool
{
    $primaryName = payroll_normalize_text($row['driver'] ?? '');
    $primaryId = payroll_normalize_text($row['driver_idNumber'] ?? '');
    $secondaryName = payroll_normalize_text($row[$secondaryNameField] ?? '');
    $secondaryId = payroll_normalize_text($row[$secondaryIdField] ?? '');

    if ($primaryName === '' || $secondaryName === '') {
        return true;
    }

    if ($primaryId !== '' && $secondaryId !== '') {
        return $primaryId === $secondaryId;
    }

    return strcasecmp($primaryName, $secondaryName) === 0;
}

function payroll_expand_operation(array $row): array
{
    $segment = payroll_normalize_text($row['segment'] ?? '');
    $pieceRate = payroll_normalize_amount($row['piece_rate'] ?? '');
    $pieceRateEmpty = payroll_normalize_amount($row['piece_rate_empty'] ?? '');
    $pieceRateLoaded = payroll_normalize_amount($row['piece_rate_loaded'] ?? '');
    $entryType = payroll_normalize_text($row['entry_type'] ?? '');

    if ($entryType === 'RV ENTRY') {
        if (!payroll_same_driver($row, 'delivered_by_driver', 'delivered_by_driverIdNumber')) {
            $entries = [];

            if ($pieceRateEmpty > 0) {
                $entries[] = [
                    'driver_name' => payroll_normalize_driver_name($row['driver'] ?? ''),
                    'driver_id' => payroll_normalize_driver_id($row['driver_idNumber'] ?? ''),
                    'segment' => $segment,
                    'amount' => $pieceRateEmpty,
                ];
            }

            if ($pieceRateLoaded > 0) {
                $entries[] = [
                    'driver_name' => payroll_normalize_driver_name($row['delivered_by_driver'] ?? ''),
                    'driver_id' => payroll_normalize_driver_id($row['delivered_by_driverIdNumber'] ?? ''),
                    'segment' => $segment,
                    'amount' => $pieceRateLoaded,
                ];
            }

            return $entries;
        }
    }

    if ($entryType === 'DRY VAN ENTRY') {
        if (!payroll_same_driver($row, 'driver_return', 'driver_return_idNumber')) {
            $entries = [];

            if ($pieceRateEmpty > 0) {
                $entries[] = [
                    'driver_name' => payroll_normalize_driver_name($row['driver_return'] ?? ''),
                    'driver_id' => payroll_normalize_driver_id($row['driver_return_idNumber'] ?? ''),
                    'segment' => $segment,
                    'amount' => $pieceRateEmpty,
                ];
            }

            if ($pieceRateLoaded > 0) {
                $entries[] = [
                    'driver_name' => payroll_normalize_driver_name($row['driver'] ?? ''),
                    'driver_id' => payroll_normalize_driver_id($row['driver_idNumber'] ?? ''),
                    'segment' => $segment,
                    'amount' => $pieceRateLoaded,
                ];
            }

            if (!empty($entries)) {
                return $entries;
            }
        }
    }

    if ($pieceRate <= 0) {
        return [];
    }

    return [[
        'driver_name' => payroll_normalize_driver_name($row['driver'] ?? ''),
        'driver_id' => payroll_normalize_driver_id($row['driver_idNumber'] ?? ''),
        'segment' => $segment,
        'amount' => $pieceRate,
    ]];
}

$dateFrom = trim((string) ($_GET["date_from"] ?? ""));
$dateTo = trim((string) ($_GET["date_to"] ?? ""));
$driverName = trim((string) ($_GET["driver_name"] ?? ""));
$driverId = trim((string) ($_GET["driver_id"] ?? ""));

if ($dateFrom === "" || $dateTo === "" || $driverName === "") {
    echo json_encode([
        "success" => false,
        "message" => "Driver and date range are required.",
    ]);
    exit();
}

if (
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)
) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid date format.",
    ]);
    exit();
}

if ($dateFrom > $dateTo) {
    echo json_encode([
        "success" => false,
        "message" => "Date from must not be later than date to.",
    ]);
    exit();
}

$segments = [];
$segStmt = $conn->query("SELECT DISTINCT segment FROM trip_rates WHERE TRIM(COALESCE(segment, '')) <> '' ORDER BY segment ASC");
while ($row = $segStmt->fetch(PDO::FETCH_ASSOC)) {
    $segments[] = trim((string) $row["segment"]);
}

$sql = "SELECT
    created_date::date AS work_date,
    entry_type,
    segment,
    driver,
    \"driver_idNumber\",
    delivered_by_driver,
    \"delivered_by_driverIdNumber\",
    driver_return,
    \"driver_return_idNumber\",
    piece_rate,
    piece_rate_empty,
    piece_rate_loaded
FROM operations
WHERE created_date::date BETWEEN ? AND ?";

$stmt = $conn->prepare($sql);
$stmt->execute([$dateFrom, $dateTo]);

$valueMap = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $workDate = $row["work_date"];

    foreach (payroll_expand_operation($row) as $entry) {
        if (($entry['amount'] ?? 0) <= 0) {
            continue;
        }

        if ($entry['driver_name'] !== $driverName) {
            continue;
        }

        if ($driverId !== '' && $entry['driver_id'] !== $driverId) {
            continue;
        }

        $segment = trim((string) $entry["segment"]);
        $valueMap[$workDate][$segment] = ($valueMap[$workDate][$segment] ?? 0) + (float) $entry["amount"];

        if ($segment !== "" && !in_array($segment, $segments, true)) {
            $segments[] = $segment;
        }
    }
}

$rows = [];
$subtotals = array_fill_keys($segments, 0.0);
$grandTotal = 0.0;

$current = strtotime($dateFrom);
$end = strtotime($dateTo);

while ($current <= $end) {
    $dateKey = date("Y-m-d", $current);
    $segmentValues = [];
    $dailyTotal = 0.0;

    foreach ($segments as $segment) {
        $amount = (float) ($valueMap[$dateKey][$segment] ?? 0);
        $segmentValues[$segment] = $amount;
        $subtotals[$segment] += $amount;
        $dailyTotal += $amount;
    }

    $grandTotal += $dailyTotal;

    $rows[] = [
        "date" => $dateKey,
        "display_date" => date("m/d/y", $current),
        "segments" => $segmentValues,
        "daily_total" => $dailyTotal,
    ];

    $current = strtotime("+1 day", $current);
}

echo json_encode([
    "success" => true,
    "driver_name" => $driverName,
    "driver_id" => $driverId === "" ? "-" : $driverId,
    "date_from" => $dateFrom,
    "date_to" => $dateTo,
    "segments" => array_values($segments),
    "rows" => $rows,
    "subtotals" => $subtotals,
    "grand_total" => $grandTotal,
    "generated_at" => date("c"),
]);
exit();
?>
