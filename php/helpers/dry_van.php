<?php

require_once __DIR__ . "/build_customer_billing.php";
require_once __DIR__ . "/fuel_rate_engine.php";

/**
 * Dry Van hauling billing — SAP ZPSO + PANABO statement.
 *
 * Source: `operations` entry_type "DRY VAN ENTRY", one customer per `customer_ph`
 * (e.g. "PSACC DOMESTIC"). Trip date = date_hauled (falls back to waybill_date).
 *
 * PRICING: a FLAT rate per trip from the `rates` master table, keyed by the
 * customer's `rate_code` (editable in Master Data → Rates). PHP billing, no forex.
 * When no rate is set every trip resolves to 0 and the endpoints report that
 * plainly rather than emitting a bogus zero-priced file.
 *
 * Output: the shared 30-column SAP layout (billing_column_labels /
 * billing_write_xlsx), ONE LINE PER TRIP (Order Quantity 1, Price = the flat
 * rate), plus the generic PANABO PDF statement (build_activity_pdf).
 *
 * SAP master data (Sold-To / Material Code / Profit Center) is left blank pending
 * finance and is layered in from Master Data via billing_customer_apply_sap_overrides.
 */
function dry_van_config(): array
{
    $common = [
        "order_type" => "ZPSO",
        "sales_org" => "3200",
        "distribution_channel" => "20",
        "division" => "31",
        "customer_tax_class" => "0",
        "document_currency" => "PHP",
        "billed_services" => "Hauling Dry Vans",
        "material_code" => "",
        "sales_unit" => "TRP",
        "condition_type" => "PR00",
        "condition_unit" => "1",
        "fuel_source" => "",
        "entry_type" => "DRY VAN ENTRY",
        "pdf_activity" => "HAULING OF DRY VANS",
    ];

    $config = [
        "psacc_domestic" => array_merge($common, [
            "label" => "PSACC Domestic",
            // customer_ph filter (dry van rows carry the customer here, not shipper).
            "customer_match" => "PSACC DOMESTIC",
            // Flat rate lives in the `rates` table under this code.
            "rate_code" => "PSACC_DOMESTIC",
            "sold_to" => "",
            "profit_center" => "",
            "route" => "",
            "reference_prefix" => "PSACC Domestic Dry Van",
            "charge_to" => "PSACC",
            "pdf_destination" => "",
        ]),
        // Citihardware Imports + Domestic are ONE billed customer (they share a single
        // rate matrix). customer_match "CITIHARDWARE" ILIKEs customer_ph, so it selects
        // both "CITIHARDWARE IMPORTS" and "CITIHARDWARE DOMESTIC" trips onto one statement.
        // Priced through the fuel Rate Matrix (rate_basis "matrix"), not a flat rate_code —
        // see dry_van_rate() / resolve_lane_rate; set its lanes in Master Data → Rate Matrix.
        "citihardware" => array_merge($common, [
            "label" => "CITIHARDWARE",
            "customer_match" => "CITIHARDWARE",
            "rate_basis" => "matrix",
            "matrix_key" => "citihardware",
            "fuel_source" => "",
            // ~99% of Citihardware trips warehouse at CARMEN; a blank ph (unrecorded
            // warehouse) is treated as CARMEN for lane matching (user request 2026-08-25).
            "default_warehouse" => "CARMEN",
            "rate_code" => "CITIHARDWARE",
            "sold_to" => "",
            "profit_center" => "",
            "route" => "",
            "reference_prefix" => "Citihardware Dry Van",
            "charge_to" => "CITIHARDWARE",
            "pdf_destination" => "",
        ]),
    ];

    // Short-haul dry-van customers priced through the fuel Rate Matrix (per lane), with
    // fuel escalation driven by the DOE pump price EFFECTIVE ON THE TRIP DATE — no Monthly
    // Avg Fuel is pinned (fuel_source "effective_date"; see dry_van_trip_rate). customer_match
    // ILIKEs customer_ph. Set each customer's lanes in Master Data → Rate Matrix.
    $shortHaul = [
        "ecossential"      => "ECOSSENTIAL",
        "eye_cargo"        => "EYE CARGO",
        "headsport"        => "HEADSPORT",
        "novococonut"      => "NOVOCOCONUT",
        "phil_jdu"         => "PHIL JDU",
        "solaris"          => "SOLARIS",
        "solarvista"       => "SOLARVISTA",
        "southern_harvest" => "SOUTHERN HARVEST",
    ];
    foreach ($shortHaul as $key => $name) {
        $config[$key] = array_merge($common, [
            "label" => $name,
            "customer_match" => $name,
            "rate_basis" => "matrix",
            // These 8 short-haul customers SHARE one rate matrix (same Pull-Out -> Delivered-To
            // lanes + rates), so they all resolve rates from a single customer_key in rate_lane.
            // Edit the shared lanes once in Master Data -> Rate Matrix ("dryvan_shorthaul_shared").
            "matrix_key" => "dryvan_shorthaul_shared",
            "fuel_source" => "effective_date",
            // These customers encode a booking/reference no. in the PH field, so the lane is
            // keyed on Pull-Out -> Delivered-To instead of the 3-part warehouse route.
            "lane_match" => "pullout_delivered",
            "rate_code" => "",
            "sold_to" => "",
            "profit_center" => "",
            "route" => "",
            "reference_prefix" => $name . " Dry Van",
            "charge_to" => $name,
            "pdf_destination" => "",
        ]);
    }

    return $config;
}

