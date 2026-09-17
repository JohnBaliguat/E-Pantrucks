<?php
include "php/session-check.php";
require_once dirname(__DIR__, 2) . "/php/helpers/unified_billing.php";
require_once dirname(__DIR__, 2) . "/php/helpers/billing_activities.php";
require_once dirname(__DIR__, 2) . "/php/helpers/dict_shuttling.php";
require_once dirname(__DIR__, 2) . "/php/helpers/dict_industrial_waste.php";
require_once dirname(__DIR__, 2) . "/php/helpers/app_settings.php";
// Admin toggle (Admin → Settings → Billing Display): hide the Excel download buttons
// in Generated Invoices for everyone.
$hideInvoiceExcel = app_setting_bool($conn, "hide_invoice_excel_buttons");
$billingCustomers = unified_billing_customers();
$billingCustomerGroups = unified_billing_customers_grouped();
$billingActivityList = billing_activities();
// Route-priced DICT services bill per lane/route (each = its own file) instead of by
// activity. Map each such customer key to its route options for the Lane dropdown.
$shuttlingKey = dict_shuttling_config()["key"];
$shuttlingLanes = dict_shuttling_lane_options();
// Only DICT Van Shuttling bills per route (route dropdown). DICT Industrial Waste is ONE
// combined statement for both routes, so it has no route dropdown.
$laneCustomers = [
    dict_shuttling_config()["key"] => dict_shuttling_lane_options(),
];
// The RAW records export (formerly the separate Records page) is reefer RV-ENTRY
// only, so it is offered for the Box Bananas customers and hidden for the rest.
// It filters by free-text customer, hence the key -> customer_match map.
$rawCustomerMatches = unified_billing_raw_matches();
// Keep each role's own sidebar — an Admin viewing this page should still see the
// Admin menu, not the Billing menu.
$currentRole = ucfirst(strtolower((string) ($_SESSION["user_type"] ?? "")));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/DataTables/datatables.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .status-badge { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.6rem; border-radius: 999px; font-size: 0.8rem; font-weight: 500; }
        .status-ready { background: #d1fae5; color: #065f46; }
        .status-missing { background: #fef3c7; color: #92400e; }
        .report-cell { display: flex; align-items: center; gap: 0.6rem; }
        .report-icon { font-size: 1.25rem; color: #1d6f42; }
        .report-icon.csv { color: #0d6efd; }
        .drag-handle { cursor: grab; color: #6c757d; }
        #billingPreviewTable tbody tr.dragging { opacity: 0.55; }
        #billingPreviewTable tbody tr.drag-over { outline: 2px solid #0d6efd; outline-offset: -2px; }
        /* RAW layout is 37 columns — keep cells on one line and let the wrapper scroll. */
        #billingPreviewTable.raw-view th,
        #billingPreviewTable.raw-view td { white-space: nowrap; font-size: 0.8125rem; }

        /* The #content width fix now lives globally in styles.css. Keep the preview card
           from ever forcing a page-wide scroll — its wide table scrolls inside itself. */
        #billingPreviewCard { max-width: 100%; }
        #billingPreviewCard .table-responsive { overflow-x: auto; }
        /* Vertical scroll for the preview rows so the table doesn't grow the whole page;
           the header stays pinned while scrolling. */
        #billingPreviewCard .billing-preview-scroll { max-height: 65vh; overflow-y: auto; }
        #billingPreviewTable thead th {
            position: sticky;
            top: 0;
            z-index: 3;
            background-color: #f2f2f2;
            box-shadow: inset 0 -1px 0 var(--bs-border-color, #dee2e6);
        }
        /* Rows that priced to no charge (0 / blank) — flagged so unpriced trips stand out. */
        #billingPreviewTable tbody tr.billing-no-charge > td { background-color: #fde2e2; }
        #billingPreviewTable tbody tr.billing-no-charge:hover > td { background-color: #f9d0d0; }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php if ($currentRole === "Admin") { include dirname(__DIR__) . "/Admin/sidebar.php"; } else { include "sidebar.php"; } ?>

        <div id="content">
            <?php include "navbar.php"; ?>

            <div class="main-content">
                <div class="content-header mb-4">
                    <h2 class="fw-bold">Billing</h2>
                    <p class="text-muted">Review a customer's trip records, then download the SAP billing upload or the RAW records export.</p>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Customer Billing</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Pick a customer and date range, then <strong>Preview Rows</strong> to review that customer's actual trip records. Each unbilled trip becomes a line item; the PHP rate is taken from the fuel rate matrix and converted to the customer currency using the Dollar Conversion. Already-billed trips are skipped automatically.</p>
                        <form id="billingForm" class="row g-3 align-items-end">
                            <div class="col-md-2">
                                <label for="billCustomer" class="form-label">Customer</label>
                                <select class="form-select" id="billCustomer" required>
                                    <?php foreach ($billingCustomerGroups as $groupLabel => $groupCustomers): ?>
                                        <optgroup label="<?php echo htmlspecialchars($groupLabel); ?>">
                                            <?php foreach ($groupCustomers as $key => $cfg): ?>
                                                <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($cfg["label"]); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-none" id="billLaneWrap">
                                <label for="billLane" class="form-label">Route</label>
                                <select class="form-select" id="billLane">
                                    <?php foreach ($shuttlingLanes as $code => $label): ?>
                                        <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="billDateFrom" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="billDateFrom" required>
                            </div>
                            <div class="col-md-2">
                                <label for="billDateTo" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="billDateTo" required>
                            </div>
                            <div class="col-md-2">
                                <label for="billDocumentDate" class="form-label text-nowrap">Document / Billing Date
                                    <i class="bi bi-info-circle text-muted" data-bs-toggle="tooltip"
                                       title="Blank = today. Set a past date to bill a previous month."></i>
                                </label>
                                <input type="date" class="form-control" id="billDocumentDate">
                            </div>
                            <div class="col-md-2 d-flex">
                                <button type="button" class="btn btn-primary w-100" id="billPreview">
                                    <i class="bi bi-eye me-1"></i>Preview Rows
                                </button>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="billIncludeBilled">
                                    <label class="form-check-label small text-muted" for="billIncludeBilled">
                                        Include already-billed trips (re-generate)
                                    </label>
                                </div>
                                <div id="billStatus" class="small mt-2"></div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-4 d-none" id="billingPreviewCard">
                    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Review Before Download</h5>
                            <div class="small text-muted mt-1" id="billingPreviewHint">Untick trips to exclude them, then drag rows to change the billing order.</div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="small text-muted" id="billingSelectedCount">0 selected</span>
                            <span class="small text-muted" id="billingPreviewTotal"></span>
                            <button type="button" class="btn btn-outline-secondary btn-sm billing-select-control" id="billingSelectAllButton">Select all</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm billing-select-control" id="billingClearAllButton">Clear all</button>
                            <button type="button" class="btn btn-success btn-sm" id="billGenerate">
                                <i class="bi bi-file-earmark-excel me-1"></i>Generate Billing
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="billingNotes" class="alert alert-warning small py-2 d-none"></div>
                        <div id="billingRateSummary" class="mb-3 d-none"></div>
                        <div id="billingPreviewDetails" class="d-none">
                        <div id="billingBulkForex" class="d-none align-items-center flex-wrap gap-2 mb-2 p-2 rounded" style="background:#f8f9fa;">
                            <span class="small text-muted"><i class="bi bi-lightning-charge me-1"></i>Set <strong>DOLLAR CONVERTION</strong> for every row at once:</span>
                            <div class="input-group input-group-sm" style="max-width: 260px;">
                                <input type="number" min="0.001" step="0.001" class="form-control" id="billingBulkForexInput" placeholder="e.g. 58.40">
                                <button type="button" class="btn btn-primary" id="billingBulkForexApply">Apply to all</button>
                            </div>
                            <span class="small text-muted">You can still fine-tune individual rows afterward.</span>
                        </div>
                        <div class="d-flex justify-content-end mb-2">
                            <div class="input-group input-group-sm" style="max-width: 340px;">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="billingPreviewSearch" placeholder="Search rows (truck, trip receipt…)" autocomplete="off">
                            </div>
                        </div>
                        <div class="table-responsive billing-preview-scroll">
                            <table class="table table-hover align-middle" id="billingPreviewTable">
                                <thead class="table-light" id="billingPreviewHead">
                                    <tr>
                                        <th class="text-center" style="width:36px;"></th>
                                        <th class="text-center" style="width:40px;">
                                            <input class="form-check-input" type="checkbox" id="billingSelectAllCheck" checked>
                                        </th>
                                        <th class="text-center" style="width:36px;"></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h5 class="mb-0"><i class="bi bi-archive me-2"></i>Generated Invoices</h5>
                        <div class="d-flex align-items-center gap-2">
                            <div class="input-group input-group-sm" style="width: 260px;">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="invoiceDocSearch" placeholder="Find by Document No.">
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="refreshInvoices">
                                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="invoicesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Customer</th>
                                        <th>Date Range</th>
                                        <th>Forex</th>
                                        <th>Lines</th>
                                        <th>Generated</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
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

    <!-- View / edit the per-trip peso charges locked onto a generated invoice. -->
    <div class="modal fade" id="chargesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0"><i class="bi bi-cash-stack me-2"></i>Billed Charges</h5>
                        <div class="small text-muted" id="chargesModalSub"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="chargesModalNote" class="alert alert-info small py-2 d-none"></div>
                    <div id="chargesModalStatus" class="small mb-2"></div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle" id="chargesModalTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:44px;">No.</th>
                                    <th>Date</th>
                                    <th>Trip Receipt</th>
                                    <th>Truck</th>
                                    <th>Van</th>
                                    <th class="text-end" style="width:170px;">Rate Charge (₱)</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot class="border-top">
                                <tr>
                                    <td colspan="5" class="text-end fw-bold">Total charges in Peso</td>
                                    <td class="text-end fw-bold" id="chargesModalTotal">0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="small text-muted mb-0"><i class="bi bi-info-circle me-1"></i>Editing a charge updates the stored (database) value only. Use <strong>Rebuild file</strong> to regenerate the downloadable SAP file from these charges.</p>
                </div>
                <div class="modal-footer">
                    <span class="small text-muted me-auto" id="chargesRebuildStatus"></span>
                    <button type="button" class="btn btn-outline-primary" id="chargesRebuildBtn"><i class="bi bi-arrow-repeat me-1"></i>Rebuild file from these charges</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/DataTables/datatables.min.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        $(document).ready(function () { $("#cbnav").attr({ "class": "nav-link active" }); });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const BILLING_ACTIVITIES = <?php echo json_encode(array_map(static function ($code, $a) {
                return ["code" => $code, "short" => $a["short"] ?? $a["label"], "label" => $a["label"]];
            }, array_keys($billingActivityList), array_values($billingActivityList))); ?>;
            // Admin → Settings → Billing Display: when on, the Excel download buttons in
            // Generated Invoices are hidden for everyone.
            const HIDE_INVOICE_EXCEL = <?php echo $hideInvoiceExcel ? 'true' : 'false'; ?>;

            const customer = document.getElementById('billCustomer');
            const dateFrom = document.getElementById('billDateFrom');
            const dateTo = document.getElementById('billDateTo');
            const documentDate = document.getElementById('billDocumentDate');
            const includeBilled = document.getElementById('billIncludeBilled');
            const previewBtn = document.getElementById('billPreview');
            const generateBtn = document.getElementById('billGenerate');
            const statusEl = document.getElementById('billStatus');
            const previewCard = document.getElementById('billingPreviewCard');
            const previewHead = document.getElementById('billingPreviewHead');
            const previewTableBody = document.querySelector('#billingPreviewTable tbody');
            const selectedCountEl = document.getElementById('billingSelectedCount');
            let selectAllCheck = document.getElementById('billingSelectAllCheck');
            const selectAllButton = document.getElementById('billingSelectAllButton');
            const clearAllButton = document.getElementById('billingClearAllButton');
            const notesEl = document.getElementById('billingNotes');
            const rateSummaryEl = document.getElementById('billingRateSummary');
            const previewHint = document.getElementById('billingPreviewHint');
            const previewTable = document.getElementById('billingPreviewTable');
            const previewTotalEl = document.getElementById('billingPreviewTotal');
            const previewSearch = document.getElementById('billingPreviewSearch');
            const bulkForexBox = document.getElementById('billingBulkForex');
            const bulkForexInput = document.getElementById('billingBulkForexInput');
            const bulkForexApply = document.getElementById('billingBulkForexApply');
            const previewDetailsBox = document.getElementById('billingPreviewDetails');

            // Customer key -> the RAW export's free-text customer filter. Only the
            // reefer RV-ENTRY customers (Box Bananas) have one; used to decide whether
            // the preview shows the RAW layout (RV) or the customer's own trip detail.
            const RAW_MATCHES = <?php echo json_encode($rawCustomerMatches); ?>;

            let previewColumns = [];
            let previewRecords = [];
            let previewSignature = '';
            let previewSelectable = true;
            let previewSearchTerm = '';
            // Last rate summary from the server, kept so the footer totals can be
            // recomputed live as rows are ticked/unticked or a DOLLAR CONVERTION is typed.
            let currentRateSummary = null;
            // The detailed trip table is collapsed by default; the Rate summary's
            // "Show details" button reveals it (state persists across live re-renders).
            let previewDetailsVisible = false;
            // Which activity's charges the Rate summary's amount column shows
            // ('hauling' | 'chassis' | 'genset' | 'container_van' | 'fuel').
            let rateSummaryActivity = 'hauling';

            function applyPreviewDetailsVisibility() {
                if (previewDetailsBox) {
                    previewDetailsBox.classList.toggle('d-none', !previewDetailsVisible);
                }
            }

            // Toggle the detailed trip table from the Rate summary's "Show details" button.
            // Delegated on the (persistent) container since the button is re-rendered each time.
            if (rateSummaryEl) {
                rateSummaryEl.addEventListener('click', function (event) {
                    if (!event.target.closest('#billingToggleDetails')) return;
                    previewDetailsVisible = !previewDetailsVisible;
                    applyPreviewDetailsVisibility();
                    if (currentRateSummary) renderRateSummary(currentRateSummary);
                });
                // Activity dropdown → re-render the Rate summary with the picked activity's charges.
                rateSummaryEl.addEventListener('change', function (event) {
                    const sel = event.target.closest('#billingSummaryActivity');
                    if (!sel) return;
                    rateSummaryActivity = sel.value;
                    if (currentRateSummary) renderRateSummary(currentRateSummary);
                });
            }

            const isoToday = new Date().toISOString().slice(0, 10);
            dateFrom.value = isoToday;
            dateTo.value = isoToday;

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }
            function formatStamp(value) {
                if (!value) return '-';
                const d = new Date(value);
                if (Number.isNaN(d.getTime())) return escapeHtml(value);
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const dd = String(d.getDate()).padStart(2, '0');
                const yy = String(d.getFullYear()).slice(-2);
                const hh = String(d.getHours()).padStart(2, '0');
                const mi = String(d.getMinutes()).padStart(2, '0');
                return `${mm}/${dd}/${yy} ${hh}:${mi}`;
            }
            function statusBadge(s) {
                if (s === 'ready') return '<span class="status-badge status-ready"><i class="bi bi-check-circle"></i>Ready</span>';
                if (s === 'missing') return '<span class="status-badge status-missing"><i class="bi bi-exclamation-triangle"></i>File missing</span>';
                return `<span class="status-badge">${escapeHtml(s)}</span>`;
            }

            // Route-priced DICT services (Van Shuttling, Industrial Waste) bill per lane/route
            // (each = its own file) and have no Activity; every other customer is the reverse.
            const SHUTTLING_KEY = <?php echo json_encode($shuttlingKey); ?>;
            const LANE_CUSTOMERS = <?php echo json_encode($laneCustomers); ?>;
            const lane = document.getElementById('billLane');
            const laneWrap = document.getElementById('billLaneWrap');

            // True for any customer that bills per route (shuttling / industrial waste).
            function isShuttling() { return !!LANE_CUSTOMERS[customer.value]; }
            function rawMatchFor(key) { return RAW_MATCHES[key] || ''; }

            // The reefer (RV) customers always preview in the wide RAW layout; the rest
            // (DICT shuttling, ABC KDs) have no RAW records, so they keep their own trip
            // detail. Either way the download is the SAP billing. The non-hauling
            // activities bill on their own basis and keep their own detail rows.
            function isRawView() {
                return !!rawMatchFor(customer.value) && !isShuttling();
            }

            function syncCustomerMode() {
                const shuttling = isShuttling();
                laneWrap.classList.toggle('d-none', !shuttling);
                // Populate the Lane/Route dropdown with the selected customer's routes.
                if (shuttling) {
                    const opts = LANE_CUSTOMERS[customer.value] || {};
                    const prev = lane.value;
                    lane.innerHTML = Object.entries(opts)
                        .map(([code, label]) => `<option value="${code}">${label}</option>`).join('');
                    if (Object.prototype.hasOwnProperty.call(opts, prev)) lane.value = prev;
                }
                updateSelectionSummary();
            }
            customer.addEventListener('change', syncCustomerMode);
            syncCustomerMode();

            function currentSignature() {
                return JSON.stringify({
                    customer: customer.value,
                    activity: isShuttling() ? '' : 'hauling',
                    lane: isShuttling() ? lane.value : '',
                    date_from: dateFrom.value,
                    date_to: dateTo.value,
                    include_billed: includeBilled.checked ? '1' : '0'
                });
            }

            function setButtonsDisabled(disabled) {
                previewBtn.disabled = disabled;
                generateBtn.disabled = disabled;
            }

            const table = new DataTable('#invoicesTable', {
                order: [[5, 'desc']],
                pageLength: 10,
                columnDefs: [{ orderable: false, targets: [7] }]
            });

            // Dedicated "Find by Document No." box — filters the invoices table.
            const invoiceDocSearch = document.getElementById('invoiceDocSearch');
            if (invoiceDocSearch) {
                invoiceDocSearch.addEventListener('input', function () {
                    table.search(this.value.trim()).draw();
                });
            }

            // Mark / clear "returned by customer".
            document.querySelector('#invoicesTable tbody').addEventListener('click', async function (e) {
                const btn = e.target.closest('.btn-mark-returned');
                if (!btn) return;
                const isReturned = btn.dataset.returned === '1';
                let remarks = '';
                if (!isReturned) {
                    const res = await Swal.fire({
                        title: 'Mark as returned?',
                        input: 'text',
                        inputLabel: 'Reason / remarks (optional)',
                        inputPlaceholder: 'e.g. wrong rate on line 3',
                        showCancelButton: true,
                        confirmButtonText: 'Mark returned',
                        confirmButtonColor: '#f0ad4e'
                    });
                    if (!res.isConfirmed) return;
                    remarks = res.value || '';
                }
                const fd = new FormData();
                fd.append('id', btn.dataset.id);
                fd.append('returned', isReturned ? '0' : '1');
                fd.append('remarks', remarks);
                try {
                    const r = await fetch('php/update/mark_invoice_returned.php', { method: 'POST', body: fd });
                    const data = await r.json();
                    if (!data.success) throw new Error(data.message || 'Update failed');
                    await loadInvoices();
                } catch (err) {
                    Swal.fire({ title: 'Error', text: err.message, icon: 'error' });
                }
            });

            function invoiceRow(item) {
                const reportCell = `
                    <div class="report-cell">
                        <i class="bi bi-file-earmark-excel-fill report-icon"></i>
                        <div>
                            <div class="fw-semibold">${escapeHtml(item.document_no || ('Invoice #' + item.invoice_id))}${item.returned_at ? ' <span class="badge bg-danger">RETURNED</span>' : ''}</div>
                            <div class="text-muted small">${escapeHtml(item.reference || '')}</div>
                        </div>
                    </div>`;
                const canDownload = item.status === 'ready';
                // Excel button = RAW billing (wide reefer layout), rebuilt on the fly from
                // the invoice's own trips — so it works even if the stored SAP file is
                // missing. RV invoices only; DICT/KDs have no RAW format.
                const dl = HIDE_INVOICE_EXCEL ? '' : (item.raw_capable
                    ? `<a href="php/fetch/download_invoice_raw_billing.php?id=${item.invoice_id}" class="btn btn-outline-success btn-sm" title="Download RAW billing (Excel)"><i class="bi bi-file-earmark-excel"></i></a>`
                    : `<button type="button" class="btn btn-outline-secondary btn-sm" disabled title="RAW billing is for reefer (RV) customers only"><i class="bi bi-file-earmark-excel"></i></button>`);
                // Non-hauling activities apply to the breakbulk (matrix) customers only.
                const availableActivities = item.pipeline === 'matrix'
                    ? BILLING_ACTIVITIES
                    : BILLING_ACTIVITIES.filter(a => a.code === 'hauling');
                // CSV button = SAP billing (ZPSO upload). Dropdown to download any activity's
                // CSV — hauling serves the stored sibling; the others are rebuilt on the fly,
                // mirroring the PDF dropdown.
                const csvActivityItems = availableActivities.map(a =>
                    `<li><a class="dropdown-item" href="php/fetch/download_customer_billing_csv.php?id=${item.invoice_id}&activity=${encodeURIComponent(a.code)}">${escapeHtml(a.short)}</a></li>`
                ).join('');
                const csv = canDownload
                    ? `<div class="btn-group">
                        <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Download SAP billing (CSV)"><i class="bi bi-filetype-csv"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end"><li><h6 class="dropdown-header">Download CSV</h6></li>${csvActivityItems}</ul>
                       </div>`
                    : `<button type="button" class="btn btn-outline-primary btn-sm" disabled title="SAP CSV missing — re-generate this invoice"><i class="bi bi-filetype-csv"></i></button>`;
                // Activity PDF dropdown — pick an activity to view its PANABO statement
                // (opens in a new tab to preview before downloading).
                const activityItems = availableActivities.map(a =>
                    `<li><a class="dropdown-item btn-view-activity-pdf" href="#" data-id="${item.invoice_id}" data-activity="${escapeHtml(a.code)}">${escapeHtml(a.short)}</a></li>`
                ).join('');
                const pdf = `
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-danger btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="View activity PDF">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">View PDF</h6></li>
                            ${activityItems}
                        </ul>
                    </div>`;
                const summaryExcel = (HIDE_INVOICE_EXCEL || item.pipeline !== 'sap')
                    ? ''
                    : `<a href="php/fetch/download_billing_summary_excel.php?id=${item.invoice_id}" class="btn btn-outline-success btn-sm" title="Download Summary Billing (Excel)"><i class="bi bi-file-earmark-spreadsheet"></i></a>`;
                const returnedBtn = `<button type="button" class="btn ${item.returned_at ? 'btn-warning' : 'btn-outline-warning'} btn-sm btn-mark-returned" data-id="${item.invoice_id}" data-returned="${item.returned_at ? '1' : '0'}" title="${item.returned_at ? 'Returned by customer — click to clear' : 'Mark as returned by customer'}"><i class="bi bi-arrow-return-left"></i></button>`;
                // Charges = the per-trip peso charges locked at generation (view + edit).
                const chargesBtn = `<button type="button" class="btn btn-outline-info btn-sm btn-view-charges" data-id="${item.invoice_id}" title="View / edit billed charges"><i class="bi bi-cash-stack"></i></button>`;
                const actions = `<div class="d-flex justify-content-end gap-2">${dl}${csv}${summaryExcel}${pdf}${chargesBtn}${returnedBtn}
                    <button type="button" class="btn btn-outline-dark btn-sm btn-del-invoice" data-id="${item.invoice_id}" title="Delete"><i class="bi bi-trash"></i></button></div>`;
                return [
                    reportCell,
                    escapeHtml(item.customer_label || '-'),
                    `${escapeHtml(item.date_from)} to ${escapeHtml(item.date_to)}`,
                    item.forex_rate ? Number(item.forex_rate).toLocaleString('en-US', { maximumFractionDigits: 3 }) : '-',
                    `${item.line_count} line${item.line_count === 1 ? '' : 's'}`,
                    formatStamp(item.requested_at),
                    statusBadge(item.status),
                    actions
                ];
            }

            async function loadInvoices() {
                try {
                    const res = await fetch('php/fetch/get_billing_invoices.php', { cache: 'no-store' });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Load failed');
                    table.clear();
                    (data.invoices || []).forEach(i => table.row.add(invoiceRow(i)));
                    table.draw();
                } catch (err) { console.error(err); }
            }

            function validate() {
                if (!customer.value) { window.alert('Please select a customer.'); return false; }
                if (!dateFrom.value || !dateTo.value) { window.alert('Please select both dates.'); return false; }
                if (dateFrom.value > dateTo.value) { window.alert('Date From must not be later than Date To.'); return false; }
                return true;
            }

            function updateSelectionSummary() {
                const selectedCount = previewRecords.filter(record => record.selected).length;
                // When a download line aggregates several trips (DICT shuttling) the
                // rows are review-only — every trip in range is always included.
                selectedCountEl.textContent = previewSelectable
                    ? `${selectedCount} selected of ${previewRecords.length}`
                    : `${previewRecords.length} row(s)`;
                if (selectAllCheck) {
                    selectAllCheck.checked = previewRecords.length > 0 && selectedCount === previewRecords.length;
                    selectAllCheck.indeterminate = selectedCount > 0 && selectedCount < previewRecords.length;
                }
                generateBtn.disabled = previewSelectable
                    ? selectedCount === 0
                    : previewRecords.length === 0;
                // Selection drives the statement totals — keep the Rate summary in sync.
                refreshRateSummaryTotals();
            }

            function renderPreview() {
                previewTable.classList.toggle('raw-view', isRawView());
                const headerCells = ['<th class="text-center" style="width:36px;"></th>'];
                if (previewSelectable) {
                    headerCells.push('<th class="text-center" style="width:40px;"><input class="form-check-input" type="checkbox" id="billingSelectAllCheck"></th>');
                }
                // Flag-for-update column (the visible entry ID was removed on request;
                // entry_id still rides on the row dataset for selection + flagging).
                headerCells.push('<th class="text-center" style="width:36px;"></th>');
                previewColumns.forEach(function (column) {
                    headerCells.push(`<th>${escapeHtml(column.label || '-')}</th>`);
                });
                previewHead.innerHTML = `<tr>${headerCells.join('')}</tr>`;

                previewTableBody.innerHTML = '';

                const term = (previewSearchTerm || '').trim().toLowerCase();
                const visibleRecords = term
                    ? previewRecords.filter(record => recordSearchText(record).includes(term))
                    : previewRecords;

                visibleRecords.forEach(function (record) {
                    const tr = document.createElement('tr');
                    tr.draggable = previewSelectable;
                    tr.dataset.entryId = String(record.entry_id);
                    // Highlight trips that priced to no charge (0 / blank) so unpriced rows stand out.
                    if (rowPesoCharge(record) <= 0) {
                        tr.classList.add('billing-no-charge');
                    }
                    const cellMap = Object.fromEntries((record.cells || []).map(cell => [cell.key, cell.value]));
                    const dataCells = previewColumns.map(function (column) {
                        if (column.key === 'dollar_conversion' && record.manual_forex_needed) {
                            return `<td><input type="number" min="0.001" step="0.001" class="form-control form-control-sm billing-manual-forex" placeholder="Enter rate" value="${escapeHtml(record.manual_forex || '')}"></td>`;
                        }
                        return `<td>${escapeHtml(cellMap[column.key] || '-')}</td>`;
                    }).join('');
                    const handle = previewSelectable
                        ? '<td class="text-center"><span class="drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></span></td>'
                        : '<td></td>';
                    const check = previewSelectable
                        ? `<td class="text-center"><input class="form-check-input billing-row-check" type="checkbox" ${record.selected ? 'checked' : ''}></td>`
                        : '';
                    const flagged = record.flagged ? ' text-warning' : ' text-muted';
                    const flagIcon = record.flagged ? 'bi-flag-fill' : 'bi-flag';
                    tr.innerHTML = `
                        ${handle}
                        ${check}
                        <td class="text-center">
                            <button type="button" class="btn btn-link btn-sm p-0 billing-flag-btn${flagged}" title="Flag this record for update"><i class="bi ${flagIcon}"></i></button>
                        </td>
                        ${dataCells}
                    `;
                    previewTableBody.appendChild(tr);
                });

                const previousChecked = selectAllCheck ? selectAllCheck.checked : false;
                const previousIndeterminate = selectAllCheck ? selectAllCheck.indeterminate : false;
                const refreshedSelectAll = document.getElementById('billingSelectAllCheck');
                selectAllCheck = refreshedSelectAll;
                if (refreshedSelectAll) {
                    refreshedSelectAll.checked = previousChecked;
                    refreshedSelectAll.indeterminate = previousIndeterminate;
                    refreshedSelectAll.addEventListener('change', function () { setAllSelections(refreshedSelectAll.checked); });
                }

                document.querySelectorAll('.billing-select-control').forEach(function (element) {
                    element.classList.toggle('d-none', !previewSelectable);
                });
                previewHint.textContent = !previewSelectable
                    ? 'Review only — each download line groups several trips, so rows cannot be excluded here.'
                    : (isRawView()
                        ? 'RAW record detail — untick a row to leave it out of the SAP invoice, drag to reorder. Scroll sideways for all columns.'
                        : 'Untick trips to exclude them, then drag rows to change the billing order.');

                previewCard.classList.toggle('d-none', previewRecords.length === 0);
                updateSelectionSummary();
                updateBulkForexVisibility();
            }

            // Show the "Apply to all" DOLLAR CONVERTION control only when some rows
            // actually need a manual forex rate typed in.
            function updateBulkForexVisibility() {
                if (!bulkForexBox) return;
                const needs = previewRecords.some(record => record.manual_forex_needed);
                bulkForexBox.classList.toggle('d-none', !needs);
                bulkForexBox.classList.toggle('d-flex', needs);
            }

            // Concatenated, lowercased searchable text for a preview record.
            function recordSearchText(record) {
                const cellText = (record.cells || []).map(cell => cell.value ?? '').join(' ');
                return ('#' + record.entry_id + ' ' + cellText).toLowerCase();
            }

            function renderNotes(notes) {
                const list = (notes || []).filter(Boolean);
                notesEl.classList.toggle('d-none', list.length === 0);
                notesEl.innerHTML = list.map(note => escapeHtml(note)).join('<br>');
            }

            // Parse a formatted money/number cell (strip "₱", commas) to a number.
            function parseNum(value) {
                const n = parseFloat(String(value ?? '').replace(/[^0-9.\-]/g, ''));
                return Number.isFinite(n) ? n : 0;
            }

            // The peso hauling charge for one preview row, read from whichever charge
            // column that pipeline uses (raw = RATE CHARGES, matrix = AMOUNT, else CHARGE).
            function rowPesoCharge(record) {
                const map = Object.fromEntries((record.cells || []).map(cell => [cell.key, cell.value]));
                return parseNum(map.charge ?? map.amount ?? map.rate_charge ?? '');
            }

            // Actual trip count for one preview row (its TRIPS cell), so the live recount
            // matches the server's summary. Defaults to 1 when the pipeline has no trips
            // cell (one line = one trip).
            function rowTripCount(record) {
                const map = Object.fromEntries((record.cells || []).map(cell => [cell.key, cell.value]));
                const n = parseNum(map.trips ?? '');
                return n > 0 ? n : 1;
            }

            // Live per-lane totals from the SELECTED rows, grouped by their rate-summary
            // line key (record.summary_line). Lets the summary body's Trips/Subtotal drop
            // as rows are unticked. hasKeys is false for pipelines that don't tag rows,
            // in which case the server's original line values are kept.
            function liveLineTotals() {
                // hasKeys reflects whether THIS pipeline tags rows with a summary_line at
                // all — checked across every record, not just the selected ones. Otherwise
                // unticking all rows would leave the selected subset empty, hasKeys false,
                // and the per-lane Trips/Subtotal would wrongly fall back to server values
                // instead of dropping to zero.
                const hasKeys = previewRecords.some(r => r.summary_line);
                const rows = previewSelectable ? previewRecords.filter(r => r.selected) : previewRecords;
                const map = {};
                rows.forEach(function (r) {
                    const key = r.summary_line;
                    if (!key) return;
                    const bucket = map[key] || (map[key] = { trips: 0, subtotal: 0 });
                    bucket.trips += rowTripCount(r);
                    bucket.subtotal += rowPesoCharge(r);
                });
                return { map, hasKeys };
            }

            // Live statement totals for the Rate summary footer: only the SELECTED rows
            // count. Each row's peso charge comes from whichever charge column its pipeline
            // uses (raw = RATE CHARGES, matrix = AMOUNT, else CHARGE); its forex is the
            // typed DOLLAR CONVERTION, else the value already in that column, else the
            // statement forex.
            function computeStatementTotals(summary) {
                const rows = previewSelectable ? previewRecords.filter(r => r.selected) : previewRecords;
                const serverForex = Number(summary.forex);
                let pesoTotal = 0;
                let dollarTotal = 0;
                const forexSeen = new Set();
                rows.forEach(function (r) {
                    const map = Object.fromEntries((r.cells || []).map(cell => [cell.key, cell.value]));
                    const peso = parseNum(map.charge ?? map.amount ?? map.rate_charge ?? '');
                    pesoTotal += peso;
                    const manual = Number(r.manual_forex);
                    const cellForex = parseNum(map.dollar_conversion);
                    const fx = manual > 0 ? manual : (cellForex > 0 ? cellForex : serverForex);
                    if (fx > 0) {
                        dollarTotal += peso / fx;
                        forexSeen.add(Number(fx.toFixed(3)));
                    }
                });
                return {
                    pesoTotal,
                    dollarTotal,
                    tripCount: rows.length,
                    // A single forex to print, or null when the selected rows mix rates.
                    forexDisplay: forexSeen.size === 1 ? [...forexSeen][0] : null,
                    showUsd: !!summary.show_usd,
                };
            }

            // Recompute just the footer totals in place (rows/forex changed, lines did not).
            function refreshRateSummaryTotals() {
                if (currentRateSummary) renderRateSummary(currentRateSummary);
            }

            // Rate-usage summary: what rate each group of trips billed at and how the
            // fuel-movement formula produced it. Driven by data.rate_summary from preview.
            function renderRateSummary(summary) {
                currentRateSummary = summary;
                const lines = (summary && Array.isArray(summary.lines)) ? summary.lines : [];
                if (!lines.length) {
                    rateSummaryEl.classList.add('d-none');
                    rateSummaryEl.innerHTML = '';
                    // No Rate summary means no "Show details" button to reach the table with,
                    // so reveal the detail table directly (preserves the old behaviour).
                    previewDetailsVisible = true;
                    applyPreviewDetailsVisibility();
                    return;
                }
                // Charges/rates are whole peso amounts (the rate matrix rounds to a whole
                // number), so show no decimals for whole values; a genuinely fractional value
                // (e.g. a USD conversion) still shows up to `d` decimals.
                const num = (v, d = 2) => (v === null || v === undefined || v === '')
                    ? '—'
                    : Number(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: d });
                const src = summary.fuel_source ? ` · fuel source: ${escapeHtml(summary.fuel_source)}` : '';
                const unmatched = Number(summary.unmatched || 0);
                // The banded fuel movement before the 0.4× surcharge factor. This
                // matches rate_matrix_formula_price() and the Master Data display.
                const fuelMovement = (l) => {
                    const pump = Number(l.pump_price);
                    const step = Number(l.price_movement);
                    const fuel = Number(l.fuel_price);
                    if (!Number.isFinite(pump) || pump <= 0 || !Number.isFinite(step) || step <= 0
                        || !Number.isFinite(fuel) || fuel <= pump) {
                        return '0.00%';
                    }
                    const steps = Math.floor((fuel - pump) / step);
                    return `${((Math.max(steps, 0) * step / pump) * 100).toLocaleString('en-US', {
                        minimumFractionDigits: 2, maximumFractionDigits: 2
                    })}%`;
                };
                // Equipment-rental charges (Chassis / Genset / Container Van / Fuel), per lane.
                // A dropdown at the top of the table picks the view: "Hauling" shows the fuel-rate
                // matrix columns + Subtotal; a rental activity shows Rate/Free hrs/Excess hrs; fuel
                // shows Price/Liter + Consumption. Only rental customers get the dropdown.
                const rentalActivities = (summary && Array.isArray(summary.rental_activities)) ? summary.rental_activities : [];
                const rentalByLane = (summary && summary.rental_by_lane) ? summary.rental_by_lane : {};
                const rentalTotals = (summary && summary.rental_totals) ? summary.rental_totals : {};
                const consumptionByLane = (summary && summary.rental_consumption_by_lane) ? summary.rental_consumption_by_lane : {};
                const hasRental = rentalActivities.length > 0;

                // View options: Hauling first, then each rental activity.
                const amountOptions = [{ code: 'hauling', label: 'Hauling Charges' }].concat(
                    rentalActivities.map(a => ({ code: a.code, label: `${a.short || a.code} Charges` }))
                );
                // The selected activity (persisted across live re-renders); fall back to Hauling
                // when the chosen one isn't available for this customer.
                let selectedActivity = hasRental ? rateSummaryActivity : 'hauling';
                if (!amountOptions.some(o => o.code === selectedActivity)) {
                    selectedActivity = 'hauling';
                    rateSummaryActivity = 'hauling';
                }
                const selIsHauling = selectedActivity === 'hauling';
                const selMeta = selIsHauling ? null : (rentalActivities.find(a => a.code === selectedActivity) || null);
                const selKind = selMeta ? selMeta.kind : 'hauling';          // 'hauling' | 'rental' | 'fuel'
                const selLabel = selMeta ? `${selMeta.short || selMeta.code} Charges` : 'Subtotal';
                const laneCell = (l) => `${escapeHtml(l.lane || '')}${l.tier ? ` <span class="badge bg-light text-dark">tier ${escapeHtml(l.tier)}</span>` : ''}`;

                const totals = computeStatementTotals(summary);
                const live = liveLineTotals();

                // Column layout depends on the selected view. Each shape defines its header cells,
                // a per-row cell builder, and the footer total (label colspan + amount).
                let headerCells;
                let rowFn;
                let footerColspan;
                let footerLabel;
                let footerAmount;

                if (selIsHauling) {
                    headerCells = [
                        '<th>Lane</th>',
                        '<th class="text-end">Base rate</th>',
                        '<th class="text-end">Fuel price</th>',
                        '<th class="text-end" title="Banded fuel movement used by the rate formula before the 0.4× factor">Fuel move %</th>',
                        '<th class="text-end">Charged rate</th>',
                        '<th class="text-end">Trips</th>',
                        '<th class="text-end">Subtotal</th>'
                    ];
                    rowFn = (l, trips, subtotal) => [
                        `<td>${laneCell(l)}</td>`,
                        `<td class="text-end">${num(l.base_rate)}</td>`,
                        `<td class="text-end">${l.fuel_price === null ? '—' : '₱' + num(l.fuel_price)}</td>`,
                        `<td class="text-end text-muted">${fuelMovement(l)}</td>`,
                        `<td class="text-end fw-semibold">${num(l.rate)}</td>`,
                        `<td class="text-end">${num(trips, 0)}</td>`,
                        `<td class="text-end fw-semibold">${num(subtotal)}</td>`
                    ];
                    footerColspan = 6;
                    footerLabel = 'Total charges in Peso';
                    footerAmount = num(totals.pesoTotal);
                } else if (selKind === 'fuel') {
                    // Fuel: effective Price/Liter (charge ÷ litres) and litres Consumed per lane.
                    headerCells = [
                        '<th>Lane</th>',
                        '<th class="text-end">Price/Liter</th>',
                        '<th class="text-end">Consumption (L)</th>',
                        '<th class="text-end">Trips</th>',
                        `<th class="text-end">${escapeHtml(selLabel)}</th>`
                    ];
                    rowFn = (l, trips) => {
                        const charge = (rentalByLane[l.key] || {})[selectedActivity];
                        const cons = (consumptionByLane[l.key] || {})[selectedActivity];
                        const price = (charge && cons && cons > 0) ? (charge / cons) : null;
                        return [
                            `<td>${laneCell(l)}</td>`,
                            `<td class="text-end">${price === null ? '—' : '₱' + num(price)}</td>`,
                            `<td class="text-end">${(cons === undefined || cons === null) ? '—' : num(cons)}</td>`,
                            `<td class="text-end">${num(trips, 0)}</td>`,
                            `<td class="text-end fw-semibold">${(charge === undefined || charge === null) ? '—' : num(charge)}</td>`
                        ];
                    };
                    footerColspan = 4;
                    footerLabel = `Total ${selLabel}`;
                    footerAmount = num(Number(rentalTotals[selectedActivity] || 0));
                } else {
                    // Rental (chassis / genset / container van): per-hour Rate, Free hours, and the
                    // billed Excess hours (= charge ÷ rate) per lane.
                    const rate = selMeta ? Number(selMeta.rate) : 0;
                    const freeHrs = selMeta ? Number(selMeta.free_hours) : 0;
                    headerCells = [
                        '<th>Lane</th>',
                        '<th class="text-end">Rate (₱/hr)</th>',
                        '<th class="text-end">Free hrs</th>',
                        '<th class="text-end">Excess hrs</th>',
                        '<th class="text-end">Trips</th>',
                        `<th class="text-end">${escapeHtml(selLabel)}</th>`
                    ];
                    rowFn = (l, trips) => {
                        const charge = (rentalByLane[l.key] || {})[selectedActivity];
                        const excess = (charge !== undefined && charge !== null && rate > 0) ? (charge / rate) : null;
                        return [
                            `<td>${laneCell(l)}</td>`,
                            `<td class="text-end">${num(rate)}</td>`,
                            `<td class="text-end">${num(freeHrs, 0)}</td>`,
                            `<td class="text-end">${excess === null ? '—' : num(excess)}</td>`,
                            `<td class="text-end">${num(trips, 0)}</td>`,
                            `<td class="text-end fw-semibold">${(charge === undefined || charge === null) ? '—' : num(charge)}</td>`
                        ];
                    };
                    footerColspan = 5;
                    footerLabel = `Total ${selLabel}`;
                    footerAmount = num(Number(rentalTotals[selectedActivity] || 0));
                }

                const rows = lines.map(function (l) {
                    const bucket = (live.hasKeys && l.key) ? (live.map[l.key] || { trips: 0, subtotal: 0 }) : null;
                    const trips = bucket ? bucket.trips : Number(l.trips);
                    const subtotal = bucket ? bucket.subtotal : Number(l.subtotal);
                    const dim = bucket && trips === 0 ? ' class="text-muted"' : '';
                    return `<tr${dim}>${rowFn(l, trips, subtotal).join('')}</tr>`;
                }).join('');

                let footer = `
                    <tr>
                        <td colspan="${footerColspan}" class="text-end fw-semibold">${footerLabel}</td>
                        <td class="text-end fw-semibold">${footerAmount}</td>
                    </tr>`;
                // USD conversion applies to the hauling peso total only.
                if (selIsHauling && totals.showUsd) {
                    footer += `
                        <tr>
                            <td colspan="6" class="text-end">FOREX rate (USD)</td>
                            <td class="text-end">${totals.forexDisplay === null ? '<span class="text-muted">mixed</span>' : num(totals.forexDisplay, 3)}</td>
                        </tr>
                        <tr>
                            <td colspan="6" class="text-end fw-semibold">Total charges in Dollar (US)</td>
                            <td class="text-end fw-semibold">${num(totals.dollarTotal, 5)}</td>
                        </tr>`;
                }
                // Dropdown to pick which activity the Rate summary shows.
                const activityDropdown = hasRental
                    ? `<select id="billingSummaryActivity" class="form-select form-select-sm" style="width:auto;" title="Show charges for">
                            ${amountOptions.map(o => `<option value="${o.code}"${o.code === selectedActivity ? ' selected' : ''}>${escapeHtml(o.label)}</option>`).join('')}
                       </select>`
                    : '';
                rateSummaryEl.classList.remove('d-none');
                rateSummaryEl.innerHTML = `
                    <div class="card border-0" style="background:#f8f9fa;">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                                <i class="bi bi-cash-coin me-2"></i>
                                <strong class="small">Rate summary</strong>
                                <span class="small text-muted ms-2">how each charged rate was computed${src}</span>
                                <div class="ms-auto d-flex align-items-center gap-2">
                                    ${unmatched > 0 ? `<span class="badge bg-warning text-dark">${unmatched} trip(s) unpriced</span>` : ''}
                                    ${activityDropdown}
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="billingToggleDetails">
                                        <i class="bi ${previewDetailsVisible ? 'bi-eye-slash' : 'bi-table'} me-1"></i>${previewDetailsVisible ? 'Hide details' : 'Show details'}
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>${headerCells.join('')}</tr>
                                    </thead>
                                    <tbody>${rows}</tbody>
                                    <tfoot class="border-top">${footer}</tfoot>
                                </table>
                            </div>
                        </div>
                    </div>`;
            }

            function setAllSelections(selected) {
                previewRecords = previewRecords.map(record => ({ ...record, selected }));
                renderPreview();
            }

            function markPreviewStale() {
                if (!previewRecords.length) return;
                if (previewSignature === currentSignature()) return;
                statusEl.className = 'small mt-2 text-warning';
                statusEl.textContent = 'Filters changed. Preview rows again before downloading.';
            }

            // Full-screen progress modal with an animated percentage bar. A single fetch
            // gives no real byte progress, so the bar eases toward 90% while the request is
            // in flight and jumps to 100% when it resolves.
            const billingProgress = (function () {
                let overlay = null, barEl = null, labelEl = null, timer = null, pct = 0;
                function ensure() {
                    if (overlay) return;
                    document.body.insertAdjacentHTML('beforeend',
                        '<div id="billingProgressOverlay" style="position:fixed;inset:0;z-index:2000;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.45)">' +
                          '<div style="background:#fff;border-radius:.6rem;padding:1.5rem 1.75rem;width:min(420px,92vw);box-shadow:0 12px 40px rgba(0,0,0,.3)">' +
                            '<div class="d-flex align-items-center mb-2"><div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div><strong id="billingProgressLabel">Working…</strong></div>' +
                            '<div class="progress" style="height:22px"><div id="billingProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary fw-semibold" role="progressbar" style="width:0%">0%</div></div>' +
                          '</div></div>');
                    overlay = document.getElementById('billingProgressOverlay');
                    barEl = document.getElementById('billingProgressBar');
                    labelEl = document.getElementById('billingProgressLabel');
                }
                function set(p) { pct = Math.max(0, Math.min(100, p)); barEl.style.width = pct + '%'; barEl.textContent = Math.round(pct) + '%'; }
                return {
                    start(label) {
                        ensure();
                        labelEl.textContent = label || 'Working…';
                        overlay.style.display = 'flex';
                        set(0);
                        clearInterval(timer);
                        timer = setInterval(function () { if (pct < 90) set(pct + Math.max(0.5, (90 - pct) * 0.08)); }, 180);
                    },
                    finish() {
                        clearInterval(timer);
                        if (!overlay) return;
                        set(100);
                        setTimeout(function () { overlay.style.display = 'none'; }, 350);
                    }
                };
            })();

            async function loadPreview() {
                if (!validate()) return;
                setButtonsDisabled(true);
                billingProgress.start('Loading preview rows…');
                statusEl.className = 'small mt-2 text-muted';
                statusEl.textContent = 'Loading preview rows...';

                try {
                    const params = new URLSearchParams({
                        customer: customer.value,
                        activity: 'hauling',
                        date_from: dateFrom.value,
                        date_to: dateTo.value,
                        include_billed: includeBilled.checked ? '1' : '0'
                    });
                    if (isShuttling()) params.set('lane', lane.value);
                    if (isRawView()) params.set('view', 'raw');
                    const res = await fetch(`php/fetch/get_customer_billing_preview.php?${params.toString()}`, { cache: 'no-store' });
                    const responseText = await res.text();
                    let data;
                    try {
                        data = JSON.parse(responseText);
                    } catch (_error) {
                        throw new Error(`The preview service returned an invalid response (HTTP ${res.status}).`);
                    }
                    if (!data.success) {
                        statusEl.className = 'small mt-2 text-danger';
                        statusEl.textContent = data.message || 'Preview failed.';
                        previewColumns = [];
                        previewRecords = [];
                        previewTotalEl.textContent = '';
                        renderNotes(data.notes);
                        renderRateSummary(null);
                        renderPreview();
                        return;
                    }
                    // Each new preview starts with the detail table collapsed — the user
                    // reveals it via the Rate summary's "Show details" button — and the amount
                    // column back on Hauling.
                    previewDetailsVisible = false;
                    applyPreviewDetailsVisibility();
                    rateSummaryActivity = 'hauling';
                    previewSignature = currentSignature();
                    previewColumns = data.columns || [];
                    previewSelectable = data.selectable !== false;
                    previewRecords = (data.records || []).map(record => ({ ...record, selected: true, manual_forex: '' }));
                    previewSearchTerm = '';
                    previewSearch.value = '';
                    previewTotalEl.textContent = (data.total || data.total === 0)
                        ? 'Total: ' + Number(data.total).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
                        : '';
                    renderNotes(data.notes);
                    renderRateSummary(data.rate_summary);
                    renderPreview();
                    statusEl.className = 'small mt-2 text-success';
                    statusEl.textContent = `${data.count} row(s) ready for review.`;
                } catch (err) {
                    statusEl.className = 'small mt-2 text-danger';
                    statusEl.textContent = 'Preview failed: ' + err.message;
                } finally {
                    billingProgress.finish();
                    previewBtn.disabled = false;
                    updateSelectionSummary();
                }
            }

            async function generate() {
                if (!validate()) return;
                if (!previewRecords.length) {
                    statusEl.className = 'small mt-2 text-danger';
                    statusEl.textContent = 'Preview the rows first before downloading.';
                    return;
                }
                if (previewSignature !== currentSignature()) {
                    statusEl.className = 'small mt-2 text-danger';
                    statusEl.textContent = 'Filters changed. Refresh the preview before downloading.';
                    return;
                }

                const selectedIds = previewRecords.filter(record => record.selected).map(record => record.entry_id);
                if (!selectedIds.length) {
                    statusEl.className = 'small mt-2 text-danger';
                    statusEl.textContent = 'Select at least one trip to include in the download.';
                    return;
                }

                setButtonsDisabled(true);
                billingProgress.start('Generating billing…');
                statusEl.className = 'small mt-2 text-muted';
                statusEl.textContent = 'Generating, please wait...';

                const payload = new FormData();
                payload.append('customer', customer.value);
                payload.append('activity', 'hauling');
                payload.append('date_from', dateFrom.value);
                payload.append('date_to', dateTo.value);
                payload.append('document_date', documentDate.value || '');
                payload.append('include_billed', includeBilled.checked ? '1' : '0');
                if (isShuttling()) payload.append('lane', lane.value);
                // Preview rows are the SAP entry set (RAW view included — those rows are
                // the SAP records, RAW-shaped), so the selection maps straight to the
                // download: unticked rows are excluded, drag order is the line order.
                payload.append('selected_entry_ids', selectedIds.join(','));
                payload.append('entry_order', previewRecords.map(record => record.entry_id).join(','));
                const manualForexByEntry = {};
                previewRecords.filter(record => record.selected && record.manual_forex_needed && Number(record.manual_forex) > 0)
                    .forEach(record => { manualForexByEntry[record.entry_id] = Number(record.manual_forex); });
                payload.append('manual_forex_by_entry', JSON.stringify(manualForexByEntry));

                try {
                    const res = await fetch('php/insert/generate_customer_billing.php', { method: 'POST', body: payload });
                    const data = await res.json();
                    if (!data.success) {
                        // Missing Master Data assignments — pop a SweetAlert listing them.
                        if (data.blocked && Array.isArray(data.missing) && data.missing.length) {
                            const items = data.missing.map(m => `<li>${escapeHtml(m)}</li>`).join('');
                            statusEl.className = 'small mt-2 text-danger';
                            statusEl.textContent = data.message || 'Missing Master Data assignments.';
                            Swal.fire({
                                title: 'Not billed — missing Master Data',
                                html: `This customer isn't ready to bill yet. Assign the following <strong>to this customer</strong> in <strong>Master Data → Customer SAP Codes</strong> (adding to the master lists alone isn't enough):<ul class="text-start mt-2 mb-0">${items}</ul>`,
                                icon: 'warning',
                                confirmButtonColor: '#dc3545'
                            });
                            return;
                        }
                        statusEl.className = 'small mt-2 text-danger';
                        statusEl.textContent = data.message || 'Generation failed.';
                        return;
                    }
                    statusEl.className = 'small mt-2 text-success';
                    statusEl.textContent = `${data.message} The file is ready in Generated Invoices.`;
                    loadInvoices();
                } catch (err) {
                    statusEl.className = 'small mt-2 text-danger';
                    statusEl.textContent = 'Generation failed: ' + err.message;
                } finally {
                    billingProgress.finish();
                    previewBtn.disabled = false;
                    updateSelectionSummary();
                }
            }

            previewBtn.addEventListener('click', loadPreview);
            generateBtn.addEventListener('click', generate);
            previewSearch.addEventListener('input', function () {
                previewSearchTerm = this.value;
                renderPreview();
            });
            selectAllButton.addEventListener('click', function () { setAllSelections(true); });
            clearAllButton.addEventListener('click', function () { setAllSelections(false); });
            [customer, lane, dateFrom, dateTo, includeBilled].forEach(function (element) {
                element.addEventListener('change', markPreviewStale);
            });
            updateSelectionSummary();

            // Auto-clear flag icons for records that were resolved elsewhere (on the
            // For Update page). We only ask the server about the entry_ids currently
            // shown as flagged, so the payload stays tiny, and we skip the poll when
            // nothing is flagged or the tab is hidden to keep egress low.
            async function refreshFlagStates() {
                if (document.hidden) return;
                const flaggedIds = previewRecords
                    .filter(r => r.flagged && r.entry_id)
                    .map(r => r.entry_id);
                if (!flaggedIds.length) return;
                try {
                    const res = await fetch(
                        'php/fetch/get_open_flags.php?entry_ids=' + encodeURIComponent(flaggedIds.join(',')),
                        { cache: 'no-store' }
                    );
                    const data = await res.json();
                    if (!data || !data.success) return;
                    const stillOpen = new Set((data.open_entry_ids || []).map(Number));
                    let changed = false;
                    previewRecords.forEach(function (r) {
                        if (r.flagged && r.entry_id && !stillOpen.has(Number(r.entry_id))) {
                            r.flagged = false;
                            r.flag_remarks = '';
                            changed = true;
                        }
                    });
                    if (changed) renderPreview();
                } catch (_) {}
            }
            setInterval(refreshFlagStates, 15000);
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) refreshFlagStates();
            });

            // Flag a record for update (with a remark) — appears in the For Update queue.
            previewTableBody.addEventListener('click', async function (event) {
                const flagBtn = event.target.closest('.billing-flag-btn');
                if (!flagBtn) return;
                const row = event.target.closest('tr');
                if (!row || !row.dataset.entryId) return;
                const entryId = Number(row.dataset.entryId);
                if (!entryId) return;
                const record = previewRecords.find(r => r.entry_id === entryId);
                let updateFields = [];
                let encodedBy = '';
                let encodedAt = '';
                try {
                    const fieldRes = await fetch(`php/fetch/get_update_form_fields.php?entry_id=${encodeURIComponent(entryId)}`, { cache: 'no-store' });
                    const fieldData = await fieldRes.json();
                    if (!fieldData.success || !Array.isArray(fieldData.fields)) throw new Error(fieldData.message || 'Could not load fields');
                    updateFields = fieldData.fields;
                    encodedBy = fieldData.encoded_by || '';
                    encodedAt = fieldData.encoded_at || '';
                } catch (err) {
                    Swal.fire({ title: 'Error', text: err.message || 'Could not load this entry form fields.', icon: 'error' });
                    return;
                }
                let lastSection = '';
                const fieldCards = updateFields.map(({ field: key, label, section, value }) => {
                    const heading = section !== lastSection ? (lastSection = section, `<div class="update-field-section">${section}</div>`) : '';
                    const current = value ? escapeHtml(value) : '<em>blank</em>';
                    return heading + `<div class="update-field-card text-start"><div class="form-check"><input class="form-check-input update-field-check" type="checkbox" value="${key}" id="update_${key}"><label class="form-check-label fw-semibold" for="update_${key}">${label}</label></div><div class="update-field-current">Current: ${current}</div><div class="update-field-note-wrap d-none"><input class="form-control form-control-sm update-field-note mt-2" data-field="${key}" placeholder="What should be changed?" disabled></div></div>`;
                }).join('');
                const encoderHtml = encodedBy
                    ? `<div class="text-start small mt-2 mb-1"><i class="bi bi-person-badge me-1"></i><span class="text-muted">Encoded by:</span> <strong>${escapeHtml(encodedBy)}</strong>${encodedAt ? ` <span class="text-muted">on ${escapeHtml(encodedAt)}</span>` : ''}</div>`
                    : '';
                const fieldHtml = `${encoderHtml}<p class="text-muted small text-start mt-2 mb-2">Select the fields that need correction. The list matches this record's entry form.</p><div class="update-fields-grid">${fieldCards}</div>`;

                const result = await Swal.fire({
                    title: `Flag entry #${entryId} for update`,
                    input: 'textarea',
                    inputLabel: 'What needs to be updated / corrected?',
                    inputValue: (record && record.flag_remarks) || '',
                    inputPlaceholder: 'e.g. Missing HR meter end, wrong van number…',
                    inputAttributes: { 'aria-label': 'Remarks' },
                    didOpen: () => {
                        const popup = Swal.getPopup();
                        if (popup) popup.classList.add('update-flag-popup');
                        if (!document.getElementById('updateFlagDialogStyle')) {
                            document.head.insertAdjacentHTML('beforeend', `<style id="updateFlagDialogStyle">
                                .update-flag-popup{width:min(920px,94vw)!important;padding:1.5rem!important}.update-flag-popup .swal2-textarea{min-height:70px!important}.update-fields-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem;max-height:360px;overflow:auto;padding:.15rem .3rem .4rem}.update-field-section{grid-column:1/-1;font-size:.78rem;font-weight:700;text-transform:uppercase;color:#6c757d;border-bottom:1px solid #dee2e6;padding:.45rem 0 .1rem}.update-field-card{border:1px solid #dee2e6;border-radius:.45rem;padding:.6rem;background:#fff}.update-field-current{font-size:.76rem;color:#6c757d;margin:.25rem 0 0 1.5rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.update-field-card:has(.update-field-check:checked){border-color:#f0ad4e;background:#fffaf0}@media(max-width:640px){.update-fields-grid{grid-template-columns:1fr;max-height:300px}}</style>`);
                        }
                        const input = Swal.getInput();
                        input?.insertAdjacentHTML('afterend', fieldHtml);
                        document.querySelectorAll('.update-field-check').forEach(check => check.addEventListener('change', () => {
                            const note = document.querySelector(`.update-field-note[data-field="${check.value}"]`);
                            const wrap = note?.closest('.update-field-note-wrap');
                            if (note) { note.disabled = !check.checked; wrap?.classList.toggle('d-none', !check.checked); if (check.checked) note.focus(); }
                        }));
                    },
                    showCancelButton: true,
                    confirmButtonText: 'Flag for update',
                    confirmButtonColor: '#f0ad4e',
                    preConfirm: (value) => {
                        const fieldNotes = [...document.querySelectorAll('.update-field-check:checked')].map(check => ({ field: check.value, message: document.querySelector(`.update-field-note[data-field="${check.value}"]`)?.value.trim() || '' }));
                        if (fieldNotes.some(item => !item.message)) { Swal.showValidationMessage('Enter an instruction for every selected field.'); return false; }
                        const remarks = String(value || '').trim() || fieldNotes.map(item => item.message).join('; ');
                        if (!remarks) { Swal.showValidationMessage('Select a field or enter a general remark.'); return false; }
                        return { remarks, fieldNotes };
                    }
                });
                if (!result.isConfirmed) return;

                const fd = new FormData();
                fd.append('entry_id', String(entryId));
                fd.append('remarks', result.value.remarks);
                fd.append('field_notes', JSON.stringify(result.value.fieldNotes));
                try {
                    const res = await fetch('php/insert/flag_for_update.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Flag failed');
                    if (record) { record.flagged = true; record.flag_remarks = result.value.remarks; }
                    renderPreview();
                    Swal.fire({ title: 'Flagged', text: data.message, icon: 'success', timer: 1400, showConfirmButton: false });
                } catch (err) {
                    Swal.fire({ title: 'Error', text: err.message, icon: 'error' });
                }
            });
            previewTableBody.addEventListener('input', function (event) {
                const input = event.target.closest('.billing-manual-forex');
                if (!input) return;
                const row = input.closest('tr');
                const record = previewRecords.find(item => String(item.entry_id) === String(row?.dataset.entryId || ''));
                if (record) {
                    record.manual_forex = input.value;
                    refreshRateSummaryTotals();
                }
            });

            // Fill the DOLLAR CONVERTION for every row that needs one in a single click.
            function applyBulkForex() {
                const value = (bulkForexInput.value || '').trim();
                if (!(Number(value) > 0)) {
                    bulkForexInput.focus();
                    return;
                }
                let filled = 0;
                previewRecords.forEach(function (record) {
                    if (record.manual_forex_needed) { record.manual_forex = value; filled++; }
                });
                renderPreview(); // re-renders the inputs with the applied value
                if (filled) {
                    // brief confirmation on the button
                    const original = bulkForexApply.innerHTML;
                    bulkForexApply.innerHTML = '<i class="bi bi-check2"></i> Applied to ' + filled;
                    setTimeout(function () { bulkForexApply.innerHTML = original; }, 1200);
                }
            }
            if (bulkForexApply) bulkForexApply.addEventListener('click', applyBulkForex);
            if (bulkForexInput) bulkForexInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); applyBulkForex(); }
            });

            previewTableBody.addEventListener('change', function (event) {
                const checkbox = event.target.closest('.billing-row-check');
                const row = event.target.closest('tr');
                if (!checkbox || !row) return;
                const entryId = Number(row.dataset.entryId);
                previewRecords = previewRecords.map(record => (
                    record.entry_id === entryId ? { ...record, selected: checkbox.checked } : record
                ));
                updateSelectionSummary();
            });

            (function attachPreviewDragReorder() {
                let dragSrcId = null;

                previewTableBody.addEventListener('dragstart', function (event) {
                    const row = event.target.closest('tr');
                    if (!row || !row.dataset.entryId) return;
                    dragSrcId = Number(row.dataset.entryId);
                    row.classList.add('dragging');
                    if (event.dataTransfer) {
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', String(dragSrcId));
                    }
                });

                previewTableBody.addEventListener('dragover', function (event) {
                    event.preventDefault();
                    const row = event.target.closest('tr');
                    previewTableBody.querySelectorAll('tr.drag-over').forEach(item => item.classList.remove('drag-over'));
                    if (row && row.dataset.entryId) row.classList.add('drag-over');
                });

                previewTableBody.addEventListener('drop', function (event) {
                    event.preventDefault();
                    const row = event.target.closest('tr');
                    previewTableBody.querySelectorAll('tr.drag-over').forEach(item => item.classList.remove('drag-over'));
                    if (!row || !row.dataset.entryId || dragSrcId == null) return;
                    const targetId = Number(row.dataset.entryId);
                    if (targetId === dragSrcId) return;
                    const from = previewRecords.findIndex(record => record.entry_id === dragSrcId);
                    const to = previewRecords.findIndex(record => record.entry_id === targetId);
                    if (from < 0 || to < 0) return;
                    const [moved] = previewRecords.splice(from, 1);
                    previewRecords.splice(to, 0, moved);
                    renderPreview();
                    dragSrcId = null;
                });

                previewTableBody.addEventListener('dragend', function () {
                    previewTableBody.querySelectorAll('tr.dragging').forEach(item => item.classList.remove('dragging'));
                    previewTableBody.querySelectorAll('tr.drag-over').forEach(item => item.classList.remove('drag-over'));
                    dragSrcId = null;
                });
            })();

            document.getElementById('refreshInvoices').addEventListener('click', loadInvoices);

            document.querySelector('#invoicesTable tbody').addEventListener('click', async function (e) {
                const viewBtn = e.target.closest('.btn-view-activity-pdf');
                if (viewBtn) {
                    e.preventDefault();
                    const params = new URLSearchParams({ id: viewBtn.dataset.id, activity: viewBtn.dataset.activity, view: '1' });
                    window.open('php/fetch/download_billing_pdf.php?' + params.toString(), '_blank');
                    return;
                }

                const chargesBtn = e.target.closest('.btn-view-charges');
                if (chargesBtn) {
                    openChargesModal(Number(chargesBtn.dataset.id));
                    return;
                }

                const btn = e.target.closest('.btn-del-invoice');
                if (!btn) return;
                const confirm = await Swal.fire({
                    title: 'Delete this invoice?',
                    text: 'The file will be removed and its trips will become billable again.',
                    icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#dc3545'
                });
                if (!confirm.isConfirmed) return;
                const fd = new FormData();
                fd.append('id', btn.dataset.id);
                try {
                    const res = await fetch('php/delete/customer_billing.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Delete failed');
                    await loadInvoices();
                    Swal.fire({ title: 'Deleted', text: data.message, icon: 'success' });
                } catch (err) {
                    Swal.fire({ title: 'Error', text: err.message, icon: 'error' });
                }
            });

            // ---- Billed Charges modal: view + edit the per-trip charges locked at
            // generation. Saving updates the DB value only (not the downloaded file). ----
            let chargesModalInstance = null;
            let chargesModalInvoiceId = 0;
            const chargesNumFmt = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });

            async function openChargesModal(invoiceId) {
                chargesModalInvoiceId = invoiceId;
                const modalEl = document.getElementById('chargesModal');
                chargesModalInstance = chargesModalInstance || new bootstrap.Modal(modalEl);
                const body = document.querySelector('#chargesModalTable tbody');
                body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Loading…</td></tr>';
                document.getElementById('chargesModalSub').textContent = '';
                document.getElementById('chargesModalStatus').textContent = '';
                document.getElementById('chargesModalNote').classList.add('d-none');
                document.getElementById('chargesModalTotal').textContent = '0.00';
                chargesModalInstance.show();
                try {
                    const res = await fetch('php/fetch/get_invoice_entry_charges.php?id=' + encodeURIComponent(invoiceId), { cache: 'no-store' });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Load failed');
                    renderChargesModal(data);
                } catch (err) {
                    body.innerHTML = `<tr><td colspan="6" class="text-danger text-center py-3">${escapeHtml(err.message)}</td></tr>`;
                }
            }

            function renderChargesModal(data) {
                const inv = data.invoice || {};
                document.getElementById('chargesModalSub').textContent =
                    `${inv.document_no || ('Invoice #' + inv.invoice_id)} · ${inv.customer_label || ''} · ${inv.date_from} to ${inv.date_to}`;
                const note = document.getElementById('chargesModalNote');
                if (!data.has_locked_charges) {
                    note.classList.remove('d-none');
                    note.textContent = 'This invoice was generated before charges were locked, so no per-trip charge is recorded yet. Type each charge below to backfill it.';
                } else {
                    note.classList.add('d-none');
                }
                const body = document.querySelector('#chargesModalTable tbody');
                body.innerHTML = (data.entries || []).map((en, i) => `
                    <tr data-entry-id="${en.entry_id}">
                        <td class="text-muted">${String(i + 1).padStart(2, '0')}</td>
                        <td>${escapeHtml(en.date || '-')}</td>
                        <td>${escapeHtml(en.trip_receipt || '-')}</td>
                        <td>${escapeHtml(en.truck || '-')}</td>
                        <td>${escapeHtml(en.van || '-')}</td>
                        <td>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">₱</span>
                                <input type="number" min="0" step="0.01" class="form-control text-end charges-input" value="${en.rate_charge !== null ? en.rate_charge : ''}" placeholder="—">
                                <button type="button" class="btn btn-outline-primary charges-save" title="Save this charge">${en.manual_charge ? '<i class="bi bi-pencil-fill"></i>' : '<i class="bi bi-check-lg"></i>'}</button>
                            </div>
                        </td>
                    </tr>`).join('') || '<tr><td colspan="6" class="text-center text-muted py-3">No entries on this invoice.</td></tr>';
                document.getElementById('chargesModalTotal').textContent = chargesNumFmt(data.total);
            }

            function recomputeChargesModalTotal() {
                let total = 0;
                document.querySelectorAll('#chargesModalTable tbody .charges-input').forEach(function (inp) {
                    const n = parseFloat(inp.value);
                    if (Number.isFinite(n)) total += n;
                });
                document.getElementById('chargesModalTotal').textContent = chargesNumFmt(total);
            }

            document.querySelector('#chargesModalTable tbody').addEventListener('input', recomputeChargesModalTotal);
            document.querySelector('#chargesModalTable tbody').addEventListener('click', async function (e) {
                const saveBtn = e.target.closest('.charges-save');
                if (!saveBtn) return;
                const row = saveBtn.closest('tr');
                const entryId = Number(row.dataset.entryId);
                const input = row.querySelector('.charges-input');
                const value = (input.value || '').trim();
                if (value === '' || !(Number(value) >= 0)) {
                    input.classList.add('is-invalid');
                    return;
                }
                input.classList.remove('is-invalid');
                saveBtn.disabled = true;
                const original = saveBtn.innerHTML;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                const fd = new FormData();
                fd.append('invoice_id', String(chargesModalInvoiceId));
                fd.append('entry_id', String(entryId));
                fd.append('rate_charge', value);
                const st = document.getElementById('chargesModalStatus');
                try {
                    const res = await fetch('php/update/update_invoice_entry_charge.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Save failed');
                    document.getElementById('chargesModalTotal').textContent = chargesNumFmt(data.total);
                    saveBtn.innerHTML = '<i class="bi bi-pencil-fill"></i>';
                    st.className = 'small mb-2 text-success';
                    st.textContent = 'Saved charge for trip receipt ' + (row.children[2].textContent || '').trim() + '.';
                } catch (err) {
                    saveBtn.innerHTML = original;
                    st.className = 'small mb-2 text-danger';
                    st.textContent = err.message;
                } finally {
                    saveBtn.disabled = false;
                }
            });

            document.getElementById('chargesRebuildBtn').addEventListener('click', async function () {
                const btn = this;
                const st = document.getElementById('chargesRebuildStatus');
                btn.disabled = true;
                const original = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Rebuilding…';
                st.className = 'small text-muted me-auto';
                st.textContent = '';
                const fd = new FormData();
                fd.append('id', String(chargesModalInvoiceId));
                try {
                    const res = await fetch('php/update/regenerate_invoice_file.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Rebuild failed');
                    st.className = 'small text-success me-auto';
                    st.textContent = data.message;
                    loadInvoices();
                } catch (err) {
                    st.className = 'small text-danger me-auto';
                    st.textContent = err.message;
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = original;
                }
            });

            loadInvoices();
        });
    </script>
</body>
</html>
