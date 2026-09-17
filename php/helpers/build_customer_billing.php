<?php

/**
 * Builds the SAP ZPSO sales-order upload (customer billing) file. One line
 * item per reefer-van trip, priced in the customer's currency converted from
 * the PHP rate using the fuel-matrix forex. Layout matches the customer
 * template (e.g. "Sumifru Containerized - 1.xlsx").
 *
 * Pricing per line: Price = (PHP rate / forex) * condition_unit (1000).
 */

function billing_norm($value): string
{
    return trim((string) ($value ?? ""));
}

function billing_digits($value): string
{
    $text = billing_norm($value);
    if ($text === "") {
        return "";
    }
    $digits = preg_replace('/\D+/', "", $text);
    return $digits !== "" ? $digits : $text;
}

/**
 * Trailer / chassis identifier for billing OUTPUTS (SAP CSV, PDF, statements).
 * Operations encodes chassis as a "D"-series (D11, D20) and that "D" is part of the
 * trailer's identity — it must be KEPT, so "D11" displays as "D11", not "11". Only the
 * numeric part's leading zeros are trimmed ("D011" -> "D11"; spaces/dashes ignored).
 * A value with no "D" prefix reduces to its digits exactly like before
 * ("PM642" -> "642", "TR012" -> "012"), so only the D-series gains a prefix.
 * Blank stays blank.
 */
function billing_trailer_label($value): string
{
    $s = strtoupper(billing_norm($value));
    $s = preg_replace('/[\s\-_]+/', "", $s);
    if ($s === "") {
        return "";
    }
    if (preg_match('/^D0*(\d+)$/', $s, $m)) {
        return "D" . $m[1];
    }
    return billing_digits($value);
}

/** SAP unit/route codes are three-digit text values (e.g. 48 becomes 048). */
function billing_sap_three_digits($value): string
{
    $text = billing_norm($value);
    return $text !== "" && ctype_digit($text) ? str_pad($text, 3, "0", STR_PAD_LEFT) : $text;
}

function billing_serial_datetime(string $datetime): ?float
{
    $ts = strtotime($datetime . " UTC");
    if ($ts === false) {
        return null;
    }
    $serial = ($ts / 86400) + 25569;
    return $serial > 0 ? $serial : null;
}

/**
 * The Document Date / Billing Date stamped on the SAP file (cells 5 & 6).
 *
 * Request-scoped, so every builder in a single generation stamps the same date. Call with
 * a YYYY-MM-DD string to set it (blank/invalid resets to today); call with no argument to
 * read it. Defaults to TODAY, so past behaviour is unchanged when the biller doesn't pick a
 * date. The generate endpoint sets it from the biller's choice; the rebuild helper sets it
 * from the invoice's stored document_date so re-downloaded files keep their original date.
 */
function billing_document_date(?string $set = null): string
{
    static $date = null;
    if (func_num_args() > 0) {
        $t = trim((string) $set);
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) ? $t : null;
    }
    return $date ?? date("Y-m-d");
}

/** Excel serial for the current Document/Billing Date (see billing_document_date()). */
function billing_document_serial(): ?float
{
    return billing_serial_datetime(billing_document_date());
}

/** PHP rate (per trip) for the customer's rate_code from the rates master data. */
function billing_customer_rate(PDO $conn, string $rateCode): float
{
    $stmt = $conn->prepare("SELECT NULLIF(rate, '')::numeric FROM rates WHERE rate_code = ? LIMIT 1");
    $stmt->execute([$rateCode]);
    $value = $stmt->fetchColumn();
    return is_numeric($value) ? (float) $value : 0.0;
}

/**
 * Forex rate whose effective period covers the transaction date.
 */
