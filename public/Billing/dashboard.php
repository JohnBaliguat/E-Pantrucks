<?php include "php/session-check.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Dashboard - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
</head>
<body>
    <div class="wrapper">
        <?php include "sidebar.php"; ?>

        <div id="content">
            <?php include "navbar.php"; ?>

            <div class="main-content">
                <div class="content-header mb-4 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div>
                        <h2 class="fw-bold mb-1">Billing Dashboard</h2>
                        <p class="text-muted mb-0">Billing activity and master data overview.</p>
                    </div>
                    <div class="text-lg-end">
                        <small class="text-muted d-block">Last refresh</small>
                        <strong id="lastUpdated">Loading...</strong>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body py-3">
                        <form id="rangeForm" class="row g-2 align-items-end">
                            <div class="col-auto">
                                <label for="rangeFrom" class="form-label small text-muted mb-1">From</label>
                                <input type="date" class="form-control form-control-sm" id="rangeFrom" name="from">
                            </div>
                            <div class="col-auto">
                                <label for="rangeTo" class="form-label small text-muted mb-1">To</label>
                                <input type="date" class="form-control form-control-sm" id="rangeTo" name="to">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
                            </div>
                            <div class="col-auto">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary" data-range-preset="7">Last 7 days</button>
                                    <button type="button" class="btn btn-outline-secondary" data-range-preset="30">Last 30 days</button>
                                    <button type="button" class="btn btn-outline-secondary" data-range-preset="month">This month</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-xl col-md-6">
                        <div class="stat-card stat-card-blue">
                            <div class="stat-icon"><i class="bi bi-pie-chart-fill"></i></div>
                            <div class="stat-content">
                                <h3 id="coveragePct">0%</h3>
                                <p>Billing Coverage</p>
                                <small class="stat-change text-muted" id="coverageHint">Billed trips of billable</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl col-md-6">
                        <div class="stat-card stat-card-green">
                            <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
                            <div class="stat-content">
                                <h3 id="billedTrips">0</h3>
                                <p>Billed Trips</p>
                                <small class="stat-change text-success" id="billedHint">₱0 billed</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl col-md-6">
                        <div class="stat-card stat-card-orange">
                            <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                            <div class="stat-content">
                                <h3 id="unbilledTrips">0</h3>
                                <p>Unbilled Trips</p>
                                <small class="stat-change text-warning" id="unbilledHint">₱0 pending</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl col-md-6">
                        <div class="stat-card stat-card-purple">
                            <div class="stat-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
                            <div class="stat-content">
                                <h3 id="invoicesGenerated">0</h3>
                                <p>Invoices Generated</p>
                                <small class="stat-change text-muted" id="invoicesHint">In selected range</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl col-md-6">
                        <div class="stat-card stat-card-blue">
                            <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                            <div class="stat-content">
                                <h3 id="monthRevenue">₱0</h3>
                                <p>Billable Revenue</p>
                                <small class="stat-change text-muted" id="monthRevenueHint">Selected range</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-xl-8">
                        <div class="card h-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0" id="trendTitle">Billed vs Unbilled</h5>
                                <small class="text-muted">Billable value per day, by billing status</small>
                            </div>
                            <div class="card-body">
                                <canvas id="trendChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="card h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-fuel-pump-fill me-2"></i>Current Fuel Matrix</h5>
                            </div>
                            <div class="card-body" id="fuelMatrixBody">
                                <div class="text-muted">Loading fuel price...</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-xl-8">
                        <div class="card">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Billing Status by Customer</h5>
                                <small class="text-muted" id="revenueBreakdownRange">Most pending first</small>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Customer</th>
                                                <th class="text-end">Trips</th>
                                                <th class="text-end">Billed</th>
                                                <th class="text-end">Unbilled</th>
                                                <th style="min-width:130px;">Coverage</th>
                                                <th class="text-end">Pending ₱</th>
                                            </tr>
                                        </thead>
                                        <tbody id="revenueBreakdownBody">
                                            <tr><td colspan="6" class="text-muted">Loading billing status...</td></tr>
                                        </tbody>
                                        <tfoot class="table-light fw-bold">
                                            <tr id="revenueBreakdownTotal" style="display:none;">
                                                <td>Total</td>
                                                <td class="text-end" id="statusTotalTrips">0</td>
                                                <td class="text-end" id="statusTotalBilled">0</td>
                                                <td class="text-end" id="statusTotalUnbilled">0</td>
                                                <td id="statusTotalCoverage">—</td>
                                                <td class="text-end" id="statusTotalPending">0.00</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="card h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body d-flex flex-column gap-3">
                                <a href="customer-billing" class="btn btn-outline-primary d-flex align-items-center justify-content-between">
                                    <span><i class="bi bi-receipt me-2"></i>Generate Billing</span>
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                                <?php if (ucfirst(strtolower((string) ($_SESSION["user_type"] ?? ""))) !== "Billingadmin"): ?>
                                <a href="master-data" class="btn btn-outline-secondary d-flex align-items-center justify-content-between">
                                    <span><i class="bi bi-database-gear me-2"></i>Manage Master Data</span>
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                                <?php endif; ?>
                                <a href="profile" class="btn btn-outline-secondary d-flex align-items-center justify-content-between">
                                    <span><i class="bi bi-person me-2"></i>My Profile</span>
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tripsModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0" id="tripsModalTitle">Customer Trips</h5>
                        <small class="text-muted" id="tripsModalSub"></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-wrap gap-2 mb-3" id="tripsModalStats"></div>
                    <div class="input-group input-group-sm mb-2" style="max-width:320px;">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="tripsModalSearch" placeholder="Filter (receipt, truck, destination)">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Trip Receipt</th>
                                    <th>Truck</th>
                                    <th>Destination</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody id="tripsModalBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        $(document).ready(function () {
            $("#dnav").attr({ "class" : "nav-link active" });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const trendContext = document.getElementById('trendChart');
            let trendChart = null;

            const rangeForm = document.getElementById('rangeForm');
            const rangeFromInput = document.getElementById('rangeFrom');
            const rangeToInput = document.getElementById('rangeTo');

            function toIsoDate(date) {
                const y = date.getFullYear();
                const m = String(date.getMonth() + 1).padStart(2, '0');
                const d = String(date.getDate()).padStart(2, '0');
                return `${y}-${m}-${d}`;
            }

            // Default range = month-to-date (matches the dashboard's original scope).
            (function initRangeInputs() {
                const now = new Date();
                rangeFromInput.value = toIsoDate(new Date(now.getFullYear(), now.getMonth(), 1));
                rangeToInput.value = toIsoDate(now);
            })();

            function applyPreset(preset) {
                const now = new Date();
                if (preset === 'month') {
                    rangeFromInput.value = toIsoDate(new Date(now.getFullYear(), now.getMonth(), 1));
                } else {
                    const days = parseInt(preset, 10);
                    const from = new Date(now);
                    from.setDate(from.getDate() - (days - 1));
                    rangeFromInput.value = toIsoDate(from);
                }
                rangeToInput.value = toIsoDate(now);
                loadDashboard();
            }

            rangeForm.addEventListener('submit', function (event) {
                event.preventDefault();
                loadDashboard();
            });
            rangeForm.querySelectorAll('[data-range-preset]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    applyPreset(btn.getAttribute('data-range-preset'));
                });
            });

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function formatNumber(value) {
                const num = Number.parseFloat(value);
                if (!Number.isFinite(num)) {
                    return '-';
                }
                return num.toLocaleString('en-US', { maximumFractionDigits: 2 });
            }

            function formatMoney(value) {
                const num = Number.parseFloat(value);
                if (!Number.isFinite(num)) {
                    return '-';
                }
                return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function formatPeso(value) {
                const num = Number.parseFloat(value);
                if (!Number.isFinite(num)) {
                    return '₱0';
                }
                return '₱' + num.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
            }

            function formatDate(value) {
                if (!value) {
                    return '-';
                }
                const date = new Date(value);
                if (Number.isNaN(date.getTime())) {
                    return escapeHtml(value);
                }
                return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            }

            function formatDateTime(value) {
                if (!value) {
                    return '-';
                }
                const date = new Date(value);
                if (Number.isNaN(date.getTime())) {
                    return escapeHtml(value);
                }
                return date.toLocaleString('en-US');
            }

            function updateTrend(byDay) {
                if (trendChart) {
                    trendChart.destroy();
                }
                trendChart = new Chart(trendContext, {
                    type: 'bar',
                    data: {
                        labels: byDay.labels || [],
                        datasets: [
                            {
                                label: 'Billed',
                                data: byDay.billed || [],
                                backgroundColor: '#16a34a',
                                stack: 'status'
                            },
                            {
                                label: 'Unbilled',
                                data: byDay.unbilled || [],
                                backgroundColor: '#f59e0b',
                                stack: 'status'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { display: true, position: 'top' },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return context.dataset.label + ': ₱' + Number(context.parsed.y).toLocaleString('en-US', { maximumFractionDigits: 0 });
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { stacked: true },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                ticks: {
                                    callback: function (value) {
                                        if (value >= 1000000) return '₱' + (value / 1000000).toFixed(1) + 'M';
                                        if (value >= 1000) return '₱' + (value / 1000).toFixed(0) + 'k';
                                        return '₱' + value;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            function updateFuelMatrix(fuel) {
                const body = document.getElementById('fuelMatrixBody');
                if (!fuel) {
                    body.innerHTML = '<div class="text-muted">No fuel price records yet.</div>';
                    return;
                }
                body.innerHTML = `
                    <div class="d-flex justify-content-between align-items-baseline mb-3">
                        <span class="text-muted">Week of</span>
                        <strong>${formatDate(fuel.price_date)}</strong>
                    </div>
                    <div class="text-center mb-3">
                        <div class="display-6 fw-bold text-primary">${formatNumber(fuel.average)}</div>
                        <small class="text-muted">Average Fuel Price / Liter</small>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between px-0"><span>Petron</span><strong>${formatNumber(fuel.petron)}</strong></li>
                        <li class="list-group-item d-flex justify-content-between px-0"><span>Shell</span><strong>${formatNumber(fuel.shell)}</strong></li>
                        <li class="list-group-item d-flex justify-content-between px-0"><span>Caltex</span><strong>${formatNumber(fuel.caltex)}</strong></li>
                    </ul>
                `;
            }

            function coverageBar(pct) {
                const p = Math.max(0, Math.min(100, Number(pct) || 0));
                const color = p >= 90 ? 'bg-success' : (p >= 50 ? 'bg-warning' : 'bg-danger');
                return `<div class="d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:8px;"><div class="progress-bar ${color}" style="width:${p}%"></div></div>
                    <small class="text-muted" style="min-width:34px;">${p.toFixed(0)}%</small>
                </div>`;
            }

            function updateBillingStatus(rows) {
                const body = document.getElementById('revenueBreakdownBody');
                const totalRow = document.getElementById('revenueBreakdownTotal');
                if (!rows || !rows.length) {
                    body.innerHTML = '<tr><td colspan="6" class="text-muted">No billable trips in this range.</td></tr>';
                    totalRow.style.display = 'none';
                    return;
                }
                body.innerHTML = rows.map(row => `
                    <tr class="billing-status-row" data-customer="${escapeHtml(row.key || '')}" style="cursor:pointer;" title="Click to view trips">
                        <td>
                            <div class="fw-semibold">${escapeHtml(row.label || '-')} <i class="bi bi-box-arrow-up-right text-muted small"></i></div>
                            <small class="text-muted">${escapeHtml(row.matrix_key || '')}</small>
                        </td>
                        <td class="text-end">${formatNumber(row.total_trips)}</td>
                        <td class="text-end text-success">${formatNumber(row.billed_trips)}</td>
                        <td class="text-end ${Number(row.unbilled_trips) > 0 ? 'text-warning fw-semibold' : 'text-muted'}">${formatNumber(row.unbilled_trips)}</td>
                        <td>${coverageBar(row.coverage_pct)}</td>
                        <td class="text-end ${Number(row.unbilled_revenue) > 0 ? 'fw-semibold' : 'text-muted'}">${formatMoney(row.unbilled_revenue)}</td>
                    </tr>
                `).join('');

                const sum = (k) => rows.reduce((s, r) => s + (Number(r[k]) || 0), 0);
                const totTrips = sum('total_trips'), totBilled = sum('billed_trips'), totUnbilled = sum('unbilled_trips');
                document.getElementById('statusTotalTrips').textContent = formatNumber(totTrips);
                document.getElementById('statusTotalBilled').textContent = formatNumber(totBilled);
                document.getElementById('statusTotalUnbilled').textContent = formatNumber(totUnbilled);
                document.getElementById('statusTotalCoverage').innerHTML = coverageBar(totTrips > 0 ? (totBilled / totTrips) * 100 : 0);
                document.getElementById('statusTotalPending').textContent = formatMoney(sum('unbilled_revenue'));
                totalRow.style.display = '';
            }

            function updateRangeLabels(range) {
                if (!range || !range.from || !range.to) {
                    return;
                }
                const label = `${formatDate(range.from)} – ${formatDate(range.to)}`;
                const monthRevenueHint = document.getElementById('monthRevenueHint');
                const trendTitle = document.getElementById('trendTitle');
                if (monthRevenueHint) monthRevenueHint.textContent = label;
                if (trendTitle) trendTitle.textContent = `Billed vs Unbilled (${label})`;
            }

            async function loadDashboard() {
                try {
                    const params = new URLSearchParams();
                    if (rangeFromInput.value) params.set('from', rangeFromInput.value);
                    if (rangeToInput.value) params.set('to', rangeToInput.value);
                    const response = await fetch('php/fetch/get_billing_dashboard.php?' + params.toString(), { cache: 'no-store' });
                    const data = await response.json();

                    if (!data.success) {
                        throw new Error(data.message || 'Failed to load dashboard.');
                    }

                    updateRangeLabels(data.range);

                    const finance = data.finance || {};
                    const perf = data.performance || {};

                    // Performance cards: coverage, billed, unbilled, invoices, billable revenue.
                    const coverage = Number(perf.coverage_pct ?? 0);
                    document.getElementById('coveragePct').textContent = coverage.toFixed(0) + '%';
                    document.getElementById('coverageHint').textContent =
                        `${Number(perf.billed_trips ?? 0).toLocaleString('en-US')} of ${Number(perf.total_trips ?? 0).toLocaleString('en-US')} billable trips`;
                    document.getElementById('billedTrips').textContent = Number(perf.billed_trips ?? 0).toLocaleString('en-US');
                    document.getElementById('billedHint').textContent = formatPeso(perf.billed_revenue) + ' billed';
                    document.getElementById('unbilledTrips').textContent = Number(perf.unbilled_trips ?? 0).toLocaleString('en-US');
                    document.getElementById('unbilledHint').textContent = formatPeso(perf.unbilled_revenue) + ' pending';
                    document.getElementById('invoicesGenerated').textContent = Number(perf.invoices_generated ?? 0).toLocaleString('en-US');
                    document.getElementById('invoicesHint').textContent = `${Number(perf.invoice_lines ?? 0).toLocaleString('en-US')} line item(s)`;
                    document.getElementById('monthRevenue').textContent = formatPeso(finance.month_revenue);

                    updateTrend(perf.by_day || {});
                    updateFuelMatrix(data.latest_fuel || null);
                    updateBillingStatus(perf.by_customer || []);

                    document.getElementById('lastUpdated').textContent = formatDateTime(data.generated_at);
                } catch (error) {
                    document.getElementById('lastUpdated').textContent = 'Refresh failed';
                    console.error(error);
                }
            }

            // ---- Customer drill-down modal: trips with billed/unbilled status ----
            const tripsModalEl = document.getElementById('tripsModal');
            const tripsModal = new bootstrap.Modal(tripsModalEl);
            const tripsBody = document.getElementById('tripsModalBody');
            const tripsSearch = document.getElementById('tripsModalSearch');
            let tripsRows = [];

            function statusBadge(billed) {
                return billed
                    ? '<span class="badge bg-success"><i class="bi bi-check2 me-1"></i>Billed</span>'
                    : '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Unbilled</span>';
            }

            function renderTripRows() {
                const q = (tripsSearch.value || '').trim().toLowerCase();
                const shown = q
                    ? tripsRows.filter(r => (`${r.reference} ${r.truck} ${r.destination}`).toLowerCase().includes(q))
                    : tripsRows;
                if (!shown.length) {
                    tripsBody.innerHTML = '<tr><td colspan="6" class="text-muted text-center py-3">No trips.</td></tr>';
                    return;
                }
                tripsBody.innerHTML = shown.map(r => `
                    <tr>
                        <td class="text-nowrap">${escapeHtml(formatDate(r.date))}</td>
                        <td>${escapeHtml(r.reference || '-')}</td>
                        <td>${escapeHtml(r.truck || '-')}</td>
                        <td>${escapeHtml(r.destination || '-')}</td>
                        <td class="text-end">${formatMoney(r.amount)}</td>
                        <td class="text-center">${statusBadge(!!r.billed)}</td>
                    </tr>
                `).join('');
            }
            tripsSearch.addEventListener('input', renderTripRows);

            async function openTripsModal(customerKey) {
                document.getElementById('tripsModalTitle').textContent = 'Loading trips…';
                document.getElementById('tripsModalSub').textContent = '';
                document.getElementById('tripsModalStats').innerHTML = '';
                tripsRows = [];
                tripsSearch.value = '';
                tripsBody.innerHTML = '<tr><td colspan="6" class="text-muted text-center py-3">Loading…</td></tr>';
                tripsModal.show();
                try {
                    const params = new URLSearchParams({ customer: customerKey });
                    if (rangeFromInput.value) params.set('from', rangeFromInput.value);
                    if (rangeToInput.value) params.set('to', rangeToInput.value);
                    const res = await fetch('php/fetch/get_billing_customer_trips.php?' + params.toString(), { cache: 'no-store' });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Load failed');
                    tripsRows = data.rows || [];
                    const s = data.summary || {};
                    document.getElementById('tripsModalTitle').textContent = data.label || 'Customer Trips';
                    document.getElementById('tripsModalSub').textContent =
                        `${formatDate(data.range.from)} – ${formatDate(data.range.to)}`;
                    document.getElementById('tripsModalStats').innerHTML = `
                        <span class="badge bg-light text-dark border fs-6">Total: ${Number(s.total || 0).toLocaleString('en-US')}</span>
                        <span class="badge bg-success fs-6">Billed: ${Number(s.billed || 0).toLocaleString('en-US')} · ${formatPeso(s.billed_amount)}</span>
                        <span class="badge bg-warning text-dark fs-6">Unbilled: ${Number(s.unbilled || 0).toLocaleString('en-US')} · ${formatPeso(s.unbilled_amount)}</span>`;
                    renderTripRows();
                } catch (err) {
                    document.getElementById('tripsModalTitle').textContent = 'Customer Trips';
                    tripsBody.innerHTML = `<tr><td colspan="6" class="text-danger text-center py-3">${escapeHtml(err.message)}</td></tr>`;
                }
            }

            document.getElementById('revenueBreakdownBody').addEventListener('click', function (e) {
                const row = e.target.closest('.billing-status-row');
                if (row && row.dataset.customer) {
                    openTripsModal(row.dataset.customer);
                }
            });

            loadDashboard();
            window.setInterval(loadDashboard, 30000);
        });
    </script>
</body>
</html>