function dry_van_customer(string $key): ?array
{
    return dry_van_config()[$key] ?? null;
}

/** [key => label] for the Billing page dropdown. */
function dry_van_customers(): array
{
    $out = [];
    foreach (dry_van_config() as $key => $cfg) {
        $out[$key] = $cfg["label"];
    }
    return $out;
}

/** Flat rate per trip from the `rates` master table; 0 when unset. */
function dry_van_rate(PDO $conn, array $customer): float
{
    return billing_customer_rate($conn, (string) ($customer["rate_code"] ?? ""));
}

/** Non-rate SAP config this customer still needs (Sold-To). */
function dry_van_missing_config(array $customer): array
{
    $missing = [];
    foreach (["sold_to" => "Sold-To"] as $field => $label) {
        if (trim((string) ($customer[$field] ?? "")) === "") {
            $missing[] = $label;
        }
    }
    return $missing;
}

/** The trip-date SQL expression + the columns every dry-van billing row is shaped from. */
function dry_van_trip_date_expr(): string
{
    return "COALESCE(NULLIF(date_hauled::text,'')::date, NULLIF(waybill_date::text,'')::date, created_date::date)";
}

function dry_van_select_list(): string
{
    return "entry_id, waybill, (" . dry_van_trip_date_expr() . ")::text AS trip_date,
            customer_ph, shipper, ph, pullout_location, delivered_to, return_location, size,
            van_alpha, van_number, truck, tr, driver, booking, seal, total_load, billing_sku";
}

/** True when a dry-van customer is priced via the fuel Rate Matrix (per-lane), not a flat rate. */
function dry_van_is_matrix(array $customer): bool
{
    return ($customer["rate_basis"] ?? "") === "matrix";
}

/** The rate_lane customer_key for a dry-van customer (matrix_key, else the passed fallback). */
function dry_van_matrix_key(array $customer, string $fallback = ""): string
{
    $k = trim((string) ($customer["matrix_key"] ?? ""));
    return $k !== "" ? $k : $fallback;
}

/**
 * Match one dry-van trip to a rate_lane. Two modes (per-customer via "lane_match"):
 *
 *   "warehouse" (default) — 3-part match:
 *       Origin        = Port Pull-Out   (operations.pullout_location) = lane.origin
 *       Packing House = Warehouse       (operations.ph)               = lane.packing_house
 *       Destination   = Return Empty    (operations.return_location)  = lane.destination
 *
 *   "pullout_delivered" — 2-part match (Pull-Out → Delivered-To):
 *       Origin      = Port Pull-Out (operations.pullout_location) = lane.origin
 *       Destination = Delivered To  (operations.delivered_to)     = lane.destination
 *     Warehouse (PH) and Return Empty are ignored — used for customers whose PH field holds a
 *     booking/reference number rather than a warehouse, so it can't key the lane. Their lanes
 *     should leave Packing House blank.
 *
 * Each side is canonicalised through the Location Matrix (location.location_matrix) so raw
 * entry variants (e.g. "DICT CY" -> "DICT", "KTC TIBUNGCO" -> "DAVAO") line up with the lane.
 * Only lanes active + effective on the trip date are considered; the newest-effective lane
 * per route wins. Returns the lane row or null when no match exists.
 */
