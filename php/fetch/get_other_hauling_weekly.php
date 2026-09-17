<?php
include "../config/config.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

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
        // Operational week Friday -> Thursday, anchored on most recent Friday.
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

$dateFromQ = $conn->quote($dateFrom);
$dateToQ = $conn->quote($dateTo);

$opsCte = "
WITH ops AS (
    SELECT
        UPPER(COALESCE(entry_type::text, '')) AS entry_type_norm,
        COALESCE(billing_sku::text, '') AS billing_sku,
        UPPER(COALESCE(
            NULLIF(customer_ph::text, ''),
            NULLIF(ph::text, ''),
            NULLIF(operations_ph::text, ''),
            ''
        )) AS customer,
        UPPER(COALESCE(NULLIF(ph::text, ''), '')) AS ph_norm,
        CASE
            WHEN NULLIF(REGEXP_REPLACE(COALESCE(outside::text, ''), '[^0-9.\\-]', '', 'g'), '') IS NULL THEN 0
            ELSE NULLIF(REGEXP_REPLACE(COALESCE(outside::text, ''), '[^0-9.\\-]', '', 'g'), '')::numeric
        END AS outside_num,
        CASE
            WHEN NULLIF(REGEXP_REPLACE(COALESCE(compound::text, ''), '[^0-9.\\-]', '', 'g'), '') IS NULL THEN 0
            ELSE NULLIF(REGEXP_REPLACE(COALESCE(compound::text, ''), '[^0-9.\\-]', '', 'g'), '')::numeric
        END AS compound_num,
        CASE
            WHEN NULLIF(REGEXP_REPLACE(COALESCE(load_quantity_weight::text, ''), '[^0-9.\\-]', '', 'g'), '') IS NULL THEN 0
            ELSE NULLIF(REGEXP_REPLACE(COALESCE(load_quantity_weight::text, ''), '[^0-9.\\-]', '', 'g'), '')::numeric
        END AS load_qty_num,
        COALESCE(
            NULLIF(others_date::text, '')::date,
            NULLIF(cargo_date::text, '')::date,
            NULLIF(dpc_date::text, '')::date,
            NULLIF(waybill_date::text, '')::date,
            NULLIF(pullout_location_arrival_date::text, '')::date,
            created_date::date
        ) AS eff_date
    FROM operations
)
";

// Rules now match against billing_sku (built from operations/customer/route at insert time)
// rather than the free-text segment/activity columns.
$rules = [
    [1, 'KDs',         'TPD',                          'TPD KDs - TDC Compound Trips',
        "entry_type_norm = 'DPC_KDS & OPM ENTRY' AND (ph_norm LIKE '%PH%' OR ph_norm LIKE '%TDC%')", 'count'],
    [2, 'KDs',         'TPD',                          'TPD KDs - Outside Trips',
        "entry_type_norm = 'DPC_KDS & OPM ENTRY' AND ph_norm NOT LIKE '%PH%' AND ph_norm NOT LIKE '%TDC%'", 'count'],
    [3, 'CARGO TRUCK', 'TPD or TADECO',                'Cargo Truck Hauling-Outside Trips',
        "billing_sku ILIKE 'CT-Other Hauling%'", 'sum_outside'],
    [4, 'CARGO TRUCK', 'TPD or TADECO',                'Cargo Truck Hauling-Compound Trips',
        "billing_sku ILIKE 'CT-Other Hauling%'", 'sum_compound'],
    [5, 'DRYVAN',      'TPD',                          'TPD Container DPC Export',
        "entry_type_norm = 'DRY VAN ENTRY' AND billing_sku ILIKE '%TPD%'", 'count'],
    [6, 'OTHERS',      'TPD',                          'TPD Recyclable Plastics',
        "billing_sku ILIKE '%Recyclable%'", 'count'],
    [7, 'OTHERS',      'TPD',                          'OPM Hauling',
        "billing_sku ILIKE '%OPM%'", 'count'],
    [8, 'OTHERS',      'TADECO',                       'RC Repositioning',
        "billing_sku ILIKE '%RC%Reposition%' OR billing_sku ILIKE 'RC-%' OR billing_sku ILIKE '%Repositioning%'", 'count'],
    [9, 'OTHERS',      'TPD or DICT',                  'Garbage/Industrial Waste',
        "billing_sku ILIKE '%Garbage%' OR billing_sku ILIKE '%Industrial%' OR billing_sku ILIKE '%Waste%'", 'count'],
    [10, 'OTHERS',     'TADECO',                       'Heavy Equipment Transport',
        "billing_sku ILIKE '%Heavy%'", 'count'],
    [11, 'CARGO TRUCK','TADECO',                       'TDC Reject Plastics',
        "entry_type_norm = 'CARGO TRUCK ENTRY' AND billing_sku ILIKE '%Reject%'", 'count'],
    [12, 'OTHERS',     'TADECO or TPD or OTHER CUSTOMER','Miscellaneous/Other Hauling',
        "billing_sku ILIKE '%Miscellaneous%' OR billing_sku ILIKE '%Other Hauling%'", 'count'],
    [13, 'OTHERS',     'DOLE',                         'Container Hustling- DOLE',
        "billing_sku ILIKE '%Hustling%' AND customer = 'DOLE'", 'sum_load_qty'],
    [14, 'OTHERS',     'DICT',                         'Container Hustling- DICT',
        "billing_sku ILIKE '%Hustling%' AND customer = 'DICT'", 'sum_load_qty'],
];

