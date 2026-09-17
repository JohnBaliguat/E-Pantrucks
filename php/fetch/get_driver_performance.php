<?php
include "../config/config.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");

$period = strtolower(trim((string) ($_GET["period"] ?? "weekly")));
if (!in_array($period, ["weekly", "monthly"], true)) {
    $period = "weekly";
}

$dateFromInput = trim((string) ($_GET["date_from"] ?? ""));
$dateToInput = trim((string) ($_GET["date_to"] ?? ""));

$tz = new DateTimeZone("Asia/Manila");
$today = new DateTimeImmutable("now", $tz);

if (
    $dateFromInput !== "" &&
    $dateToInput !== "" &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFromInput) &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateToInput) &&
    $dateFromInput <= $dateToInput
) {
    $dateFrom = $dateFromInput;
    $dateTo = $dateToInput;
} else {
    if ($period === "weekly") {
        $dayOfWeek = (int) $today->format("N");
        $daysBack = ($dayOfWeek - 5 + 7) % 7;
        $start = $today->modify("-{$daysBack} days");
        $end = $start->modify("+6 days");
    } else {
        $start = $today->modify("first day of this month");
        $end = $today->modify("last day of this month");
    }
    $dateFrom = $start->format("Y-m-d");
    $dateTo = $end->format("Y-m-d");
}

function dp_num($value): float
{
    if ($value === null) return 0.0;
    $s = preg_replace('/[^0-9.\-]/', "", (string) $value);
    if ($s === "" || $s === "-" || $s === ".") return 0.0;
    return (float) $s;
}

function dp_norm_name($v): string
{
    return trim((string) $v);
}

function dp_driver_key(string $name, string $id): string
{
    $normalizedId = dp_norm_name($id);
    if ($normalizedId !== '') {
        return 'ID|' . strtoupper($normalizedId);
    }

    return 'NAME|' . strtoupper(dp_norm_name($name));
}

function dp_master_driver_name(array $row): string
{
    $lastName = strtoupper(trim((string) ($row["driver_lname"] ?? "")));
    $firstName = strtoupper(trim((string) ($row["driver_fname"] ?? "")));
    $middleName = strtoupper(trim((string) ($row["driver_mname"] ?? "")));
    $middleInitial = $middleName !== "" ? " " . substr($middleName, 0, 1) . "." : "";
    return trim($lastName . ", " . $firstName . $middleInitial);
}

function dp_like(string $value, string $needle): bool
{
    return stripos($value, $needle) !== false;
}

