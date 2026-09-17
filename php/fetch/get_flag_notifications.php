<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_data_update_flags_schema.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header("Content-Type: application/json; charset=utf-8");
ensure_data_update_flags_schema($conn);

// Resolved flags that the current (billing) user raised — i.e. records they
// flagged for update that have now been updated. Unseen ones drive the badge.
// Admins see every resolved flag, not just the ones they raised.
$me = (string) ($_SESSION["user_idNumber"] ?? ($_SESSION["user_name"] ?? ""));
$isAdmin = ucfirst(strtolower((string) ($_SESSION["user_type"] ?? ""))) === "Admin";
if (!$isAdmin && $me === "") {
    echo json_encode(["success" => true, "flags" => [], "unseen_count" => 0]);
    exit();
}

$sql =
    "SELECT f.flag_id, f.entry_id, f.entry_type, f.remarks, f.flagger_seen,
            f.resolved_by, f.resolved_at,
            o.waybill, o.shipper, o.customer_ph, o.van_alpha, o.van_number
     FROM data_update_flags f
     LEFT JOIN operations o ON o.entry_id = f.entry_id
     WHERE f.status = 'resolved'";
$params = [];
if (!$isAdmin) {
    $sql .= " AND f.flagged_by = ?";
    $params[] = $me;
}
$sql .= " ORDER BY f.resolved_at DESC NULLS LAST, f.flag_id DESC LIMIT 30";
$stmt = $conn->prepare($sql);
$stmt->execute($params);

$flags = [];
$unseen = 0;
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $seen = !empty($r["flagger_seen"]);
    if (!$seen) {
        $unseen++;
    }
    $van = trim(((string) ($r["van_alpha"] ?? "")) . " " . ((string) ($r["van_number"] ?? "")));
    $flags[] = [
        "flag_id" => (int) $r["flag_id"],
        "entry_id" => (int) $r["entry_id"],
        "entry_type" => $r["entry_type"],
        "remarks" => $r["remarks"],
        "seen" => $seen,
        "resolved_by" => $r["resolved_by"],
        "resolved_at" => $r["resolved_at"],
        "waybill" => $r["waybill"],
        "shipper" => $r["shipper"] ?: $r["customer_ph"],
        "van" => $van,
    ];
}

echo json_encode(["success" => true, "flags" => $flags, "unseen_count" => $unseen]);
exit();
?>
