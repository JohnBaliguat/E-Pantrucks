<?php 
include "php/session-check.php"; 
include "php/config/config.php";
include_once "php/helpers/entry_date_filter.php";

$selectedEntryDate = getSelectedEntryDate();
$stmt = $conn->prepare("SELECT entry_id, entry_type, segment_empty, activity_empty, segment, activity, remarks, created_date, pullout_location_arrival_date, pullout_location_arrival_time, pullout_location_departure_date, pullout_location_departure_time, ph_arrival_date, ph_arrival_time, van_alpha, van_number, van_name, ph, shipper, ecs, tr, gs, waybill, waybill_empty, prime_mover, driver, empty_pullout_location, loaded_van_loading_start_date, loaded_van_loading_start_time, loaded_van_loading_finish_date, loaded_van_loading_finish_time, loaded_van_delivery_departure_date, loaded_van_delivery_departure_time, loaded_van_delivery_arrival_date, loaded_van_delivery_arrival_time, genset_shutoff_date, genset_shutoff_time, end_uploading_date, end_uploading_time, dr_no, load_description, delivered_by_prime_mover, delivered_by_driver, delivered_to, delivered_remarks, genset_hr_meter_start, genset_hr_meter_end, genset_start_date, genset_start_time, genset_end_date, genset_end_time FROM operations WHERE entry_type = 'RV ENTRY' AND created_date::date = ? ORDER BY entry_id DESC");
$stmt->execute([$selectedEntryDate]);
$result = $stmt;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refer Van Data Entry - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/DataTables/datatables.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
    <link rel="stylesheet" href="assets/alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
    
</head>

