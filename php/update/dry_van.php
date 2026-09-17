<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/waybill_duplicate.php";
require_once __DIR__ . "/../helpers/db_value.php";
require_once __DIR__ . "/../helpers/ensure_operations_emdr_schema.php";
require_once __DIR__ . "/../helpers/location_validation.php";
date_default_timezone_set("Asia/Manila");
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

ensure_operations_emdr_schema($conn);

function validate($data) {
    return htmlspecialchars(trim($data));
}

function fallback_date(...$values) {
    foreach ($values as $value) {
        $value = trim((string) $value);
        if ($value !== "") {
            return $value;
        }
    }

    return date("Y-m-d");
}

function fallback_time(...$values) {
    foreach ($values as $value) {
        $value = trim((string) $value);
        if ($value !== "") {
            return $value;
        }
    }

    return "00:00:00";
}

function build_dry_van_route_code($location) {
    return strtoupper(trim((string) $location)) === "DICT" ? "PNB" : "DVO";
}

function get_dry_van_billing_customer($customer) {
    $map = [
        "CITIHARDWARE IMPORTS"      => "CTH",
        "CITIHARDWARE DOMESTIC"     => "CTH",
        "PSACC DOMESTIC"            => "PSACC",
        "TPD DRYVAN IMPORT"         => "TPD",
        "TPD DRYVAN EXPORT"         => "TPD",
        "ECOSSENTIAL - IMPORT"      => "ECOSSENTIAL",
        "PHIL CEMENT"               => "PHIL CEMENT",
        "NOVOCOCONUT - IMPORT"      => "NOVOCOCONUT",
        "NP CHANGS - IMPORT"        => "NP CHANGS",
        "SOLARVISTA - IMPORT"       => "SOLARVISTA",
        "FRANKLIN BAKER - IMPORT"   => "FRANKLIN BAKER",
        "EYE CARGO - IMPORT"        => "EYE CARGO",
        "PHIL JDU - IMPORT"         => "JDU",
        "SOUTHERN HARVEST - IMPORT" => "SOUTHERN HARVEST",
        "HEADSPORT - IMPORT"        => "HEADSPORT",
        "AGRI EXIM - IMPORT"        => "AGRI EXIM",
        "SOLARIS - IMPORT"          => "SOLARIS",
        // Same billing series as SOLARIS - IMPORT (shares SOLARIS' SKU series).
        "HURRAH AGRO - IMPORT"      => "SOLARIS",
        "ECOSSENTIAL - EXPORT"      => "ECOSSENTIAL",
        "NOVOCOCONUT - EXPORT"      => "NOVOCOCONUT",
        "FRANKLIN BAKER - EXPORT"   => "FRANKLIN BAKER",
        "EYE CARGO - EXPORT"        => "EYE CARGO",
        "PHIL JDU - EXPORT"         => "JDU",
        "SOUTHERN HARVEST - EXPORT" => "SOUTHERN HARVEST",
        "HEADSPORT - EXPORT"        => "HEADSPORT",
        "AGRI EXIM - EXPORT"        => "AGRI EXIM",
        "SOLARIS - EXPORT"          => "SOLARIS",
        // Same billing series as AGRI EXIM - EXPORT (share AGRI EXIM's SKU series).
        "SAGREX - EXPORT"                           => "AGRI EXIM",
        "FIRST PANABO TROPICAL FOOD CORP. - EXPORT" => "AGRI EXIM",
    ];
    return $map[$customer] ?? $customer;
}

function build_dry_van_billing_sku($customer, $pulloutLocation, $returnLocation) {
    $customer = trim((string) $customer);
    if ($customer === "") {
        return "";
    }

    $billingCustomer = get_dry_van_billing_customer($customer);
    $pullout = build_dry_van_route_code($pulloutLocation);
    $empty = build_dry_van_route_code($returnLocation);

    return "Dry Container-" . $billingCustomer . "-" . $pullout . "-" . $empty;
}

