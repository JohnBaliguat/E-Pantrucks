<?php

require_once __DIR__ . "/fuel_rate_engine.php";
require_once __DIR__ . "/box_banana_customers.php";
require_once __DIR__ . "/build_box_banana_billing.php";
require_once __DIR__ . "/dict_shuttling.php";
require_once __DIR__ . "/abc_kds.php";

/**
 * Dashboard revenue, priced through the SAME fuel rate matrix the per-customer
 * billing statements use (see build_box_banana_billing / fuel_rate_engine).
 *
 * For every configured billing customer that has an active rate matrix
 * (rate_lane rows), we select its trips for the range (box_banana_fetch_entries:
 * entry_type / segment / customer_match + trip date) and price each trip against
 * the matrix — the lane base rate stepped up by the fuel band in effect on the
 * trip date, for the customer's fuel source.
 *
 * PERFORMANCE: the whole rate matrix (lanes + formula params), the fuel periods and
 * the location_matrix map are loaded ONCE (request-memoized) and every trip is
 * then priced in memory. This replaces a per-trip storm of round-trips to the
 * remote DB — the dashboard polls every 30s, and per-trip resolution took ~40s.
 *
 * Trips that don't belong to any matrix-configured customer, or whose customer
 * has no active lane, or that can't be matched to a lane, are UNPRICED (they
 * contribute nothing and are not counted as billed trips). Each entry_id is
 * counted once (first configured customer that prices it wins).
 *
 * Returns:
 *   [ 'revenue'=>float, 'trips'=>float,
 *     'by_customer'=>[ ['key','label','matrix_key','trips','revenue','avg_rate'], ... ],
 *     'by_day'=>[ 'Y-m-d'=>float ] ]
 */
function dashboard_matrix_revenue(PDO $conn, string $dateFrom, string $dateTo): array
{
    $result = ["revenue" => 0.0, "trips" => 0.0, "by_customer" => [], "by_day" => []];

    if (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ||
        $dateFrom > $dateTo
    ) {
        return $result;
    }

    $lookup = dashboard_matrix_lookup($conn);
    $allTrips = dashboard_fetch_trips($conn, $dateFrom, $dateTo);

    $seenEntry = [];       // entry_id => true (global dedupe)
    $seenMatrixKey = [];   // matrix_key => true (Sumifru appears once)

    foreach (box_banana_customers_config() as $key => $customer) {
        $matrixKey = box_banana_matrix_key($key, $customer);
        if ($matrixKey === "" || isset($seenMatrixKey[$matrixKey])) {
            continue;
        }
        // Only customers with an active rate matrix can be matrix-priced.
        if (empty($lookup["lanes"][$matrixKey])) {
            continue;
        }
        $seenMatrixKey[$matrixKey] = true;

        $fuelSource = (string) ($customer["fuel_source"] ?? "");

        // Same selection as box_banana_fetch_entries, but filtered in memory from
        // the single bulk fetch (entry_type / segment / customer_match).
        $entries = array_filter($allTrips, static fn(array $t) => dashboard_customer_matches($t, $customer));

        $custRevenue = 0.0;
        $custTrips = 0.0;

        foreach ($entries as $entry) {
            $entryId = (int) ($entry["entry_id"] ?? 0);
            if ($entryId <= 0 || isset($seenEntry[$entryId])) {
                continue;
            }

            $tripDate = substr(trim((string) ($entry["trip_date"] ?? "")), 0, 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tripDate)) {
                continue;
            }

            $dcode = box_banana_first_non_empty($entry["destination"] ?? "", $entry["delivered_to"] ?? "");
            $priced = dashboard_price_trip($lookup, $matrixKey, $dcode, $tripDate, $fuelSource);
            if (!$priced["matched"]) {
                continue; // unpriced -> ₱0, not counted
            }

            $rate = (float) $priced["rate"];
            $trips = is_numeric($entry["total_trips"] ?? null) && (float) $entry["total_trips"] > 0
                ? (float) $entry["total_trips"]
                : 1.0;
            $amount = $rate * $trips;

            $seenEntry[$entryId] = true;
            $custRevenue += $amount;
            $custTrips += $trips;
            $result["by_day"][$tripDate] = ($result["by_day"][$tripDate] ?? 0.0) + $amount;
        }

        if ($custTrips <= 0) {
            continue;
        }

        $result["revenue"] += $custRevenue;
        $result["trips"] += $custTrips;
        $result["by_customer"][] = [
            "key" => $key,
            "label" => (string) ($customer["label"] ?? $key),
            "matrix_key" => $matrixKey,
            "trips" => round($custTrips, 2),
            "revenue" => round($custRevenue, 2),
            "avg_rate" => $custTrips > 0 ? round($custRevenue / $custTrips, 2) : 0.0,
        ];
    }

    usort($result["by_customer"], static fn($a, $b) => $b["revenue"] <=> $a["revenue"]);
    $result["revenue"] = round($result["revenue"], 2);
    $result["trips"] = round($result["trips"], 2);

    return $result;
}

