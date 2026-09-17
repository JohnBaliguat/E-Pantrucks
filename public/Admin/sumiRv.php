<?php 
include "php/session-check.php"; 
include "php/config/config.php";
include_once "php/helpers/entry_date_filter.php";

$segment_filter = "SumiRV";
$selectedEntryDate = getSelectedEntryDate();
$query = "SELECT entry_id, entry_type, segment_empty, activity_empty, segment, activity, remarks, created_date, pullout_location_arrival_date, pullout_location_arrival_time, pullout_location_departure_date, pullout_location_departure_time, ph_arrival_date, ph_arrival_time, van_alpha, van_number, van_name, ph, shipper, ecs, tr, gs, waybill, waybill_empty, waybill_date, empty_trip_receipt_date, prime_mover, driver, empty_pullout_location, loaded_van_loading_start_date, loaded_van_loading_start_time, loaded_van_loading_finish_date, loaded_van_loading_finish_time, loaded_van_delivery_departure_date, loaded_van_delivery_departure_time, loaded_van_delivery_arrival_date, loaded_van_delivery_arrival_time, delivery_location_arrival_date, delivery_location_arrival_time, genset_shutoff_date, genset_shutoff_time, end_uploading_date, end_uploading_time, dr_no, load_description, delivered_by_prime_mover, delivered_by_driver, delivered_to, delivered_remarks, genset_hr_meter_start, genset_hr_meter_end, genset_start_date, genset_start_time, genset_end_date, genset_end_time FROM operations WHERE entry_type = 'RV ENTRY' AND segment = ? AND created_date::date = ? ORDER BY entry_id DESC";
$stmt = $conn->prepare($query);
$stmt->execute([$segment_filter, $selectedEntryDate]);
$result = $stmt;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sumi RV Data Entry - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/DataTables/datatables.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
    <link rel="stylesheet" href="assets/alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
    <style>#dataEntryForm input[type="text"] { text-transform: uppercase; }</style>
    
</head>

