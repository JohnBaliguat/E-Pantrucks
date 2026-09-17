<?php
include "../config/config.php";
require_once __DIR__ . "/../helpers/operations_status.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

function dashboard_query_assoc(PDO $conn, string $sql, array $params = []): array
{
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function dashboard_query_all(PDO $conn, string $sql, array $params = []): array
{
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$currentUserType = ucfirst(strtolower((string) ($_SESSION["user_type"] ?? "")));
$currentUserIdNumber = trim((string) ($_SESSION["user_idNumber"] ?? ""));
$operationsWhere = "";
$whereParams = [];

if ($currentUserType === "User" && $currentUserIdNumber !== "") {
    $operationsWhere = " WHERE created_by = ?";
    $whereParams = [$currentUserIdNumber];
}

$stats = dashboard_query_assoc(
    $conn,
    "
    SELECT
        COUNT(*) AS total_entries,
        SUM(CASE WHEN created_date::date = CURRENT_DATE THEN 1 ELSE 0 END) AS today_entries,
        SUM(CASE WHEN created_date::date = CURRENT_DATE AND entry_type = 'RV ENTRY' THEN 1 ELSE 0 END) AS today_rv,
        SUM(CASE WHEN created_date::date = CURRENT_DATE AND entry_type = 'OTHERS ENTRY' THEN 1 ELSE 0 END) AS today_others,
        SUM(CASE WHEN created_date::date = CURRENT_DATE AND entry_type = 'DPC_KDs & OPM ENTRY' THEN 1 ELSE 0 END) AS today_dpc,
        SUM(CASE WHEN created_date::date = CURRENT_DATE AND entry_type = 'DRY VAN ENTRY' THEN 1 ELSE 0 END) AS today_dry_van,
        SUM(CASE WHEN created_date::date = CURRENT_DATE AND entry_type = 'CARGO TRUCK ENTRY' THEN 1 ELSE 0 END) AS today_cargo
    FROM operations
    {$operationsWhere}
",
    $whereParams,
);

$userStats = dashboard_query_assoc(
    $conn,
    "
    SELECT
        COUNT(*) AS total_users,
        SUM(CASE WHEN \"user_accountStat\" = 'Active' THEN 1 ELSE 0 END) AS active_users
    FROM \"user\"
",
);

$chartWhere = $operationsWhere === ""
    ? "WHERE created_date IS NOT NULL"
    : $operationsWhere . " AND created_date IS NOT NULL";

$chartRows = dashboard_query_all(
    $conn,
    "
    SELECT
        created_date::date AS created_day,
        COUNT(*) AS total_operations
    FROM operations
    {$chartWhere}
    GROUP BY created_date::date
    ORDER BY created_day ASC
",
    $whereParams,
);

$chartLabels = [];
$chartValues = [];
foreach ($chartRows as $row) {
    if (empty($row["created_day"])) {
        continue;
    }

    $chartLabels[] = date("M d, Y", strtotime($row["created_day"]));
    $chartValues[] = (int) $row["total_operations"];
}

$activityRows = dashboard_query_all(
    $conn,
    "
    SELECT
        entry_id,
        entry_type,
        waybill,
        created_by,
        modified_by,
        created_date,
        modified_date
    FROM operations
    {$operationsWhere}
    ORDER BY GREATEST(
        COALESCE(modified_date, '1970-01-01 00:00:00'::timestamp),
        COALESCE(created_date, '1970-01-01 00:00:00'::timestamp)
    ) DESC
    LIMIT 6
",
    $whereParams,
);

$recentActivities = [];
foreach ($activityRows as $row) {
    $isUpdated =
        !empty($row["modified_date"]) &&
        $row["modified_date"] !== $row["created_date"];
    $recentActivities[] = [
        "type" => $isUpdated ? "updated" : "created",
        "title" => $isUpdated ? "Entry updated" : "New entry added",
        "entry_id" => (int) $row["entry_id"],
        "entry_type" => $row["entry_type"],
        "waybill" => $row["waybill"],
        "actor" => $isUpdated
            ? ($row["modified_by"] ?:
            "System")
            : ($row["created_by"] ?:
            "System"),
        "timestamp" => $isUpdated
            ? $row["modified_date"]
            : $row["created_date"],
    ];
}

$statusColumns = operations_status_select_columns_sql();
$latestRows = dashboard_query_all(
    $conn,
    "
    SELECT
        {$statusColumns},
        created_date,
        modified_date,
        remarks
    FROM operations
    {$operationsWhere}
    ORDER BY entry_id DESC
    LIMIT 8
",
    $whereParams,
);

$routeByType = operations_route_by_type();
$latestEntries = [];
$completeCount = 0;
$pendingCount = 0;

foreach ($latestRows as $row) {
    $normalizedType = operations_normalize_entry_type($row["entry_type"] ?? "");
    $missingFields = operations_missing_fields($row);
    $isComplete = count($missingFields) === 0;

    if ($isComplete) {
        $completeCount++;
    } else {
        $pendingCount++;
    }

    $latestEntries[] = [
        "entry_id" => (int) $row["entry_id"],
        "entry_type" => $row["entry_type"],
        "segment" => $row["segment"],
        "activity" => $row["activity"],
        "waybill" => $row["waybill"],
        "status" => $isComplete ? "Complete" : "Pending",
        "missing_count" => count($missingFields),
        "created_date" => $row["created_date"],
        "route" => $routeByType[$normalizedType] ?? "entry",
    ];
}

$todayWhere = $operationsWhere === ""
    ? "WHERE created_date::date = CURRENT_DATE"
    : $operationsWhere . " AND created_date::date = CURRENT_DATE";

$todayRows = dashboard_query_all(
    $conn,
    "
    SELECT
        {$statusColumns}
    FROM operations
    {$todayWhere}
",
    $whereParams,
);

$todayComplete = 0;
$todayPending = 0;
foreach ($todayRows as $row) {
    if (count(operations_missing_fields($row)) === 0) {
        $todayComplete++;
    } else {
        $todayPending++;
    }
}

echo json_encode([
    "success" => true,
    "stats" => [
        "total_entries" => (int) ($stats["total_entries"] ?? 0),
        "today_entries" => (int) ($stats["today_entries"] ?? 0),
        "today_complete" => $todayComplete,
        "today_pending" => $todayPending,
        "active_users" => (int) ($userStats["active_users"] ?? 0),
        "by_type" => [
            "rv" => (int) ($stats["today_rv"] ?? 0),
            "others" => (int) ($stats["today_others"] ?? 0),
            "dpc_kdi" => (int) ($stats["today_dpc"] ?? 0),
            "dry_van" => (int) ($stats["today_dry_van"] ?? 0),
            "cargo_truck" => (int) ($stats["today_cargo"] ?? 0),
        ],
    ],
    "chart" => [
        "labels" => $chartLabels,
        "values" => $chartValues,
    ],
    "recent_activities" => $recentActivities,
    "latest_entries" => $latestEntries,
    "generated_at" => date("c"),
]);
exit();
?>
