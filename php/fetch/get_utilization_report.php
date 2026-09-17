<?php
include "../config/config.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");

$period = strtolower(trim((string) ($_GET["period"] ?? "weekly")));
if (!in_array($period, ["weekly", "monthly"], true)) {
    $period = "weekly";
}

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
        $dayOfWeek = (int) $today->format("N");
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

function util_norm_text($value): string
{
    return trim((string) $value);
}

function util_num($value): float
{
    if ($value === null) {
        return 0.0;
    }

    $clean = preg_replace('/[^0-9.\-]/', "", (string) $value);
    if ($clean === "" || $clean === "-" || $clean === ".") {
        return 0.0;
    }

    return (float) $clean;
}

function util_first_non_empty(...$values): string
{
    foreach ($values as $value) {
        $text = util_norm_text($value);
        if ($text !== "") {
            return $text;
        }
    }

    return "";
}

function util_effective_date(array $row): ?string
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
        $text = util_norm_text($candidate);
        if ($text === "" || $text === "0000-00-00" || $text === "0000-00-00 00:00:00") {
            continue;
        }

        return substr($text, 0, 10);
    }

    return null;
}

function util_shipper_label(array $row): string
{
    return util_first_non_empty(
        $row["shipper"] ?? "",
        $row["customer_ph"] ?? "",
        $row["operations_ph"] ?? "",
        $row["ph"] ?? "",
        "Unspecified"
    );
}

function util_like(string $value, string $needle): bool
{
    return stripos($value, $needle) !== false;
}

function util_series_label(array $row): string
{
    $entryType = strtoupper(util_norm_text($row["entry_type"] ?? ""));
    $billingSku = util_norm_text($row["billing_sku"] ?? "");
    $billingSkuUpper = strtoupper($billingSku);
    $phNorm = strtoupper(util_norm_text($row["ph"] ?? ""));
    $customer = strtoupper(util_norm_text(
        $row["customer_ph"] ?? $row["ph"] ?? $row["operations_ph"] ?? ""
    ));
    $shipper = util_norm_text($row["shipper"] ?? "");

    if ($entryType === "RV ENTRY") {
        $shipperSeriesMap = [
            "DOLE" => "TDC-Dole",
            "SUMIFRU" => "TDC-Sumifru",
            "ABC CATEEL" => "ABC Cateel",
            "ABC PANTUKAN" => "ABC Pantukan",
            "ABC DONMAR" => "ABC Donmar",
            "ABC LUPON" => "ABC Lupon",
            "FARMIND" => "TDC Farmind",
            "GOOD FARMER" => "TDC Good Farmer",
            "TDC - CLASS B" => "DICT B/bulk - Class B Bananas",
        ];
        $shipperKey = strtoupper($shipper);
        if ($shipperKey !== "") {
            return $shipperSeriesMap[$shipperKey] ?? $shipper;
        }
        return "Unspecified";
    }

    if ($entryType === "DPC_KDS & OPM ENTRY") {
        if (util_like($phNorm, "PH") || util_like($phNorm, "TDC")) {
            return "TPD KDs - TDC Compound Trips";
        }
        return "TPD KDs - Outside Trips";
    }

    if ($entryType === "CARGO TRUCK ENTRY" && util_like($billingSkuUpper, "CT-OTHER HAULING")) {
        $outside = util_num($row["outside"] ?? "");
        $compound = util_num($row["compound"] ?? "");
        if ($outside > 0 && $outside >= $compound) {
            return "Cargo Truck Hauling-Outside Trips";
        }
        if ($compound > 0) {
            return "Cargo Truck Hauling-Compound Trips";
        }
        return "Cargo Truck Hauling-Outside Trips";
    }

    if ($entryType === "DRY VAN ENTRY" && util_like($billingSkuUpper, "TPD")) {
        return "TPD Container DPC Export";
    }

    if (util_like($billingSkuUpper, "RECYCLABLE")) {
        return "TPD Recyclable Plastics";
    }
    if (util_like($billingSkuUpper, "OPM")) {
        return "OPM Hauling";
    }
    if (
        util_like($billingSkuUpper, "RC") && util_like($billingSkuUpper, "REPOSITION") ||
        util_like($billingSkuUpper, "RC-") ||
        util_like($billingSkuUpper, "REPOSITIONING")
    ) {
        return "RC Repositioning";
    }
    if (
        util_like($billingSkuUpper, "GARBAGE") ||
        util_like($billingSkuUpper, "INDUSTRIAL") ||
        util_like($billingSkuUpper, "WASTE")
    ) {
        return "Garbage/Industrial Waste";
    }
    if (util_like($billingSkuUpper, "HEAVY")) {
        return "Heavy Equipment Transport";
    }
    if ($entryType === "CARGO TRUCK ENTRY" && util_like($billingSkuUpper, "REJECT")) {
        return "TDC Reject Plastics";
    }
    if (util_like($billingSkuUpper, "MISCELLANEOUS") || util_like($billingSkuUpper, "OTHER HAULING")) {
        return "Miscellaneous/Other Hauling";
    }
    if (util_like($billingSkuUpper, "HUSTLING") && $customer === "DOLE") {
        return "Container Hustling- DOLE";
    }
    if (util_like($billingSkuUpper, "HUSTLING") && $customer === "DICT") {
        return "Container Hustling- DICT";
    }

    return util_shipper_label($row);
}