<body>
    <div class="wrapper">
        <?php include "sidebar.php"; ?>

        <div id="content">

            <?php include "navbar.php"; ?>

            <div class="main-content">
                <div class="row">
                    <div class="col-md-5">
                        <div class="content-header mb-4">
                            <h2 class="fw-bold">Sumi RV Data Entry</h2>
                            <p class="text-muted">Create, view, and manage Sumi RV segment entries efficiently.</p>
                        </div>
                    </div>
                    <div class="col-md-7 text-end">
                        <div class="btn-group" role="group" aria-label="Basic outlined example">
                            <a class="btn btn-outline-secondary" href="abcrv">ABC RV</a>
                            <a class="btn btn-outline-secondary" href="doleRv">Dole RV</a>
                            <a class="btn btn-outline-secondary" href="sumiRv">Sumi/Farmined RV</a>
                            <a class="btn btn-outline-secondary" href="tdcRv">TDC/Good Farmer RV</a>
                            <a class="btn btn-outline-secondary" href="others">Others</a>
                            <a class="btn btn-outline-secondary" href="DPC_KDI">DPC_KDS & OPM</a>
                            <a class="btn btn-outline-secondary" href="cargoTruck">Cargo Truck</a>
                            <a class="btn btn-outline-secondary" href="dryVan">Dry Van</a>
                        </div>
                    </div>
                </div>


                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>New Sumi RV Entry</h5>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#entryForm">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                    <div class="collapse" id="entryForm">
                        <div class="card-body">
                            <form id="dataEntryForm">
                                <input type="hidden" id="data_id" name="data_id" value="" />
                                <div class="row g-3">
                                    <h5>EMPTY CONTAINER VAN</h5>
                                    <div class="col-md-6" hidden>
                                        <div class="mb-3 position-relative">
                                            <label for="segment_empty" class="form-label">Segment</label>
                                            <input type="text" class="form-control" id="segment_empty" name="segment_empty" value="SumiRV" required>
                                            <ul id="segmentEmptyList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6" hidden>
                                        <div class="mb-3 position-relative">
                                            <label for="activity_empty" class="form-label">Activity</label>
                                            <input type="text" class="form-control" id="activity_empty" name="activity_empty" required>
                                            <ul id="activityEmptyList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <label for="waybill_empty" class="form-label">Trip Receipt No. <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="waybill_empty" name="waybill_empty" required maxlength="6" inputmode="numeric">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="empty_trip_receipt_date" class="form-label">Trip Receipt Date</label>
                                        <input type="text" class="form-control" id="empty_trip_receipt_date" name="empty_trip_receipt_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="pullout_location_arrival_date" class="form-label">Pullout Location - Arrival Date <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="pullout_location_arrival_date" name="pullout_location_arrival_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="pullout_location_arrival_time" class="form-label">Pullout Location - Arrival Time <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="pullout_location_arrival_time" name="pullout_location_arrival_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="pullout_location_departure_date" class="form-label">Pullout Location - Departure Date <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="pullout_location_departure_date" name="pullout_location_departure_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="pullout_location_departure_time" class="form-label">Pullout Location - Departure Time <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="pullout_location_departure_time" name="pullout_location_departure_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="ph_arrival_date" class="form-label">PH Arrival Date <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="ph_arrival_date" name="ph_arrival_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="ph_arrival_time" class="form-label">PH Arrival Time <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="ph_arrival_time" name="ph_arrival_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM" required>
                                    </div>
                                    <div class="col-md-6" hidden>
                                        <label for="delivery_location_arrival_date" class="form-label">DELIVERY LOCATION - ARRIVAL DATE</label>
                                        <input type="text" class="form-control" id="delivery_location_arrival_date" name="delivery_location_arrival_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                    </div>
                                    <div class="col-md-6" hidden>
                                        <label for="delivery_location_arrival_time" class="form-label">DELIVERY LOCATION - ARRIVAL TIME</label>
                                        <input type="text" class="form-control" id="delivery_location_arrival_time" name="delivery_location_arrival_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="van_alpha" class="form-label">VAN - ALPHA <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="van_alpha" name="van_alpha" required maxlength="4">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="van_number" class="form-label">VAN - NUMBER <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="van_number" name="van_number" required maxlength="7" inputmode="numeric">
                                    </div>

                                    <div class="col-md-4">
                                        <label for="van_name" class="form-label">VAN NAME <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="van_name" name="van_name" required>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3 position-relative">
                                            <label for="ph" class="form-label">PH (Packing House / Location) <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="ph" name="ph" required autocomplete="off" placeholder="Search location or PH…">
                                            <ul id="phList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1050; display: none; max-height: 240px; overflow-y: auto;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3 position-relative">
                                            <label for="shipper" class="form-label">SHIPPER <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="shipper" name="shipper" required autocomplete="off" placeholder="Search shipper…">
                                            <ul id="shipperList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1040; display: none; max-height: 240px; overflow-y: auto;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="ecs" class="form-label">ECS <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="ecs" name="ecs" required>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3 position-relative">
                                            <label for="tr" class="form-label">TR <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="tr" name="tr" required autocomplete="off" placeholder="Search trailer…">
                                            <ul id="trailerList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1040; display: none; max-height: 240px; overflow-y: auto;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3 position-relative">
                                            <label for="gs" class="form-label">GS <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="gs" name="gs" required autocomplete="off" placeholder="Search genset (GS)…">
                                            <ul id="gensetList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1030; display: none; max-height: 240px; overflow-y: auto;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3 position-relative">
                                            <label for="prime_mover" class="form-label">PRIME MOVER <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="prime_mover" name="prime_mover" required autocomplete="off">
                                            <ul id="truckList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1020; display: none; max-height: 240px; overflow-y: auto;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="driver" class="form-label">DRIVER <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="driver" name="driver" required autocomplete="off">
                                            <ul id="driverList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1010; display: none; max-height: 240px; overflow-y: auto;"></ul>
                                            <input type="hidden" id="driver_idNumber" name="driver_idNumber">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="empty_pullout_location" class="form-label">EMPTY PULL-OUT LOCATION <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="empty_pullout_location" name="empty_pullout_location" required autocomplete="off">
                                            <ul id="emptyPulloutLocationList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1010; display: none; max-height: 240px; overflow-y: auto;"></ul>
                                        </div>
                                    </div>
                                    <h5>LOADED CONTAINER VAN</h5>
                                    <div class="col-md-6" hidden>
                                        <div class="mb-3 position-relative">
                                            <label for="segment" class="form-label">Segment</label>
                                            <input type="text" class="form-control" id="segment" name="segment" value="SumiRV" required>
                                            <ul id="segmentList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative" hidden>
                                            <label for="activity" class="form-label">Activity</label>
                                            <input type="text" class="form-control" id="activity" name="activity" required>
                                            <ul id="activityList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <label for="waybill" class="form-label">Trip Receipt No. <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="waybill" name="waybill" required maxlength="6" inputmode="numeric">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="waybill_date" class="form-label">Trip Receipt Date (Transaction Date)</label>
                                        <input type="text" class="form-control" id="waybill_date" name="waybill_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="loading_start_date" class="form-label">LOADING SCHEDULE START DATE <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="loading_start_date" name="loading_start_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="loading_start_time" class="form-label">LOADING SCHEDULE START TIME <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="loading_start_time" name="loading_start_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="loading_finish_date" class="form-label">LOADING SCHEDULE FINISH DATE <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="loading_finish_date" name="loading_finish_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="loading_finish_time" class="form-label">LOADING SCHEDULE FINISH TIME <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="loading_finish_time" name="loading_finish_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="delivery_departure_date" class="form-label">DELIVERY DEPARTURE DATE <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="delivery_departure_date" name="delivery_departure_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="delivery_departure_time" class="form-label">DELIVERY DEPARTURE TIME <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="delivery_departure_time" name="delivery_departure_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="delivery_arrival_date" class="form-label">DELIVERY ARRIVAL DATE <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="delivery_arrival_date" name="delivery_arrival_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="delivery_arrival_time" class="form-label">DELIVERY ARRIVAL TIME <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="delivery_arrival_time" name="delivery_arrival_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="end_unloading_start_date" class="form-label">END OF UNLOADING DATE <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="end_unloading_start_date" name="end_unloading_start_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="end_unloading_start_time" class="form-label">END OF UNLOADING TIME <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="end_unloading_start_time" name="end_unloading_start_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM" required>
                                    </div>
                                    <div class="col-md-6" hidden>
                                        <label for="end_unloading_finish_date" class="form-label">END OF UNLOADING FINISH DATE</label>
                                        <input type="text" class="form-control" id="end_unloading_finish_date" name="end_unloading_finish_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                    </div>
                                    <div class="col-md-6" hidden>
                                        <label for="end_unloading_finish_time" class="form-label">END OF UNLOADING FINISH TIME</label>
                                        <input type="text" class="form-control" id="end_unloading_finish_time" name="end_unloading_finish_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="reference_documents" class="form-label">REFERENCE DOCUMENTS</label>
                                        <input type="text" class="form-control" id="reference_documents" name="reference_documents">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="dr_no" class="form-label">DR. NO. <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="dr_no" name="dr_no" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="load" class="form-label">LOAD <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="load" name="load" required>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3 position-relative">
                                            <label for="pm2" class="form-label">PM (Prime Mover) <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="pm2" name="pm2" autocomplete="off" required>
                                            <ul id="pm2List" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1020; display: none; max-height: 240px; overflow-y: auto;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3 position-relative">
                                            <label for="driver2" class="form-label">DRIVER <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="driver2" name="driver2" autocomplete="off" required>
                                            <ul id="driver2List" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1010; display: none; max-height: 240px; overflow-y: auto;"></ul>
                                            <input type="hidden" id="driver_idNumber2" name="driver_idNumber2">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3 position-relative">
                                            <label for="delivered_to" class="form-label">DELIVERED TO: STATE THE LOCATION <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="delivered_to" name="delivered_to" autocomplete="off" placeholder="Search location" required>
                                            <ul id="deliveredToList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1050; display: none; max-height: 240px; overflow-y: auto;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <label for="remarks" class="form-label">REMARKS</label>
                                        <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
                                    </div>
                                    <h5>GENSET HR METER</h5>
                                    <div class="col-md-12" hidden>
                                        <label for="genset_hr_meter" class="form-label">GENSET HR METER</label>
                                        <input type="text" class="form-control" id="genset_hr_meter" name="genset_hr_meter">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="hr_meter_start" class="form-label">HR METER START</label>
                                        <input type="text" class="form-control" id="hr_meter_start" name="hr_meter_start">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="hr_meter_end" class="form-label">HR METER END</label>
                                        <input type="text" class="form-control" id="hr_meter_end" name="hr_meter_end">
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="new_meter_reading" name="new_meter_reading" value="1">
                                            <label class="form-check-label" for="new_meter_reading">NEW METER READING (allow lower reading after genset meter reset/replacement)</label>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="defect_hubo" name="defect_hubo" value="1">
                                            <label class="form-check-label" for="defect_hubo">DEFECT HUBO (hour meter reading not required)</label>
                                        </div>
                                    </div>
                                    <h5>GENSET HR READING</h5>
                                    <div class="col-md-12" hidden>
                                        <label for="genset_hr_reading" class="form-label">GENSET HR READING</label>
                                        <input type="text" class="form-control" id="genset_hr_reading" name="genset_hr_reading">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="gs_start_date" class="form-label">GS START DATE <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="gs_start_date" name="gs_start_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="gs_start_time" class="form-label">GS START TIME <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="gs_start_time" name="gs_start_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="gs_end_date" class="form-label">GS END DATE <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="gs_end_date" name="gs_end_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="gs_end_time" class="form-label">GS END TIME <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="gs_end_time" name="gs_end_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM" required>
                                    </div>
                                    <div class="col-md-12">
                                        <label for="refueled" class="form-label">REFUELED</label>
                                        <input type="text" class="form-control" id="refueled" name="refueled">
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Save Entry</button>
                                        <button type="reset" class="btn btn-secondary"><i class="bi bi-x-circle me-2"></i>Clear</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-0">Sumi RV Entries</h5>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php renderEntryDateFilter($selectedEntryDate); ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="entriesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>No</th>
                                        <th>Date</th>
                                        <th>Trip Receipt No.</th>
                                        <th>TR Date</th>
                                        <th>Trip Receipt MTY</th>
                                        <th>TR Date (MTY)</th>
                                        <th>Van</th>
                                        <th>Driver</th>
                                        <th>Driver 2</th>
                                        <th>Remarks</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch(PDO::FETCH_ASSOC)) { ?>
                                    <tr data-id="<?php echo $row['entry_id']; ?>">
                                        <td><strong>#<?php echo $row['entry_id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['created_date'] ? date('m/d/Y', strtotime($row['created_date'])) : ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['waybill'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['waybill_date'] ? date('m/d/Y', strtotime($row['waybill_date'])) : ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['waybill_empty'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['empty_trip_receipt_date'] ? date('m/d/Y', strtotime($row['empty_trip_receipt_date'])) : ''); ?></td>
                                        <td><?php echo htmlspecialchars(trim(($row['van_alpha'] ?? '') . ' ' . ($row['van_number'] ?? '') . ' ' . ($row['van_name'] ?? ''))); ?></td>
                                        <td><?php echo htmlspecialchars($row['driver'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['delivered_by_driver'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['remarks'] ?? ''); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary btn-edit" title="Edit"><i class="bi bi-pencil"></i></button>
                                                <button type="button" class="btn btn-outline-danger btn-delete" title="Delete"><i class="bi bi-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/DataTables/datatables.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
    <script>
        // Helper function to convert YYYY-MM-DD to MM/DD/YYYY
        function formatDateForDisplay(dbDate) {
            if (!dbDate || dbDate === '') return '';
            const match = dbDate.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (!match) return dbDate;
            return `${match[2]}/${match[3]}/${match[1]}`;
        }

        // Helper function to convert HH:MM:SS or HH:MM to HH:MM format
        function formatTimeForDisplay(dbTime) {
            if (!dbTime || dbTime === '') return '';
            const match = dbTime.match(/^(\d{2}):(\d{2})/);
            if (!match) return dbTime;
            return match[1] + ':' + match[2];
        }

        // Fill form from fetched record with formatting
        function fillFormFromRecord(record) {
            document.getElementById("data_id").value = record.entry_id || '';
            
            const dateFields = ['pullout_location_arrival_date', 'pullout_location_departure_date', 'ph_arrival_date', 'delivery_location_arrival_date', 'loading_start_date', 'loading_finish_date', 'delivery_departure_date', 'delivery_arrival_date', 'end_unloading_start_date', 'end_unloading_finish_date', 'gs_start_date', 'gs_end_date', 'waybill_date', 'empty_trip_receipt_date'];
            const timeFields = ['pullout_location_arrival_time', 'pullout_location_departure_time', 'ph_arrival_time', 'delivery_location_arrival_time', 'loading_start_time', 'loading_finish_time', 'delivery_departure_time', 'delivery_arrival_time', 'end_unloading_start_time', 'end_unloading_finish_time', 'gs_start_time', 'gs_end_time'];

            const fieldMap = {
                segment_empty: 'segment_empty',
                activity_empty: 'activity_empty',
                segment: 'segment',
                activity: 'activity',
                remarks: 'remarks',
                pullout_location_arrival_date: 'pullout_location_arrival_date',
                pullout_location_arrival_time: 'pullout_location_arrival_time',
                pullout_location_departure_date: 'pullout_location_departure_date',
                pullout_location_departure_time: 'pullout_location_departure_time',
                ph_arrival_date: 'ph_arrival_date',
                ph_arrival_time: 'ph_arrival_time',
                delivery_location_arrival_date: 'delivery_location_arrival_date',
                delivery_location_arrival_time: 'delivery_location_arrival_time',
                van_alpha: 'van_alpha',
                van_number: 'van_number',
                van_name: 'van_name',
                ph: 'ph',
                shipper: 'shipper',
                ecs: 'ecs',
                tr: 'tr',
                gs: 'gs',
                waybill: 'waybill',
                waybill_empty: 'waybill_empty',
                waybill_date: 'waybill_date',
                empty_trip_receipt_date: 'empty_trip_receipt_date',
                prime_mover: 'prime_mover',
                driver: 'driver',
                driver_idNumber: 'driver_idNumber',
                empty_pullout_location: 'empty_pullout_location',
                loaded_van_loading_start_date: 'loading_start_date',
                loaded_van_loading_start_time: 'loading_start_time',
                loaded_van_loading_finish_date: 'loading_finish_date',
                loaded_van_loading_finish_time: 'loading_finish_time',
                loaded_van_delivery_departure_date: 'delivery_departure_date',
                loaded_van_delivery_departure_time: 'delivery_departure_time',
                loaded_van_delivery_arrival_date: 'delivery_arrival_date',
                loaded_van_delivery_arrival_time: 'delivery_arrival_time',
                end_uploading_date: 'end_unloading_start_date',
                end_uploading_time: 'end_unloading_start_time',
                end_unloading_start_date: 'end_unloading_start_date',
                end_unloading_start_time: 'end_unloading_start_time',
                end_unloading_finish_date: 'end_unloading_finish_date',
                end_unloading_finish_time: 'end_unloading_finish_time',
                dr_no: 'dr_no',
                reference_documents: 'reference_documents',
                load_description: 'load',
                delivered_by_prime_mover: 'pm2',
                delivered_by_driver: 'driver2',
                delivered_by_driverIdNumber: 'driver_idNumber2',
                delivered_to: 'delivered_to',
                genset_hr_meter: 'genset_hr_meter',
                genset_hr_meter_start: 'hr_meter_start',
                genset_hr_meter_end: 'hr_meter_end',
                genset_hr_reading: 'genset_hr_reading',
                genset_start_date: 'gs_start_date',
                genset_start_time: 'gs_start_time',
                genset_end_date: 'gs_end_date',
                genset_end_time: 'gs_end_time',
                refueled: 'refueled'
            };

            const fallbackMap = {
                delivery_location_arrival_date: ['pullout_location_departure_date'],
                delivery_location_arrival_time: ['pullout_location_departure_time'],
                end_unloading_start_date: ['end_uploading_date'],
                end_unloading_start_time: ['end_uploading_time'],
                end_unloading_finish_date: ['end_uploading_date'],
                end_unloading_finish_time: ['end_uploading_time']
            };

            Object.entries(fieldMap).forEach(([recordKey, inputId]) => {
                const input = document.getElementById(inputId);
                if (input) {
                    let value = record[recordKey] ?? '';
                    if ((value === '' || value === null) && fallbackMap[inputId]) {
                        const fallbackKey = fallbackMap[inputId].find(key => record[key] !== '' && record[key] != null);
                        if (fallbackKey) {
                            value = record[fallbackKey];
                        }
                    }
                    if (dateFields.includes(inputId) && value) {
                        value = formatDateForDisplay(value);
                    }
                    if (timeFields.includes(inputId) && value) {
                        value = formatTimeForDisplay(value);
                    }
                    input.value = value;
                }
            });
            const defectHubo = document.getElementById('defect_hubo');
            if (defectHubo) {
                defectHubo.checked = record.defect_hubo === true || record.defect_hubo === 1 || record.defect_hubo === '1';
            }
        }

        $(document).ready(function() {
            $("#enav").attr({
                "class" : "nav-link active"
            });
         });
    </script>
    <script>
        $(document).ready(function() {
            let rvTable = $('#entriesTable').DataTable({
                "paging": true,
                "lengthChange": true,
                "searching": true,
                "ordering": true,
                "info": true,
                "autoWidth": false,
                "pageLength": 10,
                "pagingType": "full_numbers"
            });

            const driverInput = document.getElementById("driver");
            const driverList = document.getElementById("driverList");
            const driver2Input = document.getElementById("driver2");
            const driver2List = document.getElementById("driver2List");
            const driverIdInput = document.getElementById("driver_idNumber");
            const driverId2Input = document.getElementById("driver_idNumber2");
            const phInput = document.getElementById("ph");
            const phList = document.getElementById("phList");
            const deliveredToInput = document.getElementById("delivered_to");
            const deliveredToList = document.getElementById("deliveredToList");
            const emptyPulloutLocationInput = document.getElementById("empty_pullout_location");
            const emptyPulloutLocationList = document.getElementById("emptyPulloutLocationList");
            const truckInput = document.getElementById("prime_mover");
            const truckList = document.getElementById("truckList");
            const pm2Input = document.getElementById("pm2");
            const pm2List = document.getElementById("pm2List");
            const gensetInput = document.getElementById("gs");
            const gensetList = document.getElementById("gensetList");
            const trailerInput = document.getElementById("tr");
            const trailerList = document.getElementById("trailerList");
            const shipperInput = document.getElementById("shipper");
            const shipperList = document.getElementById("shipperList");

            let allDrivers = [];
            let allLocations = [];
            let allTrucks = [];
            let allGensets = [];
            let allTrailers = [];
            let allShippers = [];
            const allSearchLists = [phList, deliveredToList, driverList, driver2List, truckList, pm2List, gensetList, trailerList, shipperList].filter(Boolean);

            fetch("php/fetch/get_drivers.php")
                .then(res => res.json())
                .then(data => { allDrivers = Array.isArray(data) ? data : []; })
                .catch(() => { allDrivers = []; });

            fetch("php/fetch/get_locations.php")
                .then(res => res.json())
                .then(data => { allLocations = Array.isArray(data) ? data : []; })
                .catch(() => { allLocations = []; });

            fetch("php/fetch/get_trucks.php")
                .then(res => res.json())
                .then(data => { allTrucks = Array.isArray(data) ? data : []; })
                .catch(() => { allTrucks = []; });

            fetch("php/fetch/get_gensets.php")
                .then(res => res.json())
                .then(data => { allGensets = Array.isArray(data) ? data : []; })
                .catch(() => { allGensets = []; });

            fetch("php/fetch/get_trailers.php")
                .then(res => res.json())
                .then(data => { allTrailers = Array.isArray(data) ? data : []; })
                .catch(() => { allTrailers = []; });

            fetch("php/fetch/get_shippers.php")
                .then(res => res.json())
                .then(data => { allShippers = Array.isArray(data) ? data : []; })
                .catch(() => { allShippers = []; });

            function hideDropdown(listElem) {
                if (!listElem) return;
                listElem.style.display = "none";
                listElem.innerHTML = "";
            }

            function showDropdown(listElem) {
                if (!listElem) return;
                listElem.style.maxHeight = "240px";
                listElem.style.overflowY = "auto";
                listElem.style.overflowX = "hidden";
                listElem.style.display = "block";
            }

            function filterDropdown(inputElem, listElem, dataArr, onSelect) {
                const searchVal = inputElem.value.trim().toLowerCase();
                if (!searchVal) {
                    hideDropdown(listElem);
                    return;
                }

                listElem.innerHTML = "";
                const filtered = dataArr.filter(item => String(item).toLowerCase().includes(searchVal));

                if (filtered.length === 0) {
                    hideDropdown(listElem);
                    return;
                }

                filtered.forEach((item, index) => {
                    const label = String(item);
                    const li = document.createElement("li");
                    li.className = "list-group-item list-group-item-action";
                    li.textContent = label;
                    li.dataset.pickValue = label;

                    if (index === 0) {
                        li.classList.add("active-suggestion");
                    }

                    li.addEventListener("mousedown", function(event) {
                        event.preventDefault();
                        onSelect(label);
                        hideDropdown(listElem);
                    });

                    listElem.appendChild(li);
                });

                showDropdown(listElem);
            }

            function filterDropdownRecords(inputElem, listElem, records, getValue, formatLine, onPick) {
                const searchVal = inputElem.value.trim().toLowerCase();
                if (!searchVal) {
                    hideDropdown(listElem);
                    return;
                }

                listElem.innerHTML = "";
                const filtered = records.filter(record => getValue(record).toLowerCase().includes(searchVal));

                if (filtered.length === 0) {
                    hideDropdown(listElem);
                    return;
                }

                filtered.forEach((record, index) => {
                    const value = getValue(record);
                    const li = document.createElement("li");
                    li.className = "list-group-item list-group-item-action";
                    li.textContent = formatLine(record);
                    li.dataset.pickValue = value;

                    if (index === 0) {
                        li.classList.add("active-suggestion");
                    }

                    li.addEventListener("mousedown", function(event) {
                        event.preventDefault();
                        onPick(value);
                        hideDropdown(listElem);
                    });

                    listElem.appendChild(li);
                });

                showDropdown(listElem);
            }

            function attachKeyboardNav(inputElem, listElem, onSelect) {
                if (!inputElem || !listElem) return;

                let activeIndex = 0;

                inputElem.addEventListener("keydown", function(e) {
                    const items = listElem.querySelectorAll("li");

                    if (e.key === "Escape") {
                        hideDropdown(listElem);
                        return;
                    }

                    if (!items.length) {
                        return;
                    }

                    if (e.key === "ArrowDown") {
                        e.preventDefault();
                        activeIndex = (activeIndex + 1) % items.length;
                        updateActive(items, activeIndex);
                    } else if (e.key === "ArrowUp") {
                        e.preventDefault();
                        activeIndex = (activeIndex - 1 + items.length) % items.length;
                        updateActive(items, activeIndex);
                    } else if (e.key === "Enter") {
                        const activeItem = items[activeIndex];
                        if (activeItem) {
                            e.preventDefault();
                            onSelect(activeItem.dataset.pickValue || activeItem.textContent);
                            hideDropdown(listElem);
                        }
                    } else if (e.key === "Tab") {
                        const activeItem = items[activeIndex];
                        if (activeItem) {
                            onSelect(activeItem.dataset.pickValue || activeItem.textContent);
                            hideDropdown(listElem);
                        } else {
                            hideDropdown(listElem);
                        }
                    }
                });

                inputElem.addEventListener("input", function() {
                    activeIndex = 0;
                });

                function updateActive(items, index) {
                    items.forEach(item => item.classList.remove("active-suggestion"));
                    if (items[index]) {
                        items[index].classList.add("active-suggestion");
                        items[index].scrollIntoView({ block: "nearest" });
                    }
                }
            }

            function updateActivitiesForShipper(shipper) {
                const shipperMapping = {
                    "Sumifru": { empty: "TDC-Sumi.RV.Empty", loaded: "TDC-Sumi.RV.Loaded" },
                    "Farmind": { empty: "TDC-Joyvio.RV.Empty", loaded: "TDC-Joyvio.RV.Loaded" }
                };
                
                const trimmedShipper = shipper ? shipper.trim() : "";
                
                if (trimmedShipper && shipperMapping[trimmedShipper]) {
                    const activities = shipperMapping[trimmedShipper];
                    const activityEmptyElem = document.getElementById("activity_empty");
                    const activityElem = document.getElementById("activity");
                    
                    if (activityEmptyElem) {
                        activityEmptyElem.value = activities.empty;
                    }
                    if (activityElem) {
                        activityElem.value = activities.loaded;
                    }
                }
            }

            if (driverInput && driverList) {
                driverInput.addEventListener("input", function() {
                    driverIdInput.value = "";
                    filterDropdown(this, driverList, allDrivers.map(d => d.name || ""), (name) => {
                        driverInput.value = name;
                        const selected = allDrivers.find(d => d.name === name);
                        driverIdInput.value = selected ? selected.id : "";
                    });
                });
            }

            if (driver2Input && driver2List) {
                driver2Input.addEventListener("input", function() {
                    driverId2Input.value = "";
                    filterDropdown(this, driver2List, allDrivers.map(d => d.name || ""), (name) => {
                        driver2Input.value = name;
                        const selected = allDrivers.find(d => d.name === name);
                        driverId2Input.value = selected ? selected.id : "";
                    });
                });
            }

            if (phInput && phList) {
                phInput.addEventListener("input", function() {
                    filterDropdownRecords(this, phList, allLocations, (loc) => loc.location_name || "", (loc) => loc.location_name || "", (name) => {
                        phInput.value = name;
                    });
                });
            }

            if (deliveredToInput && deliveredToList) {
                deliveredToInput.addEventListener("input", function() {
                    filterDropdownRecords(this, deliveredToList, allLocations, (loc) => loc.location_name || "", (loc) => loc.location_name || "", (name) => {
                        deliveredToInput.value = name;
                    });
                });
            }

            if (emptyPulloutLocationInput && emptyPulloutLocationList) {
                emptyPulloutLocationInput.addEventListener("input", function() {
                    filterDropdownRecords(this, emptyPulloutLocationList, allLocations, (loc) => loc.location_name || "", (loc) => loc.location_name || "", (name) => {
                        emptyPulloutLocationInput.value = name;
                    });
                });
            }

            if (truckInput && truckList) {
                truckInput.addEventListener("input", function() {
                    filterDropdown(this, truckList, allTrucks, (name) => {
                        truckInput.value = name;
                    });
                });
            }

            if (pm2Input && pm2List) {
                pm2Input.addEventListener("input", function() {
                    filterDropdown(this, pm2List, allTrucks, (name) => {
                        pm2Input.value = name;
                    });
                });
            }

            if (gensetInput && gensetList) {
                gensetInput.addEventListener("input", function() {
                    filterDropdownRecords(this, gensetList, allGensets, (unit) => unit.unit_name || "", (unit) => {
                        const bits = [unit.unit_name];
                        if (unit.unit_model) bits.push(unit.unit_model);
                        if (unit.unit_cluster) bits.push(unit.unit_cluster);
                        return bits.join(" - ");
                    }, (name) => {
                        gensetInput.value = name;
                        autofillLastHrMeter(name);
                    });
                });
            }

            // Auto-fill HR METER START with the selected genset's last recorded reading.
            function autofillLastHrMeter(gensetName) {
                const startEl = document.getElementById("hr_meter_start");
                if (!startEl || !gensetName) return;
                fetch("php/fetch/get_last_hr_meter.php?gs=" + encodeURIComponent(gensetName), { cache: "no-store" })
                    .then(r => r.json())
                    .then(d => { if (d && d.success && d.last_hr_meter != null && d.last_hr_meter !== "") startEl.value = d.last_hr_meter; })
                    .catch(() => {});
            }

            // Clearing the genset input also clears the auto-filled HR METER START.
            if (gensetInput) {
                gensetInput.addEventListener("input", function () {
                    if (!this.value.trim()) {
                        const startEl = document.getElementById("hr_meter_start");
                        if (startEl) startEl.value = "";
                    }
                });
            }

            if (trailerInput && trailerList) {
                trailerInput.addEventListener("input", function() {
                    filterDropdownRecords(this, trailerList, allTrailers, (trailer) => trailer.trailer_name || "", (trailer) => trailer.trailer_name || "", (name) => {
                        trailerInput.value = name;
                    });
                });
            }

            if (shipperInput && shipperList) {
                shipperInput.addEventListener("input", function() {
                    filterDropdownRecords(this, shipperList, allShippers, (shipper) => shipper.shipper || "", (shipper) => shipper.shipper || "", (name) => {
                        shipperInput.value = name;
                        updateActivitiesForShipper(name);
                    });
                });
            }

            document.addEventListener("mousedown", function(event) {
                if (event.target.closest(".position-relative")) return;
                allSearchLists.forEach(hideDropdown);
            });

            attachKeyboardNav(driverInput, driverList, (name) => {
                driverInput.value = name;
                const selected = allDrivers.find(d => d.name === name);
                driverIdInput.value = selected ? selected.id : "";
            });

            attachKeyboardNav(driver2Input, driver2List, (name) => {
                driver2Input.value = name;
                const selected = allDrivers.find(d => d.name === name);
                driverId2Input.value = selected ? selected.id : "";
            });

            attachKeyboardNav(phInput, phList, (name) => {
                phInput.value = name;
            });

            attachKeyboardNav(deliveredToInput, deliveredToList, (name) => {
                deliveredToInput.value = name;
            });

            attachKeyboardNav(emptyPulloutLocationInput, emptyPulloutLocationList, (name) => {
                emptyPulloutLocationInput.value = name;
            });

            attachKeyboardNav(truckInput, truckList, (name) => {
                truckInput.value = name;
            });

            attachKeyboardNav(pm2Input, pm2List, (name) => {
                pm2Input.value = name;
            });

            attachKeyboardNav(gensetInput, gensetList, (name) => {
                gensetInput.value = name;
                autofillLastHrMeter(name);
            });

            attachKeyboardNav(trailerInput, trailerList, (name) => {
                trailerInput.value = name;
            });

            attachKeyboardNav(shipperInput, shipperList, (name) => {
                shipperInput.value = name;
                updateActivitiesForShipper(name);
            });

            const form = document.getElementById("dataEntryForm");
            document.getElementById("segment_empty").value = "SumiRV";
            document.getElementById("segment").value = "SumiRV";
            form.querySelectorAll('input[type="text"]').forEach(function (input) {
                input.addEventListener('input', function () {
                    const pos = this.selectionStart;
                    this.value = this.value.toUpperCase();
                    this.setSelectionRange(pos, pos);
                });
            });

            function getTableRowData(record) {
                const displayDate = formatDateForDisplay(record.created_date || '');
                const displayVan = [record.van_alpha, record.van_number, record.van_name].filter(Boolean).join(' ').trim();

                return [
                    '#' + record.entry_id,
                    displayDate,
                    record.waybill || '',
                    record.waybill_date ? formatDateForDisplay(record.waybill_date) : '',
                    record.waybill_empty || '',
                    record.empty_trip_receipt_date ? formatDateForDisplay(record.empty_trip_receipt_date) : '',
                    displayVan,
                    record.driver || '',
                    record.delivered_by_driver || '',
                    record.remarks || '',
                    `<div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary btn-edit" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-outline-danger btn-delete" title="Delete"><i class="bi bi-trash"></i></button>
                    </div>`
                ];
            }

            function setRowDataAttributes(row, record) {
                if (!row || !record) return;
                row.dataset.id = record.entry_id;
                row.dataset.segment = record.segment || '';
                row.dataset.activity = record.activity || '';
                row.dataset.waybill = record.waybill || '';
                row.dataset.driver = record.driver || '';
                row.dataset.remarks = record.remarks || '';
            }

            function applyDefectHuboState() {
                const defective = document.getElementById('defect_hubo')?.checked;
                ['hr_meter_start', 'hr_meter_end'].forEach((id) => {
                    const input = document.getElementById(id);
                    if (!input) return;
                    input.disabled = defective;
                    if (defective) input.value = '';
                });
            }

            document.getElementById('defect_hubo')?.addEventListener('change', applyDefectHuboState);
            applyDefectHuboState();

            function clearForm() {
                form.reset();
                document.getElementById("data_id").value = "";
                document.getElementById("segment_empty").value = "SumiRV";
                document.getElementById("segment").value = "SumiRV";
            }

            // Convert display format date (MM/DD/YYYY) to database format (YYYY-MM-DD)
            function convertDateToDatabase(displayDate) {
                if (!displayDate || displayDate === '') return '';
                const parts = displayDate.trim().split('/');
                if (parts.length < 2 || parts.length > 3) return displayDate;
                let month = parts[0].padStart(2, '0');
                let day = parts[1].padStart(2, '0');
                let year = parts[2] || new Date().getFullYear().toString();
                if (year.length === 2) year = '20' + year;
                if (isNaN(month) || isNaN(day) || isNaN(year)) return displayDate;
                return `${year}-${month}-${day}`;
            }

            // Convert display format time (HHMM) to database format (HH:MM)
            function convertTimeToDatabase(displayTime) {
                if (!displayTime || displayTime === '') return '';
                let time = displayTime.replace(':', '');
                if (time.match(/^\d{3,4}$/)) {
                    time = time.padStart(4, '0');
                    const hh = time.substring(0, 2);
                    const mm = time.substring(2, 4);
                    if (parseInt(hh) > 23 || parseInt(mm) > 59) return displayTime;
                    return `${hh}:${mm}`;
                }
                if (time.match(/^\d{2}:\d{2}$/)) {
                    const [hh, mm] = time.split(':');
                    if (parseInt(hh) > 23 || parseInt(mm) > 59) return displayTime;
                    return time;
                }
                return displayTime;
            }

            // Validate and parse DB-formatted date/time strings
            function isValidDBDate(d) {
                return /^\d{4}-\d{2}-\d{2}$/.test(d);
            }

            function isValidDBTime(t) {
                return /^\d{2}:\d{2}$/.test(t);
            }

            function parseDBDateTime(d, t) {
                if (!d || !isValidDBDate(d)) return null;
                if (!t || !isValidDBTime(t)) t = '00:00';
                const parts = d.split('-');
                const timeParts = t.split(':');
                return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10), parseInt(timeParts[0], 10), parseInt(timeParts[1], 10), 0);
            }

            function getValues() {
                const values = {
                    id: document.getElementById("data_id").value,
                    segment_empty: document.getElementById("segment_empty").value,
                    activity_empty: document.getElementById("activity_empty").value,
                    segment: document.getElementById("segment").value,
                    activity: document.getElementById("activity").value,
                    remarks: document.getElementById("remarks").value,
                    pullout_location_arrival_date: document.getElementById("pullout_location_arrival_date").value,
                    pullout_location_arrival_time: document.getElementById("pullout_location_arrival_time").value,
                    pullout_location_departure_date: document.getElementById("pullout_location_departure_date").value,
                    pullout_location_departure_time: document.getElementById("pullout_location_departure_time").value,
                    ph_arrival_date: document.getElementById("ph_arrival_date").value,
                    ph_arrival_time: document.getElementById("ph_arrival_time").value,
                    delivery_location_arrival_date: document.getElementById("delivery_location_arrival_date").value,
                    delivery_location_arrival_time: document.getElementById("delivery_location_arrival_time").value,
                    van_alpha: document.getElementById("van_alpha").value,
                    van_number: document.getElementById("van_number").value,
                    van_name: document.getElementById("van_name").value,
                    ph: document.getElementById("ph").value,
                    shipper: document.getElementById("shipper").value,
                    ecs: document.getElementById("ecs").value,
                    tr: document.getElementById("tr").value,
                    gs: document.getElementById("gs").value,
                    waybill: document.getElementById("waybill").value,
                    waybill_empty: document.getElementById("waybill_empty").value,
                    waybill_date: document.getElementById("waybill_date").value,
                    empty_trip_receipt_date: document.getElementById("empty_trip_receipt_date").value,
                    prime_mover: document.getElementById("prime_mover").value,
                    driver: document.getElementById("driver").value,
                    driver_idNumber: document.getElementById("driver_idNumber").value,
                    empty_pullout_location: document.getElementById("empty_pullout_location").value,
                    loaded_van_loading_start_date: document.getElementById("loading_start_date").value,
                    loaded_van_loading_start_time: document.getElementById("loading_start_time").value,
                    loaded_van_loading_finish_date: document.getElementById("loading_finish_date").value,
                    loaded_van_loading_finish_time: document.getElementById("loading_finish_time").value,
                    loaded_van_delivery_departure_date: document.getElementById("delivery_departure_date").value,
                    loaded_van_delivery_departure_time: document.getElementById("delivery_departure_time").value,
                    loaded_van_delivery_arrival_date: document.getElementById("delivery_arrival_date").value,
                    loaded_van_delivery_arrival_time: document.getElementById("delivery_arrival_time").value,
                    end_uploading_date: document.getElementById("end_unloading_finish_date").value || document.getElementById("end_unloading_start_date").value,
                    end_uploading_time: document.getElementById("end_unloading_finish_time").value || document.getElementById("end_unloading_start_time").value,
                    end_unloading_start_date: document.getElementById("end_unloading_start_date").value,
                    end_unloading_start_time: document.getElementById("end_unloading_start_time").value,
                    end_unloading_finish_date: document.getElementById("end_unloading_finish_date").value,
                    end_unloading_finish_time: document.getElementById("end_unloading_finish_time").value,
                    dr_no: document.getElementById("dr_no").value,
                    reference_documents: document.getElementById("reference_documents").value,
                    load_description: document.getElementById("load").value,
                    delivered_by_prime_mover: document.getElementById("pm2").value,
                    delivered_by_driver: document.getElementById("driver2").value,
                    delivered_by_driverIdNumber: document.getElementById("driver_idNumber2").value,
                    delivered_to: document.getElementById("delivered_to").value,
                    genset_hr_meter: document.getElementById("genset_hr_meter").value,
                    genset_hr_meter_start: document.getElementById("hr_meter_start").value,
                    genset_hr_meter_end: document.getElementById("hr_meter_end").value,
                    new_meter_reading: document.getElementById("new_meter_reading").checked ? '1' : '0',
                    defect_hubo: document.getElementById("defect_hubo").checked ? '1' : '0',
                    genset_hr_reading: document.getElementById("genset_hr_reading").value,
                    genset_start_date: document.getElementById("gs_start_date").value,
                    genset_start_time: document.getElementById("gs_start_time").value,
                    genset_end_date: document.getElementById("gs_end_date").value,
                    genset_end_time: document.getElementById("gs_end_time").value,
                    refueled: document.getElementById("refueled").value
                };

                // Apply date/time conversions
                const dateFields = ['pullout_location_arrival_date', 'pullout_location_departure_date', 'ph_arrival_date', 
                    'delivery_location_arrival_date', 'loaded_van_loading_start_date', 'loaded_van_loading_finish_date', 
                    'loaded_van_delivery_departure_date', 'loaded_van_delivery_arrival_date', 'end_uploading_date',
                    'end_unloading_start_date', 'end_unloading_finish_date', 'genset_start_date', 'genset_end_date', 'waybill_date', 'empty_trip_receipt_date'];
                const timeFields = ['pullout_location_arrival_time', 'pullout_location_departure_time', 'ph_arrival_time',
                    'delivery_location_arrival_time', 'loaded_van_loading_start_time', 'loaded_van_loading_finish_time',
                    'loaded_van_delivery_departure_time', 'loaded_van_delivery_arrival_time', 'end_uploading_time',
                    'end_unloading_start_time', 'end_unloading_finish_time', 'genset_start_time', 'genset_end_time'];
                
                dateFields.forEach(field => { if (values[field]) values[field] = convertDateToDatabase(values[field]); });
                timeFields.forEach(field => { if (values[field]) values[field] = convertTimeToDatabase(values[field]); });

                return values;
            }

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const values = getValues();
                if (!values.segment) {
                    Swal.fire('Missing field', 'Segment is required.', 'warning');
                    return;
                }
                // HR Meter Start/End are optional for Sumifru RV; 0 is allowed.
                const hrStartRaw = String(values.genset_hr_meter_start ?? '').trim();
                const hrEndRaw = String(values.genset_hr_meter_end ?? '').trim();
                const hrStart = parseFloat(hrStartRaw);
                const hrEnd = parseFloat(hrEndRaw);
                if (values.defect_hubo !== '1' && hrStartRaw !== '' && (!Number.isFinite(hrStart) || hrStart < 0)) {
                    Swal.fire('Invalid HR Meter', 'HR Meter Start must be a number of 0 or greater.', 'warning');
                    return;
                }
                if (values.defect_hubo !== '1' && hrEndRaw !== '' && (!Number.isFinite(hrEnd) || hrEnd < 0)) {
                    Swal.fire('Invalid HR Meter', 'HR Meter End must be a number of 0 or greater.', 'warning');
                    return;
                }
                if (values.defect_hubo !== '1' && hrStartRaw !== '' && hrEndRaw !== '' && hrEnd < hrStart && values.new_meter_reading !== '1') {
                    Swal.fire('Invalid HR Meter', 'HR Meter End must not be less than HR Meter Start.', 'warning');
                    return;
                }
                if (values.waybill && !/^\d{6}$/.test(values.waybill)) {
                    Swal.fire('Invalid format', 'Waybill must be exactly 6 digits.', 'warning');
                    return;
                }
                if (values.waybill_empty && !/^\d{6}$/.test(values.waybill_empty)) {
                    Swal.fire('Invalid format', 'Waybill (Empty) must be exactly 6 digits.', 'warning');
                    return;
                }
                if (values.van_alpha && !/^[A-Za-z]{4}$/.test(values.van_alpha)) {
                    Swal.fire('Invalid format', 'Van Alpha must be exactly 4 letters.', 'warning');
                    return;
                }
                if (values.van_number && !/^\d{7}$/.test(values.van_number)) {
                    Swal.fire('Invalid format', 'Van Number must be exactly 7 digits.', 'warning');
                    return;
                }

                // Chronological order validations
                function validateSequence(pairs, labelSequence) {
                    const dt = pairs.map(p => parseDBDateTime(values[p[0]], values[p[1]]));
                    for (let i = 0; i < dt.length - 1; i++) {
                        if (dt[i] && dt[i+1] && dt[i].getTime() > dt[i+1].getTime()) {
                            const lblPrev = labelSequence[i] || 'previous';
                            const lblNext = labelSequence[i+1] || 'next';
                            Swal.fire('Invalid sequence', `${lblNext} must be the same or after ${lblPrev}.`, 'warning');
                            return false;
                        }
                    }
                    return true;
                }

                if (!validateSequence([
                    ['pullout_location_arrival_date', 'pullout_location_arrival_time'],
                    ['pullout_location_departure_date', 'pullout_location_departure_time'],
                    ['ph_arrival_date', 'ph_arrival_time']
                ], ['Pullout Location - Arrival','Pullout Location - Departure','PH Arrival'])) return;

                if (!validateSequence([
                    ['loaded_van_loading_start_date', 'loaded_van_loading_start_time'],
                    ['loaded_van_loading_finish_date', 'loaded_van_loading_finish_time'],
                    ['loaded_van_delivery_departure_date', 'loaded_van_delivery_departure_time'],
                    ['loaded_van_delivery_arrival_date', 'loaded_van_delivery_arrival_time'],
                    ['end_uploading_date', 'end_uploading_time']
                ], ['Loading Schedule Start','Loading Schedule Finish','Delivery Departure','Delivery Arrival','End of Unloading Start'])) return;
                $.ajax({
                    url: values.id ? 'php/update/rv.php' : 'php/insert/rv.php',
                    type: 'POST',
                    data: { action: values.id ? 'update-rv' : 'add-rv', data_id: values.id || '', ...values },
                    dataType: 'json',
                    success: function(data) {
                        if (data.success) {
                            if (data.record) {
                                if (values.id) {
                                    const row = document.querySelector(`#entriesTable tbody tr[data-id="${values.id}"]`);
                                    if (row) {
                                        const rowApi = rvTable.row(row);
                                        rowApi.data(getTableRowData(data.record)).draw(false);
                                        setRowDataAttributes(rowApi.node(), data.record);
                                    } else {
                                        rvTable.draw(false);
                                    }
                                } else {
                                    const addedRow = rvTable.row.add(getTableRowData(data.record));
                                    addedRow.draw(false);
                                    setRowDataAttributes(addedRow.node(), data.record);
                                }
                            } else {
                                rvTable.draw(false);
                            }
                            clearForm();
                            Swal.fire('Success', data.message, 'success');
                        } else {
                            Swal.fire('Error', data.message || 'Operation failed.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Unable to reach server', 'error');
                    }
                });
            });

            // Auto-open a record when arriving from the For Update queue (?load_id=).
            (function () {
                const loadId = new URLSearchParams(window.location.search).get('load_id');
                if (!loadId) return;
                fetch(`php/fetch/get_rv.php?id=${encodeURIComponent(loadId)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (!data.success || !data.record) return;
                        fillFormFromRecord(data.record);
                        const collapseEl = document.querySelector('#entryForm');
                        if (collapseEl) { collapseEl.classList.add('show'); setTimeout(() => collapseEl.scrollIntoView({ behavior: 'smooth', block: 'start' }), 350); }
                    })
                    .catch(() => {});
            })();

            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-edit')) {
                    const row = e.target.closest('tr');
                    const id = row.dataset.id;
                    if (!id) return;

                    fetch(`php/fetch/get_rv.php?id=${encodeURIComponent(id)}`)
                        .then(res => res.json())
                        .then(data => {
                            if (!data.success) {
                                Swal.fire('Error', data.message || 'Unable to load record.', 'error');
                                return;
                            }
                            fillFormFromRecord(data.record);
                            const collapseEl = document.querySelector('#entryForm');
                            if (collapseEl) {
                                collapseEl.classList.add('show');
                            }
                        })
                        .catch(() => {
                            Swal.fire('Error', 'Unable to fetch record.', 'error');
                        });
                    return;
                }

                if (e.target.closest('.btn-delete')) {
                    const row = e.target.closest('tr');
                    const id = row.dataset.id;
                    Swal.fire({
                        title: 'Confirm',
                        text: 'Delete this entry?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes'
                    }).then(result => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: 'php/delete/rv.php',
                                type: 'POST',
                                data: { action: 'delete-rv', data_id: id },
                                dataType: 'json',
                                success: function(result) {
                                    if (result.success) {
                                        rvTable.row(row).remove().draw(false);
                                        Swal.fire('Deleted!', 'Entry deleted.', 'success');
                                    }
                                }
                            });
                        }
                    });
                }
            });
        });
    </script>
    <!-- Trip receipt / waybill lookup against dispatch DB (auto-fill + alert) -->
    <script src="assets/js/trip_receipt_lookup.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof attachTripReceiptLookup !== 'function') return;
            // Two-leg RV form: scope each waybill's auto-fill to its own leg so the
            // EMPTY waybill never overwrites the LOADED fields and vice versa.
            // Empty leg fills the shared van/equipment + the empty pull-out driver/PM.
            var EMPTY_MAP = {
                driver: 'driver', truck: 'prime_mover', trailer: 'tr', genset: 'gs',
                ecs: 'ecs', container_alpha: 'van_alpha', container_number: 'van_number', ph: 'ph'
            };
            // Loaded leg fills only the delivery driver/PM + destination.
            var LOADED_MAP = { driver: 'driver2', truck: 'pm2', deliver_to: 'delivered_to' };
            if (document.getElementById('waybill_empty')) attachTripReceiptLookup({ input: 'waybill_empty', map: EMPTY_MAP });
            if (document.getElementById('waybill')) attachTripReceiptLookup({ input: 'waybill', map: LOADED_MAP });
        });
    </script>
</body>

</html>
