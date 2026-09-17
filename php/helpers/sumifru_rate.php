<?php

require_once __DIR__ . "/fuel_rate_engine.php";

/**
 * Sumifru containerized hauling rate resolution: lane + fuel-driven tier.
 *
 * The rate lives in the simplified rate matrix (rate_lane) as a formula-priced lane;
 * the charged rate is computed per trip from the fuel price via
 * rate_matrix_formula_price(). The Sumifru statement/SAP line still carries a rate
 * CODE (e.g. STS115) — the lane part (STS/STP/PTP) comes from where the van went and
 * the tier (the trailing number) is the effective fuel movement over base.
 *
 *   STS = Sumifru Wharf -> Sumifru Wharf
 *   STP = Sumifru Wharf -> Panabo Port
 *   PTP = Panabo Port   -> Panabo Port
 */

/**
 * Is a trip location the Panabo Port/Wharf end of a Sumifru lane?
 *
 * Matches the long forms ("PANABO", "DICT") and the short code the encoders use
 * for Panabo Wharf — "PW" (as the whole value or a standalone token, so it never
 * fires on a substring like "PWXYZ"). Anything else is the Sumifru Wharf end.
 */
function sumifru_is_panabo(string $location): bool
{
    $loc = strtoupper(trim($location));
    if ($loc === "") {
        return false;
    }
    return strpos($loc, "PANABO") !== false
        || strpos($loc, "DICT") !== false
        || preg_match('/\bPW\b/', $loc) === 1;
}

function sumifru_lane_code(array $entry): string
{
    // Delivery end: prefer delivered_to, fall back to destination when it is blank.
    $dest = trim((string) ($entry["delivered_to"] ?? ""));
    if ($dest === "") {
        $dest = trim((string) ($entry["destination"] ?? ""));
    }
    $origin = trim((string) ($entry["empty_pullout_location"] ?? ""));

    // Panabo Port destination -> STP (Sumifru Wharf -> Panabo) or PTP (Panabo -> Panabo).
    if (sumifru_is_panabo($dest)) {
        return sumifru_is_panabo($origin) ? "PTP" : "STP";
    }
    // Default: Sumifru Wharf -> Sumifru Wharf.
    return "STS";
}

/**
 * The rate_lane row for a Sumifru lane code (STS/STP/PTP), matched by origin/destination
 * (S = Sumifru Wharf, P = Panabo Port) and effective on/before $tripDate — the newest
 * dated row wins over an undated (always-applies) one. Returns the assoc row or null.
 */
function sumifru_lane_row(PDO $conn, string $laneCode, string $tripDate = "", string $customerKey = "sumifru_containerized"): ?array
{
    static $cache = [];
    $laneCode = strtoupper(trim($laneCode));
    if (strlen($laneCode) < 3) {
        return null;
    }
    $date = substr(trim($tripDate), 0, 10);
    $key = $customerKey . "|" . $laneCode . "|" . $date;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $originLike = ($laneCode[0] === "P") ? "%PANABO%" : "%SUMIFRU%";
    $destLike = ($laneCode[2] === "P") ? "%PANABO%" : "%SUMIFRU%";
    $params = [$customerKey, $originLike, $destLike];
    $sql = "SELECT * FROM rate_lane
            WHERE customer_key = ? AND active = TRUE AND origin ILIKE ? AND destination ILIKE ?";
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $sql .= " AND (effective_from IS NULL OR effective_from <= ?)
                  AND (effective_to IS NULL OR effective_to >= ?)";
        $params[] = $date;
        $params[] = $date;
    }
    $sql .= " ORDER BY effective_from DESC NULLS LAST, sort_order, lane_id LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $cache[$key] = ($stmt->fetch(PDO::FETCH_ASSOC) ?: null);
}

/**
 * Fuel-movement tier (e.g. "108") for the fuel period in effect on $tripDate.
 *
 * A manually-set `fuel_price.fuel_tier` wins (finance can pin a period to a fixed tier).
 * Otherwise it is derived from the lane's fuel-movement formula:
 * tier = round(charged_rate / base_rate * 100) — i.e. 100 + the % the fuel has moved the
 * rate above base. Memoized per date.
 */
