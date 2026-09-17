<?php 
include "php/session-check.php"; 
include "php/config/config.php";

$query = "SELECT data_id, waybill_no, date, segment, activity, truck, tr, ph, driver, trip, total_trips, no_trips, pmtrip1, pullout_location, customer_ph, deliver_from, deliver_to, delivered_to, operations, request, type, arrival_date, arrival_time, dispatch_date, dispatch_time, ph_arrival_date, ph_arrival_time, finished_loading_date, finished_loading_time, ph_departure_date, ph_departure_time, wharf_arrival_date, wharf_arrival_time, wharf_departure_date, wharf_departure_time, loading_location, loading_location_arrival, start_loading, finish_loading, loading_location_departure, delivery_location, delivery_location_arrival_date, delivery_location_arrival_time, van_name, alpha, numerics, shipper, ecs, evita_farmind, load_qty, unit_of_measure, total_load, kg_13, sp_3kgs, nerita, body_13, cover_13, pads_13, body_18, cover_18, pads_18, total_13, total_18, tls_number, waybill_dr_no, genset_hr_meter_start, genset_hr_meter_end, gs_start_date, gs_start_time, gs_end_date, gs_end_time, refueled, departure_time, travel_time_ph_to_cy, travel_time, empty_pull_out, class_b, outside, compound, customer_exe_code, concat_field, sku, km2, per_trip_km, reference_documents, fgtrs_no, encoder, remarks, week, month, transmittal_date FROM main";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BBHM Data Entry - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
    <link rel="stylesheet" href="assets/DataTables/datatables.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
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
                            <h2 class="fw-bold">BBHM Data Entry</h2>
                            <p class="text-muted">Create, view, and manage BBHM data entries efficiently.</p>
                        </div>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="btn-group" role="group" aria-label="Basic outlined example">
                        <a class="btn btn-outline-secondary" href="rv">Refer Van</a>
                        <a class="btn btn-outline-secondary" href="others">Others</a>
                        <a class="btn btn-outline-secondary" href="bbhm">BBHM</a>
                        <a class="btn btn-outline-secondary" href="DPC_KDI">DPC_KDI & OPM</a>
                        <a class="btn btn-outline-secondary" href="cargoTruck">Cargo Truck</a>
                        </div>
                    </div>
                </div>
                

                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>New BBHM Entry</h5>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#entryForm">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                    <div class="collapse" id="entryForm">
                        <div class="card-body">
                            <form id="dataEntryForm">
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
                                    <div class="col-md-6">
                                        <label for="production_date" class="form-label">PRODUCTION DATE</label>
                                        <input type="date" class="form-control" id="production_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="finished_loading_date" class="form-label">FINISHED LOADING DATE</label>
                                        <input type="date" class="form-control" id="finished_loading_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="finished_loading_time" class="form-label">FINISHED LOADING TIME</label>
                                        <input type="time" class="form-control" id="finished_loading_time">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="ph_departure_date" class="form-label">PH DEPARTURE DATE</label>
                                        <input type="date" class="form-control" id="ph_departure_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="ph_departure_time" class="form-label">PH DEPARTURE TIME</label>
                                        <input type="time" class="form-control" id="ph_departure_time">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="wharf_arrival_date" class="form-label">WHARF ARRIVAL DATE</label>
                                        <input type="date" class="form-control" id="wharf_arrival_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="wharf_arrival_time" class="form-label">WHARF ARRIVAL TIME</label>
                                        <input type="time" class="form-control" id="wharf_arrival_time">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="wharf_departure_date" class="form-label">WHARF DEPARTURE DATE</label>
                                        <input type="date" class="form-control" id="wharf_departure_date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="wharf_departure_time" class="form-label">WHARF DEPARTURE TIME</label>
                                        <input type="time" class="form-control" id="wharf_departure_time">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="driver" class="form-label">DRIVER</label>
                                        <input type="text" class="form-control" id="driver">
                                        <ul id="driverList" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></ul>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="waybill" class="form-label">WAYBILL</label>
                                        <input type="text" class="form-control" id="waybill">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="tls_number" class="form-label">TLS NUMBER</label>
                                        <input type="text" class="form-control" id="tls_number">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="thirteen_kgs" class="form-label">13 KGS.</label>
                                        <input type="text" class="form-control" id="thirteen_kgs">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="sp_three_kgs" class="form-label">SP 3KGS.</label>
                                        <input type="text" class="form-control" id="sp_three_kgs">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="total_load" class="form-label">TOTAL LOAD</label>
                                        <input type="text" class="form-control" id="total_load">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="delivered_to" class="form-label">DELIVERED TO</label>
                                        <input type="text" class="form-control" id="delivered_to">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="type" class="form-label">TYPE</label>
                                        <input type="text" class="form-control" id="type">
                                    </div>
                                    <div class="col-md-12">
                                        <label for="remarks" class="form-label">REMARKS</label>
                                        <textarea class="form-control" id="remarks" rows="3"></textarea>
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
                                    <tr>
                                        <td><strong>#<?php echo $row['data_id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['date'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['waybill_no'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars(($row['van_name'] ?? '') !== '' ? $row['van_name'] : (trim(($row['alpha'] ?? '') . ' ' . ($row['numerics'] ?? '')) !== '' ? trim(($row['alpha'] ?? '') . ' ' . ($row['numerics'] ?? '')) : (($row['tr'] ?? '') !== '' ? $row['tr'] : ($row['truck'] ?? '')))); ?></td>
                                        <td><?php echo htmlspecialchars($row['driver'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['remarks'] ?? ''); ?></td>
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
    <script src="assets/DataTables/datatables.min.js"></script>
    <script>
        let table = new DataTable('#entriesTable');
    </script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <script>
        $(document).ready(function () {
            $("#enav").attr({
					"class" : "nav-link active"
				});
         });
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
        // ===== Truck Search =====
        const truckInput = document.getElementById("assignUnitName1");
        const truckList = document.getElementById("truckList");
        let allTrucks = [];

        fetch("php/fetch/get_trucks1.php")
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
        const gensetInput = document.getElementById("genset");
        const gensetList = document.getElementById("gensetList");
        let allGensets = [];

        fetch("php/fetch/get_gensets.php")
          .then(res => res.json())
          .then(data => {
            allGensets = data.map((g) => g.unit_name);
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
        const allSearchLists = [segmentList, activityList, driverList, truckList, gensetList, trailerList].filter(Boolean);

        fetch("php/fetch/get_trailers.php")
          .then(res => res.json())
          .then(data => {
            allTrailers = data.map((t) => t.trailer_name);
          });

        trailerInput.addEventListener("input", function() {
          filterDropdown(this, trailerList, allTrailers, (name) => {
            trailerInput.value = name;
          });
        });
        function hideDropdown(listElem) {
          if (!listElem) return;
          listElem.style.display = "none";
          listElem.innerHTML = "";
        }

        function showDropdown(listElem) {
          if (!listElem) return;
          listElem.style.maxHeight = "220px";
          listElem.style.overflowY = "auto";
          listElem.style.overflowX = "hidden";
          listElem.style.display = "block";
        }

        // ===== Shared Function =====
        function filterDropdown(inputElem, listElem, dataArr, onSelect) {
          const searchVal = inputElem.value.toLowerCase();

          if (!searchVal) {
            hideDropdown(listElem);
            return;
          }

          listElem.innerHTML = "";

          const filtered = dataArr.filter(item => item.toLowerCase().includes(searchVal));

          if (filtered.length === 0) {
            hideDropdown(listElem);
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

            li.addEventListener("mousedown", function(event) {
              event.preventDefault();
              onSelect(item);
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
                onSelect(activeItem.textContent);
                hideDropdown(listElem);
              }
            } else if (e.key === "Tab") {
              const activeItem = items[activeIndex];
              if (activeItem) {
                onSelect(activeItem.textContent);
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

        // ===== Hide all dropdowns on click outside =====
        document.addEventListener("click", function(e) {
          [segmentList, activityList, driverList, truckList, gensetList, trailerList].forEach(list => {
            if (!list.contains(e.target) &&
              !segmentInput.contains(e.target) &&
              !activityInput.contains(e.target) &&
              !driverInput.contains(e.target) &&
              !truckInput.contains(e.target) &&
              !gensetInput.contains(e.target) &&
              !trailerInput.contains(e.target)) {
              list.style.display = "none";
            }
          });
        });
      });
    </script>
</body>
</html>
