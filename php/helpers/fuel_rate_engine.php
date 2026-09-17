<?php

require_once __DIR__ . "/box_banana_customers.php";

/**
 * Fuel-based rate resolution engine.
 *
 * Given a customer, a trip's lane (segment/origin/destination/dcode) and the
 * trip date, work out the charged rate:
 *
 *   1. resolve_lane()          -> the rate_lane row for the route, effective on the
 *      trip date (newest effective_date on/before it wins).
 *   2. fuel_price_for_date()   -> the fuel_price row whose effective range covers
 *      the date (or the most recent on/before it).
 *   3. fuel_value_for_source() -> read the diesel price for the customer's chosen
 *      supplier column (e.g. "seaoil") or the common price.
 *   4. rate_matrix_formula_price() -> compute the charged rate from the lane's
 *      base_rate + fuel-movement formula (pump_price / price_movement + the 0.4x factor).
 *
 * Supplier columns recognised on fuel_price: petron, shell, caltex, phoenix,
 * flying_v, seaoil, jetti, my_gas, independent, common_price, average.
 */

function fuel_rate_num($value): ?float
{
    if ($value === null) {
        return null;
    }
    $text = trim((string) $value);
    if ($text === "" || !is_numeric($text)) {
        return null;
    }
    return (float) $text;
}

function fuel_rate_supplier_columns(): array
{
    return [
        "petron", "shell", "caltex", "phoenix", "flying_v",
        "seaoil", "jetti", "my_gas", "independent", "common_price", "average",
    ];
}

/** All fuel_price rows, loaded once per request. */
function fuel_price_rows(PDO $conn): array
{
    static $allRows = null;
    if ($allRows === null) {
        $allRows = $conn->query('SELECT * FROM fuel_price')->fetchAll(PDO::FETCH_ASSOC);
    }
    return $allRows;
}

/**
 * Whether a fuel/forex row (with a comma-separated `customer_keys`) applies to a customer:
 * a BLANK customer_keys is the global default (applies to everyone); otherwise the customer
 * must be listed. Shared by the fuel and forex resolvers.
 */
function fuel_row_applies_to_customer(array $row, string $customerKey): bool
{
    $keys = trim((string) ($row['customer_keys'] ?? ''));
    if ($keys === '') {
        return true; // global default
    }
    if ($customerKey === '') {
        return false;
    }
    foreach (explode(',', $keys) as $k) {
        if (trim($k) === $customerKey) {
            return true;
        }
    }
    return false;
}

/**
 * Pick the best fuel_price row from a GIVEN set for $onDate: prefer a row whose
 * [Updated-Date/From, To] window contains the date; otherwise (only when $strictOnly is
 * false) the most recent start (Updated Date, else From, else legacy price_date) on/before
 * it. $strictOnly = true is used for CUSTOMER-TAGGED rows, so an expired tagged window
 * (its To has passed) is NOT stretched forward — the caller then falls through to global.
 * Returns the row or null.
 */
function fuel_pick_by_date(array $rows, string $onDate, bool $strictOnly = false): ?array
{
    $best = null;
    foreach ($rows as $row) {
        $start = trim((string) ($row['updated_date'] ?? $row['effective_from'] ?? ''));
        $end = trim((string) ($row['effective_to'] ?? ''));
        if ($start === '' || $start > $onDate || ($end !== '' && $end < $onDate)) {
            continue;
        }
        if ($best === null || $start > $best['_start'] || ($start === $best['_start'] && (int) $row['id'] > (int) $best['id'])) {
            $row['_start'] = $start;
            $best = $row;
        }
    }
    if ($best !== null) {
        unset($best['_start']);
        return $best;
    }
    if ($strictOnly) {
        return null;
    }
    foreach ($rows as $row) {
        $start = trim((string) ($row['updated_date'] ?? $row['effective_from'] ?? $row['price_date'] ?? ''));
        if ($start === '' || $start > $onDate) {
            continue;
        }
        if ($best === null || $start > $best['_start'] || ($start === $best['_start'] && (int) $row['id'] > (int) $best['id'])) {
            $row['_start'] = $start;
            $best = $row;
        }
    }
    if ($best !== null) {
        unset($best['_start']);
    }
    return $best;
}

