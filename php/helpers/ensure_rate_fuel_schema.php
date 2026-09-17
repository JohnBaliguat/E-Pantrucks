<?php

/**
 * Idempotent bootstrap for the fuel-based rate matrix model.
 *
 * Pricing model:
 *   - a richer fuel_price table (multi-supplier diesel prices + an effective
 *     date range + a "common" price), and
 *   - a single-table per-customer rate matrix: each rate_lane row carries its own
 *     effective window and the fuel-movement formula params (pump_price /
 *     price_movement + the 0.4x fuel factor) alongside the base_rate.
 *
 * The applied rate for a trip = look up the fuel price in effect on the trip date
 * for the customer's chosen supplier, then compute base_rate stepped up by the
 * fuel-movement formula (see rate_matrix_formula_price in fuel_rate_engine.php).
 *
 * Cheap to call on every request (all ADD/CREATE ... IF NOT EXISTS). The
 * existing fuel_price columns (price_date, petron/shell/caltex, average,
 * dollar_conversion) are preserved so the SAP customer billing and the
 * dashboard keep working.
 */
function ensure_rate_fuel_schema(PDO $conn): void
{
    static $alreadyEnsured = false;
    if ($alreadyEnsured) {
        return;
    }

    // --- Fuel price: add the extra suppliers + effective date range. ---
    $fuelCols = [
        "phoenix" => "text",
        "flying_v" => "text",
        "seaoil" => "text",
        "jetti" => "text",
        "my_gas" => "text",
        "independent" => "text",
        "common_price" => "text",
        "effective_from" => "date",
        "effective_to" => "date",
        // The date this fuel price starts being used in BILLING (billing picks the row
        // whose COALESCE(updated_date, effective_from) is the latest on/before the trip).
        "updated_date" => "date",
        // Fuel-movement tier (e.g. 105 / 110 / 115) in effect for this fuel
        // period — drives the Sumifru lane+tier rate code (STS115, STP110, ...).
        "fuel_tier" => "text",
        // Comma-separated customer keys this fuel price applies to. BLANK = global default
        // (every customer); tagged = only those customers (they override the global row).
        "customer_keys" => "text",
    ];
    foreach ($fuelCols as $col => $type) {
        $conn->exec("ALTER TABLE fuel_price ADD COLUMN IF NOT EXISTS $col $type");
    }
    // Retired: monthly_fuel_price (Sumifru/Box Banana are now driven by customer-tagged
    // fuel_price rows). Drop the leftover table if a previous install created it.
    $conn->exec("DROP TABLE IF EXISTS monthly_fuel_price");
    // Backfill the date range from the legacy weekly price_date where unset:
    // a weekly row covers its own week (Wed..Tue) — default to the same day.
    $conn->exec(
        "UPDATE fuel_price
         SET effective_from = price_date::date
         WHERE effective_from IS NULL
           AND price_date ~ '^\\d{4}-\\d{2}-\\d{2}'"
    );
    $conn->exec(
        "UPDATE fuel_price
         SET effective_to = (price_date::date + INTERVAL '6 day')::date
         WHERE effective_to IS NULL
           AND price_date ~ '^\\d{4}-\\d{2}-\\d{2}'"
    );

    // --- Forex: USD→PHP rate on its own dated table (moved off fuel_price). Same
    //     From / To / Updated Date shape as fuel_price; billing keys on the Updated Date
    //     (falls back to From). ---
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS forex_rate (
            id             INTEGER PRIMARY KEY,
            effective_from DATE,
            effective_to   DATE,
            updated_date   DATE,
            rate           TEXT NOT NULL DEFAULT '',
            created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )"
    );
    // Add the dated columns to any pre-existing forex_rate + migrate the old single
    // effective_date into effective_from.
    foreach (["effective_from", "effective_to", "updated_date"] as $col) {
        $conn->exec("ALTER TABLE forex_rate ADD COLUMN IF NOT EXISTS $col DATE");
    }
    // Per-customer scoping (blank = global default), mirroring fuel_price.
    $conn->exec("ALTER TABLE forex_rate ADD COLUMN IF NOT EXISTS customer_keys TEXT");
    try {
        $conn->exec("UPDATE forex_rate SET effective_from = effective_date
                     WHERE effective_from IS NULL AND effective_date IS NOT NULL");
    } catch (Throwable $e) {
        // no legacy effective_date column -> nothing to migrate
    }
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_forex_rate_from ON forex_rate (effective_from DESC)");

    // --- Rate matrix tables. ---
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS rate_lane (
    lane_id      INTEGER PRIMARY KEY,
    customer_key TEXT NOT NULL,
    segment      TEXT,
    origin       TEXT,
    destination  TEXT,
    dcode        TEXT,
    base_rate    NUMERIC NOT NULL DEFAULT 0,
    sort_order   INTEGER NOT NULL DEFAULT 0,
    active       BOOLEAN NOT NULL DEFAULT TRUE
);
CREATE INDEX IF NOT EXISTS idx_rate_lane_customer ON rate_lane (customer_key);

-- Optional per-lane fuel-price BANDS: a stepped/flat rate per diesel-price range,
-- for contracts that hold a flat rate within a band and jump at thresholds (e.g. ABC
-- Donmar: flat until 87.49, flat 43,500 for 87.50-100, ...). When a lane has bands the
-- charged rate is the covering band's flat rate (base_rate below the lowest band);
-- lanes with NO bands keep using the 0.4x fuel-movement formula. customer_key is stored
-- so all bands can be replaced with the lane rows on save.
CREATE TABLE IF NOT EXISTS rate_lane_band (
    band_id      INTEGER PRIMARY KEY,
    lane_id      INTEGER NOT NULL,
    customer_key TEXT NOT NULL,
    fuel_from    NUMERIC,
    fuel_to      NUMERIC,
    rate         NUMERIC NOT NULL DEFAULT 0,
    sort_order   INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_rate_lane_band_lane ON rate_lane_band (lane_id);
CREATE INDEX IF NOT EXISTS idx_rate_lane_band_customer ON rate_lane_band (customer_key);

CREATE TABLE IF NOT EXISTS activity_rate (
    id            INTEGER PRIMARY KEY,
    customer_key  TEXT NOT NULL,
    activity_code TEXT NOT NULL,
    rate          NUMERIC NOT NULL DEFAULT 0,
    free_hours    NUMERIC NOT NULL DEFAULT 48,
    UNIQUE (customer_key, activity_code)
);
SQL;
    $conn->exec($sql);

    // Per-activity SAP Material Code (chassis vs genset differ for the same customer),
    // falls back to the customer's material_code when blank.
    $conn->exec("ALTER TABLE activity_rate ADD COLUMN IF NOT EXISTS material_code TEXT NOT NULL DEFAULT ''");

    // Simplified single-table rate matrix: each rate_lane row carries its own effective
    // date window and the fuel-movement formula parameters. The charged rate is COMPUTED
    // per trip (rate_matrix_formula_price) from the fuel price on the trip date, instead of
    // storing an explicit lane x fuel-band cell grid.
    //   effective_from  - row applies to trips on/after this date (blank = no lower bound)
    //   effective_to    - row applies to trips on/before this date (blank = no upper bound)
    //   pump_price      - base pump price at which base_rate applies
    //   price_movement  - pump-price step size for banding (e.g. 2.50)
    // The rate escalates at the company fuel factor (rate_matrix_fuel_factor(), 0.4x) times
    // the fuel's % movement above pump_price, so no per-lane movement% is stored. The legacy
    // movement_pct column is left provisioned but ignored.
    foreach ([
        "effective_date" => "date", // legacy single date (kept for backfill; superseded by from/to)
        "effective_from" => "date",
        "effective_to" => "date",
        "pump_price" => "numeric",
        "price_movement" => "numeric",
        // Monthly average fuel entered directly in the Rate Matrix. The row's
        // Effective From/To range identifies the month it applies to.
        "monthly_fuel_average" => "numeric",
        "movement_pct" => "numeric", // retired (was % per step); kept so existing rows don't error
        // Packing house — the middle leg of the rate route (Origin -> Packing House ->
        // Destination). Part of the lane's identity/versioning; descriptive for matching.
        "packing_house" => "text",
        // Per-lane rounding override for the fuel surcharge: "down" truncates, "nearest"
        // rounds to nearest, blank/NULL = the customer default (rate_matrix_rounds_down()).
        // Lets a single lane round differently from the rest of its customer's matrix — e.g.
        // TDC - Dole Asia DICT(Comml)->DICT Port floors (9,279) while its Dole - PW lanes
        // round to nearest (11,566). Read by rate_matrix_lane_rounds_down().
        "round_mode" => "text",
    ] as $col => $type) {
        $conn->exec("ALTER TABLE rate_lane ADD COLUMN IF NOT EXISTS $col $type");
    }
    // Backfill the new From bound from the legacy single effective_date where present.
    $conn->exec("UPDATE rate_lane SET effective_from = effective_date
                 WHERE effective_from IS NULL AND effective_date IS NOT NULL");
    // The legacy version_id / effective_date columns are left in place (ignored) so existing
    // rows survive; the old rate_band / rate_cell / rate_matrix_version grid model is retired.

    $alreadyEnsured = true;
}

/** Next id helper (these tables use app-assigned integer PKs, like billing). */
function rate_fuel_next_id(PDO $conn, string $table, string $pk): int
{
    $allowed = [
        "rate_lane" => "lane_id",
    ];
    if (!isset($allowed[$table]) || $allowed[$table] !== $pk) {
        throw new RuntimeException("Invalid id sequence target.");
    }
    return ((int) $conn->query("SELECT COALESCE(MAX($pk), 0) FROM $table")->fetchColumn()) + 1;
}
