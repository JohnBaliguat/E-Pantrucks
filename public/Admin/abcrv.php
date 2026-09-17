<?php 
include "php/session-check.php";
include "php/config/config.php";
include_once "php/helpers/entry_date_filter.php";
include_once "php/helpers/ensure_operations_rv_dates_schema.php";
ensure_operations_rv_dates_schema($conn);

$segment_filter = "ABCRV";
$selectedEntryDate = getSelectedEntryDate();
$query = "SELECT entry_id, entry_type, segment_empty, activity_empty, segment, activity, remarks, created_date, pullout_location_arrival_date, pullout_location_arrival_time, pullout_location_departure_date, pullout_location_departure_time, ph_arrival_date, ph_arrival_time, van_alpha, van_number, van_name, ph, shipper, ecs, tr, gs, waybill, waybill_empty, waybill_date, empty_trip_receipt_date, prime_mover, driver, empty_pullout_location, loaded_van_loading_start_date, loaded_van_loading_start_time, loaded_van_loading_finish_date, loaded_van_loading_finish_time, loaded_van_delivery_departure_date, loaded_van_delivery_departure_time, loaded_van_delivery_arrival_date, delivery_location_arrival_date, delivery_location_arrival_time, loaded_van_delivery_arrival_time, genset_shutoff_date, genset_shutoff_time, end_uploading_date, end_uploading_time, end_unloading_start_date, end_unloading_start_time, end_unloading_finish_date, end_unloading_finish_time, dr_no, load_description, delivered_by_prime_mover, delivered_by_driver, delivered_to, delivered_remarks, genset_hr_meter_start, genset_hr_meter_end, genset_start_date, genset_start_time, genset_end_date, genset_end_time FROM operations WHERE entry_type = 'RV ENTRY' AND segment = ? AND created_date::date = ? ORDER BY entry_id DESC";
$stmt = $conn->prepare($query);
$stmt->execute([$segment_filter, $selectedEntryDate]);
$result = $stmt;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ABC RV Data Entry - DataEncode System</title>
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
                            <h2 class="fw-bold">ABC RV Data Entry</h2>
                            <p class="text-muted">Create, view, and manage ABC RV segment entries efficiently.</p>
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
                        <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>New ABC RV Entry</h5>
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
                                            <input type="text" class="form-control" id="segment_empty" name="segment_empty" value="ABCRV" required>
                                            <ul id="segmentEmptyList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6" hidden>
                                        <div class="mb-3 position-relative">
                                            <label for="activity_empty" class="form-label">Activity (Empty) <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="activity_empty" name="activity_empty" required autocomplete="off" placeholder="Auto-filled from shipper">
                                            <ul id="activityEmptyList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
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
                                    <div class="col-md-12" id="skuPreviewRow" style="display:none;" hidden>
                                        <div class="alert alert-info py-1 px-3 mb-0 d-flex align-items-center gap-3" id="skuPreviewBox">
                                            <span><strong>Billing SKU:</strong> <span id="skuPreviewName">—</span></span>
                                            <span><strong>KMS:</strong> <span id="skuPreviewKms">—</span></span>
                                        </div>
                                    </div>
                                    <div class="col-md-12" id="skuNotFoundRow" style="display:none;" hidden>
                                        <div class="alert alert-warning py-1 px-3 mb-0">
                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                            No SKU found for this Shipper + PH combination. Entry cannot be saved.
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
                                            <input type="text" class="form-control" id="empty_pullout_location" name="empty_pullout_location" required autocomplete="off" placeholder="Search location">
                                            <ul id="emptyPulloutLocationList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1010; display: none; max-height: 240px; overflow-y: auto;"></ul>
                                        </div>

                                    </div>
                                    <h5>LOADED CONTAINER VAN</h5>
                                    <div class="col-md-6" hidden>
                                        <div class="mb-3 position-relative">
                                            <label for="segment" class="form-label">Segment</label>
                                            <input type="text" class="form-control" id="segment" name="segment" value="ABCRV" required>
                                            <ul id="segmentList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6" hidden>
                                        <div class="mb-3 position-relative">
                                            <label for="activity" class="form-label">Activity (Loaded) <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="activity" name="activity" required autocomplete="off" placeholder="Auto-filled from shipper">
                                            <ul id="activityList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
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
                                    <div class="col-md-2" hidden>
                                        <label for="end_unloading_finish_date" class="form-label">END OF UNLOADING FINISH DATE</label>
                                        <input type="text" class="form-control" id="end_unloading_finish_date" name="end_unloading_finish_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                    </div>
                                    <div class="col-md-2" hidden>
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
                                        <label for="hr_meter_start" class="form-label">HR METER START <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="hr_meter_start" name="hr_meter_start" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="hr_meter_end" class="form-label">HR METER END <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="hr_meter_end" name="hr_meter_end" required>
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
                                <h5 class="mb-0">ABC RV Entries</h5>
                            </div>
                            <div class="col-md-6" hidden>
                                <div class="d-flex gap-2 justify-content-md-end">

                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-filter"></i> Filter
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" data-filter="all">All</a></li>
                                            <li><a class="dropdown-item" href="#" data-filter="completed">Completed</a></li>
                                            <li><a class="dropdown-item" href="#" data-filter="pending">Pending</a></li>
                                            <li><a class="dropdown-item" href="#" data-filter="in-progress">In Progress</a></li>
                                        </ul>
                                    </div>
                                    <button class="btn btn-success">
                                        <i class="bi bi-download"></i> Export
                                    </button>
                                </div>
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
                                    <tr data-id="<?php echo $row['entry_id']; ?>" data-segment="<?php echo htmlspecialchars($row['segment']); ?>" data-activity="<?php echo htmlspecialchars($row['activity']); ?>" data-waybill="<?php echo htmlspecialchars($row['waybill']); ?>" data-driver="<?php echo htmlspecialchars($row['driver']); ?>" data-remarks="<?php echo htmlspecialchars($row['remarks']); ?>">
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
         $(document).ready(function () {
            $("#enav").attr({
					"class" : "nav-link active"
				});
         });
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // ===== Segment & Activity Search =====
            const segmentEmptyInput = document.getElementById("segment_empty");
            const segmentEmptyList = document.getElementById("segmentEmptyList");
            const activityEmptyInput = document.getElementById("activity_empty");
            const activityEmptyList = document.getElementById("activityEmptyList");
            const segmentInput = document.getElementById("segment");
            const segmentList = document.getElementById("segmentList");
            const activityInput = document.getElementById("activity");
            const activityList = document.getElementById("activityList");
            let allSegmentActivity = [];
            let selectedSegment = "ABCRV";
            let selectedEmptySegment = "ABCRV";

            fetch("php/fetch/get_segment_activity.php")
                .then(res => res.json())
                .then(data => {
                    allSegmentActivity = data;
                });

            if (activityEmptyInput && activityEmptyList) {
                activityEmptyInput.addEventListener("input", function() {
                    if (!selectedEmptySegment) {
                        Swal.fire({ title: "Segment required", text: "Please select an empty segment first.", icon: "warning" });
                        this.value = "";
                        return;
                    }

                    const searchVal = this.value.toLowerCase();
                    const filteredActivities = [...new Set(allSegmentActivity
                        .filter(item => item.segment === selectedEmptySegment && item.activity.toLowerCase().includes(searchVal))
                        .map(item => item.activity))];

                    filterDropdown(this, activityEmptyList, filteredActivities, (name) => {
                        activityEmptyInput.value = name;
                    });
                });
            }

            if (activityInput && activityList) {
                activityInput.addEventListener("input", function() {
                    if (!selectedSegment) {
                        Swal.fire({ title: "Segment required", text: "Please select a segment first.", icon: "warning" });
                        this.value = "";
                        return;
                    }

                    const searchVal = this.value.toLowerCase();
                    const filteredActivities = [...new Set(allSegmentActivity
                        .filter(item => item.segment === selectedSegment && item.activity.toLowerCase().includes(searchVal))
                        .map(item => item.activity))];

                    filterDropdown(this, activityList, filteredActivities, (name) => {
                        activityInput.value = name;
                    });
                });
            }

            // ===== Driver Search =====
            const driverInput = document.getElementById("driver");
            const driverList = document.getElementById("driverList");
            const driver2Input = document.getElementById("driver2");
            const driver2List = document.getElementById("driver2List");
            const driverIdInput = document.getElementById("driver_idNumber");
            let allDrivers = [];

            fetch("php/fetch/get_drivers.php")
                .then(res => res.json())
                .then(data => {
                    allDrivers = Array.isArray(data) ? data : [];
                })
                .catch(() => { allDrivers = []; });

            if (driverInput && driverList) {
                driverInput.addEventListener("input", function() {
                    driverIdInput.value = "";
                    filterDropdown(this, driverList, allDrivers.map(d => d.name), (name) => {
                        driverInput.value = name;
                        const selected = allDrivers.find(d => d.name === name);
                        driverIdInput.value = selected ? selected.id : "";
                    });
                });
            }

            const driverId2Input = document.getElementById("driver_idNumber2");
            if (driver2Input && driver2List) {
                driver2Input.addEventListener("input", function() {
                    driverId2Input.value = "";
                    filterDropdown(this, driver2List, allDrivers.map(d => d.name), (name) => {
                        driver2Input.value = name;
                        const selected = allDrivers.find(d => d.name === name);
                        driverId2Input.value = selected ? selected.id : "";
                    });
                });
            }

            // ===== Location / PH (Packing House) search =====
            const phInput = document.getElementById("ph");
            const phList = document.getElementById("phList");
            const deliveredToInput = document.getElementById("delivered_to");
            const deliveredToList = document.getElementById("deliveredToList");
            const emptyPulloutLocationInput = document.getElementById("empty_pullout_location");
            const emptyPulloutLocationList = document.getElementById("emptyPulloutLocationList");
            let allLocations = [];

            fetch("php/fetch/get_locations.php")
                .then(res => res.json())
                .then(data => {
                    allLocations = Array.isArray(data) ? data : [];
                })
                .catch(() => { allLocations = []; });

            if (phInput && phList) {
                phInput.addEventListener("input", function() {
                    filterDropdownRecords(
                        this,
                        phList,
                        allLocations,
                        (loc) => loc.location_name || "",
                        (loc) => loc.location_name || "",
                        (name) => { phInput.value = name; fetchSkuPreview(); }
                    );
                });
            }

            if (deliveredToInput && deliveredToList) {
                deliveredToInput.addEventListener("input", function() {
                    filterDropdownRecords(
                        this,
                        deliveredToList,
                        allLocations,
                        (loc) => loc.location_name || "",
                        (loc) => loc.location_name || "",
                        (name) => { deliveredToInput.value = name; if (window.fetchSkuPreview) window.fetchSkuPreview(); }
                    );
                });
                deliveredToInput.addEventListener("change", function() { if (window.fetchSkuPreview) window.fetchSkuPreview(); });
            }

            if (emptyPulloutLocationInput && emptyPulloutLocationList) {
                emptyPulloutLocationInput.addEventListener("input", function() {
                    filterDropdownRecords(
                        this,
                        emptyPulloutLocationList,
                        allLocations,
                        (loc) => loc.location_name || "",
                        (loc) => loc.location_name || "",
                        (name) => { emptyPulloutLocationInput.value = name; if (window.fetchSkuPreview) window.fetchSkuPreview(); }
                    );
                });
                emptyPulloutLocationInput.addEventListener("change", function() { if (window.fetchSkuPreview) window.fetchSkuPreview(); });
            }

            // ===== Truck Search (prime mover) =====
            const truckInput = document.getElementById("prime_mover");
            const truckList = document.getElementById("truckList");
            const pm2Input = document.getElementById("pm2");
            const pm2List = document.getElementById("pm2List");
            let allTrucks = [];

            fetch("php/fetch/get_trucks.php")
                .then(res => res.json())
                .then(data => {
                    allTrucks = data;
                });

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

            // ===== Genset (GS) search — units with unit_name GS* =====
            const gensetInput = document.getElementById("gs");
            const gensetList = document.getElementById("gensetList");
            let allGensets = [];

            fetch("php/fetch/get_gensets.php")
                .then(res => res.json())
                .then(data => {
                    allGensets = Array.isArray(data) ? data : [];
                });

            if (gensetInput && gensetList) {
                gensetInput.addEventListener("input", function() {
                    filterDropdownRecords(
                        this,
                        gensetList,
                        allGensets,
                        (u) => u.unit_name || "",
                        (u) => {
                            const bits = [u.unit_name];
                            if (u.unit_model) bits.push(u.unit_model);
                            if (u.unit_cluster) bits.push(u.unit_cluster);
                            return bits.join(" — ");
                        },
                        (name) => { gensetInput.value = name; autofillLastHrMeter(name); }
                    );
                });
            }

            // Auto-fill HR METER START with the selected genset's last recorded reading.
            function autofillLastHrMeter(gensetName) {
                const startEl = document.getElementById("hr_meter_start");
                if (!startEl || !gensetName) return;
                fetch("php/fetch/get_last_hr_meter.php?gs=" + encodeURIComponent(gensetName), { cache: "no-store" })
                    .then(r => r.json())
                    .then(d => {
                        if (d && d.success && d.last_hr_meter != null && d.last_hr_meter !== "") {
                            startEl.value = d.last_hr_meter;
                        }
                    })
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

            // ===== Trailer (TR) search =====
            const trailerInput = document.getElementById("tr");
            const trailerList = document.getElementById("trailerList");
            let allTrailers = [];
            
            // ===== Shipper search =====
            const shipperInput = document.getElementById("shipper");
            const shipperList = document.getElementById("shipperList");
            let allShippers = [];
            
            const allSearchLists = [segmentEmptyList, activityEmptyList, segmentList, activityList, phList, deliveredToList, driverList, driver2List, truckList, pm2List, gensetList, trailerList, shipperList].filter(Boolean);

            fetch("php/fetch/get_trailers.php")
                .then(res => res.json())
                .then(data => {
                    allTrailers = Array.isArray(data) ? data : [];
                });

            if (trailerInput && trailerList) {
                trailerInput.addEventListener("input", function() {
                    filterDropdownRecords(
                        this,
                        trailerList,
                        allTrailers,
                        (t) => t.trailer_name || "",
                        (t) => t.trailer_name,
                        (name) => { trailerInput.value = name; }
                    );
                });
            }

            // ===== Shipper search =====
            fetch("php/fetch/get_shippers.php")
                .then(res => res.json())
                .then(data => {
                    allShippers = Array.isArray(data) ? data : [];
                })
                .catch(() => { allShippers = []; });

            if (shipperInput && shipperList) {
                shipperInput.addEventListener("input", function() {
                    filterDropdownRecords(
                        this,
                        shipperList,
                        allShippers,
                        (s) => s.shipper || "",
                        (s) => s.shipper || "",
                        (name) => {
                            shipperInput.value = name.trim();
                            updateActivitiesForShipper(name.trim());
                            fetchSkuPreview();
                        }
                    );
                });

                // Handle click on dropdown items
                shipperList.addEventListener("click", function(e) {
                    const item = e.target.closest(".list-group-item");
                    if (item) {
                        const shipperName = item.textContent.trim();
                        shipperInput.value = shipperName;
                        updateActivitiesForShipper(shipperName);
                        fetchSkuPreview();
                        hideDropdown(shipperList);
                    }
                });
            }

            // ===== Live SKU Preview =====
            window.fetchSkuPreview = function fetchSkuPreview() {
                const shipperVal   = (shipperInput ? shipperInput.value.trim() : '');
                const phVal        = (phInput      ? phInput.value.trim()      : '');
                // Actual trip locations pick the right origin/dest SKU variant
                // (e.g. DICT→DICT = PNB-PNB). Sent when available; the endpoint
                // falls back to the shipper+farm lookup when they're blank.
                const pulloutVal   = (emptyPulloutLocationInput ? emptyPulloutLocationInput.value.trim() : '');
                const deliveredVal = (deliveredToInput ? deliveredToInput.value.trim() : '');
                const previewRow   = document.getElementById('skuPreviewRow');
                const notFoundRow  = document.getElementById('skuNotFoundRow');
                const skuName      = document.getElementById('skuPreviewName');
                const skuKms       = document.getElementById('skuPreviewKms');

                if (!shipperVal || !phVal) {
                    if (previewRow)  previewRow.style.display  = 'none';
                    if (notFoundRow) notFoundRow.style.display = 'none';
                    return;
                }

                fetch('php/fetch/get_sku_preview.php?shipper=' + encodeURIComponent(shipperVal) + '&ph=' + encodeURIComponent(phVal)
                        + '&pullout=' + encodeURIComponent(pulloutVal) + '&delivered=' + encodeURIComponent(deliveredVal))
                    .then(r => r.json())
                    .then(data => {
                        if (data.found) {
                            if (skuName)     skuName.textContent = data.sku || '—';
                            if (skuKms)      skuKms.textContent  = data.kms || '—';
                            if (previewRow)  previewRow.style.display  = '';
                            if (notFoundRow) notFoundRow.style.display = 'none';
                        } else {
                            if (previewRow)  previewRow.style.display  = 'none';
                            if (notFoundRow) notFoundRow.style.display = '';
                        }
                    })
                    .catch(() => {
                        if (previewRow)  previewRow.style.display  = 'none';
                        if (notFoundRow) notFoundRow.style.display = 'none';
                    });
            }

            if (shipperInput) {
                shipperInput.addEventListener('change', fetchSkuPreview);
            }
            if (phInput) {
                phInput.addEventListener('change', fetchSkuPreview);
            }

            // Shipper to Activities Mapping
            function updateActivitiesForShipper(shipper) {
                const shipperMapping = [
                    { key: "abc cateel",   empty: "ABCCat.RV.Empty",    loaded: "ABCCat.RV.Loaded" },
                    { key: "abc donmar",   empty: "ABCDonMar.RV.Empty", loaded: "ABCDonMar.RV.Loaded" },
                    { key: "abc lupon",    empty: "ABCLup.RV.Empty",    loaded: "ABCLup.RV.Loaded" },
                    { key: "abc pantukan", empty: "ABCPan.RV.Empty",    loaded: "ABCPan.RV.Loaded" },
                ];

                const trimmedShipper = shipper ? shipper.trim().toLowerCase() : "";
                if (!trimmedShipper) return;

                const match = shipperMapping.find(m => m.key === trimmedShipper);
                const activityEmptyElem = document.getElementById("activity_empty");
                const activityElem = document.getElementById("activity");

                if (match) {
                    if (activityEmptyElem) activityEmptyElem.value = match.empty;
                    if (activityElem) activityElem.value = match.loaded;
                }
            }

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

            // ===== Shared: string list =====
            function filterDropdown(inputElem, listElem, dataArr, onSelect) {
                const searchVal = inputElem.value.toLowerCase();

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
                    const li = document.createElement("li");
                    li.className = "list-group-item list-group-item-action";
                    const label = String(item);
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

            // ===== Records (location, trailer row, genset row) =====
            function filterDropdownRecords(inputElem, listElem, records, getValue, formatLine, onPick) {
                const searchVal = inputElem.value.toLowerCase();

                if (!searchVal) {
                    hideDropdown(listElem);
                    return;
                }

                listElem.innerHTML = "";

                const filtered = records.filter((r) => getValue(r).toLowerCase().includes(searchVal));

                if (filtered.length === 0) {
                    hideDropdown(listElem);
                    return;
                }

                filtered.forEach((r, index) => {
                    const li = document.createElement("li");
                    li.className = "list-group-item list-group-item-action";
                    const val = getValue(r);
                    li.textContent = formatLine(r);
                    li.dataset.pickValue = val;

                    if (index === 0) {
                        li.classList.add("active-suggestion");
                    }

                    li.addEventListener("mousedown", function(event) {
                        event.preventDefault();
                        onPick(val);
                        hideDropdown(listElem);
                    });
                    listElem.appendChild(li);
                });

                showDropdown(listElem);
            }

             // ===== Autofill + Navigation Support =====
        function attachKeyboardNav(inputElem, listElem, onSelect) {
          if (!inputElem || !listElem) return;

          let activeIndex = 0;

          inputElem.addEventListener("input", function() {
            activeIndex = 0;
          });

          inputElem.addEventListener("keydown", function(e) {
            const items = listElem.querySelectorAll("li");
            if (e.key === "Escape") {
              hideDropdown(listElem);
              return;
            }
            if (!items.length) return;

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
                const v = activeItem.dataset.pickValue !== undefined ? activeItem.dataset.pickValue : activeItem.textContent;
                onSelect(v);
                hideDropdown(listElem);
              }
            } else if (e.key === "Tab") {
              const activeItem = items[activeIndex];
              if (activeItem) {
                const v = activeItem.dataset.pickValue !== undefined ? activeItem.dataset.pickValue : activeItem.textContent;
                onSelect(v);
                hideDropdown(listElem);
              } else {
                hideDropdown(listElem);
              }
            }
          });

          function updateActive(items, index) {
            items.forEach(i => i.classList.remove("active-suggestion"));
            if (items[index]) {
              items[index].classList.add("active-suggestion");
              items[index].scrollIntoView({ block: 'nearest' });
            }
          }
        }

            document.addEventListener("mousedown", function(event) {
                const clickedInsideSearch = event.target.closest(".position-relative");
                if (clickedInsideSearch) return;
                allSearchLists.forEach(hideDropdown);
            });

            // Attach keyboard nav to each input/list
            if (activityEmptyInput && activityEmptyList) {
                attachKeyboardNav(activityEmptyInput, activityEmptyList, (val) => {
                    activityEmptyInput.value = val;
                });
            }

            if (activityInput && activityList) {
                attachKeyboardNav(activityInput, activityList, (val) => {
                    activityInput.value = val;
                });
            }

            if (phInput && phList) {
                attachKeyboardNav(phInput, phList, (val) => {
                    phInput.value = val;
                    fetchSkuPreview();
                });
            }

            if (deliveredToInput && deliveredToList) {
                attachKeyboardNav(deliveredToInput, deliveredToList, (val) => {
                    deliveredToInput.value = val;
                });
            }

            if (emptyPulloutLocationInput && emptyPulloutLocationList) {
                attachKeyboardNav(emptyPulloutLocationInput, emptyPulloutLocationList, (val) => {
                    emptyPulloutLocationInput.value = val;
                });
            }

            if (driverInput && driverList) {
                attachKeyboardNav(driverInput, driverList, (val) => {
                    driverInput.value = val;
                    const selected = allDrivers.find(d => d.name === val);
                    driverIdInput.value = selected ? selected.id : "";
                });
            }

            if (driver2Input && driver2List) {
                attachKeyboardNav(driver2Input, driver2List, (val) => {
                    driver2Input.value = val;
                    const selected = allDrivers.find(d => d.name === val);
                    driverId2Input.value = selected ? selected.id : "";
                });
            }

            if (trailerInput && trailerList) {
                attachKeyboardNav(trailerInput, trailerList, (val) => {
                    trailerInput.value = val;
                });
            }

            if (truckInput && truckList) {
                attachKeyboardNav(truckInput, truckList, (val) => {
                    truckInput.value = val;
                });
            }

            if (pm2Input && pm2List) {
                attachKeyboardNav(pm2Input, pm2List, (val) => {
                    pm2Input.value = val;
                });
            }

            if (gensetInput && gensetList) {
                attachKeyboardNav(gensetInput, gensetList, (val) => {
                    gensetInput.value = val;
                    autofillLastHrMeter(val);
                });
            }

            if (shipperInput && shipperList) {
                attachKeyboardNav(shipperInput, shipperList, (val) => {
                    shipperInput.value = val;
                    updateActivitiesForShipper(val);
                    fetchSkuPreview();
                });
            }
        });

        $(document).ready(function() {
            let rvTable;

            rvTable = $('#entriesTable').DataTable({
                "paging": true,
                "lengthChange": true,
                "searching": true,
                "ordering": true,
                "info": true,
                "autoWidth": false,
                "pageLength": 10,
                "pagingType": "full_numbers",
                "language": {
                    "paginate": {
                        "first": "First",
                        "last": "Last",
                        "next": "Next",
                        "previous": "Previous"
                    }
                }
            });

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
                // If time missing or invalid, default to 00:00
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
                    van_alpha: document.getElementById("van_alpha").value,
                    van_number: document.getElementById("van_number").value,
                    van_name: document.getElementById("van_name").value,
                    delivery_location_arrival_date: document.getElementById("delivery_location_arrival_date").value,
                    delivery_location_arrival_time: document.getElementById("delivery_location_arrival_time").value,
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
                    end_uploading_date: document.getElementById("end_unloading_start_date").value,
                    end_uploading_time: document.getElementById("end_unloading_start_time").value,
                    end_uploading_finish_date: document.getElementById("end_unloading_finish_date").value,
                    end_uploading_finish_time: document.getElementById("end_unloading_finish_time").value,
                    dr_no: document.getElementById("dr_no").value,
                    load_description: document.getElementById("load").value,
                    delivered_by_prime_mover: document.getElementById("pm2").value,
                    delivered_by_driver: document.getElementById("driver2").value,
                    delivered_by_driverIdNumber: document.getElementById("driver_idNumber2").value,
                    delivered_to: document.getElementById("delivered_to").value,
                    genset_hr_meter_start: document.getElementById("hr_meter_start").value,
                    genset_hr_meter_end: document.getElementById("hr_meter_end").value,
                    new_meter_reading: document.getElementById("new_meter_reading").checked ? '1' : '0',
                    defect_hubo: document.getElementById("defect_hubo").checked ? '1' : '0',
                    genset_start_date: document.getElementById("gs_start_date").value,
                    genset_start_time: document.getElementById("gs_start_time").value,
                    genset_end_date: document.getElementById("gs_end_date").value,
                    genset_end_time: document.getElementById("gs_end_time").value,
                    reference_documents: document.getElementById("reference_documents").value,
                    genset_hr_meter: document.getElementById("genset_hr_meter").value,
                    genset_hr_reading: document.getElementById("genset_hr_reading").value,
                    refueled: document.getElementById("refueled").value
                };

                // Apply date/time conversions
                const dateFields = ['pullout_location_arrival_date', 'pullout_location_departure_date', 'ph_arrival_date',
                    'loaded_van_loading_start_date', 'loaded_van_loading_finish_date', 'loaded_van_delivery_departure_date', 'delivery_location_arrival_date',
                    'loaded_van_delivery_arrival_date', 'end_uploading_date', 'end_uploading_finish_date', 'genset_start_date', 'genset_end_date', 'gs_start_date', 'gs_end_date',
                    'waybill_date', 'empty_trip_receipt_date'];
                const timeFields = ['pullout_location_arrival_time', 'pullout_location_departure_time', 'ph_arrival_time',
                    'loaded_van_loading_start_time', 'loaded_van_loading_finish_time', 'loaded_van_delivery_departure_time', 'delivery_location_arrival_time',
                    'loaded_van_delivery_arrival_time', 'end_uploading_time', 'end_uploading_finish_time', 'genset_start_time', 'genset_end_time', 'gs_start_time', 'gs_end_time'];
                
                dateFields.forEach(field => { if (values[field]) values[field] = convertDateToDatabase(values[field]); });
                timeFields.forEach(field => { if (values[field]) values[field] = convertTimeToDatabase(values[field]); });

                return values;
            }

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
                row.dataset.id = record.entry_id;
                row.dataset.segment = record.segment;
                row.dataset.activity = record.activity;
                row.dataset.waybill = record.waybill;
                row.dataset.driver = record.driver;
                row.dataset.remarks = record.remarks;
            }

            function applyDefectHuboState() {
                const defective = document.getElementById('defect_hubo')?.checked;
                ['hr_meter_start', 'hr_meter_end'].forEach((id) => {
                    const input = document.getElementById(id);
                    if (!input) return;
                    input.required = !defective;
                    input.disabled = defective;
                    if (defective) input.value = '';
                });
            }
            // Wire the toggle here: an inline onchange runs in GLOBAL scope and can't
            // see this DOMContentLoaded-local function, so toggling never cleared the
            // hr-meter `required` and the form silently refused to submit. Set the
            // initial state too.
            document.getElementById('defect_hubo')?.addEventListener('change', applyDefectHuboState);
            applyDefectHuboState();

            function clearForm() {
                document.getElementById("dataEntryForm").reset();
                document.getElementById("segment_empty").value = "ABCRV";
                document.getElementById("segment").value = "ABCRV";
                document.getElementById("data_id").value = "";
                const pr = document.getElementById('skuPreviewRow');
                const nr = document.getElementById('skuNotFoundRow');
                if (pr) pr.style.display = 'none';
                if (nr) nr.style.display = 'none';
            }

            const form = document.getElementById("dataEntryForm");
            form.querySelectorAll('input[type="text"]').forEach(function (input) {
                input.addEventListener('input', function () {
                    const pos = this.selectionStart;
                    this.value = this.value.toUpperCase();
                    this.setSelectionRange(pos, pos);
                });
            });
            if (form) {
                form.addEventListener("reset", function() {
                    document.getElementById("segment_empty").value = "ABCRV";
                    document.getElementById("segment").value = "ABCRV";
                });
            }

            // Helper function to convert YYYY-MM-DD to MM/DD/YYYY
            function formatDateForDisplay(dbDate) {
                if (!dbDate || dbDate === '') return '';
                const match = dbDate.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                if (!match) return dbDate; // Return as-is if not in expected format
                return `${match[2]}/${match[3]}/${match[1]}`; // MM/DD/YYYY
            }

            // Helper function to convert HH:MM:SS or HH:MM to HH:MM format
            function formatTimeForDisplay(dbTime) {
                if (!dbTime || dbTime === '') return '';
                // Extract HH:MM from HH:MM:SS or HH:MM format
                const match = dbTime.match(/^(\d{2}):(\d{2})/);
                if (!match) return dbTime;
                return match[1] + ':' + match[2]; // HH:MM format
            }

            function fillFormFromRecord(record) {
                // Set the ID field
                document.getElementById("data_id").value = record.entry_id || '';
                
                // Fields that need date formatting (YYYY-MM-DD to MM/DD/YYYY)
                const dateFields = [
                    'pullout_location_arrival_date',
                    'pullout_location_departure_date',
                    'ph_arrival_date',
                    'loading_start_date',
                    'loading_finish_date',
                    'delivery_departure_date',
                    'delivery_arrival_date',
                    'end_unloading_start_date',
                    'end_unloading_finish_date',
                    'genset_start_date',
                    'genset_end_date',
                    'gs_start_date',
                    'gs_end_date',
                    'delivery_location_arrival_date',
                    'waybill_date',
                    'empty_trip_receipt_date'
                ];

                // Fields that need time formatting (HH:MM to HHMM)
                const timeFields = [
                    'pullout_location_arrival_time',
                    'pullout_location_departure_time',
                    'ph_arrival_time',
                    'loading_start_time',
                    'loading_finish_time',
                    'delivery_departure_time',
                    'delivery_arrival_time',
                    'end_unloading_start_time',
                    'end_unloading_finish_time',
                    'genset_start_time',
                    'genset_end_time',
                    'gs_start_time',
                    'gs_end_time',
                    'delivery_location_arrival_time'
                ];

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
                    van_alpha: 'van_alpha',
                    van_number: 'van_number',
                    van_name: 'van_name',
                    delivery_location_arrival_date: 'delivery_location_arrival_date',
                    delivery_location_arrival_time: 'delivery_location_arrival_time',
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
                    end_unloading_finish_date: 'end_unloading_finish_date',
                    end_unloading_finish_time: 'end_unloading_finish_time',
                    dr_no: 'dr_no',
                    load_description: 'load',
                    delivered_by_prime_mover: 'pm2',
                    delivered_by_driver: 'driver2',
                    delivered_to: 'delivered_to',
                    genset_hr_meter_start: 'hr_meter_start',
                    genset_hr_meter_end: 'hr_meter_end',
                    genset_start_date: 'gs_start_date',
                    genset_start_time: 'gs_start_time',
                    genset_end_date: 'gs_end_date',
                    genset_end_time: 'gs_end_time',
                    reference_documents: 'reference_documents',
                    genset_hr_meter: 'genset_hr_meter',
                    genset_hr_reading: 'genset_hr_reading',
                    refueled: 'refueled',
                    delivered_by_driverIdNumber: 'driver_idNumber2'
                };

            Object.entries(fieldMap).forEach(([recordKey, inputId]) => {
                    const input = document.getElementById(inputId);
                    if (input) {
                        let value = record[recordKey] ?? '';
                        if ((value === '' || value == null) && recordKey === 'end_uploading_date') {
                            value = record.end_unloading_start_date ?? '';
                        }
                        if ((value === '' || value == null) && recordKey === 'end_uploading_time') {
                            value = record.end_unloading_start_time ?? '';
                        }
                        
                        // Format date fields
                        if (dateFields.includes(inputId) && value) {
                            value = formatDateForDisplay(value);
                        }
                        
                        // Format time fields
                        if (timeFields.includes(inputId) && value) {
                            value = formatTimeForDisplay(value);
                        }
                        
                        input.value = value;
                }
            });
            const defectHubo = document.getElementById('defect_hubo');
            if (defectHubo) {
                defectHubo.checked = record.defect_hubo === true || record.defect_hubo === 1 || record.defect_hubo === '1';
                applyDefectHuboState();
            }
        }

            function loadRecordIntoForm(row) {
                const id = row?.dataset?.id;
                if (!id) return;

                fetch(`php/fetch/get_rv.php?id=${encodeURIComponent(id)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (!data.success) {
                            Swal.fire('Error', data.message || 'Unable to load record.', 'error');
                            return;
                        }
                        fillFormFromRecord(data.record);
                        if (window.fetchSkuPreview) window.fetchSkuPreview();
                        const collapseEl = document.querySelector('#entryForm');
                        if (collapseEl) {
                            collapseEl.classList.add('show');
                        }
                    })
                    .catch(() => {
                        Swal.fire('Error', 'Unable to fetch record.', 'error');
                    });
            }

            // Auto-open a record when arriving from the For Update queue (?load_id=).
            (function () {
                const loadId = new URLSearchParams(window.location.search).get('load_id');
                if (!loadId) return;
                loadRecordIntoForm({ dataset: { id: loadId } });
                const form = document.querySelector('#entryForm');
                if (form) setTimeout(() => form.scrollIntoView({ behavior: 'smooth', block: 'start' }), 350);
            })();

            let rowActionsAttached = false;

            function refreshTable() {
                if (rvTable) {
                    rvTable.draw(false);
                }
            }

            function attachRowActions() {
                if (rowActionsAttached) return;
                const rowActionsRoot = document.querySelector('#entriesTable')?.closest('.table-responsive') || document.body;
                if (!rowActionsRoot) return;

                rowActionsRoot.addEventListener('click', async (e) => {
                    const btn = e.target.closest('.btn-edit, .btn-delete');
                    if (!btn) return;
                    const row = btn.closest('tr');
                    if (!row) return;

                    if (btn.classList.contains('btn-edit')) {
                        loadRecordIntoForm(row);
                        return;
                    }

                    if (btn.classList.contains('btn-delete')) {
                        const id = row.dataset.id;
                        const confirmed = await Swal.fire({
                            title: 'Confirm delete',
                            text: 'Delete row #' + id + ' ? This cannot be undone.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, delete',
                            cancelButtonText: 'Cancel'
                        });

                        if (!confirmed.isConfirmed) return;

                        $.ajax({
                            url: 'php/delete/rv.php',
                            type: 'POST',
                            data: {
                                action: 'delete-rv',
                                data_id: id
                            },
                            dataType: 'json',
                            success: function(result) {
                                if (result.success) {
                                    rvTable.row(row).remove().draw(false);
                                    Swal.fire('Deleted!', 'Entry deleted.', 'success');
                                } else {
                                    Swal.fire('Error', result.message || 'Delete failed', 'error');
                                }
                            },
                            error: function() {
                                Swal.fire('Error', 'Unable to reach server', 'error');
                            }
                        });
                    }
                });

                rowActionsAttached = true;
            }

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const values = getValues();

                if (!values.segment_empty) {
                    Swal.fire('Missing field', 'Empty segment is required.', 'warning');
                    return;
                }
                if (!values.activity_empty) {
                    Swal.fire('Missing field', 'Empty activity is required.', 'warning');
                    return;
                }
                if (!values.segment) {
                    Swal.fire('Missing field', 'Loaded segment is required.', 'warning');
                    return;
                }
                if (!values.activity) {
                    Swal.fire('Missing field', 'Loaded activity is required.', 'warning');
                    return;
                }
                if (!values.waybill) {
                    Swal.fire('Missing field', 'Waybill is required.', 'warning');
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
                if (!values.ph) {
                    Swal.fire('Missing field', 'PH (Packing House / Location) is required. Use the search list from the location master.', 'warning');
                    return;
                }
                if (!values.tr) {
                    Swal.fire('Missing field', 'Trailer (TR) is required. Pick from the trailer list.', 'warning');
                    return;
                }
                if (!values.gs) {
                    Swal.fire('Missing field', 'Genset (GS) is required. Pick a unit with name starting with GS.', 'warning');
                    return;
                }
                const hrStart = parseFloat(values.genset_hr_meter_start);
                const hrEnd = parseFloat(values.genset_hr_meter_end);
                if (values.defect_hubo !== '1' && (!Number.isFinite(hrStart) || hrStart <= 0 || !Number.isFinite(hrEnd) || hrEnd <= 0)) {
                    Swal.fire('Invalid HR Meter', 'HR Meter Start and HR Meter End must be greater than 0.', 'warning');
                    return;
                }
                if (values.defect_hubo !== '1' && hrEnd < hrStart && values.new_meter_reading !== '1') {
                    Swal.fire('Invalid HR Meter', 'HR Meter End must not be less than HR Meter Start.', 'warning');
                    return;
                }
                if (!values.prime_mover) {
                    Swal.fire('Missing field', 'Prime mover is required.', 'warning');
                    return;
                }
                if (!values.driver) {
                    Swal.fire('Missing field', 'Driver is required.', 'warning');
                    return;
                }

                // --- Chronological order validations ---
                // Helper to validate a sequence of [dateField, timeField] pairs (values already converted to DB format)
                function validateSequence(pairs, labelSequence) {
                    // Build array of Date objects for pairs that have valid dates
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

                // Sequence A: Pullout Arrival -> Pullout Departure -> PH Arrival
                if (!validateSequence([
                    ['pullout_location_arrival_date', 'pullout_location_arrival_time'],
                    ['pullout_location_departure_date', 'pullout_location_departure_time'],
                    ['ph_arrival_date', 'ph_arrival_time']
                ], [
                    'Pullout Location - Arrival',
                    'Pullout Location - Departure',
                    'PH Arrival'
                ])) return;

                // Sequence B: Loading Start -> Loading Finish -> Delivery Departure -> Delivery Arrival -> End Unloading Start
                if (!validateSequence([
                    ['loaded_van_loading_start_date', 'loaded_van_loading_start_time'],
                    ['loaded_van_loading_finish_date', 'loaded_van_loading_finish_time'],
                    ['loaded_van_delivery_departure_date', 'loaded_van_delivery_departure_time'],
                    ['loaded_van_delivery_arrival_date', 'loaded_van_delivery_arrival_time'],
                    ['end_uploading_date', 'end_uploading_time']
                ], [
                    'Loading Schedule Start',
                    'Loading Schedule Finish',
                    'Delivery Departure',
                    'Delivery Arrival',
                    'End of Unloading Start'
                ])) return;

                const action = values.id ? 'update-rv' : 'add-rv';
                const endpoint = values.id ? 'php/update/rv.php' : 'php/insert/rv.php';

                const payload = {
                    action: action,
                    data_id: values.id || '',
                    ...values
                };
                delete payload.id;

                $.ajax({
                    url: endpoint,
                    type: 'POST',
                    data: payload,
                    dataType: 'json',
                    success: function(data) {
                        if (data.success) {
                            if (action === 'add-rv') {
                                const addedRow = rvTable.row.add(getTableRowData(data.record));
                                addedRow.draw(false);
                                setRowDataAttributes(addedRow.node(), data.record);
                            } else {
                                const row = document.querySelector(`#entriesTable tbody tr[data-id="${values.id}"]`);
                                if (row) {
                                    const rowApi = rvTable.row(row);
                                    if (rowApi.any()) {
                                        rowApi.data(getTableRowData(data.record)).draw(false);
                                        setRowDataAttributes(rowApi.node(), data.record);
                                    }
                                }
                            }
                            refreshTable();
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

            refreshTable();
            attachRowActions();
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
