<?php
session_start();

require_once "php/config/config.php";
require_once "php/helpers/auth.php";
require_once "php/helpers/microsoft_auth.php";

function redirectMicrosoftError(string $message): void
{
    header("Location: login?error=" . urlencode($message));
    exit();
}

if (!microsoftAuthConfigured()) {
    redirectMicrosoftError("Microsoft sign-in is not configured yet");
}

if (isset($_GET["error"])) {
    $message = (string) ($_GET["error_description"] ?? $_GET["error"]);
    redirectMicrosoftError(
        $message !== "" ? $message : "Microsoft sign-in failed",
    );
}

$state = (string) ($_GET["state"] ?? "");
$expectedState = (string) ($_SESSION["microsoft_oauth_state"] ?? "");
unset($_SESSION["microsoft_oauth_state"]);

if (
    $state === "" ||
    $expectedState === "" ||
    !hash_equals($expectedState, $state)
) {
    redirectMicrosoftError("Invalid Microsoft sign-in state");
}

$code = trim((string) ($_GET["code"] ?? ""));
if ($code === "") {
    redirectMicrosoftError("Microsoft sign-in code is missing");
}

try {
    $microsoftUser = getMicrosoftUserClaims($code);
} catch (Throwable $exception) {
    redirectMicrosoftError($exception->getMessage());
}

$sql =
    'SELECT user_id, "user_idNumber", user_name, user_fname, user_lname, user_mname, user_email, user_type, user_image, "user_accountStat", user_code FROM "user" WHERE user_email = ?';
$stmt = $conn->prepare($sql);
$stmt->execute([$microsoftUser["email"]]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    redirectMicrosoftError(
        "No local account is linked to this Microsoft work email: " .
            $microsoftUser["email"],
    );
}

if (($user["user_accountStat"] ?? "") === "Inactive") {
    redirectMicrosoftError(
        "Account is inactive. Please contact administrator",
    );
}

setUserSession($user);

header("Location: dashboard");
exit();
