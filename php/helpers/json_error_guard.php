<?php

/**
 * Ensures every AJAX endpoint responds with JSON, even when PHP would
 * normally emit an HTML error page (uncaught exception, fatal error,
 * stray warning, or echoed text from a helper).
 *
 * Without this guard, jQuery / fetch parses the HTML response as JSON,
 * fails, and shows the generic "Unable to reach server" alert — hiding
 * the real problem.
 *
 * Include this file as the very first line of an endpoint:
 *     require_once __DIR__ . "/../helpers/json_error_guard.php";
 */

if (defined('OPS_JSON_GUARD_INSTALLED')) {
    return;
}
define('OPS_JSON_GUARD_INSTALLED', true);

@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
error_reporting(E_ALL);

ob_start();

function ops_json_guard_collect_buffer(): string
{
    $output = '';
    while (ob_get_level() > 0) {
        $chunk = ob_get_clean();
        if ($chunk === false) {
            break;
        }
        $output .= (string) $chunk;
    }
    return $output;
}

function ops_json_guard_emit(string $message): void
{
    ops_json_guard_collect_buffer();
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode([
        'success' => false,
        'message' => $message,
    ]);
}

set_exception_handler(function ($e) {
    ops_json_guard_emit('Server error: ' . $e->getMessage());
});

register_shutdown_function(function () {
    $err = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if ($err !== null && in_array($err['type'], $fatalTypes, true)) {
        ops_json_guard_emit('Server error: ' . $err['message']);
        return;
    }

    $output = ops_json_guard_collect_buffer();
    $trimmed = ltrim($output);

    if ($trimmed === '') {
        return;
    }

    if ($trimmed[0] === '{' || $trimmed[0] === '[') {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo $output;
        return;
    }

    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . trim(strip_tags($output)),
    ]);
});
