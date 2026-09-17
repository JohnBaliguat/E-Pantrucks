<?php

/**
 * Add the optional per-row `customer_key` tag column to the Service Materials and
 * Profit Center tables (idempotent). Lets finance mark which billing customer a
 * material / profit center is for, as a reference alongside the Customer SAP Codes tab.
 */
function ensure_master_settings_customer_tag(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    foreach (["service_material", "profit_center"] as $table) {
        try {
            $conn->exec("ALTER TABLE $table ADD COLUMN IF NOT EXISTS customer_key TEXT NOT NULL DEFAULT ''");
        } catch (Throwable $e) {
            // Table may not exist yet on a fresh install — ignore.
        }
    }
    $done = true;
}

/**
 * The SKU Routes table: each row picks an existing SKU and defines its route as three
 * locations (Pullout / PH / Delivered). Segment / Farm / Roundtrip Distance are NOT
 * stored here — they come from the chosen SKU.
 */
function ensure_sku_route_schema(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS sku_route (
            id              INTEGER PRIMARY KEY,
            sap_assigned_no TEXT DEFAULT '',
            sku_id          INTEGER,
            pullout         TEXT NOT NULL DEFAULT '',
            ph              TEXT NOT NULL DEFAULT '',
            delivered       TEXT NOT NULL DEFAULT '',
            created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )"
    );
    // Added after the table shipped.
    $conn->exec("ALTER TABLE sku_route ADD COLUMN IF NOT EXISTS sap_assigned_no TEXT DEFAULT ''");
    // sap_assigned_no is an OPTIONAL field (left blank until finance fills it). The generic
    // save path (db_nullable_all) turns a blank into NULL, so the column must accept NULL —
    // drop the legacy NOT NULL on existing installs (idempotent).
    try {
        $conn->exec("ALTER TABLE sku_route ALTER COLUMN sap_assigned_no DROP NOT NULL");
    } catch (Throwable $e) {
        // already nullable, or the table/column is absent — nothing to do
    }

    // Equipment → SAP Equipment code map (used for the PM / TR / GS columns in SAP billing).
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS equipment_sap (
            id                 INTEGER PRIMARY KEY,
            equipment_type     TEXT NOT NULL DEFAULT '',
            unit_no            TEXT NOT NULL DEFAULT '',
            sap_equipment_code TEXT NOT NULL DEFAULT '',
            description        TEXT NOT NULL DEFAULT '',
            created_at         TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )"
    );
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_equipment_sap_lookup ON equipment_sap (equipment_type, unit_no)");

    $done = true;
}