/**
 * Fuel_price row in effect on $onDate for GLOBAL (untagged) rows only. Customer-specific
 * lookups go through fuel_price_for_customer_date(). Returns the assoc row or null.
 */
function fuel_price_for_date(PDO $conn, string $onDate): ?array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $onDate)) {
        return null;
    }
    static $cache = [];
    if (array_key_exists($onDate, $cache)) {
        return $cache[$onDate];
    }
    $global = array_filter(
        fuel_price_rows($conn),
        static fn(array $r): bool => trim((string) ($r['customer_keys'] ?? '')) === ''
    );
    return $cache[$onDate] = fuel_pick_by_date($global, $onDate);
}

/**
 * Fuel_price row for a specific customer on $onDate. Two-pass: first the rows TAGGED with
 * this customer (customer_keys lists it), then the GLOBAL (untagged) rows — so a tagged row
 * overrides the global default for that customer. (Replaces the old monthly_fuel_price
 * routing for Sumifru / Box Banana — those customers are now driven by tagged fuel_price
 * rows.) Returns the assoc row or null.
 */
function fuel_price_for_customer_date(PDO $conn, string $customerKey, string $onDate): ?array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $onDate)) {
        return null;
    }
    static $cache = [];
    $cacheKey = trim($customerKey) . '|' . $onDate;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }
    $all = fuel_price_rows($conn);

    // Pass 1: rows explicitly tagged with this customer.
    $tagged = array_filter($all, static function (array $r) use ($customerKey): bool {
        return trim((string) ($r['customer_keys'] ?? '')) !== ''
            && fuel_row_applies_to_customer($r, $customerKey);
    });
    $row = fuel_pick_by_date($tagged, $onDate, true); // strict: don't stretch an expired tagged window
    if ($row !== null) {
        return $cache[$cacheKey] = $row;
    }

    // Pass 2: global (untagged) rows.
    $global = array_filter($all, static fn(array $r): bool => trim((string) ($r['customer_keys'] ?? '')) === '');
    return $cache[$cacheKey] = fuel_pick_by_date($global, $onDate);
}

/**
 * Diesel price for the customer's chosen supplier from a fuel_price row.
 * $source is a column name (e.g. "seaoil", "common_price", "average").
 * Falls back to common_price, then average, then any supplier value present.
 */
function fuel_value_for_source(?array $fuelRow, string $source): ?float
{
    if (!$fuelRow) {
        return null;
    }
    $source = trim($source);
    $candidates = [];
    if ($source !== "") {
        $candidates[] = $source;
    }
    $candidates[] = "common_price";
    $candidates[] = "average";

    foreach ($candidates as $col) {
        if (array_key_exists($col, $fuelRow)) {
            $val = fuel_rate_num($fuelRow[$col]);
            if ($val !== null) {
                return $val;
            }
        }
    }
    // Last resort: any supplier column with a value.
    foreach (fuel_rate_supplier_columns() as $col) {
        if (array_key_exists($col, $fuelRow)) {
            $val = fuel_rate_num($fuelRow[$col]);
            if ($val !== null) {
                return $val;
            }
        }
    }
    return null;
}

/**
 * Company fuel-surcharge factor: the charged rate moves at this multiple of the fuel's
 * percentage movement above the base pump price (fuel +5% -> rate +2% at 0.4x). Verified
 * against the Sumifru PM+CHASSIS+GENSET and CTH rate sheets. Change here if a contract
 * ever uses a different escalation.
 */
function rate_matrix_fuel_factor(): float
{
    return 0.4;
}