/**
 * Billing PERFORMANCE for a range across ALL SAP pipelines — box-banana matrix +
 * Sumifru (fuel matrix), DICT Van Shuttling (fixed lane prices), and ABC KDs (KDs
 * matrix). Each billable trip is split by whether it has been BILLED (its entry_id
 * is in a non-deleted SAP invoice). Answers "of the billable transactions in this
 * period, how many / how much peso are billed vs still pending".
 *
 * Trip counts are PER TRANSACTION (one per operations record); revenue uses each
 * pipeline's real billed amount (rate × billed quantity / lane price). KDs trips
 * whose matrix is unloaded still count toward coverage at ₱0 (billable, unpriced).
 *
 * Returns:
 *   [ 'total_trips','billed_trips','unbilled_trips',
 *     'billed_revenue','unbilled_revenue','coverage_pct',
 *     'by_customer'=>[ ['key','label','matrix_key','total_trips','billed_trips',
 *                       'unbilled_trips','billed_revenue','unbilled_revenue','coverage_pct'], ... ],
 *     'by_day'=>[ 'Y-m-d'=>['billed'=>float,'unbilled'=>float] ] ]
 */
function dashboard_billing_performance(PDO $conn, string $dateFrom, string $dateTo): array
{
    $out = [
        "total_trips" => 0.0, "billed_trips" => 0.0, "unbilled_trips" => 0.0,
        "billed_revenue" => 0.0, "unbilled_revenue" => 0.0, "coverage_pct" => 0.0,
        "by_customer" => [], "by_day" => [],
    ];
    if (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ||
        $dateFrom > $dateTo
    ) {
        return $out;
    }

    // Once-only billed entry_ids (any non-deleted SAP invoice).
    $billed = [];
    try {
        foreach ($conn->query(
            "SELECT DISTINCT e.entry_id
             FROM billing_invoice_entries e
             JOIN billing_invoices i ON i.invoice_id = e.invoice_id
             WHERE i.status <> 'deleted'"
        )->fetchAll(PDO::FETCH_COLUMN) as $eid) {
            $billed[(int) $eid] = true;
        }
    } catch (Throwable $e) {
        $billed = [];
    }

    $lookup = dashboard_matrix_lookup($conn);
    $allTrips = dashboard_fetch_trips($conn, $dateFrom, $dateTo);

    $seenEntry = [];
    $seenMatrixKey = [];
    $byDay = [];

    foreach (box_banana_customers_config() as $key => $customer) {
        $matrixKey = box_banana_matrix_key($key, $customer);
        if ($matrixKey === "" || isset($seenMatrixKey[$matrixKey]) || empty($lookup["lanes"][$matrixKey])) {
            continue;
        }
        $seenMatrixKey[$matrixKey] = true;
        $fuelSource = (string) ($customer["fuel_source"] ?? "");
        $entries = array_filter($allTrips, static fn(array $t) => dashboard_customer_matches($t, $customer));

        $c = ["billed_trips" => 0.0, "unbilled_trips" => 0.0, "billed_revenue" => 0.0, "unbilled_revenue" => 0.0];
        foreach ($entries as $entry) {
            $entryId = (int) ($entry["entry_id"] ?? 0);
            if ($entryId <= 0 || isset($seenEntry[$entryId])) {
                continue;
            }
            $tripDate = substr(trim((string) ($entry["trip_date"] ?? "")), 0, 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tripDate)) {
                continue;
            }
            $dcode = box_banana_first_non_empty($entry["destination"] ?? "", $entry["delivered_to"] ?? "");
            $priced = dashboard_price_trip($lookup, $matrixKey, $dcode, $tripDate, $fuelSource);
            if (!$priced["matched"]) {
                continue;
            }
            $rate = (float) $priced["rate"];
            // COUNT is per transaction (one record); revenue uses the billed quantity.
            $qty = is_numeric($entry["total_trips"] ?? null) && (float) $entry["total_trips"] > 0
                ? (float) $entry["total_trips"] : 1.0;
            $amount = $rate * $qty;
            $seenEntry[$entryId] = true;

            $isBilled = isset($billed[$entryId]);
            if (!isset($byDay[$tripDate])) {
                $byDay[$tripDate] = ["billed" => 0.0, "unbilled" => 0.0];
            }
            if ($isBilled) {
                $c["billed_trips"] += 1;
                $c["billed_revenue"] += $amount;
                $byDay[$tripDate]["billed"] += $amount;
            } else {
                $c["unbilled_trips"] += 1;
                $c["unbilled_revenue"] += $amount;
                $byDay[$tripDate]["unbilled"] += $amount;
            }
        }

        $total = $c["billed_trips"] + $c["unbilled_trips"];
        if ($total <= 0) {
            continue;
        }
        $out["by_customer"][] = [
            "key" => $key,
            "label" => (string) ($customer["label"] ?? $key),
            "matrix_key" => $matrixKey,
            "total_trips" => round($total, 2),
            "billed_trips" => round($c["billed_trips"], 2),
            "unbilled_trips" => round($c["unbilled_trips"], 2),
            "billed_revenue" => round($c["billed_revenue"], 2),
            "unbilled_revenue" => round($c["unbilled_revenue"], 2),
            "coverage_pct" => round($c["billed_trips"] / $total * 100, 1),
        ];
        $out["total_trips"] += $total;
        $out["billed_trips"] += $c["billed_trips"];
        $out["unbilled_trips"] += $c["unbilled_trips"];
        $out["billed_revenue"] += $c["billed_revenue"];
        $out["unbilled_revenue"] += $c["unbilled_revenue"];
    }

    // Helper: fold one customer's split into the by_customer list + running totals.
    $addCustomer = static function (array $meta, array $c) use (&$out) {
        $total = $c["billed_trips"] + $c["unbilled_trips"];
        if ($total <= 0) {
            return;
        }
        $out["by_customer"][] = array_merge($meta, [
            "total_trips" => round($total, 2),
            "billed_trips" => round($c["billed_trips"], 2),
            "unbilled_trips" => round($c["unbilled_trips"], 2),
            "billed_revenue" => round($c["billed_revenue"], 2),
            "unbilled_revenue" => round($c["unbilled_revenue"], 2),
            "coverage_pct" => round($c["billed_trips"] / $total * 100, 1),
        ]);
        $out["total_trips"] += $total;
        $out["billed_trips"] += $c["billed_trips"];
        $out["unbilled_trips"] += $c["unbilled_trips"];
        $out["billed_revenue"] += $c["billed_revenue"];
        $out["unbilled_revenue"] += $c["unbilled_revenue"];
    };
    $blank = ["billed_trips" => 0.0, "unbilled_trips" => 0.0, "billed_revenue" => 0.0, "unbilled_revenue" => 0.0];
    $tally = static function (array &$c, array &$byDay, bool $isBilled, float $trips, float $amount, string $tripDate) {
        if ($isBilled) {
            $c["billed_trips"] += $trips;
            $c["billed_revenue"] += $amount;
        } else {
            $c["unbilled_trips"] += $trips;
            $c["unbilled_revenue"] += $amount;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tripDate)) {
            if (!isset($byDay[$tripDate])) {
                $byDay[$tripDate] = ["billed" => 0.0, "unbilled" => 0.0];
            }
            $byDay[$tripDate][$isBilled ? "billed" : "unbilled"] += $amount;
        }
    };

    // ---- DICT Van Shuttling: fixed per-lane prices (no rate matrix) ----
    try {
        $dictCfg = dict_shuttling_config();
        $dictTrips = dict_shuttling_fetch($conn, $dateFrom, $dateTo, []);
        $c = $blank;
        foreach ($dictTrips as $e) {
            $eid = (int) ($e["entry_id"] ?? 0);
            if ($eid <= 0) {
                continue;
            }
            $laneKey = dict_shuttling_lane($e);
            if ($laneKey === null || !isset($dictCfg["lanes"][$laneKey])) {
                continue; // can't resolve a lane -> not billable here
            }
            $qty = dict_shuttling_trips($e);
            if ($qty <= 0) {
                continue;
            }
            $amount = $qty * (float) $dictCfg["lanes"][$laneKey]["price"];
            $tripDate = substr(trim((string) ($e["trip_date"] ?? "")), 0, 10);
            // Count 1 transaction; revenue is qty × lane price.
            $tally($c, $byDay, isset($billed[$eid]), 1.0, $amount, $tripDate);
        }
        $addCustomer([
            "key" => (string) ($dictCfg["key"] ?? "dict_van_shuttling"),
            "label" => (string) ($dictCfg["label"] ?? "DICT Van Shuttling"),
            "matrix_key" => "dict (lane prices)",
        ], $c);
    } catch (Throwable $e) {
        // DICT config/data missing -> just skip this pipeline.
    }

    // ---- ABC KDs: per-customer, priced via the KDs rate matrix (in-memory lookup) ----
    try {
        foreach (array_keys(abc_kds_customers()) as $kdsKey) {
            $kds = abc_kds_customer($kdsKey);
            if (!is_array($kds)) {
                continue;
            }
            $matrixKey = (string) ($kds["matrix_key"] ?? "");
            $fuelSource = (string) ($kds["fuel_source"] ?? "");
            $entries = abc_kds_fetch($conn, $kds, $dateFrom, $dateTo, []);
            $c = $blank;
            foreach ($entries as $e) {
                $eid = (int) ($e["entry_id"] ?? 0);
                if ($eid <= 0) {
                    continue;
                }
                $tripDate = substr(trim((string) ($e["trip_date"] ?? "")), 0, 10);
                // 1 trip per KD record; rate from the KDs matrix (0 if unloaded — the
                // trip is still billable, so it counts toward coverage).
                $priced = dashboard_price_trip($lookup, $matrixKey, "", $tripDate, $fuelSource);
                $amount = !empty($priced["matched"]) ? (float) $priced["rate"] : 0.0;
                $tally($c, $byDay, isset($billed[$eid]), 1.0, $amount, $tripDate);
            }
            $addCustomer([
                "key" => $kdsKey,
                "label" => (string) ($kds["label"] ?? $kdsKey),
                "matrix_key" => $matrixKey,
            ], $c);
        }
    } catch (Throwable $e) {
        // KDs config/data missing -> skip.
    }

    // Most pending revenue first — that's the actionable backlog.
    usort($out["by_customer"], static fn($a, $b) => $b["unbilled_revenue"] <=> $a["unbilled_revenue"]);
    $out["coverage_pct"] = $out["total_trips"] > 0 ? round($out["billed_trips"] / $out["total_trips"] * 100, 1) : 0.0;
    foreach (["total_trips", "billed_trips", "unbilled_trips", "billed_revenue", "unbilled_revenue"] as $k) {
        $out[$k] = round($out[$k], 2);
    }
    ksort($byDay);
    $out["by_day"] = $byDay;

    return $out;
}