<body>
    <div class="wrapper">
        <?php include "sidebar.php"; ?>

        <div id="content">

            <?php include "navbar.php"; ?>

            <div class="main-content">
                <div class="row">
                    <div class="col-md-6">
                        <div class="content-header mb-4">
                            <h2 class="fw-bold">Refer Van Data Entry</h2>
                            <p class="text-muted">Create, view, and manage refer van data entries efficiently.</p>
                        </div>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="btn-group" role="group" aria-label="Basic outlined example">
                            <a class="btn btn-outline-secondary" href="rv">Refer Van</a>
                            <a class="btn btn-outline-secondary" href="others">Others</a>
                            <a class="btn btn-outline-secondary" href="bbhm" hidden>BBHM</a>
                            <a class="btn btn-outline-secondary" href="DPC_KDI">DPC_KDI & OPM</a>
                            <a class="btn btn-outline-secondary" href="cargoTruck">Cargo Truck</a>
                        </div>
                    </div>
                </div>


                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>New Refer Van Entry</h5>
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
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="segment_empty" class="form-label">Segment</label>
                                            <input type="text" class="form-control" id="segment_empty" name="segment_empty" required>
                                            <ul id="segmentEmptyList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="activity_empty" class="form-label">Activity</label>
                                            <input type="text" class="form-control" id="activity_empty" name="activity_empty" required>
                                            <ul id="activityEmptyList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="waybill_empty" class="form-label">WAYBILL EMPTY</label>
                                        <input type="text" class="form-control" id="waybill_empty" name="waybill_empty" maxlength="6" inputmode="numeric">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="pullout_location_arrival_date" class="form-label">Pullout Location - Arrival Date</label>
                                        <input type="text" class="form-control" id="pullout_location_arrival_date" name="pullout_location_arrival_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="pullout_location_arrival_time" class="form-label">Pullout Location - Arrival Time</label>
                                        <input type="text" class="form-control" id="pullout_location_arrival_time" name="pullout_location_arrival_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="pullout_location_departure_date" class="form-label">Pullout Location - Departure Date</label>
                                        <input type="text" class="form-control" id="pullout_location_departure_date" name="pullout_location_departure_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="pullout_location_departure_time" class="form-label">Pullout Location - Departure Time</label>
                                        <input type="text" class="form-control" id="pullout_location_departure_time" name="pullout_location_departure_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="ph_arrival_date" class="form-label">PH Arrival Date</label>
                                        <input type="text" class="form-control" id="ph_arrival_date" name="ph_arrival_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="ph_arrival_time" class="form-label">PH Arrival Time</label>
                                        <input type="text" class="form-control" id="ph_arrival_time" name="ph_arrival_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM">
                                    </div>
                                    <div class="col-md-12">
                                        <label for="van_alpha" class="form-label">VAN - ALPHA</label>
                                        <input type="text" class="form-control" id="van_alpha" name="van_alpha" maxlength="4">
                                    </div>
                                    <div class="col-md-12">
                                        <label for="van_number" class="form-label">VAN - NUMBER</label>
                                        <input type="text" class="form-control" id="van_number" name="van_number" maxlength="7" inputmode="numeric">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="withdrawal_date" class="form-label">DATE</label>
                                        <input type="text" class="form-control" id="withdrawal_date" name="withdrawal_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="withdrawal_time" class="form-label">TIME</label>
                                        <input type="text" class="form-control" id="withdrawal_time" name="withdrawal_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="van_name" class="form-label">VAN NAME</label>
                                        <input type="text" class="form-control" id="van_name" name="van_name">
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="ph" class="form-label">PH (Packing House / Location)</label>
                                            <input type="text" class="form-control" id="ph" name="ph" required autocomplete="off" placeholder="Search location or PH…">
                                            <ul id="phList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1050; display: none; max-height: 240px; overflow-y: auto;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="shipper" class="form-label">SHIPPER</label>
                                        <input type="text" class="form-control" id="shipper" name="shipper">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="ecs" class="form-label">ECS</label>
                                        <input type="text" class="form-control" id="ecs" name="ecs">
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="tr" class="form-label">TR</label>
                                            <input type="text" class="form-control" id="tr" name="tr" required autocomplete="off" placeholder="Search trailer…">
                                            <ul id="trailerList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1040; display: none; max-height: 240px; overflow-y: auto;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="gs" class="form-label">GS</label>
                                            <input type="text" class="form-control" id="gs" name="gs" required autocomplete="off" placeholder="Search genset (GS)…">
                                            <ul id="gensetList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1030; display: none; max-height: 240px; overflow-y: auto;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="prime_mover" class="form-label">PRIME MOVER</label>
                                            <input type="text" class="form-control" id="prime_mover" name="prime_mover" required autocomplete="off">
                                            <ul id="truckList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1020; display: none; max-height: 240px; overflow-y: auto;"></ul>

                                        </div>

                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="driver" class="form-label">DRIVER</label>
                                            <input type="text" class="form-control" id="driver" name="driver" required autocomplete="off">
                                            <ul id="driverList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1010; display: none; max-height: 240px; overflow-y: auto;"></ul>
                                            <input type="hidden" id="driver_idNumber" name="driver_idNumber">
                                        </div>

                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="empty_pullout_location" class="form-label">EMPTY PULL-OUT LOCATION</label>
                                            <input type="text" class="form-control" id="empty_pullout_location" name="empty_pullout_location" autocomplete="off">
                                            <ul id="emptyPulloutLocationList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1010; display: none; max-height: 240px; overflow-y: auto;"></ul>
                                        </div>

                                    </div>
                                    <h5>LOADED CONTAINER VAN</h5>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="segment" class="form-label">Segment</label>
                                            <input type="text" class="form-control" id="segment" name="segment" required>
                                            <ul id="segmentList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="activity" class="form-label">Activity</label>
                                            <input type="text" class="form-control" id="activity" name="activity" required>
                                            <ul id="activityList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="waybill" class="form-label">WAYBILL</label>
                                        <input type="text" class="form-control" id="waybill" name="waybill" required maxlength="6" inputmode="numeric">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="loading_start_date" class="form-label">LOADING SCHEDULE START DATE</label>
                                        <input type="text" class="form-control" id="loading_start_date" name="loading_start_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="loading_start_time" class="form-label">LOADING SCHEDULE START TIME</label>
                                        <input type="text" class="form-control" id="loading_start_time" name="loading_start_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="loading_finish_date" class="form-label">LOADING SCHEDULE FINISH DATE</label>
                                        <input type="text" class="form-control" id="loading_finish_date" name="loading_finish_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="loading_finish_time" class="form-label">LOADING SCHEDULE FINISH TIME</label>
                                        <input type="text" class="form-control" id="loading_finish_time" name="loading_finish_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="delivery_departure_date" class="form-label">DELIVERY DEPARTURE DATE</label>
                                        <input type="text" class="form-control" id="delivery_departure_date" name="delivery_departure_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="delivery_departure_time" class="form-label">DELIVERY DEPARTURE TIME</label>
                                        <input type="text" class="form-control" id="delivery_departure_time" name="delivery_departure_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="delivery_arrival_date" class="form-label">DELIVERY ARRIVAL DATE</label>
                                        <input type="text" class="form-control" id="delivery_arrival_date" name="delivery_arrival_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="delivery_arrival_time" class="form-label">DELIVERY ARRIVAL TIME</label>
                                        <input type="text" class="form-control" id="delivery_arrival_time" name="delivery_arrival_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="end_unloading_start_date" class="form-label">END OF UNLOADING DATE</label>
                                        <input type="text" class="form-control" id="end_unloading_start_date" name="end_unloading_start_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="end_unloading_start_time" class="form-label">END OF UNLOADING TIME</label>
                                        <input type="text" class="form-control" id="end_unloading_start_time" name="end_unloading_start_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="end_unloading_finish_date" class="form-label">END OF UNLOADING FINISH DATE</label>
                                        <input type="text" class="form-control" id="end_unloading_finish_date" name="end_unloading_finish_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="end_unloading_finish_time" class="form-label">END OF UNLOADING FINISH TIME</label>
                                        <input type="text" class="form-control" id="end_unloading_finish_time" name="end_unloading_finish_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM">
                                    </div>
                                    <div class="col-md-12">
                                        <label for="reference_documents" class="form-label">REFERENCE DOCUMENTS</label>
                                        <input type="text" class="form-control" id="reference_documents" name="reference_documents">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="dr_no" class="form-label">DR. NO.</label>
                                        <input type="text" class="form-control" id="dr_no" name="dr_no">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="load" class="form-label">LOAD</label>
                                        <input type="text" class="form-control" id="load" name="load">
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="pm2" class="form-label">PM (Prime Mover)</label>
                                            <input type="text" class="form-control" id="pm2" name="pm2" autocomplete="off">
                                            <ul id="pm2List" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1020; display: none; max-height: 240px; overflow-y: auto;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="driver2" class="form-label">DRIVER</label>
                                            <input type="text" class="form-control" id="driver2" name="driver2" autocomplete="off">
                                            <ul id="driver2List" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1010; display: none; max-height: 240px; overflow-y: auto;"></ul>
                                            <input type="hidden" id="driver_idNumber2" name="driver_idNumber2">
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="mb-3 position-relative">
                                            <label for="delivered_to" class="form-label">DELIVERED TO: STATE THE LOCATION</label>
                                            <input type="text" class="form-control" id="delivered_to" name="delivered_to" autocomplete="off" placeholder="Search location">
                                            <ul id="deliveredToList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1050; display: none; max-height: 240px; overflow-y: auto;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <label for="remarks" class="form-label">REMARKS</label>
                                        <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
                                    </div>
                                    <div class="col-md-12">
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
                                        <label for="genset_hr_reading" class="form-label">GENSET HR READING</label>
                                        <input type="text" class="form-control" id="genset_hr_reading" name="genset_hr_reading">
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="defect_hubo" name="defect_hubo" value="1">
                                            <label class="form-check-label" for="defect_hubo">DEFECT HUBO (genset hour meter defective &mdash; record GS start/end date &amp; time instead)</label>
                                        </div>
                                    </div>
                                    <div class="col-md-12 d-none" id="gsDefectFields">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="gs_start_date" class="form-label">GS START DATE</label>
                                                <input type="text" class="form-control" id="gs_start_date" name="gs_start_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="gs_start_time" class="form-label">GS START TIME</label>
                                                <input type="text" class="form-control" id="gs_start_time" name="gs_start_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="gs_end_date" class="form-label">GS END DATE</label>
                                                <input type="text" class="form-control" id="gs_end_date" name="gs_end_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="gs_end_time" class="form-label">GS END TIME</label>
                                                <input type="text" class="form-control" id="gs_end_time" name="gs_end_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM">
                                            </div>
                                        </div>
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
                                <h5 class="mb-0">All Entries</h5>
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
                                        <th>Waybill</th>
                                        <th>Van</th>
                                        <th>Driver</th>
                                        <th>Remarks</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch(PDO::FETCH_ASSOC)) { ?>
                                    <tr data-id="<?php echo $row['entry_id']; ?>" data-segment="<?php echo htmlspecialchars($row['segment']); ?>" data-activity="<?php echo htmlspecialchars($row['activity']); ?>" data-waybill="<?php echo htmlspecialchars($row['waybill']); ?>" data-driver="<?php echo htmlspecialchars($row['driver']); ?>" data-remarks="<?php echo htmlspecialchars($row['remarks']); ?>">
                                        <td><strong>#<?php echo $row['entry_id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['created_date'] ? date('m/d/Y', strtotime($row['created_date'])) : ''); ?></td>
                                        <td><?php echo htmlspecialchars(($row['waybill'] ?? '') !== '' ? $row['waybill'] : ($row['waybill_empty'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(trim(($row['van_alpha'] ?? '') . ' ' . ($row['van_number'] ?? '') . ' ' . ($row['van_name'] ?? ''))); ?></td>
                                        <td><?php echo htmlspecialchars($row['driver'] ?? ''); ?></td>
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
            // ===== Defect Hubo toggle: reveal GS start/end date & time inputs =====
            const defectHuboToggle = document.getElementById("defect_hubo");
            if (defectHuboToggle) {
                defectHuboToggle.addEventListener("change", applyDefectHuboState);
                applyDefectHuboState();
            }

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
            let selectedSegment = "";
            let selectedEmptySegment = "";

            fetch("php/fetch/get_segment_activity.php")
                .then(res => res.json())
                .then(data => {
                    allSegmentActivity = data;
                });

            if (segmentEmptyInput && segmentEmptyList) {
                segmentEmptyInput.addEventListener("input", function() {
                    const searchVal = this.value.toLowerCase();
                    const filteredSegments = [...new Set(allSegmentActivity
                        .filter(item => item.segment.toLowerCase().includes(searchVal))
                        .map(item => item.segment))];

                    filterDropdown(this, segmentEmptyList, filteredSegments, (name) => {
                        segmentEmptyInput.value = name;
                        selectedEmptySegment = name;
                        if (activityEmptyInput) {
                            activityEmptyInput.value = "";
                        }
                        if (activityEmptyList) {
                            activityEmptyList.innerHTML = "";
                        }
                    });
                });
            }

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

            if (segmentInput && segmentList) {
                segmentInput.addEventListener("input", function() {
                    const searchVal = this.value.toLowerCase();
                    const filteredSegments = [...new Set(allSegmentActivity
                        .filter(item => item.segment.toLowerCase().includes(searchVal))
                        .map(item => item.segment))];

                    filterDropdown(this, segmentList, filteredSegments, (name) => {
                        segmentInput.value = name;
                        selectedSegment = name;
                        if (activityInput) {
                            activityInput.value = "";
                        }
                        if (activityList) {
                            activityList.innerHTML = "";
                        }
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
                    allDrivers = data;
                });

            if (driverInput && driverList) {
                driverInput.addEventListener("input", function() {
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
                        (name) => { phInput.value = name; }
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
                        (name) => { deliveredToInput.value = name; }
                    );
                });
            }

            if (emptyPulloutLocationInput && emptyPulloutLocationList) {
                emptyPulloutLocationInput.addEventListener("input", function() {
                    filterDropdownRecords(
                        this,
                        emptyPulloutLocationList,
                        allLocations,
                        (loc) => loc.location_name || "",
                        (loc) => loc.location_name || "",
                        (name) => { emptyPulloutLocationInput.value = name; }
                    );
                });
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
                        (name) => { gensetInput.value = name; }
                    );
                });
            }

            // ===== Trailer (TR) search =====
            const trailerInput = document.getElementById("tr");
            const trailerList = document.getElementById("trailerList");
            let allTrailers = [];
            const allSearchLists = [segmentEmptyList, activityEmptyList, segmentList, activityList, phList, deliveredToList, driverList, driver2List, truckList, pm2List, gensetList, trailerList].filter(Boolean);

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
          let activeIndex = 0;

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
            items[index].classList.add("active-suggestion");
            items[index].scrollIntoView({ block: 'nearest' });
          }
        }

            document.addEventListener("mousedown", function(event) {
                const clickedInsideSearch = event.target.closest(".position-relative");
                if (clickedInsideSearch) return;
                allSearchLists.forEach(hideDropdown);
            });

            // Attach keyboard nav to each input/list
            if (segmentEmptyInput && segmentEmptyList) {
                attachKeyboardNav(segmentEmptyInput, segmentEmptyList, (val) => {
                    segmentEmptyInput.value = val;
                    selectedEmptySegment = val;
                    if (activityEmptyInput) {
                        activityEmptyInput.value = "";
                    }
                    if (activityEmptyList) {
                        activityEmptyList.innerHTML = "";
                    }
                });
            }

            if (activityEmptyInput && activityEmptyList) {
                attachKeyboardNav(activityEmptyInput, activityEmptyList, (val) => {
                    activityEmptyInput.value = val;
                });
            }

            if (segmentInput && segmentList) {
                attachKeyboardNav(segmentInput, segmentList, (val) => {
                    segmentInput.value = val;
                    selectedSegment = val;
                    if (activityInput) {
                        activityInput.value = "";
                    }
                    if (activityList) {
                        activityList.innerHTML = "";
                    }
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
                });
            }

            if (trailerInput && trailerList) {
                attachKeyboardNav(trailerInput, trailerList, (val) => {
                    trailerInput.value = val;
                });
            }
        });

        // === ENTRY CRUD (fast inline encoding) ===
        const rvTableElement = document.querySelector('#entriesTable');
        let rvTable = new DataTable('#entriesTable');

        const form = document.getElementById('dataEntryForm');
        const dataIdInput = document.getElementById('data_id');

        // Convert display format date (MM/DD/YYYY or M/D/YYYY) to database format (YYYY-MM-DD)
        function convertDateToDatabase(displayDate) {
            if (!displayDate || displayDate === '') return '';
            
            // Try to parse M/D/YYYY, MM/DD/YYYY, M/D, or MM/DD formats
            const parts = displayDate.trim().split('/');
            if (parts.length < 2 || parts.length > 3) return displayDate; // Invalid format, return as-is
            
            let month = parts[0];
            let day = parts[1];
            let year = parts[2];
            
            // Pad month and day with leading zeros if needed
            month = month.padStart(2, '0');
            day = day.padStart(2, '0');
            
            // If year is not provided, use current year
            if (!year) {
                const today = new Date();
                year = today.getFullYear().toString();
            } else if (year.length === 2) {
                // Convert 2-digit year to 4-digit year (assume 00-99 is 2000-2099)
                year = '20' + year;
            }
            
            // Validate date values
            if (isNaN(month) || isNaN(day) || isNaN(year)) return displayDate;
            
            // Return in YYYY-MM-DD format
            return `${year}-${month}-${day}`;
        }

        // Convert display format time (HHMM or H:MM) to database format (HH:MM)
        function convertTimeToDatabase(displayTime) {
            if (!displayTime || displayTime === '') return '';
            
            // Remove any colons first
            let time = displayTime.replace(':', '');
            
            // If it's 3 or 4 digits, assume it's HHMM format
            if (time.match(/^\d{3,4}$/)) {
                // Pad to 4 digits if needed (e.g., "930" becomes "0930")
                time = time.padStart(4, '0');
                // Extract HH and MM
                const hh = time.substring(0, 2);
                const mm = time.substring(2, 4);
                
                // Validate
                const hour = parseInt(hh);
                const minute = parseInt(mm);
                if (hour > 23 || minute > 59) return displayTime; // Invalid, return as-is
                
                return `${hh}:${mm}`;
            }
            
            // If already in HH:MM format, validate and return
            if (time.match(/^\d{2}:\d{2}$/)) {
                const [hh, mm] = time.split(':');
                const hour = parseInt(hh);
                const minute = parseInt(mm);
                if (hour > 23 || minute > 59) return displayTime;
                return time;
            }
            
            // Couldn't parse, return as-is
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
                id: dataIdInput.value || null,
                segment_empty: document.getElementById('segment_empty').value.trim(),
                activity_empty: document.getElementById('activity_empty').value.trim(),
                segment: document.getElementById('segment').value.trim(),
                activity: document.getElementById('activity').value.trim(),
                pullout_location_arrival_date: document.getElementById('pullout_location_arrival_date').value,
                pullout_location_arrival_time: document.getElementById('pullout_location_arrival_time').value,
                pullout_location_departure_date: document.getElementById('pullout_location_departure_date').value,
                pullout_location_departure_time: document.getElementById('pullout_location_departure_time').value,
                ph_arrival_date: document.getElementById('ph_arrival_date').value,
                ph_arrival_time: document.getElementById('ph_arrival_time').value,
                withdrawal_date: document.getElementById('withdrawal_date').value,
                withdrawal_time: document.getElementById('withdrawal_time').value,
                van_alpha: document.getElementById('van_alpha').value.trim(),
                van_number: document.getElementById('van_number').value.trim(),
                van_name: document.getElementById('van_name').value.trim(),
                ph: document.getElementById('ph').value.trim(),
                shipper: document.getElementById('shipper').value.trim(),
                ecs: document.getElementById('ecs').value.trim(),
                tr: document.getElementById('tr').value.trim(),
                gs: document.getElementById('gs').value.trim(),
                waybill_empty: document.getElementById('waybill_empty') ? document.getElementById('waybill_empty').value.trim() : '',
                prime_mover: document.getElementById('prime_mover').value.trim(),
                driver: document.getElementById('driver').value.trim(),
                empty_pullout_location: document.getElementById('empty_pullout_location').value.trim(),
                remarks: document.getElementById('remarks').value.trim(),
                loaded_van_loading_start_date: document.getElementById('loading_start_date').value,
                loaded_van_loading_start_time: document.getElementById('loading_start_time').value,
                loaded_van_loading_finish_date: document.getElementById('loading_finish_date').value,
                loaded_van_loading_finish_time: document.getElementById('loading_finish_time').value,
                loaded_van_delivery_departure_date: document.getElementById('delivery_departure_date').value,
                loaded_van_delivery_departure_time: document.getElementById('delivery_departure_time').value,
                loaded_van_delivery_arrival_date: document.getElementById('delivery_arrival_date').value,
                loaded_van_delivery_arrival_time: document.getElementById('delivery_arrival_time').value,
                end_uploading_date: document.getElementById('end_unloading_finish_date').value || document.getElementById('end_unloading_start_date').value,
                end_uploading_time: document.getElementById('end_unloading_finish_time').value || document.getElementById('end_unloading_start_time').value,
                end_unloading_start_date: document.getElementById('end_unloading_start_date').value,
                end_unloading_start_time: document.getElementById('end_unloading_start_time').value,
                end_unloading_finish_date: document.getElementById('end_unloading_finish_date').value,
                end_unloading_finish_time: document.getElementById('end_unloading_finish_time').value,
                waybill: document.getElementById('waybill').value.trim(),
                dr_no: document.getElementById('dr_no').value.trim(),
                reference_documents: document.getElementById('reference_documents').value.trim(),
                load_description: document.getElementById('load').value.trim(),
                delivered_by_prime_mover: document.getElementById('pm2').value.trim(),
                delivered_by_driver: document.getElementById('driver2').value.trim(),
                delivered_to: document.getElementById('delivered_to').value.trim(),
                genset_hr_meter: document.getElementById('genset_hr_meter').value.trim(),
                genset_hr_meter_start: document.getElementById('hr_meter_start').value.trim(),
                genset_hr_meter_end: document.getElementById('hr_meter_end').value.trim(),
                new_meter_reading: document.getElementById('new_meter_reading').checked ? '1' : '0',
                genset_hr_reading: document.getElementById('genset_hr_reading').value.trim(),
                defect_hubo: document.getElementById('defect_hubo').checked ? '1' : '0',
                genset_start_date: document.getElementById('gs_start_date').value,
                genset_start_time: document.getElementById('gs_start_time').value,
                genset_end_date: document.getElementById('gs_end_date').value,
                genset_end_time: document.getElementById('gs_end_time').value,
                refueled: document.getElementById('refueled').value.trim(),
                driver_idNumber: document.getElementById('driver_idNumber').value.trim(),
                delivered_by_driverIdNumber: document.getElementById('driver_idNumber2').value.trim()
            };

            // List of all date fields that need conversion from display format to database format
            const dateFields = [
                'pullout_location_arrival_date',
                'pullout_location_departure_date',
                'ph_arrival_date',
                'withdrawal_date',
                'loaded_van_loading_start_date',
                'loaded_van_loading_finish_date',
                'loaded_van_delivery_departure_date',
                'loaded_van_delivery_arrival_date',
                'end_uploading_date',
                'end_unloading_start_date',
                'end_unloading_finish_date',
                'genset_start_date',
                'genset_end_date'
            ];

            // List of all time fields that need conversion from display format to database format
            const timeFields = [
                'pullout_location_arrival_time',
                'pullout_location_departure_time',
                'ph_arrival_time',
                'withdrawal_time',
                'loaded_van_loading_start_time',
                'loaded_van_loading_finish_time',
                'loaded_van_delivery_departure_time',
                'loaded_van_delivery_arrival_time',
                'end_uploading_time',
                'end_unloading_start_time',
                'end_unloading_finish_time',
                'genset_start_time',
                'genset_end_time'
            ];

            // Convert all date fields from display format to database format
            dateFields.forEach(field => {
                if (values[field]) {
                    values[field] = convertDateToDatabase(values[field]);
                }
            });

            // Convert all time fields from display format to database format
            timeFields.forEach(field => {
                if (values[field]) {
                    values[field] = convertTimeToDatabase(values[field]);
                }
            });

            return values;
        }

        function fillFormFromRow(row) {
            document.getElementById('segment_empty').value = row.dataset.segment_empty || '';
            document.getElementById('activity_empty').value = row.dataset.activity_empty || '';
            dataIdInput.value = row.dataset.id;
            document.getElementById('segment').value = row.dataset.segment || '';
            document.getElementById('activity').value = row.dataset.activity || '';
            document.getElementById('waybill').value = row.dataset.waybill || '';
            document.getElementById('driver').value = row.dataset.driver || '';
            document.getElementById('remarks').value = row.dataset.remarks || '';
        }

        // Show the GS start/end date & time inputs only when "Defect Hubo" is on.
        // When switched off, hide and clear them so no stale datetime is submitted.
        function applyDefectHuboState() {
            const toggle = document.getElementById('defect_hubo');
            const fields = document.getElementById('gsDefectFields');
            if (!toggle || !fields) return;
            ['hr_meter_start', 'hr_meter_end'].forEach(id => {
                const input = document.getElementById(id);
                if (!input) return;
                input.disabled = toggle.checked;
                if (toggle.checked) input.value = '';
            });
            if (toggle.checked) {
                fields.classList.remove('d-none');
            } else {
                fields.classList.add('d-none');
                ['gs_start_date', 'gs_start_time', 'gs_end_date', 'gs_end_time'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
            }
        }

        function clearForm() {
            dataIdInput.value = '';
            form.reset();
            applyDefectHuboState();
        }

        function writeRow(rowData) {
            const displayDate = rowData.pullout_location_arrival_date || '';
            const displayWaybill = rowData.waybill || rowData.waybill_empty || '';
            const displayVan = [rowData.van_alpha, rowData.van_number, rowData.van_name].filter(Boolean).join(' ').trim();
            return `<tr data-id="${rowData.entry_id}" data-segment_empty="${rowData.segment_empty || ''}" data-activity_empty="${rowData.activity_empty || ''}" data-segment="${rowData.segment}" data-activity="${rowData.activity}" data-waybill="${rowData.waybill}" data-driver="${rowData.driver}" data-remarks="${rowData.remarks}">
                <td><strong>#${rowData.entry_id}</strong></td>
                <td>${displayDate}</td>
                <td>${displayWaybill}</td>
                <td>${displayVan}</td>
                <td>${rowData.driver || ''}</td>
                <td>${rowData.remarks || ''}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary btn-edit" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-outline-danger btn-delete" title="Delete"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
            </tr>`;
        }

        function getTableRowData(rowData) {
            const displayDate = formatDateForDisplay(rowData.created_date || '');
            const displayWaybill = rowData.waybill || rowData.waybill_empty || '';
            const displayVan = [rowData.van_alpha, rowData.van_number, rowData.van_name].filter(Boolean).join(' ').trim();
            return [
                `<strong>#${rowData.entry_id}</strong>`,
                displayDate,
                displayWaybill,
                displayVan,
                rowData.driver || '',
                rowData.remarks || '',
                `<div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-primary btn-edit" title="Edit"><i class="bi bi-pencil"></i></button>
                    <button type="button" class="btn btn-outline-danger btn-delete" title="Delete"><i class="bi bi-trash"></i></button>
                </div>`
            ];
        }

        function setRowDataAttributes(row, rowData) {
            if (!row) return;
            row.dataset.id = rowData.entry_id;
            row.dataset.segment_empty = rowData.segment_empty || '';
            row.dataset.activity_empty = rowData.activity_empty || '';
            row.dataset.segment = rowData.segment;
            row.dataset.activity = rowData.activity;
            row.dataset.waybill = rowData.waybill;
            row.dataset.driver = rowData.driver;
            row.dataset.remarks = rowData.remarks;
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
            if (!record) return;
            dataIdInput.value = record.entry_id || '';
            selectedEmptySegment = record.segment_empty || '';
            selectedSegment = record.segment || '';
            
            // Fields that need date formatting (YYYY-MM-DD to MM/DD/YYYY)
            const dateFields = [
                'pullout_location_arrival_date',
                'pullout_location_departure_date',
                'ph_arrival_date',
                'withdrawal_date',
                'loading_start_date',
                'loading_finish_date',
                'delivery_departure_date',
                'delivery_arrival_date',
                'end_unloading_start_date',
                'end_unloading_finish_date',
                'gs_start_date',
                'gs_end_date'
            ];

            // Fields that need time formatting (HH:MM to HHMM)
            const timeFields = [
                'pullout_location_arrival_time',
                'pullout_location_departure_time',
                'ph_arrival_time',
                'withdrawal_time',
                'loading_start_time',
                'loading_finish_time',
                'delivery_departure_time',
                'delivery_arrival_time',
                'end_unloading_start_time',
                'end_unloading_finish_time',
                'gs_start_time',
                'gs_end_time'
            ];

            const fieldMap = {
                segment_empty: 'segment_empty',
                activity_empty: 'activity_empty',
                segment: 'segment',
                activity: 'activity',
                pullout_location_arrival_date: 'pullout_location_arrival_date',
                pullout_location_arrival_time: 'pullout_location_arrival_time',
                pullout_location_departure_date: 'pullout_location_departure_date',
                pullout_location_departure_time: 'pullout_location_departure_time',
                ph_arrival_date: 'ph_arrival_date',
                ph_arrival_time: 'ph_arrival_time',
                withdrawal_date: 'withdrawal_date',
                withdrawal_time: 'withdrawal_time',
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
                end_unloading_start_date: 'end_unloading_start_date',
                end_unloading_start_time: 'end_unloading_start_time',
                end_uploading_date: 'end_unloading_finish_date',
                end_uploading_time: 'end_unloading_finish_time',
                end_unloading_finish_date: 'end_unloading_finish_date',
                end_unloading_finish_time: 'end_unloading_finish_time',
                dr_no: 'dr_no',
                reference_documents: 'reference_documents',
                load_description: 'load',
                delivered_by_prime_mover: 'pm2',
                delivered_by_driver: 'driver2',
                delivered_to: 'delivered_to',
                remarks: 'remarks',
                genset_hr_meter: 'genset_hr_meter',
                genset_hr_meter_start: 'hr_meter_start',
                genset_hr_meter_end: 'hr_meter_end',
                genset_hr_reading: 'genset_hr_reading',
                genset_start_date: 'gs_start_date',
                genset_start_time: 'gs_start_time',
                genset_end_date: 'gs_end_date',
                genset_end_time: 'gs_end_time',
                delivered_by_driverIdNumber: 'driver_idNumber2',
                refueled: 'refueled'
            };

            const fallbackMap = {
                withdrawal_date: ['pullout_location_departure_date'],
                withdrawal_time: ['pullout_location_departure_time'],
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

            // Defect Hubo: on if the flag is set, or (for older records) if a GS
            // start/end date was captured. Reveal the datetime inputs accordingly.
            const defectHubo = document.getElementById('defect_hubo');
            if (defectHubo) {
                const flag = record.defect_hubo;
                const flagOn = flag === true || flag === 1 || flag === '1' || flag === 't' || flag === 'true';
                defectHubo.checked = flagOn || !!record.genset_start_date || !!record.genset_end_date;
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
                    const collapseEl = document.querySelector('#entryForm');
                    if (collapseEl) {
                        collapseEl.classList.add('show');
                    }
                })
                .catch(() => {
                    Swal.fire('Error', 'Unable to fetch record.', 'error');
                });
        }

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
            if (!values.prime_mover) {
                Swal.fire('Missing field', 'Prime mover is required.', 'warning');
                return;
            }
            if (!values.driver) {
                Swal.fire('Missing field', 'Driver is required.', 'warning');
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

    </script>
</body>

</html>
