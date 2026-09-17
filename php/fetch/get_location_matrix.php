<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";

header("Content-Type: application/json; charset=utf-8");

// Ensure the columns exist so the editor works even on a fresh DB.
try {
    $conn->exec("ALTER TABLE location ADD COLUMN IF NOT EXISTS location_matrix TEXT");
    // region (DAVAO / PANABO) drives the SAP Route from a trip's actual pull-out/delivered.
    $conn->exec("ALTER TABLE location ADD COLUMN IF NOT EXISTS region TEXT");
} catch (Throwable $e) {
    // ignore — a missing table is surfaced below
}

try {
    $locations = $conn->query(
        "SELECT location_id, location_name, location_matrix, region FROM location ORDER BY location_name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Could not load locations: " . $e->getMessage()]);
    exit();
}

// Autofill suggestions: the vocabulary the rate matrix already uses
// (origins + destinations + dcodes), plus any location_matrix codes already
// set, so users pick consistent values instead of free-typing.
$suggestions = [];
try {
    $suggestions = $conn->query(
        "SELECT DISTINCT val FROM (
            SELECT origin AS val FROM rate_lane
            UNION SELECT destination FROM rate_lane
            UNION SELECT dcode FROM rate_lane
            UNION SELECT location_matrix FROM location
         ) s
         WHERE val IS NOT NULL AND TRIM(val) <> ''
         ORDER BY val ASC"
    )->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $suggestions = [];
}

echo json_encode([
    "success" => true,
    "locations" => $locations,
    "suggestions" => array_values($suggestions),
]);
exit();
?>
