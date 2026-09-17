<?php include "php/session-check.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Trips - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/DataTables/datatables.min.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        #tripsTable td, #tripsTable th { vertical-align: middle; }
        #tripsTable tfoot td { border-top: 2px solid #dee2e6; font-weight: 600; }
        .trip-route { white-space: nowrap; }
        .km-cell { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .km-missing { color: #adb5bd; }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include "sidebar.php"; ?>

        <div id="content">
            <?php include "navbar.php"; ?>

            <div class="main-content">
                <div class="content-header mb-4 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div>
                        <h2 class="fw-bold mb-1">Driver Trips</h2>
                        <p class="text-muted mb-0">
                            Every trip per driver with its route and kilometres, totalled for the selected range.
                            <span class="text-secondary">Each trip is counted once, credited to its primary driver.</span>
                        </p>
                    </div>
                    <div class="text-lg-end">
                        <small class="text-muted d-block">Last refresh</small>
                        <strong id="lastUpdated">Waiting for data...</strong>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-2">
                                <label for="dateFrom" class="form-label fw-semibold">Date From</label>
                                <input type="date" class="form-control" id="dateFrom" style="height:45px;">
                            </div>
                            <div class="col-md-2">
                                <label for="dateTo" class="form-label fw-semibold">Date To</label>
                                <input type="date" class="form-control" id="dateTo" style="height:45px;">
                            </div>
                            <div class="col-md-3 position-relative">
                                <label for="driverFilter" class="form-label fw-semibold">Driver</label>
                                <input type="text" class="form-control" id="driverFilter" placeholder="Search driver name…" style="height:45px;" autocomplete="off">
                                <ul id="driverFilterList" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1010; display: none; max-height: 240px; overflow-y: auto;"></ul>
                            </div>
                            <div class="col-md-2">
                                <label for="segmentFilter" class="form-label fw-semibold">Trip Segment</label>
                                <select class="form-select" id="segmentFilter" style="height:45px;">
                                    <option value="">All segments</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="entryTypeFilter" class="form-label fw-semibold">Entry Type</label>
                                <select class="form-select" id="entryTypeFilter" style="height:45px;">
                                    <option value="">All types</option>
                                </select>
                            </div>
                            <div class="col-md-1 d-grid">
                                <button type="button" class="btn btn-primary" id="applyFilters" style="height:45px;" title="Apply filters">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                        <div id="reportStatus" class="small mt-3 text-muted"></div>
                    </div>
                </div>

                <div class="row g-4 mb-4 analytics-summary">
                    <div class="col-md-4">
                        <div class="stat-card stat-card-blue">
                            <div class="stat-icon"><i class="bi bi-truck"></i></div>
                            <div><h3 id="totalTrips">0</h3><p>Total Trips</p></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card stat-card-green">
                            <div class="stat-icon"><i class="bi bi-signpost-split"></i></div>
                            <div><h3 id="totalKm">0</h3><p>Total KM</p></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card stat-card-orange">
                            <div class="stat-icon"><i class="bi bi-people"></i></div>
                            <div><h3 id="totalDrivers">0</h3><p>Drivers</p></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Trips</h5>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-success btn-sm" id="exportExcel">
                                <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="exportCsv">
                                <i class="bi bi-filetype-csv me-1"></i>Export CSV
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="tripsTable" style="width:100%;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Driver</th>
                                        <th>Trip Segment</th>
                                        <th>Trip Receipt</th>
                                        <th>Route</th>
                                        <th class="text-end">Total KM</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-end">TOTAL</td>
                                        <td class="km-cell" id="footTotalKm">0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="assets/DataTables/datatables.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <script>
        $(document).ready(function () { $("#dtnav").attr({ "class": "nav-link active" }); });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const dateFrom = document.getElementById('dateFrom');
            const dateTo = document.getElementById('dateTo');
            const driverFilter = document.getElementById('driverFilter');
            const driverFilterList = document.getElementById('driverFilterList');
            const segmentFilter = document.getElementById('segmentFilter');
            const entryTypeFilter = document.getElementById('entryTypeFilter');
            const applyFilters = document.getElementById('applyFilters');
            const reportStatus = document.getElementById('reportStatus');
            const exportCsv = document.getElementById('exportCsv');
            const exportExcel = document.getElementById('exportExcel');
            const footTotalKm = document.getElementById('footTotalKm');

            let table = null;
            let filtersLoaded = false;
            let currentTrips = [];

            // Default to the current month.
            const now = new Date();
            const pad = n => String(n).padStart(2, '0');
            const firstOfMonth = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`;
            const lastOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            dateFrom.value = firstOfMonth;
            dateTo.value = `${lastOfMonth.getFullYear()}-${pad(lastOfMonth.getMonth() + 1)}-${pad(lastOfMonth.getDate())}`;

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }

            function formatKm(value) {
                return Number(value || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2
                });
            }

            function setStatus(message, kind) {
                reportStatus.className = 'small mt-3 ' + (kind || 'text-muted');
                reportStatus.textContent = message || '';
            }

            function buildQuery() {
                const params = new URLSearchParams({
                    date_from: dateFrom.value,
                    date_to: dateTo.value
                });
                if (driverFilter.value.trim()) params.set('driver', driverFilter.value.trim());
                if (segmentFilter.value) params.set('segment', segmentFilter.value);
                if (entryTypeFilter.value) params.set('entry_type', entryTypeFilter.value);
                return params.toString();
            }

            // ---- Driver auto-suggest (same behaviour as the entry forms) ----
            let allDrivers = [];

            fetch('php/fetch/get_drivers.php', { cache: 'no-store' })
                .then(res => res.json())
                .then(data => { allDrivers = Array.isArray(data) ? data : []; })
                .catch(() => { allDrivers = []; });

            function hideDropdown(listElem) {
                if (!listElem) return;
                listElem.style.display = 'none';
                listElem.innerHTML = '';
            }

            function showDropdown(listElem) {
                if (!listElem) return;
                listElem.style.maxHeight = '240px';
                listElem.style.overflowY = 'auto';
                listElem.style.overflowX = 'hidden';
                listElem.style.display = 'block';
            }

            function pickDriver(name) {
                driverFilter.value = name;
                hideDropdown(driverFilterList);
                loadReport();
            }

            function filterDriverDropdown() {
                const searchVal = driverFilter.value.trim().toLowerCase();
                if (!searchVal) {
                    hideDropdown(driverFilterList);
                    return;
                }

                driverFilterList.innerHTML = '';
                const matches = allDrivers
                    .filter(d => String(d.name || '').toLowerCase().includes(searchVal))
                    .slice(0, 50);

                if (!matches.length) {
                    hideDropdown(driverFilterList);
                    return;
                }

                matches.forEach(function (driver, index) {
                    const li = document.createElement('li');
                    li.className = 'list-group-item list-group-item-action';
                    li.textContent = driver.id ? `${driver.name} — ${driver.id}` : driver.name;
                    li.dataset.pickValue = driver.name;
                    if (index === 0) li.classList.add('active-suggestion');
                    li.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                        pickDriver(driver.name);
                    });
                    driverFilterList.appendChild(li);
                });

                showDropdown(driverFilterList);
            }

            (function attachDriverKeyboardNav() {
                let activeIndex = 0;

                driverFilter.addEventListener('input', function () {
                    activeIndex = 0;
                    filterDriverDropdown();
                });

                driverFilter.addEventListener('focus', filterDriverDropdown);

                driverFilter.addEventListener('keydown', function (e) {
                    const items = driverFilterList.querySelectorAll('li');

                    if (e.key === 'Escape') { hideDropdown(driverFilterList); return; }

                    if (!items.length) {
                        if (e.key === 'Enter') { e.preventDefault(); loadReport(); }
                        return;
                    }

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        activeIndex = (activeIndex + 1) % items.length;
                        updateActive(items, activeIndex);
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        activeIndex = (activeIndex - 1 + items.length) % items.length;
                        updateActive(items, activeIndex);
                    } else if (e.key === 'Enter' || e.key === 'Tab') {
                        const activeItem = items[activeIndex];
                        if (activeItem) {
                            if (e.key === 'Enter') e.preventDefault();
                            pickDriver(activeItem.dataset.pickValue);
                        } else {
                            hideDropdown(driverFilterList);
                        }
                    }
                });

                function updateActive(items, index) {
                    items.forEach(item => item.classList.remove('active-suggestion'));
                    if (items[index]) {
                        items[index].classList.add('active-suggestion');
                        items[index].scrollIntoView({ block: 'nearest' });
                    }
                }
            })();

            document.addEventListener('mousedown', function (event) {
                if (!event.target.closest('#driverFilter') && !event.target.closest('#driverFilterList')) {
                    hideDropdown(driverFilterList);
                }
            });

            function fillFilterOptions(filters) {
                if (filtersLoaded) return;
                (filters.segments || []).forEach(function (segment) {
                    const option = document.createElement('option');
                    option.value = segment;
                    option.textContent = segment;
                    segmentFilter.appendChild(option);
                });
                (filters.entry_types || []).forEach(function (entryType) {
                    const option = document.createElement('option');
                    option.value = entryType;
                    option.textContent = entryType;
                    entryTypeFilter.appendChild(option);
                });
                filtersLoaded = true;
            }

            function renderRows(trips) {
                const rows = trips.map(function (trip) {
                    // A trip with no encoded KM shows a dash rather than a misleading 0.00.
                    const kmCell = trip.has_km
                        ? `<span class="km-cell">${formatKm(trip.kms)}</span>`
                        : '<span class="km-cell km-missing" title="No KM encoded on this trip">—</span>';
                    return [
                        escapeHtml(trip.driver || '-'),
                        escapeHtml(trip.segment || '-'),
                        escapeHtml(trip.trip_receipt || '-'),
                        `<span class="trip-route">${escapeHtml(trip.route || '-')}</span>`,
                        kmCell
                    ];
                });

                if (table) {
                    table.clear();
                    table.rows.add(rows);
                    table.draw();
                    return;
                }

                table = new DataTable('#tripsTable', {
                    data: rows,
                    pageLength: 25,
                    order: [],
                    columnDefs: [{ className: 'text-end', targets: [4] }]
                });
            }

            async function loadReport() {
                if (!dateFrom.value || !dateTo.value) {
                    setStatus('Please select both a start and end date.', 'text-danger');
                    return;
                }
                if (dateFrom.value > dateTo.value) {
                    setStatus('Date From must not be later than Date To.', 'text-danger');
                    return;
                }

                applyFilters.disabled = true;
                setStatus('Loading trips...', 'text-muted');

                try {
                    const response = await fetch('php/fetch/get_driver_trips.php?' + buildQuery(), { cache: 'no-store' });
                    const data = await response.json();
                    if (!data.success) {
                        setStatus(data.message || 'Failed to load report.', 'text-danger');
                        return;
                    }

                    fillFilterOptions(data.filters || {});
                    currentTrips = data.trips || [];
                    renderRows(currentTrips);

                    document.getElementById('totalTrips').textContent = Number(data.totals.trips).toLocaleString('en-US');
                    document.getElementById('totalKm').textContent = formatKm(data.totals.total_km);
                    document.getElementById('totalDrivers').textContent = Number(data.totals.drivers).toLocaleString('en-US');
                    footTotalKm.textContent = formatKm(data.totals.total_km);

                    const missing = data.totals.trips_without_km || 0;
                    if (missing > 0) {
                        setStatus(
                            `${data.totals.trips} trip(s) found. ${missing} have no KM encoded and contribute 0 to the total.`,
                            'text-warning'
                        );
                    } else {
                        setStatus(`${data.totals.trips} trip(s) found.`, 'text-success');
                    }

                    document.getElementById('lastUpdated').textContent = new Date().toLocaleString();
                } catch (error) {
                    setStatus('Failed to load report: ' + error.message, 'text-danger');
                } finally {
                    applyFilters.disabled = false;
                }
            }

            // ---- Exports (shared shape so Excel and CSV never drift apart) ----
            const EXPORT_HEADERS = ['Driver', 'Trip Segment', 'Trip Receipt', 'Route', 'Total KM'];

            function buildExportRows() {
                let total = 0;
                const rows = currentTrips.map(function (trip) {
                    total += Number(trip.kms || 0);
                    return [
                        trip.driver || '',
                        trip.segment || '',
                        trip.trip_receipt || '',
                        trip.route || '',
                        // Blank (not 0) when no KM was encoded, and a real number
                        // so Excel can sum the column.
                        trip.has_km ? Number(trip.kms) : ''
                    ];
                });
                return { rows, total: Number(total.toFixed(2)) };
            }

            function exportFileName(extension) {
                return `driver_trips_${dateFrom.value}_${dateTo.value}.${extension}`;
            }

            function downloadBlob(blob, filename) {
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href);
            }

            function hasRowsToExport() {
                if (!currentTrips.length) {
                    setStatus('Nothing to export — load the report first.', 'text-danger');
                    return false;
                }
                return true;
            }

            exportExcel.addEventListener('click', function () {
                if (!hasRowsToExport()) return;
                if (typeof XLSX === 'undefined') {
                    setStatus('Excel library failed to load. Use Export CSV instead.', 'text-danger');
                    return;
                }
                const { rows, total } = buildExportRows();
                const aoa = [EXPORT_HEADERS, ...rows, ['', '', '', 'TOTAL', total]];
                const sheet = XLSX.utils.aoa_to_sheet(aoa);
                sheet['!cols'] = [{ wch: 28 }, { wch: 14 }, { wch: 13 }, { wch: 46 }, { wch: 11 }];

                // Two decimals on the KM column, data rows plus the TOTAL.
                for (let i = 1; i < aoa.length; i++) {
                    const cell = sheet[XLSX.utils.encode_cell({ r: i, c: 4 })];
                    if (cell && cell.t === 'n') cell.z = '#,##0.00';
                }

                const book = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(book, sheet, 'Driver Trips');
                XLSX.writeFile(book, exportFileName('xlsx'));
            });

            exportCsv.addEventListener('click', function () {
                if (!hasRowsToExport()) return;
                const { rows, total } = buildExportRows();
                const quote = value => '"' + String(value ?? '').replace(/"/g, '""') + '"';
                const lines = [EXPORT_HEADERS.map(quote).join(',')];
                rows.forEach(function (row) {
                    const cells = row.slice(0, 4).map(quote);
                    cells.push(quote(row[4] === '' ? '' : Number(row[4]).toFixed(2)));
                    lines.push(cells.join(','));
                });
                lines.push(['', '', '', quote('TOTAL'), quote(total.toFixed(2))].join(','));

                // BOM so Excel reads the route arrows (→) as UTF-8 rather than mojibake.
                const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
                downloadBlob(blob, exportFileName('csv'));
            });

            applyFilters.addEventListener('click', loadReport);
            [dateFrom, dateTo, segmentFilter, entryTypeFilter].forEach(function (element) {
                element.addEventListener('change', loadReport);
            });

            loadReport();
        });
    </script>
</body>
</html>
