<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/unified_billing.php";
require_once __DIR__ . "/../helpers/billing_customers.php";
require_once __DIR__ . "/../helpers/box_banana_customers.php";
require_once __DIR__ . "/../helpers/abc_kds.php";
require_once __DIR__ . "/../helpers/dict_shuttling.php";
require_once __DIR__ . "/../helpers/customer_sap_codes.php";

require_once __DIR__ . "/../helpers/custom_billing_customers.php";

header("Content-Type: application/json; charset=utf-8");
ensure_customer_sap_schema($conn);
ensure_custom_customer_schema($conn);

$payload = json_decode((string) ($_POST["values"] ?? ""), true);
if (!is_array($payload)) {
    echo json_encode(["success" => false, "message" => "Invalid payload."]);
    exit();
}

// Only accept known billing customer keys.
$validKeys = array_keys(unified_billing_customers());

try {
    $conn->beginTransaction();
    $upsert = $conn->prepare(
        "INSERT INTO billing_customer_sap (customer_key, sold_to, material_code, profit_center, affiliate, updated_at)
         VALUES (?, ?, ?, ?, ?, NOW())
         ON CONFLICT (customer_key) DO UPDATE SET
            sold_to = EXCLUDED.sold_to,
            material_code = EXCLUDED.material_code,
            profit_center = EXCLUDED.profit_center,
            affiliate = EXCLUDED.affiliate,
            updated_at = NOW()"
    );
    $delete = $conn->prepare("DELETE FROM billing_customer_sap WHERE customer_key = ?");
    // VAT flag lives on the DB-backed customer row (Box Banana built-ins + user-added);
    // UPDATE is a no-op for keys without such a row (other pipelines have no VAT toggle).
    $vatUpdate = $conn->prepare("UPDATE billing_custom_customer SET is_vat = ?, updated_at = NOW() WHERE customer_key = ?");

    $truthy = static function ($v): bool {
        $v = strtolower(trim((string) $v));
        return $v === "1" || $v === "true" || $v === "on" || $v === "yes";
    };

    $saved = 0;
    foreach ($payload as $key => $vals) {
        if (!in_array($key, $validKeys, true) || !is_array($vals)) {
            continue;
        }
        // Sold-To (free text) + Material Code / Profit Center (picked from the master-data
        // dropdowns). Keep only values that GENUINELY differ from the code default —
        // blank/"(use default)"/equal-to-default is stored blank so the config stays
        // authoritative and future default changes still take effect.
        $default = billing_customer_sap_default($key);
        $store = [];
        foreach (["sold_to", "material_code", "profit_center"] as $f) {
            $val = trim((string) ($vals[$f] ?? ""));
            $store[$f] = ($val === "" || $val === trim((string) ($default[$f] ?? ""))) ? "" : $val;
        }
        // VAT flag is stored independently on the customer's DB row (if any).
        if (array_key_exists("is_vat", $vals)) {
            $vatUpdate->execute([$truthy($vals["is_vat"]) ? "true" : "false", $key]);
        }

        $affiliate = $truthy($vals["affiliate"] ?? false);
        $affiliateIsDefault = $affiliate === (bool) ($default["affiliate"] ?? false);

        if ($store["sold_to"] === "" && $store["material_code"] === "" && $store["profit_center"] === "" && $affiliateIsDefault) {
            $delete->execute([$key]);
            continue;
        }
        $upsert->execute([$key, $store["sold_to"], $store["material_code"], $store["profit_center"], $affiliate ? "true" : "false"]);
        $saved++;
    }
    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(["success" => false, "message" => "Save failed: " . $e->getMessage()]);
    exit();
}

echo json_encode(["success" => true, "message" => "Customer SAP codes saved (" . $saved . " with values)."]);
exit();
