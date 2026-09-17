<?php

function ensure_activity_log_schema(PDO $conn): void
{
    static $alreadyEnsured = false;
    if ($alreadyEnsured) {
        return;
    }

    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS user_activity_log (
    activity_id     INTEGER PRIMARY KEY,
    user_id         INTEGER,
    user_id_number  TEXT,
    user_name       TEXT,
    user_type       TEXT,
    activity_type   TEXT NOT NULL,
    activity_label  TEXT NOT NULL,
    request_method  TEXT,
    route_name      TEXT,
    request_uri     TEXT,
    referrer        TEXT,
    ip_address      TEXT,
    device_type     TEXT,
    device_name     TEXT,
    browser_name    TEXT,
    os_name         TEXT,
    client_device_name TEXT,
    user_agent      TEXT,
    session_id      TEXT,
    context_summary TEXT,
    details_json    TEXT,
    created_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_user_activity_created_at ON user_activity_log (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_user_activity_user_id_number ON user_activity_log (user_id_number);
CREATE INDEX IF NOT EXISTS idx_user_activity_activity_type ON user_activity_log (activity_type);
SQL;

    $conn->exec($sql);
    $conn->exec("ALTER TABLE user_activity_log ADD COLUMN IF NOT EXISTS client_device_name TEXT");
    $conn->exec("ALTER TABLE user_activity_log ADD COLUMN IF NOT EXISTS details_json TEXT");
    $alreadyEnsured = true;
}