function util_unique_units(array $values): array
{
    $map = [];
    foreach ($values as $value) {
        $text = util_norm_text($value);
        if ($text === "") {
            continue;
        }

        $map[strtoupper($text)] = $text;
    }

    return array_values($map);
}

function util_init_shipper_bucket(string $shipper): array
{
    return [
        "shipper" => $shipper,
        "trips" => 0.0,
        "unique_units_map" => [],
        "unit_uses" => 0.0,
        "units" => [],
    ];
}

function util_apply_units(array &$bucket, array $units, float $tripCount): void
{
    foreach ($units as $unit) {
        $unitKey = strtoupper($unit);
        $bucket["unique_units_map"][$unitKey] = $unit;
        $bucket["units"][$unitKey] = ($bucket["units"][$unitKey] ?? 0) + $tripCount;
        $bucket["unit_uses"] += $tripCount;
    }
}

function util_finalize_breakdown(array $buckets): array
{
    $rows = [];
    foreach ($buckets as $bucket) {
        arsort($bucket["units"]);
        $topUnit = "-";
        $topUnitUses = 0;
        if (!empty($bucket["units"])) {
            $topUnit = (string) array_key_first($bucket["units"]);
            $topUnit = $bucket["unique_units_map"][$topUnit] ?? $topUnit;
            $topUnitUses = reset($bucket["units"]);
        }

        $clean = static function ($value) {
            return $value == (int) $value ? (int) $value : round((float) $value, 2);
        };

        $rows[] = [
            "shipper" => $bucket["shipper"],
            "trips" => $clean($bucket["trips"]),
            "unique_units" => count($bucket["unique_units_map"]),
            "unit_uses" => $clean($bucket["unit_uses"]),
            "top_unit" => $topUnit,
            "top_unit_uses" => $clean($topUnitUses),
            "units_breakdown" => array_map(static function ($unitName, $uses) use ($clean) {
                return [
                    "unit_name" => $unitName,
                    "uses" => $clean($uses),
                ];
            }, array_map(static function ($key) use ($bucket) {
                return $bucket["unique_units_map"][$key] ?? $key;
            }, array_keys($bucket["units"])), array_values($bucket["units"])),
        ];
    }

    usort($rows, static function ($a, $b) {
        if ($b["unit_uses"] !== $a["unit_uses"]) {
            return $b["unit_uses"] <=> $a["unit_uses"];
        }
        if ($b["unique_units"] !== $a["unique_units"]) {
            return $b["unique_units"] <=> $a["unique_units"];
        }
        return strcasecmp($a["shipper"], $b["shipper"]);
    });

    return $rows;
}

