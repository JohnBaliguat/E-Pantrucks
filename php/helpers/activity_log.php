<?php

require_once __DIR__ . "/ensure_activity_log_schema.php";

function activity_log_string($value): string
{
    return trim((string) ($value ?? ""));
}

function activity_log_client_ip(): string
{
    foreach (["HTTP_X_FORWARDED_FOR", "REMOTE_ADDR"] as $key) {
        $value = activity_log_string($_SERVER[$key] ?? "");
        if ($value === "") {
            continue;
        }
        if ($key === "HTTP_X_FORWARDED_FOR") {
            $parts = array_map("trim", explode(",", $value));
            return $parts[0] ?? "";
        }
        return $value;
    }

    return "";
}

function activity_log_detect_device(string $userAgent): array
{
    $ua = strtolower($userAgent);

    $deviceType = "Desktop";
    if (str_contains($ua, "tablet") || str_contains($ua, "ipad")) {
        $deviceType = "Tablet";
    } elseif (str_contains($ua, "mobile") || str_contains($ua, "android") || str_contains($ua, "iphone")) {
        $deviceType = "Mobile";
    } elseif (str_contains($ua, "bot") || str_contains($ua, "crawl") || str_contains($ua, "spider")) {
        $deviceType = "Bot";
    }

    $browser = "Unknown";
    if (str_contains($ua, "edg/")) {
        $browser = "Microsoft Edge";
    } elseif (str_contains($ua, "opr/") || str_contains($ua, "opera")) {
        $browser = "Opera";
    } elseif (str_contains($ua, "chrome/")) {
        $browser = "Google Chrome";
    } elseif (str_contains($ua, "firefox/")) {
        $browser = "Mozilla Firefox";
    } elseif (str_contains($ua, "safari/")) {
        $browser = "Safari";
    } elseif (str_contains($ua, "msie") || str_contains($ua, "trident/")) {
        $browser = "Internet Explorer";
    }

    $os = "Unknown";
    if (str_contains($ua, "windows")) {
        $os = "Windows";
    } elseif (str_contains($ua, "android")) {
        $os = "Android";
    } elseif (str_contains($ua, "iphone") || str_contains($ua, "ipad") || str_contains($ua, "ios")) {
        $os = "iOS";
    } elseif (str_contains($ua, "mac os") || str_contains($ua, "macintosh")) {
        $os = "macOS";
    } elseif (str_contains($ua, "linux")) {
        $os = "Linux";
    }

    return [
        "device_type" => $deviceType,
        "browser_name" => $browser,
        "os_name" => $os,
        "device_name" => trim($deviceType . " / " . $os . " / " . $browser, " /"),
    ];
}

function activity_log_route_name(): string
{
    $route = activity_log_string($_GET["route"] ?? "");
    if ($route !== "") {
        return $route;
    }

    $uriPath = parse_url((string) ($_SERVER["REQUEST_URI"] ?? ""), PHP_URL_PATH);
    $uriPath = activity_log_string($uriPath);
    if ($uriPath === "" || $uriPath === "/") {
        return "";
    }

    return trim(basename($uriPath), "/");
}

function activity_log_context_summary(): string
{
    $keys = ["action", "entity", "entry_type", "customer", "id", "data_id", "code"];
    $parts = [];

    foreach ($keys as $key) {
        $value = activity_log_string($_POST[$key] ?? $_GET[$key] ?? "");
        if ($value === "") {
            continue;
        }
        if (in_array($key, ["id", "data_id"], true) && !ctype_digit($value)) {
            continue;
        }
        $parts[] = $key . "=" . substr($value, 0, 120);
    }

    return implode("; ", $parts);
}

function activity_log_details_json(): string
{
    $sanitize = static function (array $source): array {
        $result = [];
        $blocked = [
            "pass",
            "password",
            "currentPassword",
            "newPassword",
            "confirmPassword",
            "user_pass",
        ];

        foreach ($source as $key => $value) {
            $keyString = (string) $key;
            if (in_array($keyString, $blocked, true)) {
                $result[$keyString] = "[REDACTED]";
                continue;
            }

            if (is_array($value)) {
                $result[$keyString] = "[ARRAY]";
                continue;
            }

            $result[$keyString] = substr(trim((string) $value), 0, 500);
        }

        return $result;
    };

    $details = [
        "get" => $sanitize($_GET ?? []),
        "post" => $sanitize($_POST ?? []),
        "files" => array_keys($_FILES ?? []),
    ];

    return json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: "";
}

