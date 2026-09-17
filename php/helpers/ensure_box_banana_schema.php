<?php

/**
 * Idempotent bootstrap for the Box Bananas billing tables. Cheap to call on
 * every request (all CREATE ... IF NOT EXISTS). Mirrors the customer-billing
 * schema: one statement per generated file, with the entries it consumed so
 * the same trip is not billed twice.
 */
function ensure_box_banana_schema(PDO $conn): void
{
    static $alreadyEnsured = false;
    if ($alreadyEnsured) {
        return;
    }

    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS box_banana_statements (
    statement_id    INTEGER PRIMARY KEY,
    customer_key    TEXT NOT NULL,
    customer_label  TEXT NOT NULL,
    reference       TEXT,
    date_from       DATE NOT NULL,
    date_to         DATE NOT NULL,
    total_amount    NUMERIC NOT NULL DEFAULT 0,
    total_boxes     NUMERIC NOT NULL DEFAULT 0,
    file_name       TEXT NOT NULL,
    file_path       TEXT NOT NULL,
    file_size_bytes BIGINT NOT NULL DEFAULT 0,
    line_count      INTEGER NOT NULL DEFAULT 0,
    requested_by    TEXT,
    requested_at    TIMESTAMP NOT NULL DEFAULT NOW(),
    status          TEXT NOT NULL DEFAULT 'ready'
);
CREATE INDEX IF NOT EXISTS idx_box_banana_statements_requested_at ON box_banana_statements (requested_at DESC);
CREATE TABLE IF NOT EXISTS box_banana_statement_entries (
    statement_id INTEGER NOT NULL REFERENCES box_banana_statements (statement_id) ON DELETE CASCADE,
    entry_id     INTEGER NOT NULL,
    PRIMARY KEY (statement_id, entry_id)
);
CREATE INDEX IF NOT EXISTS idx_box_banana_statement_entries_entry_id ON box_banana_statement_entries (entry_id);
SQL;

    $conn->exec($sql);

    // Per-entry PHP peso charge LOCKED at generation (mirrors billing_invoice_entries)
    // so the billed amount is reproducible even if a rate/fuel price is later edited.
    // manual_charge marks a value finance edited by hand after generation.
    $conn->exec("ALTER TABLE box_banana_statement_entries ADD COLUMN IF NOT EXISTS rate_charge NUMERIC");
    $conn->exec("ALTER TABLE box_banana_statement_entries ADD COLUMN IF NOT EXISTS manual_charge BOOLEAN NOT NULL DEFAULT FALSE");
    $conn->exec("ALTER TABLE box_banana_statement_entries ADD COLUMN IF NOT EXISTS charge_updated_at TIMESTAMP");
    $conn->exec("ALTER TABLE box_banana_statement_entries ADD COLUMN IF NOT EXISTS charge_updated_by TEXT");

    $alreadyEnsured = true;
}