/**
 * All RV trips in the range, fetched once (request-memoized per range). Carries
 * the fields needed to match a trip to a customer and to price it. The trip date
 * mirrors box_banana_fetch_entries' COALESCE so selection is identical.
 */
function dashboard_fetch_trips(PDO $conn, string $from, string $to): array
{
    static $cache = [];
    $ck = $from . "|" . $to;
    if (isset($cache[$ck])) {
        return $cache[$ck];
    }
    // Billing trip date = Trip Receipt (waybill) date, falling back to DELIVERY DEPARTURE date
    // when waybill_date is blank (matches box_banana_fetch_entries; user rule 2026-08-12 for
    // Sumifru + all Box Banana). Keeps the dashboard in step with the generated invoices/previews.
    $tripDateExpr = "COALESCE(waybill_date, loaded_van_delivery_departure_date)";
    $stmt = $conn->prepare(
        "SELECT entry_id, entry_type, segment, customer_ph, shipper, operations_ph,
                destination, delivered_to, total_trips, ($tripDateExpr)::text AS trip_date
         FROM operations
         WHERE entry_type = 'RV ENTRY' AND $tripDateExpr BETWEEN ? AND ?
         ORDER BY $tripDateExpr ASC, entry_id ASC"
    );
    $stmt->execute([$from, $to]);
    return $cache[$ck] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** In-memory equivalent of box_banana_fetch_entries' WHERE (entry_type/segment/customer_match). */
function dashboard_customer_matches(array $trip, array $customer): bool
{
    $entryType = trim((string) ($customer["entry_type"] ?? ""));
    if ($entryType !== "" && (string) ($trip["entry_type"] ?? "") !== $entryType) {
        return false;
    }
    $segment = trim((string) ($customer["segment"] ?? ""));
    if ($segment !== "" && (string) ($trip["segment"] ?? "") !== $segment) {
        return false;
    }
    $match = trim((string) ($customer["customer_match"] ?? ""));
    if ($match !== "") {
        foreach (["customer_ph", "shipper", "operations_ph"] as $field) {
            if (stripos((string) ($trip[$field] ?? ""), $match) !== false) {
                return true;
            }
        }
        return false;
    }
    return true;
}

/**
 * Load the whole rate matrix + fuel periods + location map once per request.
 * Shapes:
 *   lanes[customer_key] = [ ['lane_id','dcode','destination','segment','base_rate',
 *       'effective_from','effective_to','pump_price','price_movement'], ... ] (active only)
 *   fuel = [ fuel_price rows (assoc) ] with effective_from set
 *   loc[UPPER(location_name)] = location_matrix
 */
function dashboard_matrix_lookup(PDO $conn): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    // Per-lane fuel bands (stepped rates), keyed by lane_id, so the dashboard prices banded
    // lanes the same way billing does.
    $bandsByLane = [];
    try {
        foreach ($conn->query(
            "SELECT lane_id, fuel_from, fuel_to, rate FROM rate_lane_band ORDER BY lane_id, sort_order, band_id"
        )->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $bandsByLane[(int) $b["lane_id"]][] = [
                "fuel_from" => fuel_rate_num($b["fuel_from"] ?? null),
                "fuel_to" => fuel_rate_num($b["fuel_to"] ?? null),
                "rate" => (float) $b["rate"],
            ];
        }
    } catch (Throwable $e) {
        $bandsByLane = [];
    }

    $lanes = [];
    foreach ($conn->query(
        "SELECT lane_id, customer_key, dcode, destination, origin, packing_house, segment, base_rate, sort_order,
                effective_from, effective_to, pump_price, price_movement, monthly_fuel_average, round_mode
         FROM rate_lane WHERE active = TRUE ORDER BY customer_key, sort_order, lane_id"
    )->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $lanes[$row["customer_key"]][] = [
            "lane_id" => (int) $row["lane_id"],
            "dcode" => (string) ($row["dcode"] ?? ""),
            "destination" => (string) ($row["destination"] ?? ""),
            "origin" => (string) ($row["origin"] ?? ""),
            "packing_house" => (string) ($row["packing_house"] ?? ""),
            "segment" => (string) ($row["segment"] ?? ""),
            "base_rate" => (float) $row["base_rate"],
            "effective_from" => trim((string) ($row["effective_from"] ?? "")),
            "effective_to" => trim((string) ($row["effective_to"] ?? "")),
            "pump_price" => fuel_rate_num($row["pump_price"] ?? null),
            "price_movement" => fuel_rate_num($row["price_movement"] ?? null),
            // Per-lane rounding override (mirrors billing via rate_matrix_lane_round_mode).
            "round_mode" => (string) ($row["round_mode"] ?? ""),
            // Escalation driver: the dashboard mirrors billing — a lane escalates ONLY when
            // its Monthly Avg Fuel is pinned; blank charges the base rate.
            "monthly_fuel_average" => fuel_rate_num($row["monthly_fuel_average"] ?? null),
            "bands" => $bandsByLane[(int) $row["lane_id"]] ?? [],
        ];
    }

    $fuel = [];
    try {
        // Bill from the Updated Date (falls back to From) — mirror fuel_price_for_date.
        $fuel = $conn->query(
            "SELECT * FROM fuel_price
             WHERE COALESCE(updated_date, effective_from) IS NOT NULL
             ORDER BY COALESCE(updated_date, effective_from) DESC, id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $fuel = [];
    }

    $loc = [];
    try {
        foreach ($conn->query(
            "SELECT location_name, location_matrix FROM location
             WHERE location_matrix IS NOT NULL AND TRIM(location_matrix) <> ''"
        )->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $loc[strtoupper(trim((string) $row["location_name"]))] = (string) $row["location_matrix"];
        }
    } catch (Throwable $e) {
        $loc = [];
    }

    return $cache = ["lanes" => $lanes, "fuel" => $fuel, "loc" => $loc];
}

/** Digits only (truck/PM display), like billing_digits without the dependency. */
function dashboard_digits($value): string
{
    return preg_replace('/\D+/', "", (string) $value) ?? "";
}

/** Set of once-only billed entry_ids (non-deleted SAP invoices). */
function dashboard_billed_entry_ids(PDO $conn): array
{
    $billed = [];
    try {
        foreach ($conn->query(
            "SELECT DISTINCT e.entry_id FROM billing_invoice_entries e
             JOIN billing_invoices i ON i.invoice_id = e.invoice_id
             WHERE i.status <> 'deleted'"
        )->fetchAll(PDO::FETCH_COLUMN) as $eid) {
            $billed[(int) $eid] = true;
        }
    } catch (Throwable $e) {
        $billed = [];
    }
    return $billed;
}

/**
 * Per-trip detail for ONE billing customer in a range, each row tagged billed/unbilled.
 * Powers the dashboard drill-down modal. Handles all pipelines (box-banana matrix +
 * Sumifru, DICT lane prices, ABC KDs matrix). Selection/pricing mirror
 * dashboard_billing_performance so the modal rows reconcile with the coverage counts.
 *
 * Returns ['label','rows'=>[{entry_id,date,reference,truck,destination,amount,billed}],
 *          'summary'=>{total,billed,unbilled,billed_amount,unbilled_amount}].
 */
function dashboard_customer_trip_detail(PDO $conn, string $customerKey, string $dateFrom, string $dateTo): array
{
    $out = ["label" => $customerKey, "rows" => [], "summary" => [
        "total" => 0, "billed" => 0, "unbilled" => 0, "billed_amount" => 0.0, "unbilled_amount" => 0.0,
    ]];
    if (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) || $dateFrom > $dateTo
    ) {
        return $out;
    }

    $billed = dashboard_billed_entry_ids($conn);
    $rows = [];

    $push = static function (array &$rows, int $eid, string $date, string $ref, string $truck, string $dest, float $amount, array $billed) {
        $rows[] = [
            "entry_id" => $eid,
            "date" => $date,
            "reference" => $ref,
            "truck" => $truck,
            "destination" => $dest,
            "amount" => round($amount, 2),
            "billed" => isset($billed[$eid]),
        ];
    };

    $boxConfig = box_banana_customers_config();

    if (isset($boxConfig[$customerKey])) {
        // ---- Box Bananas matrix + Sumifru ----
        $customer = box_banana_customer($customerKey);
        $out["label"] = (string) ($customer["label"] ?? $customerKey);
        $matrixKey = box_banana_matrix_key($customerKey, $customer);
        $fuelSource = (string) ($customer["fuel_source"] ?? "");
        $lookup = dashboard_matrix_lookup($conn);
        $entries = box_banana_fetch_entries($conn, $customer, $dateFrom, $dateTo, []);
        foreach ($entries as $e) {
            $eid = (int) ($e["entry_id"] ?? 0);
            if ($eid <= 0) {
                continue;
            }
            $tripDate = substr(trim((string) ($e["trip_date"] ?? "")), 0, 10);
            $dcode = box_banana_first_non_empty($e["destination"] ?? "", $e["delivered_to"] ?? "");
            $priced = dashboard_price_trip($lookup, $matrixKey, $dcode, $tripDate, $fuelSource);
            if (empty($priced["matched"])) {
                continue;
            }
            $qty = is_numeric($e["total_trips"] ?? null) && (float) $e["total_trips"] > 0 ? (float) $e["total_trips"] : 1.0;
            $truck = dashboard_digits(box_banana_first_non_empty($e["prime_mover"] ?? "", $e["delivered_by_prime_mover"] ?? "", $e["truck"] ?? ""));
            $push($rows, $eid, $tripDate, (string) ($e["waybill"] ?? ""), $truck, $dcode, (float) $priced["rate"] * $qty, $billed);
        }
    } elseif (function_exists("dict_shuttling_config") && $customerKey === (dict_shuttling_config()["key"] ?? "")) {
        // ---- DICT Van Shuttling ----
        $dictCfg = dict_shuttling_config();
        $out["label"] = (string) ($dictCfg["label"] ?? $customerKey);
        foreach (dict_shuttling_fetch($conn, $dateFrom, $dateTo, []) as $e) {
            $eid = (int) ($e["entry_id"] ?? 0);
            if ($eid <= 0) {
                continue;
            }
            $laneKey = dict_shuttling_lane($e);
            if ($laneKey === null || !isset($dictCfg["lanes"][$laneKey])) {
                continue;
            }
            $qty = dict_shuttling_trips($e);
            if ($qty <= 0) {
                continue;
            }
            $lane = $dictCfg["lanes"][$laneKey];
            $push($rows, $eid, substr(trim((string) ($e["trip_date"] ?? "")), 0, 10),
                (string) ($e["waybill"] ?? ""), dashboard_digits($e["truck"] ?? ""),
                (string) ($lane["label"] ?? ""), $qty * (float) $lane["price"], $billed);
        }
    } elseif (function_exists("abc_kds_customer") && abc_kds_customer($customerKey) !== null) {
        // ---- ABC KDs ----
        $kds = abc_kds_customer($customerKey);
        $out["label"] = (string) ($kds["label"] ?? $customerKey);
        $matrixKey = (string) ($kds["matrix_key"] ?? "");
        $fuelSource = (string) ($kds["fuel_source"] ?? "");
        $lookup = dashboard_matrix_lookup($conn);
        foreach (abc_kds_fetch($conn, $kds, $dateFrom, $dateTo, []) as $e) {
            $eid = (int) ($e["entry_id"] ?? 0);
            if ($eid <= 0) {
                continue;
            }
            $tripDate = substr(trim((string) ($e["trip_date"] ?? "")), 0, 10);
            $priced = dashboard_price_trip($lookup, $matrixKey, "", $tripDate, $fuelSource);
            $amount = !empty($priced["matched"]) ? (float) $priced["rate"] : 0.0;
            $push($rows, $eid, $tripDate, (string) ($e["waybill"] ?? ""), dashboard_digits($e["truck"] ?? ""),
                (string) ($e["ph"] ?? ""), $amount, $billed);
        }
    } else {
        return $out; // unknown customer
    }

    // Newest first.
    usort($rows, static fn($a, $b) => strcmp($b["date"], $a["date"]));

    $s = &$out["summary"];
    foreach ($rows as $r) {
        $s["total"]++;
        if ($r["billed"]) {
            $s["billed"]++;
            $s["billed_amount"] += $r["amount"];
        } else {
            $s["unbilled"]++;
            $s["unbilled_amount"] += $r["amount"];
        }
    }
    $s["billed_amount"] = round($s["billed_amount"], 2);
    $s["unbilled_amount"] = round($s["unbilled_amount"], 2);
    $out["rows"] = $rows;
    return $out;
}

