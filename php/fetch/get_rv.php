<?php
// Guard first: guarantees a JSON response even if a stray PHP notice/warning or an
// uncaught error would otherwise corrupt the body. Without it a single warning makes the
// front-end's res.json() throw and show the generic "Unable to fetch record." error.
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_operations_rv_dates_schema.php";
ensure_operations_rv_dates_schema($conn);

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'record' => null];

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    $response['message'] = 'Invalid record ID.';
    echo json_encode($response);
    exit;
}

$sql = "SELECT entry_id, entry_type, segment_empty, activity_empty, segment, activity, remarks, pullout_location_arrival_date, pullout_location_arrival_time, pullout_location_departure_date, pullout_location_departure_time, ph_arrival_date, ph_arrival_time, withdrawal_date, withdrawal_time, van_alpha, van_number, van_name, ph, shipper, ecs, tr, gs, waybill, waybill_empty, waybill_date, empty_trip_receipt_date, prime_mover, driver, empty_pullout_location, loaded_van_loading_start_date, loaded_van_loading_start_time, loaded_van_loading_finish_date, loaded_van_loading_finish_time, loaded_van_delivery_departure_date, loaded_van_delivery_departure_time, loaded_van_delivery_arrival_date, loaded_van_delivery_arrival_time, genset_shutoff_date, genset_shutoff_time, end_uploading_date, end_uploading_time, end_unloading_start_date, end_unloading_start_time, end_unloading_finish_date, end_unloading_finish_time, dr_no, reference_documents, genset_hr_meter, genset_hr_reading, refueled, defect_hubo, load_description, delivered_by_prime_mover, delivered_by_driver, delivered_to, delivered_remarks, genset_hr_meter_start, genset_hr_meter_end, genset_start_date, genset_start_time, genset_end_date, genset_end_time, \"driver_idNumber\", \"delivered_by_driverIdNumber\", created_date, delivery_location_arrival_date, delivery_location_arrival_time FROM operations WHERE entry_id = ?";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($record !== false) {
        $response['success'] = true;
        $response['record'] = $record;
    } else {
        $response['message'] = 'Record not found.';
    }
} catch (Throwable $e) {
    $response = ['success' => false, 'message' => 'Could not read record: ' . $e->getMessage(), 'record' => null];
}

// Substitute (don't fail on) any invalid UTF-8 byte in a text field, so one bad character
// can't blank the whole response and produce the generic fetch error.
$json = json_encode($response, JSON_INVALID_UTF8_SUBSTITUTE);
if ($json === false) {
    $json = json_encode([
        'success' => false,
        'message' => 'This record has unreadable characters — please re-check and re-save the entry.',
        'record' => null,
    ]);
}
echo $json;
exit;
?>
