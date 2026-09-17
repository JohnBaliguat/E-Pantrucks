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

function build_billing_sku($operations, $customer_ph)
{
    $operations = trim($operations);
    $customer_ph = trim($customer_ph);

    if ($operations === "" && $customer_ph === "") {
        return "";
    }

    return $operations . "-" . $customer_ph;
}

$response = ["success" => false, "message" => ""];

if (
    $_SERVER["REQUEST_METHOD"] !== "POST" ||
    !isset($_POST["action"]) ||
    $_POST["action"] !== "update-others"
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

$segment = validate($_POST["segment"] ?? "");
$activity = validate($_POST["activity"] ?? "");
$date = validate($_POST["date"] ?? "");
$waybill = validate($_POST["waybill"] ?? "");
$truck = validate($_POST["truck"] ?? "");
$driver = validate($_POST["driver"] ?? "");
$tr = validate($_POST["tr"] ?? "");
$gs = validate($_POST["gs"] ?? "");
$operations = validate($_POST["operations_ph"] ?? "");
$customer_ph = validate($_POST["customer_ph"] ?? "");
$load_qty = validate($_POST["load_quantity_weight"] ?? "");
$unit_of_measure = validate($_POST["unit_of_measure"] ?? "");
$kms = validate($_POST["kms"] ?? "");
$deliver_from = validate($_POST["deliver_from"] ?? "");

// Deliver-From must be a registered location (Master Data → Locations).
$__locError = unregistered_locations_message($conn, ["Deliver From" => $deliver_from]);
if ($__locError !== "") {
    header("Content-Type: application/json");
    echo json_encode(["success" => false, "message" => $__locError]);
    exit();
}
$deliver_to = validate($_POST["deliver_to"] ?? "");
$driver_idNumber = validate($_POST["driver_idNumber"] ?? "");
$remarks = validate($_POST["remarks"] ?? "");
$piece_rate = operations_lookup_piece_rate($conn, $segment, $activity);
$billing_sku = build_billing_sku($operations, $customer_ph);

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
if (empty($waybill)) {
    $response["message"] = "Waybill is required.";
    header("Content-Type: application/json");
    echo json_encode($response);
    exit();
}
if (empty($operations)) {
    $response["message"] = "Operations / PH (location) is required.";
    header("Content-Type: application/json");
    echo json_encode($response);
    exit();
}
if (empty($driver)) {
    $response["message"] = "Driver is required.";
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
if (
    ($e = master_validate_others(
        $conn,
        $segment,
        $activity,
        $driver,
        $driver_idNumber,
        $operations,
        $tr,
        $gs,
        $truck,
    )) !== null
) {
    $response["message"] = $e;
    header("Content-Type: application/json");
    echo json_encode($response);
    exit();
}

$entry_type = "OTHERS ENTRY";

$sql = "UPDATE operations SET
    entry_type = ?,
    waybill_date = ?,
    waybill = ?,
    segment = ?,
    activity = ?,
    truck = ?,
    tr = ?,
    gs = ?,
    operations_ph = ?,
    customer_ph = ?,
    load_quantity_weight = ?,
    unit_of_measure = ?,
    kms = ?,
    deliver_from = ?,
    delivered_to = ?,
    driver = ?,
    \"driver_idNumber\" = ?,
    remarks = ?,
    piece_rate = ?,
    billing_sku = ?,
    modified_by = ?,
    modified_date = ?
    WHERE entry_id = ?";

$stmt = $conn->prepare($sql);
$stmt->execute(db_nullable_all([
    $entry_type,
    $date,
    $waybill,
    $segment,
    $activity,
    $truck,
    $tr,
    $gs,
    $operations,
    $customer_ph,
    $load_qty,
    $unit_of_measure,
    $kms,
    $deliver_from,
    $deliver_to,
    $driver,
    $driver_idNumber,
    $remarks,
    $piece_rate,
    $billing_sku,
    $modified_by,
    $modified_date,
    $data_id,
]));

$response["success"] = true;
$response["message"] = "Others entry updated successfully.";
$response["record"] = [
    "entry_id" => $data_id,
    "segment" => $segment,
    "activity" => $activity,
    "waybill_date" => $date,
    "waybill" => $waybill,
    "truck" => $truck,
    "driver" => $driver,
    "driver_idNumber" => $driver_idNumber,
    "tr" => $tr,
    "gs" => $gs,
    "operations_ph" => $operations,
    "customer_ph" => $customer_ph,
    "load_quantity_weight" => $load_qty,
    "unit_of_measure" => $unit_of_measure,
    "kms" => $kms,
    "deliver_from" => $deliver_from,
    "delivered_to" => $deliver_to,
    "remarks" => $remarks,
    "piece_rate" => $piece_rate,
    "billing_sku" => $billing_sku,
];

header("Content-Type: application/json");
echo json_encode($response);
exit();
