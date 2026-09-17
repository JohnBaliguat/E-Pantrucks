<?php
include "../config/config.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");

/**
 * Driver Trips report: one row per trip (driver, segment, trip receipt, route,
 * km) plus the KM total for the range.
 *
 * Only the primary `driver` is credited per trip. Unlike the Driver Performance
 * report -- which splits an RV/Dry Van trip between two drivers to divide the
 * piece rate -- a trip's kilometres are driven once, so splitting here would
 * double-count the total.
 */

$dateFromInput = trim((string) ($_GET["date_from"] ?? ""));
$dateToInput = trim((string) ($_GET["date_to"] ?? ""));
$driverFilter = trim((string) ($_GET["driver"] ?? ""));
$segmentFilter = trim((string) ($_GET["segment"] ?? ""));
$entryTypeFilter = trim((string) ($_GET["entry_type"] ?? ""));

$tz = new DateTimeZone("Asia/Manila");
$today = new DateTimeImmutable("now", $tz);

if (
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFromInput) &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateToInput) &&
    $dateFromInput <= $dateToInput
) {
    $dateFrom = $dateFromInput;
    $dateTo = $dateToInput;
} else {
    $dateFrom = $today->modify("first day of this month")->format("Y-m-d");
    $dateTo = $today->modify("last day of this month")->format("Y-m-d");
}

/** Parse the varchar `kms` column into a number. */
function dt_num($value): float
{
    if ($value === null) {
        return 0.0;
    }
    $s = preg_replace('/[^0-9.\-]/', "", (string) $value);
    if ($s === "" || $s === "-" || $s === ".") {
        return 0.0;
    }
    return (float) $s;
}

function dt_text($value): string
{
    return trim((string) ($value ?? ""));
}

/**
 * Compose the trip's route as "pull-out -> PH -> delivered to". Each entry type
 * keeps its origin in a different column -- these mirror the conventions already
 * used by build_records_xlsx(). Empty legs are dropped, so a trip without a PH
 * stop simply reads "origin -> destination".
 */
function dt_route(array $row): string
{
    $entryType = strtoupper(dt_text($row["entry_type"] ?? ""));
    $isDryVan = $entryType === "DRY VAN ENTRY";
    $isOthers = $entryType === "OTHERS ENTRY";
    $isDpc = $entryType === "DPC_KDS & OPM ENTRY";
    $isCargoTruck = $entryType === "CARGO TRUCK ENTRY";

    if ($isDryVan) {
        $from = $row["pullout_location"] ?? "";
    } elseif ($isOthers || $isCargoTruck) {
        $from = $row["deliver_from"] ?? "";
    } elseif ($isDpc) {
        $from = "TPD";
    } else {
        $from = $row["empty_pullout_location"] ?? "";
    }

    // Others/Cargo Truck have no PH leg: their `operations_ph` holds a service
    // category ("OPM Hauling"), not a location, so it must not be routed through.
    $via = ($isOthers || $isCargoTruck) ? "" : ($row["ph"] ?? "");

    $to = $row["delivered_to"] ?? "";

    $legs = array_filter(
        array_map("dt_text", [$from, $via, $to]),
        static fn($leg) => $leg !== ""
    );

    return implode(" \u{2192} ", $legs);
}

/** Effective trip date: same fallback chain as the Driver Performance report. */
function dt_effective_date(array $row): ?string
{
    $candidates = [
        $row["waybill_date"] ?? null,
        $row["cargo_date"] ?? null,
        $row["dpc_date"] ?? null,
        $row["others_date"] ?? null,
        $row["date_hauled"] ?? null,
        $row["pullout_location_arrival_date"] ?? null,
        $row["created_date"] ?? null,
    ];
    foreach ($candidates as $candidate) {
        $s = dt_text($candidate);
        if ($s === "" || str_starts_with($s, "0000-00-00")) {
            continue;
        }
        return substr($s, 0, 10);
    }
    return null;
}