function master_settings_config(): array
{
    return [
        "location" => [
            "table" => "location",
            "primary_key" => "location_id",
            "label" => "Location",
            "fields" => [
                "location_name" => [
                    "label" => "Location Name",
                    "required" => true,
                ],
                "latitude" => ["label" => "Latitude", "required" => false],
                "longitude" => ["label" => "Longitude", "required" => false],
            ],
            "default_sort" => "location_name ASC",
        ],
        "trailer" => [
            "table" => "trailer",
            "primary_key" => "trailer_id",
            "label" => "Trailer",
            "fields" => [
                "trailer_name" => [
                    "label" => "Trailer Name",
                    "required" => true,
                ],
            ],
            "default_sort" => "trailer_name ASC",
        ],
        "trip_rates" => [
            "table" => "trip_rates",
            "primary_key" => "id",
            "label" => "Trip Rate",
            "fields" => [
                "segment" => ["label" => "Segment", "required" => true],
                "activity" => ["label" => "Activity", "required" => true],
                "baseRate" => ["label" => "Base Rate", "required" => false],
                "additional" => ["label" => "Additional", "required" => false],
                "totalRates" => ["label" => "Total Rates", "required" => false],
            ],
            "default_sort" => "segment ASC, activity ASC",
        ],
        "units" => [
            "table" => "units",
            "primary_key" => "unit_id",
            "label" => "Unit",
            "fields" => [
                "unit_name" => ["label" => "Unit Name", "required" => true],
                "unit_std" => ["label" => "Unit STD", "required" => false],
                "unit_model" => ["label" => "Unit Model", "required" => false],
                "unit_cluster" => [
                    "label" => "Unit Cluster",
                    "required" => false,
                ],
            ],
            "default_sort" => "unit_name ASC",
        ],
        "sku" => [
            "table" => "sku",
            "primary_key" => "sku_id",
            "label" => "SKU",
            "fields" => [
                "sku_name" => ["label" => "SKU Name", "required" => true],
                "sku_shipper_segment" => ["label" => "SKU Shipper Segment", "required" => false],
                "sku_farm" => ["label" => "SKU Farm", "required" => false],
                "sku_rountripDistance" => ["label" => "Roundtrip Distance", "required" => false],
            ],
            "default_sort" => "sku_name ASC",
        ],
        "fuel_price" => [
            "table" => "fuel_price",
            "primary_key" => "id",
            "label" => "Fuel Price",
            "fields" => [
                "effective_from" => ["label" => "From", "required" => true],
                "effective_to" => ["label" => "To", "required" => false],
                // The date this fuel price starts being used in BILLING (billing picks the
                // row whose Updated Date, or From if blank, is the latest on/before the trip).
                "updated_date" => ["label" => "Updated Date (billing start)", "required" => false],
                "petron" => ["label" => "Petron", "required" => false],
                "shell" => ["label" => "Shell", "required" => false],
                "caltex" => ["label" => "Caltex", "required" => false],
                "phoenix" => ["label" => "Phoenix", "required" => false],
                "flying_v" => ["label" => "Flying V", "required" => false],
                "seaoil" => ["label" => "Seaoil", "required" => false],
                "jetti" => ["label" => "Jetti", "required" => false],
                "my_gas" => ["label" => "My Gas", "required" => false],
                "independent" => ["label" => "Independent", "required" => false],
                "common_price" => ["label" => "Common Price", "required" => false],
                "fuel_tier" => ["label" => "Fuel Tier % (e.g. 115)", "required" => false],
                "average" => ["label" => "Average", "required" => false],
                "price_date" => ["label" => "Legacy Date", "required" => false],
                // Comma-separated customer keys (blank = applies to all customers).
                "customer_keys" => ["label" => "Customers", "required" => false],
            ],
            "default_sort" => "effective_from DESC NULLS LAST, id DESC",
        ],
        "forex_rate" => [
            "table" => "forex_rate",
            "primary_key" => "id",
            "label" => "Forex Rate",
            "fields" => [
                "effective_from" => ["label" => "From", "required" => true],
                "effective_to" => ["label" => "To", "required" => false],
                "updated_date" => ["label" => "Updated Date (billing start)", "required" => false],
                "rate" => ["label" => "USD → PHP Rate", "required" => true],
                // Comma-separated customer keys (blank = applies to all customers).
                "customer_keys" => ["label" => "Customers", "required" => false],
            ],
            "default_sort" => "effective_from DESC NULLS LAST, id DESC",
        ],
        "sku_route" => [
            "table" => "sku_route",
            "primary_key" => "id",
            "label" => "SKU Route",
            "fields" => [
                "sap_assigned_no" => ["label" => "SAP Assigned No", "required" => false],
                "sku_id" => ["label" => "SKU", "required" => true],
                "pullout" => ["label" => "Pullout Location", "required" => false],
                "ph" => ["label" => "PH", "required" => false],
                "delivered" => ["label" => "Delivered Location", "required" => false],
            ],
            "default_sort" => "id ASC",
        ],
        "equipment_sap" => [
            "table" => "equipment_sap",
            "primary_key" => "id",
            "label" => "Equipment SAP Code",
            "fields" => [
                "equipment_type" => ["label" => "Equipment Type", "required" => true],
                "unit_no" => ["label" => "Unit No", "required" => true],
                "sap_equipment_code" => ["label" => "SAP Equipment Code", "required" => true],
                "description" => ["label" => "Description", "required" => false],
            ],
            "default_sort" => "equipment_type ASC, unit_no ASC",
        ],
        "service_material" => [
            "table" => "service_material",
            "primary_key" => "id",
            "label" => "Service Material",
            "fields" => [
                "revenue_stream" => ["label" => "Revenue Stream", "required" => true],
                "material_code" => ["label" => "Material Code", "required" => true],
                "material_description" => ["label" => "Material Description", "required" => true],
                "rate_type" => ["label" => "Rate Type", "required" => false],
                "rate" => ["label" => "Rate", "required" => false],
                "tax_class" => ["label" => "Tax Class", "required" => false],
                "profit_center" => ["label" => "Profit Center", "required" => false],
                "account_assignment" => ["label" => "Account Assignment", "required" => false],
                "gen_item_category_group" => ["label" => "Gen. Item Category Group", "required" => false],
                "item_category_group2" => ["label" => "Item Category Group2", "required" => false],
                "customer_key" => ["label" => "Customer", "required" => false],
            ],
            "default_sort" => "revenue_stream ASC, material_code ASC",
        ],
        "profit_center" => [
            "table" => "profit_center",
            "primary_key" => "id",
            "label" => "Profit Center",
            "fields" => [
                "profit_center_code" => ["label" => "Profit Center", "required" => true],
                "controlling_area" => ["label" => "Controlling Area", "required" => false],
                "valid_from_date" => ["label" => "Valid From Date", "required" => false],
                "valid_to_date" => ["label" => "Valid To Date", "required" => false],
                "name" => ["label" => "Name", "required" => true],
                "long_text" => ["label" => "Long Text", "required" => false],
                "person_responsible" => ["label" => "Person Responsible", "required" => false],
                "department" => ["label" => "Department", "required" => false],
                "profit_center_group" => ["label" => "Profit Center Group", "required" => false],
                "segment" => ["label" => "Segment", "required" => false],
                "customer_key" => ["label" => "Customer", "required" => false],
            ],
            "default_sort" => "profit_center_code ASC",
        ],
        "rates" => [
            "table" => "rates",
            "primary_key" => "id",
            "label" => "Rate",
            "fields" => [
                "origin" => ["label" => "Origin", "required" => true],
                "packing_house" => ["label" => "Packing House", "required" => false],
                "port_of_destination" => ["label" => "Port of Destination", "required" => false],
                "loc_code" => ["label" => "Loc. Code", "required" => false],
                "rate_code" => ["label" => "Rate Code", "required" => false],
                "rate" => ["label" => "Rate", "required" => false],
            ],
            "default_sort" => "origin ASC, rate_code ASC",
        ],
    ];
}

