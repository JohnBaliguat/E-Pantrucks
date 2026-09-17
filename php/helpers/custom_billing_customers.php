<?php

/**
 * User-defined (DB-stored) billing customers, priced through the fuel Rate Matrix.
 *
 * These are customers finance adds from Master Data → Customer SAP Codes ("Add New
 * Customer") instead of hardcoding them in code. custom_billing_customers_config()
 * returns rows shaped like a box_banana_customers.php entry, so
 * box_banana_customers_config() merges them into the MATRIX pipeline: trips are
 * selected by segment / customer_match and each trip is priced by resolve_lane_rate
 * against the customer's lanes in the Rate Matrix (Master Data → Rate Matrix) — the
 * same path as the built-in Box Bananas customers. No flat rate_code is stored here.
 *
 * Because they flow through the matrix pipeline, the whole billing stack — pipeline
 * detection, trip selection, preview, generate, PANABO PDF, CSV/Excel, SAP overrides,
 * dashboard, and the Rate Matrix editor — picks them up automatically.
 */

/** The config fields stored per custom customer (all TEXT). */
function custom_customer_fields(): array
{
    return [
        // SAP ZPSO header constants
        "order_type", "sales_org", "distribution_channel", "division", "sold_to",
        "customer_tax_class", "document_currency", "billed_services", "material_code",
        "sales_unit", "condition_type", "condition_unit", "profit_center", "route",
        // Selection (pricing is set per-lane in the Rate Matrix, not here)
        "segment", "customer_match", "reference_prefix",
        // PANABO PDF invoice
        "bill_to_name", "activity", "destination", "origin", "packing_station", "port_of_loading",
    ];
}

/** Sensible SAP defaults so the Add-Customer form isn't a wall of blanks. */
function custom_customer_defaults(): array
{
    return [
        "order_type" => "ZPSO",
        "sales_org" => "3200",
        "distribution_channel" => "20",
        "division" => "31",
        "customer_tax_class" => "0",
        "document_currency" => "PHP",
        "sales_unit" => "TRP",
        "condition_type" => "PR00",
        "condition_unit" => "1000",
    ];
}

function ensure_custom_customer_schema(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS billing_custom_customer (
            customer_key         TEXT PRIMARY KEY,
            label                TEXT NOT NULL DEFAULT '',
            order_type           TEXT NOT NULL DEFAULT 'ZPSO',
            sales_org            TEXT NOT NULL DEFAULT '',
            distribution_channel TEXT NOT NULL DEFAULT '20',
            division             TEXT NOT NULL DEFAULT '',
            sold_to              TEXT NOT NULL DEFAULT '',
            customer_tax_class   TEXT NOT NULL DEFAULT '0',
            document_currency    TEXT NOT NULL DEFAULT 'PHP',
            billed_services      TEXT NOT NULL DEFAULT '',
            material_code        TEXT NOT NULL DEFAULT '',
            sales_unit           TEXT NOT NULL DEFAULT 'TRP',
            condition_type       TEXT NOT NULL DEFAULT 'PR00',
            condition_unit       TEXT NOT NULL DEFAULT '1000',
            profit_center        TEXT NOT NULL DEFAULT '',
            route                TEXT NOT NULL DEFAULT '',
            segment              TEXT NOT NULL DEFAULT '',
            customer_match       TEXT NOT NULL DEFAULT '',
            rate_code            TEXT NOT NULL DEFAULT '',
            reference_prefix     TEXT NOT NULL DEFAULT '',
            bill_to_name         TEXT NOT NULL DEFAULT '',
            activity             TEXT NOT NULL DEFAULT '',
            destination          TEXT NOT NULL DEFAULT '',
            origin               TEXT NOT NULL DEFAULT '',
            packing_station      TEXT NOT NULL DEFAULT '',
            port_of_loading      TEXT NOT NULL DEFAULT '',
            is_vat               BOOLEAN NOT NULL DEFAULT FALSE,
            builtin              BOOLEAN NOT NULL DEFAULT FALSE,
            created_at           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            updated_at           TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )"
    );
    // Columns added after the table first shipped — add them to existing installs.
    // is_vat  → per-customer 12% VAT flag (shown only on the PANABO PDF, Box Bananas).
    // builtin → true for the code-defined Box Banana customers seeded into the DB, so
    //           the list lives in the database while their pipeline wiring stays in code.
    $conn->exec("ALTER TABLE billing_custom_customer ADD COLUMN IF NOT EXISTS is_vat BOOLEAN NOT NULL DEFAULT FALSE");
    $conn->exec("ALTER TABLE billing_custom_customer ADD COLUMN IF NOT EXISTS builtin BOOLEAN NOT NULL DEFAULT FALSE");
    $done = true;
}

