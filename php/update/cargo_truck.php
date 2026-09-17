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

$response = ["success" => false, "message" => ""];

if (
    $_SERVER["REQUEST_METHOD"] !== "POST" ||
    !isset($_POST["action"]) ||
    $_POST["action"] !== "update-cargo-truck"
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

$entry_type = "CARGO TRUCK ENTRY";
$segment = validate($_POST["segment"] ?? "");
$activity = validate($_POST["activity"] ?? "");
$waybill_date = validate($_POST["date"] ?? "");
$waybill = validate($_POST["waybill"] ?? "");
$truck = validate($_POST["truck"] ?? "");
$driver = validate($_POST["driver"] ?? "");
$driver_idNumber = validate($_POST["driver_idNumber"] ?? "");
$customer_ph = validate($_POST["customer_ph"] ?? "");
$outside = validate($_POST["outside"] ?? "");
$compound = validate($_POST["compound"] ?? "");
$total_trips = validate($_POST["total_trips"] ?? "");
$operations = validate($_POST["operations"] ?? "");
$deliver_from = validate($_POST["deliver_from"] ?? "");

// Deliver-From must be a registered location (Master Data → Locations).
$__locError = unregistered_locations_message($conn, ["Deliver From" => $deliver_from]);
if ($__locError !== "") {
    header("Content-Type: application/json");
    echo json_encode(["success" => false, "message" => $__locError]);
    exit();
}
$deliver_to = validate($_POST["deliver_to"] ?? "");
$remarks = validate($_POST["remarks"] ?? "");
$cargo_date = validate($_POST["cargo_date"] ?? "");
$piece_rate = operations_lookup_piece_rate($conn, $segment, $activity);
$billing_sku = "CT-Other Hauling";
$modified_by = isset($_SESSION["user_idNumber"])
    ? validate($_SESSION["user_idNumber"])
    : "system";
$modified_date = date("Y-m-d H:i:s");

if (operations_waybill_exists($conn, $waybill, $data_id)) {
    $response["message"] =
        "This waybill number is already in use. Please use a different waybill.";
    header("Content-Type: application/json");
    echo json_encode($response);
    exit();
}
if (
    ($e = master_validate_cargo(
        $conn,
        $segment,
        $activity,
        $driver,
        $driver_idNumber,
        $truck,
        $customer_ph,
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
    truck = ?,
    driver = ?,
    \"driver_idNumber\" = ?,
    customer_ph = ?,
    outside = ?,
    compound = ?,
    total_trips = ?,
    operations = ?,
    deliver_from = ?,
    delivered_to = ?,
    remarks = ?,
    cargo_date = ?,
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
    $truck,
    $driver,
    $driver_idNumber,
    $customer_ph,
    $outside,
    $compound,
    $total_trips,
    $operations,
    $deliver_from,
    $deliver_to,
    $remarks,
    $cargo_date,
    $piece_rate,
    $billing_sku,
    $modified_by,
    $modified_date,
    $data_id,
]));

$response["success"] = true;
$response["message"] = "Cargo Truck entry updated successfully.";
$response["record"] = [
    "entry_id" => $data_id,
    "segment" => $segment,
    "activity" => $activity,
    "waybill_date" => $waybill_date,
    "waybill" => $waybill,
    "truck" => $truck,
    "driver" => $driver,
    "customer_ph" => $customer_ph,
    "outside" => $outside,
    "compound" => $compound,
    "total_trips" => $total_trips,
    "operations" => $operations,
    "deliver_from" => $deliver_from,
    "delivered_to" => $deliver_to,
    "remarks" => $remarks,
    "cargo_date" => $cargo_date,
    "piece_rate" => $piece_rate,
    "billing_sku" => $billing_sku,
];

header("Content-Type: application/json");
echo json_encode($response);
exit();
?>
