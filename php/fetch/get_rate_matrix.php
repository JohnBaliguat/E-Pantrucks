<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_rate_fuel_schema.php";
require_once __DIR__ . "/../helpers/rate_matrix_customers.php";
require_once __DIR__ . "/../helpers/fuel_rate_engine.php";
require_once __DIR__ . "/../helpers/dict_shuttling.php";
require_once __DIR__ . "/../helpers/dict_industrial_waste.php";

/**
 * Default grid rows for the flat, per-trip DICT services (Shuttling / Industrial Waste)
 * when finance has not saved any rate_lane rows yet — so the Rate Matrix shows each route
 * with its built-in fallback rate ready to view/edit (a Save persists them). dcode = the
 * lane key (what dict_route_rate matches on); the fuel columns are hidden for these keys.
 */
function rate_matrix_flat_defaults(string $customerKey): array
{
    $cfgs = [
        "dict_van_shuttling" => function_exists("dict_shuttling_config") ? dict_shuttling_config() : null,
        "dict_industrial_waste" => function_exists("industrial_waste_config") ? industrial_waste_config() : null,
    ];
    $cfg = $cfgs[$customerKey] ?? null;
    if ($cfg === null || empty($cfg["lanes"])) {
        return [];
    }
    $out = [];
    foreach ($cfg["lanes"] as $laneKey => $lane) {
        $out[] = [
            "lane_id" => 0,
            "effective_from" => "", "effective_to" => "",
            "segment" => "", "origin" => "", "packing_house" => "",
            "destination" => $lane["label"] ?? $laneKey,
            "dcode" => $laneKey,
            "base_rate" => $lane["price"] ?? "",
            "pump_price" => "", "price_movement" => "", "monthly_fuel_average" => "",
            "sort_order" => 0, "active" => true, "round_mode" => "",
            "bands" => [], "rate_fuel" => null, "rate_fuel_date" => "",
        ];
    }
    return $out;
}

header("Content-Type: application/json; charset=utf-8");
ensure_rate_fuel_schema($conn);

$customers = rate_matrix_customers($conn);

// Today's diesel price (common/average source) for the live "Rate (now)" column.
$fuelDate = date("Y-m-d");
$customerKey = trim((string) ($_GET["customer"] ?? ""));
$currentFuel = fuel_value_for_source(fuel_price_for_customer_date($conn, $customerKey, $fuelDate), "");
$lanes = [];

if ($customerKey !== "") {
    $laneStmt = $conn->prepare(
        "SELECT lane_id, effective_from, effective_to, segment, origin, packing_house, destination, dcode, base_rate,
                pump_price, price_movement, monthly_fuel_average, sort_order, active, round_mode
         FROM rate_lane WHERE customer_key = ?
         ORDER BY effective_from ASC NULLS FIRST, sort_order ASC, lane_id ASC"
    );
    $laneStmt->execute([$customerKey]);
    $lanes = $laneStmt->fetchAll(PDO::FETCH_ASSOC);

    // Flat DICT services with nothing saved yet: seed the grid with their default routes/rates
    // so finance can see and edit them (Save then persists real rate_lane rows).
    if (empty($lanes)) {
        $lanes = rate_matrix_flat_defaults($customerKey);
    }

    // Attach each lane's optional fuel-price bands (stepped flat rate per diesel range).
    $bandStmt = $conn->prepare(
        "SELECT fuel_from, fuel_to, rate FROM rate_lane_band WHERE lane_id = ? ORDER BY sort_order, band_id"
    );
    foreach ($lanes as &$ln) {
        $bandStmt->execute([(int) $ln["lane_id"]]);
        $ln["bands"] = $bandStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($ln);

    // "Rate (now)" reflects billing: escalation applies only when a Monthly Avg Fuel is
    // pinned (user rule 2026-08-18). A blank monthly average has NO effective fuel price,
    // so the preview shows the base rate (the JS renders base + 0% when rate_fuel is null).
    foreach ($lanes as &$lane) {
        $lane["rate_fuel"] = is_numeric($lane["monthly_fuel_average"] ?? null) && (float) $lane["monthly_fuel_average"] > 0
            ? (float) $lane["monthly_fuel_average"]
            : null;
        $lane["rate_fuel_date"] = "";
    }
    unset($lane);
}

echo json_encode([
    "success" => true,
    "customers" => $customers,
    "customer" => $customerKey,
    "lanes" => $lanes,
    "current_fuel" => $currentFuel,
    "fuel_date" => $fuelDate,
]);
exit();
?>