function sumifru_fuel_tier(PDO $conn, string $tripDate, string $customerKey = "sumifru_containerized", string $fuelSource = ""): string
{
    static $cache = [];
    $date = substr(trim($tripDate), 0, 10);
    $ck = $customerKey . "|" . $fuelSource . "|" . $date;
    if (array_key_exists($ck, $cache)) {
        return $cache[$ck];
    }

    $row = fuel_price_for_customer_date($conn, $customerKey, $date);
    if (!$row) {
        return $cache[$ck] = "";
    }

    // 1) Explicit manual override on the fuel period wins.
    $manual = preg_replace('/[^0-9]/', "", trim((string) ($row["fuel_tier"] ?? "")));
    if ($manual !== "") {
        return $cache[$ck] = $manual;
    }

    // 2) Derive from the fuel-movement formula on a representative lane (the fuel model is
    //    customer-wide, so any active Sumifru lane yields the same tier).
    $fuelPrice = fuel_value_for_source($row, $fuelSource);
    if ($fuelPrice === null) {
        return $cache[$ck] = "";
    }
    $lane = sumifru_lane_row($conn, "STS", $date, $customerKey)
        ?? sumifru_lane_row($conn, "STP", $date, $customerKey)
        ?? sumifru_lane_row($conn, "PTP", $date, $customerKey);
    if (!$lane) {
        return $cache[$ck] = "";
    }
    $base = (float) $lane["base_rate"];
    if ($base <= 0) {
        return $cache[$ck] = "";
    }
    $rate = rate_matrix_formula_price(
        $base,
        fuel_rate_num($lane["pump_price"] ?? null),
        fuel_rate_num($lane["price_movement"] ?? null),
        $fuelPrice
    );
    return $cache[$ck] = (string) (int) round($rate / $base * 100);
}

/**
 * Charged rate for a Sumifru lane on $tripDate, computed from the lane's fuel-movement
 * formula (rate_matrix_formula_price). Memoized per lane+date. Returns 0.0 when the lane
 * row is missing (caller falls back to the flat `rates` code).
 */
function sumifru_matrix_rate(PDO $conn, string $lane, string $tier, string $customerKey = "sumifru_containerized", string $tripDate = ""): float
{
    static $cache = [];
    $lane = strtoupper(trim($lane));
    if (strlen($lane) < 3) {
        return 0.0;
    }
    $date = substr(trim($tripDate), 0, 10);
    $key = $customerKey . "|" . $lane . "|" . $date;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $row = sumifru_lane_row($conn, $lane, $date, $customerKey);
    if (!$row) {
        return $cache[$key] = 0.0;
    }
    $monthlyAverage = fuel_rate_num($row["monthly_fuel_average"] ?? null);
    $fuelPrice = $monthlyAverage !== null && $monthlyAverage > 0
        ? $monthlyAverage
        : fuel_value_for_source(fuel_price_for_customer_date($conn, $customerKey, $date), "");
    $rate = rate_matrix_formula_price(
        (float) $row["base_rate"],
        fuel_rate_num($row["pump_price"] ?? null),
        fuel_rate_num($row["price_movement"] ?? null),
        $fuelPrice
    );
    return $cache[$key] = $rate;
}

