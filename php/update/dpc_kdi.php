<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/waybill_duplicate.php";
require_once __DIR__ . "/../helpers/master_data_validate.php";
require_once __DIR__ . "/../helpers/trip_rate_lookup.php";
require_once __DIR__ . "/../helpers/db_value.php";
require_once __DIR__ . "/../helpers/location_validation.php";
date_default_timezone_set("Asia/Manila");
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function validate($data)
{
    return htmlspecialchars(trim($data));
}

function build_dpc_billing_sku($ph)
{
    $ph = strtoupper(trim((string) $ph));

    if ($ph === "") {
        return "DPC KDS-Outside";
    }

    foreach (["PH", "TDC", "DPC"] as $keyword) {
        if (strpos($ph, $keyword) !== false) {
            return "DPC KDS-Compound";
        }
    }

    return "DPC KDS-Outside";
}

$response = ["success" => false, "message" => ""];

if (
    $_SERVER["REQUEST_METHOD"] !== "POST" ||
    !isset($_POST["action"]) ||
    $_POST["action"] !== "update-dpc-kdi"
) {
    $response["message"] = "Invalid request.";
    header("Content-Type: application/json");
    echo json_encode($response);
    exit();
}

$data_id = intval($_POST["data_id"] ?? 0);
if ($data_id <= 0) {
    $response["message"] = "Invalid record ID.";
    header("Content-Type: application/json");
    echo json_encode($response);
    exit();
}

$entry_type = "DPC_KDs & OPM ENTRY";
$segment = validate($_POST["segment"] ?? "");
$activity = validate($_POST["activity"] ?? "");
$waybill_date = validate($_POST["date"] ?? "");
$waybill = validate($_POST["waybill"] ?? "");
$evita_farmind = validate($_POST["evita_farmind"] ?? "");
$driver = validate($_POST["driver"] ?? "");
$driver_idNumber = validate($_POST["driver_idNumber"] ?? "");
$departure = validate($_POST["departure"] ?? "");
$arrival = validate($_POST["arrival"] ?? "");
$truck = validate($_POST["truck"] ?? "");
$tr = validate($_POST["tr"] ?? "");
$ph = validate($_POST["ph"] ?? "");

// Packing House / Location must be registered (Master Data → Locations).
$__locError = unregistered_locations_message($conn, ["Packing House / Location" => $ph]);
if ($__locError !== "") {
    header("Content-Type: application/json");
    echo json_encode(["success" => false, "message" => $__locError]);
    exit();
}
$thirteen_body = validate($_POST["thirteen_body"] ?? "");
$thirteen_cover = validate($_POST["thirteen_cover"] ?? "");
$thirteen_pads = validate($_POST["thirteen_pads"] ?? "");
$eighteen_body = validate($_POST["eighteen_body"] ?? "");
$eighteen_cover = validate($_POST["eighteen_cover"] ?? "");
$eighteen_pads = validate($_POST["eighteen_pads"] ?? "");
$thirteen_total = validate($_POST["thirteen_total"] ?? "");
$eighteen_total = validate($_POST["eighteen_total"] ?? "");
$other_body = validate($_POST["other_body"] ?? "");
$other_cover = validate($_POST["other_cover"] ?? "");
$other_pads = validate($_POST["other_pads"] ?? "");
$other_total = validate($_POST["other_total"] ?? "");
$total_load = validate($_POST["total_load"] ?? "");
$fgtr_no = validate($_POST["fgtrs_no"] ?? "");
$remarks = validate($_POST["remarks"] ?? "");
$dpc_date = validate($_POST["dpc_date"] ?? "");
$piece_rate = operations_lookup_piece_rate($conn, $segment, $activity);
$billing_sku = build_dpc_billing_sku($ph);
$modified_by = isset($_SESSION["user_idNumber"])
    ? validate($_SESSION["user_idNumber"])
    : "system";
$modified_date = date("Y-m-d H:i:s");