/** In-memory equivalent of resolve_lane_rate(): ['rate'=>float,'matched'=>bool]. */
function dashboard_price_trip(array $lookup, string $customerKey, string $rawDest, string $tripDate, string $fuelSource): array
{
    $lanes = $lookup["lanes"][$customerKey] ?? [];
    if (empty($lanes)) {
        return ["rate" => 0.0, "matched" => false];
    }

    $lane = dashboard_resolve_lane($lanes, $rawDest, $lookup["loc"], $tripDate);
    if ($lane === null) {
        return ["rate" => 0.0, "matched" => false];
    }

    $baseRate = (float) $lane["base_rate"];
    $rate = $baseRate;

    // Mirror resolve_lane_rate: escalation applies ONLY when the lane's Monthly Avg Fuel is
    // pinned. Blank/zero -> base rate (no live-DOE fallback). When pinned, banded lanes use
    // their band, otherwise the 0.4x formula — both off the pinned fuel price.
    $fuelPrice = $lane["monthly_fuel_average"] ?? null;
    if ($fuelPrice !== null && $fuelPrice > 0) {
        $banded = dashboard_banded_price($lane["bands"] ?? [], (float) $fuelPrice, $baseRate);
        $rate = $banded !== null
            ? $banded
            : rate_matrix_formula_price($baseRate, $lane["pump_price"] ?? null, $lane["price_movement"] ?? null, (float) $fuelPrice, rate_matrix_lane_round_mode($customerKey, $lane));
    }

    return ["rate" => $rate, "matched" => true];
}

