<?php

/**
 * Returns null when the value is null or an empty/whitespace string.
 *
 * PostgreSQL rejects empty strings for date/time/timestamp columns
 * (SQLSTATE 22007). Wrap any optional date/time bind value in this helper
 * so PDO sends SQL NULL instead.
 */
function db_nullable($value)
{
    if ($value === null) {
        return null;
    }
    return trim((string) $value) === "" ? null : $value;
}

/**
 * Apply db_nullable() to every value in an array. Use when binding a long
 * INSERT/UPDATE parameter list where any empty string would cause PostgreSQL
 * to fail on numeric/date/time columns (SQLSTATE 22007 / 22P02).
 */
function db_nullable_all(array $values): array
{
    return array_map('db_nullable', $values);
}
