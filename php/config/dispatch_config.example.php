<?php

/**
 * Copy this file to dispatch_config.php and fill in your real credentials.
 * dispatch_config.php is git-ignored and must never be committed.
 *
 * Connection to the secondary "dispatch" Supabase database (the operations /
 * dispatch system). This is a SEPARATE database from the E-Pantrucks app DB,
 * so it is exposed through a function instead of overwriting the global $conn.
 */
function dispatch_db(): ?PDO
{
    static $pdo = null;
    static $tried = false;

    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if ($tried) {
        return null; // already failed once this request; don't keep retrying
    }
    $tried = true;

    $dsn = 'pgsql:host=YOUR_DISPATCH_HOST;port=5432;dbname=postgres';
    $user = 'YOUR_DISPATCH_DB_USER';
    $pass = 'YOUR_DISPATCH_DB_PASSWORD';

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5,
        ]);
    } catch (Throwable $e) {
        $pdo = null;
    }

    return $pdo;
}
