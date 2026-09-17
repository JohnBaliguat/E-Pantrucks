<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
header("Content-Type: application/json; charset=utf-8");

$entryId = (int) ($_GET["entry_id"] ?? 0);
$stmt = $conn->prepare("SELECT * FROM operations WHERE entry_id = ? LIMIT 1");
$stmt->execute([$entryId]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$record) {
    echo json_encode(["success" => false, "message" => "Record not found."]);
    exit;
}

$common = [
    "Trip information" => [
        ["segment_empty", "Empty Segment"], ["activity_empty", "Empty Activity"], ["segment", "Loaded Segment"], ["activity", "Loaded Activity"],
        ["remarks", "Remarks"], ["waybill", "Trip Receipt"], ["waybill_empty", "Trip Receipt Empty"], ["dr_no", "DR No."], ["reference_documents", "Reference Documents"],
    ],
    "Equipment and personnel" => [
        ["van_alpha", "Van Alpha"], ["van_number", "Van Number"], ["van_name", "Van / Shipping Line"], ["ph", "PH"], ["shipper", "Shipper"], ["ecs", "ECS"],
        ["tr", "Trailer / Chassis"], ["gs", "Genset"], ["prime_mover", "Prime Mover"], ["driver", "Driver"], ["empty_pullout_location", "Empty Pull-out Location"],
    ],
    "Pull-out and loading" => [
        ["pullout_location_arrival_date", "Pull-out Arrival Date"], ["pullout_location_arrival_time", "Pull-out Arrival Time"],
        ["pullout_location_departure_date", "Pull-out Departure Date"], ["pullout_location_departure_time", "Pull-out Departure Time"],
        ["ph_arrival_date", "PH Arrival Date"], ["ph_arrival_time", "PH Arrival Time"],
        ["loading_start_date", "Loading Start Date"], ["loading_start_time", "Loading Start Time"], ["loading_finish_date", "Loading Finish Date"], ["loading_finish_time", "Loading Finish Time"],
    ],
    "Delivery and unloading" => [
        ["delivery_departure_date", "Delivery Departure Date"], ["delivery_departure_time", "Delivery Departure Time"], ["delivery_arrival_date", "Delivery Arrival Date"], ["delivery_arrival_time", "Delivery Arrival Time"],
        ["delivery_location_arrival_date", "Delivery Location Arrival Date"], ["delivery_location_arrival_time", "Delivery Location Arrival Time"],
        ["end_unloading_start_date", "Unloading Start Date"], ["end_unloading_start_time", "Unloading Start Time"], ["end_unloading_finish_date", "Unloading Finish Date"], ["end_unloading_finish_time", "Unloading Finish Time"],
        ["delivered_to", "Delivered To"], ["pm2", "Delivered By Prime Mover"], ["driver2", "Delivered By Driver"], ["load", "Load"],
    ],
];

$rv = [
    "Genset and hour meter" => [
        ["hr_meter_start", "HR Meter Start"], ["hr_meter_end", "HR Meter End"], ["genset_hr_reading", "Genset HR Reading"], ["refueled", "Refueled"],
        ["gs_start_date", "Genset Start Date"], ["gs_start_time", "Genset Start Time"], ["gs_end_date", "Genset End Date"], ["gs_end_time", "Genset End Time"],
    ],
];

$type = strtoupper(trim((string) $record["entry_type"]));
$groups = $common;
if ($type === "RV ENTRY") {
    $groups = array_merge($groups, $rv);
} elseif ($type === "DRY VAN ENTRY") {
    $groups["Dry van details"] = [["withdrawal_date", "Withdrawal Date"], ["withdrawal_time", "Withdrawal Time"], ["return_location", "Return Location"]];
} elseif ($type === "CARGO TRUCK ENTRY") {
    $groups["Cargo details"] = [["load_quantity_weight", "Load Quantity / Weight"], ["uom", "Unit of Measure"], ["billing_sku", "Billing SKU"]];
} elseif ($type === "DPC_KDS & OPM ENTRY" || $type === "OTHERS ENTRY") {
    $groups["Service details"] = [["billing_sku", "Billing SKU"], ["load_quantity_weight", "Load Quantity / Weight"], ["uom", "Unit of Measure"], ["date_hauled", "Date Hauled"]];
}

$valueMap = [
    "loading_start_date" => "loaded_van_loading_start_date", "loading_start_time" => "loaded_van_loading_start_time",
    "loading_finish_date" => "loaded_van_loading_finish_date", "loading_finish_time" => "loaded_van_loading_finish_time",
    "delivery_departure_date" => "loaded_van_delivery_departure_date", "delivery_departure_time" => "loaded_van_delivery_departure_time",
    "delivery_arrival_date" => "loaded_van_delivery_arrival_date", "delivery_arrival_time" => "loaded_van_delivery_arrival_time",
    "pm2" => "delivered_by_prime_mover", "driver2" => "delivered_by_driver", "load" => "load_description",
    "hr_meter_start" => "genset_hr_meter_start", "hr_meter_end" => "genset_hr_meter_end",
    "gs_start_date" => "genset_start_date", "gs_start_time" => "genset_start_time", "gs_end_date" => "genset_end_date", "gs_end_time" => "genset_end_time",
];
// Legacy-column fallback so the "Current" value matches what the entry form displays.
// The RV forms save the End-of-Unloading into the legacy `end_uploading_*` columns and
// show them via a fallback on load; older records only have the legacy column populated,
// so read it here too — otherwise these fields wrongly show "blank" in the flag picker.
$fallbackMap = [
    "end_unloading_start_date"  => "end_uploading_date",
    "end_unloading_start_time"  => "end_uploading_time",
    "end_unloading_finish_date" => "end_uploading_date",
    "end_unloading_finish_time" => "end_uploading_time",
];
$fields = [];
foreach ($groups as $section => $items) {
    foreach ($items as [$field, $label]) {
        $column = $valueMap[$field] ?? $field;
        $value = $record[$column] ?? "";
        if ((string) $value === "" && isset($fallbackMap[$field])) {
            $value = $record[$fallbackMap[$field]] ?? "";
        }
        $fields[] = ["section" => $section, "field" => $field, "label" => $label, "value" => is_scalar($value) ? (string) $value : ""];
    }
}
// Resolve the encoder (operations.created_by = user_idNumber) to a display name so the
// flag modal can show who encoded the record.
$encodedBy = "";
$createdBy = trim((string) ($record["created_by"] ?? ""));
if ($createdBy !== "") {
    $encodedBy = $createdBy; // fall back to the raw id when the user can't be resolved
    try {
        $u = $conn->prepare('SELECT "user_fname", "user_lname", "user_name" FROM "user" WHERE CAST("user_idNumber" AS TEXT) = ? LIMIT 1');
        $u->execute([$createdBy]);
        $ur = $u->fetch(PDO::FETCH_ASSOC);
        if ($ur) {
            $name = trim(((string) ($ur["user_fname"] ?? "")) . " " . ((string) ($ur["user_lname"] ?? "")));
            $encodedBy = $name !== "" ? $name : (string) ($ur["user_name"] ?? $createdBy);
        }
    } catch (Throwable $e) {
        // keep the raw id
    }
}

echo json_encode([
    "success" => true,
    "entry_type" => $record["entry_type"],
    "encoded_by" => $encodedBy,
    "encoded_at" => substr((string) ($record["created_date"] ?? ""), 0, 10),
    "fields" => $fields,
]);
