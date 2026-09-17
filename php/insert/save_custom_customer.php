<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/unified_billing.php";
require_once __DIR__ . "/../helpers/custom_billing_customers.php";

header("Content-Type: application/json; charset=utf-8");
ensure_custom_customer_schema($conn);

$fail = static function (string $msg): void {
    echo json_encode(["success" => false, "message" => $msg]);
    exit();
};

$payload = json_decode((string) ($_POST["customer"] ?? ""), true);
if (!is_array($payload)) {
    $fail("Invalid payload.");
}

$mode = ($_POST["mode"] ?? "create") === "update" ? "update" : "create";

// --- Customer key: slug, unique, never shadowing a built-in customer. ---
$key = strtolower(trim((string) ($payload["customer_key"] ?? "")));
$key = preg_replace('/[^a-z0-9_]+/', "_", $key);
$key = trim((string) $key, "_");
if ($key === "") {
    $fail("Customer key is required (letters, numbers, underscores).");
}

$customKeys = array_keys(custom_billing_customers_config($conn));
$reserved = array_diff(array_keys(unified_billing_customers()), $customKeys);

if ($mode === "create") {
    if (in_array($key, $reserved, true)) {
        $fail("The key \"$key\" is a built-in customer. Choose a different key.");
    }
    if (in_array($key, $customKeys, true)) {
        $fail("A customer with the key \"$key\" already exists.");
    }
} else {
    if (!in_array($key, $customKeys, true)) {
        $fail("Cannot update \"$key\" — it is not a user-added customer.");
    }
}

// --- Required content. ---
$label = trim((string) ($payload["label"] ?? ""));
if ($label === "") {
    $fail("Customer name (label) is required.");
}
// Pricing is set separately in the Rate Matrix (per lane/date), so no flat rate is
// captured here — a customer with no matrix lanes simply prices at 0 until finance
// adds its rates in Master Data → Rate Matrix.
$segment = trim((string) ($payload["segment"] ?? ""));
$match = trim((string) ($payload["customer_match"] ?? ""));
if ($segment === "" && $match === "") {
    $fail("Set a Segment and/or a Customer Match so the customer's trips can be selected — otherwise the invoice would be empty.");
}

// --- Assemble the stored row from defaults + submitted fields. ---
$defaults = custom_customer_defaults();
$row = ["customer_key" => $key, "label" => $label];
foreach (custom_customer_fields() as $f) {
    $val = trim((string) ($payload[$f] ?? ""));
    if ($val === "" && isset($defaults[$f])) {
        $val = $defaults[$f];
    }
    $row[$f] = $val;
}

// Affiliate toggle drives the SAP Distribution Channel (30 affiliate / 20 not),
// mirroring the Customer SAP Codes tab convention.
$affiliate = in_array(strtolower(trim((string) ($payload["affiliate"] ?? ""))), ["1", "true", "on", "yes"], true);
$row["distribution_channel"] = $affiliate ? "30" : "20";
$row["document_currency"] = strtoupper($row["document_currency"] !== "" ? $row["document_currency"] : "PHP");

// VAT flag: when on, the PANABO PDF adds 12% VAT on top of the billed amount.
$isVat = in_array(strtolower(trim((string) ($payload["is_vat"] ?? ""))), ["1", "true", "on", "yes"], true);
$row["is_vat"] = $isVat ? "true" : "false";

$columns = array_keys($row);
$placeholders = implode(", ", array_fill(0, count($columns), "?"));
$updates = implode(", ", array_map(static fn($c) => "$c = EXCLUDED.$c", array_filter($columns, static fn($c) => $c !== "customer_key")));

try {
    $sql = "INSERT INTO billing_custom_customer (" . implode(", ", $columns) . ", updated_at)
            VALUES ($placeholders, NOW())
            ON CONFLICT (customer_key) DO UPDATE SET $updates, updated_at = NOW()";
    $conn->prepare($sql)->execute(array_values($row));
} catch (Throwable $e) {
    $fail("Save failed: " . $e->getMessage());
}

echo json_encode([
    "success" => true,
    "message" => ($mode === "create" ? "Customer \"$label\" added." : "Customer \"$label\" updated."),
    "customer_key" => $key,
]);
exit();
