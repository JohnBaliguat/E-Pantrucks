-- =====================================================================
-- Customer billing (SAP ZPSO sales-order upload) support.
--   * fuel_price.dollar_conversion: USD->PHP forex rate per fuel-matrix week
--   * billing_invoices / billing_invoice_entries: tracks each generated
--     customer billing file and which operation entries were billed, so a
--     trip is billed only once.
--
-- Run once in the Supabase SQL editor (or psql) against your DB.
-- =====================================================================

ALTER TABLE fuel_price ADD COLUMN IF NOT EXISTS dollar_conversion text;

CREATE TABLE IF NOT EXISTS billing_invoices (
    invoice_id      INTEGER PRIMARY KEY,
    customer_key    TEXT NOT NULL,
    customer_label  TEXT NOT NULL,
    reference       TEXT,
    date_from       DATE NOT NULL,
    date_to         DATE NOT NULL,
    forex_rate      NUMERIC,
    file_name       TEXT NOT NULL,
    file_path       TEXT NOT NULL,
    file_size_bytes BIGINT NOT NULL DEFAULT 0,
    line_count      INTEGER NOT NULL DEFAULT 0,
    requested_by    TEXT,
    requested_at    TIMESTAMP NOT NULL DEFAULT NOW(),
    status          TEXT NOT NULL DEFAULT 'ready'
);

CREATE INDEX IF NOT EXISTS idx_billing_invoices_requested_at ON billing_invoices (requested_at DESC);

CREATE TABLE IF NOT EXISTS billing_invoice_entries (
    invoice_id INTEGER NOT NULL REFERENCES billing_invoices (invoice_id) ON DELETE CASCADE,
    entry_id   INTEGER NOT NULL,
    PRIMARY KEY (invoice_id, entry_id)
);

CREATE INDEX IF NOT EXISTS idx_billing_invoice_entries_entry_id ON billing_invoice_entries (entry_id);