$dateFilter = "eff_date BETWEEN $dateFromQ AND $dateToQ";

// 1. Per-category totals (UNION ALL of 14 selects)
$selectParts = [];
foreach ($rules as $r) {
    [$sort, $entryType, $systemId, $series, $where, $metric] = $r;
    $entryTypeQ = $conn->quote($entryType);
    $systemIdQ = $conn->quote($systemId);
    $seriesQ = $conn->quote($series);

    // $where may contain OR terms, so it must be parenthesized before being
    // ANDed with the date filter — otherwise AND binds only to the last OR
    // term and the date range is ignored for the earlier terms.
    if ($metric === 'sum_outside') {
        $tripsExpr = "COALESCE(SUM(outside_num) FILTER (WHERE ($where) AND $dateFilter), 0)";
    } elseif ($metric === 'sum_compound') {
        $tripsExpr = "COALESCE(SUM(compound_num) FILTER (WHERE ($where) AND $dateFilter), 0)";
    } elseif ($metric === 'sum_load_qty') {
        $tripsExpr = "COALESCE(SUM(load_qty_num) FILTER (WHERE ($where) AND $dateFilter), 0)";
    } else {
        $tripsExpr = "COALESCE(COUNT(*) FILTER (WHERE ($where) AND $dateFilter), 0)";
    }

    $selectParts[] = "SELECT $sort AS sort_order, $entryTypeQ AS entry_type, $systemIdQ AS system_id, $seriesQ AS series, ($tripsExpr)::numeric AS trips FROM ops";
}
$rowsSql = $opsCte . implode("\nUNION ALL\n", $selectParts) . "\nORDER BY sort_order";

$seriesTrendSelectParts = [];
foreach ($rules as $r) {
    [, , , $series, $where, $metric] = $r;
    $seriesQ = $conn->quote($series);

    if ($metric === 'sum_outside') {
        $trendTripsExpr = "COALESCE(SUM(outside_num) FILTER (WHERE $where), 0)";
    } elseif ($metric === 'sum_compound') {
        $trendTripsExpr = "COALESCE(SUM(compound_num) FILTER (WHERE $where), 0)";
    } elseif ($metric === 'sum_load_qty') {
        $trendTripsExpr = "COALESCE(SUM(load_qty_num) FILTER (WHERE $where), 0)";
    } else {
        $trendTripsExpr = "COALESCE(COUNT(*) FILTER (WHERE $where), 0)";
    }

    $seriesTrendSelectParts[] = "
        SELECT eff_date AS day, $seriesQ AS series, ($trendTripsExpr)::numeric AS trips
        FROM ops
        WHERE $dateFilter
        GROUP BY eff_date
    ";
}
$seriesTrendSql = $opsCte . implode("\nUNION ALL\n", $seriesTrendSelectParts) . "\nORDER BY day ASC, series ASC";

// 2. Daily trend: per eff_date, sum trip attribution across all rules.
$trendCases = [];
foreach ($rules as $r) {
    [, , , , $where, $metric] = $r;
    if ($metric === 'sum_outside') {
        $trendCases[] = "(CASE WHEN $where THEN outside_num ELSE 0 END)";
    } elseif ($metric === 'sum_compound') {
        $trendCases[] = "(CASE WHEN $where THEN compound_num ELSE 0 END)";
    } elseif ($metric === 'sum_load_qty') {
        $trendCases[] = "(CASE WHEN $where THEN load_qty_num ELSE 0 END)";
    } else {
        $trendCases[] = "(CASE WHEN $where THEN 1 ELSE 0 END)";
    }
}
$trendExpr = implode(" + ", $trendCases);
$trendSql = $opsCte . "
SELECT eff_date AS day, COALESCE(SUM($trendExpr), 0)::numeric AS trips
FROM ops
WHERE $dateFilter
GROUP BY eff_date
ORDER BY eff_date
";