try {
    $sql = "
        SELECT
            entry_id,
            entry_type,
            shipper,
            customer_ph,
            operations_ph,
            ph,
            billing_sku,
            outside,
            compound,
            total_trips,
            prime_mover,
            truck,
            truck2,
            delivered_by_prime_mover,
            tr,
            tr2,
            waybill_date,
            cargo_date,
            dpc_date,
            others_date,
            date_hauled,
            pullout_location_arrival_date,
            created_date
        FROM operations
        WHERE COALESCE(
            NULLIF(waybill_date::text, '')::date,
            NULLIF(cargo_date::text, '')::date,
            NULLIF(dpc_date::text, '')::date,
            NULLIF(others_date::text, '')::date,
            NULLIF(date_hauled::text, '')::date,
            NULLIF(pullout_location_arrival_date::text, '')::date,
            created_date::date
        ) BETWEEN ? AND ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$dateFrom, $dateTo]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $truckByShipper = [];
    $trailerByShipper = [];
    $allTrucks = [];
    $allTrailers = [];
    $totalTrips = 0.0;

    foreach ($rows as $row) {
        if (util_effective_date($row) === null) {
            continue;
        }

        $shipper = util_series_label($row);
        $tripCount = util_num($row["total_trips"] ?? 0);
        if ($tripCount <= 0) {
            $tripCount = 1.0;
        }

        $truckUnits = util_unique_units([
            $row["prime_mover"] ?? "",
            $row["truck"] ?? "",
            $row["truck2"] ?? "",
            $row["delivered_by_prime_mover"] ?? "",
        ]);
        $trailerUnits = util_unique_units([
            $row["tr"] ?? "",
            $row["tr2"] ?? "",
        ]);

        if (!isset($truckByShipper[$shipper])) {
            $truckByShipper[$shipper] = util_init_shipper_bucket($shipper);
        }
        if (!isset($trailerByShipper[$shipper])) {
            $trailerByShipper[$shipper] = util_init_shipper_bucket($shipper);
        }

        $truckByShipper[$shipper]["trips"] += $tripCount;
        $trailerByShipper[$shipper]["trips"] += $tripCount;
        $totalTrips += $tripCount;

        util_apply_units($truckByShipper[$shipper], $truckUnits, $tripCount);
        util_apply_units($trailerByShipper[$shipper], $trailerUnits, $tripCount);

        foreach ($truckUnits as $unit) {
            $allTrucks[strtoupper($unit)] = $unit;
        }
        foreach ($trailerUnits as $unit) {
            $allTrailers[strtoupper($unit)] = $unit;
        }
    }

    $truckBreakdown = util_finalize_breakdown($truckByShipper);
    $trailerBreakdown = util_finalize_breakdown($trailerByShipper);

    $chartTruck = array_slice(array_map(static function ($row) {
        return [
            "shipper" => $row["shipper"],
            "unique_units" => $row["unique_units"],
            "unit_uses" => $row["unit_uses"],
        ];
    }, $truckBreakdown), 0, 10);

    $chartTrailer = array_slice(array_map(static function ($row) {
        return [
            "shipper" => $row["shipper"],
            "unique_units" => $row["unique_units"],
            "unit_uses" => $row["unit_uses"],
        ];
    }, $trailerBreakdown), 0, 10);

    $clean = static function ($value) {
        return $value == (int) $value ? (int) $value : round((float) $value, 2);
    };

    echo json_encode([
        "success" => true,
        "period" => $period,
        "date_from" => $dateFrom,
        "date_to" => $dateTo,
        "totals" => [
            "active_shippers" => count(array_unique(array_merge(array_keys($truckByShipper), array_keys($trailerByShipper)))),
            "total_trips" => $clean($totalTrips),
            "unique_trucks" => count($allTrucks),
            "unique_trailers" => count($allTrailers),
        ],
        "truck_breakdown" => $truckBreakdown,
        "trailer_breakdown" => $trailerBreakdown,
        "truck_chart" => $chartTruck,
        "trailer_chart" => $chartTrailer,
        "generated_at" => date("c"),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to build utilization report: " . $e->getMessage(),
    ]);
}
exit();
?>
