<?php 
include "php/session-check.php"; 
include "php/config/config.php";
include_once "php/helpers/entry_date_filter.php";

function normalizeOthersDateValue($value): string
{
    if (!is_string($value)) {
        return '';
    }

    $value = trim($value);
    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '';
    }

    return $value;
}

function getOthersEffectiveDate(array $row): string
{
    $candidates = [
        $row['others_date'] ?? '',
        $row['cargo_date'] ?? '',
        $row['dpc_date'] ?? '',
        $row['waybill_date'] ?? '',
        $row['pullout_location_arrival_date'] ?? '',
        $row['production_date'] ?? '',
        $row['finished_loading_date'] ?? '',
        $row['created_date'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $normalized = normalizeOthersDateValue($candidate);
        if ($normalized !== '') {
            return $normalized;
        }
    }

    return '';
}

$selectedEntryDate = getSelectedEntryDate();
$stmt = $conn->prepare("SELECT entry_id, entry_type, segment, activity, waybill_date, remarks, pullout_location_arrival_date, pullout_location_arrival_time, pullout_location_departure_date, pullout_location_departure_time, ph_arrival_date, ph_arrival_time, van_alpha, van_number, van_name, ph, shipper, ecs, tr, gs, waybill, waybill_empty, prime_mover, driver, \"driver_idNumber\", empty_pullout_location, loaded_van_loading_start_date, loaded_van_loading_start_time, loaded_van_loading_finish_date, loaded_van_loading_finish_time, loaded_van_delivery_departure_date, loaded_van_delivery_departure_time, loaded_van_delivery_arrival_date, loaded_van_delivery_arrival_time, genset_shutoff_date, genset_shutoff_time, end_uploading_date, end_uploading_time, dr_no, load_description, delivered_by_prime_mover, delivered_by_driver, delivered_to, delivered_remarks, genset_hr_meter_start, genset_hr_meter_end, genset_start_date, genset_start_time, genset_end_date, genset_end_time, others_date, truck, operations_ph, load_quantity_weight, unit_of_measure, deliver_from, production_date, finished_loading_date, finished_loading_time, ph_departure_date, ph_departure_time, wharf_arrival_date, wharf_arrival_time, wharf_departure_date, wharf_departure_time, tls_number, \"13_kgs\", sp_3kgs, total_load, bbhm_type, dpc_date, evita_farmind, departure, arrival, \"13_body\", \"13_cover\", \"13_pads\", \"18_body\", \"18_cover\", \"18_pads\", \"13_total\", \"18_total\", fgtr_no, cargo_date, customer_ph, outside, compound, total_trips, operations, piece_rate, kms, created_by, created_date, modified_by, modified_date FROM operations WHERE entry_type = 'OTHERS ENTRY' AND created_date::date = ? ORDER BY entry_id DESC");
$stmt->execute([$selectedEntryDate]);
$result = $stmt;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Others Data Entry - DataEncode System</title>
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
                            <h2 class="fw-bold">Others Data Entry</h2>
                            <p class="text-muted">Create, view, and manage others data entries efficiently.</p>
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
                        <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>New Others Entry</h5>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#entryForm">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                    <div class="collapse" id="entryForm">
                        <div class="card-body">
                            <form id="dataEntryForm">
                                <input type="hidden" id="data_id" name="data_id">
                                <div class="row g-3">
                                    <div class="col-md-6" hidden>
                                        <div class="mb-3 position-relative">
                                            <label for="segment" class="form-label">Segment</label>
                                            <input type="text" class="form-control" id="segment" name="segment" value="Hustling">
                                            <ul id="segmentList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6" hidden>
                                        <div class="mb-3 position-relative">
                                            <label for="activity" class="form-label">Activity</label>
                                            <input type="text" class="form-control" id="activity" name="activity" value="DICT.Hustling (Less 10Vans)">
                                            <ul id="activityList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="date" class="form-label">DATE <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="date" name="date" required data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="waybill" class="form-label">Trip Receipt No. <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="waybill" name="waybill" required maxlength="6" inputmode="numeric">
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="truck" class="form-label">TRUCK <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="truck" name="truck" required autocomplete="off">
                                            <ul id="truckList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1020; display: none; max-height: 220px; overflow-y: auto;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="driver" class="form-label">DRIVER <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="driver" name="driver" required autocomplete="off">
                                            <ul id="driverList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                                            <input type="hidden" id="driver_idNumber" name="driver_idNumber">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="tr" class="form-label">TR (Trailer)</label>
                                            <input type="text" class="form-control" id="tr" name="tr" autocomplete="off" placeholder="Search trailer…">
                                            <ul id="trList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1040; display: none; max-height: 220px; overflow-y: auto;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="gs" class="form-label">GS (Genset)</label>
                                            <input type="text" class="form-control" id="gs" name="gs" autocomplete="off" placeholder="Search genset (GS)…">
                                            <ul id="gensetList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1030; display: none; max-height: 220px; overflow-y: auto;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="operations_ph" class="form-label">OPERATIONS / PH (Location) <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="operations_ph" name="operations_ph" required autocomplete="off" placeholder="Search packing house or location…">
                                            <ul id="operationsPhList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1050; display: none; max-height: 220px; overflow-y: auto;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="customer_ph" class="form-label">CUSTOMER <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="customer_ph" name="customer_ph" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="load_quantity_weight" class="form-label">Load/Quantity/Weight <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="load_quantity_weight" name="load_quantity_weight" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="unit_of_measure" class="form-label">UNIT OF MEASURE <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="unit_of_measure" name="unit_of_measure" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="kms" class="form-label">KMS</label>
                                        <input type="text" class="form-control" id="kms" name="kms">
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="deliver_from" class="form-label">DELIVER FROM <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="deliver_from" name="deliver_from" required autocomplete="off" placeholder="Search location">
                                            <ul id="deliverFromList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1050; display: none; max-height: 220px; overflow-y: auto;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label for="deliver_to" class="form-label">DELIVER TO <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="deliver_to" name="deliver_to" required autocomplete="off" placeholder="Search location">
                                            <ul id="deliverToList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1050; display: none; max-height: 220px; overflow-y: auto;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <label for="remarks" class="form-label">REMARKS</label>
                                        <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
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
                                        <th>Trip Receipt No.</th>
                                        <th>Trailer</th>
                                        <th>Driver</th>
                                        <th>Remarks</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch(PDO::FETCH_ASSOC)) { ?>
                                    <tr
                                        data-id="<?php echo $row['entry_id']; ?>"
                                        data-segment="<?php echo htmlspecialchars($row['segment']); ?>"
                                        data-activity="<?php echo htmlspecialchars($row['activity']); ?>"
                                        data-date="<?php echo htmlspecialchars(getOthersEffectiveDate($row)); ?>"
                                        data-waybill="<?php echo htmlspecialchars($row['waybill']); ?>"
                                        data-truck="<?php echo htmlspecialchars($row['truck']); ?>"
                                        data-driver="<?php echo htmlspecialchars($row['driver']); ?>"
                                        data-driver_id="<?php echo htmlspecialchars($row['driver_idNumber'] ?? ''); ?>"
                                        data-tr="<?php echo htmlspecialchars($row['tr']); ?>"
                                        data-gs="<?php echo htmlspecialchars($row['gs'] ?? ''); ?>"
                                        data-operations="<?php echo htmlspecialchars($row['operations_ph']); ?>"
                                        data-customer_ph="<?php echo htmlspecialchars($row['customer_ph']); ?>"
                                        data-load_qty="<?php echo htmlspecialchars($row['load_quantity_weight']); ?>"
                                        data-unit_of_measure="<?php echo htmlspecialchars($row['unit_of_measure']); ?>"
                                        data-kms="<?php echo htmlspecialchars($row['kms'] ?? ''); ?>"
                                        data-deliver_from="<?php echo htmlspecialchars($row['deliver_from']); ?>"
                                        data-deliver_to="<?php echo htmlspecialchars($row['delivered_to']); ?>"
                                        data-remarks="<?php echo htmlspecialchars($row['remarks']); ?>"
                                    >
                                        <td><strong>#<?php echo $row['entry_id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars(getOthersEffectiveDate($row)); ?></td>
                                        <td><?php echo htmlspecialchars(($row['waybill'] ?? '') !== '' ? $row['waybill'] : ($row['waybill_empty'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(($row['tr'] ?? '') !== '' ? $row['tr'] : (trim(($row['van_alpha'] ?? '') . ' ' . ($row['van_number'] ?? '')) !== '' ? trim(($row['van_alpha'] ?? '') . ' ' . ($row['van_number'] ?? '')) : (($row['tr'] ?? '') !== '' ? $row['tr'] : ($row['truck'] ?? '')))); ?></td>
                                        <td><?php echo htmlspecialchars(($row['driver'] ?? '') !== '' ? $row['driver'] : ($row['delivered_by_driver'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(($row['remarks'] ?? '') !== '' ? $row['remarks'] : ($row['delivered_remarks'] ?? '')); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary btn-edit" title="Edit"><i class="bi bi-pencil"></i></button>
                                                <button class="btn btn-outline-danger btn-delete" title="Delete"><i class="bi bi-trash"></i></button>
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
    <script src="assets/DataTables/datatables.min.js"></script>
    <script src="assets/alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
    <script>
        let table = new DataTable('#entriesTable');

        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('dataEntryForm');
            const dataIdInput = document.getElementById('data_id');
            const segmentInput = document.getElementById('segment');
            const activityInput = document.getElementById('activity');
            const dateInput = document.getElementById('date');
            const waybillInput = document.getElementById('waybill');
            const truckInput = document.getElementById('truck');
            const driverInput = document.getElementById('driver');
            const driverIdInput = document.getElementById("driver_idNumber");
            const trInput = document.getElementById('tr');
            const gsInput = document.getElementById('gs');
            const operationsInput = document.getElementById('operations_ph');
            const customerPhInput = document.getElementById('customer_ph');
            const loadQtyInput = document.getElementById('load_quantity_weight');
            const unitOfMeasureInput = document.getElementById('unit_of_measure');
            const kmsInput = document.getElementById('kms');
            const deliverFromInput = document.getElementById('deliver_from');
            const deliverToInput = document.getElementById('deliver_to');
            const remarksInput = document.getElementById('remarks');
            const segmentList = document.getElementById('segmentList');
            const activityList = document.getElementById('activityList');
            const truckList = document.getElementById('truckList');
            const driverList = document.getElementById('driverList');
            const trList = document.getElementById('trList');
            const gensetList = document.getElementById('gensetList');
            const operationsPhList = document.getElementById('operationsPhList');
            const deliverFromList = document.getElementById('deliverFromList');
            const deliverToList = document.getElementById('deliverToList');
            const allSearchLists = [segmentList, activityList, truckList, driverList, trList, gensetList, operationsPhList, deliverFromList, deliverToList].filter(Boolean);

            // Auto-calculate arithmetic typed in Load/Quantity/Weight, e.g. "50+50" -> "100".
            // Only evaluates when the value is a pure arithmetic expression; anything
            // else (text, units) is left exactly as typed.
            if (loadQtyInput) {
                loadQtyInput.addEventListener('blur', function () {
                    const raw = String(loadQtyInput.value || '').trim();
                    if (raw === '' || !/^[\d+\-*/.()\s]+$/.test(raw) || !/[+\-*/]/.test(raw)) {
                        return;
                    }
                    try {
                        const result = Function('"use strict"; return (' + raw + ')')();
                        if (Number.isFinite(result)) {
                            loadQtyInput.value = Number.isInteger(result)
                                ? String(result)
                                : String(parseFloat(result.toFixed(4)));
                        }
                    } catch (e) {
                        /* not a valid expression: leave the typed value untouched */
                    }
                });
            }

            let allSegmentActivity = [];
            let selectedSegment = '';
            let allDrivers = [];
            let allTrucks = [];
            let allLocations = [];
            let allTrailers = [];
            let allGensets = [];
            form.querySelectorAll('input[type="text"]').forEach(function (input) {
                input.addEventListener('input', function () {
                    const pos = this.selectionStart;
                    this.value = this.value.toUpperCase();
                    this.setSelectionRange(pos, pos);
                });
            });

            fetch('php/fetch/get_segment_activity.php')
                .then(res => res.json())
                .then(data => {
                    allSegmentActivity = data;
                })
                .catch(() => {
                    // no-op
                });

            if (segmentInput && segmentList) {
                segmentInput.addEventListener('input', function () {
                    const searchVal = this.value.toLowerCase();
                    const filteredSegments = [...new Set(allSegmentActivity
                        .filter(item => item.segment.toLowerCase().includes(searchVal))
                        .map(item => item.segment))];

                    filterDropdown(this, segmentList, filteredSegments, (name) => {
                        segmentInput.value = name;
                        selectedSegment = name;
                        if (activityInput) activityInput.value = '';
                        if (activityList) activityList.innerHTML = '';
                    });
                });

                segmentInput.addEventListener('change', function () {
                    selectedSegment = this.value;
                    if (activityInput) activityInput.value = '';
                    if (activityList) activityList.innerHTML = '';
                });
            }

            if (activityInput && activityList) {
                activityInput.addEventListener('input', function () {
                    if (!selectedSegment) {
                        Swal.fire({ title: 'Segment required', text: 'Please select a segment first.', icon: 'warning' });
                        this.value = '';
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

            fetch('php/fetch/get_drivers.php')
                .then(res => res.json())
                .then(data => {
                    allDrivers = data;
                })
                .catch(() => {
                    // no-op
                });

            if (driverInput && driverList) {
                driverInput.addEventListener('input', function () {
                    filterDropdown(this, driverList, allDrivers.map(item => item.name),
                        (name) => {
                            driverInput.value = name;
                            const selected = allDrivers.find(d => d.name === name);
                            driverIdInput.value = selected ? selected.id : "";
                        });
                });
            }

            fetch('php/fetch/get_trucks.php')
                .then(res => res.json())
                .then(data => {
                    allTrucks = data.map(item => item.name || item);
                })
                .catch(() => {
                    // no-op
                });

            if (truckInput && truckList) {
                truckInput.addEventListener('input', function () {
                    filterDropdown(this, truckList, allTrucks,
                        (name) => {
                            truckInput.value = name;
                        });
                });
            }

            fetch('php/fetch/get_locations.php')
                .then(res => res.json())
                .then(data => { allLocations = Array.isArray(data) ? data : []; })
                .catch(() => { allLocations = []; });

            if (operationsInput && operationsPhList) {
                operationsInput.addEventListener('input', function () {
                    filterDropdownRecords(
                        this,
                        operationsPhList,
                        allLocations,
                        (loc) => loc.location_name || '',
                        (loc) => loc.location_name || '',
                        (name) => { operationsInput.value = name; }
                    );
                });
            }

            if (deliverFromInput && deliverFromList) {
                deliverFromInput.addEventListener('input', function () {
                    filterDropdownRecords(
                        this,
                        deliverFromList,
                        allLocations,
                        (loc) => loc.location_name || '',
                        (loc) => loc.location_name || '',
                        (name) => { deliverFromInput.value = name; }
                    );
                });
            }

            if (deliverToInput && deliverToList) {
                deliverToInput.addEventListener('input', function () {
                    filterDropdownRecords(
                        this,
                        deliverToList,
                        allLocations,
                        (loc) => loc.location_name || '',
                        (loc) => loc.location_name || '',
                        (name) => { deliverToInput.value = name; }
                    );
                });
            }

            fetch('php/fetch/get_trailers.php')
                .then(res => res.json())
                .then(data => { allTrailers = Array.isArray(data) ? data : []; })
                .catch(() => { allTrailers = []; });

            if (trInput && trList) {
                trInput.addEventListener('input', function () {
                    filterDropdownRecords(
                        this,
                        trList,
                        allTrailers,
                        (t) => t.trailer_name || '',
                        (t) => t.trailer_name,
                        (name) => { trInput.value = name; }
                    );
                });
            }

            fetch('php/fetch/get_gensets.php')
                .then(res => res.json())
                .then(data => { allGensets = Array.isArray(data) ? data : []; })
                .catch(() => { allGensets = []; });

            if (gsInput && gensetList) {
                gsInput.addEventListener('input', function () {
                    filterDropdownRecords(
                        this,
                        gensetList,
                        allGensets,
                        (u) => u.unit_name || '',
                        (u) => {
                            const bits = [u.unit_name];
                            if (u.unit_model) bits.push(u.unit_model);
                            if (u.unit_cluster) bits.push(u.unit_cluster);
                            return bits.join(' — ');
                        },
                        (name) => { gsInput.value = name; }
                    );
                });
            }

            function hideDropdown(listElem) {
                if (!listElem) return;
                listElem.style.display = 'none';
                listElem.innerHTML = '';
            }

            function showDropdown(listElem) {
                if (!listElem) return;
                listElem.style.maxHeight = '220px';
                listElem.style.overflowY = 'auto';
                listElem.style.overflowX = 'hidden';
                listElem.style.display = 'block';
            }

            function filterDropdown(inputElem, listElem, dataArr, onSelect) {
                const searchVal = inputElem.value.toLowerCase();

                if (!searchVal) {
                    hideDropdown(listElem);
                    return;
                }

                listElem.innerHTML = '';

                const filtered = dataArr.filter(item => String(item).toLowerCase().includes(searchVal));
                if (filtered.length === 0) {
                    hideDropdown(listElem);
                    return;
                }

                filtered.forEach((item, index) => {
                    const li = document.createElement('li');
                    li.className = 'list-group-item list-group-item-action';
                    const label = String(item);
                    li.textContent = label;
                    li.dataset.pickValue = label;

                    if (index === 0) {
                        li.classList.add('active-suggestion');
                    }

                    li.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                        onSelect(label);
                        hideDropdown(listElem);
                    });
                    listElem.appendChild(li);
                });

                showDropdown(listElem);
            }

            function filterDropdownRecords(inputElem, listElem, records, getValue, formatLine, onPick) {
                const searchVal = inputElem.value.toLowerCase();

                if (!searchVal) {
                    hideDropdown(listElem);
                    return;
                }

                listElem.innerHTML = '';

                const filtered = records.filter((r) => getValue(r).toLowerCase().includes(searchVal));
                if (filtered.length === 0) {
                    hideDropdown(listElem);
                    return;
                }

                filtered.forEach((r, index) => {
                    const li = document.createElement('li');
                    li.className = 'list-group-item list-group-item-action';
                    const val = getValue(r);
                    li.textContent = formatLine(r);
                    li.dataset.pickValue = val;

                    if (index === 0) {
                        li.classList.add('active-suggestion');
                    }

                    li.addEventListener('mousedown', function (event) {
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

            document.addEventListener('mousedown', function (event) {
                const clickedInsideSearch = event.target.closest('.position-relative');
                if (clickedInsideSearch) return;
                allSearchLists.forEach(hideDropdown);
            });

            // Attach keyboard nav to each input/list
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

            if (driverInput && driverList) {
                attachKeyboardNav(driverInput, driverList, (val) => {
                    driverInput.value = val;
                    const selected = allDrivers.find(d => d.name === val);
                    driverIdInput.value = selected ? selected.id : "";
                });
            }

            if (truckInput && truckList) {
                attachKeyboardNav(truckInput, truckList, (val) => {
                    truckInput.value = val;
                });
            }

            if (operationsInput && operationsPhList) {
                attachKeyboardNav(operationsInput, operationsPhList, (val) => {
                    operationsInput.value = val;
                });
            }

            if (deliverFromInput && deliverFromList) {
                attachKeyboardNav(deliverFromInput, deliverFromList, (val) => {
                    deliverFromInput.value = val;
                });
            }

            if (deliverToInput && deliverToList) {
                attachKeyboardNav(deliverToInput, deliverToList, (val) => {
                    deliverToInput.value = val;
                });
            }

            if (trInput && trList) {
                attachKeyboardNav(trInput, trList, (val) => {
                    trInput.value = val;
                });
            }

            if (gsInput && gensetList) {
                attachKeyboardNav(gsInput, gensetList, (val) => {
                    gsInput.value = val;
                });
            }

            const showAlert = (title, message, icon = 'info') => {
                return Swal.fire({
                    title: title,
                    text: message,
                    icon: icon,
                    confirmButtonText: 'OK'
                });
            };

            const resetForm = () => {
                form.reset();
                dataIdInput.value = '';
                selectedSegment = '';
                if (segmentList) segmentList.innerHTML = '';
                if (activityList) activityList.innerHTML = '';
                if (truckList) truckList.innerHTML = '';
                if (driverList) driverList.innerHTML = '';
                if (trList) trList.innerHTML = '';
                if (gensetList) gensetList.innerHTML = '';
                if (operationsPhList) operationsPhList.innerHTML = '';
            };

            const fillFormFromRecord = (record) => {
                dataIdInput.value = record.entry_id || '';
                segmentInput.value = record.segment || '';
                activityInput.value = record.activity || '';
                dateInput.value = formatDateForDisplay(record.waybill_date) || '';
                waybillInput.value = record.waybill || '';
                truckInput.value = record.truck || '';
                driverInput.value = record.driver || '';
                driverIdInput.value = record.driver_idNumber || '';
                trInput.value = record.tr || '';
                gsInput.value = record.gs || '';
                operationsInput.value = record.operations_ph || '';
                customerPhInput.value = record.customer_ph || '';
                loadQtyInput.value = record.load_quantity_weight || '';
                unitOfMeasureInput.value = record.unit_of_measure || '';
                kmsInput.value = record.kms || '';
                deliverFromInput.value = record.deliver_from || '';
                deliverToInput.value = record.delivered_to || '';
                remarksInput.value = record.remarks || '';
                selectedSegment = record.segment || '';

                const collapseElement = document.getElementById('entryForm');
                const collapseInstance = bootstrap.Collapse.getOrCreateInstance(collapseElement, { toggle: false });
                collapseInstance.show();
            };

            // Helper function to convert YYYY-MM-DD to MM/DD/YYYY
            function formatDateForDisplay(dbDate) {
                if (!dbDate || dbDate === '') return '';
                const normalizedDate = String(dbDate).slice(0, 10);
                const match = normalizedDate.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                if (!match) return dbDate; // Return as-is if not in expected format
                return `${match[2]}/${match[3]}/${match[1]}`; // MM/DD/YYYY
            }

            function normalizeDateValue(value) {
                if (!value) return '';
                const normalized = String(value).trim();
                if (normalized === '' || normalized === '0000-00-00' || normalized === '0000-00-00 00:00:00') {
                    return '';
                }
                return normalized;
            }

            function getEffectiveOthersDate(rowData) {
                const candidates = [
                    rowData.others_date,
                    rowData.cargo_date,
                    rowData.dpc_date,
                    rowData.waybill_date,
                    rowData.pullout_location_arrival_date,
                    rowData.production_date,
                    rowData.finished_loading_date,
                    rowData.created_date
                ];

                for (const candidate of candidates) {
                    const normalized = normalizeDateValue(candidate);
                    if (normalized) {
                        return normalized;
                    }
                }

                return '';
            }

            const getTableRowData = (rowData) => {
                const displayDate = formatDateForDisplay(getEffectiveOthersDate(rowData));
                const displayWaybill = rowData.waybill || rowData.waybill_empty || '';
                const displayVan = rowData.van_name || (rowData.van_alpha || '' + ' ' + rowData.van_number || '').trim() || rowData.tr || rowData.truck || '';
                const displayDriver = rowData.driver || rowData.delivered_by_driver || '';
                const displayRemarks = rowData.remarks || rowData.delivered_remarks || '';
                return [
                    `<strong>#${rowData.entry_id}</strong>`,
                    displayDate,
                    displayWaybill,
                    displayVan,
                    displayDriver,
                    displayRemarks,
                    `<div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary btn-edit" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-outline-danger btn-delete" title="Delete"><i class="bi bi-trash"></i></button>
                    </div>`
                ];
            };

            const setRowDataAttributes = (row, rowData) => {
                if (!row) return;
                row.dataset.id = rowData.entry_id || '';
                row.dataset.segment = rowData.segment || '';
                row.dataset.activity = rowData.activity || '';
                row.dataset.date = getEffectiveOthersDate(rowData);
                row.dataset.waybill = rowData.waybill || '';
                row.dataset.truck = rowData.truck || '';
                row.dataset.driver = rowData.driver || '';
                row.dataset.driver_id = rowData.driver_idNumber || '';
                row.dataset.tr = rowData.tr || '';
                row.dataset.gs = rowData.gs || '';
                row.dataset.operations = rowData.operations_ph || '';
                row.dataset.customer_ph = rowData.customer_ph || '';
                row.dataset.load_qty = rowData.load_quantity_weight || '';
                row.dataset.unit_of_measure = rowData.unit_of_measure || '';
                row.dataset.kms = rowData.kms || '';
                row.dataset.deliver_from = rowData.deliver_from || '';
                row.dataset.deliver_to = rowData.delivered_to || '';
                row.dataset.remarks = rowData.remarks || '';
            };

            const loadRecordIntoForm = (row) => {
                const id = row?.dataset?.id;
                if (!id) return;

                fetch(`php/fetch/get_others.php?id=${encodeURIComponent(id)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (!data.success) {
                            showAlert('Error', data.message || 'Unable to load record.', 'error');
                            return;
                        }
                        fillFormFromRecord(data.record);
                    })
                    .catch(() => {
                        showAlert('Error', 'Unable to fetch record.', 'error');
                    });
            };

            // Auto-open a record when arriving from the For Update queue (?load_id=).
            (function () {
                const loadId = new URLSearchParams(window.location.search).get('load_id');
                if (!loadId) return;
                loadRecordIntoForm({ dataset: { id: loadId } });
                const collapseEl = document.querySelector('#entryForm');
                if (collapseEl) { collapseEl.classList.add('show'); setTimeout(() => collapseEl.scrollIntoView({ behavior: 'smooth', block: 'start' }), 350); }
            })();

            const attachRowActions = () => {
                const tbody = document.querySelector('#entriesTable tbody');
                if (!tbody) return;

                tbody.addEventListener('click', function (event) {
                    const btn = event.target.closest('.btn-edit, .btn-delete');
                    if (!btn) return;
                    const row = btn.closest('tr');
                    if (!row) return;

                    if (btn.classList.contains('btn-edit')) {
                        loadRecordIntoForm(row);
                        return;
                    }

                    if (btn.classList.contains('btn-delete')) {
                        const dataId = row.dataset.id;
                        if (!dataId) return;

                        Swal.fire({
                            title: 'Delete this Others entry?',
                            text: 'This action cannot be undone.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, delete it',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (!result.isConfirmed) return;

                            const formData = new FormData();
                            formData.append('action', 'delete-others');
                            formData.append('data_id', dataId);

                            fetch('php/delete/others.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    const rowApi = table.row(row);
                                    if (rowApi.any()) {
                                        rowApi.remove().draw(false);
                                    }
                                    showAlert('Deleted!', data.message || 'Entry deleted.', 'success');
                                } else {
                                    showAlert('Error', data.message || 'Delete failed.', 'error');
                                }
                            })
                            .catch(() => showAlert('Error', 'Unable to reach server.', 'error'));
                        });
                    }
                });
            };

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                if (!dateInput.value) {
                    showAlert('Missing field', 'Date is required.', 'warning');
                    return;
                }
                if (!waybillInput.value.trim()) {
                    showAlert('Missing field', 'Waybill is required.', 'warning');
                    return;
                }
                if (!/^\d{6}$/.test(waybillInput.value.trim())) {
                    showAlert('Invalid format', 'Waybill must be exactly 6 digits.', 'warning');
                    return;
                }
                if (!operationsInput.value.trim()) {
                    showAlert('Missing field', 'Operations / PH (location) is required. Use the location search list.', 'warning');
                    return;
                }
                if (!driverInput.value.trim()) {
                    showAlert('Missing field', 'Driver is required.', 'warning');
                    return;
                }

                const action = dataIdInput.value ? 'update-others' : 'add-others';
                const endpoint = dataIdInput.value ? 'php/update/others.php' : 'php/insert/others.php';

                const formData = new FormData();
                if (dataIdInput.value) {
                    formData.append('data_id', dataIdInput.value);
                }
                formData.append('action', action);
                formData.append('segment', segmentInput.value.trim());
                formData.append('activity', activityInput.value.trim());
                const dateParts = dateInput.value.split('/');
                const isoDate = dateParts.length === 3
                    ? `${dateParts[2]}-${dateParts[0].padStart(2, '0')}-${dateParts[1].padStart(2, '0')}`
                    : dateInput.value;
                formData.append('date', isoDate);
                formData.append('waybill', waybillInput.value.trim());
                formData.append('truck', truckInput.value.trim());
                formData.append('driver', driverInput.value.trim());
                formData.append('driver_idNumber', driverIdInput.value.trim());
                formData.append('tr', trInput.value.trim());
                formData.append('gs', gsInput.value.trim());
                formData.append('operations_ph', operationsInput.value.trim());
                formData.append('customer_ph', customerPhInput.value.trim());
                formData.append('load_quantity_weight', loadQtyInput.value.trim());
                formData.append('unit_of_measure', unitOfMeasureInput.value.trim());
                formData.append('kms', kmsInput.value.trim());
                formData.append('deliver_from', deliverFromInput.value.trim());
                formData.append('deliver_to', deliverToInput.value.trim());
                formData.append('remarks', remarksInput.value.trim());

                fetch(endpoint, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        showAlert('Error', data.message || 'Operation failed.', 'error');
                        return;
                    }

                    if (action === 'add-others') {
                        const newRow = table.row.add(getTableRowData(data.record)).draw(false).node();
                        setRowDataAttributes(newRow, data.record);
                    } else {
                        const row = document.querySelector(`#entriesTable tbody tr[data-id="${data.record.entry_id}"]`);
                        if (row) {
                            const rowApi = table.row(row);
                            if (rowApi.any()) {
                                rowApi.data(getTableRowData(data.record)).draw(false);
                                setRowDataAttributes(rowApi.node(), data.record);
                            }
                        }
                    }

                    resetForm();
                    showAlert('Success', data.message || 'Entry saved successfully.', 'success');
                })
                .catch(error => {
                    console.error('FETCH ERROR:', error);
                    showAlert('Error', 'Unable to reach server. Check console.', 'error');
                });
            });

            attachRowActions();

            $("#enav").attr({
                "class" : "nav-link active"
            });
        });
    </script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <!-- Trip receipt / waybill lookup against dispatch DB (auto-fill + alert) -->
    <script src="assets/js/trip_receipt_lookup.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof attachTripReceiptLookup !== 'function') return;
            ['waybill', 'waybill_empty'].forEach(function (id) {
                // Keep the encoder's chosen Customer (PH) authoritative — the dispatch
                // lookup must not overwrite it (it would fill e.g. "DPC" instead).
                if (document.getElementById(id)) attachTripReceiptLookup({ input: id, exclude: ['customer_ph'] });
            });
        });
    </script>
</body>
</html>