try {
    $stmt = $conn->prepare($rowsSql);
    $stmt->execute();
    $report = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt2 = $conn->prepare($trendSql);
    $stmt2->execute();
    $trendRows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    $stmt3 = $conn->prepare($seriesTrendSql);
    $stmt3->execute();
    $seriesTrendRows = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    // Format breakdown rows
    $rowsOut = [];
    $total = 0.0;
    $byEntryType = [];
    $bySystemId = [];
    $topSeries = ['series' => '-', 'trips' => 0];
    $activeCategories = 0;

    foreach ($report as $row) {
        $trips = (float) $row['trips'];
        $tripsClean = $trips == (int) $trips ? (int) $trips : $trips;
        $total += $trips;
        if ($trips > 0) {
            $activeCategories++;
        }
        if ($trips > $topSeries['trips']) {
            $topSeries = ['series' => $row['series'], 'trips' => $tripsClean];
        }
        $et = $row['entry_type'];
        $sid = $row['system_id'];
        $byEntryType[$et] = ($byEntryType[$et] ?? 0) + $trips;
        $bySystemId[$sid] = ($bySystemId[$sid] ?? 0) + $trips;
        $rowsOut[] = [
            "entry_type" => $et,
            "system_id" => $sid,
            "series" => $row['series'],
            "trips" => $tripsClean,
        ];
    }

    // Build full daily label sequence (fill missing days with 0)
    $trendMap = [];
    foreach ($trendRows as $row) {
        $trendMap[$row['day']] = (float) $row['trips'];
    }
    $seriesTrendMap = [];
    foreach ($seriesTrendRows as $row) {
        $series = (string) $row['series'];
        $day = (string) $row['day'];
        if ($series === '' || $day === '') {
            continue;
        }
        if (!isset($seriesTrendMap[$series])) {
            $seriesTrendMap[$series] = [];
        }
        $seriesTrendMap[$series][$day] = (float) $row['trips'];
    }
    $labels = [];
    $values = [];
    $seriesTrendOut = [];
    $cursor = new DateTimeImmutable($dateFrom, $tz);
    $endDate = new DateTimeImmutable($dateTo, $tz);
    while ($cursor <= $endDate) {
        $key = $cursor->format("Y-m-d");
        $label = $cursor->format("M d");
        $labels[] = $label;
        $v = $trendMap[$key] ?? 0;
        $values[] = $v == (int) $v ? (int) $v : $v;
        foreach ($seriesTrendMap as $series => $dayValues) {
            if (!isset($seriesTrendOut[$series])) {
                $seriesTrendOut[$series] = [
                    'labels' => [],
                    'trips' => [],
                ];
            }
            $seriesValue = $dayValues[$key] ?? 0;
            $seriesTrendOut[$series]['labels'][] = $label;
            $seriesTrendOut[$series]['trips'][] = $seriesValue == (int) $seriesValue ? (int) $seriesValue : $seriesValue;
        }
        $cursor = $cursor->modify("+1 day");
    }

    // Format aggregates as int when possible
    $cleanFloat = static function ($v) {
        return $v == (int) $v ? (int) $v : (float) $v;
    };
    $byEntryTypeOut = [];
    foreach ($byEntryType as $k => $v) {
        $byEntryTypeOut[$k] = $cleanFloat($v);
    }
    $bySystemIdOut = [];
    foreach ($bySystemId as $k => $v) {
        $bySystemIdOut[$k] = $cleanFloat($v);
    }

    echo json_encode([
        "success" => true,
        "period" => $period,
        "date_from" => $dateFrom,
        "date_to" => $dateTo,
        "rows" => $rowsOut,
        "totals" => [
            "trips" => $cleanFloat($total),
            "categories" => count($rules),
            "active_categories" => $activeCategories,
            "top_series" => $topSeries['series'],
            "top_trips" => $topSeries['trips'],
        ],
        "by_entry_type" => $byEntryTypeOut,
        "by_system_id" => $bySystemIdOut,
        "trend" => [
            "labels" => $labels,
            "trips" => $values,
        ],
        "series_trend" => $seriesTrendOut,
        "generated_at" => date("c"),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to build weekly report: " . $e->getMessage(),
    ]);
}
exit();
