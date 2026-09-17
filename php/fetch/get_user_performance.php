<?php
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_data_update_flags_schema.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"]) || ucfirst(strtolower((string) ($_SESSION["user_type"] ?? ""))) !== "Admin") {
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized",
    ]);
    exit();
}

// The "For Update" queue (data_update_flags) is where billing tags an encoder's
// record as needing correction. Each flag on a record the user encoded counts as
// a mistake for that encoder. Ensure the table exists before we reference it.
ensure_data_update_flags_schema($conn);

// Optional date-range scope. Both entries and mistakes are counted against the
// record's created_date, so the numbers read as "in this period, this encoder
// made X entries and Y of them were flagged For Update". Values are strictly
// validated to YYYY-MM-DD, so they can be inlined into the SQL safely.
$dateFrom = trim((string) ($_GET["date_from"] ?? ""));
$dateTo = trim((string) ($_GET["date_to"] ?? ""));
$validFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ? $dateFrom : "";
$validTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ? $dateTo : "";

// Guard against a reversed range.
if ($validFrom !== "" && $validTo !== "" && $validFrom > $validTo) {
    [$validFrom, $validTo] = [$validTo, $validFrom];
}

$entryDateClause = "";
$flagDateClause = "";
if ($validFrom !== "") {
    $entryDateClause .= " AND o.created_date::date >= '$validFrom'";
    $flagDateClause .= " AND fo.created_date::date >= '$validFrom'";
}
if ($validTo !== "") {
    $entryDateClause .= " AND o.created_date::date <= '$validTo'";
    $flagDateClause .= " AND fo.created_date::date <= '$validTo'";
}

$sql = "
    SELECT
        u.user_id,
        u.\"user_idNumber\",
        u.user_name,
        u.user_fname,
        u.user_lname,
        u.user_type,
        u.\"user_accountStat\",
        COUNT(o.entry_id) AS total_entries,
        SUM(CASE WHEN o.created_date::date = CURRENT_DATE THEN 1 ELSE 0 END) AS today_entries,
        SUM(CASE WHEN o.entry_type = 'RV ENTRY' THEN 1 ELSE 0 END) AS rv_entries,
        SUM(CASE WHEN o.entry_type = 'OTHERS ENTRY' THEN 1 ELSE 0 END) AS others_entries,
        SUM(CASE WHEN o.entry_type = 'DPC_KDs & OPM ENTRY' THEN 1 ELSE 0 END) AS dpc_entries,
        SUM(CASE WHEN o.entry_type = 'CARGO TRUCK ENTRY' THEN 1 ELSE 0 END) AS cargo_entries,
        SUM(CASE WHEN o.entry_type = 'DRY VAN ENTRY' THEN 1 ELSE 0 END) AS dry_van_entries,
        MAX(o.created_date) AS last_entry_date,
        ROUND(COUNT(o.entry_id) / NULLIF(COUNT(DISTINCT o.created_date::date), 0), 2) AS avg_entries_per_day,
        (
            SELECT COUNT(*)
            FROM data_update_flags f
            JOIN operations fo ON fo.entry_id = f.entry_id
            WHERE NULLIF(TRIM(fo.created_by), '') = u.\"user_idNumber\"::text" . $flagDateClause . "
        ) AS mistakes_total,
        (
            SELECT COUNT(*)
            FROM data_update_flags f
            JOIN operations fo ON fo.entry_id = f.entry_id
            WHERE NULLIF(TRIM(fo.created_by), '') = u.\"user_idNumber\"::text
              AND f.status = 'open'" . $flagDateClause . "
        ) AS mistakes_open
    FROM \"user\" u
    LEFT JOIN operations o
        ON NULLIF(TRIM(o.created_by), '') = u.\"user_idNumber\"::text" . $entryDateClause . "
    WHERE u.user_type = 'User'
    GROUP BY
        u.user_id,
        u.\"user_idNumber\",
        u.user_name,
        u.user_fname,
        u.user_lname,
        u.user_type,
        u.\"user_accountStat\"
    ORDER BY total_entries DESC, u.user_lname ASC, u.user_fname ASC
";

$stmt = $conn->query($sql);
$rows = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $fullName = trim(($row["user_fname"] ?? "") . " " . ($row["user_lname"] ?? ""));
    $rows[] = [
        "user_id" => (int) ($row["user_id"] ?? 0),
        "user_idNumber" => $row["user_idNumber"] ?? "",
        "user_name" => $row["user_name"] ?? "",
        "full_name" => $fullName,
        "user_type" => $row["user_type"] ?? "",
        "user_accountStat" => $row["user_accountStat"] ?? "",
        "total_entries" => (int) ($row["total_entries"] ?? 0),
        "today_entries" => (int) ($row["today_entries"] ?? 0),
        "rv_entries" => (int) ($row["rv_entries"] ?? 0),
        "others_entries" => (int) ($row["others_entries"] ?? 0),
        "dpc_entries" => (int) ($row["dpc_entries"] ?? 0),
        "cargo_entries" => (int) ($row["cargo_entries"] ?? 0),
        "dry_van_entries" => (int) ($row["dry_van_entries"] ?? 0),
        "last_entry_date" => $row["last_entry_date"] ?? null,
        "avg_entries_per_day" => (float) ($row["avg_entries_per_day"] ?? 0),
        "mistakes_total" => (int) ($row["mistakes_total"] ?? 0),
        "mistakes_open" => (int) ($row["mistakes_open"] ?? 0),
    ];
}

$usersWithEntries = array_values(array_filter($rows, function ($row) {
    return ($row["total_entries"] ?? 0) > 0;
}));

$topPerformer = $usersWithEntries[0] ?? null;
$totalEntries = array_sum(array_map(function ($row) {
    return (int) ($row["total_entries"] ?? 0);
}, $rows));
$todayEntries = array_sum(array_map(function ($row) {
    return (int) ($row["today_entries"] ?? 0);
}, $rows));
$totalMistakes = array_sum(array_map(function ($row) {
    return (int) ($row["mistakes_total"] ?? 0);
}, $rows));
$openMistakes = array_sum(array_map(function ($row) {
    return (int) ($row["mistakes_open"] ?? 0);
}, $rows));

$chartRows = array_slice($usersWithEntries, 0, 10);
$chartLabels = array_map(function ($row) {
    return $row["full_name"] ?: $row["user_name"] ?: $row["user_idNumber"];
}, $chartRows);
$chartValues = array_map(function ($row) {
    return (int) ($row["total_entries"] ?? 0);
}, $chartRows);

echo json_encode([
    "success" => true,
    "summary" => [
        "total_users" => count($rows),
        "users_with_entries" => count($usersWithEntries),
        "total_entries" => $totalEntries,
        "today_entries" => $todayEntries,
        "total_mistakes" => $totalMistakes,
        "open_mistakes" => $openMistakes,
        "top_performer" => $topPerformer,
    ],
    "chart" => [
        "labels" => $chartLabels,
        "values" => $chartValues,
    ],
    "rows" => $rows,
    "range" => [
        "date_from" => $validFrom,
        "date_to" => $validTo,
    ],
    "generated_at" => date("c"),
]);
exit();
?>
