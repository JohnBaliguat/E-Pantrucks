<?php

function activity_log_display_value($value): string
{
    $value = trim((string) ($value ?? ""));
    return $value !== "" ? $value : "blank";
}

function activity_log_humanize_text(string $value): string
{
    $value = trim($value);
    if ($value === "") {
        return "";
    }

    $value = preg_replace('/[_-]+/', ' ', $value) ?: $value;
    $value = preg_replace('/\s+/', ' ', $value) ?: $value;

    $words = array_map(static function ($word) {
        $lower = strtolower($word);
        if (in_array($lower, ["id", "ip", "emdr", "psacc"], true)) {
            return strtoupper($lower);
        }
        return ucfirst($lower);
    }, explode(" ", $value));

    return implode(" ", $words);
}

function activity_log_humanize_route(string $route): string
{
    $route = trim($route);
    if ($route === "") {
        return "system";
    }

    return activity_log_humanize_text($route);
}

function activity_log_parse_context(string $context): array
{
    $pairs = [];
    foreach (explode(";", $context) as $part) {
        $part = trim($part);
        if ($part === "" || !str_contains($part, "=")) {
            continue;
        }

        [$key, $value] = array_map("trim", explode("=", $part, 2));
        if ($key === "") {
            continue;
        }

        $pairs[$key] = $value;
    }

    return $pairs;
}

function activity_log_human_context(array $context): string
{
    $parts = [];
    foreach ($context as $key => $value) {
        if ($value === "") {
            continue;
        }
        $parts[] = activity_log_humanize_text((string) $key) . " " . $value;
    }
    return implode(", ", $parts);
}

function activity_log_type_label(array $row): string
{
    $type = (string) ($row["activity_type"] ?? "");
    $label = trim((string) ($row["activity_label"] ?? ""));

    $map = [
        "page_view" => "View Page",
        "fetch" => "Fetch Data",
        "action" => "Action",
        "request" => "Request",
        "login" => "Login",
        "login_failed" => "Failed Login",
        "user_update" => "Update User Information",
        "profile_update" => "Update Personal Information",
        "user_password_reset" => "Reset User Password",
    ];

    if (isset($map[$type])) {
        if ($type === "action" && $label !== "" && !preg_match('/^(GET|POST|PUT|DELETE|PATCH)\b/i', $label)) {
            return $label;
        }
        return $map[$type];
    }

    if ($label !== "" && !preg_match('/^(GET|POST|PUT|DELETE|PATCH)\b/i', $label)) {
        return $label;
    }

    return activity_log_humanize_text($type !== "" ? $type : "Activity");
}

function activity_log_human_details(array $row): string
{
    $type = (string) ($row["activity_type"] ?? "");
    $label = trim((string) ($row["activity_label"] ?? ""));
    $route = activity_log_humanize_route((string) ($row["route_name"] ?? ""));
    $method = strtoupper(trim((string) ($row["request_method"] ?? "")));
    $context = activity_log_parse_context((string) ($row["context_summary"] ?? ""));
    $details = [];

    if (!empty($row["details_json"])) {
        $decoded = json_decode((string) $row["details_json"], true);
        if (is_array($decoded)) {
            if (in_array($type, ["user_update", "profile_update"], true) && !empty($decoded["changes"]) && is_array($decoded["changes"])) {
                foreach ($decoded["changes"] as $changeLabel => $change) {
                    $from = activity_log_display_value($change["from"] ?? "");
                    $to = activity_log_display_value($change["to"] ?? "");
                    $details[] = $changeLabel . " changed from " . $from . " to " . $to;
                }
            } elseif ($type === "user_password_reset") {
                $target = trim((string) ($decoded["target_user_name"] ?? $decoded["target_username"] ?? ""));
                $performedBy = trim((string) ($decoded["performed_by"] ?? ""));
                $message = "Password was reset";
                if ($target !== "") {
                    $message .= " for " . $target;
                }
                if ($performedBy !== "") {
                    $message .= " by " . $performedBy;
                }
                $details[] = $message;
            } elseif ($type === "login_failed") {
                $username = trim((string) ($decoded["post"]["username"] ?? $decoded["get"]["username"] ?? ""));
                if ($username !== "") {
                    $details[] = "Failed login attempt using username " . $username;
                }
            } elseif ($type === "login") {
                $details[] = "User signed in successfully";
            }
        }
    }

    if ($details !== []) {
        return implode("; ", $details);
    }

    if ($type === "page_view") {
        return "Viewed " . $route . " page";
    }

    if ($type === "fetch") {
        $suffix = $context !== [] ? " (" . activity_log_human_context($context) . ")" : "";
        return "Viewed data from " . $route . $suffix;
    }

    if ($type === "login_failed") {
        $username = trim((string) ($context["username"] ?? ""));
        return $username !== ""
            ? "Failed login attempt using username " . $username
            : "Failed login attempt";
    }

    if ($type === "login") {
        return "User signed in successfully";
    }

    if ($method === "POST" || in_array($type, ["action", "request"], true)) {
        $action = trim((string) ($context["action"] ?? ""));
        $actionText = $action !== "" ? activity_log_humanize_text($action) : activity_log_type_label($row);
        $suffix = $context !== [] ? " (" . activity_log_human_context($context) . ")" : "";
        return $actionText . " performed in " . $route . $suffix;
    }

    if ($label !== "" && !preg_match('/^(GET|POST|PUT|DELETE|PATCH)\b/i', $label)) {
        return $label;
    }

    if ($context !== []) {
        return activity_log_human_context($context);
    }

    return "Opened " . $route;
}

function activity_log_filters_from_input(array $source): array
{
    return [
        "user" => trim((string) ($source["user"] ?? "")),
        "activity" => trim((string) ($source["activity"] ?? $source["type"] ?? "")),
        "user_type" => trim((string) ($source["user_type"] ?? "")),
        "date_from" => trim((string) ($source["date_from"] ?? "")),
        "date_to" => trim((string) ($source["date_to"] ?? "")),
    ];
}

function activity_log_build_where(array $filters): array
{
    $where = [];
    $params = [];

    if (($filters["user"] ?? "") !== "") {
        $where[] = '(COALESCE(user_name, \'\') ILIKE ? OR COALESCE(user_id_number, \'\') ILIKE ?)';
        $like = "%" . $filters["user"] . "%";
        $params[] = $like;
        $params[] = $like;
    }

    if (($filters["activity"] ?? "") !== "") {
        $where[] = 'activity_type = ?';
        $params[] = $filters["activity"];
    }

    if (($filters["user_type"] ?? "") !== "") {
        $where[] = 'user_type = ?';
        $params[] = $filters["user_type"];
    }

    if (($filters["date_from"] ?? "") !== "" && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters["date_from"])) {
        $where[] = 'DATE(created_at) >= ?';
        $params[] = $filters["date_from"];
    }

    if (($filters["date_to"] ?? "") !== "" && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters["date_to"])) {
        $where[] = 'DATE(created_at) <= ?';
        $params[] = $filters["date_to"];
    }

    return [$where, $params];
}
