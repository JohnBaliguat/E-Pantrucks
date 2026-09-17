<?php

/**
 * Location-master validation.
 *
 * Billing (especially the Dry Van fuel Rate Matrix) matches trips by their Pull-Out /
 * Warehouse / Return locations, so a mistyped value (a number, the customer name, a date
 * in the wrong field) silently drops the trip from billing. To stop that at the source,
 * data-entry endpoints reject a location that isn't registered in the `location` master.
 */

/**
 * Whether a location name is registered in the location master (case-insensitive, trimmed).
 * A blank value is treated as registered — optional fields aren't forced to be filled here.
 * Cached per request. If the location table is unavailable, returns true so saves are never
 * blocked by an infrastructure problem.
 */
function location_is_registered(PDO $conn, string $name): bool
{
    $key = strtoupper(trim($name));
    if ($key === "") {
        return true;
    }
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $stmt = $conn->prepare("SELECT 1 FROM location WHERE UPPER(TRIM(location_name)) = ? LIMIT 1");
        $stmt->execute([$key]);
        $cache[$key] = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $cache[$key] = true; // table missing / DB issue -> don't block the save
    }
    return $cache[$key];
}

/**
 * Given [label => value] location fields, return [label => value] for those whose value is
 * non-blank and NOT registered in the location master. Empty result = everything is valid.
 */
function unregistered_locations(PDO $conn, array $fields): array
{
    $bad = [];
    foreach ($fields as $label => $value) {
        $value = trim((string) $value);
        if ($value !== "" && !location_is_registered($conn, $value)) {
            $bad[$label] = $value;
        }
    }
    return $bad;
}

/**
 * A ready-to-show error message for unregistered location fields, or "" when all are valid.
 */
function unregistered_locations_message(PDO $conn, array $fields): string
{
    $bad = unregistered_locations($conn, $fields);
    if (!$bad) {
        return "";
    }
    $parts = [];
    foreach ($bad as $label => $value) {
        $parts[] = "$label \"$value\"";
    }
    return "Not a registered location: " . implode(", ", $parts)
        . ". Add it in Master Data → Locations first, or pick an existing one.";
}