function billing_forex_rate(PDO $conn, string $onDate, string $customerKey = ""): float
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $onDate)) {
        return 0.0;
    }
    // Guarantee the per-customer column exists (this function is called from many places
    // that don't run the schema bootstrap first).
    static $ensured = false;
    if (!$ensured) {
        try {
            $conn->exec("ALTER TABLE forex_rate ADD COLUMN IF NOT EXISTS customer_keys TEXT");
        } catch (Throwable $e) {
            // table absent -> handled below
        }
        $ensured = true;
    }

    // Load ALL forex rows ONCE per request (tiny table). Previously this ran a query per
    // distinct trip date, a DB round-trip per date — a major cause of preview/generate
    // timeouts (HTTP 504) on large date ranges. The date rule + two-pass customer scoping
    // are done in PHP below, faithfully mirroring the old SQL.
    static $allRows = null;
    if ($allRows === null) {
        try {
            $allRows = $conn->query(
                "SELECT rate, customer_keys, updated_date, effective_from, effective_to, id FROM forex_rate"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $allRows = [];
        }
    }

    static $cache = [];
    $cacheKey = trim($customerKey) . '|' . $onDate;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $d = static fn($v): string => substr(trim((string) ($v ?? "")), 0, 10);
    // Eligible on $onDate — same date rule as the old SQL (Updated Date overrides From; within To).
    $eligible = static function (array $r) use ($onDate, $d): bool {
        if (trim((string) ($r["rate"] ?? "")) === "" || !is_numeric(trim((string) $r["rate"]))) {
            return false;
        }
        $upd = $d($r["updated_date"] ?? "");
        $from = $d($r["effective_from"] ?? "");
        $to = $d($r["effective_to"] ?? "");
        if ($upd !== "") {
            if ($to !== "" && $upd > $to && $upd === $onDate) {
                return true; // an Updated-Date row dated exactly today applies even past its window
            }
            return ($to === "" || $upd <= $to) && $upd <= $onDate && ($to === "" || $to >= $onDate);
        }
        return $from !== "" && $from <= $onDate && ($to === "" || $to >= $onDate);
    };
    // ORDER BY updated_date DESC NULLS LAST, effective_from DESC NULLS LAST, id DESC.
    $cmp = static function (array $a, array $b) use ($d): int {
        $ua = $d($a["updated_date"] ?? ""); $ub = $d($b["updated_date"] ?? "");
        if ($ua !== $ub) { return $ua === "" ? 1 : ($ub === "" ? -1 : $ub <=> $ua); }
        $fa = $d($a["effective_from"] ?? ""); $fb = $d($b["effective_from"] ?? "");
        if ($fa !== $fb) { return $fa === "" ? 1 : ($fb === "" ? -1 : $fb <=> $fa); }
        return ((int) ($b["id"] ?? 0)) <=> ((int) ($a["id"] ?? 0));
    };
    $pick = static function (array $candidates) use ($eligible, $cmp): ?float {
        $elig = array_values(array_filter($candidates, $eligible));
        if (!$elig) { return null; }
        usort($elig, $cmp);
        return (float) $elig[0]["rate"];
    };

    // Pass 1: rows tagged with this customer (override the global default).
    $ck = trim($customerKey);
    if ($ck !== "") {
        $tagged = array_filter($allRows, static function (array $r) use ($ck): bool {
            $keys = trim((string) ($r["customer_keys"] ?? ""));
            if ($keys === "") { return false; }
            return strpos("," . str_replace(" ", "", $keys) . ",", "," . $ck . ",") !== false;
        });
        $r = $pick($tagged);
        if ($r !== null) {
            return $cache[$cacheKey] = $r;
        }
    }

    // Pass 2: global (untagged) rows.
    $global = array_filter($allRows, static fn(array $r): bool => trim((string) ($r["customer_keys"] ?? "")) === "");
    return $cache[$cacheKey] = ($pick($global) ?? 0.0);
}

/**
 * SAP Assigned No. for a trip's SKU, from Master Data → SKU Routes (matched by
 * billing_sku = sku.sku_name). Becomes the SAP "Route" column across all pipelines.
 * Returns "" when the SKU has no SKU-Route/SAP-No mapping, so callers fall back to
 * their per-customer/lane route. Memoized (whole map loaded once); an absent
 * sku_route/sku table just yields "".
 */
function sku_route_sap_no(PDO $conn, string $billingSku): string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        try {
            $rows = $conn->query(
                "SELECT s.sku_name, sr.sap_assigned_no
                 FROM sku_route sr
                 JOIN sku s ON s.sku_id = sr.sku_id
                 WHERE TRIM(COALESCE(sr.sap_assigned_no, '')) <> ''
                 ORDER BY sr.id"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $key = strtoupper(trim((string) $r["sku_name"]));
                if ($key !== "" && !isset($map[$key])) {
                    $map[$key] = trim((string) $r["sap_assigned_no"]); // first route per SKU wins
                }
            }
        } catch (Throwable $e) {
            $map = [];
        }
    }
    return $map[strtoupper(trim($billingSku))] ?? "";
}

