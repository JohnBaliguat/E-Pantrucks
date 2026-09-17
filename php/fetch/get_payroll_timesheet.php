<?php
include "../config/config.php";
include "../helpers/payroll_calendar.php";

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

function payroll_name_key($value): string
{
    $value = strtoupper(payroll_normalize_text($value));
    $value = str_replace('.', '', $value);
    return preg_replace('/\s+/', ' ', $value);
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

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format.']);
    exit;
}

if ($dateFrom > $dateTo) {
    echo json_encode(['success' => false, 'message' => 'Date from must not be later than date to.']);
    exit;
}

$rangeDays = (int) ((strtotime($dateTo) - strtotime($dateFrom)) / 86400) + 1;
if ($rangeDays > 45) {
    echo json_encode(['success' => false, 'message' => 'Date range must not exceed 45 days.']);
    exit;
}

// Driver master: daily rate lookup by ID number and by "LNAME, FNAME MNAME".
$rateById = [];
$rateByName = [];
$rateStmt = $conn->query('SELECT driver_fname, driver_mname, driver_lname, "driver_IdNumber", "driver_dailyRate" FROM drivers');
while ($row = $rateStmt->fetch(PDO::FETCH_ASSOC)) {
    $rate = payroll_normalize_amount($row['driver_dailyRate'] ?? '');
    $id = payroll_normalize_text($row['driver_IdNumber'] ?? '');
    if ($id !== '') {
        $rateById[$id] = $rate;
    }

    $last = payroll_normalize_text($row['driver_lname'] ?? '');
    $first = payroll_normalize_text($row['driver_fname'] ?? '');
    $middle = payroll_normalize_text($row['driver_mname'] ?? '');
    if ($last !== '' && $first !== '') {
        $full = $last . ', ' . $first . ($middle !== '' ? ' ' . $middle : '');
        $rateByName[payroll_name_key($full)] = $rate;
        $rateByName[payroll_name_key($last . ', ' . $first)] = $rate;
    }
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

// Group piece-work earnings per driver per day (Excel DataBase equivalent).
$drivers = [];
$segments = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $workDate = payroll_normalize_text($row['work_date'] ?? '');
    if ($workDate === '') {
        continue;
    }

    foreach (payroll_expand_operation($row) as $entry) {
        if (($entry['amount'] ?? 0) <= 0) {
            continue;
        }

        $key = $entry['driver_name'] . '|' . $entry['driver_id'];
        if (!isset($drivers[$key])) {
            $drivers[$key] = [
                'driver_name' => $entry['driver_name'],
                'driver_id' => $entry['driver_id'],
                'days' => [],
            ];
        }

        if (!isset($drivers[$key]['days'][$workDate])) {
            $drivers[$key]['days'][$workDate] = ['earning' => 0.0, 'trips' => 0, 'segments' => []];
        }

        $segment = payroll_normalize_text($entry['segment']);
        $drivers[$key]['days'][$workDate]['earning'] += (float) $entry['amount'];
        $drivers[$key]['days'][$workDate]['trips']++;
        $drivers[$key]['days'][$workDate]['segments'][$segment] =
            ($drivers[$key]['days'][$workDate]['segments'][$segment] ?? 0) + (float) $entry['amount'];

        if ($segment !== '' && !in_array($segment, $segments, true)) {
            $segments[] = $segment;
        }
    }
}

sort($segments);

// Build the calendar for the requested range once.
$dates = [];
for ($ts = strtotime($dateFrom); $ts <= strtotime($dateTo); $ts += 86400) {
    $date = date('Y-m-d', $ts);
    $year = (int) date('Y', $ts);
    $holiday = payroll_holidays($year)[$date] ?? null;
    $dates[] = [
        'date' => $date,
        'day' => date('D', $ts),
        'payclass' => payroll_payclass($date),
        'holiday' => $holiday !== null ? $holiday['name'] : '',
    ];
}

$sapColumns = payroll_sap_columns();

// Apply the Excel pay rules per driver per day:
// - Worked day: Final Pay = max(piece work, daily rate). Mode PR when piece
//   work wins, MIN when the daily-rate guarantee tops it up. 8 SAP hours go
//   to the day's pay class code; the excess over the daily rate is reported
//   as piece rate (Excel SAPPiecerate).
// - Unworked regular holiday following a paid day: Guaranteed Pay (GP) at
//   the daily rate, 8 hours under code 92.
$rows = [];
$grand = ['final_pay' => 0.0, 'piece_work' => 0.0, 'basic_pay' => 0.0, 'hours' => 0.0, 'days' => 0];

