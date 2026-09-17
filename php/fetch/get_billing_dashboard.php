<?php
include "../config/config.php";
require_once __DIR__ . "/../helpers/dashboard_matrix_revenue.php";

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Manila');

$today = date('Y-m-d');
$monthStart = date('Y-m-01');

// ---- Selected date range (drives the range cards + revenue trend) ----
// Defaults to month-to-date so the dashboard matches its previous behaviour
// when no range is supplied. Falls back to defaults on any invalid input.
$isValidDate = static function ($value): bool {
    return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false;
};
$rangeFrom = $isValidDate($_GET['from'] ?? null) ? $_GET['from'] : $monthStart;
$rangeTo = $isValidDate($_GET['to'] ?? null) ? $_GET['to'] : $today;
if ($rangeFrom > $rangeTo) {
    [$rangeFrom, $rangeTo] = [$rangeTo, $rangeFrom];
}

$response = [
    'success' => true,
    'generated_at' => date('Y-m-d H:i:s'),
    'range' => ['from' => $rangeFrom, 'to' => $rangeTo],
];

try {
    // ---- Billing rows / SKUs for a date range (mirrors get_billing_summary) ----
    $summarySql = "SELECT
            COUNT(*) AS total_rows,
            COUNT(DISTINCT COALESCE(NULLIF(TRIM(billing_sku), ''), CONCAT('ROW-', entry_id::text))) AS total_skus
        FROM operations
        WHERE created_date::date BETWEEN ? AND ?
          AND TRIM(COALESCE(billing_sku, '')) <> ''";

    $summaryStmt = $conn->prepare($summarySql);

    $summaryStmt->execute([$today, $today]);
    $todaySummary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $summaryStmt->execute([$rangeFrom, $rangeTo]);
    $monthSummary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // ---- Matrix-priced revenue (same fuel rate matrix as the billing statements) ----
    // Each RV trip is priced through resolve_lane_rate() per configured customer;
    // trips with no matching lane/matrix are unpriced (contribute nothing). This
    // drives the finance cards, the revenue trend, and the per-customer breakdown.
    $todayMatrix = dashboard_matrix_revenue($conn, $today, $today);
    $rangeMatrix = dashboard_matrix_revenue($conn, $rangeFrom, $rangeTo);

    // ---- Revenue trend over the selected range (matrix revenue per trip date) ----
    $trendByDay = $rangeMatrix['by_day'];
    $trendLabels = [];
    $trendValues = [];
    $cursor = new DateTime($rangeFrom);
    $end = new DateTime($rangeTo);
    while ($cursor <= $end) {
        $day = $cursor->format('Y-m-d');
        $trendLabels[] = $cursor->format('M j');
        $trendValues[] = round($trendByDay[$day] ?? 0, 2);
        $cursor->modify('+1 day');
    }

    // ---- Master data counts + active rate codes (single round-trip) ----
    $counts = $conn->query(
        "SELECT
            (SELECT COUNT(*) FROM fuel_price)       AS fuel_price,
            (SELECT COUNT(*) FROM service_material) AS service_material,
            (SELECT COUNT(*) FROM profit_center)    AS profit_center,
            (SELECT COUNT(*) FROM rates)            AS rates,
            (SELECT COUNT(*) FROM rates WHERE TRIM(COALESCE(rate_code, '')) <> '') AS rate_codes"
    )->fetch(PDO::FETCH_ASSOC) ?: [];
    $masterCounts = [
        'fuel_price' => (int) ($counts['fuel_price'] ?? 0),
        'service_material' => (int) ($counts['service_material'] ?? 0),
        'profit_center' => (int) ($counts['profit_center'] ?? 0),
        'rates' => (int) ($counts['rates'] ?? 0),
    ];
    $rateCodeCount = (int) ($counts['rate_codes'] ?? 0);

    // ---- Finance figures (matrix-priced, see dashboard_matrix_revenue) ----
    $todayRevenue = $todayMatrix['revenue'];
    $monthRevenue = $rangeMatrix['revenue'];
    $monthTrips = $rangeMatrix['trips'];
    $avgPerTrip = $monthTrips > 0 ? $monthRevenue / $monthTrips : 0.0;

    // ---- Equipment (genset) revenue: genset hours x genset hourly rate ----
    // Hours come from the hour-meter readings on each RV record; the rate is
    // the "Genset Charges" PER HOUR price set in Service Materials master data.
    $gensetHourlyRate = (float) ($conn->query(
        "SELECT MAX(NULLIF(rate, '')::numeric)
         FROM service_material
         WHERE material_description ILIKE 'genset charges' AND rate_type ILIKE '%hour%'"
    )->fetchColumn() ?: 0);

    // Per-record genset hours = meter end - start, but the hour-meter data has
    // many bad rows (full-odometer baselines, typos), so only count diffs within
    // a sane window (0..120h ≈ up to 5 days); anything outside is treated as 0.
    $gensetHours = function (string $dateFrom, string $dateTo) use ($conn): float {
        $stmt = $conn->prepare(
            "SELECT COALESCE(SUM(
                 CASE WHEN (genset_hr_meter_end - genset_hr_meter_start) BETWEEN 0 AND 120
                      THEN (genset_hr_meter_end - genset_hr_meter_start) ELSE 0 END
             ), 0)
             FROM operations
             WHERE entry_type = 'RV ENTRY'
               AND created_date::date BETWEEN ? AND ?
               AND genset_hr_meter_start IS NOT NULL AND genset_hr_meter_end IS NOT NULL"
        );
        $stmt->execute([$dateFrom, $dateTo]);
        return (float) $stmt->fetchColumn();
    };

    $todayGensetHours = $gensetHours($today, $today);
    $monthGensetHours = $gensetHours($rangeFrom, $rangeTo);
    $todayEquipment = $todayGensetHours * $gensetHourlyRate;
    $monthEquipment = $monthGensetHours * $gensetHourlyRate;

    // ---- Latest fuel price entry ----
    $latestFuel = $conn->query(
        'SELECT price_date, petron, shell, caltex, average
         FROM fuel_price
         ORDER BY price_date DESC NULLS LAST, id DESC
         LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC) ?: null;

    // ---- Hauling revenue by customer for the selected range ----
    // The matrix helper already returns the priced trips grouped per customer;
    // 'rate' here is the effective average rate (revenue / trips), since the
    // per-trip rate varies with the fuel band on each trip date.
    $revenueBreakdown = [];
    foreach ($rangeMatrix['by_customer'] as $row) {
        $revenueBreakdown[] = [
            'lane' => $row['label'],
            'segment' => $row['matrix_key'],
            'rate' => $row['avg_rate'],
            'trips' => $row['trips'],
            'revenue' => $row['revenue'],
        ];
    }

    $response['today'] = [
        'rows' => (int) ($todaySummary['total_rows'] ?? 0),
        'skus' => (int) ($todaySummary['total_skus'] ?? 0),
    ];
    $response['month'] = [
        'rows' => (int) ($monthSummary['total_rows'] ?? 0),
        'skus' => (int) ($monthSummary['total_skus'] ?? 0),
    ];
    $response['trend'] = [
        'labels' => $trendLabels,
        'values' => $trendValues,
    ];
    $response['master_counts'] = $masterCounts;
    $response['rate_codes'] = $rateCodeCount;
    $response['finance'] = [
        'today_revenue' => (float) $todayRevenue,
        'month_revenue' => $monthRevenue,
        'month_trips' => (int) round($monthTrips),
        'avg_per_trip' => $avgPerTrip,
        'equipment_today' => $todayEquipment,
        'equipment_month' => $monthEquipment,
        'equipment_hours_month' => round($monthGensetHours, 1),
        'genset_hourly_rate' => $gensetHourlyRate,
    ];
    $response['latest_fuel'] = $latestFuel;
    $response['revenue_breakdown'] = $revenueBreakdown;

    // ---- Billing performance: billed vs unbilled trips/revenue for the range ----
    $perf = dashboard_billing_performance($conn, $rangeFrom, $rangeTo);

    // Per-day billed vs unbilled series aligned to the trend labels.
    $perfByDay = $perf['by_day'];
    $billedSeries = [];
    $unbilledSeries = [];
    $cursor = new DateTime($rangeFrom);
    $end = new DateTime($rangeTo);
    while ($cursor <= $end) {
        $day = $cursor->format('Y-m-d');
        $billedSeries[] = round($perfByDay[$day]['billed'] ?? 0, 2);
        $unbilledSeries[] = round($perfByDay[$day]['unbilled'] ?? 0, 2);
        $cursor->modify('+1 day');
    }

    // Invoices generated within the range (by generation timestamp).
    $invStmt = $conn->prepare(
        "SELECT COUNT(*) AS invoices, COALESCE(SUM(line_count), 0) AS lines
         FROM billing_invoices
         WHERE status <> 'deleted' AND requested_at::date BETWEEN ? AND ?"
    );
    $invStmt->execute([$rangeFrom, $rangeTo]);
    $invRow = $invStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $response['performance'] = [
        'total_trips' => $perf['total_trips'],
        'billed_trips' => $perf['billed_trips'],
        'unbilled_trips' => $perf['unbilled_trips'],
        'billed_revenue' => $perf['billed_revenue'],
        'unbilled_revenue' => $perf['unbilled_revenue'],
        'coverage_pct' => $perf['coverage_pct'],
        'by_customer' => $perf['by_customer'],
        'by_day' => [
            'labels' => $trendLabels,
            'billed' => $billedSeries,
            'unbilled' => $unbilledSeries,
        ],
        'invoices_generated' => (int) ($invRow['invoices'] ?? 0),
        'invoice_lines' => (int) ($invRow['lines'] ?? 0),
    ];
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load billing dashboard: ' . $e->getMessage(),
    ]);
    exit;
}

echo json_encode($response);
exit;
?>
