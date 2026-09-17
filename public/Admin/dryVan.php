<?php
include "php/session-check.php";
include "php/config/config.php";
include_once "php/helpers/entry_date_filter.php";

$selectedEntryDate = getSelectedEntryDate();
$stmt = $conn->prepare("SELECT entry_id, entry_type, status, customer_ph, delivered_to, return_location, size, van_alpha, van_number, van_name, waybill, date_hauled, driver, truck, tr, tr2, date_unloaded, remarks, waybill_empty, date_returned, driver_return, truck2, type, delivered_remarks, kms, booking, shipment_no, seal FROM operations WHERE entry_type = 'DRY VAN ENTRY' AND created_date::date = ? ORDER BY entry_id DESC");
$stmt->execute([$selectedEntryDate]);
$result = $stmt;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dry Van Data Entry - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/DataTables/datatables.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
                            <h2 class="fw-bold">Dry Van Data Entry</h2>
                            <p class="text-muted">Create, view, and manage dry van entries efficiently.</p>
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
                <div class="row">
                    <div class="col-md-12">
                        <div class="card mb-4">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>New Dry Van Entry</h5>
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#entryForm">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                            </div>
                            <div class="collapse" id="entryForm">
                                <div class="card-body">
                                    <form id="dataEntryForm">
                                        <input type="hidden" id="data_id" name="data_id" value="">
                                        <div class="row g-3">
                                            <div class="col-md-6" hidden>
                                                <label for="status" class="form-label">Status *</label>
                                                <select class="form-select" id="status" name="status" required>
                                                    <option value="">-- Select Status --</option>
                                                    <option value="Loaded Import / Empty Export">Loaded Import / Empty Export</option>
                                                    <option value="Empty Import / Loaded Export">Empty Import / Loaded Export</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 position-relative">
                                                <label for="customer_ph" class="form-label">Customer (PH) *</label>
                                                <input type="text" class="form-control" id="customer_ph" name="customer_ph" required>
                                                <ul id="customerList" class="list-group position-absolute w-100" style="z-index: 1000; display: none; max-height: 220px; overflow-y: auto;"></ul>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="ecs" class="form-label">ECS</label>
                                                <input type="text" class="form-control" id="ecs" name="ecs">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="van_alpha" class="form-label">Van Alpha</label>
                                                <input type="text" class="form-control" id="van_alpha" name="van_alpha" maxlength="4">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="van_number" class="form-label">Van Numeric</label>
                                                <input type="text" class="form-control" id="van_number" name="van_number" maxlength="7" inputmode="numeric">
                                            </div>
                                            <div class="col-md-6 position-relative">
                                                <label for="shipper" class="form-label">Shipping Line</label>
                                                <input type="text" class="form-control" id="shipper" name="shipper" autocomplete="off">
                                                <ul id="shipperList" class="list-group position-absolute w-100" style="z-index: 1000; display: none; max-height: 220px; overflow-y: auto;"></ul>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="eir_out" class="form-label">EIR Out</label>
                                                <input type="text" class="form-control" id="eir_out" name="eir_out">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="eir_outDate" class="form-label">EIR Out Date</label>
                                                <input type="text" class="form-control" id="eir_outDate" name="eir_outDate" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="eir_outTime" class="form-label">EIR Out Time</label>
                                                <input type="text" class="form-control" id="eir_outTime" name="eir_outTime" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="eir_in" class="form-label">EIR In</label>
                                                <input type="text" class="form-control" id="eir_in" name="eir_in">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="eir_inDate" class="form-label">EIR In Date</label>
                                                <input type="text" class="form-control" id="eir_inDate" name="eir_inDate" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                            </div>
                                            <div class="col-md-6 position-relative">
                                                <label for="pullout_location" class="form-label">Pull Out Location</label>
                                                <input type="text" class="form-control" id="pullout_location" name="pullout_location" autocomplete="off">
                                                <ul id="pulloutLocationList" class="list-group position-absolute w-100" style="z-index: 1000; display: none; max-height: 220px; overflow-y: auto;"></ul>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="pullout_date" class="form-label">Pull Out Date</label>
                                                <input type="text" class="form-control" id="pullout_date" name="pullout_date" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="pullout_time" class="form-label">Pull Out Time</label>
                                                <input type="text" class="form-control" id="pullout_time" name="pullout_time" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM">
                                            </div>
                                            <div class="col-md-6 position-relative">
                                                <label for="delivered_to" class="form-label">Delivery Location</label>
                                                <input type="text" class="form-control" id="delivered_to" name="delivered_to">
                                                <ul id="deliveredToList" class="list-group position-absolute w-100" style="z-index: 1000; display: none; max-height: 220px; overflow-y: auto;"></ul>
                                            </div>
                                            <div class="col-md-6 position-relative">
                                                <label for="return_location" class="form-label">Return Location</label>
                                                <input type="text" class="form-control" id="return_location" name="return_location">
                                                <ul id="returnLocationList" class="list-group position-absolute w-100" style="z-index: 1000; display: none; max-height: 220px; overflow-y: auto;"></ul>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="size" class="form-label">Size</label>
                                                <input type="text" class="form-control" id="size" name="size">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="commodity" class="form-label">Commodity</label>
                                                <input type="text" class="form-control" id="commodity" name="commodity">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="slp_no" class="form-label">SLPS No.</label>
                                                <input type="text" class="form-control" id="slp_no" name="slp_no">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="destination" class="form-label">Destination</label>
                                                <input type="text" class="form-control" id="destination" name="destination">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="gs" class="form-label">Genset No.</label>
                                                <input type="text" class="form-control" id="gs" name="gs">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="genset_hr_meter_start" class="form-label">Hour Meter Start</label>
                                                <input type="text" class="form-control" id="genset_hr_meter_start" name="genset_hr_meter_start">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="genset_hr_meter_end" class="form-label">Hour Meter End</label>
                                                <input type="text" class="form-control" id="genset_hr_meter_end" name="genset_hr_meter_end">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="kms" class="form-label">KMS</label>
                                                <input type="text" class="form-control" id="kms" name="kms">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="booking" class="form-label">Booking</label>
                                                <input type="text" class="form-control" id="booking" name="booking">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="shipment_no" class="form-label">Shipment No.</label>
                                                <input type="text" class="form-control" id="shipment_no" name="shipment_no">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="vessel_name" class="form-label">Vessel</label>
                                                <input type="text" class="form-control" id="vessel_name" name="vessel_name">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="voyage_no" class="form-label">Voyage No.</label>
                                                <input type="text" class="form-control" id="voyage_no" name="voyage_no">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="emdr_no" class="form-label">EMDR No.</label>
                                                <input type="text" class="form-control" id="emdr_no" name="emdr_no">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="date_unloaded" class="form-label">Date of Unloading</label>
                                                <input type="text" class="form-control" id="date_unloaded" name="date_unloaded" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="time_unloaded" class="form-label">Time of Unloading</label>
                                                <input type="text" class="form-control" id="time_unloaded" name="time_unloaded" data-manual-time="true" inputmode="numeric" autocomplete="off" placeholder="HHMM">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="seal" class="form-label">Seal</label>
                                                <input type="text" class="form-control" id="seal" name="seal">
                                            </div>

                                            <!-- Loaded Import / Empty Export Section -->
                                            <div id="loadedExportSection" class="col-12">
                                                <hr class="my-3">
                                                <h6 class="fw-bold mb-3">LOADED</h6>
                                                <div class="row g-3">
                                                    <div class="col-md-6" hidden>
                                                        <label for="segment" class="form-label">Segment - Loaded</label>
                                                        <input type="text" class="form-control" id="segment" name="segment">
                                                    </div>
                                                    <div class="col-md-6" hidden>
                                                        <label for="activity" class="form-label">Activity - Loaded</label>
                                                        <input type="text" class="form-control" id="activity" name="activity">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="waybill" class="form-label">Trip Receipt No. - Loaded</label>
                                                        <input type="text" class="form-control" id="waybill" name="waybill" maxlength="6" inputmode="numeric">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="date_hauled" class="form-label">Date Hauled</label>
                                                        <input type="text" class="form-control" id="date_hauled" name="date_hauled" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                                    </div>
                                                    <div class="col-md-6 position-relative">
                                                        <label for="driver" class="form-label">Driver's Name (Loaded)</label>
                                                        <input type="text" class="form-control" id="driver" name="driver">
                                                        <ul id="driverList" class="list-group position-absolute w-100" style="z-index: 1000; display: none; max-height: 220px; overflow-y: auto;"></ul>
                                                        <input type="hidden" id="driver_idNumber" name="driver_idNumber">
                                                    </div>
                                                    <div class="col-md-6 position-relative">
                                                        <label for="truck" class="form-label">Prime Mover - Loaded</label>
                                                        <input type="text" class="form-control" id="truck" name="truck">
                                                        <ul id="truckList" class="list-group position-absolute w-100" style="z-index: 1000; display: none; max-height: 220px; overflow-y: auto;"></ul>
                                                    </div>
                                                    <div class="col-md-6 position-relative">
                                                        <label for="tr" class="form-label">Trailer No.</label>
                                                        <input type="text" class="form-control" id="tr" name="tr">
                                                        <ul id="trList" class="list-group position-absolute w-100" style="z-index: 1000; display: none; max-height: 220px; overflow-y: auto;"></ul>
                                                    </div>
                                                    <div class="col-md-6 position-relative">
                                                        <label for="tr2" class="form-label">Trailer No. - Loaded</label>
                                                        <input type="text" class="form-control" id="tr2" name="tr2">
                                                        <ul id="tr2List" class="list-group position-absolute w-100" style="z-index: 1000; display: none; max-height: 220px; overflow-y: auto;"></ul>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <label for="remarks" class="form-label">Remarks</label>
                                                        <textarea class="form-control" id="remarks" name="remarks" rows="2"></textarea>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Empty Import / Loaded Export Section -->
                                            <div id="emptyImportSection" class="col-12">
                                                <hr class="my-3">
                                                <h6 class="fw-bold mb-3">EMPTY</h6>
                                                <div class="row g-3">
                                                    <div class="col-md-6" hidden>
                                                        <label for="segment_empty" class="form-label">Segment - Empty</label>
                                                        <input type="text" class="form-control" id="segment_empty" name="segment_empty">
                                                    </div>
                                                    <div class="col-md-6" hidden>
                                                        <label for="activity_empty" class="form-label">Activity - Empty</label>
                                                        <input type="text" class="form-control" id="activity_empty" name="activity_empty">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="waybill_empty" class="form-label">Trip Receipt No. - Empty</label>
                                                        <input type="text" class="form-control" id="waybill_empty" name="waybill_empty" maxlength="6" inputmode="numeric">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="date_returned" class="form-label">Actual Date Returned</label>
                                                        <input type="text" class="form-control" id="date_returned" name="date_returned" data-manual-date="true" inputmode="numeric" autocomplete="off" placeholder="M/D or M/D/YYYY">
                                                    </div>
                                                    <div class="col-md-6 position-relative">
                                                        <label for="driver_return" class="form-label">Driver's Name (Empty)</label>
                                                        <input type="text" class="form-control" id="driver_return" name="driver_return">
                                                        <ul id="driverReturnList" class="list-group position-absolute w-100" style="z-index: 1000; display: none; max-height: 220px; overflow-y: auto;"></ul>
                                                        <input type="hidden" id="driver_return_idNumber" name="driver_return_idNumber">
                                                    </div>
                                                    <div class="col-md-6 position-relative">
                                                        <label for="truck_return" class="form-label">Prime Mover - Empty</label>
                                                        <input type="text" class="form-control" id="truck_return" name="truck_return">
                                                        <ul id="truckReturnList" class="list-group position-absolute w-100" style="z-index: 1000; display: none; max-height: 220px; overflow-y: auto;"></ul>
                                                    </div>
                                                    <div class="col-md-6 position-relative">
                                                        <label for="tr_empty" class="form-label">Trailer No. - Empty</label>
                                                        <input type="text" class="form-control" id="tr_empty" data-storage-field="tr">
                                                        <ul id="trEmptyList" class="list-group position-absolute w-100" style="z-index: 1000; display: none; max-height: 220px; overflow-y: auto;"></ul>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="type" class="form-label">Type</label>
                                                        <select class="form-select" id="type" name="type">
                                                            <option value="">-- Select Type --</option>
                                                            <option value="EXPORT">EXPORT</option>
                                                            <option value="IMPORT">IMPORT</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <label for="delivered_remarks" class="form-label">Remarks</label>
                                                        <textarea class="form-control" id="delivered_remarks" name="delivered_remarks" rows="2"></textarea>
                                                    </div>
                                                </div>
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
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">All Entries</h5>
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
                                                <th>Trip Receipt MTY</th>
                                                <th>Van</th>
                                                <th>Driver</th>
                                                <th>Driver 2</th>
                                                <th>Remarks</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $rows = $result->fetchAll(PDO::FETCH_ASSOC); if (count($rows) > 0): ?>
                                                <?php foreach ($rows as $row): ?>
                                                    <tr data-entry-id="<?php echo htmlspecialchars($row['entry_id']); ?>">
                                                        <td><?php echo htmlspecialchars($row['entry_id']); ?></td>
                                                        <td><?php echo htmlspecialchars(($row['date_hauled'] ?? '') !== '' ? $row['date_hauled'] : ($row['date_returned'] ?? '')); ?></td>
                                                        <td><?php echo htmlspecialchars($row['waybill'] ?? ''); ?></td>
                                                        <td><?php echo htmlspecialchars($row['waybill_empty'] ?? ''); ?></td>
                                                        <td><?php echo htmlspecialchars(trim(($row['van_alpha'] ?? '') . ' ' . ($row['van_number'] ?? '') . ' ' . ($row['van_name'] ?? ''))); ?></td>
                                                        <td><?php echo htmlspecialchars($row['driver'] ?? ''); ?></td>
                                                        <td><?php echo htmlspecialchars($row['driver_return'] ?? ''); ?></td>
                                                        <td><?php echo htmlspecialchars(($row['remarks'] ?? '') !== '' ? $row['remarks'] : ($row['delivered_remarks'] ?? '')); ?></td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <button type="button" class="btn btn-outline-primary edit-btn" data-id="<?php echo $row['entry_id']; ?>"><i class="bi bi-pencil"></i></button>
                                                                <button type="button" class="btn btn-outline-danger delete-btn" data-id="<?php echo $row['entry_id']; ?>"><i class="bi bi-trash"></i></button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr class="no-entries-row">
                                                    <td colspan="9" class="text-center text-muted">No entries found for today</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/DataTables/datatables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
        $(document).ready(function() {
            $("#entriesTable tbody tr.no-entries-row").remove();
            const entriesTable = $("#entriesTable").DataTable({
                order: [
                    [0, "desc"]
                ],
                language: {
                    emptyTable: "No entries found for selected date"
                }
            });

            const form = document.getElementById("dataEntryForm");
            const formGrid = form.querySelector(".row.g-3");
            const dataIdInput = document.getElementById("data_id");
            const statusSelect = document.getElementById("status");
            const loadedExportSection = document.getElementById("loadedExportSection");
            const emptyImportSection = document.getElementById("emptyImportSection");
            const selectedEntryDate = "<?php echo htmlspecialchars($selectedEntryDate, ENT_QUOTES); ?>";
            const todayEntryDate = "<?php echo date('Y-m-d'); ?>";

            const customerPhInput = document.getElementById("customer_ph");
            const customerList = document.getElementById("customerList");
            const ecsInput = document.getElementById("ecs");
            const vanAlphaInput = document.getElementById("van_alpha");
            const vanNumberInput = document.getElementById("van_number");
            const shipperInput = document.getElementById("shipper");
            const shipperList = document.getElementById("shipperList");
            const eirOutInput = document.getElementById("eir_out");
            const eirOutDateInput = document.getElementById("eir_outDate");
            const eirOutTimeInput = document.getElementById("eir_outTime");
            const eirInInput = document.getElementById("eir_in");
            const eirInDateInput = document.getElementById("eir_inDate");
            const pulloutLocationInput = document.getElementById("pullout_location");
            const pulloutLocationList = document.getElementById("pulloutLocationList");
            const pulloutDateInput = document.getElementById("pullout_date");
            const deliveredToInput = document.getElementById("delivered_to");
            const deliveredToList = document.getElementById("deliveredToList");
            const returnLocationInput = document.getElementById("return_location");
            const returnLocationList = document.getElementById("returnLocationList");
            const slpNoInput = document.getElementById("slp_no");
            const destinationInput = document.getElementById("destination");
            const gensetInput = document.getElementById("gs");
            const hourMeterStartInput = document.getElementById("genset_hr_meter_start");
            const hourMeterEndInput = document.getElementById("genset_hr_meter_end");
            const driverInput = document.getElementById("driver");
            const driverList = document.getElementById("driverList");
            const driverReturnInput = document.getElementById("driver_return");
            const driverReturnList = document.getElementById("driverReturnList");
            const truckInput = document.getElementById("truck");
            const truckList = document.getElementById("truckList");
            const truckReturnInput = document.getElementById("truck_return");
            const truckReturnList = document.getElementById("truckReturnList");
            const trInput = document.getElementById("tr");
            const trList = document.getElementById("trList");
            const tr2Input = document.getElementById("tr2");
            const tr2List = document.getElementById("tr2List");
            const trEmptyInput = document.getElementById("tr_empty");
            const trEmptyList = document.getElementById("trEmptyList");
            const loadedSectionRow = loadedExportSection.querySelector(".row.g-3");
            const emptySectionRow = emptyImportSection.querySelector(".row.g-3");

            const dryVanCustomers = [
                "CITIHARDWARE IMPORTS",
                "CITIHARDWARE DOMESTIC",
                "PSACC DOMESTIC",
                "TPD DRYVAN IMPORT",
                "TPD DRYVAN EXPORT",
                "ECOSSENTIAL - IMPORT",
                "PHIL CEMENT",
                "NOVOCOCONUT - IMPORT",
                "NP CHANGS - IMPORT",
                "SOLARVISTA - IMPORT",
                "FRANKLIN BAKER - IMPORT",
                "EYE CARGO - IMPORT",
                "PHIL JDU - IMPORT",
                "BIO PULP - IMPORT",
                "SOUTHERN HARVEST - IMPORT",
                "HEADSPORT - IMPORT",
                "AGRI EXIM - IMPORT",
                "SOLARIS - IMPORT",
                "HURRAH AGRO - IMPORT",
                "ECOSSENTIAL - EXPORT",
                "NOVOCOCONUT - EXPORT",
                "FRANKLIN BAKER - EXPORT",
                "EYE CARGO - EXPORT",
                "PHIL JDU - EXPORT",
                "BIO PULP - EXPORT",
                "SOUTHERN HARVEST - EXPORT",
                "HEADSPORT - EXPORT",
                "AGRI EXIM - EXPORT",
                "SOLARIS - EXPORT",
                "SAGREX - EXPORT",
                "FIRST PANABO TROPICAL FOOD CORP. - EXPORT"
            ];
            const importCustomers = new Set([
                "CITIHARDWARE IMPORTS",
                "TPD DRYVAN IMPORT",
                "ECOSSENTIAL - IMPORT",
                "PHIL CEMENT",
                "NOVOCOCONUT - IMPORT",
                "NP CHANGS - IMPORT",
                "SOLARVISTA - IMPORT",
                "FRANKLIN BAKER - IMPORT",
                "EYE CARGO - IMPORT",
                "PHIL JDU - IMPORT",
                "BIO PULP - IMPORT",
                "SOUTHERN HARVEST - IMPORT",
                "HEADSPORT - IMPORT",
                "AGRI EXIM - IMPORT",
                "SOLARIS - IMPORT",
                "HURRAH AGRO - IMPORT"
            ]);
            const exportCustomers = new Set([
                "CITIHARDWARE DOMESTIC",
                "PSACC DOMESTIC",
                "TPD DRYVAN EXPORT",
                "ECOSSENTIAL - EXPORT",
                "NOVOCOCONUT - EXPORT",
                "FRANKLIN BAKER - EXPORT",
                "EYE CARGO - EXPORT",
                "PHIL JDU - EXPORT",
                "BIO PULP - EXPORT",
                "SOUTHERN HARVEST - EXPORT",
                "HEADSPORT - EXPORT",
                "AGRI EXIM - EXPORT",
                "SOLARIS - EXPORT",
                "SAGREX - EXPORT",
                "FIRST PANABO TROPICAL FOOD CORP. - EXPORT"
            ]);
            const importTopFields = ["ecs", "van_alpha", "van_number", "shipper", "eir_out", "eir_outDate", "eir_in", "eir_inDate", "pullout_location", "delivered_to", "return_location", "date_unloaded", "shipment_no", "booking", "size"];
            const exportTopFields = ["ecs", "van_alpha", "van_number", "shipper", "size", "eir_out", "eir_outDate", "eir_in", "eir_inDate", "pullout_location", "delivered_to", "return_location"];
            const customerConfigs = {
                "CITIHARDWARE IMPORTS": {
                    topFields: importTopFields,
                    loadedFields: ["waybill", "date_hauled", "truck", "tr", "driver"],
                    emptyFields: ["waybill_empty", "date_returned", "truck_return", "driver_return"],
                    labels: {
                        size: "Volume Size",
                        date_returned: "Returned Date"
                    }
                },
                "TPD DRYVAN IMPORT": {
                    topFields: ["ecs", "van_alpha", "van_number", "shipper", "eir_out", "eir_outDate", "eir_in", "eir_inDate", "pullout_location", "pullout_date", "pullout_time", "delivered_to", "return_location", "date_unloaded", "time_unloaded", "booking", "size", "commodity"],
                    loadedFields: ["waybill", "date_hauled", "truck", "tr", "driver"],
                    emptyFields: ["waybill_empty", "date_returned", "truck_return", "driver_return"],
                    labels: {
                        size: "Volume Size",
                        date_returned: "Returned Date"
                    }
                },
                "CITIHARDWARE DOMESTIC": {
                    topFields: [...exportTopFields, "slp_no", "destination", "vessel_name", "voyage_no"],
                    // Convention: tr = Loaded trailer, tr2 = Empty trailer.
                    loadedFields: ["waybill", "date_hauled", "truck", "tr", "driver"],
                    emptyFields: ["waybill_empty", "pullout_date", "truck_return", "tr2", "driver_return"],
                    labels: {
                        size: "Van Size - 20FT or 40FT",
                        tr: "Trailer No.",
                        tr2: "Trailer No. - Empty"
                    }
                },
                "PSACC DOMESTIC": {
                    topFields: [...exportTopFields, "slp_no", "destination", "vessel_name", "voyage_no", "emdr_no"],
                    // Convention: tr = Loaded trailer, tr2 = Empty trailer.
                    loadedFields: ["waybill", "date_hauled", "truck", "tr", "driver"],
                    emptyFields: ["waybill_empty", "pullout_date", "truck_return", "tr2", "driver_return"],
                    labels: {
                        size: "Van Size - 20FT or 40FT",
                        tr: "Trailer No.",
                        tr2: "Trailer No. - Empty"
                    }
                },
                "TPD DRYVAN EXPORT": {
                    topFields: ["ecs", "van_alpha", "van_number", "shipper", "size", "commodity", "eir_out", "eir_outDate", "eir_in", "eir_inDate", "pullout_location", "pullout_date", "pullout_time", "delivered_to", "return_location", "time_unloaded"],
                    // Convention for TPD export: tr = Loaded trailer, tr2 = Empty trailer.
                    loadedFields: ["waybill", "date_hauled", "truck", "tr", "driver"],
                    emptyFields: ["waybill_empty", "truck_return", "tr2", "driver_return"],
                    labels: {
                        size: "Van Size - 20FT or 40FT",
                        tr: "Trailer No.",
                        tr2: "Trailer No. - Empty"
                    }
                }
            };
            [
                "ECOSSENTIAL - IMPORT",
                "PHIL CEMENT",
                "NOVOCOCONUT - IMPORT",
                "NP CHANGS - IMPORT",
                "SOLARVISTA - IMPORT",
                "FRANKLIN BAKER - IMPORT",
                "EYE CARGO - IMPORT",
                "PHIL JDU - IMPORT",
                "BIO PULP - IMPORT",
                "SOUTHERN HARVEST - IMPORT",
                "HEADSPORT - IMPORT",
                "AGRI EXIM - IMPORT",
                "SOLARIS - IMPORT",
                "HURRAH AGRO - IMPORT"
            ].forEach((customer) => {
                customerConfigs[customer] = {
                    topFields: [...importTopFields.filter(field => field !== "shipment_no")],
                    loadedFields: ["waybill", "date_hauled", "truck", "tr", "driver"],
                    emptyFields: ["waybill_empty", "date_returned", "truck_return", "driver_return"],
                    labels: {
                        size: "Volume Size",
                        date_returned: "Returned Date"
                    }
                };
            });
            [
                "ECOSSENTIAL - EXPORT",
                "NOVOCOCONUT - EXPORT",
                "FRANKLIN BAKER - EXPORT",
                "EYE CARGO - EXPORT",
                "PHIL JDU - EXPORT",
                "BIO PULP - EXPORT",
                "SOUTHERN HARVEST - EXPORT",
                "HEADSPORT - EXPORT",
                "AGRI EXIM - EXPORT",
                "SOLARIS - EXPORT",
                "SAGREX - EXPORT",
                "FIRST PANABO TROPICAL FOOD CORP. - EXPORT"
            ].forEach((customer) => {
                customerConfigs[customer] = {
                    topFields: exportTopFields,
                    // Convention: tr = Loaded trailer, tr2 = Empty trailer.
                    loadedFields: ["waybill", "date_hauled", "truck", "tr", "driver"],
                    emptyFields: ["waybill_empty", "pullout_date", "truck_return", "tr2", "driver_return"],
                    labels: {
                        size: "Van Size - 20FT or 40FT",
                        tr: "Trailer No.",
                        tr2: "Trailer No. - Empty"
                    }
                };
            });

            const controlledDryVanFields = ["ecs", "van_alpha", "van_number", "shipper", "size", "commodity", "slp_no", "destination", "eir_out", "eir_outDate", "eir_outTime", "eir_in", "eir_inDate", "pullout_location", "delivered_to", "return_location", "gs", "genset_hr_meter_start", "genset_hr_meter_end", "date_unloaded", "shipment_no", "booking", "vessel_name", "voyage_no", "emdr_no", "kms", "seal", "waybill", "date_hauled", "truck", "tr", "tr2", "tr_empty", "driver", "waybill_empty", "pullout_date", "pullout_time", "date_returned", "truck_return", "driver_return", "departure_time", "arrival_time", "time_unloaded", "remarks", "type", "delivered_remarks"];
            const defaultLabels = {
                van_alpha: "Van Alpha",
                van_number: "Van Numeric",
                shipper: "Shipping Line",
                pullout_location: "Pull Out Location",
                pullout_date: "Pull Out Date",
                pullout_time: "Pull Out Time",
                eir_out: "EIR Out",
                eir_outDate: "EIR Out Date",
                eir_inDate: "EIR In Date",
                date_unloaded: "Date of Unloading",
                time_unloaded: "Time of Unloading",
                shipment_no: "Shipment No.",
                booking: "BL No.",
                vessel_name: "Vessel",
                voyage_no: "Voyage No.",
                emdr_no: "EMDR No.",
                size: "Van Size - 20FT or 40FT",
                commodity: "Commodity",
                slp_no: "SLPS No.",
                destination: "Destination",
                delivered_to: "Delivery Location",
                return_location: "Return Location",
                gs: "Genset No.",
                genset_hr_meter_start: "Hour Meter Start",
                genset_hr_meter_end: "Hour Meter End",
                tr: "Trailer No.",
                tr2: "Trailer No. - Loaded",
                truck: "Prime Mover - Loaded",
                truck_return: "Prime Mover - Empty",
                driver: "Driver's Name (Loaded)",
                driver_return: "Driver's Name (Empty)",
                segment: "Segment - Loaded",
                activity: "Activity - Loaded",
                segment_empty: "Segment - Empty",
                activity_empty: "Activity - Empty",
                waybill: "Waybill No.",
                waybill_empty: "Waybill No.",
                date_hauled: "Date Hauled",
                date_returned: "Returned Date",
                eir_in: "EIR In"
            };

            let allLocations = [];
            let allShippers = [];
            let allDrivers = [];
            let allTrucks = [];
            let allTrailers = [];
            form.querySelectorAll('input[type="text"]').forEach(function (input) {
                input.addEventListener('input', function () {
                    const pos = this.selectionStart;
                    this.value = this.value.toUpperCase();
                    this.setSelectionRange(pos, pos);
                });
            });

            // Fetch lookup data
            fetch("php/fetch/get_locations.php")
                .then(res => res.json())
                .then(data => {
                    allLocations = Array.isArray(data) ? data : [];
                })
                .catch(() => {
                    allLocations = [];
                });

            fetch("php/fetch/get_shippers.php")
                .then(res => res.json())
                .then(data => {
                    allShippers = Array.isArray(data) ? data : [];
                })
                .catch(() => {
                    allShippers = [];
                });

            fetch("php/fetch/get_drivers.php")
                .then(res => res.json())
                .then(data => {
                    allDrivers = Array.isArray(data) ? data : [];
                })
                .catch(() => {
                    allDrivers = [];
                });

            fetch("php/fetch/get_trucks.php")
                .then(res => res.json())
                .then(data => {
                    allTrucks = Array.isArray(data) ? data : [];
                })
                .catch(() => {
                    allTrucks = [];
                });

            fetch("php/fetch/get_trailers.php")
                .then(res => res.json())
                .then(data => {
                    allTrailers = Array.isArray(data) ? data : [];
                })
                .catch(() => {
                    allTrailers = [];
                });

            // Helper function to hide dropdown
            function hideDropdown(listElem) {
                if (listElem) {
                    listElem.style.display = "none";
                    listElem.innerHTML = "";
                }
            }

            // Helper function to show dropdown
            function showDropdown(listElem) {
                if (listElem) {
                    listElem.style.maxHeight = "240px";
                    listElem.style.overflowY = "auto";
                    listElem.style.overflowX = "hidden";
                    listElem.style.display = "block";
                }
            }

            // Generic filter function
            function filterAndDisplay(input, listElem, dataArray, getDisplay, onSelect) {
                const searchVal = input.value.trim().toLowerCase();
                listElem.innerHTML = "";

                if (!searchVal) {
                    hideDropdown(listElem);
                    return;
                }

                const filtered = dataArray.filter(item =>
                    (getDisplay(item) || "").toLowerCase().includes(searchVal)
                );

                if (filtered.length === 0) {
                    hideDropdown(listElem);
                    return;
                }

                filtered.forEach((item, index) => {
                    const li = document.createElement("li");
                    li.className = "list-group-item list-group-item-action";
                    const display = getDisplay(item);
                    li.textContent = display;
                    li.dataset.pickValue = display;
                    if (index === 0) li.classList.add("active-suggestion");
                    li.addEventListener("mousedown", (event) => {
                        event.preventDefault();
                        onSelect(item);
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
                        items[index].scrollIntoView({
                            block: "nearest"
                        });
                    }
                }
            }

            // Customer PH
            customerPhInput.addEventListener("input", function() {
                filterAndDisplay(this, customerList, dryVanCustomers,
                    customer => customer || "",
                    item => {
                        customerPhInput.value = item || "";
                        applyCustomerFormRules();
                    }
                );
                applyCustomerFormRules();
            });

            deliveredToInput.addEventListener("input", function() {
                filterAndDisplay(this, deliveredToList, allLocations,
                    loc => loc.location_name || "",
                    item => {
                        deliveredToInput.value = item.location_name;
                    }
                );
            });

            pulloutLocationInput.addEventListener("input", function() {
                filterAndDisplay(this, pulloutLocationList, allLocations,
                    loc => loc.location_name || "",
                    item => {
                        pulloutLocationInput.value = item.location_name;
                    }
                );
            });

            returnLocationInput.addEventListener("input", function() {
                filterAndDisplay(this, returnLocationList, allLocations,
                    loc => loc.location_name || "",
                    item => {
                        returnLocationInput.value = item.location_name;
                    }
                );
            });

            shipperInput.addEventListener("input", function() {
                filterAndDisplay(this, shipperList, allShippers,
                    shipper => shipper.shipper || "",
                    item => {
                        shipperInput.value = item.shipper || "";
                    }
                );
            });

            // Driver (Loaded Export)
            driverInput.addEventListener("input", function() {
                document.getElementById("driver_idNumber").value = "";
                filterAndDisplay(this, driverList, allDrivers,
                    drv => drv.name || "",
                    item => {
                        driverInput.value = item.name || "";
                        document.getElementById("driver_idNumber").value = item.id || "";
                    }
                );
            });

            // Driver (Empty Import)
            driverReturnInput.addEventListener("input", function() {
                document.getElementById("driver_return_idNumber").value = "";
                filterAndDisplay(this, driverReturnList, allDrivers,
                    drv => drv.name || "",
                    item => {
                        driverReturnInput.value = item.name || "";
                        document.getElementById("driver_return_idNumber").value = item.id || "";
                    }
                );
            });

            // Truck (Loaded Export)
            truckInput.addEventListener("input", function() {
                filterAndDisplay(this, truckList, allTrucks,
                    trk => trk || "",
                    item => {
                        truckInput.value = item || "";
                    }
                );
            });

            // Truck (Empty Import)
            truckReturnInput.addEventListener("input", function() {
                filterAndDisplay(this, truckReturnList, allTrucks,
                    trk => trk || "",
                    item => {
                        truckReturnInput.value = item || "";
                    }
                );
            });

            // Trailer
            trInput.addEventListener("input", function() {
                filterAndDisplay(this, trList, allTrailers,
                    trl => trl.trailer_name || "",
                    item => {
                        trInput.value = item.trailer_name;
                    }
                );
            });

            tr2Input.addEventListener("input", function() {
                filterAndDisplay(this, tr2List, allTrailers,
                    trl => trl.trailer_name || "",
                    item => {
                        tr2Input.value = item.trailer_name;
                    }
                );
            });

            trEmptyInput.addEventListener("input", function() {
                filterAndDisplay(this, trEmptyList, allTrailers,
                    trl => trl.trailer_name || "",
                    item => {
                        trEmptyInput.value = item.trailer_name;
                    }
                );
            });

            // Close dropdowns when clicking outside
            document.addEventListener("mousedown", function(event) {
                if (!event.target.closest(".position-relative")) {
                    [customerList, deliveredToList, pulloutLocationList, returnLocationList, shipperList, driverList, driverReturnList, truckList, truckReturnList, trList, tr2List, trEmptyList].forEach(hideDropdown);
                }
            });

            attachKeyboardNav(customerPhInput, customerList, (value) => {
                customerPhInput.value = value;
                applyCustomerFormRules();
            });

            attachKeyboardNav(deliveredToInput, deliveredToList, (value) => {
                deliveredToInput.value = value;
            });

            attachKeyboardNav(pulloutLocationInput, pulloutLocationList, (value) => {
                pulloutLocationInput.value = value;
            });

            attachKeyboardNav(returnLocationInput, returnLocationList, (value) => {
                returnLocationInput.value = value;
            });

            attachKeyboardNav(shipperInput, shipperList, (value) => {
                shipperInput.value = value;
            });

            attachKeyboardNav(driverInput, driverList, (value) => {
                driverInput.value = value;
                const selected = allDrivers.find(item => item.name === value);
                document.getElementById("driver_idNumber").value = selected ? selected.id : "";
            });

            attachKeyboardNav(driverReturnInput, driverReturnList, (value) => {
                driverReturnInput.value = value;
                const selected = allDrivers.find(item => item.name === value);
                document.getElementById("driver_return_idNumber").value = selected ? selected.id : "";
            });

            attachKeyboardNav(truckInput, truckList, (value) => {
                truckInput.value = value;
            });

            attachKeyboardNav(truckReturnInput, truckReturnList, (value) => {
                truckReturnInput.value = value;
            });

            attachKeyboardNav(trInput, trList, (value) => {
                trInput.value = value;
            });

            attachKeyboardNav(tr2Input, tr2List, (value) => {
                tr2Input.value = value;
            });

            attachKeyboardNav(trEmptyInput, trEmptyList, (value) => {
                trEmptyInput.value = value;
            });

            function getFieldContainer(fieldName) {
                const input = form.elements[fieldName] || document.getElementById(fieldName);
                if (!input) return null;
                return input.closest(".col-md-6, .col-md-12, .col-12");
            }

            function setFieldVisible(fieldName, visible) {
                const container = getFieldContainer(fieldName);
                if (!container) return;
                container.hidden = !visible;
                const input = form.elements[fieldName] || document.getElementById(fieldName);
                if (input) {
                    input.disabled = !visible;
                    input.required = visible;
                }
            }

            function setFieldLabel(fieldName, text) {
                const input = form.elements[fieldName] || document.getElementById(fieldName);
                if (!input) return;
                const label = form.querySelector(`label[for="${fieldName}"]`);
                if (label) {
                    label.textContent = text;
                }
            }

            function updateStatusFromCustomer(customer) {
                if (importCustomers.has(customer)) {
                    statusSelect.value = "Loaded Import / Empty Export";
                } else if (exportCustomers.has(customer)) {
                    statusSelect.value = "Empty Import / Loaded Export";
                }
            }

            function applyCustomerFormRules() {
                const customer = customerPhInput.value.trim().toUpperCase();
                const config = customerConfigs[customer] || {
                    topFields: [],
                    loadedFields: [],
                    emptyFields: [],
                    labels: {}
                };
                const topFields = config.topFields || [];
                const loadedFields = new Set(config.loadedFields || []);
                const emptyFields = new Set(config.emptyFields || []);

                updateStatusFromCustomer(customer);
                Object.entries(defaultLabels).forEach(([fieldName, labelText]) => {
                    setFieldLabel(fieldName, (config.labels && config.labels[fieldName]) || labelText);
                });

                if (exportCustomers.has(customer)) {
                    formGrid.insertBefore(emptyImportSection, loadedExportSection);
                } else {
                    formGrid.insertBefore(loadedExportSection, emptyImportSection);
                }

                controlledDryVanFields.forEach(fieldName => {
                    setFieldVisible(fieldName, false);
                });

                loadedExportSection.hidden = loadedFields.size === 0;
                emptyImportSection.hidden = emptyFields.size === 0;

                topFields.forEach((fieldName) => {
                    const container = getFieldContainer(fieldName);
                    if (container) {
                        formGrid.insertBefore(container, exportCustomers.has(customer) ? emptyImportSection : loadedExportSection);
                    }
                    setFieldVisible(fieldName, true);
                });

                ["waybill", "date_hauled", "driver", "truck", "tr", "tr2"].forEach((fieldName) => {
                    // tr2 can belong to the Empty leg (TPD DRYVAN EXPORT); when it does,
                    // the empty-section handler below owns its placement/visibility.
                    if (fieldName === "tr2" && emptyFields.has("tr2")) {
                        return;
                    }
                    const sectionVisible = loadedFields.has(fieldName);
                    const container = getFieldContainer(fieldName);
                    if (container) {
                        loadedSectionRow.appendChild(container);
                    }
                    setFieldVisible(fieldName, sectionVisible);
                });

                ["waybill_empty", "pullout_date", "date_returned", "driver_return", "truck_return", "tr"].forEach((fieldName) => {
                    // A field promoted into the top section (e.g. pullout_date for TPD)
                    // must not be pulled back into the Empty section here.
                    if (topFields.includes(fieldName)) {
                        return;
                    }
                    const sectionVisible = emptyFields.has(fieldName);
                    if (fieldName !== "tr") {
                        const container = getFieldContainer(fieldName);
                        if (container) {
                            emptySectionRow.appendChild(container);
                        }
                        setFieldVisible(fieldName, sectionVisible);
                    }
                });

                setFieldVisible("tr_empty", emptyFields.has("tr"));
                setFieldVisible("tr", loadedFields.has("tr"));
                // TPD DRYVAN EXPORT keeps the Empty-leg trailer in tr2; render that
                // input inside the Empty section when the config asks for it.
                if (emptyFields.has("tr2")) {
                    const tr2Container = getFieldContainer("tr2");
                    if (tr2Container) {
                        emptySectionRow.appendChild(tr2Container);
                    }
                    setFieldVisible("tr2", true);
                }
                const emptyWaybillContainer = getFieldContainer("waybill_empty");
                if (emptyWaybillContainer) {
                    emptySectionRow.insertBefore(emptyWaybillContainer, emptySectionRow.firstChild);
                }
                if (emptyFields.has("tr")) {
                    trEmptyInput.value = trInput.value;
                }
                if (loadedFields.has("tr")) {
                    trInput.value = trEmptyInput.value || trInput.value;
                }
            }

            function convertDateToDatabase(displayDate) {
                if (!displayDate || displayDate === "") return "";

                const parts = displayDate.trim().split("/");
                if (parts.length < 2 || parts.length > 3) return displayDate;

                let month = parts[0].padStart(2, "0");
                let day = parts[1].padStart(2, "0");
                let year = parts[2] || new Date().getFullYear().toString();

                if (year.length === 2) {
                    year = "20" + year;
                }

                if (isNaN(month) || isNaN(day) || isNaN(year)) return displayDate;

                return `${year}-${month}-${day}`;
            }

            function convertTimeToDatabase(displayTime) {
                if (!displayTime || displayTime === "") return "";

                const value = displayTime.trim();
                if (/^\d{2}:\d{2}(:\d{2})?$/.test(value)) {
                    return value.length === 5 ? `${value}:00` : value;
                }

                const compact = value.replace(/[^0-9]/g, "");
                if (compact.length === 3 || compact.length === 4) {
                    const padded = compact.padStart(4, "0");
                    return `${padded.slice(0, 2)}:${padded.slice(2, 4)}:00`;
                }

                return displayTime;
            }

            function escapeHtml(value) {
                return $("<div>").text(value ?? "").html();
            }

            function formatDateForDisplay(dbDate) {
                if (!dbDate || dbDate === "") return "";

                const normalizedDate = String(dbDate).slice(0, 10);
                const match = normalizedDate.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                if (!match) return dbDate;

                return `${match[2]}/${match[3]}/${match[1]}`;
            }

            function formatTimeForDisplay(dbTime) {
                if (!dbTime || dbTime === "") return "";

                const match = String(dbTime).match(/^(\d{2}):(\d{2})/);
                if (!match) return dbTime;

                return `${match[1]}:${match[2]}`;
            }

            function normalizeManualDateInput(displayDate) {
                if (!displayDate || displayDate === "") return "";

                const parts = displayDate.trim().split("/");
                if (parts.length < 2 || parts.length > 3) return displayDate;

                let month = parts[0].trim();
                let day = parts[1].trim();
                let year = (parts[2] || "").trim();

                if (!month || !day) return displayDate;
                if (isNaN(month) || isNaN(day)) return displayDate;

                month = month.padStart(2, "0");
                day = day.padStart(2, "0");

                if (!year) {
                    year = new Date().getFullYear().toString();
                } else if (isNaN(year)) {
                    return displayDate;
                } else if (year.length === 2) {
                    year = "20" + year;
                }

                return `${month}/${day}/${year}`;
            }

            function buildEntryRowData(record) {
                const displayDate = record.date_hauled || record.date_returned || "";
                const displayVan = `${record.van_alpha || ""} ${record.van_number || ""} ${record.van_name || ""}`.trim();
                const displayRemarks = record.remarks || record.delivered_remarks || "";
                const entryId = escapeHtml(record.entry_id || "");

                return [
                    entryId,
                    escapeHtml(displayDate),
                    escapeHtml(record.waybill || ""),
                    escapeHtml(record.waybill_empty || ""),
                    escapeHtml(displayVan),
                    escapeHtml(record.driver || ""),
                    escapeHtml(record.driver_return || ""),
                    escapeHtml(displayRemarks),
                    `<div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary edit-btn" data-id="${entryId}"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-outline-danger delete-btn" data-id="${entryId}"><i class="bi bi-trash"></i></button>
                    </div>`
                ];
            }

            function upsertTableRow(record) {
                if (!record || !record.entry_id) {
                    return;
                }

                const rowData = buildEntryRowData(record);
                const existingRow = $(`#entriesTable tbody tr[data-entry-id="${record.entry_id}"]`);

                if (existingRow.length) {
                    const rowApi = entriesTable.row(existingRow);
                    rowApi.data(rowData).draw(false);
                    $(rowApi.node()).attr("data-entry-id", record.entry_id);
                } else if (selectedEntryDate === todayEntryDate) {
                    const newRow = entriesTable.row.add(rowData).draw(false).node();
                    $(newRow).attr("data-entry-id", record.entry_id);
                }

                entriesTable.order([0, "desc"]).draw(false);
            }

            function removeTableRow(entryId) {
                const existingRow = $(`#entriesTable tbody tr[data-entry-id="${entryId}"]`);
                if (existingRow.length) {
                    entriesTable.row(existingRow).remove().draw(false);
                }
            }

            document.querySelectorAll("[data-manual-date='true']").forEach((input) => {
                input.addEventListener("blur", function() {
                    this.value = normalizeManualDateInput(this.value);
                });
            });

            document.querySelectorAll("[data-manual-time='true']").forEach((input) => {
                input.addEventListener("blur", function() {
                    const converted = convertTimeToDatabase(this.value);
                    this.value = formatTimeForDisplay(converted);
                });
            });

            form.addEventListener("reset", function() {
                window.setTimeout(() => {
                    dataIdInput.value = "";
                    document.getElementById("driver_idNumber").value = "";
                    document.getElementById("driver_return_idNumber").value = "";
                    applyCustomerFormRules();
                }, 0);
            });

            applyCustomerFormRules();

            // Form submission
            form.addEventListener("submit", function(e) {
                e.preventDefault();

                // Validate status is selected
                if (!statusSelect.value) {
                    Swal.fire("Error!", "Please select a status.", "error");
                    return;
                }
                if (vanAlphaInput.value && !/^[A-Za-z]{4}$/.test(vanAlphaInput.value)) {
                    Swal.fire("Invalid format", "Van Alpha must be exactly 4 letters.", "warning");
                    return;
                }
                if (vanNumberInput.value && !/^\d{7}$/.test(vanNumberInput.value)) {
                    Swal.fire("Invalid format", "Van Number must be exactly 7 digits.", "warning");
                    return;
                }
                const waybillVal = document.getElementById("waybill") ? document.getElementById("waybill").value : "";
                if (waybillVal && !/^\d{6}$/.test(waybillVal)) {
                    Swal.fire("Invalid format", "Waybill must be exactly 6 digits.", "warning");
                    return;
                }
                const waybillEmptyVal = document.getElementById("waybill_empty") ? document.getElementById("waybill_empty").value : "";
                if (waybillEmptyVal && !/^\d{6}$/.test(waybillEmptyVal)) {
                    Swal.fire("Invalid format", "Waybill (Empty) must be exactly 6 digits.", "warning");
                    return;
                }
                // Pull-Out / Return must be registered locations (they drive the billing rate
                // matrix). Only enforced when the location list actually loaded; the server
                // re-checks regardless.
                if (Array.isArray(allLocations) && allLocations.length) {
                    const known = new Set(allLocations.map(l => String(l.location_name || "").trim().toUpperCase()));
                    const badLoc = [["Pull Out Location", pulloutLocationInput.value], ["Return Location", returnLocationInput.value]]
                        .filter(([, v]) => String(v || "").trim() !== "" && !known.has(String(v).trim().toUpperCase()));
                    if (badLoc.length) {
                        const list = badLoc.map(([lbl, v]) => `${lbl} “${v}”`).join(", ");
                        Swal.fire("Unregistered location", `${list} is not in the location list. Pick an existing location, or add it in Master Data → Locations first.`, "warning");
                        return;
                    }
                }

                const formData = new FormData(form);
                if (!trEmptyInput.disabled) {
                    formData.set("tr", trEmptyInput.value);
                }

                ["date_hauled", "date_unloaded", "date_returned", "eir_outDate", "eir_inDate", "pullout_date"].forEach((field) => {
                    const currentValue = formData.get(field);
                    if (typeof currentValue === "string" && currentValue !== "") {
                        formData.set(field, convertDateToDatabase(currentValue));
                    }
                });
                ["eir_outTime", "departure_time", "arrival_time", "time_unloaded", "pullout_time"].forEach((field) => {
                    const currentValue = formData.get(field);
                    if (typeof currentValue === "string" && currentValue !== "") {
                        formData.set(field, convertTimeToDatabase(currentValue));
                    }
                });
                const isUpdate = Boolean(dataIdInput.value);
                formData.append("action", isUpdate ? "update-dry-van" : "add-dry-van");

                fetch(isUpdate ? "php/update/dry_van.php" : "php/insert/dry_van.php", {
                        method: "POST",
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            upsertTableRow(data.record || {});
                            Swal.fire("Success!", data.message, "success").then(() => {
                                form.reset();
                            });
                        } else {
                            Swal.fire("Error!", data.message, "error");
                        }
                    })
                    .catch(err => Swal.fire("Error!", "An error occurred while saving.", "error"));
            });

            // Edit button
            $(document).on("click", ".edit-btn", function() {
                const id = $(this).data("id");
                fetch("php/fetch/get_dry_van.php?id=" + id)
                    .then(res => res.json())
                    .then(record => {
                        if (record) {
                            dataIdInput.value = record.entry_id;
                            document.getElementById("status").value = record.status;
                            customerPhInput.value = record.customer_ph;
                            ecsInput.value = record.ecs || "";
                            vanAlphaInput.value = record.van_alpha || "";
                            vanNumberInput.value = record.van_number || "";
                            shipperInput.value = record.shipper || "";
                            eirOutInput.value = record.eir_out || "";
                            eirOutDateInput.value = formatDateForDisplay(record.eir_outDate || "");
                            eirOutTimeInput.value = formatTimeForDisplay(record.eir_outTime || "");
                            eirInInput.value = record.eir_in || "";
                            eirInDateInput.value = formatDateForDisplay(record.eir_inDate || "");
                            pulloutLocationInput.value = record.pullout_location || "";
                            pulloutDateInput.value = formatDateForDisplay(record.pullout_date || "");
                            const pulloutTimeInput = document.getElementById("pullout_time");
                            if (pulloutTimeInput) {
                                pulloutTimeInput.value = formatTimeForDisplay(record.pullout_time || "");
                            }
                            document.getElementById("delivered_to").value = record.delivered_to || "";
                            document.getElementById("return_location").value = record.return_location || "";
                            document.getElementById("size").value = record.size || "";
                            document.getElementById("commodity").value = record.commodity || "";
                            slpNoInput.value = record.slp_no || "";
                            destinationInput.value = record.destination || "";
                            gensetInput.value = record.gs || "";
                            hourMeterStartInput.value = record.genset_hr_meter_start || "";
                            hourMeterEndInput.value = record.genset_hr_meter_end || "";
                            document.getElementById("kms").value = record.kms || "";
                            document.getElementById("booking").value = record.booking || "";
                            document.getElementById("shipment_no").value = record.shipment_no || "";
                            document.getElementById("vessel_name").value = record.vessel_name || "";
                            document.getElementById("voyage_no").value = record.voyage_no || "";
                            document.getElementById("emdr_no").value = record.emdr_no || "";
                            document.getElementById("seal").value = record.seal || "";

                            // Load both sections with data
                            document.getElementById("segment").value = record.segment || "";
                            document.getElementById("activity").value = record.activity || "";
                            document.getElementById("waybill").value = record.waybill || "";
                            document.getElementById("date_hauled").value = formatDateForDisplay(record.date_hauled || "");
                            driverInput.value = record.driver || "";
                            document.getElementById("driver_idNumber").value = record.driver_idNumber || "";
                            truckInput.value = record.truck || "";
                            trInput.value = record.tr || "";
                            trEmptyInput.value = record.tr || "";
                            tr2Input.value = record.tr2 || "";
                            document.getElementById("date_unloaded").value = formatDateForDisplay(record.date_unloaded || "");
                            const departureTimeInput = document.getElementById("departure_time");
                            if (departureTimeInput) {
                                departureTimeInput.value = formatTimeForDisplay(record.departure_time || "");
                            }
                            const timeUnloadedInput = document.getElementById("time_unloaded");
                            if (timeUnloadedInput) {
                                timeUnloadedInput.value = formatTimeForDisplay(record.time_unloaded || "");
                            }
                            const remarksInput = document.getElementById("remarks");
                            if (remarksInput) {
                                remarksInput.value = record.remarks || "";
                            }

                            document.getElementById("segment_empty").value = record.segment_empty || "";
                            document.getElementById("activity_empty").value = record.activity_empty || "";
                            document.getElementById("waybill_empty").value = record.waybill_empty || "";
                            document.getElementById("date_returned").value = formatDateForDisplay(record.date_returned || "");
                            driverReturnInput.value = record.driver_return || "";
                            document.getElementById("driver_return_idNumber").value = record.driver_return_idNumber || "";
                            truckReturnInput.value = record.truck2 || "";
                            document.getElementById("type").value = record.type || "";
                            document.getElementById("delivered_remarks").value = record.delivered_remarks || "";
                            applyCustomerFormRules();

                            document.querySelector('[data-bs-target="#entryForm"]').click();
                        }
                    });
            });

            // Auto-open a record when arriving from the For Update queue (?load_id=).
            // Reuses the existing .edit-btn handler (which also applies customer rules).
            (function () {
                const loadId = new URLSearchParams(window.location.search).get('load_id');
                if (!loadId) return;
                const $btn = $('<button class="edit-btn d-none"></button>').attr('data-id', loadId).appendTo('body');
                $btn.trigger('click');
                setTimeout(function () {
                    $btn.remove();
                    const el = document.getElementById('entryForm');
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 450);
            })();

            // Delete button
            $(document).on("click", ".delete-btn", function() {
                const id = $(this).data("id");
                Swal.fire({
                    title: "Delete Entry?",
                    text: "This action cannot be undone.",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#dc3545"
                }).then(result => {
                    if (result.isConfirmed) {
                        fetch("php/delete/dry_van.php", {
                                method: "POST",
                                headers: {
                                    "Content-Type": "application/x-www-form-urlencoded"
                                },
                                body: "entry_id=" + id
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    removeTableRow(id);
                                    Swal.fire("Deleted!", "", "success").then(() => {
                                        dataIdInput.value = "";
                                    });
                                }
                            });
                    }
                });
            });
        });
    </script>
    <!-- Trip receipt / waybill lookup against dispatch DB (auto-fill + alert) -->
    <script src="assets/js/trip_receipt_lookup.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof attachTripReceiptLookup !== 'function') return;
            ['waybill', 'waybill_empty'].forEach(function (id) {
                // Never let the dispatch lookup overwrite Customer (PH): on Dry Van
                // the encoder's selected customer drives the form layout and billing,
                // so it stays authoritative (dispatch would fill e.g. "DPC" instead).
                if (document.getElementById(id)) attachTripReceiptLookup({ input: id, exclude: ['customer_ph'] });
            });
        });
    </script>
</body>

</html>