/**
 * All DB-defined flat-rate customers keyed by customer_key, each shaped like a
 * billing_customers.php config entry. Returns [] (never throws) if the table is
 * absent — so a fresh install with no custom customers simply adds nothing.
 */
function custom_billing_customers_config(?PDO $conn = null, bool $fresh = false): array
{
    if (!($conn instanceof PDO)) {
        global $conn;
    }
    if (!($conn instanceof PDO)) {
        return [];
    }

    static $cache = null;
    if ($cache !== null && !$fresh) {
        return $cache;
    }

    $out = [];
    try {
        $rows = $conn->query(
            "SELECT * FROM billing_custom_customer ORDER BY label"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // Table not created yet (no custom customers) — nothing to merge.
        $cache = [];
        return $cache;
    }

    foreach ($rows as $r) {
        $key = trim((string) ($r["customer_key"] ?? ""));
        if ($key === "") {
            continue;
        }
        $cfg = ["label" => (string) ($r["label"] ?? $key)];
        foreach (custom_customer_fields() as $f) {
            $cfg[$f] = (string) ($r[$f] ?? "");
        }
        // Reference label falls back to the customer name when finance left it blank.
        if (trim((string) $cfg["reference_prefix"]) === "") {
            $cfg["reference_prefix"] = $cfg["label"];
        }
        // Per-customer 12% VAT flag (applied only on the PANABO PDF).
        $cfg["is_vat"] = filter_var($r["is_vat"] ?? false, FILTER_VALIDATE_BOOLEAN);
        // Provenance: `custom` marks a DB row; `builtin` marks a code-defined Box Banana
        // customer seeded into the DB (name/VAT editable, but not deletable — its pipeline
        // wiring is backfilled from box_banana_builtin_customers()).
        $cfg["custom"] = true;
        $cfg["builtin"] = filter_var($r["builtin"] ?? false, FILTER_VALIDATE_BOOLEAN);
        $out[$key] = $cfg;
    }

    $cache = $out;
    return $cache;
}

/**
 * Seed the code-defined Box Banana customers into the DB (idempotent) so the customer
 * list lives in the database and is editable from Master Data → Customer SAP Codes,
 * while their pipeline wiring (PDF layout, matrix key, forex, …) stays authoritative
 * in box_banana_builtin_customers() and is backfilled at read time.
 *
 * Only inserts a row when the key is absent — it never overwrites finance's later edits
 * (renamed label, toggled VAT). Rows are flagged builtin=true so the UI locks their key
 * and blocks deletion. Returns the number of rows inserted.
 *
 * @param array<string,array> $builtins customer_key => box-banana config entry
 */
function seed_box_banana_builtins(PDO $conn, array $builtins): int
{
    ensure_custom_customer_schema($conn);

    // Which keys already have a DB row? (Avoid clobbering edits / re-seeding.)
    $existing = [];
    try {
        $existing = array_fill_keys(
            $conn->query("SELECT customer_key FROM billing_custom_customer")->fetchAll(PDO::FETCH_COLUMN),
            true
        );
    } catch (Throwable $e) {
        return 0;
    }

    $defaults = custom_customer_defaults();
    $columns = array_merge(["customer_key", "label"], custom_customer_fields(), ["builtin"]);
    $placeholders = implode(", ", array_fill(0, count($columns), "?"));
    $sql = "INSERT INTO billing_custom_customer (" . implode(", ", $columns) . ", updated_at)
            VALUES ($placeholders, NOW())
            ON CONFLICT (customer_key) DO NOTHING";
    $stmt = $conn->prepare($sql);

    $inserted = 0;
    foreach ($builtins as $key => $cfg) {
        $key = trim((string) $key);
        if ($key === "" || isset($existing[$key])) {
            continue;
        }
        $row = [$key, (string) ($cfg["label"] ?? $key)];
        foreach (custom_customer_fields() as $f) {
            $val = $cfg[$f] ?? "";
            // pdf_hide_columns and similar array fields aren't stored here — they stay in
            // code and are backfilled — so only scalar config values are seeded.
            $row[] = is_array($val) ? "" : trim((string) ($val !== "" ? $val : ($defaults[$f] ?? "")));
        }
        $row[] = "true"; // builtin
        try {
            $stmt->execute($row);
            $inserted += $stmt->rowCount();
        } catch (Throwable $e) {
            // Skip a single bad row rather than aborting the whole seed.
        }
    }
    return $inserted;
}
