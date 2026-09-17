<?php

function getEnvValue(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value !== false && $value !== "") {
        return $value;
    }

    static $envValues = null;
    if ($envValues === null) {
        $envPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ".env";
        $envValues = is_file($envPath)
            ? parse_ini_file($envPath, false, INI_SCANNER_RAW)
            : [];
    }

    if (isset($envValues[$key]) && $envValues[$key] !== "") {
        return (string) $envValues[$key];
    }

    return $default;
}

function getMicrosoftAuthConfig(): array
{
    $tenant = trim((string) getEnvValue("MICROSOFT_TENANT_ID", "organizations"));
    $clientId = trim((string) getEnvValue("MICROSOFT_CLIENT_ID", "b74d2891-7ec3-4967-af45-0036556f8978"));
    $clientSecret = trim((string) getEnvValue("MICROSOFT_CLIENT_SECRET", "cx18Q~CrZWihcVopBV1bbu7q_CdUr.FmwXqPgaou"));
    $configuredRedirectUri = trim((string) getEnvValue("MICROSOFT_REDIRECT_URI", "https://dataentry.fleet-pantrucks.com/microsoft-callback"));
    $allowedTenantId = trim((string) getEnvValue("MICROSOFT_ALLOWED_TENANT_ID", "95b1c458-41cb-489b-9c8f-b1d5764c60fd"));

    $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
        ? "https"
        : "http";
    $host = $_SERVER["HTTP_HOST"] ?? "localhost";
    $basePath = rtrim(dirname($_SERVER["SCRIPT_NAME"] ?? "/index.php"), "/\\");
    $defaultRedirectUri =
        $scheme .
        "://" .
        $host .
        ($basePath === "" ? "" : $basePath) .
        "/microsoft-callback";

    return [
        "tenant" => $tenant,
        "client_id" => $clientId,
        "client_secret" => $clientSecret,
        "redirect_uri" =>
            $configuredRedirectUri !== ""
                ? $configuredRedirectUri
                : $defaultRedirectUri,
        "allowed_tenant_id" => $allowedTenantId,
        "scope" => "openid profile email",
    ];
}

function microsoftAuthConfigured(): bool
{
    $config = getMicrosoftAuthConfig();
    return $config["client_id"] !== "" && $config["client_secret"] !== "";
}

function buildMicrosoftAuthorizeUrl(): string
{
    $config = getMicrosoftAuthConfig();
    $state = bin2hex(random_bytes(16));
    $_SESSION["microsoft_oauth_state"] = $state;

    $query = http_build_query([
        "client_id" => $config["client_id"],
        "response_type" => "code",
        "redirect_uri" => $config["redirect_uri"],
        "response_mode" => "query",
        "scope" => $config["scope"],
        "state" => $state,
        "prompt" => "select_account",
    ]);

    return "https://login.microsoftonline.com/" .
        rawurlencode($config["tenant"]) .
        "/oauth2/v2.0/authorize?" .
        $query;
}

function exchangeMicrosoftCodeForToken(string $code): array
{
    $config = getMicrosoftAuthConfig();
    $tokenUrl =
        "https://login.microsoftonline.com/" .
        rawurlencode($config["tenant"]) .
        "/oauth2/v2.0/token";

    $postFields = http_build_query([
        "client_id" => $config["client_id"],
        "client_secret" => $config["client_secret"],
        "grant_type" => "authorization_code",
        "code" => $code,
        "redirect_uri" => $config["redirect_uri"],
        "scope" => $config["scope"],
    ]);

    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/x-www-form-urlencoded",
        ],
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException(
            "Microsoft token request failed: " . $curlError,
        );
    }

    $payload = json_decode($response, true);
    if (!is_array($payload)) {
        throw new RuntimeException("Invalid Microsoft token response");
    }

    if ($statusCode >= 400 || isset($payload["error"])) {
        $message = $payload["error_description"] ??
            $payload["error"] ??
            "Microsoft sign-in failed";
        throw new RuntimeException($message);
    }

    return $payload;
}

function decodeMicrosoftJwtPayload(string $jwt): array
{
    $parts = explode(".", $jwt);
    if (count($parts) < 2) {
        throw new RuntimeException("Invalid Microsoft ID token");
    }

    $payload = $parts[1];
    $payload .= str_repeat("=", (4 - strlen($payload) % 4) % 4);
    $decoded = base64_decode(strtr($payload, "-_", "+/"), true);

    if ($decoded === false) {
        throw new RuntimeException("Unable to decode Microsoft ID token");
    }

    $claims = json_decode($decoded, true);
    if (!is_array($claims)) {
        throw new RuntimeException("Invalid Microsoft ID token claims");
    }

    return $claims;
}

function getMicrosoftUserClaims(string $code): array
{
    $tokenData = exchangeMicrosoftCodeForToken($code);
    if (empty($tokenData["id_token"])) {
        throw new RuntimeException("Microsoft did not return an ID token");
    }

    $claims = decodeMicrosoftJwtPayload($tokenData["id_token"]);
    $email = trim(
        (string) ($claims["preferred_username"] ?? $claims["email"] ?? ""),
    );

    if ($email === "") {
        throw new RuntimeException("Microsoft account email was not provided");
    }

    $config = getMicrosoftAuthConfig();
    if (
        $config["allowed_tenant_id"] !== "" &&
        (($claims["tid"] ?? "") !== $config["allowed_tenant_id"])
    ) {
        throw new RuntimeException(
            "This Microsoft tenant is not allowed for this application",
        );
    }

    return [
        "email" => $email,
        "tenant_id" => (string) ($claims["tid"] ?? ""),
        "name" => (string) ($claims["name"] ?? $email),
    ];
}

