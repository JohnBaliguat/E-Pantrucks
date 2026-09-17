<?php include "php/session-check.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Performance - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/DataTables/datatables.min.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .analytics-toggle .btn.active { background-color: #0d6efd; color: #fff; }
        .analytics-summary .stat-card h3 { font-size: 1.85rem; }
        .chart-card canvas { max-height: 320px; }
        .driver-table th, .driver-table td { white-space: nowrap; }
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
                        <h2 class="fw-bold mb-1">Driver Performance</h2>
                        <p class="text-muted mb-0">
                            Trips, KMs, and earnings per driver based on operations records.
                            <span class="text-secondary">Effective date uses operational dates with fallback to created date. RV / Dry Van rows split when primary and return drivers differ.</span>
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
                                <h3 id="totalDrivers">0</h3>
                                <p>Total Drivers</p>
                                <small class="stat-change text-muted">Registered in system</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card stat-card-green">
                            <div class="stat-icon"><i class="bi bi-person-check"></i></div>
                            <div class="stat-content">
                                <h3 id="activeDrivers">0</h3>
                                <p>Active Drivers</p>
                                <small class="stat-change text-success">With trips in period</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card stat-card-orange">
                            <div class="stat-icon"><i class="bi bi-truck"></i></div>
                            <div class="stat-content">
                                <h3 id="totalTrips">0</h3>
                                <p>Total Trips</p>
                                <small class="stat-change text-warning">Across all drivers</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card stat-card-purple">
                            <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                            <div class="stat-content">
                                <h3 id="totalEarnings">0.00</h3>
                                <p>Total Earnings</p>
                                <small class="stat-change text-success">Sum of piece rates</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-xl-8">
                            <div class="card chart-card h-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0" id="topDriversTitle">Top 10 Drivers (by Trips)</h5>
                                <div class="d-flex align-items-center gap-2">
                                    <select class="form-select form-select-sm" id="topDriversMode" style="min-width: 170px;">
                                        <option value="top">Highest Trips</option>
                                        <option value="low">Lowest Trips</option>
                                        <option value="none">No Trips</option>
                                    </select>
                                    <small class="text-muted" id="topDriversSubtitle">Weekly</small>
                                </div>
                            </div>
                            <div class="card-body">
                                <canvas id="topDriversChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="card chart-card h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Trips by Series</h5>
                            </div>
                            <div class="card-body d-flex align-items-center justify-content-center">
                                <canvas id="entryTypeDoughnut"></canvas>
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
                                <canvas id="trendLineChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Driver Breakdown</h5>
                        <span class="badge bg-primary" id="driverCountBadge">0 drivers</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover driver-table" id="driverTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Driver</th>
                                        <th>ID Number</th>
                                        <th class="text-end">Trips</th>
                                        <th class="text-end">KMs</th>
                                        <th class="text-end">Earnings</th>
                                        <th>Last Trip</th>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="assets/DataTables/datatables.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <script>
        $(document).ready(function () {
            $("#dpnav").attr({ "class": "nav-link active" });
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
                topDriversMode: 'top',
                selectedSeries: '',
                lastData: null
            };

            const periodButtons = document.querySelectorAll('#periodToggle button');
            const dateFromInput = document.getElementById('dateFrom');
            const dateToInput = document.getElementById('dateTo');
            const topDriversModeInput = document.getElementById('topDriversMode');

            let topDriversChart = null;
            let entryTypeDoughnut = null;
            let trendLineChart = null;
            let driverTable = null;

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

            function formatCurrency(value) {
                const num = Number(value || 0);
                return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
                document.getElementById('totalDrivers').textContent = formatNumber(totals.total_drivers);
                document.getElementById('activeDrivers').textContent = formatNumber(totals.active_drivers);
                document.getElementById('totalTrips').textContent = formatNumber(totals.total_trips);
                document.getElementById('totalEarnings').textContent = formatCurrency(totals.total_earnings);
            }

            function updateRangeLabel(data) {
                const range = `${data.date_from} to ${data.date_to}`;
                document.getElementById('rangeLabel').textContent = state.period === 'weekly'
                    ? `Showing weekly data: ${range}`
                    : `Showing monthly data: ${range}`;
                const periodLabel = state.period === 'weekly' ? 'Weekly' : 'Monthly';
                document.getElementById('topDriversSubtitle').textContent = state.selectedSeries
                    ? `${periodLabel} • Series: ${state.selectedSeries}`
                    : periodLabel;
            }

            function getTopDriversTitle() {
                if (state.topDriversMode === 'low') return 'Top 10 Drivers With Lowest Trips';
                if (state.topDriversMode === 'none') return 'Drivers With No Trips';
                return 'Top 10 Drivers (by Trips)';
            }

            function getTopDriversRows() {
                const data = state.lastData || {};
                const seriesFilter = state.selectedSeries;
                const applySeriesFilter = (rows, includeZeroOnly = false) => {
                    if (!seriesFilter) return rows;
                    return (rows || [])
                        .map(driver => ({
                            ...driver,
                            trips: Number((driver.series_trips || {})[seriesFilter] || 0)
                        }))
                        .filter(driver => includeZeroOnly ? driver.trips === 0 : driver.trips > 0);
                };

                if (state.topDriversMode === 'low') {
                    const rows = seriesFilter
                        ? applySeriesFilter(data.all_drivers || [])
                        : (data.low_trip_drivers || []);
                    return rows
                        .sort((a, b) => a.trips - b.trips || a.earnings - b.earnings || String(a.driver_name).localeCompare(String(b.driver_name)))
                        .slice(0, 10);
                }
                if (state.topDriversMode === 'none') {
                    const rows = seriesFilter
                        ? applySeriesFilter(data.all_drivers || [], true)
                        : (data.no_trip_drivers || []);
                    return rows
                        .sort((a, b) => String(a.driver_name).localeCompare(String(b.driver_name)))
                        .slice(0, 10);
                }
                const rows = seriesFilter
                    ? applySeriesFilter(data.all_drivers || [])
                    : (data.top_drivers || []);
                return rows
                    .sort((a, b) => b.trips - a.trips || b.earnings - a.earnings)
                    .slice(0, 10);
            }

            function updateTopDriversChart() {
                const top = getTopDriversRows();
                const labels = top.map(d => d.driver_name || d.driver_id || 'Unknown Driver');
                const values = top.map(d => Number(d.trips || 0));
                const colors = labels.map((_, i) => palette[i % palette.length]);
                document.getElementById('topDriversTitle').textContent = getTopDriversTitle();

                if (topDriversChart) topDriversChart.destroy();
                topDriversChart = new Chart(document.getElementById('topDriversChart'), {
                    type: 'bar',
                    data: { labels, datasets: [{ label: 'Trips', data: values, backgroundColor: colors, borderRadius: 6 }] },
                    options: {
                        indexAxis: 'y',
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            }

            function updateEntryTypeDoughnut(bySeries) {
                const labels = Object.keys(bySeries || {});
                const values = labels.map(k => bySeries[k]);
                const colors = labels.map((_, i) => palette[i % palette.length]);

                if (entryTypeDoughnut) entryTypeDoughnut.destroy();
                entryTypeDoughnut = new Chart(document.getElementById('entryTypeDoughnut'), {
                    type: 'doughnut',
                    data: { labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
                        onClick: (_, elements, chart) => {
                            const clicked = elements[0];
                            if (!clicked) return;
                            const label = chart.data.labels?.[clicked.index] || '';
                            state.selectedSeries = state.selectedSeries === label ? '' : label;
                            updateRangeLabel(state.lastData || { date_from: '', date_to: '' });
                            updateTopDriversChart();
                        }
                    }
                });
            }

            function updateTrendChart(trend) {
                if (trendLineChart) trendLineChart.destroy();
                trendLineChart = new Chart(document.getElementById('trendLineChart'), {
                    type: 'line',
                    data: {
                        labels: trend.labels || [],
                        datasets: [{
                            label: 'Daily Trips',
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

            function updateDriverTable(drivers) {
                if (driverTable) {
                    driverTable.destroy();
                    document.querySelector('#driverTable tbody').innerHTML = '';
                }
                const rows = drivers.map(d => [
                    escapeHtml(d.driver_name),
                    escapeHtml(d.driver_id),
                    formatNumber(d.trips),
                    formatNumber(d.kms),
                    formatCurrency(d.earnings),
                    d.last_trip ? escapeHtml(d.last_trip) : '-'
                ]);
                driverTable = new DataTable('#driverTable', {
                    data: rows,
                    pageLength: 25,
                    order: [[2, 'desc']],
                    columnDefs: [{ targets: [2, 3, 4], className: 'text-end' }]
                });
                document.getElementById('driverCountBadge').textContent = `${drivers.length} drivers`;
            }

            async function loadReport() {
                document.getElementById('lastUpdated').textContent = 'Loading...';
                try {
                    const response = await fetch('php/fetch/get_driver_performance.php?' + buildQueryString(), { cache: 'no-store' });
                    const data = await response.json();
                    if (!data.success) throw new Error(data.message || 'Failed to load.');
                    state.lastData = data;
                    state.selectedSeries = '';
                    if (!state.dateFrom) dateFromInput.value = data.date_from;
                    if (!state.dateTo) dateToInput.value = data.date_to;
                    updateSummary(data.totals || {});
                    updateRangeLabel(data);
                    updateTopDriversChart();
                    updateEntryTypeDoughnut(data.by_series || {});
                    updateTrendChart(data.trend || { labels: [], trips: [] });
                    updateDriverTable(data.drivers || []);
                    document.getElementById('lastUpdated').textContent = formatDateTime(data.generated_at);
                } catch (error) {
                    document.getElementById('lastUpdated').textContent = 'Refresh failed';
                    console.error(error);
                }
            }

            function buildExportRows() {
                const headers = ['Driver', 'ID Number', 'Trips', 'KMs', 'Earnings', 'Last Trip'];
                const drivers = (state.lastData && state.lastData.drivers) || [];
                const rows = drivers.map(d => [
                    d.driver_name || '',
                    d.driver_id || '',
                    Number(d.trips || 0),
                    Number(d.kms || 0),
                    Number(d.earnings || 0),
                    d.last_trip || ''
                ]);
                return { headers, rows };
            }

            function filenameTag() {
                if (!state.lastData) return state.period;
                return `${state.lastData.date_from}_${state.lastData.date_to}`;
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
                const { headers, rows } = buildExportRows();
                const lines = [headers.map(csvEscape).join(',')];
                rows.forEach(r => lines.push(r.map(csvEscape).join(',')));
                const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
                downloadBlob(blob, `driver-performance-${state.period}-${filenameTag()}.csv`);
            }

            function exportExcel() {
                if (!state.lastData) return;
                if (typeof XLSX === 'undefined') { alert('Excel library failed to load.'); return; }
                const { headers, rows } = buildExportRows();
                const aoa = [headers, ...rows];
                const wb = XLSX.utils.book_new();
                const ws = XLSX.utils.aoa_to_sheet(aoa);
                ws['!cols'] = [
                    { wch: 28 }, { wch: 14 }, { wch: 8 }, { wch: 10 }, { wch: 14 }, { wch: 14 }
                ];
                XLSX.utils.book_append_sheet(wb, ws, 'Driver Performance');
                XLSX.writeFile(wb, `driver-performance-${state.period}-${filenameTag()}.xlsx`);
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
            topDriversModeInput.addEventListener('change', function () {
                state.topDriversMode = this.value;
                updateTopDriversChart();
            });

            loadReport();
        });
    </script>
</body>
</html>