/** Live rate for a rate_code from the `rates` master data (memoized per code). */
function sumifru_rate_for_code(PDO $conn, string $rateCode): float
{
    static $cache = [];
    $rateCode = trim($rateCode);
    if ($rateCode === "") {
        return 0.0;
    }
    if (array_key_exists($rateCode, $cache)) {
        return $cache[$rateCode];
    }
    $stmt = $conn->prepare("SELECT NULLIF(rate, '')::numeric FROM rates WHERE rate_code = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$rateCode]);
    $value = $stmt->fetchColumn();
    return $cache[$rateCode] = (is_numeric($value) ? (float) $value : 0.0);
}

/**
 * Rate-usage summary for Sumifru: group the previewed trips by (lane, tier, rate)
 * and show how each PHP rate was derived from the lane's fuel-movement formula.
 * Same shape as box_banana_rate_summary(); subtotal is PHP rate x trips (before forex).
 */
function sumifru_rate_summary(PDO $conn, array $entries, string $customerKey = "sumifru_containerized"): array
{
    $groups = [];
    $entryKeys = [];
    $unmatched = 0;
    foreach ($entries as $e) {
        $tripDate = substr(trim((string) ($e["trip_date"] ?? "")), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tripDate)) {
            $unmatched++;
            continue;
        }
        $res = sumifru_resolve_rate($conn, $e, $tripDate);
        $rate = (float) $res["rate"];
        if ($rate <= 0) {
            $unmatched++;
            continue;
        }
        $laneCode = (string) $res["lane"];
        $laneRow = sumifru_lane_row($conn, $laneCode, $tripDate, $customerKey);
        $monthlyAverage = $laneRow ? fuel_rate_num($laneRow["monthly_fuel_average"] ?? null) : null;
        $fuel = $monthlyAverage !== null && $monthlyAverage > 0
            ? $monthlyAverage
            : fuel_value_for_source(fuel_price_for_customer_date($conn, $customerKey, $tripDate), "");
        $fuel = $fuel !== null ? (float) $fuel : null;
        $key = $laneCode . "|" . number_format($rate, 2, ".", "");
        $entryKeys[(int) ($e["entry_id"] ?? 0)] = $key;
        if (!isset($groups[$key])) {
            // Lane label as Origin → Packing House → Destination (from the rate_lane
            // config), falling back to the lane code when the route parts are blank.
            $origin = $laneRow ? trim((string) ($laneRow["origin"] ?? "")) : "";
            $ph = $laneRow ? trim((string) ($laneRow["packing_house"] ?? "")) : "";
            $dest = $laneRow ? trim((string) ($laneRow["destination"] ?? "")) : "";
            $route = implode(" → ", array_filter([$origin, $ph, $dest]));
            $groups[$key] = [
                "key" => $key,
                "lane" => $route !== "" ? $route : $laneCode,
                "dcode" => (string) $res["rate_code"],
                "tier" => (string) $res["tier"],
                "base_rate" => $laneRow ? (float) $laneRow["base_rate"] : null,
                "pump_price" => $laneRow ? fuel_rate_num($laneRow["pump_price"] ?? null) : null,
                "price_movement" => $laneRow ? fuel_rate_num($laneRow["price_movement"] ?? null) : null,
                "fuel_price" => $fuel,
                "fuel_varies" => false,
                "rate" => $rate,
                "trips" => 0,
                "subtotal" => 0.0,
            ];
        } elseif ($fuel !== null && $groups[$key]["fuel_price"] !== null
            && abs($fuel - (float) $groups[$key]["fuel_price"]) > 0.0001) {
            $groups[$key]["fuel_varies"] = true;
        }
        $groups[$key]["trips"]++;
        $groups[$key]["subtotal"] += $rate;
    }

    $lines = array_values($groups);
    foreach ($lines as &$ln) {
        $ln["subtotal"] = round($ln["subtotal"], 2);
        if (!empty($ln["fuel_varies"])) {
            $ln["fuel_price"] = null;
        }
        unset($ln["fuel_varies"]);
    }
    unset($ln);
    usort($lines, static fn($a, $b) => $b["subtotal"] <=> $a["subtotal"]);

    return ["lines" => $lines, "unmatched" => $unmatched, "fuel_source" => "", "entry_keys" => $entryKeys];
}

/**
 * Resolve a trip's PHP rate via lane + fuel tier. Returns
 *   ['rate', 'rate_code', 'lane', 'tier'] (rate 0 when the lane/code is missing).
 */
function sumifru_resolve_rate(PDO $conn, array $entry, string $tripDate): array
{
    $lane = sumifru_lane_code($entry);
    $tier = sumifru_fuel_tier($conn, $tripDate);
    $code = $tier !== "" ? $lane . $tier : "";
    // Source of truth = the fuel rate matrix (formula-priced lane); fall back to the flat
    // `rates` code only if the matrix has no matching lane.
    $rate = sumifru_matrix_rate($conn, $lane, $tier, "sumifru_containerized", $tripDate);
    if ($rate <= 0 && $code !== "") {
        $rate = sumifru_rate_for_code($conn, $code);
    }
    return ["rate" => $rate, "rate_code" => $code, "lane" => $lane, "tier" => $tier];
}
