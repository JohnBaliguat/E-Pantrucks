<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_customer_billing_schema.php";

date_default_timezone_set("Asia/Manila");
header("Content-Type: application/json; charset=utf-8");
ensure_customer_billing_schema($conn);

// Per-entry billed charges for one generated invoice — powers the Charges
// view/edit modal. Each row is one trip on the invoice with its LOCKED peso
// charge (rate_charge), plus enough identity (trip receipt, truck, date) to
// recognise it. manual_charge flags a value finance edited after generation.
$invoiceId = (int) ($_GET["id"] ?? $_GET["invoice_id"] ?? 0);
if ($invoiceId <= 0) {
    echo json_encode(["success" => false, "message" => "Missing invoice id."]);
    exit();
}

$inv = $conn->prepare(
    "SELECT invoice_id, customer_label, document_no, reference, date_from, date_to, forex_rate, status
     FROM billing_invoices WHERE invoice_id = ? AND status <> 'deleted'"
);
$inv->execute([$invoiceId]);
$invoice = $inv->fetch(PDO::FETCH_ASSOC);
if (!$invoice) {
    echo json_encode(["success" => false, "message" => "Invoice not found."]);
    exit();
}

$stmt = $conn->prepare(
    "SELECT e.entry_id, e.rate_charge, e.manual_charge, e.charge_updated_at, e.charge_updated_by,
            o.waybill, o.tr, o.truck, o.delivered_by_prime_mover, o.van_alpha, o.van_number,
            o.loaded_van_loading_start_date, o.waybill_date, o.date_hauled, o.created_date
     FROM billing_invoice_entries e
     LEFT JOIN operations o ON o.entry_id = e.entry_id
     WHERE e.invoice_id = ?
     ORDER BY e.entry_id"
);
$stmt->execute([$invoiceId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$firstNonEmpty = static function (...$vals): string {
    foreach ($vals as $v) {
        $t = trim((string) ($v ?? ""));
        if ($t !== "" && !str_starts_with($t, "0000-")) {
            return $t;
        }
    }
    return "";
};
$digits = static function (string $text): string {
    $d = preg_replace('/\D+/', "", $text);
    return $d !== "" ? $d : $text;
};
$fmtDate = static function (string $raw): string {
    $ts = strtotime(substr(trim($raw), 0, 10));
    return $ts ? date("m/d/Y", $ts) : "";
};

$entries = [];
$total = 0.0;
$anyLocked = false;
foreach ($rows as $r) {
    $charge = $r["rate_charge"];
    $hasCharge = is_numeric($charge);
    if ($hasCharge) {
        $total += (float) $charge;
        $anyLocked = true;
    }
    $entries[] = [
        "entry_id" => (int) $r["entry_id"],
        "trip_receipt" => $firstNonEmpty($r["waybill"] ?? "", $r["tr"] ?? ""),
        "truck" => $digits($firstNonEmpty($r["delivered_by_prime_mover"] ?? "", $r["truck"] ?? "")),
        "van" => trim(((string) ($r["van_alpha"] ?? "")) . " " . ((string) ($r["van_number"] ?? ""))),
        "date" => $fmtDate($firstNonEmpty(
            $r["loaded_van_loading_start_date"] ?? "",
            $r["waybill_date"] ?? "",
            $r["date_hauled"] ?? "",
            substr((string) ($r["created_date"] ?? ""), 0, 10)
        )),
        "rate_charge" => $hasCharge ? round((float) $charge, 2) : null,
        "manual_charge" => (bool) $r["manual_charge"],
        "charge_updated_at" => $r["charge_updated_at"],
        "charge_updated_by" => $r["charge_updated_by"],
    ];
}

echo json_encode([
    "success" => true,
    "invoice" => [
        "invoice_id" => (int) $invoice["invoice_id"],
        "customer_label" => $invoice["customer_label"],
        "document_no" => $invoice["document_no"],
        "reference" => $invoice["reference"],
        "date_from" => $invoice["date_from"],
        "date_to" => $invoice["date_to"],
        "forex_rate" => $invoice["forex_rate"] !== null ? (float) $invoice["forex_rate"] : null,
        "status" => $invoice["status"],
    ],
    "entries" => $entries,
    "count" => count($entries),
    // False for legacy invoices generated before charges were locked — the UI
    // then shows a "not recorded" hint instead of blank editable rows.
    "has_locked_charges" => $anyLocked,
    "total" => round($total, 2),
]);
exit();
