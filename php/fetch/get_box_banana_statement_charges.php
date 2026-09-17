<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_box_banana_schema.php";

date_default_timezone_set("Asia/Manila");
header("Content-Type: application/json; charset=utf-8");
ensure_box_banana_schema($conn);

// Per-entry billed charges for one Box Bananas statement — powers the Charges
// view/edit modal on box_banana.php. Mirrors get_invoice_entry_charges.php but on
// the box_banana_statements / box_banana_statement_entries tables.
$statementId = (int) ($_GET["id"] ?? $_GET["statement_id"] ?? 0);
if ($statementId <= 0) {
    echo json_encode(["success" => false, "message" => "Missing statement id."]);
    exit();
}

$inv = $conn->prepare(
    "SELECT statement_id, customer_label, reference, date_from, date_to, status
     FROM box_banana_statements WHERE statement_id = ? AND status <> 'deleted'"
);
$inv->execute([$statementId]);
$statement = $inv->fetch(PDO::FETCH_ASSOC);
if (!$statement) {
    echo json_encode(["success" => false, "message" => "Statement not found."]);
    exit();
}

$stmt = $conn->prepare(
    "SELECT e.entry_id, e.rate_charge, e.manual_charge, e.charge_updated_at, e.charge_updated_by,
            o.waybill, o.tr, o.truck, o.delivered_by_prime_mover, o.van_alpha, o.van_number,
            o.loaded_van_loading_start_date, o.waybill_date, o.date_hauled, o.created_date
     FROM box_banana_statement_entries e
     LEFT JOIN operations o ON o.entry_id = e.entry_id
     WHERE e.statement_id = ?
     ORDER BY e.entry_id"
);
$stmt->execute([$statementId]);
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
        "invoice_id" => (int) $statement["statement_id"],
        "customer_label" => $statement["customer_label"],
        "document_no" => $statement["reference"],
        "reference" => $statement["reference"],
        "date_from" => $statement["date_from"],
        "date_to" => $statement["date_to"],
        "forex_rate" => null,
        "status" => $statement["status"],
    ],
    "entries" => $entries,
    "count" => count($entries),
    "has_locked_charges" => $anyLocked,
    "total" => round($total, 2),
]);
exit();