/**
 * The PH leg (e.g. "PH10") for a trip's SKU, from sku_route (matched by billing_sku =
 * sku.sku_name). The operation record does not store PH, so it comes from the SKU.
 * Memoized. Returns "" when unmapped.
 */
function sku_route_ph(PDO $conn, string $billingSku): string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        try {
            $rows = $conn->query(
                "SELECT s.sku_name, sr.ph
                 FROM sku_route sr JOIN sku s ON s.sku_id = sr.sku_id
                 WHERE TRIM(COALESCE(sr.ph, '')) <> '' ORDER BY sr.id"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $key = strtoupper(trim((string) $r["sku_name"]));
                if ($key !== "" && !isset($map[$key])) {
                    $map[$key] = trim((string) $r["ph"]);
                }
            }
        } catch (Throwable $e) {
            $map = [];
        }
    }
    return $map[strtoupper(trim($billingSku))] ?? "";
}

/**
 * Shipper segment (e.g. "Sumifru", "Good Farmer", "Farmind") for a trip's SKU, from the
 * sku master (sku_shipper_segment, matched by billing_sku = sku.sku_name). Used to keep
 * the geography-based route match within the trip's own customer, since different
 * customers can share the same DAVAO→PHxx→DAVAO geography with different SAP routes.
 * Memoized. Returns "" when the SKU is unmapped.
 */
function sku_segment(PDO $conn, string $billingSku): string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        try {
            $rows = $conn->query(
                "SELECT sku_name, sku_shipper_segment FROM sku
                 WHERE TRIM(COALESCE(sku_shipper_segment, '')) <> ''"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $key = strtoupper(trim((string) $r["sku_name"]));
                if ($key !== "" && !isset($map[$key])) {
                    $map[$key] = trim((string) $r["sku_shipper_segment"]);
                }
            }
        } catch (Throwable $e) {
            $map = [];
        }
    }
    return $map[strtoupper(trim($billingSku))] ?? "";
}

/**
 * Region (DAVAO / PANABO) for a location name, from Master Data → Locations. Memoized.
 * Returns "" when the location has no region set.
 */
function location_region(PDO $conn, string $name): string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        try {
            $rows = $conn->query(
                "SELECT location_name, region FROM location WHERE TRIM(COALESCE(region, '')) <> ''"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $map[strtoupper(trim((string) $r["location_name"]))] = strtoupper(trim((string) $r["region"]));
            }
        } catch (Throwable $e) {
            $map = [];
        }
    }
    return $map[strtoupper(trim($name))] ?? "";
}

/**
 * SAP Route resolved from the trip's ACTUAL geography — the pull-out and delivered
 * locations' regions (Master Data → Locations) plus the SKU's PH — matched against
 * sku_route(pullout, ph, delivered) WITHIN the trip's own shipper segment. This corrects
 * trips whose stored SKU is mistagged (e.g. delivered to SUMIFRU CY = DAVAO but tagged
 * ...-DVO-PNB) while keeping the match on the correct customer — several customers share
 * the same DAVAO→PHxx→DAVAO geography but each has its own SAP route (e.g. Sumifru-10 = 466
 * vs Good Farmer-10 = 228). Returns "" when any piece (a region, the PH, or the segment) is
 * unmapped, so the caller falls back to the stored-SKU route. Memoized by
 * segment|pullout|ph|delivered key.
 */
