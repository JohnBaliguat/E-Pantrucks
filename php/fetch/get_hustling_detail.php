<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header("Content-Type: application/json; charset=utf-8");

$tz = new DateTimeZone("Asia/Manila");
$today = new DateTimeImmutable("now", $tz);

$dateFromInput = trim((string) ($_GET["date_from"] ?? ""));
$dateToInput   = trim((string) ($_GET["date_to"]   ?? ""));
$series        = trim((string) ($_GET["series"]    ?? "DICT"));

// Series → customer filter mapping
$customer = null;
$seriesLabel = $series;
switch (strtoupper($series)) {
    case "DICT":
        $customer = "DICT";
        $seriesLabel = "Container Hustling- DICT";
        break;
    case "DOLE":
        $customer = "DOLE";
        $seriesLabel = "Container Hustling- DOLE";
        break;
    case "ALL":
        $customer = null;
        $seriesLabel = "All Container Hustling";
        break;
    default:
        echo json_encode([
            "success" => false,
            "message" => "Unknown series: $series. Use DICT, DOLE, or ALL.",
        ]);
        exit;
}

if (
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFromInput) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateToInput) ||
    $dateFromInput > $dateToInput
) {
    // Fallback: current operational week (Friday → Thursday).
    $dayOfWeek = (int) $today->format("N");
    $daysBack = ($dayOfWeek - 5 + 7) % 7;
    $start = $today->modify("-{$daysBack} days");
    $end = $start->modify("+6 days");
    $dateFromInput = $start->format("Y-m-d");
    $dateToInput   = $end->format("Y-m-d");
}

$sql = "
    WITH ops AS (
        SELECT
            entry_id,
            UPPER(COALESCE(
                NULLIF(customer_ph::text, ''),
                NULLIF(ph::text, ''),
                NULLIF(operations_ph::text, ''),
                ''
            )) AS customer_norm,
            COALESCE(billing_sku::text, '') AS billing_sku,
            NULLIF(waybill_date::text, '')::date AS eff_date,
            waybill,
            truck,
            tr,
            driver,
            load_quantity_weight,
            unit_of_measure
        FROM operations
        WHERE billing_sku ILIKE '%Hustling%'
    )
    SELECT entry_id, eff_date, waybill, truck, tr, driver,
           load_quantity_weight, unit_of_measure, customer_norm, billing_sku
    FROM ops
    WHERE eff_date BETWEEN ? AND ?
";

$params = [$dateFromInput, $dateToInput];
if ($customer !== null) {
    $sql .= " AND customer_norm = ?";
    $params[] = $customer;
}
$sql .= " ORDER BY eff_date ASC, entry_id ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$out = [];
$totalLoad = 0.0;
foreach ($rows as $r) {
    $loadRaw = trim((string) ($r["load_quantity_weight"] ?? ""));
    $loadNum = (float) preg_replace('/[^0-9.\-]/', '', $loadRaw);
    $totalLoad += $loadNum;

    // Display: integer if whole, else trimmed decimal — no unit suffix.
    if ($loadRaw === "") {
        $loadDisplay = "";
    } elseif ($loadNum == (int) $loadNum) {
        $loadDisplay = (string) (int) $loadNum;
    } else {
        $loadDisplay = rtrim(rtrim(number_format($loadNum, 4, ".", ""), "0"), ".");
    }

    $out[] = [
        "entry_id" => (int) $r["entry_id"],
        "date" => $r["eff_date"],
        "waybill" => $r["waybill"] ?? "",
        "truck" => $r["truck"] ?? "",
        "tr" => $r["tr"] ?? "",
        "driver" => $r["driver"] ?? "",
        "load_quantity_weight" => $loadDisplay,
        "unit_of_measure" => $r["unit_of_measure"] ?? "",
    ];
}

echo json_encode([
    "success" => true,
    "series" => $seriesLabel,
    "date_from" => $dateFromInput,
    "date_to" => $dateToInput,
    "row_count" => count($out),
    "total_load" => $totalLoad,
    "rows" => $out,
    "generated_at" => date("c"),
]);
exit;
?>