/** In-memory mirror of rate_lane_banded_price() for the dashboard lookup. */
function dashboard_banded_price(array $bands, float $fuelPrice, float $baseRate): ?float
{
    if (empty($bands)) {
        return null;
    }
    foreach ($bands as $b) {
        $from = $b["fuel_from"];
        $to = $b["fuel_to"];
        if (($from === null || $fuelPrice >= $from) && ($to === null || $fuelPrice <= $to)) {
            return (float) $b["rate"];
        }
    }
    return $baseRate;
}

/**
 * In-memory resolve_lane(): only rows effective on/before $tripDate, newest per route wins;
 * then single route wins, else dcode, else destination substring.
 */
function dashboard_resolve_lane(array $lanes, string $rawDest, array $locMap, string $tripDate = ""): ?array
{
    $date = substr(trim($tripDate), 0, 10);
    $dateValid = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);

    // Filter to rows whose [from,to] window covers the date, then collapse to the
    // newest-starting per route.
    $byRoute = [];
    foreach ($lanes as $lane) {
        $from = trim((string) ($lane["effective_from"] ?? ""));
        $to = trim((string) ($lane["effective_to"] ?? ""));
        if ($dateValid && (($from !== "" && $from > $date) || ($to !== "" && $to < $date))) {
            continue;
        }
        $routeKey = strtoupper(implode("|", [
            trim((string) ($lane["origin"] ?? "")),
            trim((string) ($lane["packing_house"] ?? "")),
            trim((string) ($lane["destination"] ?? "")),
            trim((string) ($lane["dcode"] ?? "")),
            trim((string) ($lane["segment"] ?? "")),
        ]));
        if (!isset($byRoute[$routeKey]) || $from > trim((string) ($byRoute[$routeKey]["effective_from"] ?? ""))) {
            $byRoute[$routeKey] = $lane;
        }
    }
    $lanes = array_values($byRoute);
    if (empty($lanes)) {
        return null;
    }
    if (count($lanes) === 1) {
        return $lanes[0];
    }

    $key = strtoupper(trim($rawDest));
    $code = strtoupper(trim($locMap[$key] ?? $rawDest));

    foreach ($lanes as $lane) {
        $laneDcode = strtoupper(trim((string) $lane["dcode"]));
        if ($code !== "" && $laneDcode !== "" && $laneDcode === $code) {
            return $lane;
        }
    }
    foreach ($lanes as $lane) {
        $laneDest = strtoupper(trim((string) $lane["destination"]));
        if ($laneDest !== "" && $code !== "" &&
            (strpos($code, $laneDest) !== false || strpos($laneDest, $code) !== false)) {
            return $lane;
        }
    }
    return null;
}