function trip_route_sap_no(PDO $conn, string $pulloutLoc, string $deliveredLoc, string $billingSku): string
{
    $pullout = location_region($conn, $pulloutLoc);
    $delivered = location_region($conn, $deliveredLoc);
    $ph = strtoupper(trim(sku_route_ph($conn, $billingSku)));
    $segment = strtoupper(trim(sku_segment($conn, $billingSku)));
    if ($pullout === "" || $delivered === "" || $ph === "" || $segment === "") {
        return "";
    }

    static $cache = [];
    $key = $segment . "|" . $pullout . "|" . $ph . "|" . $delivered;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $stmt = $conn->prepare(
            "SELECT sr.sap_assigned_no FROM sku_route sr JOIN sku s ON s.sku_id = sr.sku_id
             WHERE UPPER(TRIM(sr.pullout)) = ? AND UPPER(TRIM(sr.ph)) = ? AND UPPER(TRIM(sr.delivered)) = ?
               AND UPPER(TRIM(s.sku_shipper_segment)) = ?
               AND TRIM(COALESCE(sr.sap_assigned_no, '')) <> ''
             ORDER BY sr.id LIMIT 1"
        );
        $stmt->execute([$pullout, $ph, $delivered, $segment]);
        $v = $stmt->fetchColumn();
        return $cache[$key] = ($v !== false && $v !== null ? trim((string) $v) : "");
    } catch (Throwable $e) {
        return $cache[$key] = "";
    }
}

/**
 * SAP Equipment code for a unit, from Master Data → Equipment SAP Code (matched by
 * equipment_type + unit_no). Replaces the raw unit digits in the SAP PM / TR / GS
 * columns. $type is "PM" | "TR" | "GS"; $unitDigits is the already-extracted digits.
 * Falls back to $unitDigits when the unit is unmapped (blank stays blank). Memoized.
 */
function equipment_sap_code(PDO $conn, string $type, string $unit): string
{
    // Normalise a unit number for matching. The ALPHA PREFIX is significant and
    // must be preserved: "D12" and "TR012" are different trailers and must NOT
    // collide. Only leading zeros on the numeric portion are normalised so that
    // "D02"/"D2" and "TR012"/"TR12" still match. Spaces/dashes are ignored.
    $norm = static function (string $s): string {
        $s = strtoupper(trim($s));
        $s = preg_replace('/[\s\-_]+/', "", $s);
        if ($s === "") {
            return "";
        }
        // Optional alpha prefix followed by digits: keep the prefix, drop the
        // numeric part's leading zeros (e.g. "TR012" -> "TR12", "007" -> "7").
        if (preg_match('/^([A-Z]*)0*(\d+)$/', $s, $m)) {
            return $m[1] . $m[2];
        }
        return $s;
    };

    static $map = null;
    if ($map === null) {
        $map = [];
        try {
            $rows = $conn->query(
                "SELECT equipment_type, unit_no, sap_equipment_code
                 FROM equipment_sap
                 WHERE TRIM(COALESCE(sap_equipment_code, '')) <> ''"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $rType = strtoupper(trim((string) $r["equipment_type"]));
                $rUnit = $norm((string) $r["unit_no"]);
                if ($rUnit === "") {
                    continue;
                }
                $rCode = trim((string) $r["sap_equipment_code"]);
                $key = $rType . "|" . $rUnit;
                if (!isset($map[$key])) {
                    $map[$key] = $rCode;
                }
                // A unit stored in the master as a BARE number (e.g. TR "12") is
                // encoded in operations WITH its type prefix (e.g. "TR012" -> "TR12").
                // Register that prefixed alias too, so both forms resolve to the same
                // code — without letting the bare number collide with a different
                // prefix (e.g. "D12"), which is what plain digit-matching did wrong.
                if (ctype_digit($rUnit)) {
                    $aliasKey = $rType . "|" . $rType . $rUnit;
                    if (!isset($map[$aliasKey])) {
                        $map[$aliasKey] = $rCode;
                    }
                }
            }
        } catch (Throwable $e) {
            $map = [];
        }
    }
    $unit = billing_norm($unit);
    if ($unit === "") {
        return "";
    }
    $code = $map[strtoupper($type) . "|" . $norm($unit)] ?? null;
    if ($code !== null) {
        return $code;
    }
    // Unmapped: fall back to the trailer label. A "D"-series chassis KEEPS its D so the
    // SAP column shows "D11" (not "011") until finance maps it; a plain numeric unit still
    // exports as its digits (three-digit padding applied by billing_sap_three_digits).
    return billing_trailer_label($unit);
}