if (empty($segment) || empty($activity)) {
    $response["message"] = "Segment and Activity are required.";
    header("Content-Type: application/json");
    echo json_encode($response);
    exit();
}
if (operations_waybill_exists($conn, $waybill, $data_id)) {
    $response["message"] =
        "This waybill number is already in use. Please use a different waybill.";
    header("Content-Type: application/json");
    echo json_encode($response);
    exit();
}
if (operations_fgtr_no_exists($conn, $fgtr_no, $data_id)) {
    $response["message"] =
        "This FGTR's NO. is already in use. Please use a different FGTR's NO.";
    header("Content-Type: application/json");
    echo json_encode($response);
    exit();
}
if (
    ($e = master_validate_dpc(
        $conn,
        $segment,
        $activity,
        $driver,
        $driver_idNumber,
        $ph,
        $tr,
        $truck,
    )) !== null
) {
    $response["message"] = $e;
    header("Content-Type: application/json");
    echo json_encode($response);
    exit();
}

$sql = "UPDATE operations SET
    entry_type = ?,
    segment = ?,
    activity = ?,
    waybill_date = ?,
    waybill = ?,
    evita_farmind = ?,
    driver = ?,
    \"driver_idNumber\" = ?,
    departure = ?,
    arrival = ?,
    truck = ?,
    tr = ?,
    ph = ?,
    \"13_body\" = ?,
    \"13_cover\" = ?,
    \"13_pads\" = ?,
    \"18_body\" = ?,
    \"18_cover\" = ?,
    \"18_pads\" = ?,
    \"13_total\" = ?,
    \"18_total\" = ?,
    other_body = ?,
    other_cover = ?,
    other_pads = ?,
    other_total = ?,
    total_load = ?,
    fgtr_no = ?,
    remarks = ?,
    dpc_date = ?,
    piece_rate = ?,
    billing_sku = ?,
    modified_by = ?,
    modified_date = ?
  WHERE entry_id = ?";

$stmt = $conn->prepare($sql);
$stmt->execute(db_nullable_all([
    $entry_type,
    $segment,
    $activity,
    $waybill_date,
    $waybill,
    $evita_farmind,
    $driver,
    $driver_idNumber,
    $departure,
    $arrival,
    $truck,
    $tr,
    $ph,
    $thirteen_body,
    $thirteen_cover,
    $thirteen_pads,
    $eighteen_body,
    $eighteen_cover,
    $eighteen_pads,
    $thirteen_total,
    $eighteen_total,
    $other_body,
    $other_cover,
    $other_pads,
    $other_total,
    $total_load,
    $fgtr_no,
    $remarks,
    $dpc_date,
    $piece_rate,
    $billing_sku,
    $modified_by,
    $modified_date,
    $data_id,
]));

$response["success"] = true;
$response["message"] = "DPC KDI entry updated successfully.";
$response["record"] = [
    "entry_id" => $data_id,
    "segment" => $segment,
    "activity" => $activity,
    "waybill_date" => $waybill_date,
    "waybill" => $waybill,
    "evita_farmind" => $evita_farmind,
    "driver" => $driver,
    "departure" => $departure,
    "arrival" => $arrival,
    "truck" => $truck,
    "tr" => $tr,
    "ph" => $ph,
    "13_body" => $thirteen_body,
    "13_cover" => $thirteen_cover,
    "13_pads" => $thirteen_pads,
    "18_body" => $eighteen_body,
    "18_cover" => $eighteen_cover,
    "18_pads" => $eighteen_pads,
    "13_total" => $thirteen_total,
    "18_total" => $eighteen_total,
    "other_body" => $other_body,
    "other_cover" => $other_cover,
    "other_pads" => $other_pads,
    "other_total" => $other_total,
    "total_load" => $total_load,
    "fgtr_no" => $fgtr_no,
    "remarks" => $remarks,
    "dpc_date" => $dpc_date,
    "piece_rate" => $piece_rate,
    "billing_sku" => $billing_sku,
];

header("Content-Type: application/json");
echo json_encode($response);
exit();
?>
