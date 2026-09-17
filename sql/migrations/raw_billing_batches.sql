-- =====================================================================
-- RAW for Billing batches: tracks each generated RAW billing export and
-- which operations entries were included, so already-billed records can be
-- excluded from the next generation.
--
-- Run once in the Supabase SQL editor (or psql) against your DB.
-- =====================================================================

CREATE TABLE IF NOT EXISTS raw_billing_batches (
    batch_id          INTEGER PRIMARY KEY,
    label             TEXT NOT NULL,
    date_from         DATE NOT NULL,
    date_to           DATE NOT NULL,
    customer_filter   TEXT,
    format            TEXT NOT NULL DEFAULT 'xlsx',
    include_billed    BOOLEAN NOT NULL DEFAULT FALSE,
    file_name         TEXT NOT NULL,
    file_path         TEXT NOT NULL,
    file_size_bytes   BIGINT NOT NULL DEFAULT 0,
    record_count      INTEGER NOT NULL DEFAULT 0,
    requested_by      TEXT,
    requested_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    status            TEXT NOT NULL DEFAULT 'ready'
);

CREATE INDEX IF NOT EXISTS idx_raw_billing_batches_requested_at
    ON raw_billing_batches (requested_at DESC);

CREATE TABLE IF NOT EXISTS raw_billing_batch_entries (
    batch_id  INTEGER NOT NULL
        REFERENCES raw_billing_batches (batch_id) ON DELETE CASCADE,
    entry_id  INTEGER NOT NULL,
    PRIMARY KEY (batch_id, entry_id)
);

CREATE INDEX IF NOT EXISTS idx_raw_billing_batch_entries_entry_id
    ON raw_billing_batch_entries (entry_id);