function activity_log_default_label(): string
{
    $method = strtoupper(activity_log_string($_SERVER["REQUEST_METHOD"] ?? "GET"));
    $route = activity_log_route_name();
    $script = basename((string) ($_SERVER["SCRIPT_NAME"] ?? ""));
    $context = activity_log_context_summary();
    $base = $route !== "" ? $route : $script;
    $base = $base !== "" ? $base : "request";

    if ($context !== "") {
        return $method . " " . $base . " (" . $context . ")";
    }

    return $method . " " . $base;
}

function activity_log_write(PDO $conn, array $override = []): void
{
    ensure_activity_log_schema($conn);

    $userAgent = activity_log_string($_SERVER["HTTP_USER_AGENT"] ?? "");
    $device = activity_log_detect_device($userAgent);

    $data = [
        "user_id" => $_SESSION["user_id"] ?? null,
        "user_id_number" => activity_log_string($_SESSION["user_idNumber"] ?? ""),
        "user_name" => activity_log_string($_SESSION["user_name"] ?? ""),
        "user_type" => activity_log_string($_SESSION["user_type"] ?? ""),
        "activity_type" => "request",
        "activity_label" => activity_log_default_label(),
        "request_method" => activity_log_string($_SERVER["REQUEST_METHOD"] ?? ""),
        "route_name" => activity_log_route_name(),
        "request_uri" => activity_log_string($_SERVER["REQUEST_URI"] ?? ""),
        "referrer" => activity_log_string($_SERVER["HTTP_REFERER"] ?? ""),
        "ip_address" => activity_log_client_ip(),
        "device_type" => $device["device_type"],
        "device_name" => $device["device_name"],
        "browser_name" => $device["browser_name"],
        "os_name" => $device["os_name"],
        // Do NOT capture a client-supplied device name: it was free text a user
        // could accidentally fill with sensitive data (e.g. a password). The
        // server-detected device_name (type/OS/browser) is used instead.
        "client_device_name" => "",
        "user_agent" => $userAgent,
        "session_id" => activity_log_string(session_id()),
        "context_summary" => activity_log_context_summary(),
        "details_json" => activity_log_details_json(),
    ];

    foreach ($override as $key => $value) {
        $data[$key] = $value;
    }

    $stmt = $conn->prepare(
        'INSERT INTO user_activity_log (
            activity_id, user_id, user_id_number, user_name, user_type,
            activity_type, activity_label, request_method, route_name, request_uri,
            referrer, ip_address, device_type, device_name, browser_name, os_name,
            client_device_name, user_agent, session_id, context_summary, details_json
        ) VALUES (
            COALESCE((SELECT MAX(activity_id) FROM user_activity_log), 0) + 1,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )'
    );

    $stmt->execute([
        $data["user_id"] !== null && $data["user_id"] !== "" ? (int) $data["user_id"] : null,
        $data["user_id_number"] !== "" ? $data["user_id_number"] : null,
        $data["user_name"] !== "" ? $data["user_name"] : null,
        $data["user_type"] !== "" ? $data["user_type"] : null,
        $data["activity_type"],
        $data["activity_label"],
        $data["request_method"] !== "" ? $data["request_method"] : null,
        $data["route_name"] !== "" ? $data["route_name"] : null,
        $data["request_uri"] !== "" ? $data["request_uri"] : null,
        $data["referrer"] !== "" ? $data["referrer"] : null,
        $data["ip_address"] !== "" ? $data["ip_address"] : null,
        $data["device_type"] !== "" ? $data["device_type"] : null,
        $data["device_name"] !== "" ? $data["device_name"] : null,
        $data["browser_name"] !== "" ? $data["browser_name"] : null,
        $data["os_name"] !== "" ? $data["os_name"] : null,
        $data["client_device_name"] !== "" ? $data["client_device_name"] : null,
        $data["user_agent"] !== "" ? $data["user_agent"] : null,
        $data["session_id"] !== "" ? $data["session_id"] : null,
        $data["context_summary"] !== "" ? $data["context_summary"] : null,
        $data["details_json"] !== "" ? $data["details_json"] : null,
    ]);
}

function activity_log_auto_request(PDO $conn): void
{
    static $alreadyLogged = false;
    if ($alreadyLogged || !isset($_SESSION["user_id"])) {
        return;
    }

    $alreadyLogged = true;

    $scriptName = strtolower(basename((string) ($_SERVER["SCRIPT_NAME"] ?? "")));
    $method = strtoupper(activity_log_string($_SERVER["REQUEST_METHOD"] ?? "GET"));

    $type = "request";
    if ($method === "POST") {
        $type = "action";
    } elseif (str_contains($scriptName, "fetch")) {
        $type = "fetch";
    } elseif ($scriptName === "index.php" || $scriptName === "") {
        $type = "page_view";
    }

    activity_log_write($conn, [
        "activity_type" => $type,
    ]);
}