/**
 * Customer-level DEFAULT rounding mode for the fuel surcharge: "down" (truncate), "up"
 * (round up / ceil) or "nearest". Different finance rate sheets round differently:
 *   - ABC Lupon - Dole Asia (dole_asia_lupon) floors (base 22,139 @55% -> 27,009 not 27,010).
 *   - ABC Cateel (abc_cateel) rounds UP (base 35,292 @55% -> 43,057 not 43,056) to match the
 *     customer's rate matrix (finance 2026-09-04).
 *   - Everyone else, incl. TDC - Dole Asia and Sumifru, rounds to NEAREST (base 16,456 @40%
 *     -> 19,089; base 8,762 @80% -> 11,566).
 * A per-lane `round_mode` override (rate_matrix_lane_round_mode) wins over this — e.g. within
 * TDC - Dole Asia the DICT lane floors (9,279) while its Dole - PW lanes round to nearest.
 * Kept in sync with the JS mirror in master-data.php.
 */
function rate_matrix_customer_round_mode(string $customerKey): string
{
    if (str_starts_with($customerKey, "dole_asia") && $customerKey !== "dole_asia_tdc") {
        return "down";
    }
    if ($customerKey === "abc_cateel") {
        return "up";
    }
    return "nearest";
}

/** Back-compat bool for callers that only distinguish truncate vs not. */
function rate_matrix_rounds_down(string $customerKey): bool
{
    return rate_matrix_customer_round_mode($customerKey) === "down";
}

/**
 * Per-lane rounding decision: an explicit `round_mode` on the rate_lane row ("down"/"up"/
 * "nearest") wins over the customer default (rate_matrix_customer_round_mode()). Blank/null
 * -> use the customer default. Lets finance set the odd lane that rounds differently from the
 * rest of its customer's matrix (e.g. TDC - Dole Asia DICT floors while its Dole - PW lanes
 * round to nearest). Returns one of "down" | "up" | "nearest".
 */
function rate_matrix_lane_round_mode(string $customerKey, ?array $lane = null): string
{
    $mode = strtolower(trim((string) ($lane["round_mode"] ?? "")));
    if ($mode === "down" || $mode === "up" || $mode === "nearest") {
        return $mode;
    }
    return rate_matrix_customer_round_mode($customerKey);
}

/**
 * The charged rate for a lane, computed from the fuel-movement formula:
 *
 *   steps     = floor((fuelPrice - pumpPrice) / priceMovement)   (>= 0)
 *   movement% = steps * priceMovement / pumpPrice        (banded pump-price movement %)
 *   rate      = ROUND|ROUNDDOWN(baseRate * 40% * movement%) + baseRate
 *
 * i.e. the fuel surcharge = base * FACTOR * movement% computed and rounded on its OWN, then
 * added to the base rate. FACTOR is the company fuel-surcharge factor (rate_matrix_fuel_factor(),
 * 0.4 = 40%). $round selects how the surcharge is rounded: "nearest" (default), "down" (floor/
 * truncate) or "up" (ceil). A bool is still accepted for back-compat (true = "down"). Most
 * customers round to NEAREST (ABC Pantukan base 16,456 @40% -> 19,089); Dole Asia lanes floor
 * (base 22,139 @55% -> 27,009 not 27,010); ABC Cateel rounds UP (base 35,292 @55% -> 43,057
 * not 43,056) — see rate_matrix_customer_round_mode() / rate_matrix_lane_round_mode(). Below
 * the pump price (or when a required parameter is missing/zero) the base rate is charged
 * unchanged. Single source of truth, reused by the dashboard and Sumifru ports.
 */