foreach ($drivers as $driver) {
    $dailyRate = $rateById[$driver['driver_id']]
        ?? $rateByName[payroll_name_key($driver['driver_name'])]
        ?? 0.0;

    $hours = array_fill_keys(array_keys($sapColumns), 0);
    $detail = [];
    $daysWorked = 0;
    $pieceWork = 0.0;
    $basicPay = 0.0;
    $finalPay = 0.0;
    $prevDayPaid = false;

    foreach ($dates as $calDay) {
        $dayData = $driver['days'][$calDay['date']] ?? null;
        $earning = $dayData !== null ? (float) $dayData['earning'] : 0.0;
        $payClass = $calDay['payclass'];

        $mode = '';
        $dayFinal = 0.0;
        $dayPiece = 0.0;
        $dayBasic = 0.0;
        $sapCode = null;

        if ($earning > 0) {
            $daysWorked++;
            if ($dailyRate > 0 && $earning < $dailyRate) {
                $mode = 'MIN';
                $dayFinal = $dailyRate;
                $dayBasic = $dailyRate;
            } else {
                $mode = 'PR';
                $dayFinal = $earning;
                $dayBasic = min($earning, $dailyRate > 0 ? $dailyRate : $earning);
                $dayPiece = $earning - $dayBasic;
            }
            $sapCode = payroll_sap_code($payClass);
            $hours[$sapCode] = ($hours[$sapCode] ?? 0) + 8;
        } elseif (in_array($payClass, ['RH', 'RHSUN'], true) && $prevDayPaid && $dailyRate > 0) {
            $mode = 'GP';
            $dayFinal = $dailyRate;
            $dayBasic = $dailyRate;
            $sapCode = payroll_sap_code('GP');
            $hours[$sapCode] = ($hours[$sapCode] ?? 0) + 8;
        }

        $pieceWork += $dayPiece;
        $basicPay += $dayBasic;
        $finalPay += $dayFinal;
        $prevDayPaid = $dayFinal > 0;

        $detail[] = [
            'date' => $calDay['date'],
            'day' => $calDay['day'],
            'payclass' => $payClass,
            'holiday' => $calDay['holiday'],
            'trips' => $dayData !== null ? (int) $dayData['trips'] : 0,
            'segments' => $dayData !== null ? array_map(
                static fn ($v) => round((float) $v, 2),
                $dayData['segments']
            ) : new stdClass(),
            'earning' => round($earning, 2),
            'mode' => $mode,
            'sap_code' => $sapCode,
            'final_pay' => round($dayFinal, 2),
        ];
    }

    $totalHours = array_sum($hours);

    $rows[] = [
        'driver_name' => $driver['driver_name'],
        'driver_id' => $driver['driver_id'],
        'daily_rate' => round((float) $dailyRate, 2),
        'days' => $daysWorked,
        'hours' => $hours,
        'total_hours' => $totalHours,
        'piece_work' => round($pieceWork, 2),
        'basic_pay' => round($basicPay, 2),
        'final_pay' => round($finalPay, 2),
        'detail' => $detail,
    ];

    $grand['final_pay'] += $finalPay;
    $grand['piece_work'] += $pieceWork;
    $grand['basic_pay'] += $basicPay;
    $grand['hours'] += $totalHours;
    $grand['days'] += $daysWorked;
}

usort($rows, static fn ($a, $b) => strcasecmp($a['driver_name'], $b['driver_name']));

$year = (int) substr($dateFrom, 0, 4);

echo json_encode([
    'success' => true,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'sap_columns' => $sapColumns,
    'segments' => $segments,
    'rows' => $rows,
    'summary' => [
        'total_drivers' => count($rows),
        'total_days' => $grand['days'],
        'total_hours' => $grand['hours'],
        'total_basic_pay' => number_format($grand['basic_pay'], 2, '.', ''),
        'total_piece_work' => number_format($grand['piece_work'], 2, '.', ''),
        'grand_final_pay' => number_format($grand['final_pay'], 2, '.', ''),
    ],
    'pay_periods' => payroll_pay_periods($year),
    'generated_at' => date('c'),
]);
exit;
?>