function dry_van_resolve_lane(
    PDO $conn,
    string $customerKey,
    string $pullout,
    string $warehouse,
    string $returnEmpty,
    string $tripDate,
    string $mode = "warehouse",
    string $delivered = ""
): ?array {
    $date = substr(trim($tripDate), 0, 10);

    // All active lanes for the customer, loaded once per request (a preview/generate prices
    // many trips off the same handful of lanes). Effective-date filtering + route collapse
    // happen per-call in PHP so a dated re-price still supersedes correctly.
    static $laneCache = [];
    if (!array_key_exists($customerKey, $laneCache)) {
        $stmt = $conn->prepare(
            "SELECT * FROM rate_lane WHERE customer_key = ? AND active = TRUE ORDER BY sort_order, lane_id"
        );
        $stmt->execute([$customerKey]);
        $laneCache[$customerKey] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $rows = $laneCache[$customerKey];
    if ($date !== "" && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $rows = array_filter($rows, static function (array $l) use ($date): bool {
            $from = trim((string) ($l["effective_from"] ?? ""));
            $to = trim((string) ($l["effective_to"] ?? ""));
            return ($from === "" || $from <= $date) && ($to === "" || $to >= $date);
        });
    }
    if (!$rows) {
        return null;
    }

    // Collapse to the newest-starting lane per (origin|packing_house|destination) route so a
    // re-dated rate supersedes the older one for the same lane.
    $byRoute = [];
    foreach ($rows as $l) {
        $rk = strtoupper(implode("|", [
            trim((string) ($l["origin"] ?? "")),
            trim((string) ($l["packing_house"] ?? "")),
            trim((string) ($l["destination"] ?? "")),
        ]));
        $eff = trim((string) ($l["effective_from"] ?? ""));
        if (!isset($byRoute[$rk]) || $eff > trim((string) ($byRoute[$rk]["effective_from"] ?? ""))) {
            $byRoute[$rk] = $l;
        }
    }

    $tp = strtoupper(trim(location_matrix_code($conn, $pullout)));

    // 2-part mode: Pull-Out -> Delivered-To, ignoring Warehouse (PH) and Return Empty.
    if ($mode === "pullout_delivered") {
        $tdl = strtoupper(trim(location_matrix_code($conn, $delivered)));
        foreach ($byRoute as $l) {
            $lo = strtoupper(trim(location_matrix_code($conn, (string) ($l["origin"] ?? ""))));
            $ld = strtoupper(trim(location_matrix_code($conn, (string) ($l["destination"] ?? ""))));
            if ($lo === $tp && $ld === $tdl) {
                return $l;
            }
        }
        return null;
    }

    $tw = strtoupper(trim(location_matrix_code($conn, $warehouse)));
    $tr = strtoupper(trim(location_matrix_code($conn, $returnEmpty)));

    foreach ($byRoute as $l) {
        $lo = strtoupper(trim(location_matrix_code($conn, (string) ($l["origin"] ?? ""))));
        $lw = strtoupper(trim(location_matrix_code($conn, (string) ($l["packing_house"] ?? ""))));
        $ld = strtoupper(trim(location_matrix_code($conn, (string) ($l["destination"] ?? ""))));
        if ($lo === $tp && $lw === $tw && $ld === $tr) {
            return $l;
        }
    }
    return null;
}

/**
 * The charged rate for one matrix-priced dry-van trip. Prices via the lane's fuel bands
 * (explicit rate per diesel-price range) when the lane has them, else the 0.4x formula.
 *
 * Two fuel-escalation modes pick the diesel price that drives the rate:
 *   - "effective_date": the DOE pump price effective on the trip's date (fuel_price table,
 *     per-customer→global). No Monthly Avg Fuel needs to be pinned on the lane.
 *   - default: the pinned Monthly Avg Fuel on the lane (user rule 2026-08-18).
 * No matched lane, or no fuel price available, charges the base rate (0 when unmatched).
 */
function dry_van_trip_rate(PDO $conn, string $customerKey, array $e, string $tripDate, string $defaultWarehouse = "", string $fuelSource = "", string $laneMatch = "warehouse"): float
{
    // A blank warehouse (ph) falls back to the customer's default warehouse, if configured,
    // so trips missing that field still match their lane.
    $warehouse = trim((string) ($e["ph"] ?? ""));
    if ($warehouse === "") {
        $warehouse = $defaultWarehouse;
    }
    $lane = dry_van_resolve_lane(
        $conn,
        $customerKey,
        (string) ($e["pullout_location"] ?? ""),
        $warehouse,
        (string) ($e["return_location"] ?? ""),
        $tripDate,
        $laneMatch,
        (string) ($e["delivered_to"] ?? "")
    );
    if (!$lane) {
        return 0.0;
    }
    $base = (float) $lane["base_rate"];

    if (strtolower(trim($fuelSource)) === "effective_date") {
        $date = substr(trim($tripDate), 0, 10);
        $fuelPrice = fuel_value_for_source(
            $date !== "" ? fuel_price_for_customer_date($conn, $customerKey, $date) : null,
            ""
        );
    } else {
        $fuelPrice = fuel_rate_num($lane["monthly_fuel_average"] ?? null);
    }
    if ($fuelPrice === null || $fuelPrice <= 0) {
        return $base; // no fuel price -> base rate
    }

    $banded = rate_lane_banded_price($conn, (int) $lane["lane_id"], $fuelPrice, $base);
    if ($banded !== null) {
        return $banded;
    }
    return rate_matrix_formula_price(
        $base,
        fuel_rate_num($lane["pump_price"] ?? null),
        fuel_rate_num($lane["price_movement"] ?? null),
        $fuelPrice,
        false
    );
}

/**
 * Per-entry charged rate [entry_id => rate] for a matrix-priced dry-van customer, resolving
 * each trip's lane from its Port Pull-Out / Warehouse / Return Empty. Returns [] for
 * flat-rate customers (the caller then uses the single flat rate).
 */
function dry_van_rates_by_entry(PDO $conn, array $customer, array $entries, string $customerKey = ""): array
{
    if (!dry_van_is_matrix($customer)) {
        return [];
    }
    $key = dry_van_matrix_key($customer, $customerKey);
    if ($key === "") {
        return [];
    }
    $defaultWarehouse = trim((string) ($customer["default_warehouse"] ?? ""));
    $fuelSource = (string) ($customer["fuel_source"] ?? "");
    $laneMatch = (string) ($customer["lane_match"] ?? "warehouse");
    $out = [];
    foreach ($entries as $e) {
        $eid = (int) ($e["entry_id"] ?? 0);
        $tripDate = substr((string) ($e["trip_date"] ?? ""), 0, 10);
        $out[$eid] = dry_van_trip_rate($conn, $key, $e, $tripDate, $defaultWarehouse, $fuelSource, $laneMatch);
    }
    return $out;
}

/**
 * Rate-usage summary for a dry-van customer, grouped by lane + charged rate — the same
 * shape box_banana_rate_summary() returns so the Billing page's Rate summary panel renders
 * it identically. Each line shows the lane route (Port Pull-Out → Warehouse → Return Empty),
 * its formula params, the diesel price used, the charged rate, the trip count and subtotal.
 * Flat-rate dry-van customers collapse to a single "Flat rate" line. Trips that match no lane
 * (or price 0) are counted in 'unmatched'.
 *
 * Returns [ 'lines' => [ {key, lane, dcode, base_rate, pump_price, price_movement, fuel_price,
 * rate, trips, subtotal} ... ] (subtotal desc), 'unmatched' => int, 'fuel_source' => string,
 * 'entry_keys' => [entry_id => key] ].
 */
function dry_van_rate_summary(PDO $conn, array $entries, array $customer, string $customerKey = ""): array
{
    $fuelSource = (string) ($customer["fuel_source"] ?? "");
    $isMatrix = dry_van_is_matrix($customer);
    $key = dry_van_matrix_key($customer, $customerKey);
    $defaultWarehouse = trim((string) ($customer["default_warehouse"] ?? ""));
    $laneMatch = (string) ($customer["lane_match"] ?? "warehouse");
    $flatRate = dry_van_rate($conn, $customer);
    $effectiveDate = strtolower(trim($fuelSource)) === "effective_date";

    $groups = [];
    $entryKeys = [];
    $unmatched = 0;

    foreach ($entries as $e) {
        $eid = (int) ($e["entry_id"] ?? 0);
        $tripDate = substr((string) ($e["trip_date"] ?? ""), 0, 10);

        // Flat-rate customers: one line for the whole statement.
        if (!$isMatrix || $key === "") {
            if ($flatRate <= 0) {
                $unmatched++;
                continue;
            }
            $gk = "flat|" . number_format($flatRate, 2, ".", "");
            $entryKeys[$eid] = $gk;
            if (!isset($groups[$gk])) {
                $groups[$gk] = [
                    "key" => $gk, "lane" => "Flat rate", "dcode" => "",
                    "base_rate" => $flatRate, "pump_price" => null, "price_movement" => null,
                    "fuel_price" => null, "fuel_varies" => false, "rate" => $flatRate,
                    "trips" => 0, "subtotal" => 0.0,
                ];
            }
            $groups[$gk]["trips"] += 1;
            $groups[$gk]["subtotal"] += $flatRate;
            continue;
        }

        // Matrix customers: resolve the lane by the customer's match mode.
        $warehouse = trim((string) ($e["ph"] ?? ""));
        if ($warehouse === "") {
            $warehouse = $defaultWarehouse;
        }
        $lane = dry_van_resolve_lane(
            $conn,
            $key,
            (string) ($e["pullout_location"] ?? ""),
            $warehouse,
            (string) ($e["return_location"] ?? ""),
            $tripDate,
            $laneMatch,
            (string) ($e["delivered_to"] ?? "")
        );
        $rate = $lane ? dry_van_trip_rate($conn, $key, $e, $tripDate, $defaultWarehouse, $fuelSource, $laneMatch) : 0.0;
        if (!$lane || $rate <= 0) {
            $unmatched++;
            continue;
        }

        // Diesel price behind the rate (for display): mirror dry_van_trip_rate's choice.
        // 0/absent means no escalation (base rate charged) — show it blank, not "0.00".
        $fuel = $effectiveDate
            ? ($tripDate !== "" ? fuel_value_for_source(fuel_price_for_customer_date($conn, $key, $tripDate), "") : null)
            : fuel_rate_num($lane["monthly_fuel_average"] ?? null);
        if ($fuel !== null && $fuel <= 0) {
            $fuel = null;
        }

        $laneId = (int) $lane["lane_id"];
        $gk = $laneId . "|" . number_format($rate, 2, ".", "");
        $entryKeys[$eid] = $gk;
        if (!isset($groups[$gk])) {
            $route = implode(" → ", array_filter([
                trim((string) ($lane["origin"] ?? "")),
                trim((string) ($lane["packing_house"] ?? "")),
                trim((string) ($lane["destination"] ?? "")),
            ]));
            $label = trim(implode(" / ", array_filter([trim((string) ($lane["segment"] ?? "")), $route])));
            if ($label === "") {
                $label = trim((string) ($lane["dcode"] ?? "")) ?: ("Lane #" . $laneId);
            }
            $groups[$gk] = [
                "key" => $gk, "lane" => $label, "dcode" => (string) ($lane["dcode"] ?? ""),
                "base_rate" => (float) ($lane["base_rate"] ?? $rate),
                "pump_price" => fuel_rate_num($lane["pump_price"] ?? null),
                "price_movement" => fuel_rate_num($lane["price_movement"] ?? null),
                "fuel_price" => $fuel, "fuel_varies" => false,
                "rate" => $rate, "trips" => 0, "subtotal" => 0.0,
            ];
        } elseif ($fuel !== null && $groups[$gk]["fuel_price"] !== null
            && abs($fuel - (float) $groups[$gk]["fuel_price"]) > 0.0001) {
            $groups[$gk]["fuel_varies"] = true;
        }
        $groups[$gk]["trips"] += 1;
        $groups[$gk]["subtotal"] += $rate;
    }

    $lines = array_values($groups);
    foreach ($lines as &$ln) {
        $ln["subtotal"] = round($ln["subtotal"], 2);
        if (!empty($ln["fuel_varies"])) {
            $ln["fuel_price"] = null; // rate held across fuel prices -> don't imply a single one
        }
        unset($ln["fuel_varies"]);
    }
    unset($ln);
    usort($lines, static fn($a, $b) => $b["subtotal"] <=> $a["subtotal"]);

    return ["lines" => $lines, "unmatched" => $unmatched, "fuel_source" => $fuelSource, "entry_keys" => $entryKeys];
}

/** Fetch this customer's dry-van trips for a date range. */
function dry_van_fetch(PDO $conn, array $customer, string $dateFrom, string $dateTo, array $excludeEntryIds = []): array
{
    if (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ||
        $dateFrom > $dateTo
    ) {
        throw new RuntimeException("Invalid date range.");
    }

    $expr = dry_van_trip_date_expr();
    $params = [(string) $customer["entry_type"], "%" . $customer["customer_match"] . "%", $dateFrom, $dateTo];
    $where = "entry_type = ? AND customer_ph ILIKE ? AND $expr BETWEEN ? AND ?";

    $excludeIds = array_values(array_unique(array_filter(array_map("intval", $excludeEntryIds), fn($v) => $v > 0)));
    if (!empty($excludeIds)) {
        $where .= " AND entry_id NOT IN (" . implode(",", array_fill(0, count($excludeIds), "?")) . ")";
        foreach ($excludeIds as $id) {
            $params[] = $id;
        }
    }

    $stmt = $conn->prepare(
        "SELECT " . dry_van_select_list() . " FROM operations
         WHERE $where
         ORDER BY $expr ASC, entry_id ASC"
    );
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Fetch dry-van rows for a specific set of entry ids (for the PDF rebuild). */
function dry_van_fetch_by_ids(PDO $conn, array $entryIds): array
{
    $ids = array_values(array_unique(array_filter(array_map("intval", $entryIds), fn($v) => $v > 0)));
    if (empty($ids)) {
        return [];
    }
    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $expr = dry_van_trip_date_expr();
    $stmt = $conn->prepare(
        "SELECT " . dry_van_select_list() . " FROM operations
         WHERE entry_id IN ($placeholders)
         ORDER BY $expr ASC, entry_id ASC"
    );
    $stmt->execute($ids);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Build the SAP rows — one line per trip, Order Quantity 1, Price = the flat
 * rate. Returns ['rows','entry_ids','total','priced'].
 */
function dry_van_build(PDO $conn, array $entries, array $customer, float $rate, string $reference, array $chargeOverrideByEntry = [], array $rateByEntry = []): array
{
    $docSerial = billing_document_serial();
    $conditionUnit = billing_sap_condition_unit($customer["document_currency"] ?? "PHP"); // PHP 0

    $rows = [];
    $entryIds = [];
    $chargeByEntry = [];
    $item = 0;
    $total = 0.0;

    foreach ($entries as $e) {
        // Rate precedence: a stored/edited charge (regeneration) wins; else the per-trip
        // matrix rate for matrix customers; else the single flat rate.
        $eid = (int) ($e["entry_id"] ?? 0);
        $hasOverride = array_key_exists($eid, $chargeOverrideByEntry) && is_numeric($chargeOverrideByEntry[$eid]);
        $matrixRate = array_key_exists($eid, $rateByEntry) ? (float) $rateByEntry[$eid] : null;
        $lineRate = $hasOverride
            ? (float) $chargeOverrideByEntry[$eid]
            : ($matrixRate !== null ? $matrixRate : $rate);
        if ($lineRate <= 0 && !$hasOverride) {
            continue; // never emit a zero-priced SAP line (unmatched matrix lane -> skipped)
        }
        $item += 10;
        $entryIds[] = $eid;
        $chargeByEntry[$eid] = round($lineRate, 2);
        $total += $lineRate;

        $rows[] = [
            ["t", $customer["order_type"]],
            ["t", $customer["sales_org"]],
            ["t", $customer["distribution_channel"]],
            ["t", $customer["division"]],
            ["dt", $docSerial],
            ["dt", $docSerial],
            ["t", $customer["sold_to"]],
            ["t", $customer["customer_tax_class"]],
            ["t", $customer["document_currency"]],
            ["t", $reference],
            ["t", ""],                       // Vessel Visit
            ["t", $customer["billed_services"]],
            ["n", $item],
            ["t", $customer["material_code"]],
            ["n", 1],                        // Order Quantity = 1 per trip
            ["t", $customer["sales_unit"]],
            billing_load_cell($e),              // Alternate Quantity = recorded load
            ["t", $customer["condition_type"]],
            ["n", round($lineRate, 2)],      // Price = the flat rate (or stored override)
            ["n", $conditionUnit],           // USD 1000 / PHP 0
            ["t", $customer["document_currency"]],
            ["t", ""],                       // Forex Rate (PHP billing)
            ["t", $customer["profit_center"]],
            // PM / TR = the unit's SAP Equipment code (Master Data → Equipment SAP Code), else digits.
            ["t", equipment_sap_code($conn, "PM", $e["truck"] ?? "")],
            ["t", ""],                       // Activity
            // Route = the SKU's SAP Assigned No (Master Data → SKU Routes), else config route.
            ["t", sku_route_sap_no($conn, (string) ($e["billing_sku"] ?? "")) ?: $customer["route"]],
            ["t", equipment_sap_code($conn, "TR", $e["tr"] ?? "")],
            ["t", ""],                       // RV
            ["t", ""],                       // GS (blank for dry vans)
            ["t", ""],                       // AD (helper) — intentionally blank
        ];
    }

    return [
        "rows" => $rows,
        "entry_ids" => $entryIds,
        "charge_by_entry" => $chargeByEntry,
        "total" => round($total, 2),
        // Priced when a flat rate is set OR any matrix trip resolved to a rate.
        "priced" => $rate > 0 || $total > 0,
    ];
}

/** PANABO statement columns for the dry-van hauling statement. */
function dry_van_pdf_columns(): array
{
    return [
        "date" => "DATE",
        "trip_receipt" => "TRIP RECEIPT",
        "truck" => "TRUCK",
        "trailer" => "TRAILER",
        "van" => "VAN",
        "size" => "SIZE",
        "origin" => "ORIGIN",
        "destination" => "DELIVERED TO",
        "charge" => "HAULING CHARGE",
    ];
}

/**
 * PANABO statement rows — one line per trip, charge = the flat rate. Mirrors the
 * SAP lines 1:1. Returns ['rows','entry_ids','total'].
 */
function dry_van_detail_rows(array $entries, array $customer, float $rate, array $rateByEntry = []): array
{
    $rows = [];
    $entryIds = [];
    $total = 0.0;

    foreach ($entries as $e) {
        // Per-trip matrix rate for matrix customers, else the single flat rate.
        $eid = (int) ($e["entry_id"] ?? 0);
        $lineRate = array_key_exists($eid, $rateByEntry) ? (float) $rateByEntry[$eid] : $rate;
        if ($lineRate <= 0) {
            continue; // keep the statement consistent with the SAP file
        }
        $tripDate = substr(billing_norm($e["trip_date"] ?? ""), 0, 10);
        $ts = $tripDate !== "" ? strtotime($tripDate) : false;
        $van = trim(billing_norm($e["van_alpha"] ?? "") . " " . billing_norm($e["van_number"] ?? ""));

        $total += $lineRate;
        $entryIds[] = $eid;

        $rows[] = [
            "date" => $ts ? date("m/d/Y", $ts) : "",
            "trip_receipt" => billing_norm($e["waybill"] ?? ""),
            "truck" => billing_digits($e["truck"] ?? ""),
            "trailer" => billing_trailer_label($e["tr"] ?? ""),
            "van" => $van,
            "size" => billing_norm($e["size"] ?? ""),
            "origin" => billing_norm($e["ph"] ?? ""),
            "destination" => billing_norm($e["delivered_to"] ?? ""),
            "charge" => number_format($lineRate, 2, ".", ","),
        ];
    }

    return ["rows" => $rows, "entry_ids" => $entryIds, "total" => round($total, 2)];
}
