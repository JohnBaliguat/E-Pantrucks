<?php

/**
 * Weekly Container Hustling report (pivot: unit x day, cell = total trips).
 *
 * Source: E-Pantrucks `operations` rows whose billing_sku contains "Hustling"
 * (same source as the Detail report).
 *   - Day column   = waybill_date
 *   - Cell value   = SUM(load_quantity_weight)  (this field holds the trip count)
 *   - Row (unit)   = truck
 *
 * GET ?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD  (defaults to the current
 * operational week, Friday -> Thursday).
 *
 * Returns:
 *   {
 *     success, date_from, date_to,
 *     days: ["YYYY-MM-DD", ...],            // one column per day
 *     units: [ { unit: "PM-88", values: [number|null per day], total: number } ]
 *   }
 */

require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header("Content-Type: application/json; charset=utf-8");

$tz = new DateTimeZone("Asia/Manila");
$today = new DateTimeImmutable("now", $tz);

$from = trim((string) ($_GET["date_from"] ?? ""));
$to   = trim((string) ($_GET["date_to"]   ?? ""));

if (
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ||
    $from > $to
) {
    // Fallback: current operational week (Friday -> Thursday).
    $dow = (int) $today->format("N");
    $back = ($dow - 5 + 7) % 7;
    $start = $today->modify("-{$back} days");
    $end = $start->modify("+6 days");
    $from = $start->format("Y-m-d");
    $to = $end->format("Y-m-d");
}

// Column list: one per day in the range (guard against an absurd span).
$days = [];
$cursor = new DateTimeImmutable($from, $tz);
$endDate = new DateTimeImmutable($to, $tz);
$guard = 0;
while ($cursor <= $endDate && $guard < 60) {
    $days[] = $cursor->format("Y-m-d");
    $cursor = $cursor->modify("+1 day");
    $guard++;
}

try {
    $stmt = $conn->prepare(
        "SELECT
            truck,
            NULLIF(waybill_date::text, '')::date AS eff_date,
            load_quantity_weight
         FROM operations
         WHERE billing_sku ILIKE '%Hustling%'
           AND truck IS NOT NULL AND TRIM(truck) <> ''
           AND NULLIF(waybill_date::text, '')::date BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $to]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Lookup failed."]);
    exit;
}

// Display the unit number only: "PM088" -> "88", "PM604" -> "604". Falls back
// to the raw value if there is no number in it.
$fmtUnit = static function ($u): string {
    $u = strtoupper(trim((string) $u));
    if ($u === "") {
        return "(no unit)";
    }
    if (preg_match('/(\d+)/', $u, $m)) {
        $n = ltrim($m[1], "0");
        return $n === "" ? "0" : $n;
    }
    return $u;
};

$matrix = [];
foreach ($rows as $r) {
    $key = trim((string) $r["truck"]);
    $day = $r["eff_date"];
    if ($key === "" || $day === null) {
        continue;
    }
    // load_quantity_weight holds the trip count; strip any unit text and sum.
    $load = (float) preg_replace('/[^0-9.\-]/', '', (string) ($r["load_quantity_weight"] ?? ""));

    if (!isset($matrix[$key])) {
        $matrix[$key] = ["unit" => $fmtUnit($key), "raw" => $key, "cells" => [], "total" => 0.0];
    }
    if (!isset($matrix[$key]["cells"][$day])) {
        $matrix[$key]["cells"][$day] = 0.0;
    }
    $matrix[$key]["cells"][$day] += $load;
    $matrix[$key]["total"] += $load;
}

// Order by unit number ascending for a predictable layout.
uasort($matrix, static function ($a, $b) {
    $na = (int) preg_replace('/\D/', '', $a["raw"]);
    $nb = (int) preg_replace('/\D/', '', $b["raw"]);
    return $na <=> $nb ?: strcmp($a["raw"], $b["raw"]);
});

// Emit whole numbers as int, otherwise a trimmed decimal.
$clean = static function (float $v) {
    if ($v == (int) $v) {
        return (int) $v;
    }
    return (float) rtrim(rtrim(number_format($v, 4, ".", ""), "0"), ".");
};

$units = [];
foreach ($matrix as $m) {
    $values = [];
    foreach ($days as $day) {
        $values[] = array_key_exists($day, $m["cells"]) ? $clean($m["cells"][$day]) : null;
    }
    $units[] = ["unit" => $m["unit"], "values" => $values, "total" => $clean($m["total"])];
}

echo json_encode([
    "success"      => true,
    "date_from"    => $from,
    "date_to"      => $to,
    "days"         => $days,
    "units"        => $units,
    "generated_at" => date("c"),
]);
exit;