/** Fetch the trips (operation records) that belong to this customer. */
function billing_fetch_entries(PDO $conn, array $customer, string $dateFrom, string $dateTo, array $excludeEntryIds = []): array
{
    if (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ||
        $dateFrom > $dateTo
    ) {
        throw new RuntimeException("Invalid date range.");
    }

    $params = [$dateFrom, $dateTo];
    $where = "entry_type = 'RV ENTRY' AND created_date::date BETWEEN ? AND ?";

    if (!empty($customer["segment"])) {
        $where .= " AND segment = ?";
        $params[] = $customer["segment"];
    }
    if (!empty($customer["customer_match"])) {
        $where .= " AND (customer_ph ILIKE ? OR shipper ILIKE ? OR operations_ph ILIKE ?)";
        $like = "%" . $customer["customer_match"] . "%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $excludeIds = array_values(array_unique(array_filter(array_map("intval", $excludeEntryIds), fn($v) => $v > 0)));
    if (!empty($excludeIds)) {
        $where .= " AND entry_id NOT IN (" . implode(",", array_fill(0, count($excludeIds), "?")) . ")";
        foreach ($excludeIds as $id) {
            $params[] = $id;
        }
    }

    $sql = "SELECT entry_id, created_date::date::text AS trip_date,
                   prime_mover, delivered_by_prime_mover, truck, tr, waybill, gs,
                   total_load, load_quantity_weight, load_description
            FROM operations
            WHERE $where
            ORDER BY COALESCE(waybill_date, created_date::date) ASC, entry_id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Apply the user's include list and manual row order to fetched billing trips.
 * Unknown ids are ignored. When no selection is passed, all trips remain.
 */
function billing_apply_selection(array $entries, array $selectedEntryIds = [], array $entryOrder = []): array
{
    $selectedMap = [];
    foreach ($selectedEntryIds as $entryId) {
        $entryId = (int) $entryId;
        if ($entryId > 0) {
            $selectedMap[$entryId] = true;
        }
    }

    if (!empty($selectedMap)) {
        $entries = array_values(array_filter($entries, static function (array $entry) use ($selectedMap): bool {
            $entryId = (int) ($entry["entry_id"] ?? 0);
            return $entryId > 0 && isset($selectedMap[$entryId]);
        }));
    }

    $orderMap = [];
    $position = 0;
    foreach ($entryOrder as $entryId) {
        $entryId = (int) $entryId;
        if ($entryId > 0 && !isset($orderMap[$entryId])) {
            $orderMap[$entryId] = $position++;
        }
    }

    if (!empty($orderMap)) {
        usort($entries, static function (array $a, array $b) use ($orderMap): int {
            $aId = (int) ($a["entry_id"] ?? 0);
            $bId = (int) ($b["entry_id"] ?? 0);
            $aPos = $orderMap[$aId] ?? PHP_INT_MAX;
            $bPos = $orderMap[$bId] ?? PHP_INT_MAX;

            if ($aPos === $bPos) {
                return $aId <=> $bId;
            }

            return $aPos <=> $bPos;
        });
    }

    return array_values($entries);
}

function billing_column_labels(): array
{
    // SAP upload columns only. The internal per-unit helper is not exported.
    return [
        "Order Type", "Sales Organization", "Distribution Channel", "Division",
        "Document Date", "Billing Date", "Sold-To", "Customer Tax Class",
        "Document Currency", "Reference", "Vessel Visit", "Billed Services",
        "Item Number", "Material Code", "Order Quantity", "Sales Unit",
        "Alternate Quantity", "Condition Type", "Price", "Condition Unit",
        "Currency", "Forex Rate", "Profit Center", "PM", "Activity", "Route",
        "TR", "RV", "GS",
    ];
}

/**
 * SAP "Condition Unit" column value, assigned by document currency: USD prices are
 * quoted per 1000, PHP prices are flat (shown as 0). Display + pricing both derive
 * from this so the column and the Price agree.
 */
function billing_sap_condition_unit(string $currency): int
{
    return strtoupper(trim($currency)) === "USD" ? 1000 : 0;
}

/**
 * The multiplier the Price is computed with for a currency. USD scales by 1000 (per
 * the 1000 condition unit); PHP is flat (×1) — a 0 condition unit means "not per-1000",
 * NOT "price 0". Keeps peso amounts unchanged while the column shows 0.
 */
function billing_sap_price_scale(string $currency): float
{
    return strtoupper(trim($currency)) === "USD" ? 1000.0 : 1.0;
}

/**
 * HAULING-only SAP "Condition Unit" column: USD stays per-1000; non-USD (PHP) shows
 * 1 (finance request 2026-08-18). Display only — the Price still scales by
 * billing_sap_price_scale(), so peso amounts are unchanged. Kept separate from
 * billing_sap_condition_unit() so the other SAP exports (activity, dry van, ABC KDs,
 * DICT shuttling) are not affected.
 */
function billing_sap_hauling_condition_unit(string $currency): int
{
    return strtoupper(trim($currency)) === "USD" ? 1000 : 1;
}

/**
 * HAULING-only SAP "Activity" column: non-USD (PHP) trips show "P01"; USD stays blank
 * (finance request 2026-08-18).
 */
function billing_sap_hauling_activity(string $currency): string
{
    return strtoupper(trim($currency)) === "USD" ? "" : "P01";
}

/** Typed SAP Alternate Quantity cell from the trip's recorded load. */
function billing_load_cell(array $entry): array
{
    foreach (["total_load", "load_quantity_weight", "load_description"] as $field) {
        $value = trim((string) ($entry[$field] ?? ""));
        if ($value !== "") {
            return is_numeric($value) ? ["n", (float) $value] : ["t", $value];
        }
    }
    return ["t", ""];
}

/**
 * Build typed cell rows (each cell: [value, type]) for the writer.
 * Returns ['rows' => [...], 'entry_ids' => [...], 'total' => float].
 */
function billing_build_rows(array $entries, array $customer, float $rate, float $forex, string $reference, ?PDO $conn = null, array $manualForexByEntry = [], array $chargeOverrideByEntry = []): array
{
    $docSerial = billing_document_serial();
    $currency = (string) ($customer["document_currency"] ?? "PHP");
    $conditionUnit = billing_sap_hauling_condition_unit($currency); // column value: USD 1000 / PHP 1
    $scale = billing_sap_price_scale($currency);                  // price multiplier: USD 1000 / PHP 1
    $isUsd = strtoupper(trim($currency)) === "USD";

    $rows = [];
    $entryIds = [];
    $chargeByEntry = [];
    $item = 0;
    $total = 0.0;

    foreach ($entries as $e) {
        $item += 10;
        $entryIds[] = (int) $e["entry_id"];
        // Regeneration: a stored/edited charge overrides the flat rate for this trip.
        $entryKeyId = (int) ($e["entry_id"] ?? 0);
        $lineRate = (array_key_exists($entryKeyId, $chargeOverrideByEntry) && is_numeric($chargeOverrideByEntry[$entryKeyId]))
            ? (float) $chargeOverrideByEntry[$entryKeyId]
            : $rate;
        // Lock the PHP peso charge for this trip.
        $chargeByEntry[$entryKeyId] = round($lineRate, 2);
        $tripDate = billing_norm($e["trip_date"] ?? "");
        $manualForex = (float) ($manualForexByEntry[(int) ($e["entry_id"] ?? 0)] ?? 0);
        $fxKey = (string) ($customer["matrix_key"] ?? $customer["customer_key"] ?? "");
        $lineForex = $isUsd && $conn !== null ? ($manualForex > 0 ? $manualForex : billing_forex_rate($conn, substr($tripDate, 0, 10), $fxKey)) : $forex;
        $hasForex = !$isUsd || $lineForex > 0;
        $price = $hasForex ? ($lineRate / $lineForex) * $scale : 0.0;
        $perUnit = $hasForex ? $lineRate / $lineForex : 0.0;
        if ($hasForex) {
            $total += $perUnit;
        }
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
            ["t", ""],
            ["t", $customer["billed_services"]],
            ["n", $item],
            ["t", $customer["material_code"]],
            ["n", 1],
            ["t", $customer["sales_unit"]],
            billing_load_cell($e),
            ["t", $customer["condition_type"]],
            $hasForex ? ["n", round($price, 2)] : ["t", ""],
            ["n", $conditionUnit],
            ["t", $customer["document_currency"]],
            $isUsd && $hasForex ? ["n", round($lineForex, 3)] : ["t", ""],
            ["t", $customer["profit_center"]],
            // PM / TR / GS = the unit's SAP Equipment code (Master Data → Equipment SAP Code), else digits.
            ["n3", billing_sap_three_digits($conn !== null ? equipment_sap_code($conn, "PM", $e["delivered_by_prime_mover"] ?: $e["truck"]) : billing_digits($e["delivered_by_prime_mover"] ?: $e["truck"]))],
            ["t", billing_sap_hauling_activity($currency)],   // Activity: PHP → P01, USD → blank
            ["n3", billing_sap_three_digits($customer["route"])],
            ["n3", billing_sap_three_digits($conn !== null ? equipment_sap_code($conn, "TR", $e["tr"] ?: $e["waybill"]) : billing_trailer_label($e["tr"] ?: $e["waybill"]))],
            ["t", ""],
            ["n3", billing_sap_three_digits($conn !== null ? equipment_sap_code($conn, "GS", $e["gs"]) : billing_digits($e["gs"]))],
            ["t", ""],                              // AD (per-unit helper) — intentionally blank
        ];
    }

    return ["rows" => $rows, "entry_ids" => $entryIds, "charge_by_entry" => $chargeByEntry, "total" => round($total, 2)];
}

// ---------------------------------------------------------------------------
// CSV writer — same SAP ZPSO columns/data as the xlsx, comma-separated.
// ---------------------------------------------------------------------------

function billing_write_csv(array $rows, float $total, string $outputPath): void
{
    $labels = billing_column_labels();
    $handle = fopen($outputPath, "w");
    if ($handle === false) {
        throw new RuntimeException("Could not open CSV file for writing.");
    }

    fputcsv($handle, $labels);

    foreach ($rows as $row) {
        $line = [];
        foreach (array_slice($row, 0, count($labels)) as $cell) {
            [$type, $value] = $cell;
            if ($value === null || $value === "") {
                $line[] = "";
                continue;
            }
            if ($type === "dt") {
                $ts = (int) round((((float) $value) - 25569) * 86400);
                // CSV has no cell formatting. Excel otherwise re-parses 07/30/2026
                // and displays it as 7/30/2026, so emit a harmless Excel formula
                // that preserves the required zero-padded SAP display.
                $line[] = $ts > 0 ? '="' . gmdate("m/d/Y", $ts) . '"' : "";
            } elseif ($type === "n3") {
                // CSV cannot store Excel's `000` cell format. This formula is
                // required for Excel to visibly preserve a leading zero in CSV.
                $digits = billing_sap_three_digits($value);
                $line[] = $digits !== "" ? '="' . $digits . '"' : "";
            } elseif ($type === "n") {
                $num = rtrim(rtrim(number_format((float) $value, 8, ".", ""), "0"), ".");
                $line[] = ($num === "" || $num === "-") ? "0" : $num;
            } else {
                $line[] = billing_norm($value);
            }
        }
        fputcsv($handle, $line);
    }

    fclose($handle);
}

/** Write a .csv next to a generated .xlsx (best-effort; never blocks the xlsx). */
function billing_write_csv_sibling(array $rows, float $total, string $xlsxPath): ?string
{
    $csvPath = preg_replace('/\.xlsx$/i', ".csv", $xlsxPath);
    if ($csvPath === null || $csvPath === $xlsxPath) {
        $csvPath = $xlsxPath . ".csv";
    }
    try {
        billing_write_csv($rows, $total, $csvPath);
        return $csvPath;
    } catch (Throwable $e) {
        return null;
    }
}

// ---------------------------------------------------------------------------
// XLSX writer (from scratch, inline strings)
// ---------------------------------------------------------------------------

function billing_col_letter(int $index): string
{
    $letters = "";
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $letters = chr(65 + $mod) . $letters;
        $index = (int) (($index - $mod) / 26);
    }
    return $letters;
}

