<?php
include "../config/config.php";

header('Content-Type: application/json; charset=utf-8');

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

$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));

if ($dateFrom === '' || $dateTo === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Date range is required.',
    ]);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid date format.',
    ]);
    exit;
}

if ($dateFrom > $dateTo) {
    echo json_encode([
        'success' => false,
        'message' => 'Date from must not be later than date to.',
    ]);
    exit;
}

$sql = "SELECT
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

$driverTotals = [];
$grandTotal = 0.0;
$grandEntries = 0;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    foreach (payroll_expand_operation($row) as $entry) {
        if (($entry['amount'] ?? 0) <= 0) {
            continue;
        }

        $key = $entry['driver_name'] . '|' . $entry['driver_id'];
        if (!isset($driverTotals[$key])) {
            $driverTotals[$key] = [
                'driver_name' => $entry['driver_name'],
                'driver_id' => $entry['driver_id'],
                'total_entries' => 0,
                'total_piece_rate' => 0.0,
            ];
        }

        $driverTotals[$key]['total_entries']++;
        $driverTotals[$key]['total_piece_rate'] += (float) $entry['amount'];
        $grandTotal += (float) $entry['amount'];
        $grandEntries++;
    }
}

usort($driverTotals, function ($a, $b) {
    return strcasecmp($a['driver_name'], $b['driver_name']);
});

$rows = [];
foreach ($driverTotals as $row) {
    $rows[] = [
        'driver_name' => $row['driver_name'],
        'driver_id' => $row['driver_id'],
        'total_entries' => (int) $row['total_entries'],
        'total_piece_rate' => number_format((float) $row['total_piece_rate'], 2, '.', ''),
    ];
}

echo json_encode([
    'success' => true,
    'rows' => $rows,
    'summary' => [
        'total_drivers' => count($rows),
        'total_entries' => $grandEntries,
        'grand_total_piece_rate' => number_format($grandTotal, 2, '.', ''),
    ],
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'generated_at' => date('c'),
]);
exit;
?>