function dp_series_label(array $row): string
{
    $entryType = strtoupper(trim((string) ($row['entry_type'] ?? '')));
    $billingSku = trim((string) ($row['billing_sku'] ?? ''));
    $billingSkuUpper = strtoupper($billingSku);
    $phNorm = strtoupper(trim((string) ($row['ph'] ?? '')));
    $customer = strtoupper(trim((string) (
        $row['customer_ph'] ??
        $row['ph'] ??
        $row['operations_ph'] ??
        ''
    )));
    $shipper = trim((string) ($row['shipper'] ?? ''));

    if ($entryType === 'RV ENTRY') {
        $shipperSeriesMap = [
            'DOLE' => 'TDC-Dole',
            'SUMIFRU' => 'TDC-Sumifru',
            'ABC CATEEL' => 'ABC Cateel',
            'ABC PANTUKAN' => 'ABC Pantukan',
            'ABC DONMAR' => 'ABC Donmar',
            'ABC LUPON' => 'ABC Lupon',
            'FARMIND' => 'TDC Farmind',
            'GOOD FARMER' => 'TDC Good Farmer',
            'TDC - CLASS B' => 'DICT B/bulk - Class B Bananas',
        ];
        $shipperKey = strtoupper($shipper);
        if ($shipperKey !== '') {
            return $shipperSeriesMap[$shipperKey] ?? $shipper;
        }
        return 'Unspecified';
    }

    if ($entryType === 'DPC_KDS & OPM ENTRY') {
        if (dp_like($phNorm, 'PH') || dp_like($phNorm, 'TDC')) {
            return 'TPD KDs - TDC Compound Trips';
        }
        return 'TPD KDs - Outside Trips';
    }

    if ($entryType === 'CARGO TRUCK ENTRY' && dp_like($billingSkuUpper, 'CT-OTHER HAULING')) {
        $outside = dp_num($row['outside'] ?? '');
        $compound = dp_num($row['compound'] ?? '');
        if ($outside > 0 && $outside >= $compound) {
            return 'Cargo Truck Hauling-Outside Trips';
        }
        if ($compound > 0) {
            return 'Cargo Truck Hauling-Compound Trips';
        }
        return 'Cargo Truck Hauling-Outside Trips';
    }

    if ($entryType === 'DRY VAN ENTRY' && dp_like($billingSkuUpper, 'TPD')) {
        return 'TPD Container DPC Export';
    }

    if (dp_like($billingSkuUpper, 'RECYCLABLE')) {
        return 'TPD Recyclable Plastics';
    }
    if (dp_like($billingSkuUpper, 'OPM')) {
        return 'OPM Hauling';
    }
    if (
        dp_like($billingSkuUpper, 'RC') && dp_like($billingSkuUpper, 'REPOSITION') ||
        dp_like($billingSkuUpper, 'RC-') ||
        dp_like($billingSkuUpper, 'REPOSITIONING')
    ) {
        return 'RC Repositioning';
    }
    if (
        dp_like($billingSkuUpper, 'GARBAGE') ||
        dp_like($billingSkuUpper, 'INDUSTRIAL') ||
        dp_like($billingSkuUpper, 'WASTE')
    ) {
        return 'Garbage/Industrial Waste';
    }
    if (dp_like($billingSkuUpper, 'HEAVY')) {
        return 'Heavy Equipment Transport';
    }
    if ($entryType === 'CARGO TRUCK ENTRY' && dp_like($billingSkuUpper, 'REJECT')) {
        return 'TDC Reject Plastics';
    }
    if (dp_like($billingSkuUpper, 'MISCELLANEOUS') || dp_like($billingSkuUpper, 'OTHER HAULING')) {
        return 'Miscellaneous/Other Hauling';
    }
    if (dp_like($billingSkuUpper, 'HUSTLING') && $customer === 'DOLE') {
        return 'Container Hustling- DOLE';
    }
    if (dp_like($billingSkuUpper, 'HUSTLING') && $customer === 'DICT') {
        return 'Container Hustling- DICT';
    }

    if ($shipper !== '') {
        return $shipper;
    }
    if ($billingSku !== '') {
        return $billingSku;
    }
    if ($entryType !== '') {
        return $entryType;
    }

    return 'Unspecified';
}

function dp_effective_date(array $row): ?string
{
    $candidates = [
        $row['waybill_date'] ?? null,
        $row['cargo_date'] ?? null,
        $row['dpc_date'] ?? null,
        $row['others_date'] ?? null,
        $row['date_hauled'] ?? null,
        $row['pullout_location_arrival_date'] ?? null,
        $row['created_date'] ?? null,
    ];
    foreach ($candidates as $c) {
        $s = trim((string) $c);
        if ($s === '' || $s === '0000-00-00' || $s === '0000-00-00 00:00:00') continue;
        // strip time portion if present
        return substr($s, 0, 10);
    }
    return null;
}

