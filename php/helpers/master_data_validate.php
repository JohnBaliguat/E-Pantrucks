<?php

/**
 * Master-data checks: trip_rates (segment+activity), drivers, location, trailer, units.
 * Each function returns null if OK, or a user-facing error message.
 */

function master_validate_trip_rates_pair(PDO $conn, string $segment, string $activity): ?string
{
    $segment = trim($segment);
    $activity = trim($activity);
    if ($segment === '' || $activity === '') {
        return null;
    }

    $stmt = $conn->prepare(
        'SELECT 1 FROM trip_rates WHERE LOWER(TRIM(segment)) = LOWER(TRIM(?)) AND LOWER(TRIM(activity)) = LOWER(TRIM(?)) LIMIT 1'
    );
    $stmt->execute([$segment, $activity]);
    $ok = $stmt->fetch() !== false;

    if (!$ok) {
        return 'The selected activity is not defined for this segment in trip rates. Please choose a segment and activity from the list.';
    }

    return null;
}

function master_validate_driver(PDO $conn, string $driverName, string $driverIdNumber = ''): ?string
{
    $driverName = trim($driverName);
    if ($driverName === '') {
        return null;
    }

    $driverIdNumber = trim($driverIdNumber);

    if ($driverIdNumber === '') {
        return null;
    }

    $stmt = $conn->prepare('SELECT 1 FROM drivers WHERE "driver_IdNumber" = ? LIMIT 1');
    $stmt->execute([$driverIdNumber]);
    $ok = $stmt->fetch() !== false;

    if (!$ok) {
        return 'Driver must be selected from the driver master list.';
    }

    return null;
}

function master_validate_location(PDO $conn, string $locationName): ?string
{
    $locationName = trim($locationName);
    if ($locationName === '') {
        return null;
    }

    $stmt = $conn->prepare('SELECT 1 FROM location WHERE TRIM(location_name) = TRIM(?) LIMIT 1');
    $stmt->execute([$locationName]);
    $ok = $stmt->fetch() !== false;

    if (!$ok) {
        return 'Location / PH must be selected from the location master list.';
    }

    return null;
}

function master_validate_trailer(PDO $conn, string $trailerName): ?string
{
    $trailerName = trim($trailerName);
    if ($trailerName === '') {
        return null;
    }

    $stmt = $conn->prepare('SELECT 1 FROM trailer WHERE TRIM(trailer_name) = TRIM(?) LIMIT 1');
    $stmt->execute([$trailerName]);
    $ok = $stmt->fetch() !== false;

    if (!$ok) {
        return 'Trailer must be selected from the trailer master list.';
    }

    return null;
}

function master_validate_unit_truck(PDO $conn, string $unitName): ?string
{
    $unitName = trim($unitName);
    if ($unitName === '') {
        return null;
    }

    $stmt = $conn->prepare("SELECT 1 FROM units WHERE TRIM(unit_name) = TRIM(?) AND unit_name NOT LIKE 'GS%' LIMIT 1");
    $stmt->execute([$unitName]);
    $ok = $stmt->fetch() !== false;

    if (!$ok) {
        return 'Unit (truck / prime mover) must exist in the units master list (non-GS).';
    }

    return null;
}

function master_validate_unit_genset(PDO $conn, string $unitName): ?string
{
    $unitName = trim($unitName);
    if ($unitName === '') {
        return null;
    }

    $stmt = $conn->prepare("SELECT 1 FROM units WHERE TRIM(unit_name) = TRIM(?) AND unit_name LIKE 'GS%' LIMIT 1");
    $stmt->execute([$unitName]);
    $ok = $stmt->fetch() !== false;

    if (!$ok) {
        return 'Genset must be a GS unit from the units master list.';
    }

    return null;
}

function master_validate_rv(
    PDO $conn,
    string $segment,
    string $activity,
    string $driver,
    string $driverId,
    string $ph,
    string $tr,
    string $gs,
    string $primeMover
): ?string {
    if ($e = master_validate_trip_rates_pair($conn, $segment, $activity)) {
        return $e;
    }
    if ($e = master_validate_driver($conn, $driver, $driverId)) {
        return $e;
    }
    if ($e = master_validate_location($conn, $ph)) {
        return $e;
    }
    if ($e = master_validate_trailer($conn, $tr)) {
        return $e;
    }
    if ($e = master_validate_unit_genset($conn, $gs)) {
        return $e;
    }
    if ($e = master_validate_unit_truck($conn, $primeMover)) {
        return $e;
    }

    return null;
}

function master_validate_others(
    PDO $conn,
    string $segment,
    string $activity,
    string $driver,
    string $driverId,
    string $operationsPh,
    string $tr,
    string $gs,
    string $truck
): ?string {
    if ($e = master_validate_trip_rates_pair($conn, $segment, $activity)) {
        return $e;
    }
    if ($e = master_validate_driver($conn, $driver, $driverId)) {
        return $e;
    }
    if ($e = master_validate_location($conn, $operationsPh)) {
        return $e;
    }
    if ($e = master_validate_trailer($conn, $tr)) {
        return $e;
    }
    if ($e = master_validate_unit_genset($conn, $gs)) {
        return $e;
    }
    if ($e = master_validate_unit_truck($conn, $truck)) {
        return $e;
    }

    return null;
}

function master_validate_cargo(
    PDO $conn,
    string $segment,
    string $activity,
    string $driver,
    string $driverId,
    string $truck,
    string $customerPh
): ?string {
    if ($e = master_validate_trip_rates_pair($conn, $segment, $activity)) {
        return $e;
    }
    if ($e = master_validate_driver($conn, $driver, $driverId)) {
        return $e;
    }
    if ($e = master_validate_unit_truck($conn, $truck)) {
        return $e;
    }
    // Cargo truck "Customer / PH" is a free-text customer field (its suggestions
    // come from past entries via get_customers.php, not the location master), so
    // it is intentionally NOT validated against the location table.

    return null;
}

function master_validate_dpc(
    PDO $conn,
    string $segment,
    string $activity,
    string $driver,
    string $driverId,
    string $ph,
    string $tr,
    string $truck
): ?string {
    if ($e = master_validate_trip_rates_pair($conn, $segment, $activity)) {
        return $e;
    }
    if ($e = master_validate_driver($conn, $driver, $driverId)) {
        return $e;
    }
    if ($e = master_validate_location($conn, $ph)) {
        return $e;
    }
    if ($e = master_validate_trailer($conn, $tr)) {
        return $e;
    }
    if ($e = master_validate_unit_truck($conn, $truck)) {
        return $e;
    }

    return null;
}