function lookup_dry_van_kms(PDO $conn, $billing_sku) {
    if (trim((string) $billing_sku) === "") {
        return "";
    }
    $stmt = $conn->prepare('SELECT "sku_rountripDistance" FROM sku WHERE TRIM(sku_name) = ? LIMIT 1');
    $stmt->execute([$billing_sku]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? trim((string) ($row["sku_rountripDistance"] ?? "")) : "";
}

$response = ["success" => false, "message" => ""];

if (
    $_SERVER["REQUEST_METHOD"] !== "POST" ||
    !isset($_POST["action"]) ||
    $_POST["action"] !== "update-dry-van"
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

$status = validate($_POST["status"] ?? "");
$customer_ph = validate($_POST["customer_ph"] ?? "");
$ph = validate($_POST["ph"] ?? "");
$ecs = validate($_POST["ecs"] ?? "");
$van_alpha = validate($_POST["van_alpha"] ?? "");
$van_number = validate($_POST["van_number"] ?? "");
$shipper = validate($_POST["shipper"] ?? "");
$eir_out = validate($_POST["eir_out"] ?? "");
$eir_outDate = validate($_POST["eir_outDate"] ?? "");
$eir_outTime = validate($_POST["eir_outTime"] ?? "");
$eir_in = validate($_POST["eir_in"] ?? "");
$eir_inDate = validate($_POST["eir_inDate"] ?? "");
$pullout_location = validate($_POST["pullout_location"] ?? "");
$pullout_date = validate($_POST["pullout_date"] ?? "");
$pullout_time = validate($_POST["pullout_time"] ?? "");
$delivered_to = validate($_POST["delivered_to"] ?? "");
$return_location = validate($_POST["return_location"] ?? "");
$size = validate($_POST["size"] ?? "");
$commodity = validate($_POST["commodity"] ?? "");
$slp_no = validate($_POST["slp_no"] ?? "");
$destination = validate($_POST["destination"] ?? "");
$gs = validate($_POST["gs"] ?? "");
$genset_hr_meter_start = validate($_POST["genset_hr_meter_start"] ?? "");
$genset_hr_meter_end = validate($_POST["genset_hr_meter_end"] ?? "");
$booking = validate($_POST["booking"] ?? "");
$shipment_no = validate($_POST["shipment_no"] ?? "");
$vessel_name = validate($_POST["vessel_name"] ?? "");
$voyage_no = validate($_POST["voyage_no"] ?? "");
$emdr_no = validate($_POST["emdr_no"] ?? "");
$seal = validate($_POST["seal"] ?? "");

$segment = validate($_POST["segment"] ?? "");
$activity = validate($_POST["activity"] ?? "");
$waybill = validate($_POST["waybill"] ?? "");
$date_hauled = validate($_POST["date_hauled"] ?? "");
$driver = validate($_POST["driver"] ?? "");
$truck = validate($_POST["truck"] ?? "");
$tr = validate($_POST["tr"] ?? "");
$tr2 = validate($_POST["tr2"] ?? "");
$date_unloaded = validate($_POST["date_unloaded"] ?? "");
$departure_time = validate($_POST["departure_time"] ?? "");
$arrival_time = validate($_POST["arrival_time"] ?? "");
$time_unloaded = validate($_POST["time_unloaded"] ?? "");
$remarks = validate($_POST["remarks"] ?? "");
$driver_idNumber = validate($_POST["driver_idNumber"] ?? "");

$segment_empty = validate($_POST["segment_empty"] ?? "");
$activity_empty = validate($_POST["activity_empty"] ?? "");
$waybill_empty = validate($_POST["waybill_empty"] ?? "");
$date_returned = validate($_POST["date_returned"] ?? "");
$driver_return = validate($_POST["driver_return"] ?? "");
$truck_return = validate($_POST["truck_return"] ?? "");
$type = validate($_POST["type"] ?? "");
$delivered_remarks = validate($_POST["delivered_remarks"] ?? "");
$driver_return_idNumber = validate($_POST["driver_return_idNumber"] ?? "");

$driver_idNumber = $driver_idNumber === "" ? "0" : $driver_idNumber;
$date_hauled = fallback_date($date_hauled, $pullout_date, $date_returned, $eir_outDate, $eir_inDate);
$date_unloaded = fallback_date($date_unloaded, $date_hauled, $pullout_date, $date_returned);
$date_returned = fallback_date($date_returned, $pullout_date, $date_hauled, $date_unloaded);
$pullout_date = fallback_date($pullout_date, $eir_outDate, $date_hauled, $date_returned);
$eir_outDate = fallback_date($eir_outDate, $pullout_date, $date_hauled, $date_returned);
$eir_inDate = fallback_date($eir_inDate, $date_hauled, $date_returned, $pullout_date);
$eir_outTime = fallback_time($eir_outTime);
$departure_time = fallback_time($departure_time);
$arrival_time = fallback_time($arrival_time, $departure_time);

$modified_by = isset($_SESSION["user_idNumber"])
    ? validate($_SESSION["user_idNumber"])
    : "system";
$modified_date = date("Y-m-d H:i:s");

$billing_sku = build_dry_van_billing_sku($customer_ph, $pullout_location, $return_location);
$kms = lookup_dry_van_kms($conn, $billing_sku);

$locationError = unregistered_locations_message($conn, [
    "Pull Out Location" => $pullout_location,
    "Return Location" => $return_location,
]);

if ($status === "") {
    $response["message"] = "Status is required.";
} elseif ($customer_ph === "") {
    $response["message"] = "Customer (PH) is required.";
} elseif ($locationError !== "") {
    $response["message"] = $locationError;
} else {
    $sql = "UPDATE operations SET
        status = ?,
        customer_ph = ?,
        ph = ?,
        ecs = ?,
        van_alpha = ?,
        van_number = ?,
        shipper = ?,
        eir_out = ?,
        \"eir_outDate\" = ?,
        \"eir_outTime\" = ?,
        eir_in = ?,
        \"eir_inDate\" = ?,
        pullout_location = ?,
        pullout_date = ?,
        pullout_time = ?,
        delivered_to = ?,
        return_location = ?,
        size = ?,
        commodity = ?,
        slp_no = ?,
        destination = ?,
        gs = ?,
        genset_hr_meter_start = ?,
        genset_hr_meter_end = ?,
        segment = ?,
        activity = ?,
        waybill = ?,
        date_hauled = ?,
        driver = ?,
        truck = ?,
        tr = ?,
        tr2 = ?,
        date_unloaded = ?,
        departure_time = ?,
        arrival_time = ?,
        time_unloaded = ?,
        remarks = ?,
        segment_empty = ?,
        activity_empty = ?,
        waybill_empty = ?,
        date_returned = ?,
        type = ?,
        delivered_remarks = ?,
        kms = ?,
        booking = ?,
        shipment_no = ?,
        vessel_name = ?,
        voyage_no = ?,
        emdr_no = ?,
        seal = ?,
        \"driver_idNumber\" = ?,
        driver_return = ?,
        truck2 = ?,
        \"driver_return_idNumber\" = ?,
        billing_sku = ?,
        modified_by = ?,
        modified_date = ?
        WHERE entry_id = ? AND entry_type = 'DRY VAN ENTRY'";

    $stmt = $conn->prepare($sql);
    $stmt->execute(db_nullable_all([
        $status,
        $customer_ph,
        $ph,
        $ecs,
        $van_alpha,
        $van_number,
        $shipper,
        $eir_out,
        $eir_outDate,
        $eir_outTime,
        $eir_in,
        $eir_inDate,
        $pullout_location,
        $pullout_date,
        $pullout_time,
        $delivered_to,
        $return_location,
        $size,
        $commodity,
        $slp_no,
        $destination,
        $gs,
        $genset_hr_meter_start,
        $genset_hr_meter_end,
        $segment,
        $activity,
        $waybill,
        $date_hauled,
        $driver,
        $truck,
        $tr,
        $tr2,
        $date_unloaded,
        $departure_time,
        $arrival_time,
        $time_unloaded,
        $remarks,
        $segment_empty,
        $activity_empty,
        $waybill_empty,
        $date_returned,
        $type,
        $delivered_remarks,
        $kms,
        $booking,
        $shipment_no,
        $vessel_name,
        $voyage_no,
        $emdr_no,
        $seal,
        $driver_idNumber,
        $driver_return,
        $truck_return,
        $driver_return_idNumber,
        $billing_sku,
        $modified_by,
        $modified_date,
        $data_id,
    ]));

    $response["success"] = true;
    $response["message"] = "Dry van entry updated successfully.";
    $response["record"] = [
        "entry_id" => $data_id,
        "status" => $status,
        "customer_ph" => $customer_ph,
        "ph" => $ph,
        "ecs" => $ecs,
        "van_alpha" => $van_alpha,
        "van_number" => $van_number,
        "shipper" => $shipper,
        "eir_out" => $eir_out,
        "eir_outDate" => $eir_outDate,
        "eir_outTime" => $eir_outTime,
        "eir_in" => $eir_in,
        "eir_inDate" => $eir_inDate,
        "pullout_location" => $pullout_location,
        "pullout_date" => $pullout_date,
        "pullout_time" => $pullout_time,
        "delivered_to" => $delivered_to,
        "return_location" => $return_location,
        "size" => $size,
        "commodity" => $commodity,
        "slp_no" => $slp_no,
        "destination" => $destination,
        "gs" => $gs,
        "genset_hr_meter_start" => $genset_hr_meter_start,
        "genset_hr_meter_end" => $genset_hr_meter_end,
        "segment" => $segment,
        "activity" => $activity,
        "driver" => $driver,
        "truck" => $truck,
        "driver_idNumber" => $driver_idNumber,
        "driver_return" => $driver_return,
        "truck2" => $truck_return,
        "driver_return_idNumber" => $driver_return_idNumber,
        "segment_empty" => $segment_empty,
        "activity_empty" => $activity_empty,
        "waybill" => $waybill,
        "date_hauled" => $date_hauled,
        "tr" => $tr,
        "tr2" => $tr2,
        "date_unloaded" => $date_unloaded,
        "departure_time" => $departure_time,
        "arrival_time" => $arrival_time,
        "remarks" => $remarks,
        "waybill_empty" => $waybill_empty,
        "date_returned" => $date_returned,
        "type" => $type,
        "delivered_remarks" => $delivered_remarks,
        "booking" => $booking,
        "shipment_no" => $shipment_no,
        "vessel_name" => $vessel_name,
        "voyage_no" => $voyage_no,
        "emdr_no" => $emdr_no,
        "billing_sku" => $billing_sku
    ];
}

header("Content-Type: application/json");
echo json_encode($response);
exit();
?>