try {
    $effectiveDateSql = "COALESCE(
        NULLIF(waybill_date::text, '')::date,
        NULLIF(cargo_date::text, '')::date,
        NULLIF(dpc_date::text, '')::date,
        NULLIF(others_date::text, '')::date,
        NULLIF(date_hauled::text, '')::date,
        NULLIF(pullout_location_arrival_date::text, '')::date,
        created_date::date
    )";

    $where = [$effectiveDateSql . " BETWEEN ? AND ?"];
    $params = [$dateFrom, $dateTo];

    // Every row in this report belongs to a driver.
    $where[] = "COALESCE(driver, '') <> ''";

    if ($driverFilter !== "") {
        // Matched on name only. operations."driver_idNumber" is not trustworthy
        // here -- the same id appears against several different driver names, so
        // matching on it would credit one driver with another's trips.
        $where[] = "driver ILIKE ?";
        $params[] = "%" . $driverFilter . "%";
    }
    if ($segmentFilter !== "") {
        $where[] = "COALESCE(segment, '') = ?";
        $params[] = $segmentFilter;
    }
    if ($entryTypeFilter !== "") {
        $where[] = "UPPER(entry_type) = UPPER(?)";
        $params[] = $entryTypeFilter;
    }

    $sql = "
        SELECT
            entry_id,
            entry_type,
            driver,
            \"driver_idNumber\",
            segment,
            waybill,
            kms,
            empty_pullout_location,
            pullout_location,
            deliver_from,
            ph,
            delivered_to,
            waybill_date, cargo_date, dpc_date, others_date,
            date_hauled, pullout_location_arrival_date, created_date
        FROM operations
        WHERE " . implode("\n          AND ", $where) . "
        ORDER BY " . $effectiveDateSql . " DESC, driver ASC, entry_id DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $trips = [];
    $totalKm = 0.0;
    $tripsWithoutKm = 0;
    $drivers = [];

    foreach ($rows as $row) {
        $km = dt_num($row["kms"] ?? "");
        $hasKm = dt_text($row["kms"] ?? "") !== "";
        if (!$hasKm) {
            $tripsWithoutKm++;
        }

        $driver = dt_text($row["driver"] ?? "");
        $drivers[strtoupper($driver)] = true;
        $totalKm += $km;

        $trips[] = [
            "entry_id" => (int) $row["entry_id"],
            "date" => dt_effective_date($row),
            "entry_type" => dt_text($row["entry_type"] ?? ""),
            "driver" => $driver,
            "driver_id" => dt_text($row["driver_idNumber"] ?? ""),
            "segment" => dt_text($row["segment"] ?? ""),
            "trip_receipt" => dt_text($row["waybill"] ?? ""),
            "route" => dt_route($row),
            "kms" => round($km, 2),
            "has_km" => $hasKm,
        ];
    }

    // Filter options drawn from the whole table so the dropdowns stay stable.
    $segments = $conn->query("
        SELECT DISTINCT segment FROM operations
        WHERE COALESCE(segment, '') <> ''
        ORDER BY segment ASC
    ")->fetchAll(PDO::FETCH_COLUMN);

    // "OTHERS ENTRY" and "Others ENTRY" both exist in the data. The filter matches
    // case-insensitively, so collapse the variants into one option.
    $entryTypes = $conn->query("
        SELECT DISTINCT ON (UPPER(entry_type)) entry_type FROM operations
        WHERE COALESCE(entry_type, '') <> ''
        ORDER BY UPPER(entry_type) ASC, entry_type ASC
    ")->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        "success" => true,
        "date_from" => $dateFrom,
        "date_to" => $dateTo,
        "totals" => [
            "trips" => count($trips),
            "total_km" => round($totalKm, 2),
            "drivers" => count($drivers),
            "trips_without_km" => $tripsWithoutKm,
        ],
        "trips" => $trips,
        "filters" => [
            "segments" => $segments,
            "entry_types" => $entryTypes,
        ],
        "generated_at" => date("c"),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to build driver trips report: " . $e->getMessage(),
    ]);
}
exit();
