<?php

require_once __DIR__ . "/../helpers/xlsx_helper.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION["user_idNumber"])) {
    http_response_code(403);
    exit("Unauthorized");
}

$entry_type = $_GET["entry_type"] ?? "";

$templates = [
    "RV ENTRY" => [
        "filename" => "rv_entry_template.xlsx",
        "headers" => [
            "Segment (Empty)",
            "Activity (Empty)",
            "Waybill (Empty)",
            "Pullout Location Arrival Date (M/D/YYYY)",
            "Pullout Location Arrival Time (HHMM)",
            "Pullout Location Departure Date (M/D/YYYY)",
            "Pullout Location Departure Time (HHMM)",
            "PH Arrival Date (M/D/YYYY)",
            "PH Arrival Time (HHMM)",
            "Van Alpha (4 letters)",
            "Van Number (7 digits)",
            "Van Name",
            "PH",
            "Shipper",
            "ECS",
            "TR",
            "GS",
            "Prime Mover",
            "Driver",
            "Empty Pullout Location",
            "Segment (Loaded)",
            "Activity (Loaded)",
            "Waybill (Loaded, 6 digits)",
            "Loading Start Date (M/D/YYYY)",
            "Loading Start Time (HHMM)",
            "Loading Finish Date (M/D/YYYY)",
            "Loading Finish Time (HHMM)",
            "Delivery Departure Date (M/D/YYYY)",
            "Delivery Departure Time (HHMM)",
            "Delivery Arrival Date (M/D/YYYY)",
            "Delivery Arrival Time (HHMM)",
            "End Unloading Start Date (M/D/YYYY)",
            "End Unloading Start Time (HHMM)",
            "End Unloading Finish Date (M/D/YYYY)",
            "End Unloading Finish Time (HHMM)",
            "DR No",
            "Reference Documents",
            "Load Description",
            "Delivered By Prime Mover",
            "Delivered By Driver",
            "Delivered To",
            "Remarks",
            "Genset HR Meter Start",
            "Genset HR Meter End",
            "Genset Start Date (M/D/YYYY)",
            "Genset Start Time (HHMM)",
            "Genset End Date (M/D/YYYY)",
            "Genset End Time (HHMM)",
            "Refueled",
        ],
    ],
    "DRY VAN ENTRY" => [
        "filename" => "dry_van_entry_template.xlsx",
        "headers" => [
            "Customer / PH",
            "ECS",
            "Van Alpha (4 letters)",
            "Van Number (7 digits)",
            "Shipper (Shipping Line)",
            "EIR Out",
            "EIR Out Date (M/D/YYYY)",
            "EIR Out Time (HHMM)",
            "EIR In",
            "EIR In Date (M/D/YYYY)",
            "Pullout Location",
            "Pullout Date (M/D/YYYY)",
            "Delivered To",
            "Return Location",
            "Size",
            "SLP No",
            "Destination",
            "GS",
            "Genset HR Meter Start",
            "Genset HR Meter End",
            "KMs",
            "Booking",
            "Shipment No",
            "Date Unloaded (M/D/YYYY)",
            "Seal",
            "Waybill (Loaded, 6 digits)",
            "Date Hauled (M/D/YYYY)",
            "Driver (Loaded)",
            "Truck (Loaded)",
            "TR",
            "TR2",
            "Remarks",
            "Waybill (Empty, 6 digits)",
            "Date Returned (M/D/YYYY)",
            "Driver (Return)",
            "Truck (Return)",
            "Type (EXPORT/IMPORT)",
            "Delivered Remarks",
        ],
    ],
    "OTHERS ENTRY" => [
        "filename" => "others_entry_template.xlsx",
        "headers" => [
            "Date (M/D/YYYY)",
            "Waybill (6 digits)",
            "Truck",
            "Driver",
            "TR (Trailer)",
            "GS (Genset)",
            "Operations / PH Location",
            "Customer / PH",
            "Load Quantity / Weight",
            "Unit of Measure",
            "KMs",
            "Deliver From",
            "Deliver To",
            "Remarks",
        ],
    ],
    "DPC_KDs & OPM ENTRY" => [
        "filename" => "dpc_kdi_entry_template.xlsx",
        "headers" => [
            "Date (M/D/YYYY)",
            "Waybill (6 digits)",
            "Driver",
            "Departure (M/D/YYYY HHMM)",
            "Arrival (M/D/YYYY HHMM)",
            "Truck",
            "TR",
            "PH",
            "13 Body",
            "13 Cover",
            "13 Pads",
            "18 Body",
            "18 Cover",
            "18 Pads",
            "Other Body",
            "Other Cover",
            "Other Pads",
            "FGTR No",
            "Remarks",
        ],
    ],
    "CARGO TRUCK ENTRY" => [
        "filename" => "cargo_truck_entry_template.xlsx",
        "headers" => [
            "Date (M/D/YYYY)",
            "Waybill (6 digits)",
            "Truck",
            "Driver",
            "Customer / PH",
            "Outside",
            "Compound",
            "Total Trips",
            "Operations",
            "Deliver From",
            "Deliver To",
            "Remarks",
        ],
    ],
];

if (!isset($templates[$entry_type])) {
    http_response_code(400);
    header("Content-Type: application/json");
    echo json_encode(["success" => false, "message" => "Invalid or unsupported entry type."]);
    exit();
}

$tpl  = $templates[$entry_type];
$xlsx = xlsx_create($tpl["headers"]);

if ($xlsx === '') {
    http_response_code(500);
    header("Content-Type: application/json");
    echo json_encode(["success" => false, "message" => "Failed to generate template."]);
    exit();
}

header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"" . $tpl["filename"] . "\"");
header("Content-Length: " . strlen($xlsx));
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

echo $xlsx;
exit();
