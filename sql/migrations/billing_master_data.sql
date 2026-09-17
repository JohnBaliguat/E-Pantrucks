-- =====================================================================
-- Billing master data: Fuel Price, Service Material, Profit Center, and
-- Rates lookup tables managed from the Finance/Billing master data view.
--
-- Run once in the Supabase SQL editor (or psql) against your DB.
-- =====================================================================

CREATE TABLE IF NOT EXISTS fuel_price (
    id          INTEGER PRIMARY KEY,
    price_date  TEXT NOT NULL,
    petron      TEXT,
    shell       TEXT,
    caltex      TEXT,
    average     TEXT
);

CREATE TABLE IF NOT EXISTS service_material (
    id                       INTEGER PRIMARY KEY,
    revenue_stream           TEXT NOT NULL,
    material_code            TEXT NOT NULL,
    material_description     TEXT NOT NULL,
    rate_type                TEXT,
    tax_class                TEXT,
    profit_center            TEXT,
    account_assignment       TEXT,
    gen_item_category_group  TEXT,
    item_category_group2     TEXT
);

CREATE TABLE IF NOT EXISTS profit_center (
    id                  INTEGER PRIMARY KEY,
    profit_center_code  TEXT NOT NULL,
    controlling_area    TEXT,
    valid_from_date      TEXT,
    valid_to_date        TEXT,
    name                TEXT NOT NULL,
    long_text           TEXT,
    person_responsible  TEXT,
    department          TEXT,
    profit_center_group TEXT,
    segment             TEXT
);

CREATE TABLE IF NOT EXISTS rates (
    id                    INTEGER PRIMARY KEY,
    origin                TEXT NOT NULL,
    packing_house         TEXT,
    port_of_destination   TEXT,
    loc_code              TEXT,
    rate_code             TEXT,
    rate                  TEXT,
    trips                 TEXT,
    revenue               TEXT
);
