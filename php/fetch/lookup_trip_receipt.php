<?php

/**
 * Look up a trip receipt / waybill number in the dispatch database.
 *
 * GET  ?tr=433976
 * Returns JSON:
 *   {
 *     success:   bool,   // false only on a hard error
 *     found:     bool,   // a dispatch row with this trip receipt exists
 *     completed: bool,   // that trip is a completed trip (POD captured)
 *     status:    string, // workflow stage label
 *     record:    { ...fields available for auto-fill... }
 *   }
 *
 * The feature is non-blocking: if the dispatch DB is unreachable we return
 * success:false and the front-end simply lets the user proceed.
 */

require_once __DIR__ . "/../config/dispatch_config.php";

header("Content-Type: application/json; charset=utf-8");

$tr = trim((string) ($_GET["tr"] ?? ""));

if ($tr === "") {
    echo json_encode(["success" => false, "found" => false, "message" => "No trip receipt provided."]);
    exit();
}

$db = dispatch_db();
if (!$db instanceof PDO) {
    echo json_encode(["success" => false, "found" => false, "message" => "Dispatch database unavailable."]);
    exit();
}

try {
    $sql = "SELECT
                d.d_id, d.d_tripreceipt, d.d_ecs, d.workflow_stage, d.trip_completed_at,
                d.d_drivername, d.d_truck, d.d_trailer, d.d_genset, d.costumer,
                d.booking_no, d.d_origin,
                t.trip_from, t.trip_to, t.return_location, t.trip_container, t.trip_status,
                t.trip_departuredatetime, t.trip_arrivaldatetime, t.km_run
            FROM dispatch d
            LEFT JOIN trips t ON t.d_id = d.d_id
            WHERE TRIM(d.d_tripreceipt) = ?
            ORDER BY d.d_id DESC
            LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$tr]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo json_encode(["success" => false, "found" => false, "message" => "Lookup failed."]);
    exit();
}

if (!$row) {
    echo json_encode([
        "success"   => true,
        "found"     => false,
        "completed" => false,
        "status"    => "",
        "record"    => null,
    ]);
    exit();
}

$stage = trim((string) ($row["workflow_stage"] ?? ""));
$completed = $stage === "pod_captured"
    || trim((string) ($row["trip_completed_at"] ?? "")) !== "";

// Split a container number like "MEDU9622800" into alpha prefix + numeric tail.
$container = trim((string) ($row["trip_container"] ?? ""));
$containerAlpha = "";
$containerNumber = "";
if ($container !== "" && preg_match('/^([A-Za-z]+)\s*([0-9].*)$/', $container, $m)) {
    $containerAlpha = strtoupper($m[1]);
    $containerNumber = trim($m[2]);
}

$val = static fn($key) => trim((string) ($row[$key] ?? ""));

/*
 * Auto-fill must never inject a value that the entry-form save would reject.
 * Several form fields are validated against the E-Pantrucks master tables
 * (units, trailer, location) on save, so we connect to the app DB and only
 * keep values that exist there; otherwise we blank them out. Fields that are
 * not master-validated (driver name, deliver from/to, container, ECS) always
 * pass through.
 */
$appConn = null;
require_once __DIR__ . "/../config/config.php"; // sets $conn (app DB)
if (isset($conn) && $conn instanceof PDO) {
    $appConn = $conn;
}

$existsIn = static function (?PDO $db, string $sql, string $value): bool {
    if ($db === null || trim($value) === "") {
        return false;
    }
    try {
        $s = $db->prepare($sql);
        $s->execute([$value]);
        return $s->fetch() !== false;
    } catch (Throwable $e) {
        return false;
    }
};

$validTruck = static fn($v) => $existsIn($appConn, "SELECT 1 FROM units WHERE TRIM(unit_name)=TRIM(?) AND unit_name NOT LIKE 'GS%' LIMIT 1", $v) ? $v : "";
$validGenset = static fn($v) => $existsIn($appConn, "SELECT 1 FROM units WHERE TRIM(unit_name)=TRIM(?) AND unit_name LIKE 'GS%' LIMIT 1", $v) ? $v : "";
$validTrailer = static fn($v) => $existsIn($appConn, "SELECT 1 FROM trailer WHERE TRIM(trailer_name)=TRIM(?) LIMIT 1", $v) ? $v : "";
$validLocation = static fn($v) => $existsIn($appConn, "SELECT 1 FROM location WHERE TRIM(location_name)=TRIM(?) LIMIT 1", $v) ? $v : "";

$record = [
    "trip_receipt"     => $val("d_tripreceipt"),
    "driver"           => $val("d_drivername"),
    "truck"            => $validTruck($val("d_truck")),
    "trailer"          => $validTrailer($val("d_trailer")),
    "genset"           => $validGenset($val("d_genset")),
    "customer"         => $validLocation($val("costumer")),
    "ecs"              => $val("d_ecs"),
    "origin"           => $val("d_origin"),
    "ph"               => $validLocation($val("trip_to")),
    "deliver_from"     => $val("trip_from") !== "" ? $val("trip_from") : $val("d_origin"),
    "deliver_to"       => $val("trip_to"),
    "return_location"  => $val("return_location"),
    "container"        => $container,
    "container_alpha"  => $containerAlpha,
    "container_number" => $containerNumber,
    "booking_no"       => $val("booking_no"),
    "departure"        => $val("trip_departuredatetime"),
    "arrival"          => $val("trip_arrivaldatetime"),
    "km"               => $val("km_run"),
    "trip_status"      => $val("trip_status"),
];

echo json_encode([
    "success"   => true,
    "found"     => true,
    "completed" => $completed,
    "status"    => $stage !== "" ? $stage : ($val("trip_status") ?: "unknown"),
    "record"    => $record,
]);
exit();