function billing_xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, "UTF-8");
}

function billing_cell_xml(string $ref, array $cell, int $textStyle, int $dateStyle): string
{
    [$type, $value] = $cell;
    // SAP's PM/Route/TR/GS codes are normally numeric. Preserve an exceptional
    // alphanumeric master-data code as text rather than converting it to zero.
    if ($type === "n3" && $value !== null && $value !== "" && !is_numeric($value)) {
        return '<c r="' . $ref . '" s="' . $textStyle . '" t="inlineStr"><is><t xml:space="preserve">'
            . billing_xml_escape(billing_norm($value)) . "</t></is></c>";
    }
    if ($type === "n" || $type === "dt" || $type === "n3") {
        if ($value === null || $value === "") {
            $style = $type === "dt" ? $dateStyle : ($type === "n3" ? 4 : null);
            return '<c r="' . $ref . '"' . ($style !== null ? ' s="' . $style . '"' : '') . "/>";
        }
        $num = rtrim(rtrim(number_format((float) $value, 8, ".", ""), "0"), ".");
        if ($num === "" || $num === "-") {
            $num = "0";
        }
        $s = $type === "dt" ? ' s="' . $dateStyle . '"' : ($type === "n3" ? ' s="4"' : "");
        return '<c r="' . $ref . '"' . $s . "><v>" . $num . "</v></c>";
    }
    $text = billing_norm($value);
    if ($text === "") {
        return '<c r="' . $ref . '"/>';
    }
    return '<c r="' . $ref . '" s="' . $textStyle . '" t="inlineStr"><is><t xml:space="preserve">'
        . billing_xml_escape($text) . "</t></is></c>";
}

