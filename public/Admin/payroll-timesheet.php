<?php include "php/session-check.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Timesheet - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/DataTables/datatables.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
    <style>
        #timesheetTable td, #timesheetTable th { white-space: nowrap; }
        #timesheetTable .hours-col { text-align: center; }
        #timesheetTable .amount-col { text-align: right; }
        .payclass-badge { font-size: .72rem; }
        .segment-breakdown { font-size: .78rem; color: #6c757d; white-space: normal; }
        #detailTable td, #detailTable th { font-size: .85rem; }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include "sidebar.php"; ?>

        <div id="content">
            <?php include "navbar.php"; ?>

            <div class="main-content">
                <div class="content-header mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h2 class="fw-bold">Payroll Timesheet</h2>
                        <p class="text-muted mb-0">Summary of driver's time sheet — pay classes, SAP hours, piece work, and minimum-pay guarantee.</p>
                    </div>
                    <a href="payroll" class="btn btn-outline-secondary">
                        <i class="bi bi-table me-1"></i>Simple Payroll View
                    </a>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-calendar-range me-2"></i>Pay Period</h5>
                    </div>
                    <div class="card-body">
                        <form id="timesheetFilterForm" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="payPeriod" class="form-label">Pay Period</label>
                                <select class="form-select" id="payPeriod">
                                    <option value="">Custom range…</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="dateFrom" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="dateFrom" required>
                            </div>
                            <div class="col-md-3">
                                <label for="dateTo" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="dateTo" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-1"></i>Load
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-primary"><i class="bi bi-people-fill"></i></div>
                            <div><h3 id="statDrivers">0</h3><p>Drivers</p></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-info"><i class="bi bi-clock-history"></i></div>
                            <div><h3 id="statHours">0</h3><p>SAP Hours</p></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-success"><i class="bi bi-box-seam"></i></div>
                            <div><h3 id="statPieceWork">0.00</h3><p>Piece Work</p></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-warning"><i class="bi bi-cash-stack"></i></div>
                            <div><h3 id="statFinalPay">0.00</h3><p>Final Pay</p></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Summary of Driver's Time Sheet</h5>
                        <small class="text-muted" id="periodLabel"></small>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="timesheetTable">
                                <thead class="table-light"></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Driver daily timesheet modal (Excel TSheet equivalent) -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="detailDriverName">Driver</h5>
                        <small class="text-muted" id="detailDriverMeta"></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle" id="detailTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Day</th>
                                    <th>Pay Class</th>
                                    <th class="text-center">Trips</th>
                                    <th>Earnings by Segment</th>
                                    <th class="text-end">Daily Earning</th>
                                    <th class="text-center">Mode</th>
                                    <th class="text-center">SAP Code</th>
                                    <th class="text-end">Final Pay</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot class="table-light fw-semibold"></tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/DataTables/datatables.min.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        $(document).ready(function () {
            $("#paynav").attr({ "class" : "nav-link active" });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('timesheetFilterForm');
            const payPeriodSelect = document.getElementById('payPeriod');
            const dateFromInput = document.getElementById('dateFrom');
            const dateToInput = document.getElementById('dateTo');
            const periodLabel = document.getElementById('periodLabel');
            const detailModalEl = document.getElementById('detailModal');
            const detailModal = new bootstrap.Modal(detailModalEl);

            let table = null;
            let lastRows = [];

            const payclassColors = {
                RD: 'secondary', SUN: 'info', RH: 'danger', SPH: 'warning',
                RHSUN: 'danger', SPHSUN: 'warning', GP: 'success'
            };

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function money(value) {
                return Number(value || 0).toLocaleString('en-PH', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2
                });
            }

            // Semi-monthly pay periods used by the payroll template: 6-20 and 21-5.
            function currentPayPeriod(today) {
                const y = today.getFullYear();
                const m = today.getMonth();
                const d = today.getDate();
                const fmt = dt => `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;

                if (d >= 6 && d <= 20) {
                    return { start: fmt(new Date(y, m, 6)), end: fmt(new Date(y, m, 20)) };
                }
                if (d >= 21) {
                    return { start: fmt(new Date(y, m, 21)), end: fmt(new Date(y, m + 1, 5)) };
                }
                return { start: fmt(new Date(y, m - 1, 21)), end: fmt(new Date(y, m, 5)) };
            }

            function populatePayPeriods(periods) {
                if (payPeriodSelect.options.length > 1) return;
                (periods || []).forEach(p => {
                    const option = document.createElement('option');
                    option.value = `${p.start}|${p.end}`;
                    option.textContent = p.label;
                    payPeriodSelect.appendChild(option);
                });
                syncPeriodSelect();
            }

            function syncPeriodSelect() {
                const wanted = `${dateFromInput.value}|${dateToInput.value}`;
                payPeriodSelect.value = Array.from(payPeriodSelect.options)
                    .some(o => o.value === wanted) ? wanted : '';
            }

            payPeriodSelect.addEventListener('change', function () {
                if (!this.value) return;
                const [start, end] = this.value.split('|');
                dateFromInput.value = start;
                dateToInput.value = end;
                loadTimesheet().catch(console.error);
            });

            function buildHeader(sapColumns) {
                const codes = Object.keys(sapColumns);
                const hourCells = codes.map(code =>
                    `<th class="hours-col" title="${escapeHtml(sapColumns[code])}">${escapeHtml(code)}</th>`
                ).join('');
                document.querySelector('#timesheetTable thead').innerHTML = `
                    <tr>
                        <th>#</th>
                        <th>ID No.</th>
                        <th>Name</th>
                        <th class="hours-col">Days</th>
                        ${hourCells}
                        <th class="hours-col">Total Hrs</th>
                        <th class="amount-col">Piece Work</th>
                        <th class="amount-col">Final Pay</th>
                    </tr>`;
                return codes;
            }

            function updateSummary(summary) {
                document.getElementById('statDrivers').textContent = String(summary.total_drivers ?? 0);
                document.getElementById('statHours').textContent = Number(summary.total_hours ?? 0).toLocaleString();
                document.getElementById('statPieceWork').textContent = money(summary.total_piece_work);
                document.getElementById('statFinalPay').textContent = money(summary.grand_final_pay);
            }

            function showDetail(row) {
                document.getElementById('detailDriverName').textContent = row.driver_name;
                document.getElementById('detailDriverMeta').textContent =
                    `ID: ${row.driver_id}  •  Daily Rate: ${money(row.daily_rate)}  •  ${dateFromInput.value} to ${dateToInput.value}`;

                const body = document.querySelector('#detailTable tbody');
                const foot = document.querySelector('#detailTable tfoot');
                body.innerHTML = '';

                let totalEarning = 0;
                let totalFinal = 0;
                let totalTrips = 0;

                (row.detail || []).forEach(day => {
                    totalEarning += Number(day.earning || 0);
                    totalFinal += Number(day.final_pay || 0);
                    totalTrips += Number(day.trips || 0);

                    const cls = day.mode === 'GP' ? 'GP' : day.payclass;
                    const color = payclassColors[cls] || 'secondary';
                    const holiday = day.holiday
                        ? ` <span class="text-muted" title="${escapeHtml(day.holiday)}"><i class="bi bi-star-fill text-warning"></i></span>` : '';
                    const segments = Object.entries(day.segments || {})
                        .map(([seg, amt]) => `${escapeHtml(seg || 'N/A')}: ${money(amt)}`)
                        .join(' • ');
                    const modeBadge = day.mode
                        ? `<span class="badge bg-${day.mode === 'MIN' ? 'warning text-dark' : (day.mode === 'GP' ? 'success' : 'primary')}">${escapeHtml(day.mode)}</span>`
                        : '';

                    const tr = document.createElement('tr');
                    if (!day.mode) tr.classList.add('text-muted');
                    tr.innerHTML = `
                        <td>${escapeHtml(day.date)}</td>
                        <td>${escapeHtml(day.day)}</td>
                        <td><span class="badge payclass-badge bg-${color}">${escapeHtml(day.payclass)}</span>${holiday}</td>
                        <td class="text-center">${day.trips || ''}</td>
                        <td class="segment-breakdown">${segments}</td>
                        <td class="text-end">${day.earning ? money(day.earning) : ''}</td>
                        <td class="text-center">${modeBadge}</td>
                        <td class="text-center">${day.sap_code ?? ''}</td>
                        <td class="text-end fw-semibold">${day.final_pay ? money(day.final_pay) : ''}</td>`;
                    body.appendChild(tr);
                });

                foot.innerHTML = `
                    <tr>
                        <td colspan="3">Total</td>
                        <td class="text-center">${totalTrips}</td>
                        <td></td>
                        <td class="text-end">${money(totalEarning)}</td>
                        <td></td>
                        <td></td>
                        <td class="text-end">${money(totalFinal)}</td>
                    </tr>`;

                detailModal.show();
            }

            async function loadTimesheet() {
                const params = new URLSearchParams({
                    date_from: dateFromInput.value,
                    date_to: dateToInput.value
                });

                const response = await fetch(`php/fetch/get_payroll_timesheet.php?${params.toString()}`, {
                    cache: 'no-store'
                });
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to load payroll timesheet.');
                }

                populatePayPeriods(data.pay_periods);
                periodLabel.textContent = `For the period covering ${data.date_from} to ${data.date_to}`;

                if (table) {
                    table.destroy();
                    table = null;
                }

                const codes = buildHeader(data.sap_columns || {});
                const tbody = document.querySelector('#timesheetTable tbody');
                tbody.innerHTML = '';
                lastRows = data.rows || [];

                lastRows.forEach((row, index) => {
                    const hourCells = codes.map(code => {
                        const value = Number(row.hours?.[code] ?? 0);
                        return `<td class="hours-col">${value > 0 ? value : ''}</td>`;
                    }).join('');

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${index + 1}</td>
                        <td>${escapeHtml(row.driver_id)}</td>
                        <td><a href="#" class="fw-semibold text-decoration-none driver-detail" data-index="${index}">${escapeHtml(row.driver_name)}</a></td>
                        <td class="hours-col">${row.days}</td>
                        ${hourCells}
                        <td class="hours-col fw-semibold">${row.total_hours}</td>
                        <td class="amount-col">${money(row.piece_work)}</td>
                        <td class="amount-col fw-semibold">${money(row.final_pay)}</td>`;
                    tbody.appendChild(tr);
                });

                table = new DataTable('#timesheetTable', {
                    order: [[2, 'asc']],
                    pageLength: 25
                });

                updateSummary(data.summary || {});
                syncPeriodSelect();
            }

            document.querySelector('#timesheetTable').addEventListener('click', function (event) {
                const link = event.target.closest('.driver-detail');
                if (!link) return;
                event.preventDefault();
                const row = lastRows[Number(link.dataset.index)];
                if (row) showDetail(row);
            });

            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                await loadTimesheet();
            });

            const initialParams = new URLSearchParams(window.location.search);
            const period = currentPayPeriod(new Date());
            dateFromInput.value = initialParams.get('date_from') || period.start;
            dateToInput.value = initialParams.get('date_to') || period.end;

            loadTimesheet().catch(console.error);
        });
    </script>
</body>
</html>