function rate_matrix_formula_price(
    float $baseRate,
    ?float $pumpPrice,
    ?float $priceMovement,
    ?float $fuelPrice,
    $round = "nearest"
): float {
    if ($fuelPrice === null || $pumpPrice === null || $pumpPrice <= 0
        || $priceMovement === null || $priceMovement <= 0 || $fuelPrice <= $pumpPrice) {
        return round($baseRate); // charged rate is always a whole peso amount
    }
    $steps = (int) floor(($fuelPrice - $pumpPrice) / $priceMovement);
    if ($steps <= 0) {
        return round($baseRate);
    }
    $movementPct = ($steps * $priceMovement) / $pumpPrice;
    // Surcharge computed on its OWN, then added to the base rate. The 1e-9 epsilon guards a
    // mathematically-integer surcharge from binary-float underflow (e.g. 885.0 stored as
    // 884.9999999) so "down" doesn't drop it a peso and "up" doesn't add one.
    $mode = ($round === true) ? "down" : (($round === false) ? "nearest" : (string) $round);
    $surcharge = $baseRate * rate_matrix_fuel_factor() * $movementPct;
    if ($mode === "down") {
        $surcharge = floor($surcharge + 1e-9);
    } elseif ($mode === "up") {
        $surcharge = ceil($surcharge - 1e-9);
    } else {
        $surcharge = round($surcharge);
    }
    // The surcharge is rounded per $mode (down/up/nearest) to match each finance sheet; the
    // FINAL charged rate is then rounded to the nearest whole peso so both the Rate (now)
    // preview and the billing charge are always whole numbers (base rates may carry decimals).
    return round($surcharge + $baseRate);
}

/**
 * Canonical rate code for a raw operations location name, via the
 * location.location_matrix column (e.g. "DICT CY" -> "DICT", "PW" -> "PW").
 * Falls back to the raw name when the location/column is missing or unmapped,
 * so pricing degrades gracefully to the previous free-text behaviour.
 */
function location_matrix_code(PDO $conn, string $rawName): string
{
    static $cache = [];
    $key = strtoupper(trim($rawName));
    if ($key === "") {
        return "";
    }
    if (!array_key_exists($key, $cache)) {
        $code = null;
        try {
            $stmt = $conn->prepare(
                "SELECT location_matrix FROM location
                 WHERE UPPER(location_name) = ? AND location_matrix IS NOT NULL AND location_matrix <> ''
                 LIMIT 1"
            );
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            $code = ($val !== false && $val !== null) ? (string) $val : null;
        } catch (Throwable $e) {
            $code = null; // location table/column absent -> fall back to raw
        }
        $cache[$key] = $code ?? $rawName;
    }
    return $cache[$key];
}

/**
 * Find the lane row for a trip. Only rows effective on/before $tripDate are
 * considered (blank effective_date = always applies); when a route has several
 * dated rows the newest effective one wins. Matches on customer + (dcode equality,
 * or destination ILIKE). The incoming destination is first canonicalised through
 * location.location_matrix so raw entry variants map to the lane's dcode. When a
 * customer has exactly one active route, that route is used regardless. Returns the
 * lane row or null.
 */
