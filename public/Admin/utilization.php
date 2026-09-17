<?php include "php/session-check.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utilization Report - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/DataTables/datatables.min.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .analytics-toggle .btn.active { background-color: #0d6efd; color: #fff; }
        .analytics-summary .stat-card h3 { font-size: 1.85rem; }
        .chart-card canvas { max-height: 320px; }
        .utilization-table th, .utilization-table td { white-space: nowrap; }
        #truckTable tbody tr, #trailerTable tbody tr { cursor: pointer; }
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
                        <h2 class="fw-bold mb-1">Truck and Trailer Utilization</h2>
                        <p class="text-muted mb-0">
                            Utilization by analytics series based on recorded truck, prime mover, and trailer assignments.
                            <span class="text-secondary">Series follow the same grouping rules used in Analytics. Each unit is counted once per operation after deduping repeated fields in the same row.</span>
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
                            <div class="col-md-auto">
                                <label class="form-label fw-semibold mb-2 d-block">Report Period</label>
                                <div class="btn-group analytics-toggle" role="group" id="periodToggle">
                                    <button type="button" class="btn btn-outline-primary active" data-period="weekly">
                                        <i class="bi bi-calendar-week me-1"></i>Weekly
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" data-period="monthly">
                                        <i class="bi bi-calendar-month me-1"></i>Monthly
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="dateFrom" class="form-label fw-semibold">Date From</label>
                                <input type="date" class="form-control" id="dateFrom" style="height:45px;">
                            </div>
                            <div class="col-md-3">
                                <label for="dateTo" class="form-label fw-semibold">Date To</label>
                                <input type="date" class="form-control" id="dateTo" style="height:45px;">
                            </div>
                            <div class="col-md-auto">
                                <button class="btn btn-primary w-100" id="applyFilters">
                                    <i class="bi bi-funnel me-1"></i>Apply
                                </button>
                            </div>
                            <div class="col-md-auto">
                                <button class="btn btn-outline-secondary w-100" id="resetPeriod">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                                </button>
                            </div>
                            <div class="col-md-auto ms-md-auto">
                                <button class="btn btn-outline-success" id="exportCsv">
                                    <i class="bi bi-filetype-csv me-1"></i>Export CSV
                                </button>
                                <button class="btn btn-outline-success ms-1" id="exportExcel">
                                    <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
                                </button>
                            </div>
                        </div>
                        <div class="mt-3 small text-muted" id="rangeLabel">Showing data for the current week.</div>
                    </div>
                </div>

                <div class="row g-4 mb-4 analytics-summary">
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card stat-card-blue">
                            <div class="stat-icon"><i class="bi bi-people"></i></div>
                            <div class="stat-content">
                                <h3 id="activeShippers">0</h3>
                                <p>Active Series</p>
                                <small class="stat-change text-muted">With utilization records</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card stat-card-green">
                            <div class="stat-icon"><i class="bi bi-truck"></i></div>
                            <div class="stat-content">
                                <h3 id="uniqueTrucks">0</h3>
                                <p>Unique Trucks</p>
                                <small class="stat-change text-success">Distinct units used</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card stat-card-orange">
                            <div class="stat-icon"><i class="bi bi-distribute-horizontal"></i></div>
                            <div class="stat-content">
                                <h3 id="uniqueTrailers">0</h3>
                                <p>Unique Trailers</p>
                                <small class="stat-change text-warning">Distinct trailers used</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card stat-card-purple">
                            <div class="stat-icon"><i class="bi bi-sign-turn-right"></i></div>
                            <div class="stat-content">
                                <h3 id="totalTrips">0</h3>
                                <p>Total Trips</p>
                                <small class="stat-change text-success">Weighted by trip count</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-xl-6">
                        <div class="card chart-card h-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Truck Utilization by Series</h5>
                                <small class="text-muted">Distinct trucks</small>
                            </div>
                            <div class="card-body">
                                <canvas id="truckUtilizationChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="card chart-card h-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Trailer Utilization by Series</h5>
                                <small class="text-muted">Distinct trailers</small>
                            </div>
                            <div class="card-body">
                                <canvas id="trailerUtilizationChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-12">
                        <div class="card mb-4">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Truck Utilization Breakdown</h5>
                                <span class="badge bg-primary" id="truckCountBadge">0 series</span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover utilization-table" id="truckTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Series</th>
                                                <th class="text-end">Trips</th>
                                                <th class="text-end">Unique Trucks</th>
                                                <th class="text-end">Truck Uses</th>
                                                <th>Top Truck</th>
                                                <th class="text-end">Top Truck Uses</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card mb-4">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Trailer Utilization Breakdown</h5>
                                <span class="badge bg-primary" id="trailerCountBadge">0 series</span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover utilization-table" id="trailerTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Series</th>
                                                <th class="text-end">Trips</th>
                                                <th class="text-end">Unique Trailers</th>
                                                <th class="text-end">Trailer Uses</th>
                                                <th>Top Trailer</th>
                                                <th class="text-end">Top Trailer Uses</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="utilizationDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1" id="utilizationDetailTitle">Utilization Detail</h5>
                        <small class="text-muted" id="utilizationDetailSubtitle"></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light h-100">
                                <small class="text-muted d-block">Trips</small>
                                <strong id="utilizationDetailTrips">0</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light h-100">
                                <small class="text-muted d-block">Top Unit</small>
                                <strong id="utilizationDetailTopUnit">-</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light h-100">
                                <small class="text-muted d-block">Top Unit Uses</small>
                                <strong id="utilizationDetailTopUnitUses">0</strong>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th id="utilizationDetailUnitLabel">Truck</th>
                                    <th class="text-end">Uses</th>
                                </tr>
                            </thead>
                            <tbody id="utilizationDetailBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="assets/DataTables/datatables.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <script>
        $(document).ready(function () {
            $("#utnav").attr({ "class": "nav-link active" });
        });

        document.addEventListener('DOMContentLoaded', function () {
            const palette = [
                '#0d6efd', '#198754', '#fd7e14', '#dc3545', '#6f42c1',
                '#20c997', '#ffc107', '#0dcaf0', '#d63384', '#6610f2'
            ];

            const state = {
                period: 'weekly',
                dateFrom: '',
                dateTo: '',
                lastData: null
            };

            const periodButtons = document.querySelectorAll('#periodToggle button');
            const dateFromInput = document.getElementById('dateFrom');
            const dateToInput = document.getElementById('dateTo');

            let truckChart = null;
            let trailerChart = null;
            let truckTable = null;
            let trailerTable = null;
            const detailModal = new bootstrap.Modal(document.getElementById('utilizationDetailModal'));

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }

            function formatNumber(value) {
                const num = Number(value || 0);
                if (Number.isInteger(num)) return num.toLocaleString();
                return num.toLocaleString(undefined, { maximumFractionDigits: 2 });
            }

            function formatDateTime(value) {
                if (!value) return '-';
                const date = new Date(value);
                if (Number.isNaN(date.getTime())) return value;
                const m = String(date.getMonth() + 1).padStart(2, '0');
                const d = String(date.getDate()).padStart(2, '0');
                const y = date.getFullYear();
                const h = String(date.getHours()).padStart(2, '0');
                const mi = String(date.getMinutes()).padStart(2, '0');
                return `${m}/${d}/${y} ${h}:${mi}`;
            }

            function setActiveButton(buttons, value, attr) {
                buttons.forEach(btn => {
                    if (btn.dataset[attr] === value) btn.classList.add('active');
                    else btn.classList.remove('active');
                });
            }

            function buildQueryString() {
                const params = new URLSearchParams();
                params.set('period', state.period);
                if (state.dateFrom) params.set('date_from', state.dateFrom);
                if (state.dateTo) params.set('date_to', state.dateTo);
                return params.toString();
            }

            function updateSummary(totals) {
                document.getElementById('activeShippers').textContent = formatNumber(totals.active_shippers);
                document.getElementById('uniqueTrucks').textContent = formatNumber(totals.unique_trucks);
                document.getElementById('uniqueTrailers').textContent = formatNumber(totals.unique_trailers);
                document.getElementById('totalTrips').textContent = formatNumber(totals.total_trips);
            }

            function updateRangeLabel(data) {
                const range = `${data.date_from} to ${data.date_to}`;
                document.getElementById('rangeLabel').textContent = state.period === 'weekly'
                    ? `Showing weekly data: ${range}`
                    : `Showing monthly data: ${range}`;
            }

            function renderBarChart(canvasId, chartRef, rows, label) {
                const labels = rows.map(row => row.shipper);
                const values = rows.map(row => Number(row.unique_units || 0));
                const colors = labels.map((_, i) => palette[i % palette.length]);

                if (chartRef.current) chartRef.current.destroy();
                chartRef.current = new Chart(document.getElementById(canvasId), {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label,
                            data: values,
                            backgroundColor: colors,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            }

            function updateCharts(data) {
                renderBarChart('truckUtilizationChart', { current: truckChart }, data.truck_chart || [], 'Unique Trucks');
                truckChart = document.getElementById('truckUtilizationChart').chart || truckChart;
                renderBarChart('trailerUtilizationChart', { current: trailerChart }, data.trailer_chart || [], 'Unique Trailers');
                trailerChart = document.getElementById('trailerUtilizationChart').chart || trailerChart;
            }

            function rebuildChart(targetId, existingChart, rows, label) {
                const labels = rows.map(row => row.shipper);
                const values = rows.map(row => Number(row.unique_units || 0));
                const colors = labels.map((_, i) => palette[i % palette.length]);
                if (existingChart) existingChart.destroy();
                return new Chart(document.getElementById(targetId), {
                    type: 'bar',
                    data: { labels, datasets: [{ label, data: values, backgroundColor: colors, borderRadius: 6 }] },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            }

            function updateTruckChart(rows) {
                truckChart = rebuildChart('truckUtilizationChart', truckChart, rows, 'Unique Trucks');
            }

            function updateTrailerChart(rows) {
                trailerChart = rebuildChart('trailerUtilizationChart', trailerChart, rows, 'Unique Trailers');
            }

            function showBreakdownModal(type, row) {
                const unitLabel = type === 'truck' ? 'Truck' : 'Trailer';
                document.getElementById('utilizationDetailTitle').textContent = `${unitLabel} Utilization Detail`;
                document.getElementById('utilizationDetailSubtitle').textContent = row.shipper || '-';
                document.getElementById('utilizationDetailTrips').textContent = formatNumber(row.trips);
                document.getElementById('utilizationDetailTopUnit').textContent = row.top_unit || '-';
                document.getElementById('utilizationDetailTopUnitUses').textContent = formatNumber(row.top_unit_uses);
                document.getElementById('utilizationDetailUnitLabel').textContent = unitLabel;

                const units = row.units_breakdown || [];
                document.getElementById('utilizationDetailBody').innerHTML = units.length
                    ? units.map(unit => `
                        <tr>
                            <td>${escapeHtml(unit.unit_name || '-')}</td>
                            <td class="text-end">${formatNumber(unit.uses)}</td>
                        </tr>
                    `).join('')
                    : `<tr><td colspan="2" class="text-center text-muted">No ${unitLabel.toLowerCase()} data found.</td></tr>`;

                detailModal.show();
            }

            function updateTruckTable(rows) {
                if (truckTable) {
                    truckTable.destroy();
                    document.querySelector('#truckTable tbody').innerHTML = '';
                }

                truckTable = new DataTable('#truckTable', {
                    data: rows,
                    pageLength: 15,
                    order: [[3, 'desc']],
                    columns: [
                        { data: 'shipper', render: data => escapeHtml(data) },
                        { data: 'trips', render: data => formatNumber(data) },
                        { data: 'unique_units', render: data => formatNumber(data) },
                        { data: 'unit_uses', render: data => formatNumber(data) },
                        { data: 'top_unit', render: data => escapeHtml(data || '-') },
                        { data: 'top_unit_uses', render: data => formatNumber(data) }
                    ],
                    columnDefs: [{ targets: [1, 2, 3, 5], className: 'text-end' }]
                });

                document.getElementById('truckCountBadge').textContent = `${rows.length} series`;
                document.querySelector('#truckTable tbody').onclick = function (event) {
                    const tr = event.target.closest('tr');
                    if (!tr) return;
                    const row = truckTable.row(tr).data();
                    if (row) showBreakdownModal('truck', row);
                };
            }

            function updateTrailerTable(rows) {
                if (trailerTable) {
                    trailerTable.destroy();
                    document.querySelector('#trailerTable tbody').innerHTML = '';
                }

                trailerTable = new DataTable('#trailerTable', {
                    data: rows,
                    pageLength: 15,
                    order: [[3, 'desc']],
                    columns: [
                        { data: 'shipper', render: data => escapeHtml(data) },
                        { data: 'trips', render: data => formatNumber(data) },
                        { data: 'unique_units', render: data => formatNumber(data) },
                        { data: 'unit_uses', render: data => formatNumber(data) },
                        { data: 'top_unit', render: data => escapeHtml(data || '-') },
                        { data: 'top_unit_uses', render: data => formatNumber(data) }
                    ],
                    columnDefs: [{ targets: [1, 2, 3, 5], className: 'text-end' }]
                });

                document.getElementById('trailerCountBadge').textContent = `${rows.length} series`;
                document.querySelector('#trailerTable tbody').onclick = function (event) {
                    const tr = event.target.closest('tr');
                    if (!tr) return;
                    const row = trailerTable.row(tr).data();
                    if (row) showBreakdownModal('trailer', row);
                };
            }

            async function loadReport() {
                document.getElementById('lastUpdated').textContent = 'Loading...';
                try {
                    const response = await fetch('php/fetch/get_utilization_report.php?' + buildQueryString(), { cache: 'no-store' });
                    const data = await response.json();
                    if (!data.success) throw new Error(data.message || 'Failed to load utilization report.');

                    state.lastData = data;
                    if (!state.dateFrom) dateFromInput.value = data.date_from;
                    if (!state.dateTo) dateToInput.value = data.date_to;

                    updateSummary(data.totals || {});
                    updateRangeLabel(data);
                    updateTruckChart(data.truck_chart || []);
                    updateTrailerChart(data.trailer_chart || []);
                    updateTruckTable(data.truck_breakdown || []);
                    updateTrailerTable(data.trailer_breakdown || []);
                    document.getElementById('lastUpdated').textContent = formatDateTime(data.generated_at);
                } catch (error) {
                    document.getElementById('lastUpdated').textContent = 'Refresh failed';
                    console.error(error);
                }
            }

            function csvEscape(value) {
                const s = String(value ?? '');
                return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
            }

            function downloadBlob(blob, filename) {
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }

            function exportCsv() {
                if (!state.lastData) return;
                const truckHeader = ['Truck Utilization by Series'];
                const truckCols = ['Series', 'Trips', 'Unique Trucks', 'Truck Uses', 'Top Truck', 'Top Truck Uses'];
                const trailerHeader = ['Trailer Utilization by Series'];
                const trailerCols = ['Series', 'Trips', 'Unique Trailers', 'Trailer Uses', 'Top Trailer', 'Top Trailer Uses'];
                const lines = [];

                lines.push(truckHeader[0]);
                lines.push(truckCols.map(csvEscape).join(','));
                (state.lastData.truck_breakdown || []).forEach(row => {
                    lines.push([
                        row.shipper,
                        row.trips,
                        row.unique_units,
                        row.unit_uses,
                        row.top_unit,
                        row.top_unit_uses
                    ].map(csvEscape).join(','));
                });

                lines.push('');
                lines.push(trailerHeader[0]);
                lines.push(trailerCols.map(csvEscape).join(','));
                (state.lastData.trailer_breakdown || []).forEach(row => {
                    lines.push([
                        row.shipper,
                        row.trips,
                        row.unique_units,
                        row.unit_uses,
                        row.top_unit,
                        row.top_unit_uses
                    ].map(csvEscape).join(','));
                });

                const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
                downloadBlob(blob, `utilization-report-${state.period}-${state.lastData.date_from}_${state.lastData.date_to}.csv`);
            }

            function exportExcel() {
                if (!state.lastData) return;
                if (typeof XLSX === 'undefined') { alert('Excel library failed to load.'); return; }

                const wb = XLSX.utils.book_new();
                const truckSheet = XLSX.utils.json_to_sheet((state.lastData.truck_breakdown || []).map(row => ({
                    Series: row.shipper,
                    Trips: row.trips,
                    'Unique Trucks': row.unique_units,
                    'Truck Uses': row.unit_uses,
                    'Top Truck': row.top_unit,
                    'Top Truck Uses': row.top_unit_uses
                })));
                const trailerSheet = XLSX.utils.json_to_sheet((state.lastData.trailer_breakdown || []).map(row => ({
                    Series: row.shipper,
                    Trips: row.trips,
                    'Unique Trailers': row.unique_units,
                    'Trailer Uses': row.unit_uses,
                    'Top Trailer': row.top_unit,
                    'Top Trailer Uses': row.top_unit_uses
                })));

                truckSheet['!cols'] = [{ wch: 28 }, { wch: 10 }, { wch: 14 }, { wch: 12 }, { wch: 18 }, { wch: 14 }];
                trailerSheet['!cols'] = [{ wch: 28 }, { wch: 10 }, { wch: 16 }, { wch: 13 }, { wch: 18 }, { wch: 16 }];

                XLSX.utils.book_append_sheet(wb, truckSheet, 'Truck Utilization');
                XLSX.utils.book_append_sheet(wb, trailerSheet, 'Trailer Utilization');
                XLSX.writeFile(wb, `utilization-report-${state.period}-${state.lastData.date_from}_${state.lastData.date_to}.xlsx`);
            }

            periodButtons.forEach(btn => {
                btn.addEventListener('click', function () {
                    state.period = this.dataset.period;
                    setActiveButton(periodButtons, state.period, 'period');
                    state.dateFrom = '';
                    state.dateTo = '';
                    dateFromInput.value = '';
                    dateToInput.value = '';
                    loadReport();
                });
            });

            document.getElementById('applyFilters').addEventListener('click', function () {
                state.dateFrom = dateFromInput.value;
                state.dateTo = dateToInput.value;
                loadReport();
            });

            document.getElementById('resetPeriod').addEventListener('click', function () {
                state.dateFrom = '';
                state.dateTo = '';
                dateFromInput.value = '';
                dateToInput.value = '';
                loadReport();
            });

            document.getElementById('exportCsv').addEventListener('click', exportCsv);
            document.getElementById('exportExcel').addEventListener('click', exportExcel);

            loadReport();
        });
    </script>
</body>
</html>