/** Pick the covering fuel period from a given (sorted) set — mirrors fuel_pick_by_date(). */
function dashboard_fuel_pick(array $periods, string $date, bool $strictOnly = false): ?array
{
    $startOf = static fn(array $p): string => substr((string) ($p["updated_date"] ?? "") ?: (string) ($p["effective_from"] ?? ""), 0, 10);
    foreach ($periods as $p) {
        $from = $startOf($p);
        if ($from === "" || $from > $date) {
            continue;
        }
        $to = substr((string) ($p["effective_to"] ?? ""), 0, 10);
        if ($to === "" || $to >= $date) {
            return $p; // first (latest start) with an open/covering range
        }
    }
    if ($strictOnly) {
        return null; // expired tagged window: fall through to global
    }
    foreach ($periods as $p) {
        $from = $startOf($p);
        if ($from !== "" && $from <= $date) {
            return $p; // latest start on/before the date
        }
    }
    return null;
}

/**
 * In-memory fuel_price_for_customer_date() + fuel_value_for_source(): two-pass — rows
 * tagged with $customerKey first, then global (blank customer_keys). Mirrors the PHP
 * resolver so dashboard revenue matches billing.
 */
function dashboard_fuel_price(array $periods, string $date, string $source, string $customerKey = ""): ?float
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return null;
    }
    $applies = static function (array $p, string $ck): bool {
        $keys = trim((string) ($p["customer_keys"] ?? ""));
        if ($keys === "") {
            return true;
        }
        if ($ck === "") {
            return false;
        }
        foreach (explode(",", $keys) as $k) {
            if (trim($k) === $ck) {
                return true;
            }
        }
        return false;
    };

    $chosen = null;
    if (trim($customerKey) !== "") {
        $tagged = array_filter($periods, static fn(array $p): bool => trim((string) ($p["customer_keys"] ?? "")) !== "" && $applies($p, $customerKey));
        $chosen = dashboard_fuel_pick($tagged, $date, true);
    }
    if ($chosen === null) {
        $global = array_filter($periods, static fn(array $p): bool => trim((string) ($p["customer_keys"] ?? "")) === "");
        $chosen = dashboard_fuel_pick($global, $date);
    }
    if ($chosen === null) {
        return null;
    }

    $candidates = [];
    if (trim($source) !== "") {
        $candidates[] = trim($source);
    }
    $candidates[] = "common_price";
    $candidates[] = "average";
    foreach (["petron", "shell", "caltex", "phoenix", "flying_v", "seaoil", "jetti", "my_gas", "independent", "common_price", "average"] as $c) {
        $candidates[] = $c;
    }
    foreach ($candidates as $col) {
        if (array_key_exists($col, $chosen)) {
            $v = trim((string) $chosen[$col]);
            if ($v !== "" && is_numeric($v)) {
                return (float) $v;
            }
        }
    }
    return null;
}