function resolve_lane(PDO $conn, string $customerKey, string $dcode, string $destination, string $tripDate = ""): ?array
{
    // Load the customer's active lanes ONCE per request (small table). Previously this
    // queried rate_lane on every call, which is a DB round-trip PER TRIP — the main cause
    // of preview/generate timeouts (HTTP 504) on large date ranges. The date-window filter,
    // route dedup and dcode/destination matching are all done in PHP below.
    static $laneCache = [];
    if (!array_key_exists($customerKey, $laneCache)) {
        $stmt = $conn->prepare("SELECT * FROM rate_lane WHERE customer_key = ? AND active = TRUE ORDER BY sort_order, lane_id");
        $stmt->execute([$customerKey]);
        $laneCache[$customerKey] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $allLanes = $laneCache[$customerKey];
    if (!$allLanes) {
        return null;
    }

    $date = substr(trim($tripDate), 0, 10);
    $hasDate = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    $rows = [];
    foreach ($allLanes as $lane) {
        if ($hasDate) {
            // Row's [effective_from, effective_to] window must contain the trip date (blank = open).
            $ef = substr(trim((string) ($lane["effective_from"] ?? "")), 0, 10);
            $et = substr(trim((string) ($lane["effective_to"] ?? "")), 0, 10);
            if (($ef !== "" && $ef > $date) || ($et !== "" && $et < $date)) {
                continue;
            }
        }
        $rows[] = $lane;
    }
    if (!$rows) {
        return null;
    }

    // Collapse to the newest-starting row per route so a re-dated rate supersedes the older
    // one for the same lane (rows sharing the route identity are effective-dated versions).
    $byRoute = [];
    foreach ($rows as $lane) {
        // Lane identity = Origin -> Packing House -> Destination (+ dcode/segment).
        $routeKey = strtoupper(implode("|", [
            trim((string) ($lane["origin"] ?? "")),
            trim((string) ($lane["packing_house"] ?? "")),
            trim((string) ($lane["destination"] ?? "")),
            trim((string) ($lane["dcode"] ?? "")),
            trim((string) ($lane["segment"] ?? "")),
        ]));
        $eff = trim((string) ($lane["effective_from"] ?? ""));
        if (!isset($byRoute[$routeKey]) || $eff > trim((string) ($byRoute[$routeKey]["effective_from"] ?? ""))) {
            $byRoute[$routeKey] = $lane;
        }
    }
    $rows = array_values($byRoute);
    if (count($rows) === 1) {
        return $rows[0];
    }

    // Canonicalise the trip's raw destination to its location_matrix code.
    $dcode = strtoupper(trim(location_matrix_code($conn, $dcode)));
    $destination = strtoupper(trim(location_matrix_code($conn, $destination)));

    foreach ($rows as $lane) {
        $laneDcode = strtoupper(trim((string) ($lane["dcode"] ?? "")));
        if ($dcode !== "" && $laneDcode !== "" && $laneDcode === $dcode) {
            return $lane;
        }
    }
    foreach ($rows as $lane) {
        $laneDest = strtoupper(trim((string) ($lane["destination"] ?? "")));
        if ($laneDest !== "" && $destination !== "" &&
            (strpos($destination, $laneDest) !== false || strpos($laneDest, $destination) !== false)) {
            return $lane;
        }
    }
    return null;
}

/**
 * Fuel-price bands for a lane (ordered), or [] when the lane has none. Each band:
 *   ['fuel_from' => ?float, 'fuel_to' => ?float, 'rate' => float]
 * Cached per request (a preview prices many trips off the same lanes).
 */
function rate_lane_bands(PDO $conn, int $laneId): array
{
    static $cache = [];
    if (array_key_exists($laneId, $cache)) {
        return $cache[$laneId];
    }
    $bands = [];
    try {
        $stmt = $conn->prepare(
            "SELECT fuel_from, fuel_to, rate FROM rate_lane_band
             WHERE lane_id = ? ORDER BY sort_order, band_id"
        );
        $stmt->execute([$laneId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $bands[] = [
                "fuel_from" => fuel_rate_num($r["fuel_from"] ?? null),
                "fuel_to" => fuel_rate_num($r["fuel_to"] ?? null),
                "rate" => (float) $r["rate"],
            ];
        }
    } catch (Throwable $e) {
        $bands = []; // table absent -> no bands
    }
    return $cache[$laneId] = $bands;
}

/**
 * Stepped/banded rate for a lane at a diesel price, or null when the lane has NO
 * bands (so the caller falls back to the 0.4x formula). When bands exist:
 *   - a band whose [fuel_from, fuel_to] range contains the fuel price wins (its flat
 *     rate is charged; null bound = open on that side);
 *   - if the fuel price is below every band, the base rate is charged (flat until the
 *     first threshold). Give the top band a blank "to" so high fuel is always covered.
 */
function rate_lane_banded_price(PDO $conn, int $laneId, float $fuelPrice, float $baseRate): ?float
{
    $bands = rate_lane_bands($conn, $laneId);
    if (!$bands) {
        return null;
    }
    foreach ($bands as $b) {
        $from = $b["fuel_from"];
        $to = $b["fuel_to"];
        if (($from === null || $fuelPrice >= $from) && ($to === null || $fuelPrice <= $to)) {
            return $b["rate"];
        }
    }
    return $baseRate; // below the lowest band -> base rate
}

/**
 * Resolve the charged rate for a trip. Returns a detail array so callers can
 * surface how the price was derived:
 *   [ 'rate', 'base_rate', 'fuel_price', 'movement_pct', 'lane_id',
 *     'source', 'matched' (bool) ]
 * where 'movement_pct' is the effective total movement over base for this trip
 * (e.g. 8.0 when the rate is 8% above base).
 */
function resolve_lane_rate(
    PDO $conn,
    string $customerKey,
    string $dcode,
    string $destination,
    string $tripDate,
    string $fuelSource = ""
): array {
    // Within one request the same lane+date+source resolves to the same rate. A preview
    // prices every trip once for the detail rows and again for the rate summary, and many
    // trips share a lane/date — so memoize to avoid a per-trip DB round-trip each time.
    static $cache = [];
    $cacheKey = $customerKey . "|" . $dcode . "|" . $destination . "|" . $tripDate . "|" . $fuelSource;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $result = [
        "rate" => 0.0,
        "base_rate" => 0.0,
        "fuel_price" => null,
        "movement_pct" => null,
        "lane_id" => null,
        "source" => $fuelSource,
        "matched" => false,
    ];

    $lane = resolve_lane($conn, $customerKey, $dcode, $destination, $tripDate);
    if (!$lane) {
        return $cache[$cacheKey] = $result;
    }
    $baseRate = (float) $lane["base_rate"];
    $result["lane_id"] = (int) $lane["lane_id"];
    $result["base_rate"] = $baseRate;
    $result["rate"] = round($baseRate); // charged rate is always whole
    $result["matched"] = true;

    // Fuel escalation applies ONLY when finance has pinned a Monthly Avg Fuel for the
    // lane's effective period (user rule 2026-08-18). A blank/zero monthly average charges
    // the BASE rate — no live-DOE fallback — for both formula and banded lanes. This makes
    // the pinned fuel price the single, deliberate driver of every escalated rate.
    $monthlyAverage = fuel_rate_num($lane["monthly_fuel_average"] ?? null);
    if ($monthlyAverage === null || $monthlyAverage <= 0) {
        return $cache[$cacheKey] = $result; // no pinned fuel -> base rate
    }
    $fuelPrice = $monthlyAverage;
    $result["fuel_price"] = $fuelPrice;

    // Banded (stepped flat) lanes win over the formula; a lane with no bands prices
    // via the 0.4x fuel-movement formula as before.
    $banded = rate_lane_banded_price($conn, (int) $lane["lane_id"], $fuelPrice, $baseRate);
    if ($banded !== null) {
        $result["rate"] = round($banded); // charged rate is always whole
        $result["movement_pct"] = $baseRate > 0 ? round(($banded / $baseRate - 1) * 100, 4) : null;
        return $cache[$cacheKey] = $result;
    }

    $rate = rate_matrix_formula_price(
        $baseRate,
        fuel_rate_num($lane["pump_price"] ?? null),
        fuel_rate_num($lane["price_movement"] ?? null),
        $fuelPrice,
        rate_matrix_lane_round_mode($customerKey, $lane)
    );
    $result["rate"] = $rate;
    // Effective total movement over base for this trip (e.g. 8.0 = 8% above base).
    $result["movement_pct"] = $baseRate > 0 ? round(($rate / $baseRate - 1) * 100, 4) : null;

    return $cache[$cacheKey] = $result;
}
