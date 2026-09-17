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

$entryType = trim((string) ($_GET["entry_type"] ?? "RV ENTRY"));
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
        // Operational week runs Friday -> Thursday (e.g. May 1 Fri to May 7 Thu).
        // Anchor on the most recent Friday on or before today.
        $dayOfWeek = (int) $today->format("N"); // 1=Mon ... 5=Fri ... 7=Sun
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

$params = [$dateFrom, $dateTo];
$entryTypeSql = "";
if ($entryType !== "" && strtoupper($entryType) !== "ALL") {
    $entryTypeSql = " AND entry_type = ?";
    $params[] = $entryType;
}

function analytics_query_all(PDO $conn, string $sql, array $params): array
{
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function analytics_to_number($value): float
{
    if ($value === null) {
        return 0.0;
    }
    $cleaned = preg_replace('/[^0-9.\-]/', "", (string) $value);
    if ($cleaned === "" || $cleaned === "-" || $cleaned === ".") {
        return 0.0;
    }
    return (float) $cleaned;
}

/*
 * Effective bucketing date rule (per business request):
 *   - Use loaded_van_loading_start_date / loaded_van_loading_finish_date.
 *   - If the loading crossed midnight (start date != finish date):
 *       * finish time < 08:00 AM => attribute to Start Loading Date
 *       * finish time >= 08:00 AM => attribute to Finished Loading Date
 *   - If both dates are equal (no midnight crossing) => use Start Loading Date.
 *   - Fallbacks: whichever loading date is present. created_date is intentionally NOT used.
 *   - Rows with no loading dates are excluded from the analytics buckets.
 */
$effectiveDateSql = "
    CASE
        WHEN NULLIF(loaded_van_loading_finish_date::text, '') IS NOT NULL
         AND NULLIF(loaded_van_loading_start_date::text, '') IS NOT NULL
         AND loaded_van_loading_finish_date::date <> loaded_van_loading_start_date::date
         AND NULLIF(loaded_van_loading_finish_time::text, '') IS NOT NULL
         AND loaded_van_loading_finish_time::time < TIME '08:00:00'
            THEN loaded_van_loading_start_date::date
        WHEN NULLIF(loaded_van_loading_finish_date::text, '') IS NOT NULL
            THEN loaded_van_loading_finish_date::date
        WHEN NULLIF(loaded_van_loading_start_date::text, '') IS NOT NULL
            THEN loaded_van_loading_start_date::date
        ELSE NULL
    END
";

$shipperRows = analytics_query_all(
    $conn,
    "
    WITH ops AS (
        SELECT
            COALESCE(NULLIF(TRIM(shipper), ''), 'Unspecified') AS shipper_label,
            entry_type,
            total_trips,
            load_description,
            kms,
            {$effectiveDateSql} AS effective_date
        FROM operations
    )
    SELECT
        shipper_label,
        entry_type,
        total_trips,
        load_description,
        kms,
        effective_date AS day
    FROM ops
    WHERE effective_date IS NOT NULL
      AND effective_date BETWEEN ? AND ?" . $entryTypeSql . "
    ORDER BY effective_date ASC
    ",
    $params,
);

$byShipper = [];
$byDay = [];
$byShipperDay = [];
$totals = [
    "trips" => 0.0,
    "loads" => 0.0,
    "kms" => 0.0,
    "entries" => 0,
];

$canonicalShippers = [
    "Dole",
    "Sumifru",
    "ABC Cateel",
    "ABC PANTUKAN",
    "ABC Donmar",
    "ABC Lupon",
    "Farmind",
    "Good Farmer",
    "TDC - CLASS B",
];

$shipperSeriesMap = [
    "Dole" => "TDC-Dole",
    "Sumifru" => "TDC-Sumifru",
    "ABC Cateel" => "ABC Cateel",
    "ABC PANTUKAN" => "ABC Pantukan",
    "ABC Donmar" => "ABC Donmar",
    "ABC Lupon" => "ABC Lupon",
    "Farmind" => "TDC Farmind",
    "Good Farmer" => "TDC Good Farmer",
    "TDC - CLASS B" => "DICT B/bulk - Class B Bananas",
];

foreach ($canonicalShippers as $name) {
    $byShipper[$name] = [
        "shipper" => $name,
        "series" => $shipperSeriesMap[$name] ?? $name,
        "trips" => 0.0,
        "loads" => 0.0,
        "kms" => 0.0,
        "entries" => 0,
    ];
}

foreach ($shipperRows as $row) {
    $rawShipper = trim((string) $row["shipper_label"]);
    $matchKey = null;
    foreach ($canonicalShippers as $candidate) {
        if (strcasecmp($rawShipper, $candidate) === 0) {
            $matchKey = $candidate;
            break;
        }
    }
    if ($matchKey === null) {
        $matchKey = $rawShipper !== "" ? $rawShipper : "Unspecified";
        if (!isset($byShipper[$matchKey])) {
            $byShipper[$matchKey] = [
                "shipper" => $matchKey,
                "series" => $matchKey,
                "trips" => 0.0,
                "loads" => 0.0,
                "kms" => 0.0,
                "entries" => 0,
            ];
        }
    }

    $trips = analytics_to_number($row["total_trips"] ?? 0);
    if ($trips <= 0) {
        $trips = 1.0;
    }
    $loads = analytics_to_number($row["load_description"] ?? 0);
    $kms = analytics_to_number($row["kms"] ?? 0);

    $byShipper[$matchKey]["trips"] += $trips;
    $byShipper[$matchKey]["loads"] += $loads;
    $byShipper[$matchKey]["kms"] += $kms;
    $byShipper[$matchKey]["entries"] += 1;

    $totals["trips"] += $trips;
    $totals["loads"] += $loads;
    $totals["kms"] += $kms;
    $totals["entries"] += 1;

    $day = (string) ($row["day"] ?? "");
    if ($day !== "") {
        if (!isset($byDay[$day])) {
            $byDay[$day] = [
                "day" => $day,
                "trips" => 0.0,
                "loads" => 0.0,
                "kms" => 0.0,
            ];
        }
        $byDay[$day]["trips"] += $trips;
        $byDay[$day]["loads"] += $loads;
        $byDay[$day]["kms"] += $kms;

        if (!isset($byShipperDay[$matchKey])) {
            $byShipperDay[$matchKey] = [];
        }
        if (!isset($byShipperDay[$matchKey][$day])) {
            $byShipperDay[$matchKey][$day] = [
                "trips" => 0.0,
                "loads" => 0.0,
                "kms" => 0.0,
            ];
        }
        $byShipperDay[$matchKey][$day]["trips"] += $trips;
        $byShipperDay[$matchKey][$day]["loads"] += $loads;
        $byShipperDay[$matchKey][$day]["kms"] += $kms;
    }
}

ksort($byDay);

$trendLabels = [];
$trendTrips = [];
$trendLoads = [];
$trendKms = [];
$shipperTrend = [];

$cursor = new DateTimeImmutable($dateFrom, $tz);
$endCursor = new DateTimeImmutable($dateTo, $tz);
while ($cursor <= $endCursor) {
    $key = $cursor->format("Y-m-d");
    $label = $cursor->format("M d");
    $trendLabels[] = $label;
    $trendTrips[] = isset($byDay[$key]) ? round($byDay[$key]["trips"], 2) : 0;
    $trendLoads[] = isset($byDay[$key]) ? round($byDay[$key]["loads"], 2) : 0;
    $trendKms[] = isset($byDay[$key]) ? round($byDay[$key]["kms"], 2) : 0;

    foreach ($byShipperDay as $shipper => $dayValues) {
        if (!isset($shipperTrend[$shipper])) {
            $shipperTrend[$shipper] = [
                "labels" => [],
                "trips" => [],
                "loads" => [],
                "kms" => [],
            ];
        }

        $shipperTrend[$shipper]["labels"][] = $label;
        $shipperTrend[$shipper]["trips"][] = isset($dayValues[$key]) ? round($dayValues[$key]["trips"], 2) : 0;
        $shipperTrend[$shipper]["loads"][] = isset($dayValues[$key]) ? round($dayValues[$key]["loads"], 2) : 0;
        $shipperTrend[$shipper]["kms"][] = isset($dayValues[$key]) ? round($dayValues[$key]["kms"], 2) : 0;
    }

    $cursor = $cursor->modify("+1 day");
}

$breakdown = array_values($byShipper);
usort($breakdown, function ($a, $b) {
    return $b["trips"] <=> $a["trips"];
});

$entryTypeBreakdown = analytics_query_all(
    $conn,
    "
    WITH ops AS (
        SELECT
            COALESCE(NULLIF(TRIM(entry_type), ''), 'UNKNOWN') AS entry_type,
            {$effectiveDateSql} AS effective_date
        FROM operations
    )
    SELECT
        entry_type,
        COUNT(*) AS count
    FROM ops
    WHERE effective_date IS NOT NULL
      AND effective_date BETWEEN ? AND ?
    GROUP BY entry_type
    ORDER BY count DESC
    ",
    [$dateFrom, $dateTo],
);

echo json_encode([
    "success" => true,
    "period" => $period,
    "entry_type" => $entryType,
    "date_from" => $dateFrom,
    "date_to" => $dateTo,
    "totals" => [
        "trips" => round($totals["trips"], 2),
        "loads" => round($totals["loads"], 2),
        "kms" => round($totals["kms"], 2),
        "entries" => (int) $totals["entries"],
        "shippers_active" => count(array_filter($byShipper, fn($s) => $s["entries"] > 0)),
    ],
    "breakdown" => array_map(function ($row) {
        return [
            "shipper" => $row["shipper"],
            "series" => $row["series"],
            "trips" => round($row["trips"], 2),
            "loads" => round($row["loads"], 2),
            "kms" => round($row["kms"], 2),
            "entries" => (int) $row["entries"],
        ];
    }, $breakdown),
    "trend" => [
        "labels" => $trendLabels,
        "trips" => $trendTrips,
        "loads" => $trendLoads,
        "kms" => $trendKms,
    ],
    "shipper_trend" => $shipperTrend,
    "entry_type_breakdown" => array_map(function ($row) {
        return [
            "entry_type" => $row["entry_type"],
            "count" => (int) $row["count"],
        ];
    }, $entryTypeBreakdown),
    "generated_at" => date("c"),
]);
exit();
?>
