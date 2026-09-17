<?php include "php/session-check.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/DataTables/datatables.min.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .analytics-toggle .btn.active {
            background-color: #0d6efd;
            color: #fff;
        }
        .report-type-toggle .btn.active {
            background-color: #198754;
            color: #fff;
            border-color: #198754;
        }
        .analytics-summary .stat-card h3 {
            font-size: 1.85rem;
        }
        .top-series-card h3 {
            font-size: 1.25rem;
            line-height: 1.2;
        }
        .chart-card canvas {
            max-height: 320px;
        }
        .breakdown-table th,
        .breakdown-table td {
            white-space: nowrap;
        }
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
                        <h2 class="fw-bold mb-1">Analytics</h2>
                        <p class="text-muted mb-0" id="reportDescription">
                            Boxed bananas trips, loads &amp; kms breakdown by shipper.
                            <span class="text-secondary">Date is taken from loading start/finish (8&nbsp;AM cutoff rule), not entry creation date.</span>
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
                                <label class="form-label fw-semibold mb-2 d-block">Report Type</label>
                                <div class="btn-group report-type-toggle" role="group" id="reportTypeToggle">
                                    <button type="button" class="btn btn-outline-success active" data-report="boxed_banana">
                                        <i class="bi bi-box-seam me-1"></i>Boxed Banana
                                    </button>
                                    <button type="button" class="btn btn-outline-success" data-report="other_hauling">
                                        <i class="bi bi-truck-flatbed me-1"></i>Other Hauling
                                    </button>
                                </div>
                            </div>
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
                            <div class="col-md-3" id="entryTypeFilterCol" hidden>
                                <label for="entryTypeFilter" class="form-label fw-semibold">Entry Type</label>
                                <select class="form-select" id="entryTypeFilter">
                                    <option value="RV ENTRY" selected>RV ENTRY (Boxed Bananas)</option>
                                    <option value="DPC_KDs &amp; OPM ENTRY">DPC_KDs &amp; OPM ENTRY</option>
                                    <option value="DRY VAN ENTRY">DRY VAN ENTRY</option>
                                    <option value="OTHERS ENTRY">OTHERS ENTRY</option>
                                    <option value="CARGO TRUCK ENTRY">CARGO TRUCK ENTRY</option>
                                    <option value="ALL">All Entry Types</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="dateFrom" class="form-label fw-semibold">Date From</label>
                                <input type="date" class="form-control" id="dateFrom" style="height: 45px;">
                            </div>
                            <div class="col-md-2">
                                <label for="dateTo" class="form-label fw-semibold">Date To</label>
                                <input type="date" class="form-control" id="dateTo" style="height: 45px;">
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
                            <div class="col-md-auto ms-md-auto" id="exportCsvCol">
                                <button class="btn btn-outline-success" id="exportCsv">
                                    <i class="bi bi-filetype-csv me-1"></i>Export CSV
                                </button>
                                <button class="btn btn-outline-success ms-1" id="exportExcel">
                                    <i class="bi bi-file-earmark-excel me-1"></i>Excel
                                </button>
                            </div>
                        </div>
                        <div class="mt-3 small text-muted" id="rangeLabel">Showing data for the current week.</div>
                    </div>
                </div>

                <!-- ========== BOXED BANANA SECTION ========== -->
                <div id="bbSection">
                    <div class="row g-4 mb-4 analytics-summary">
                        <div class="col-xl-3 col-md-6">
                            <div class="stat-card stat-card-blue">
                                <div class="stat-icon"><i class="bi bi-truck"></i></div>
                                <div class="stat-content">
                                    <h3 id="totalTrips">0</h3>
                                    <p>Total Trips</p>
                                    <small class="stat-change text-muted" id="totalTripsLabel">For the selected period</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="stat-card stat-card-green">
                                <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
                                <div class="stat-content">
                                    <h3 id="totalLoads">0</h3>
                                    <p>Total Loads</p>
                                    <small class="stat-change text-success">Sum of total_load</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="stat-card stat-card-orange">
                                <div class="stat-icon"><i class="bi bi-signpost-split"></i></div>
                                <div class="stat-content">
                                    <h3 id="totalKms">0</h3>
                                    <p>Total KMs</p>
                                    <small class="stat-change text-warning">Distance covered</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="stat-card stat-card-purple">
                                <div class="stat-icon"><i class="bi bi-people"></i></div>
                                <div class="stat-content">
                                    <h3 id="activeShippers">0</h3>
                                    <p>Active Shippers</p>
                                    <small class="stat-change text-success">Shippers with entries</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-xl-8">
                            <div class="card chart-card h-100">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><span id="chartShipperTitle">Trips by Shipper</span></h5>
                                    <small class="text-muted" id="chartShipperSubtitle">Weekly</small>
                                </div>
                                <div class="card-body">
                                    <canvas id="shipperBarChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4">
                            <div class="card chart-card h-100">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Shipper Distribution</h5>
                                </div>
                                <div class="card-body d-flex align-items-center justify-content-center">
                                    <canvas id="shipperDoughnutChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-12">
                            <div class="card chart-card">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Daily Trend</h5>
                                    <div class="btn-group btn-group-sm" role="group" id="trendMetricToggle">
                                        <button type="button" class="btn btn-outline-primary active" data-metric="trips">Trips</button>
                                        <button type="button" class="btn btn-outline-primary" data-metric="loads">Loads</button>
                                        <button type="button" class="btn btn-outline-primary" data-metric="kms">KMs</button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <canvas id="trendLineChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><span id="reportTitle">BOXED BANANAS - WEEKLY REPORT</span></h5>
                            <span class="badge bg-primary" id="reportBadge">RV ENTRY</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover breakdown-table" id="breakdownTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Entry Type</th>
                                            <th>Shipper</th>
                                            <th>Series</th>
                                            <th class="text-end">Trips</th>
                                            <th class="text-end" id="metricHeader">KMs</th>
                                            <th class="text-end">Loads</th>
                                            <th class="text-end">Entries</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ========== END BOXED BANANA ========== -->

                <!-- ========== OTHER HAULING SECTION ========== -->
                <div id="ohSection" style="display:none;">
                    <div class="row g-4 mb-4 analytics-summary">
                        <div class="col-xl-3 col-md-6">
                            <div class="stat-card stat-card-blue">
                                <div class="stat-icon"><i class="bi bi-truck"></i></div>
                                <div class="stat-content">
                                    <h3 id="ohTotalTrips">0</h3>
                                    <p>Total Trips</p>
                                    <small class="stat-change text-muted" id="ohTotalTripsLabel">For the selected period</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="stat-card stat-card-green">
                                <div class="stat-icon"><i class="bi bi-collection"></i></div>
                                <div class="stat-content">
                                    <h3 id="ohActiveCategories">0</h3>
                                    <p>Active Categories</p>
                                    <small class="stat-change text-success">Categories with trips</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="stat-card stat-card-orange">
                                <div class="stat-icon"><i class="bi bi-list-check"></i></div>
                                <div class="stat-content">
                                    <h3 id="ohCategoriesCount">0</h3>
                                    <p>Total Categories</p>
                                    <small class="stat-change text-warning">Tracked series</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="stat-card stat-card-purple top-series-card">
                                <div class="stat-icon"><i class="bi bi-trophy"></i></div>
                                <div class="stat-content">
                                    <h3 id="ohTopSeries">-</h3>
                                    <p>Top Series</p>
                                    <small class="stat-change text-success" id="ohTopSeriesTrips">0 trips</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-xl-8">
                            <div class="card chart-card h-100">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Trips by Series</h5>
                                    <small class="text-muted" id="ohSeriesSubtitle">Weekly</small>
                                </div>
                                <div class="card-body">
                                    <canvas id="ohSeriesBarChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4">
                            <div class="card chart-card h-100">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Entry Type Distribution</h5>
                                </div>
                                <div class="card-body d-flex align-items-center justify-content-center">
                                    <canvas id="ohEntryTypeDoughnut"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-12">
                            <div class="card chart-card">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Daily Trend</h5>
                                    <small class="text-muted">Trips per day</small>
                                </div>
                                <div class="card-body">
                                    <canvas id="ohTrendLineChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><span id="ohReportTitle">OTHER HAULING - WEEKLY REPORT</span></h5>
                            <span class="badge bg-primary" id="ohReportBadge">Total: 0</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover breakdown-table" id="ohBreakdownTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Entry Type</th>
                                            <th>Series</th>
                                            <th class="text-end">Trips</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ===== Hustling Weekly Report ===== -->
                    <style>
                        #hustlingWeeklyTable { border-collapse: collapse; }
                        #hustlingWeeklyTable thead th { background: #F2A900; color: #1f2937; font-weight: 700; text-align: center; white-space: nowrap; }
                        #hustlingWeeklyTable thead th:first-child { text-align: left; }
                        #hustlingWeeklyTable tbody th { background: #fff; font-weight: 700; text-align: left; white-space: nowrap; }
                        #hustlingWeeklyTable td { text-align: center; }
                        #hustlingWeeklyTable td, #hustlingWeeklyTable th { border: 1px solid #d1d5db; }
                        #hustlingWeeklyTable tbody tr:nth-child(odd) td { background: #f3f4f6; }
                    </style>
                    <div class="card mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5 class="mb-0"><i class="bi bi-calendar-week me-2"></i>Container Hustling — Weekly Report</h5>
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <button type="button" class="btn btn-sm btn-primary" id="hustlingWeeklyGenerateBtn">
                                    <i class="bi bi-search me-1"></i>Generate
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" id="hustlingWeeklyExportCsv">
                                    <i class="bi bi-filetype-csv me-1"></i>CSV
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" id="hustlingWeeklyExportExcel">
                                    <i class="bi bi-file-earmark-excel me-1"></i>Excel
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="small text-muted mb-2" id="hustlingWeeklyMeta">Uses the date range above (defaults to the current operational week). Total trips per unit per day.</div>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0" id="hustlingWeeklyTable">
                                    <thead><tr><th>Unit</th></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ===== Hustling Detail Report ===== -->
                    <div class="card mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Container Hustling — Detail Report</h5>
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <select class="form-select form-select-sm" id="hustlingSeriesFilter" style="width:auto;">
                                    <option value="DICT" selected>Container Hustling- DICT</option>
                                    <option value="DOLE">Container Hustling- DOLE</option>
                                    <option value="ALL">All Hustling</option>
                                </select>
                                <button type="button" class="btn btn-sm btn-primary" id="hustlingGenerateBtn">
                                    <i class="bi bi-search me-1"></i>Generate
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" id="hustlingExportCsv">
                                    <i class="bi bi-filetype-csv me-1"></i>CSV
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" id="hustlingExportExcel">
                                    <i class="bi bi-file-earmark-excel me-1"></i>Excel
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="small text-muted mb-2" id="hustlingMeta">Pick a series and click Generate. Uses the same date range as above.</div>
                            <div class="table-responsive">
                                <table class="table table-hover breakdown-table" id="hustlingDetailTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Trip Receipt</th>
                                            <th>Truck</th>
                                            <th>TR</th>
                                            <th>Driver</th>
                                            <th class="text-end">Load Qty / Weight</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ========== END OTHER HAULING ========== -->

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
            $("#anav").attr({ "class": "nav-link active" });
        });

        document.addEventListener('DOMContentLoaded', function () {
            const palette = [
                '#0d6efd', '#198754', '#fd7e14', '#dc3545', '#6f42c1',
                '#20c997', '#ffc107', '#0dcaf0', '#d63384', '#6610f2',
                '#0a3d91', '#54a154', '#b94e00', '#a52834'
            ];

            const state = {
                report: 'boxed_banana',
                period: 'weekly',
                entryType: 'RV ENTRY',
                dateFrom: '',
                dateTo: '',
                trendMetric: 'trips',
                bbSelectedShipper: '',
                ohSelectedSeries: '',
                lastBB: null,
                lastOH: null
            };

            const reportButtons = document.querySelectorAll('#reportTypeToggle button');
            const periodButtons = document.querySelectorAll('#periodToggle button');
            const trendButtons = document.querySelectorAll('#trendMetricToggle button');
            const dateFromInput = document.getElementById('dateFrom');
            const dateToInput = document.getElementById('dateTo');
            const entryTypeSelect = document.getElementById('entryTypeFilter');
            const entryTypeFilterCol = document.getElementById('entryTypeFilterCol');
            const exportCsvCol = document.getElementById('exportCsvCol');
            const bbSection = document.getElementById('bbSection');
            const ohSection = document.getElementById('ohSection');
            const reportDescription = document.getElementById('reportDescription');

            // Boxed Banana charts
            let shipperBarChart = null;
            let shipperDoughnutChart = null;
            let trendLineChart = null;
            let breakdownTable = null;

            // Other Hauling charts
            let ohSeriesBarChart = null;
            let ohEntryTypeDoughnut = null;
            let ohTrendLineChart = null;
            let ohBreakdownTable = null;

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
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
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const year = date.getFullYear();
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                return `${month}/${day}/${year} ${hours}:${minutes}`;
            }

            function setActiveButton(buttons, value, attr) {
                buttons.forEach(btn => {
                    if (btn.dataset[attr] === value) btn.classList.add('active');
                    else btn.classList.remove('active');
                });
            }

            // -------- Boxed Banana --------
            function bbBuildQuery() {
                const params = new URLSearchParams();
                params.set('period', state.period);
                if (state.entryType) params.set('entry_type', state.entryType);
                if (state.dateFrom) params.set('date_from', state.dateFrom);
                if (state.dateTo) params.set('date_to', state.dateTo);
                return params.toString();
            }

            function bbUpdateSummary(totals) {
                document.getElementById('totalTrips').textContent = formatNumber(totals.trips);
                document.getElementById('totalLoads').textContent = formatNumber(totals.loads);
                document.getElementById('totalKms').textContent = formatNumber(totals.kms);
                document.getElementById('activeShippers').textContent = formatNumber(totals.shippers_active);
            }

            function bbUpdateRangeLabel(data) {
                const range = `${data.date_from} to ${data.date_to}`;
                document.getElementById('rangeLabel').textContent = state.period === 'weekly'
                    ? `Showing weekly data: ${range}`
                    : `Showing monthly data: ${range}`;
                document.getElementById('reportTitle').textContent = state.period === 'weekly'
                    ? 'BOXED BANANAS - WEEKLY REPORT'
                    : 'BOXED BANANAS - MONTHLY REPORT';
                document.getElementById('reportBadge').textContent = state.entryType || 'ALL';
                const periodLabel = state.period === 'weekly' ? 'Weekly' : 'Monthly';
                document.getElementById('chartShipperSubtitle').textContent = state.bbSelectedShipper
                    ? `${periodLabel} • ${state.bbSelectedShipper}`
                    : periodLabel;
                document.getElementById('totalTripsLabel').textContent = `Period: ${range}`;
            }

            function bbUpdateShipperCharts(breakdown) {
                const labels = breakdown.map(row => row.shipper);
                const tripValues = breakdown.map(row => row.trips);
                const colors = labels.map((_, i) => palette[i % palette.length]);

                if (shipperBarChart) shipperBarChart.destroy();
                shipperBarChart = new Chart(document.getElementById('shipperBarChart'), {
                    type: 'bar',
                    data: { labels, datasets: [{ label: 'Total Trips', data: tripValues, backgroundColor: colors, borderRadius: 6 }] },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                        onClick: (_, elements, chart) => {
                            const clicked = elements[0];
                            if (!clicked) return;
                            const selectedShipper = chart.data.labels?.[clicked.index] || '';
                            state.bbSelectedShipper = state.bbSelectedShipper === selectedShipper ? '' : selectedShipper;
                            bbUpdateRangeLabel(state.lastBB || { date_from: '', date_to: '' });
                            if (state.lastBB) {
                                bbUpdateTrendChart(state.lastBB);
                            }
                        }
                    }
                });

                if (shipperDoughnutChart) shipperDoughnutChart.destroy();
                shipperDoughnutChart = new Chart(document.getElementById('shipperDoughnutChart'), {
                    type: 'doughnut',
                    data: { labels, datasets: [{ data: tripValues, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } }
                    }
                });
            }

            function bbUpdateTrendChart(data) {
                const metric = state.trendMetric;
                const trend = state.bbSelectedShipper
                    ? ((data.shipper_trend || {})[state.bbSelectedShipper] || data.trend || { labels: [], trips: [], loads: [], kms: [] })
                    : (data.trend || { labels: [], trips: [], loads: [], kms: [] });
                const dataset = trend[metric] || [];
                const colorMap = { trips: '#0d6efd', loads: '#198754', kms: '#fd7e14' };
                const labelMap = { trips: 'Daily Trips', loads: 'Daily Loads', kms: 'Daily KMs' };
                const label = state.bbSelectedShipper ? `${labelMap[metric]} - ${state.bbSelectedShipper}` : labelMap[metric];

                if (trendLineChart) trendLineChart.destroy();
                trendLineChart = new Chart(document.getElementById('trendLineChart'), {
                    type: 'line',
                    data: {
                        labels: trend.labels || [],
                        datasets: [{
                            label, data: dataset,
                            borderColor: colorMap[metric], backgroundColor: colorMap[metric] + '22',
                            borderWidth: 3, fill: true, tension: 0.35,
                            pointRadius: 4, pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: true,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            }

            function bbUpdateBreakdownTable(breakdown) {
                // KMs and Loads are now shown as their own columns (no metric toggle).
                document.getElementById('metricHeader').textContent = 'KMs';

                if (breakdownTable) {
                    breakdownTable.destroy();
                    document.querySelector('#breakdownTable tbody').innerHTML = '';
                }

                const rows = breakdown.map(row => {
                    return [
                        '<span class="badge bg-secondary">' + escapeHtml(state.entryType || 'ALL') + '</span>',
                        escapeHtml(row.shipper),
                        escapeHtml(row.series),
                        formatNumber(row.trips),
                        formatNumber(row.kms),
                        formatNumber(row.loads),
                        formatNumber(row.entries)
                    ];
                });

                breakdownTable = new DataTable('#breakdownTable', {
                    data: rows,
                    pageLength: 15,
                    order: [[3, 'desc']],
                    columnDefs: [{ targets: [3, 4, 5, 6], className: 'text-end' }]
                });
            }

            async function bbLoad() {
                document.getElementById('lastUpdated').textContent = 'Loading...';
                try {
                    const response = await fetch('php/fetch/get_analytics_data.php?' + bbBuildQuery(), { cache: 'no-store' });
                    const data = await response.json();
                    if (!data.success) throw new Error(data.message || 'Failed to load analytics.');
                    state.bbSelectedShipper = '';
                    state.lastBB = data;
                    if (!state.dateFrom) dateFromInput.value = data.date_from;
                    if (!state.dateTo) dateToInput.value = data.date_to;
                    bbUpdateSummary(data.totals || {});
                    bbUpdateRangeLabel(data);
                    bbUpdateShipperCharts(data.breakdown || []);
                    bbUpdateTrendChart(data);
                    bbUpdateBreakdownTable(data.breakdown || []);
                    document.getElementById('lastUpdated').textContent = formatDateTime(data.generated_at);
                } catch (error) {
                    document.getElementById('lastUpdated').textContent = 'Refresh failed';
                    console.error(error);
                }
            }

            // -------- Other Hauling --------
            function ohBuildQuery() {
                const params = new URLSearchParams();
                params.set('period', state.period);
                if (state.dateFrom) params.set('date_from', state.dateFrom);
                if (state.dateTo) params.set('date_to', state.dateTo);
                return params.toString();
            }

            function ohUpdateSummary(data) {
                const totals = data.totals || {};
                document.getElementById('ohTotalTrips').textContent = formatNumber(totals.trips);
                document.getElementById('ohActiveCategories').textContent = formatNumber(totals.active_categories);
                document.getElementById('ohCategoriesCount').textContent = formatNumber(totals.categories);
                document.getElementById('ohTopSeries').textContent = totals.top_series || '-';
                document.getElementById('ohTopSeriesTrips').textContent = `${formatNumber(totals.top_trips)} trips`;
                document.getElementById('ohReportBadge').textContent = `Total: ${formatNumber(totals.trips)}`;
            }

            function ohUpdateRangeLabel(data) {
                const range = `${data.date_from} to ${data.date_to}`;
                document.getElementById('rangeLabel').textContent = state.period === 'weekly'
                    ? `Showing weekly data: ${range}`
                    : `Showing monthly data: ${range}`;
                document.getElementById('ohReportTitle').textContent = state.period === 'weekly'
                    ? 'OTHER HAULING - WEEKLY REPORT'
                    : 'OTHER HAULING - MONTHLY REPORT';
                const periodLabel = state.period === 'weekly' ? 'Weekly' : 'Monthly';
                document.getElementById('ohSeriesSubtitle').textContent = state.ohSelectedSeries
                    ? `${periodLabel} • ${state.ohSelectedSeries}`
                    : periodLabel;
                document.getElementById('ohTotalTripsLabel').textContent = `Period: ${range}`;
            }

            function ohUpdateSeriesBarChart(rows) {
                const sorted = [...rows].sort((a, b) => b.trips - a.trips);
                const labels = sorted.map(r => r.series);
                const tripValues = sorted.map(r => r.trips);
                const colors = labels.map((_, i) => palette[i % palette.length]);

                if (ohSeriesBarChart) ohSeriesBarChart.destroy();
                ohSeriesBarChart = new Chart(document.getElementById('ohSeriesBarChart'), {
                    type: 'bar',
                    data: { labels, datasets: [{ label: 'Trips', data: tripValues, backgroundColor: colors, borderRadius: 6 }] },
                    options: {
                        indexAxis: 'y',
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
                        onClick: (_, elements, chart) => {
                            const clicked = elements[0];
                            if (!clicked) return;
                            const selectedSeries = chart.data.labels?.[clicked.index] || '';
                            state.ohSelectedSeries = state.ohSelectedSeries === selectedSeries ? '' : selectedSeries;
                            ohUpdateRangeLabel(state.lastOH || { date_from: '', date_to: '' });
                            if (state.lastOH) {
                                ohUpdateTrendChart(state.lastOH);
                            }
                        }
                    }
                });
            }

            function ohUpdateEntryTypeDoughnut(byEntryType) {
                const labels = Object.keys(byEntryType || {});
                const values = labels.map(k => byEntryType[k]);
                const colors = labels.map((_, i) => palette[i % palette.length]);

                if (ohEntryTypeDoughnut) ohEntryTypeDoughnut.destroy();
                ohEntryTypeDoughnut = new Chart(document.getElementById('ohEntryTypeDoughnut'), {
                    type: 'doughnut',
                    data: { labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
                    }
                });
            }

            function ohUpdateTrendChart(data) {
                const selectedSeries = state.ohSelectedSeries;
                const trend = selectedSeries
                    ? ((data.series_trend || {})[selectedSeries] || { labels: data.trend?.labels || [], trips: [] })
                    : (data.trend || { labels: [], trips: [] });
                const label = selectedSeries ? `Daily Trips - ${selectedSeries}` : 'Daily Trips';

                if (ohTrendLineChart) ohTrendLineChart.destroy();
                ohTrendLineChart = new Chart(document.getElementById('ohTrendLineChart'), {
                    type: 'line',
                    data: {
                        labels: trend.labels || [],
                        datasets: [{
                            label,
                            data: trend.trips || [],
                            borderColor: '#0d6efd', backgroundColor: '#0d6efd22',
                            borderWidth: 3, fill: true, tension: 0.35,
                            pointRadius: 4, pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: true,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            }

            function ohUpdateBreakdownTable(rows) {
                if (ohBreakdownTable) {
                    ohBreakdownTable.destroy();
                    document.querySelector('#ohBreakdownTable tbody').innerHTML = '';
                }
                const tableRows = rows.map(row => [
                    escapeHtml(row.entry_type),
                    escapeHtml(row.series),
                    formatNumber(row.trips)
                ]);
                ohBreakdownTable = new DataTable('#ohBreakdownTable', {
                    data: tableRows,
                    pageLength: 25,
                    order: [],
                    columnDefs: [{ targets: [2], className: 'text-end' }]
                });
            }

            async function ohLoad() {
                document.getElementById('lastUpdated').textContent = 'Loading...';
                try {
                    const response = await fetch('php/fetch/get_other_hauling_weekly.php?' + ohBuildQuery(), { cache: 'no-store' });
                    const data = await response.json();
                    if (!data.success) throw new Error(data.message || 'Failed to load report.');
                    state.ohSelectedSeries = '';
                    state.lastOH = data;
                    if (!state.dateFrom) dateFromInput.value = data.date_from;
                    if (!state.dateTo) dateToInput.value = data.date_to;
                    ohUpdateSummary(data);
                    ohUpdateRangeLabel(data);
                    ohUpdateSeriesBarChart(data.rows || []);
                    ohUpdateEntryTypeDoughnut(data.by_entry_type || {});
                    ohUpdateTrendChart(data);
                    ohUpdateBreakdownTable(data.rows || []);
                    document.getElementById('lastUpdated').textContent = formatDateTime(data.generated_at);
                } catch (error) {
                    document.getElementById('lastUpdated').textContent = 'Refresh failed';
                    console.error(error);
                }
            }

            // -------- Routing --------
            function loadCurrent() {
                if (state.report === 'boxed_banana') bbLoad();
                else ohLoad();
            }

            function applyReportTypeUI() {
                if (state.report === 'boxed_banana') {
                    bbSection.style.display = '';
                    ohSection.style.display = 'none';
                    entryTypeFilterCol.style.display = '';
                    reportDescription.innerHTML = 'Boxed bananas trips, loads &amp; kms breakdown by shipper. <span class="text-secondary">Date is taken from loading start/finish (8&nbsp;AM cutoff rule), not entry creation date.</span>';
                } else {
                    bbSection.style.display = 'none';
                    ohSection.style.display = '';
                    entryTypeFilterCol.style.display = 'none';
                    reportDescription.innerHTML = 'Trip counts grouped by entry type, system identification, and series. <span class="text-secondary">Operational week runs Friday &rarr; Thursday. Effective date uses operational dates with fallback to created date.</span>';
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

            // ---- Boxed Banana exports ----
            function bbBuildExportRows() {
                const breakdown = (state.lastBB && state.lastBB.breakdown) || [];
                const headers = ['Entry Type', 'Shipper', 'Series', 'Trips', 'KMs', 'Loads', 'Entries'];
                const rows = breakdown.map(r => [
                    state.entryType || 'ALL',
                    r.shipper || '',
                    r.series || '',
                    Number(r.trips || 0),
                    Number(r.kms || 0),
                    Number(r.loads || 0),
                    Number(r.entries || 0),
                ]);
                return { headers, rows };
            }

            function bbFilenameTag() {
                if (!state.lastBB) return state.period;
                return `${state.lastBB.date_from}_${state.lastBB.date_to}`;
            }

            function bbExportCsv() {
                if (!state.lastBB) return;
                const { headers, rows } = bbBuildExportRows();
                const lines = [headers.map(csvEscape).join(',')];
                rows.forEach(r => lines.push(r.map(csvEscape).join(',')));
                const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
                downloadBlob(blob, `boxed-banana-${state.period}-${bbFilenameTag()}.csv`);
            }

            function bbExportExcel() {
                if (!state.lastBB) return;
                if (typeof XLSX === 'undefined') { alert('Excel library failed to load.'); return; }
                const { headers, rows } = bbBuildExportRows();
                const aoa = [headers, ...rows];
                const wb = XLSX.utils.book_new();
                const ws = XLSX.utils.aoa_to_sheet(aoa);
                ws['!cols'] = headers.map(h => ({ wch: Math.max(12, String(h).length + 2) }));
                XLSX.utils.book_append_sheet(wb, ws, 'Boxed Banana');
                XLSX.writeFile(wb, `boxed-banana-${state.period}-${bbFilenameTag()}.xlsx`);
            }

            // ---- Other Hauling exports ----
            function ohBuildExportRows() {
                const headers = ['Entry Type', 'Series', 'Trips'];
                const rows = ((state.lastOH && state.lastOH.rows) || []).map(r => [
                    r.entry_type || '',
                    r.series || '',
                    Number(r.trips || 0),
                ]);
                return { headers, rows };
            }

            function ohFilenameTag() {
                if (!state.lastOH) return state.period;
                return `${state.lastOH.date_from}_${state.lastOH.date_to}`;
            }

            function ohExportCsv() {
                if (!state.lastOH) return;
                const { headers, rows } = ohBuildExportRows();
                const lines = [headers.map(csvEscape).join(',')];
                rows.forEach(r => lines.push(r.map(csvEscape).join(',')));
                const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
                downloadBlob(blob, `other-hauling-${state.period}-${ohFilenameTag()}.csv`);
            }

            function ohExportExcel() {
                if (!state.lastOH) return;
                if (typeof XLSX === 'undefined') { alert('Excel library failed to load.'); return; }
                const { headers, rows } = ohBuildExportRows();
                const aoa = [headers, ...rows];
                const wb = XLSX.utils.book_new();
                const ws = XLSX.utils.aoa_to_sheet(aoa);
                ws['!cols'] = headers.map(h => ({ wch: Math.max(14, String(h).length + 2) }));
                XLSX.utils.book_append_sheet(wb, ws, 'Other Hauling');
                XLSX.writeFile(wb, `other-hauling-${state.period}-${ohFilenameTag()}.xlsx`);
            }

            function exportCurrentCsv() {
                if (state.report === 'boxed_banana') bbExportCsv();
                else ohExportCsv();
            }

            function exportCurrentExcel() {
                if (state.report === 'boxed_banana') bbExportExcel();
                else ohExportExcel();
            }

            // -------- Container Hustling Detail --------
            const hustlingSeriesFilter = document.getElementById('hustlingSeriesFilter');
            const hustlingGenerateBtn = document.getElementById('hustlingGenerateBtn');
            const hustlingMeta = document.getElementById('hustlingMeta');
            let hustlingDetailTable = null;
            let hustlingLast = null;

            function hustlingCurrentDateRange() {
                // Prefer explicit user-picked dates; fall back to last OH response.
                const from = dateFromInput.value || (state.lastOH && state.lastOH.date_from) || '';
                const to = dateToInput.value || (state.lastOH && state.lastOH.date_to) || '';
                return { from, to };
            }

            async function hustlingLoad() {
                const { from, to } = hustlingCurrentDateRange();
                if (!from || !to) {
                    alert('Pick a date range first (run Apply on the main filter).');
                    return;
                }
                const params = new URLSearchParams({
                    series: hustlingSeriesFilter.value,
                    date_from: from,
                    date_to: to,
                });
                hustlingMeta.textContent = 'Loading…';
                try {
                    const res = await fetch('php/fetch/get_hustling_detail.php?' + params.toString(), { cache: 'no-store' });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Load failed');
                    hustlingLast = data;
                    hustlingRender(data);
                } catch (err) {
                    hustlingMeta.textContent = 'Error: ' + err.message;
                }
            }

            function hustlingRender(data) {
                hustlingMeta.textContent =
                    `${data.series} · ${data.date_from} to ${data.date_to} · ${data.row_count} entr${data.row_count === 1 ? 'y' : 'ies'}` +
                    (data.total_load ? ` · Total load: ${Number(data.total_load).toLocaleString()}` : '');

                if (hustlingDetailTable) {
                    hustlingDetailTable.destroy();
                    document.querySelector('#hustlingDetailTable tbody').innerHTML = '';
                }

                const tableRows = (data.rows || []).map(r => [
                    escapeHtml(r.date || ''),
                    escapeHtml(r.waybill || ''),
                    escapeHtml(r.truck || ''),
                    escapeHtml(r.tr || ''),
                    escapeHtml(r.driver || ''),
                    escapeHtml(r.load_quantity_weight || '')
                ]);

                hustlingDetailTable = new DataTable('#hustlingDetailTable', {
                    data: tableRows,
                    pageLength: 25,
                    order: [],
                    columnDefs: [{ targets: [5], className: 'text-end' }]
                });
            }

            function hustlingBuildExportRows() {
                const headers = ['Date', 'Trip Receipt', 'Truck', 'TR', 'Driver', 'Load Qty/Weight'];
                const rows = (hustlingLast && hustlingLast.rows ? hustlingLast.rows : []).map(r => {
                    const numeric = Number(r.load_quantity_weight);
                    const loadCell = Number.isFinite(numeric) && r.load_quantity_weight !== ''
                        ? numeric
                        : (r.load_quantity_weight || '');
                    return [
                        r.date || '',
                        r.waybill || '',
                        r.truck || '',
                        r.tr || '',
                        r.driver || '',
                        loadCell
                    ];
                });
                return { headers, rows };
            }

            function hustlingFilenameTag() {
                if (!hustlingLast) return 'no-data';
                const safe = (hustlingLast.series || 'hustling').replace(/[^A-Za-z0-9_-]+/g, '_');
                return `${safe}_${hustlingLast.date_from}_${hustlingLast.date_to}`;
            }

            function hustlingExportCsv() {
                if (!hustlingLast) { alert('Generate the report first.'); return; }
                const { headers, rows } = hustlingBuildExportRows();
                const lines = [headers.map(csvEscape).join(',')];
                rows.forEach(r => lines.push(r.map(csvEscape).join(',')));
                const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
                downloadBlob(blob, `hustling-${hustlingFilenameTag()}.csv`);
            }

            function hustlingExportExcel() {
                if (!hustlingLast) { alert('Generate the report first.'); return; }
                if (typeof XLSX === 'undefined') { alert('Excel library failed to load.'); return; }
                const { headers, rows } = hustlingBuildExportRows();
                const aoa = [headers, ...rows];
                const wb = XLSX.utils.book_new();
                const ws = XLSX.utils.aoa_to_sheet(aoa);
                ws['!cols'] = headers.map(h => ({ wch: Math.max(14, String(h).length + 2) }));
                XLSX.utils.book_append_sheet(wb, ws, 'Hustling Detail');
                XLSX.writeFile(wb, `hustling-${hustlingFilenameTag()}.xlsx`);
            }

            hustlingGenerateBtn.addEventListener('click', hustlingLoad);
            document.getElementById('hustlingExportCsv').addEventListener('click', hustlingExportCsv);
            document.getElementById('hustlingExportExcel').addEventListener('click', hustlingExportExcel);

            // -------- Container Hustling Weekly (pivot: unit x day, cell = trips) --------
            const hwGenerateBtn = document.getElementById('hustlingWeeklyGenerateBtn');
            const hwMeta = document.getElementById('hustlingWeeklyMeta');
            const hwTable = document.getElementById('hustlingWeeklyTable');
            let hwLast = null;

            function hwFmtDay(iso) {
                const parts = String(iso).split('-');
                return parts.length === 3 ? `${parts[1]}.${parts[2]}.${parts[0]}` : iso;
            }

            async function hustlingWeeklyLoad() {
                const { from, to } = hustlingCurrentDateRange();
                if (!from || !to) {
                    alert('Pick a date range first (run Apply on the main filter).');
                    return;
                }
                const params = new URLSearchParams({ date_from: from, date_to: to });
                hwMeta.textContent = 'Loading…';
                try {
                    const res = await fetch('php/fetch/get_hustling_weekly.php?' + params.toString(), { cache: 'no-store' });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Load failed');
                    hwLast = data;
                    hustlingWeeklyRender(data);
                } catch (err) {
                    hwMeta.textContent = 'Error: ' + err.message;
                }
            }

            function hustlingWeeklyRender(data) {
                const days = data.days || [];
                const units = data.units || [];
                hwMeta.textContent = `${data.date_from} to ${data.date_to} · ${units.length} unit${units.length === 1 ? '' : 's'} · total trips per unit per day`;

                const thead = hwTable.querySelector('thead');
                const tbody = hwTable.querySelector('tbody');
                thead.innerHTML = '<tr>' + ['Unit', ...days.map(hwFmtDay)]
                    .map(h => `<th>${escapeHtml(h)}</th>`).join('') + '</tr>';

                if (!units.length) {
                    tbody.innerHTML = `<tr><td colspan="${days.length + 1}" class="text-center text-muted">No hustling records in this range.</td></tr>`;
                    return;
                }
                // Blank out empty days and recorded zeros, matching the report format.
                tbody.innerHTML = units.map(u => {
                    const cells = u.values
                        .map(v => `<td>${(v === null || v === 0) ? '' : escapeHtml(String(v))}</td>`)
                        .join('');
                    return `<tr><th>${escapeHtml(u.unit)}</th>${cells}</tr>`;
                }).join('');
            }

            function hwBuildAoa() {
                const days = (hwLast && hwLast.days) || [];
                const units = (hwLast && hwLast.units) || [];
                const headers = ['Unit', ...days.map(hwFmtDay)];
                const rows = units.map(u => [u.unit, ...u.values.map(v => (v === null || v === 0) ? '' : v)]);
                return [headers, ...rows];
            }

            function hwFilenameTag() {
                return hwLast ? `${hwLast.date_from}_${hwLast.date_to}` : 'no-data';
            }

            function hustlingWeeklyExportCsv() {
                if (!hwLast) { alert('Generate the report first.'); return; }
                const aoa = hwBuildAoa();
                const lines = aoa.map(r => r.map(csvEscape).join(','));
                const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
                downloadBlob(blob, `hustling-weekly-${hwFilenameTag()}.csv`);
            }

            function hustlingWeeklyExportExcel() {
                if (!hwLast) { alert('Generate the report first.'); return; }
                if (typeof XLSX === 'undefined') { alert('Excel library failed to load.'); return; }
                const aoa = hwBuildAoa();
                const wb = XLSX.utils.book_new();
                const ws = XLSX.utils.aoa_to_sheet(aoa);
                ws['!cols'] = aoa[0].map((h, i) => ({ wch: i === 0 ? 12 : 11 }));
                XLSX.utils.book_append_sheet(wb, ws, 'Hustling Weekly');
                XLSX.writeFile(wb, `hustling-weekly-${hwFilenameTag()}.xlsx`);
            }

            hwGenerateBtn.addEventListener('click', hustlingWeeklyLoad);
            document.getElementById('hustlingWeeklyExportCsv').addEventListener('click', hustlingWeeklyExportCsv);
            document.getElementById('hustlingWeeklyExportExcel').addEventListener('click', hustlingWeeklyExportExcel);

            // -------- Event wiring --------
            reportButtons.forEach(btn => {
                btn.addEventListener('click', function () {
                    state.report = this.dataset.report;
                    setActiveButton(reportButtons, state.report, 'report');
                    state.dateFrom = '';
                    state.dateTo = '';
                    dateFromInput.value = '';
                    dateToInput.value = '';
                    applyReportTypeUI();
                    loadCurrent();
                });
            });

            periodButtons.forEach(btn => {
                btn.addEventListener('click', function () {
                    state.period = this.dataset.period;
                    setActiveButton(periodButtons, state.period, 'period');
                    state.dateFrom = '';
                    state.dateTo = '';
                    dateFromInput.value = '';
                    dateToInput.value = '';
                    loadCurrent();
                });
            });

            trendButtons.forEach(btn => {
                btn.addEventListener('click', function () {
                    state.trendMetric = this.dataset.metric;
                    setActiveButton(trendButtons, state.trendMetric, 'metric');
                    if (state.report === 'boxed_banana' && state.lastBB) {
                        bbUpdateTrendChart(state.lastBB);
                    }
                });
            });

            entryTypeSelect.addEventListener('change', function () {
                state.entryType = this.value;
                if (state.report === 'boxed_banana') bbLoad();
            });

            document.getElementById('applyFilters').addEventListener('click', function () {
                state.dateFrom = dateFromInput.value;
                state.dateTo = dateToInput.value;
                loadCurrent();
            });

            document.getElementById('resetPeriod').addEventListener('click', function () {
                state.dateFrom = '';
                state.dateTo = '';
                dateFromInput.value = '';
                dateToInput.value = '';
                loadCurrent();
            });

            document.getElementById('exportCsv').addEventListener('click', exportCurrentCsv);
            document.getElementById('exportExcel').addEventListener('click', exportCurrentExcel);

            applyReportTypeUI();
            loadCurrent();
        });
    </script>
</body>
</html>
