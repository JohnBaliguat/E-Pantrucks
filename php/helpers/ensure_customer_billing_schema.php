<?php

/**
 * Idempotent bootstrap for customer-billing tables + the fuel_price forex
 * column. Cheap to call on every request (all CREATE/ADD IF NOT EXISTS).
 */
function ensure_customer_billing_schema(PDO $conn): void
{
    static $alreadyEnsured = false;
    if ($alreadyEnsured) {
        return;
    }

    $conn->exec("ALTER TABLE fuel_price ADD COLUMN IF NOT EXISTS dollar_conversion text");
    // Which billing activity an invoice represents (hauling / chassis / genset /
    // container_van / fuel). Defaults to hauling for existing rows.
    $conn->exec("ALTER TABLE billing_invoices ADD COLUMN IF NOT EXISTS activity text NOT NULL DEFAULT 'hauling'");
    // Human-friendly document number (PREFIX-YYYY-NNN) printed on the file so a
    // billing the customer returns can be looked up. Plus a returned marker.
    $conn->exec("ALTER TABLE billing_invoices ADD COLUMN IF NOT EXISTS document_no text");
    $conn->exec("ALTER TABLE billing_invoices ADD COLUMN IF NOT EXISTS returned_at timestamp");
    $conn->exec("ALTER TABLE billing_invoices ADD COLUMN IF NOT EXISTS returned_remarks text");
    // Biller-chosen Document Date / Billing Date stamped on the SAP file (cells 5 & 6).
    // NULL = legacy invoices generated before this feature (they used the generation day).
    $conn->exec("ALTER TABLE billing_invoices ADD COLUMN IF NOT EXISTS document_date date");
    // Unique per document number (partial index skips legacy NULLs until backfilled).
    $conn->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_billing_invoices_document_no ON billing_invoices (document_no) WHERE document_no IS NOT NULL");
    // Per-entry PHP peso charge, LOCKED at generation time so the billed amount is
    // reproducible even if the rate matrix / fuel price is later edited. Nullable:
    // legacy rows and pipelines that don't record it stay NULL (fall back to recompute).
    // manual_charge marks a value finance edited by hand after generation.
    $conn->exec("ALTER TABLE billing_invoice_entries ADD COLUMN IF NOT EXISTS rate_charge NUMERIC");
    $conn->exec("ALTER TABLE billing_invoice_entries ADD COLUMN IF NOT EXISTS manual_charge BOOLEAN NOT NULL DEFAULT FALSE");
    $conn->exec("ALTER TABLE billing_invoice_entries ADD COLUMN IF NOT EXISTS charge_updated_at TIMESTAMP");
    $conn->exec("ALTER TABLE billing_invoice_entries ADD COLUMN IF NOT EXISTS charge_updated_by TEXT");

    $sql = <<<SQL
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
CREATE TABLE IF NOT EXISTS billing_invoice_entry_forex (
    invoice_id INTEGER NOT NULL REFERENCES billing_invoices (invoice_id) ON DELETE CASCADE,
    entry_id   INTEGER NOT NULL,
    forex_rate NUMERIC NOT NULL CHECK (forex_rate > 0),
    PRIMARY KEY (invoice_id, entry_id)
);
SQL;

    $conn->exec($sql);
    $alreadyEnsured = true;
}