try {
    $sql = "
        SELECT
            entry_type,
            shipper,
            customer_ph,
            ph,
            operations_ph,
            billing_sku,
            outside,
            compound,
            driver, \"driver_idNumber\",
            driver_return, \"driver_return_idNumber\",
            delivered_by_driver, \"delivered_by_driverIdNumber\",
            piece_rate, piece_rate_empty, piece_rate_loaded,
            kms,
            waybill_date, cargo_date, dpc_date, others_date,
            date_hauled, pullout_location_arrival_date, created_date
        FROM operations
        WHERE COALESCE(
            NULLIF(waybill_date::text, '')::date,
            NULLIF(cargo_date::text, '')::date,
            NULLIF(dpc_date::text, '')::date,
            NULLIF(others_date::text, '')::date,
            NULLIF(date_hauled::text, '')::date,
            NULLIF(pullout_location_arrival_date::text, '')::date,
            created_date::date
        ) BETWEEN ? AND ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$dateFrom, $dateTo]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Aggregate per driver. Each operation may credit one or two drivers.
    // Convention: for RV (split when driver != delivered_by_driver) and DRY VAN
    // (split when driver != driver_return), split by piece_rate_empty/_loaded.
    // Otherwise the primary `driver` gets full piece_rate.
    $byDriver = [];
    $byEntryType = [];
    $bySeries = [];
    $byDay = [];
    $totalTrips = 0;
    $totalEarnings = 0.0;
    $totalKms = 0.0;

    foreach ($rows as $row) {
        $entryType = trim((string) ($row['entry_type'] ?? ''));
        $series = dp_series_label($row);
        $effDate = dp_effective_date($row);
        $kms = dp_num($row['kms'] ?? '');
        $primaryName = dp_norm_name($row['driver'] ?? '');
        $primaryId = dp_norm_name($row['driver_idNumber'] ?? '');
        $rate = dp_num($row['piece_rate'] ?? '');
        $rateEmpty = dp_num($row['piece_rate_empty'] ?? '');
        $rateLoaded = dp_num($row['piece_rate_loaded'] ?? '');

        $credits = [];

        if ($entryType === 'RV ENTRY') {
            $secName = dp_norm_name($row['delivered_by_driver'] ?? '');
            $secId = dp_norm_name($row['delivered_by_driverIdNumber'] ?? '');
            $isSplit = $primaryName !== '' && $secName !== '' &&
                strcasecmp($primaryName, $secName) !== 0;
            if ($isSplit && ($rateEmpty > 0 || $rateLoaded > 0)) {
                if ($rateEmpty > 0) $credits[] = [$primaryName, $primaryId, $rateEmpty];
                if ($rateLoaded > 0) $credits[] = [$secName, $secId, $rateLoaded];
            }
        } elseif ($entryType === 'DRY VAN ENTRY') {
            $secName = dp_norm_name($row['driver_return'] ?? '');
            $secId = dp_norm_name($row['driver_return_idNumber'] ?? '');
            $isSplit = $primaryName !== '' && $secName !== '' &&
                strcasecmp($primaryName, $secName) !== 0;
            if ($isSplit && ($rateEmpty > 0 || $rateLoaded > 0)) {
                if ($rateLoaded > 0) $credits[] = [$primaryName, $primaryId, $rateLoaded];
                if ($rateEmpty > 0) $credits[] = [$secName, $secId, $rateEmpty];
            }
        }

        if (empty($credits)) {
            if ($primaryName === '') continue; // skip rows without a primary driver
            $credits[] = [$primaryName, $primaryId, $rate];
        }

        foreach ($credits as $c) {
            [$name, $id, $amount] = $c;
            $key = dp_driver_key($name, $id);
            if (!isset($byDriver[$key])) {
                $byDriver[$key] = [
                    'driver_name' => $name,
                    'driver_id' => $id,
                    'trips' => 0,
                    'earnings' => 0.0,
                    'kms' => 0.0,
                    'last_trip' => null,
                    'series_trips' => [],
                ];
            }
            $byDriver[$key]['trips']++;
            $byDriver[$key]['earnings'] += $amount;
            $byDriver[$key]['kms'] += $kms;
            if ($series !== '') {
                $byDriver[$key]['series_trips'][$series] = ($byDriver[$key]['series_trips'][$series] ?? 0) + 1;
            }
            if ($effDate !== null) {
                $cur = $byDriver[$key]['last_trip'];
                if ($cur === null || $effDate > $cur) {
                    $byDriver[$key]['last_trip'] = $effDate;
                }
            }
            $totalTrips++;
            $totalEarnings += $amount;
            $totalKms += $kms;
            if ($entryType !== '') {
                $byEntryType[$entryType] = ($byEntryType[$entryType] ?? 0) + 1;
            }
            if ($series !== '') {
                $bySeries[$series] = ($bySeries[$series] ?? 0) + 1;
            }
            if ($effDate !== null) {
                $byDay[$effDate] = ($byDay[$effDate] ?? 0) + 1;
            }
        }
    }

    $activeDrivers = count($byDriver);

    $registeredDriversStmt = $conn->query('
        SELECT driver_fname, driver_mname, driver_lname, "driver_IdNumber"
        FROM drivers
        ORDER BY driver_lname ASC, driver_fname ASC
    ');
    $registeredDrivers = $registeredDriversStmt ? $registeredDriversStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $totalDrivers = count($registeredDrivers);

    foreach ($registeredDrivers as $driverRow) {
        $driverName = dp_master_driver_name($driverRow);
        $driverId = dp_norm_name($driverRow['driver_IdNumber'] ?? '');
        $key = dp_driver_key($driverName, $driverId);

        if (!isset($byDriver[$key])) {
            $byDriver[$key] = [
                'driver_name' => $driverName !== '' ? $driverName : '-',
                'driver_id' => $driverId,
                'trips' => 0,
                'earnings' => 0.0,
                'kms' => 0.0,
                'last_trip' => null,
                'series_trips' => [],
            ];
            continue;
        }

        if (($byDriver[$key]['driver_name'] ?? '') === '' && $driverName !== '') {
            $byDriver[$key]['driver_name'] = $driverName;
        }
        if (($byDriver[$key]['driver_id'] ?? '') === '' && $driverId !== '') {
            $byDriver[$key]['driver_id'] = $driverId;
        }
    }

    // Build daily trend with zero-fill
    $labels = [];
    $values = [];
    $cursor = new DateTimeImmutable($dateFrom, $tz);
    $endDate = new DateTimeImmutable($dateTo, $tz);
    while ($cursor <= $endDate) {
        $key = $cursor->format("Y-m-d");
        $labels[] = $cursor->format("M d");
        $values[] = (int) ($byDay[$key] ?? 0);
        $cursor = $cursor->modify("+1 day");
    }

    $sortDriversDesc = static function (&$list): void {
        usort($list, static function ($a, $b) {
            if ($b['trips'] !== $a['trips']) return $b['trips'] - $a['trips'];
            return $b['earnings'] <=> $a['earnings'];
        });
    };

    $sortDriversAsc = static function (&$list): void {
        usort($list, static function ($a, $b) {
            if ($a['trips'] !== $b['trips']) return $a['trips'] <=> $b['trips'];
            if ($a['earnings'] !== $b['earnings']) return $a['earnings'] <=> $b['earnings'];
            return strcasecmp((string) $a['driver_name'], (string) $b['driver_name']);
        });
    };

    $allDriversList = array_values($byDriver);
    $sortDriversDesc($allDriversList);

    $driversList = array_values(array_filter($allDriversList, static function ($driver) {
        return (int) ($driver['trips'] ?? 0) > 0;
    }));

    $zeroTripDrivers = array_values(array_filter($allDriversList, static function ($driver) {
        return (int) ($driver['trips'] ?? 0) === 0;
    }));
    $sortDriversAsc($zeroTripDrivers);

    $lowTripDrivers = $driversList;
    $sortDriversAsc($lowTripDrivers);

    // Sort active drivers by trips desc
    usort($driversList, static function ($a, $b) {
        if ($b['trips'] !== $a['trips']) return $b['trips'] - $a['trips'];
        return $b['earnings'] <=> $a['earnings'];
    });

    $cleanInt = static fn($v) => $v == (int) $v ? (int) $v : (float) $v;
    foreach ($driversList as &$d) {
        $d['earnings'] = round($d['earnings'], 2);
        $d['kms'] = $cleanInt($d['kms']);
        ksort($d['series_trips']);
    }
    unset($d);

    foreach ($allDriversList as &$d) {
        $d['earnings'] = round($d['earnings'], 2);
        $d['kms'] = $cleanInt($d['kms']);
        ksort($d['series_trips']);
    }
    unset($d);

    foreach ($lowTripDrivers as &$d) {
        $d['earnings'] = round($d['earnings'], 2);
        $d['kms'] = $cleanInt($d['kms']);
        ksort($d['series_trips']);
    }
    unset($d);

    foreach ($zeroTripDrivers as &$d) {
        $d['earnings'] = round($d['earnings'], 2);
        $d['kms'] = $cleanInt($d['kms']);
        ksort($d['series_trips']);
    }
    unset($d);

    // Top 10 for chart
    $top = array_slice($driversList, 0, 10);
    arsort($bySeries);

    echo json_encode([
        "success" => true,
        "period" => $period,
        "date_from" => $dateFrom,
        "date_to" => $dateTo,
        "totals" => [
            "total_drivers" => $totalDrivers,
            "active_drivers" => $activeDrivers,
            "total_trips" => $totalTrips,
            "total_earnings" => round($totalEarnings, 2),
            "total_kms" => $cleanInt($totalKms),
        ],
        "drivers" => $driversList,
        "all_drivers" => $allDriversList,
        "top_drivers" => $top,
        "low_trip_drivers" => $lowTripDrivers,
        "no_trip_drivers" => $zeroTripDrivers,
        "by_entry_type" => $byEntryType,
        "by_series" => $bySeries,
        "trend" => [
            "labels" => $labels,
            "trips" => $values,
        ],
        "generated_at" => date("c"),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to build driver performance: " . $e->getMessage(),
    ]);
}
exit();
