<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";

header("Content-Type: text/plain; charset=utf-8");

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

try {
    $conn->exec($sql);
} catch (PDOException $e) {
    http_response_code(500);
    echo "Migration FAILED:\n" . $e->getMessage();
    exit;
}

$tables = $conn->query("
    SELECT table_schema, table_name
    FROM information_schema.tables
    WHERE table_name IN ('transmittals', 'transmittal_entries')
    ORDER BY table_name
")->fetchAll(PDO::FETCH_ASSOC);

echo "Migration applied successfully.\n\nTables now present:\n";
foreach ($tables as $row) {
    echo "  - {$row['table_schema']}.{$row['table_name']}\n";
}

if (empty($tables)) {
    echo "  (none found — schema search_path may not include where they were created)\n";
}
