<?php 
include "php/session-check.php"; 
include "php/config/config.php";

$query = "SELECT entry_id, entry_type, segment, activity, remarks, pullout_location_arrival_date, pullout_location_arrival_time, pullout_location_departure_date, pullout_location_departure_time, ph_arrival_date, ph_arrival_time, van_alpha, van_number, van_name, ph, shipper, ecs, tr, gs, waybill, waybill_empty, prime_mover, driver, empty_pullout_location, loaded_van_loading_start_date, loaded_van_loading_start_time, loaded_van_loading_finish_date, loaded_van_loading_finish_time, loaded_van_delivery_departure_date, loaded_van_delivery_departure_time, loaded_van_delivery_arrival_date, loaded_van_delivery_arrival_time, genset_shutoff_date, genset_shutoff_time, end_uploading_date, end_uploading_time, dr_no, load_description, delivered_by_prime_mover, delivered_by_driver, delivered_to, delivered_remarks, genset_hr_meter_start, genset_hr_meter_end, genset_start_date, genset_start_time, genset_end_date, genset_end_time FROM operations";
$result = mysqli_query($conn, $query);
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
    <link rel="stylesheet" href="assets/alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
    <script src="assets/alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
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
                                    <h5>PULLOUT LOCATION</h5>
                                    <div class="col-md-6">
                                        <label for="pullout_location_arrival_date" class="form-label">Pullout Location - Arrival Date</label>
                                        <input type="date" class="form-control" id="pullout_location_arrival_date" name="pullout_location_arrival_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="pullout_location_arrival_time" class="form-label">Pullout Location - Arrival Time</label>
                                        <input type="time" class="form-control" id="pullout_location_arrival_time" name="pullout_location_arrival_time">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="pullout_location_departure_date" class="form-label">Pullout Location - Departure Date</label>
                                        <input type="date" class="form-control" id="pullout_location_departure_date" name="pullout_location_departure_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="ph_arrival_date" class="form-label">PH Arrival Date</label>
                                        <input type="date" class="form-control" id="ph_arrival_date" name="ph_arrival_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="ph_arrival_time" class="form-label">PH Arrival Time</label>
                                        <input type="time" class="form-control" id="ph_arrival_time" name="ph_arrival_time">
                                    </div>
                                    <div class="col-md-12">
                                        <label for="van_alpha" class="form-label">VAN - ALPHA</label>
                                        <input type="text" class="form-control" id="van_alpha" name="van_alpha">
                                    </div>
                                    <div class="col-md-12">
                                        <label for="van_number" class="form-label">VAN - NUMBER</label>
                                        <input type="text" class="form-control" id="van_number" name="van_number">
                                    </div>
                                    <div class="col-md-12">
                                        <label for="empty_container_van" class="form-label">EMPTY CONTAINER VAN (WITHDRAWAL)</label>
                                        <input type="text" class="form-control" id="empty_container_van" name="empty_container_van">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="withdrawal_date" class="form-label">DATE</label>
                                        <input type="date" class="form-control" id="withdrawal_date" name="withdrawal_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="withdrawal_time" class="form-label">TIME</label>
                                        <input type="time" class="form-control" id="withdrawal_time" name="withdrawal_time">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="van_name" class="form-label">VAN NAME</label>
                                        <input type="text" class="form-control" id="van_name" name="van_name">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="ph" class="form-label">PH</label>
                                        <input type="text" class="form-control" id="ph" name="ph">
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
                                            <input type="text" class="form-control" id="tr" name="tr">
                                            <ul id="trailerList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="gs" class="form-label">GS</label>
                                            <input type="text" class="form-control" id="gs" name="gs">
                                            <ul id="gensetList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="prime_mover" class="form-label">PRIME MOVER</label>
                                            <input type="text" class="form-control" id="prime_mover" name="prime_mover">
                                            <ul id="truckList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>

                                        </div>

                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="driver" class="form-label">DRIVER</label>
                                            <input type="text" class="form-control" id="driver" name="driver">
                                            <ul id="driverList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                                        </div>

                                    </div>
                                    <div class="col-md-12">
                                        <label for="empty_pullout_location" class="form-label">EMPTY PULLOUT LOCATION</label>
                                        <input type="text" class="form-control" id="empty_pullout_location" name="empty_pullout_location">
                                    </div>
                                    <div class="col-md-12">
                                        <label for="loaded_container_van" class="form-label">LOADED CONTAINER VAN</label>
                                        <input type="text" class="form-control" id="loaded_container_van" name="loaded_container_van">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="loading_start_date" class="form-label">LOADING SCHEDULE START DATE</label>
                                        <input type="date" class="form-control" id="loading_start_date" name="loading_start_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="loading_start_time" class="form-label">LOADING SCHEDULE START TIME</label>
                                        <input type="time" class="form-control" id="loading_start_time" name="loading_start_time">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="loading_finish_date" class="form-label">LOADING SCHEDULE FINISH DATE</label>
                                        <input type="date" class="form-control" id="loading_finish_date" name="loading_finish_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="loading_finish_time" class="form-label">LOADING SCHEDULE FINISH TIME</label>
                                        <input type="time" class="form-control" id="loading_finish_time" name="loading_finish_time">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="delivery_departure_date" class="form-label">DELIVERY DEPARTURE DATE</label>
                                        <input type="date" class="form-control" id="delivery_departure_date" name="delivery_departure_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="delivery_departure_time" class="form-label">DELIVERY DEPARTURE TIME</label>
                                        <input type="time" class="form-control" id="delivery_departure_time" name="delivery_departure_time">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="delivery_arrival_date" class="form-label">DELIVERY ARRIVAL DATE</label>
                                        <input type="date" class="form-control" id="delivery_arrival_date" name="delivery_arrival_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="delivery_arrival_time" class="form-label">DELIVERY ARRIVAL TIME</label>
                                        <input type="time" class="form-control" id="delivery_arrival_time" name="delivery_arrival_time">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="genset_shut_off_start_date" class="form-label">GENSET SHUT OFF START DATE</label>
                                        <input type="date" class="form-control" id="genset_shut_off_start_date" name="genset_shut_off_start_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="genset_shut_off_start_time" class="form-label">GENSET SHUT OFF START TIME</label>
                                        <input type="time" class="form-control" id="genset_shut_off_start_time" name="genset_shut_off_start_time">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="genset_shut_off_finish_date" class="form-label">GENSET SHUT OFF FINISH DATE</label>
                                        <input type="date" class="form-control" id="genset_shut_off_finish_date" name="genset_shut_off_finish_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="genset_shut_off_finish_time" class="form-label">GENSET SHUT OFF FINISH TIME</label>
                                        <input type="time" class="form-control" id="genset_shut_off_finish_time" name="genset_shut_off_finish_time">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="end_unloading_start_date" class="form-label">END OF UNLOADING START DATE</label>
                                        <input type="date" class="form-control" id="end_unloading_start_date" name="end_unloading_start_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="end_unloading_start_time" class="form-label">END OF UNLOADING START TIME</label>
                                        <input type="time" class="form-control" id="end_unloading_start_time" name="end_unloading_start_time">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="end_unloading_finish_date" class="form-label">END OF UNLOADING FINISH DATE</label>
                                        <input type="date" class="form-control" id="end_unloading_finish_date" name="end_unloading_finish_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="end_unloading_finish_time" class="form-label">END OF UNLOADING FINISH TIME</label>
                                        <input type="time" class="form-control" id="end_unloading_finish_time" name="end_unloading_finish_time">
                                    </div>
                                    <div class="col-md-12">
                                        <label for="reference_documents" class="form-label">REFERENCE DOCUMENTS</label>
                                        <input type="text" class="form-control" id="reference_documents" name="reference_documents">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="waybill" class="form-label">WAYBILL</label>
                                        <input type="text" class="form-control" id="waybill" name="waybill">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="waybill_empty" class="form-label">WAYBILL EMPTY</label>
                                        <input type="text" class="form-control" id="waybill_empty" name="waybill_empty">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="dr_no" class="form-label">DR. NO.</label>
                                        <input type="text" class="form-control" id="dr_no" name="dr_no">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="load" class="form-label">LOAD</label>
                                        <input type="text" class="form-control" id="load" name="load">
                                    </div>
                                    <h5>DELIVERY DETAILS</h5>
                                    <div class="col-md-6">
                                        <label for="pm2" class="form-label">PM</label>
                                        <input type="text" class="form-control" id="pm2" name="pm2">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="driver2" class="form-label">DRIVER</label>
                                        <input type="text" class="form-control" id="driver2" name="driver2">
                                    </div>
                                    <div class="col-md-12">
                                        <label for="delivered_to" class="form-label">DELIVERED TO: STATE THE LOCATION</label>
                                        <input type="text" class="form-control" id="delivered_to" name="delivered_to">
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
                                        <label for="genset_hr_reading" class="form-label">GENSET HR READING</label>
                                        <input type="text" class="form-control" id="genset_hr_reading" name="genset_hr_reading">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="gs_start_date" class="form-label">GS START DATE</label>
                                        <input type="date" class="form-control" id="gs_start_date" name="gs_start_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="gs_start_time" class="form-label">GS START TIME</label>
                                        <input type="time" class="form-control" id="gs_start_time" name="gs_start_time">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="gs_end_date" class="form-label">GS END DATE</label>
                                        <input type="date" class="form-control" id="gs_end_date" name="gs_end_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="gs_end_time" class="form-label">GS END TIME</label>
                                        <input type="time" class="form-control" id="gs_end_time" name="gs_end_time">
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
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="entriesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>
                                            <input type="checkbox" class="form-check-input" id="selectAll">
                                        </th>
                                        <th>ID</th>
                                        <th>Segment</th>
                                        <th>Activity</th>
                                        <th>Waybill</th>
                                        <th>Driver</th>
                                        <th>Remarks</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                                    <tr data-id="<?php echo $row['entry_id']; ?>" data-segment="<?php echo htmlspecialchars($row['segment']); ?>" data-activity="<?php echo htmlspecialchars($row['activity']); ?>" data-waybill="<?php echo htmlspecialchars($row['waybill']); ?>" data-driver="<?php echo htmlspecialchars($row['driver']); ?>" data-remarks="<?php echo htmlspecialchars($row['remarks']); ?>">
                                        <td><input type="checkbox" class="form-check-input row-checkbox"></td>
                                        <td><strong>#<?php echo $row['entry_id']; ?></strong></td>
                                        <td><?php echo $row['segment']; ?></td>
                                        <td><?php echo $row['activity']; ?></td>
                                        <td><?php echo $row['waybill']; ?></td>
                                        <td><?php echo $row['driver']; ?></td>
                                        <td><?php echo $row['remarks']; ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></button>
                                                <button class="btn btn-outline-warning" title="Edit"><i class="bi bi-pencil"></i></button>
                                                <button class="btn btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
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
    <script>
        let table = new DataTable('#entriesTable');
         $(document).ready(function () {
            $("#enav").attr({
					"class" : "nav-link active"
				});
         });
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // ===== Segment & Activity Search =====
            const segmentInput = document.getElementById("segment");
            const segmentList = document.getElementById("segmentList");
            const activityInput = document.getElementById("activity");
            const activityList = document.getElementById("activityList");
            let allSegmentActivity = [];
            let selectedSegment = "";

            fetch("php/fetch/get_segment_activity.php")
                .then(res => res.json())
                .then(data => {
                    allSegmentActivity = data;
                });

            segmentInput.addEventListener("input", function() {
                const searchVal = this.value.toLowerCase();
                const filteredSegments = [...new Set(allSegmentActivity
                    .filter(item => item.segment.toLowerCase().includes(searchVal))
                    .map(item => item.segment))];

                filterDropdown(this, segmentList, filteredSegments, (name) => {
                    segmentInput.value = name;
                    selectedSegment = name;
                    activityInput.value = "";
                    activityList.innerHTML = "";
                });
            });

            activityInput.addEventListener("input", function() {
                if (!selectedSegment) {
                    alert("Please select a segment first");
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

            // ===== Driver Search =====
            const driverInput = document.getElementById("driver");
            const driverList = document.getElementById("driverList");
            let allDrivers = [];

            fetch("php/fetch/get_drivers.php")
                .then(res => res.json())
                .then(data => {
                    allDrivers = data;
                });

            driverInput.addEventListener("input", function() {
                filterDropdown(this, driverList, allDrivers.map(d => d.name), (name) => {
                    driverInput.value = name;
                });
            });

            // ===== Truck Search (PM field) =====
            const truckInput = document.getElementById("pm");
            const truckList = document.getElementById("truckList");
            let allTrucks = [];

            fetch("php/fetch/get_trucks.php")
                .then(res => res.json())
                .then(data => {
                    allTrucks = data;
                });

            truckInput.addEventListener("input", function() {
                filterDropdown(this, truckList, allTrucks, (name) => {
                    truckInput.value = name;
                });
            });

            // ===== Genset Search =====
            const gensetInput = document.getElementById("gs");
            const gensetList = document.getElementById("gensetList");
            let allGensets = [];

            fetch("php/fetch/get_gensets.php")
                .then(res => res.json())
                .then(data => {
                    allGensets = data;
                });

            gensetInput.addEventListener("input", function() {
                filterDropdown(this, gensetList, allGensets, (name) => {
                    gensetInput.value = name;
                });
            });

            // ===== Trailer Search =====
            const trailerInput = document.getElementById("trailer");
            const trailerList = document.getElementById("trailerList");
            let allTrailers = [];

            fetch("php/fetch/get_trailers.php")
                .then(res => res.json())
                .then(data => {
                    allTrailers = data;
                });

            trailerInput.addEventListener("input", function() {
                filterDropdown(this, trailerList, allTrailers, (name) => {
                    trailerInput.value = name;
                });
            });

            // ===== Shared Function =====
            function filterDropdown(inputElem, listElem, dataArr, onSelect) {
                const searchVal = inputElem.value.toLowerCase();
                listElem.innerHTML = "";

                if (!searchVal) {
                    listElem.style.display = "none";
                    return;
                }

                const filtered = dataArr.filter(item => item.toLowerCase().includes(searchVal));

                if (filtered.length === 0) {
                    listElem.style.display = "none";
                    return;
                }

                filtered.forEach((item, index) => {
                    const li = document.createElement("li");
                    li.className = "list-group-item";
                    li.textContent = item;

                    // highlight first suggestion
                    if (index === 0) {
                        li.classList.add("active-suggestion");
                    }

                    li.addEventListener("click", function() {
                        onSelect(item);
                        listElem.style.display = "none";
                    });
                    listElem.appendChild(li);
                });

                listElem.style.display = "block";
            }

            // ===== Autofill + Navigation Support =====
            function attachKeyboardNav(inputElem, listElem, onSelect) {
                let activeIndex = 0;

                inputElem.addEventListener("keydown", function(e) {
                    const items = listElem.querySelectorAll("li");
                    if (!items.length) return;

                    if (e.key === "ArrowDown") {
                        e.preventDefault();
                        activeIndex = (activeIndex + 1) % items.length;
                        updateActive(items, activeIndex);
                    } else if (e.key === "ArrowUp") {
                        e.preventDefault();
                        activeIndex = (activeIndex - 1 + items.length) % items.length;
                        updateActive(items, activeIndex);
                    } else if (e.key === "Enter" || e.key === "Tab") {
                        const activeItem = items[activeIndex];
                        if (activeItem) {
                            onSelect(activeItem.textContent);
                            listElem.style.display = "none";
                        }
                    }
                });

                function updateActive(items, index) {
                    items.forEach(i => i.classList.remove("active-suggestion"));
                    items[index].classList.add("active-suggestion");
                }
            }

            // Attach keyboard nav to each input/list
            attachKeyboardNav(segmentInput, segmentList, (val) => {
                segmentInput.value = val;
                selectedSegment = val;
                activityInput.value = "";
                activityList.innerHTML = "";
            });

            attachKeyboardNav(activityInput, activityList, (val) => {
                activityInput.value = val;
            });

            attachKeyboardNav(driverInput, driverList, (val) => {
                driverInput.value = val;
            });

            attachKeyboardNav(truckInput, truckList, (val) => {
                truckInput.value = val;
            });

            attachKeyboardNav(gensetInput, gensetList, (val) => {
                gensetInput.value = val;
            });

            attachKeyboardNav(trailerInput, trailerList, (val) => {
                trailerInput.value = val;
            });
        });

        // === ENTRY CRUD (fast inline encoding) ===
        const rvTableElement = document.querySelector('#entriesTable');
        let rvTable = new DataTable('#entriesTable');

        const form = document.getElementById('dataEntryForm');
        const dataIdInput = document.getElementById('data_id');

        function getValues() {
            const values = {
                id: dataIdInput.value || null,
                segment: document.getElementById('segment').value.trim(),
                activity: document.getElementById('activity').value.trim(),
                pullout_location_arrival_date: document.getElementById('pullout_location_arrival_date').value,
                pullout_location_arrival_time: document.getElementById('pullout_location_arrival_time').value,
                pullout_location_departure_date: document.getElementById('pullout_location_departure_date').value,
                pullout_location_departure_time: document.getElementById('pullout_location_departure_time').value,
                ph_arrival_date: document.getElementById('ph_arrival_date').value,
                ph_arrival_time: document.getElementById('ph_arrival_time').value,
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
                genset_shutoff_date: document.getElementById('genset_shut_off_start_date').value,
                genset_shutoff_time: document.getElementById('genset_shut_off_start_time').value,
                end_uploading_date: document.getElementById('end_unloading_finish_date').value,
                end_uploading_time: document.getElementById('end_unloading_finish_time').value,
                waybill: document.getElementById('waybill').value.trim(),
                dr_no: document.getElementById('dr_no').value.trim(),
                load_description: document.getElementById('load').value.trim(),
                delivered_by_prime_mover: document.getElementById('pm2').value.trim(),
                delivered_by_driver: document.getElementById('driver2').value.trim(),
                delivered_to: document.getElementById('delivered_to').value.trim(),
                delivered_remarks: document.getElementById('remarks').value.trim(),
                genset_hr_meter_start: document.getElementById('hr_meter_start').value.trim(),
                genset_hr_meter_end: document.getElementById('hr_meter_end').value.trim(),
                genset_start_date: document.getElementById('gs_start_date').value,
                genset_start_time: document.getElementById('gs_start_time').value,
                genset_end_date: document.getElementById('gs_end_date').value,
                genset_end_time: document.getElementById('gs_end_time').value
            };
            return values;
        }

        // Parse display date (M/D or M/D/YYYY) and display time (HHMM or H:MM) into Date
        function parseDisplayDate(d) {
            if (!d) return null;
            const parts = d.trim().split('/');
            if (parts.length < 2) return null;
            let month = parseInt(parts[0], 10);
            let day = parseInt(parts[1], 10);
            let year = parts[2] ? parseInt(parts[2], 10) : (new Date()).getFullYear();
            if (String(year).length === 2) year = 2000 + year;
            if (isNaN(month) || isNaN(day) || isNaN(year)) return null;
            return { year: year, month: month, day: day };
        }

        function parseDisplayTime(t) {
            if (!t) return null;
            let time = t.replace(':', '').trim();
            if (!time.match(/^\d{1,4}$/)) return null;
            time = time.padStart(4, '0');
            const hh = parseInt(time.substring(0,2), 10);
            const mm = parseInt(time.substring(2,4), 10);
            if (isNaN(hh) || isNaN(mm) || hh > 23 || mm > 59) return null;
            return { hh: hh, mm: mm };
        }

        function parseDisplayDateTime(d, t) {
            const dateParts = parseDisplayDate(d);
            if (!dateParts) return null;
            const timeParts = parseDisplayTime(t) || { hh:0, mm:0 };
            return new Date(dateParts.year, dateParts.month -1, dateParts.day, timeParts.hh, timeParts.mm, 0);
        }

        function fillFormFromRow(row) {
            dataIdInput.value = row.dataset.id;
            document.getElementById('segment').value = row.dataset.segment || '';
            document.getElementById('activity').value = row.dataset.activity || '';
            document.getElementById('waybill').value = row.dataset.waybill || '';
            document.getElementById('driver').value = row.dataset.driver || '';
            document.getElementById('remarks').value = row.dataset.remarks || '';
        }

        function clearForm() {
            dataIdInput.value = '';
            form.reset();
        }

        function writeRow(rowData) {
            return `<tr data-id="${rowData.entry_id}" data-segment="${rowData.segment}" data-activity="${rowData.activity}" data-waybill="${rowData.waybill}" data-driver="${rowData.driver}" data-remarks="${rowData.remarks}">
                <td><input type="checkbox" class="form-check-input row-checkbox"></td>
                <td><strong>#${rowData.entry_id}</strong></td>
                <td>${rowData.segment}</td>
                <td>${rowData.activity}</td>
                <td>${rowData.waybill}</td>
                <td>${rowData.driver}</td>
                <td>${rowData.remarks}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary btn-view" title="View"><i class="bi bi-eye"></i></button>
                        <button type="button" class="btn btn-outline-warning btn-edit" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-outline-danger btn-delete" title="Delete"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
            </tr>`;
        }

        function refreshTable() {
            if (rvTable) {
                rvTable.destroy();
            }
            rvTable = new DataTable('#entriesTable');
            attachRowActions();
        }

        function attachRowActions() {
            document.querySelectorAll('#entriesTable .btn-edit').forEach(btn => {
                btn.onclick = (e) => {
                    const row = e.target.closest('tr');
                    if (row) {
                        fillFormFromRow(row);
                        document.querySelector('#entryForm .collapse').classList.add('show');
                    }
                };
            });

            document.querySelectorAll('#entriesTable .btn-delete').forEach(btn => {
                btn.onclick = async (e) => {
                    const row = e.target.closest('tr');
                    if (!row) return;
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
                                row.remove();
                                refreshTable();
                                Swal.fire('Deleted!', 'Entry deleted.', 'success');
                            } else {
                                Swal.fire('Error', result.message || 'Delete failed', 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'Unable to reach server', 'error');
                        }
                    });
                };
            });
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const values = getValues();

            if (!values.segment) {
                Swal.fire('Missing field', 'Segment is required.', 'warning');
                return;
            }
            if (!values.activity) {
                Swal.fire('Missing field', 'Activity is required.', 'warning');
                return;
            }
            if (!values.waybill) {
                Swal.fire('Missing field', 'Waybill is required.', 'warning');
                return;
            }

            // Chronological order validations
            function validateSequence(pairs, labelSequence) {
                const dt = pairs.map(p => parseDisplayDateTime(values[p[0]], values[p[1]]));
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
                            const tbody = document.querySelector('#entriesTable tbody');
                            tbody.insertAdjacentHTML('beforeend', writeRow(data.record));
                        } else {
                            const row = document.querySelector(`#entriesTable tbody tr[data-id="${values.id}"]`);
                            if (row) {
                                row.outerHTML = writeRow(data.record);
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

    </script>
</body>

</html>