function billing_write_xlsx(array $rows, float $total, string $outputPath): void
{
    if (!class_exists("ZipArchive")) {
        throw new RuntimeException("ZipArchive extension is required.");
    }

    $labels = billing_column_labels();
    $colCount = count($labels);
    $lastCol = billing_col_letter($colCount);

    // Styles: 1 = header (bold), 2 = body text, 3 = datetime
    $sheetRows = [];

    // Header row
    $headerCells = "";
    for ($c = 0; $c < $colCount; $c++) {
        $ref = billing_col_letter($c + 1) . "1";
        $label = $labels[$c];
        $headerCells .= $label === ""
            ? '<c r="' . $ref . '"/>'
            : '<c r="' . $ref . '" s="1" t="inlineStr"><is><t xml:space="preserve">' . billing_xml_escape($label) . "</t></is></c>";
    }
    $sheetRows[] = '<row r="1">' . $headerCells . "</row>";

    $rowNum = 1;
    foreach ($rows as $row) {
        $rowNum++;
        $cells = "";
        foreach (array_slice($row, 0, $colCount) as $i => $cell) {
            $ref = billing_col_letter($i + 1) . $rowNum;
            $cells .= billing_cell_xml($ref, $cell, 2, 3);
        }
        $sheetRows[] = '<row r="' . $rowNum . '">' . $cells . "</row>";
    }

    $dimension = "A1:" . $lastCol . $rowNum;

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="' . $dimension . '"/>'
        . '<sheetViews><sheetView tabSelected="1" workbookViewId="0"/></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . "<sheetData>" . implode("", $sheetRows) . "</sheetData>"
        . '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
        . "</worksheet>";

    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="2"><numFmt numFmtId="167" formatCode="mm/dd/yyyy"/><numFmt numFmtId="168" formatCode="000"/></numFmts>'
        . '<fonts count="2">'
        . '<font><sz val="10"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="10"/><name val="Calibri"/></font>'
        . "</fonts>"
        . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="5">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="167" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '<xf numFmtId="168" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . "</cellXfs>"
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . "</styleSheet>";

    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . "</Relationships>";

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . "</Types>";

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . "</Relationships>";

    @unlink($outputPath);
    $zip = new ZipArchive();
    if ($zip->open($outputPath, ZipArchive::CREATE) !== true) {
        throw new RuntimeException("Failed to create xlsx file.");
    }
    $zip->addFromString("[Content_Types].xml", $contentTypes);
    $zip->addFromString("_rels/.rels", $rootRels);
    $zip->addFromString("xl/workbook.xml", $workbookXml);
    $zip->addFromString("xl/_rels/workbook.xml.rels", $workbookRels);
    $zip->addFromString("xl/styles.xml", $stylesXml);
    $zip->addFromString("xl/worksheets/sheet1.xml", $sheetXml);
    $zip->close();
}
