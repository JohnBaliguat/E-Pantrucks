<?php

/**
 * Idempotent bootstrap for the "flag for update" queue: billing users tag an
 * operations record that needs its data corrected (with a remark); Admin/User
 * see the open flags on the For Update page and open the record in its entry
 * form to fix it, then resolve the flag.
 */
function ensure_data_update_flags_schema(PDO $conn): void
{
    static $alreadyEnsured = false;
    if ($alreadyEnsured) {
        return;
    }

    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS data_update_flags (
    flag_id       INTEGER PRIMARY KEY,
    entry_id      INTEGER NOT NULL,
    entry_type    TEXT,
    segment       TEXT,
    remarks       TEXT,
    status        TEXT NOT NULL DEFAULT 'open',
    flagged_by    TEXT,
    flagged_at    TIMESTAMP NOT NULL DEFAULT NOW(),
    resolved_by   TEXT,
    resolved_at   TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_data_update_flags_status ON data_update_flags (status, flagged_at DESC);
CREATE INDEX IF NOT EXISTS idx_data_update_flags_entry ON data_update_flags (entry_id);
SQL;

    $conn->exec($sql);
    // Has the billing user who flagged it seen that it was resolved? Drives the
    // "your flagged record has been updated" notification on the billing bell.
    $conn->exec("ALTER TABLE data_update_flags ADD COLUMN IF NOT EXISTS flagger_seen BOOLEAN NOT NULL DEFAULT FALSE");
    $conn->exec("ALTER TABLE data_update_flags ADD COLUMN IF NOT EXISTS field_notes JSONB NOT NULL DEFAULT '[]'::jsonb");
    $alreadyEnsured = true;
}
