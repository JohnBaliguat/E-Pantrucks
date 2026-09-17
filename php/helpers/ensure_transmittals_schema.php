<?php

/**
 * Idempotent bootstrap: makes sure transmittals + transmittal_entries
 * tables exist before any transmittal endpoint touches them. Cheap to
 * call on every request because every statement is CREATE IF NOT EXISTS.
 */
function ensure_transmittals_schema(PDO $conn): void
{
    static $alreadyEnsured = false;
    if ($alreadyEnsured) {
        return;
    }

    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS transmittals (
    transmittal_id    INTEGER PRIMARY KEY,
    label             TEXT NOT NULL,
    entry_type        TEXT,
    date_from         DATE NOT NULL,
    date_to           DATE NOT NULL,
    customer_filter   TEXT,
    include_transmitted BOOLEAN NOT NULL DEFAULT FALSE,
    file_name         TEXT NOT NULL,
    file_path         TEXT NOT NULL,
    file_size_bytes   BIGINT NOT NULL DEFAULT 0,
    record_count      INTEGER NOT NULL DEFAULT 0,
    requested_by      TEXT,
    requested_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    expires_at        TIMESTAMP NOT NULL,
    status            TEXT NOT NULL DEFAULT 'ready'
);

CREATE INDEX IF NOT EXISTS idx_transmittals_requested_at ON transmittals (requested_at DESC);
CREATE INDEX IF NOT EXISTS idx_transmittals_status ON transmittals (status);

CREATE TABLE IF NOT EXISTS transmittal_entries (
    transmittal_id INTEGER NOT NULL REFERENCES transmittals (transmittal_id) ON DELETE CASCADE,
    entry_id       INTEGER NOT NULL,
    PRIMARY KEY (transmittal_id, entry_id)
);

CREATE INDEX IF NOT EXISTS idx_transmittal_entries_entry_id ON transmittal_entries (entry_id);
SQL;

    $conn->exec($sql);
    $alreadyEnsured = true;
}
