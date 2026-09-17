<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_data_update_flags_schema.php";
require_once __DIR__ . "/../helpers/entry_update_route.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header("Content-Type: application/json; charset=utf-8");
ensure_data_update_flags_schema($conn);

$encoderId = trim((string) ($_SESSION["user_idNumber"] ?? ""));
$isAdmin = ucfirst(strtolower((string) ($_SESSION["user_type"] ?? ""))) === "Admin";
if (!$isAdmin && $encoderId === "") {
    echo json_encode(["success" => false, "message" => "Your session does not have an encoder ID."]);
    exit();
}

$status = trim((string) ($_GET["status"] ?? "open"));
if (!in_array($status, ["open", "resolved", "all"], true)) {
    $status = "open";
}

// Admins see every flagged record; regular users see records they encoded, plus
// orphaned "system" records (entries saved when the encoder's session had no
// user_idNumber) so those can still be corrected by a user rather than only an
// admin. See get_user_performance/records for the "system" attribution cause.
$where = [];
$params = [];
if (!$isAdmin) {
    $where[] = "(o.created_by = ? OR o.created_by = 'system')";
    $params[] = $encoderId;
}
if ($status !== "all") {
    $where[] = "f.status = ?";
    $params[] = $status;
}

$sql = "SELECT f.flag_id, f.entry_id, f.entry_type, f.segment, f.remarks, f.field_notes, f.status,
               f.flagged_by, f.flagged_at, f.resolved_by, f.resolved_at,
               o.waybill, o.shipper, o.customer_ph, o.van_alpha, o.van_number,
               o.created_date
        FROM data_update_flags f
        LEFT JOIN operations o ON o.entry_id = f.entry_id
        " . (empty($where) ? "" : "WHERE " . implode(" AND ", $where)) . "
        ORDER BY f.flagged_at DESC, f.flag_id DESC";

$flags = [];
$stmt = $conn->prepare($sql);
$stmt->execute($params);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $route = entry_update_route((string) ($r["entry_type"] ?? ""), (string) ($r["segment"] ?? ""));
    $van = trim(((string) ($r["van_alpha"] ?? "")) . " " . ((string) ($r["van_number"] ?? "")));
    $flags[] = [
        "flag_id" => (int) $r["flag_id"],
        "entry_id" => (int) $r["entry_id"],
        "entry_type" => $r["entry_type"],
        "segment" => $r["segment"],
        "remarks" => $r["remarks"],
        "status" => $r["status"],
        "flagged_by" => $r["flagged_by"],
        "flagged_at" => $r["flagged_at"],
        "resolved_by" => $r["resolved_by"],
        "resolved_at" => $r["resolved_at"],
        "waybill" => $r["waybill"],
        "shipper" => $r["shipper"] ?: $r["customer_ph"],
        "van" => $van,
        "field_notes" => json_decode((string) ($r["field_notes"] ?? "[]"), true) ?: [],
        "edit_url" => $route . "?load_id=" . (int) $r["entry_id"] . "&flag_id=" . (int) $r["flag_id"],
    ];
}

echo json_encode(["success" => true, "flags" => $flags, "open_count" => count(array_filter($flags, fn($f) => $f["status"] === "open"))]);
exit();
?>