function master_settings_entity(string $entity): ?array
{
    $config = master_settings_config();
    return $config[$entity] ?? null;
}

function master_settings_clean_value($value): string
{
    return trim((string) ($value ?? ""));
}

function master_settings_decimal_value($value): float
{
    $clean = master_settings_clean_value($value);
    if ($clean === "" || !is_numeric($clean)) {
        return 0.0;
    }

    return (float) $clean;
}

function master_settings_format_decimal(float $value): string
{
    $formatted = number_format($value, 2, ".", "");
    return rtrim(rtrim($formatted, "0"), ".");
}

function master_settings_validate_payload(string $entity, array $payload): array
{
    $definition = master_settings_entity($entity);

    if ($definition === null) {
        return [
            "valid" => false,
            "message" => "Invalid settings entity.",
            "values" => [],
        ];
    }

    $values = [];

    foreach ($definition["fields"] as $field => $meta) {
        $value = master_settings_clean_value($payload[$field] ?? "");
        if (!empty($meta["required"]) && $value === "") {
            return [
                "valid" => false,
                "message" => $meta["label"] . " is required.",
                "values" => [],
            ];
        }
        $values[$field] = $value;
    }

    if ($entity === "trip_rates") {
        $baseRate = master_settings_decimal_value($values["baseRate"] ?? 0);
        $additional = master_settings_decimal_value($values["additional"] ?? 0);
        $values["baseRate"] = master_settings_format_decimal($baseRate);
        $values["additional"] = master_settings_format_decimal($additional);
        $values["totalRates"] = master_settings_format_decimal(
            $baseRate + $additional,
        );
    }

    if ($entity === "fuel_price") {
        // Average of the three leading companies (legacy semantics, used by the
        // dashboard). Common price = average across every supplier provided.
        $leading = [];
        foreach (["petron", "shell", "caltex"] as $brand) {
            $clean = master_settings_clean_value($values[$brand] ?? "");
            if ($clean !== "" && is_numeric($clean)) {
                $leading[] = (float) $clean;
            }
        }
        $values["average"] = $leading === []
            ? ""
            : master_settings_format_decimal(array_sum($leading) / count($leading));

        // Only auto-fill the common price when the user left it blank.
        if (master_settings_clean_value($values["common_price"] ?? "") === "") {
            $all = [];
            foreach (["petron", "shell", "caltex", "phoenix", "flying_v", "seaoil", "jetti", "my_gas", "independent"] as $brand) {
                $clean = master_settings_clean_value($values[$brand] ?? "");
                if ($clean !== "" && is_numeric($clean)) {
                    $all[] = (float) $clean;
                }
            }
            $values["common_price"] = $all === []
                ? ""
                : master_settings_format_decimal(array_sum($all) / count($all));
        }

        // The legacy weekly date belongs only to the effective-date table.
        if ($entity === "fuel_price" && master_settings_clean_value($values["price_date"] ?? "") === "") {
            $values["price_date"] = master_settings_clean_value($values["effective_from"] ?? "");
        }
    }

    if ($entity === "rates") {
        $rate = master_settings_decimal_value($values["rate"] ?? 0);
        $trips = master_settings_decimal_value($values["trips"] ?? 0);
        $values["revenue"] = master_settings_format_decimal($rate * $trips);
    }

    return ["valid" => true, "message" => "", "values" => $values];
}
