<?php

/**
 * App-wide key/value settings (admin-controlled toggles), separate from per-user
 * user_preferences. One row per setting key. Cheap to call — the schema is ensured
 * once per request and reads degrade to the default when the table is absent.
 */
function ensure_app_settings_schema(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS app_settings (
            setting_key   TEXT PRIMARY KEY,
            setting_value TEXT,
            updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )"
    );
    $done = true;
}

/** Raw string value for a setting, or $default when unset/unavailable. */
function app_setting_get(PDO $conn, string $key, string $default = ""): string
{
    try {
        ensure_app_settings_schema($conn);
        $stmt = $conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $v = $stmt->fetchColumn();
        return ($v !== false && $v !== null) ? (string) $v : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

/** Truthy interpretation of a setting (1/true/yes/on). Defaults to false. */
function app_setting_bool(PDO $conn, string $key): bool
{
    $v = strtolower(trim(app_setting_get($conn, $key, "0")));
    return in_array($v, ["1", "true", "yes", "on"], true);
}

/** Upsert a setting value. */
function app_setting_set(PDO $conn, string $key, string $value): void
{
    ensure_app_settings_schema($conn);
    $stmt = $conn->prepare(
        "INSERT INTO app_settings (setting_key, setting_value, updated_at)
         VALUES (?, ?, NOW())
         ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = NOW()"
    );
    $stmt->execute([$key, $value]);
}
