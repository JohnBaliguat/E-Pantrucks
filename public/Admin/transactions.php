<?php include "php/session-check.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/DataTables/datatables.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
</head>
<body>
    <div class="wrapper">
        <?php include "sidebar.php"; ?>

        <div id="content">
            <?php include "navbar.php"; ?>

            <div class="main-content">
                <div class="content-header mb-4">
                    <h2 class="fw-bold">Transactions</h2>
                    <p class="text-muted">Generate an Excel record of every transaction across all entry types, or narrow it down to a single entry type.</p>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Transaction Filters</h5>
                    </div>
                    <div class="card-body">
                        <form id="txnFilterForm" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="dateFrom" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="dateFrom" required>
                            </div>
                            <div class="col-md-3">
                                <label for="dateTo" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="dateTo" required>
                            </div>
                            <div class="col-md-3">
                                <label for="entryType" class="form-label">Type of Entry</label>
                                <select class="form-select" id="entryType">
                                    <option value="ALL">All Entry Types</option>
                                    <option value="RV ENTRY">RV Entry</option>
                                    <option value="DRY VAN ENTRY">Dry Van Entry</option>
                                    <option value="OTHERS ENTRY">Others Entry</option>
                                    <option value="DPC_KDs &amp; OPM ENTRY">DPC_KDs &amp; OPM Entry</option>
                                    <option value="CARGO TRUCK ENTRY">Cargo Truck Entry</option>
                                </select>
                            </div>
                            <div class="col-md-3 position-relative">
                                <label for="customerFilter" class="form-label">Customer <span class="text-muted">(optional)</span></label>
                                <input type="text" class="form-control" id="customerFilter" placeholder="All customers" autocomplete="off">
                                <ul id="customerSuggestions" class="list-group position-absolute w-100" style="z-index: 1050; display: none; max-height: 220px; overflow-y: auto;"></ul>
                            </div>
                            <div class="col-12 d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-outline-primary" id="previewButton">
                                    <i class="bi bi-search me-1"></i>Preview
                                </button>
                                <button type="button" class="btn btn-success" id="generateDetailBtn"
                                    title="Full detail: one sheet per entry type, with every field that type uses">
                                    <i class="bi bi-layers me-1"></i>Full Detail (per-type sheets)
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-primary"><i class="bi bi-list-check"></i></div>
                            <div><h3 id="totalTransactions">0</h3><p>Total Transactions</p></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-success"><i class="bi bi-tags"></i></div>
                            <div><h3 id="selectedType">All Entry Types</h3><p>Selected Entry</p></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-warning"><i class="bi bi-calendar-range"></i></div>
                            <div><h3 id="coveredDates">-</h3><p>Covered Dates</p></div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-bar-chart-steps me-2"></i>Breakdown by Entry Type</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0" id="breakdownTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Entry Type</th>
                                        <th class="text-end" style="width:160px;">Transactions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-table me-2"></i>Transaction List</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="txnTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Entry Type</th>
                                        <th>Customer / Location</th>
                                        <th>Trip Receipt No.</th>
                                        <th>Van</th>
                                        <th>Driver</th>
                                        <th>Status</th>
                                        <th>Encoded By</th>
                                        <th>Created</th>
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

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/DataTables/datatables.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        $(document).ready(function () {
            $("#txnav").attr({ "class": "nav-link active" });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('txnFilterForm');
            const dateFromInput = document.getElementById('dateFrom');
            const dateToInput = document.getElementById('dateTo');
            const entryTypeInput = document.getElementById('entryType');
            const customerFilterInput = document.getElementById('customerFilter');
            const customerSuggestions = document.getElementById('customerSuggestions');
            const previewButton = document.getElementById('previewButton');
            const generateDetailBtn = document.getElementById('generateDetailBtn');

            const totalTransactions = document.getElementById('totalTransactions');
            const selectedType = document.getElementById('selectedType');
            const coveredDates = document.getElementById('coveredDates');
            const breakdownBody = document.querySelector('#breakdownTable tbody');

            // The known entry types, in display order. The breakdown always lists
            // these so a zero-count type is still visible.
            const ENTRY_TYPES = [
                'RV ENTRY',
                'DRY VAN ENTRY',
                'OTHERS ENTRY',
                'DPC_KDs & OPM ENTRY',
                'CARGO TRUCK ENTRY'
            ];

            const today = new Date().toISOString().slice(0, 10);
            dateFromInput.value = today;
            dateToInput.value = today;

            const table = new DataTable('#txnTable', {
                paging: true,
                pageLength: 25,
                ordering: true,
                info: true
            });

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function formatStamp(value) {
                if (!value) return '-';
                const date = new Date(value);
                if (Number.isNaN(date.getTime())) return escapeHtml(value);
                const p = n => String(n).padStart(2, '0');
                return `${p(date.getMonth() + 1)}/${p(date.getDate())}/${date.getFullYear()} ${p(date.getHours())}:${p(date.getMinutes())}`;
            }

            function buildParams(entryTypeOverride) {
                const params = new URLSearchParams({
                    date_from: dateFromInput.value,
                    date_to: dateToInput.value,
                    entry_type: entryTypeOverride ?? entryTypeInput.value
                });
                const customer = customerFilterInput.value.trim();
                if (customer !== '') params.set('customer', customer);
                return params;
            }

            function detailUrl(entryTypeOverride) {
                return 'php/fetch/export_transactions_detail.php?' + buildParams(entryTypeOverride).toString();
            }

            function validRange() {
                if (!dateFromInput.value || !dateToInput.value) {
                    Swal.fire({ title: 'Missing dates', text: 'Please choose a date range.', icon: 'warning' });
                    return false;
                }
                if (dateFromInput.value > dateToInput.value) {
                    Swal.fire({ title: 'Invalid range', text: 'Date From cannot be later than Date To.', icon: 'warning' });
                    return false;
                }
                return true;
            }

            function renderBreakdown(records) {
                const counts = {};
                ENTRY_TYPES.forEach(t => counts[t] = 0);
                records.forEach(r => {
                    const t = (r.entry_type || '').trim();
                    if (t === '') return;
                    counts[t] = (counts[t] || 0) + 1;
                });

                breakdownBody.innerHTML = '';
                // List the known types first, then any unexpected ones found in data.
                const orderedTypes = ENTRY_TYPES.slice();
                Object.keys(counts).forEach(t => { if (!orderedTypes.includes(t)) orderedTypes.push(t); });

                orderedTypes.forEach(t => {
                    const count = counts[t] || 0;
                    const tr = document.createElement('tr');
                    tr.innerHTML =
                        `<td>${escapeHtml(t)}</td>` +
                        `<td class="text-end fw-semibold">${count}</td>`;
                    breakdownBody.appendChild(tr);
                });
            }

            function renderList(records) {
                table.clear();
                records.forEach(record => {
                    table.row.add([
                        `<strong>#${record.entry_id}</strong>`,
                        escapeHtml(record.entry_type),
                        record.customer ? escapeHtml(record.customer) : '<span class="text-muted">-</span>',
                        record.waybill ? escapeHtml(record.waybill) : '<span class="text-muted">-</span>',
                        record.van ? escapeHtml(record.van) : '<span class="text-muted">-</span>',
                        record.driver ? escapeHtml(record.driver) : '<span class="text-muted">-</span>',
                        record.status ? escapeHtml(record.status) : '<span class="text-muted">-</span>',
                        record.created_by_name ? escapeHtml(record.created_by_name) : '<span class="text-muted">-</span>',
                        formatStamp(record.created_date)
                    ]);
                });
                table.draw();
            }

            async function loadPreview() {
                if (!validRange()) return;
                previewButton.disabled = true;
                const original = previewButton.innerHTML;
                previewButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Loading…';
                try {
                    const res = await fetch('php/fetch/get_records.php?' + buildParams().toString(), { cache: 'no-store' });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Failed to load transactions.');

                    const records = data.records || [];
                    totalTransactions.textContent = String(records.length);
                    selectedType.textContent = entryTypeInput.value === 'ALL' ? 'All Entry Types' : entryTypeInput.value;
                    coveredDates.textContent = `${dateFromInput.value} to ${dateToInput.value}`;

                    renderBreakdown(records);
                    renderList(records);
                } catch (err) {
                    Swal.fire({ title: 'Preview failed', text: err.message, icon: 'error' });
                } finally {
                    previewButton.disabled = false;
                    previewButton.innerHTML = original;
                }
            }

            // ---- Customer autocomplete (same endpoint the Records page uses) ----
            function hideDropdown() { customerSuggestions.style.display = 'none'; customerSuggestions.innerHTML = ''; }
            let customerDebounce;
            customerFilterInput.addEventListener('input', function () {
                clearTimeout(customerDebounce);
                const q = this.value.trim();
                if (!q) { hideDropdown(); return; }
                customerDebounce = setTimeout(async function () {
                    try {
                        const res = await fetch('php/fetch/get_customers.php?q=' + encodeURIComponent(q), { cache: 'no-store' });
                        const data = await res.json();
                        const customers = data.customers || [];
                        customerSuggestions.innerHTML = '';
                        if (!customers.length) { hideDropdown(); return; }
                        customers.forEach(function (c) {
                            const li = document.createElement('li');
                            li.className = 'list-group-item list-group-item-action';
                            li.textContent = c;
                            li.addEventListener('mousedown', function (e) {
                                e.preventDefault();
                                customerFilterInput.value = c;
                                hideDropdown();
                            });
                            customerSuggestions.appendChild(li);
                        });
                        customerSuggestions.style.display = 'block';
                    } catch (_) {}
                }, 300);
            });
            document.addEventListener('mousedown', function (e) {
                if (!e.target.closest('#customerFilter') && !e.target.closest('#customerSuggestions')) hideDropdown();
            });

            // Prevent an accidental form submit (Enter in a field) from reloading.
            form.addEventListener('submit', function (event) {
                event.preventDefault();
            });

            // ---- Full detail (per-type sheets) for the current selection ----
            generateDetailBtn.addEventListener('click', function () {
                if (!validRange()) return;
                window.location.href = detailUrl();
            });

            previewButton.addEventListener('click', loadPreview);

            // Auto-load today's transactions on first open.
            loadPreview();
        });
    </script>
</body>
</html>
