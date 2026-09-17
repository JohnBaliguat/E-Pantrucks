<?php
include "php/session-check.php";
require_once dirname(__DIR__, 2) . "/php/helpers/box_banana_customers.php";
$boxBananaCustomers = box_banana_customers_config();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Bananas Billing - DataEncode System</title>
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
        .drag-handle { cursor: grab; color: #6c757d; }
        #bbPreviewTable tbody tr.dragging { opacity: 0.55; }
        #bbPreviewTable tbody tr.drag-over { outline: 2px solid #0d6efd; outline-offset: -2px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include "sidebar.php"; ?>

        <div id="content">
            <?php include "navbar.php"; ?>

            <div class="main-content">
                <div class="content-header mb-4">
                    <h2 class="fw-bold">Box Bananas Billing</h2>
                    <p class="text-muted">Generate the breakbulk-banana hauling statement (PANABO TRUCKING SERVICES) for a customer and date range.</p>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>Box Bananas Statement</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Pick a customer and date range. Each unbilled trip becomes a statement line (DATE, TRIP RECEIPT, TRUCK, CHASSIS, DCODE, DRIVER, BOXES, TRIPS, AMOUNT). The AMOUNT uses the rate from Master Data &rarr; Rates. Already-billed trips are skipped automatically.</p>
                        <form id="bbForm" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="bbCustomer" class="form-label">Customer</label>
                                <select class="form-select" id="bbCustomer" required>
                                    <?php foreach ($boxBananaCustomers as $key => $cfg): ?>
                                        <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($cfg["label"]); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="bbDateFrom" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="bbDateFrom" required>
                            </div>
                            <div class="col-md-3">
                                <label for="bbDateTo" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="bbDateTo" required>
                            </div>
                            <div class="col-md-3 d-flex gap-2">
                                <button type="button" class="btn btn-primary" id="bbPreview">
                                    <i class="bi bi-eye me-1"></i>Preview Rows
                                </button>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="bbIncludeBilled">
                                    <label class="form-check-label small text-muted" for="bbIncludeBilled">
                                        Include already-billed trips (re-generate)
                                    </label>
                                </div>
                                <div id="bbStatus" class="small mt-2"></div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-4 d-none" id="bbPreviewCard">
                    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Review Before Download</h5>
                            <div class="small text-muted mt-1">Untick trips to exclude them, then drag rows to change the statement order.</div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="small text-muted" id="bbSelectedCount">0 selected</span>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="bbSelectAllButton">Select all</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="bbClearAllButton">Clear all</button>
                            <button type="button" class="btn btn-success btn-sm" id="bbGenerate">
                                <i class="bi bi-file-earmark-excel me-1"></i>Download Statement
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="bbTotals" class="small text-muted mb-2"></div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="bbPreviewTable">
                                <thead class="table-light" id="bbPreviewHead">
                                    <tr>
                                        <th class="text-center" style="width:36px;"></th>
                                        <th class="text-center" style="width:40px;">
                                            <input class="form-check-input" type="checkbox" id="bbSelectAllCheck" checked>
                                        </th>
                                        <th>ID</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-archive me-2"></i>Generated Statements</h5>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="refreshStatements">
                            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="statementsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Statement</th>
                                        <th>Customer</th>
                                        <th>Date Range</th>
                                        <th>Amount</th>
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

    <!-- View / edit the per-trip peso charges locked onto a generated statement. -->
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
                    <p class="small text-muted mb-0"><i class="bi bi-info-circle me-1"></i>Editing a charge updates the stored (database) value only. Use <strong>Rebuild file</strong> to regenerate the downloadable file from these charges.</p>
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
        $(document).ready(function () { $("#bbnav").attr({ "class": "nav-link active" }); });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const customer = document.getElementById('bbCustomer');
            const dateFrom = document.getElementById('bbDateFrom');
            const dateTo = document.getElementById('bbDateTo');
            const includeBilled = document.getElementById('bbIncludeBilled');
            const previewBtn = document.getElementById('bbPreview');
            const generateBtn = document.getElementById('bbGenerate');
            const statusEl = document.getElementById('bbStatus');
            const totalsEl = document.getElementById('bbTotals');
            const previewCard = document.getElementById('bbPreviewCard');
            const previewHead = document.getElementById('bbPreviewHead');
            const previewTableBody = document.querySelector('#bbPreviewTable tbody');
            const selectedCountEl = document.getElementById('bbSelectedCount');
            let selectAllCheck = document.getElementById('bbSelectAllCheck');
            const selectAllButton = document.getElementById('bbSelectAllButton');
            const clearAllButton = document.getElementById('bbClearAllButton');

            let previewColumns = [];
            let previewRecords = [];
            let previewSignature = '';

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
            function money(v) {
                if (v === null || v === undefined || v === '') return '-';
                return Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function currentSignature() {
                return JSON.stringify({
                    customer: customer.value,
                    date_from: dateFrom.value,
                    date_to: dateTo.value,
                    include_billed: includeBilled.checked ? '1' : '0'
                });
            }

            function setButtonsDisabled(disabled) {
                previewBtn.disabled = disabled;
                generateBtn.disabled = disabled;
            }

            const table = new DataTable('#statementsTable', {
                order: [[5, 'desc']],
                pageLength: 10,
                columnDefs: [{ orderable: false, targets: [7] }]
            });

            function statementRow(item) {
                const reportCell = `
                    <div class="report-cell">
                        <i class="bi bi-file-earmark-excel-fill report-icon"></i>
                        <div>
                            <div class="fw-semibold">${escapeHtml(item.reference || ('Statement #' + item.statement_id))}</div>
                            <div class="text-muted small">${escapeHtml(item.file_name)}</div>
                        </div>
                    </div>`;
                const canDownload = item.status === 'ready';
                const dl = canDownload
                    ? `<a href="php/fetch/download_box_banana.php?id=${item.statement_id}" class="btn btn-outline-secondary btn-sm" title="Download Excel"><i class="bi bi-file-earmark-excel"></i></a>`
                    : `<button type="button" class="btn btn-outline-secondary btn-sm" disabled title="File missing"><i class="bi bi-file-earmark-excel"></i></button>`;
                const chargesBtn = `<button type="button" class="btn btn-outline-info btn-sm btn-view-charges" data-id="${item.statement_id}" title="View / edit billed charges"><i class="bi bi-cash-stack"></i></button>`;
                const actions = `<div class="d-flex justify-content-end gap-2">${dl}${chargesBtn}
                    <button type="button" class="btn btn-outline-dark btn-sm btn-del-statement" data-id="${item.statement_id}" title="Delete"><i class="bi bi-trash"></i></button></div>`;
                return [
                    reportCell,
                    escapeHtml(item.customer_label || '-'),
                    `${escapeHtml(item.date_from)} to ${escapeHtml(item.date_to)}`,
                    money(item.total_amount),
                    `${item.line_count} line${item.line_count === 1 ? '' : 's'}`,
                    formatStamp(item.requested_at),
                    statusBadge(item.status),
                    actions
                ];
            }

            async function loadStatements() {
                try {
                    const res = await fetch('php/fetch/get_box_banana_statements.php', { cache: 'no-store' });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Load failed');
                    table.clear();
                    (data.statements || []).forEach(i => table.row.add(statementRow(i)));
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
                selectedCountEl.textContent = `${selectedCount} selected of ${previewRecords.length}`;
                selectAllCheck.checked = previewRecords.length > 0 && selectedCount === previewRecords.length;
                selectAllCheck.indeterminate = selectedCount > 0 && selectedCount < previewRecords.length;
                generateBtn.disabled = selectedCount === 0;
            }

            function renderPreview() {
                const headerCells = [
                    '<th class="text-center" style="width:36px;"></th>',
                    '<th class="text-center" style="width:40px;"><input class="form-check-input" type="checkbox" id="bbSelectAllCheck"></th>',
                    '<th>ID</th>'
                ];
                previewColumns.forEach(function (column) {
                    headerCells.push(`<th>${escapeHtml(column.label || '-')}</th>`);
                });
                previewHead.innerHTML = `<tr>${headerCells.join('')}</tr>`;

                previewTableBody.innerHTML = '';
                previewRecords.forEach(function (record) {
                    const tr = document.createElement('tr');
                    tr.draggable = true;
                    tr.dataset.entryId = String(record.entry_id);
                    const cellMap = Object.fromEntries((record.cells || []).map(cell => [cell.key, cell.value]));
                    const dataCells = previewColumns.map(function (column) {
                        return `<td>${escapeHtml(cellMap[column.key] || '-')}</td>`;
                    }).join('');
                    tr.innerHTML = `
                        <td class="text-center"><span class="drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></span></td>
                        <td class="text-center"><input class="form-check-input bb-row-check" type="checkbox" ${record.selected ? 'checked' : ''}></td>
                        <td><strong>#${record.entry_id}</strong></td>
                        ${dataCells}
                    `;
                    previewTableBody.appendChild(tr);
                });

                const previousChecked = selectAllCheck ? selectAllCheck.checked : false;
                const previousIndeterminate = selectAllCheck ? selectAllCheck.indeterminate : false;
                const refreshedSelectAll = document.getElementById('bbSelectAllCheck');
                selectAllCheck = refreshedSelectAll;
                refreshedSelectAll.checked = previousChecked;
                refreshedSelectAll.indeterminate = previousIndeterminate;
                refreshedSelectAll.addEventListener('change', function () { setAllSelections(refreshedSelectAll.checked); });

                previewCard.classList.toggle('d-none', previewRecords.length === 0);
                updateSelectionSummary();
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

            async function loadPreview() {
                if (!validate()) return;
                setButtonsDisabled(true);
                statusEl.className = 'small mt-2 text-muted';
                statusEl.textContent = 'Loading preview rows...';

                try {
                    const params = new URLSearchParams({
                        customer: customer.value,
                        date_from: dateFrom.value,
                        date_to: dateTo.value,
                        include_billed: includeBilled.checked ? '1' : '0'
                    });
                    const res = await fetch(`php/fetch/get_box_banana_preview.php?${params.toString()}`, { cache: 'no-store' });
                    const data = await res.json();
                    if (!data.success) {
                        statusEl.className = 'small mt-2 text-danger';
                        statusEl.textContent = data.message || 'Preview failed.';
                        previewColumns = [];
                        previewRecords = [];
                        totalsEl.textContent = '';
                        renderPreview();
                        return;
                    }
                    previewSignature = currentSignature();
                    previewColumns = data.columns || [];
                    previewRecords = (data.records || []).map(record => ({ ...record, selected: true }));
                    renderPreview();
                    const priceNote = data.priced_via_matrix
                        ? '<span class="text-success">Priced via rate matrix</span>'
                        : (data.rate > 0
                            ? `Flat rate ${money(data.rate)} (code ${escapeHtml(data.rate_code || '-')})`
                            : `<span class="text-warning">No rate matrix or rate_code set — Price will be 0. Set up the matrix in Master Data &rarr; Rate Matrix.</span>`);
                    const fx = (data.currency || 'PHP') + (Number(data.forex) > 0 ? ' @ ' + money(data.forex) : '');
                    totalsEl.innerHTML = `${priceNote} &nbsp;|&nbsp; Currency: ${escapeHtml(fx)} &nbsp;|&nbsp; Total: <strong>${money(data.total)}</strong>`;
                    statusEl.className = 'small mt-2 text-success';
                    statusEl.textContent = `${data.count} row(s) ready for review.`;
                } catch (err) {
                    statusEl.className = 'small mt-2 text-danger';
                    statusEl.textContent = 'Preview failed: ' + err.message;
                } finally {
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
                statusEl.className = 'small mt-2 text-muted';
                statusEl.textContent = 'Generating, please wait...';

                const payload = new FormData();
                payload.append('customer', customer.value);
                payload.append('date_from', dateFrom.value);
                payload.append('date_to', dateTo.value);
                payload.append('include_billed', includeBilled.checked ? '1' : '0');
                payload.append('selected_entry_ids', selectedIds.join(','));
                payload.append('entry_order', previewRecords.map(record => record.entry_id).join(','));

                try {
                    const res = await fetch('php/insert/generate_box_banana_billing.php', { method: 'POST', body: payload });
                    const data = await res.json();
                    if (!data.success) {
                        statusEl.className = 'small mt-2 text-danger';
                        statusEl.textContent = data.message || 'Generation failed.';
                        return;
                    }
                    statusEl.className = 'small mt-2 text-success';
                    statusEl.textContent = `${data.message} Downloading "${data.invoice.file_name}"...`;
                    const link = document.createElement('a');
                    link.href = data.invoice.download_url;
                    link.download = data.invoice.file_name;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    loadStatements();
                } catch (err) {
                    statusEl.className = 'small mt-2 text-danger';
                    statusEl.textContent = 'Generation failed: ' + err.message;
                } finally {
                    previewBtn.disabled = false;
                    updateSelectionSummary();
                }
            }

            previewBtn.addEventListener('click', loadPreview);
            generateBtn.addEventListener('click', generate);
            selectAllButton.addEventListener('click', function () { setAllSelections(true); });
            clearAllButton.addEventListener('click', function () { setAllSelections(false); });
            [customer, dateFrom, dateTo, includeBilled].forEach(function (element) {
                element.addEventListener('change', markPreviewStale);
            });
            updateSelectionSummary();

            previewTableBody.addEventListener('change', function (event) {
                const checkbox = event.target.closest('.bb-row-check');
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

            document.getElementById('refreshStatements').addEventListener('click', loadStatements);

            document.querySelector('#statementsTable tbody').addEventListener('click', async function (e) {
                const chargesBtn = e.target.closest('.btn-view-charges');
                if (chargesBtn) {
                    openChargesModal(Number(chargesBtn.dataset.id));
                    return;
                }
                const btn = e.target.closest('.btn-del-statement');
                if (!btn) return;
                const confirm = await Swal.fire({
                    title: 'Delete this statement?',
                    text: 'The file will be removed and its trips will become billable again.',
                    icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#dc3545'
                });
                if (!confirm.isConfirmed) return;
                const fd = new FormData();
                fd.append('id', btn.dataset.id);
                try {
                    const res = await fetch('php/delete/box_banana.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Delete failed');
                    await loadStatements();
                    Swal.fire({ title: 'Deleted', text: data.message, icon: 'success' });
                } catch (err) {
                    Swal.fire({ title: 'Error', text: err.message, icon: 'error' });
                }
            });

            // ---- Billed Charges modal: view + edit the per-trip charges locked at
            // generation. Saving updates the DB value only (not the downloaded file). ----
            let chargesModalInstance = null;
            let chargesModalStatementId = 0;
            const chargesNumFmt = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            async function openChargesModal(statementId) {
                chargesModalStatementId = statementId;
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
                    const res = await fetch('php/fetch/get_box_banana_statement_charges.php?id=' + encodeURIComponent(statementId), { cache: 'no-store' });
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
                    `${inv.reference || ('Statement #' + inv.invoice_id)} · ${inv.customer_label || ''} · ${inv.date_from} to ${inv.date_to}`;
                const note = document.getElementById('chargesModalNote');
                if (!data.has_locked_charges) {
                    note.classList.remove('d-none');
                    note.textContent = 'This statement was generated before charges were locked, so no per-trip charge is recorded yet. Type each charge below to backfill it.';
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
                    </tr>`).join('') || '<tr><td colspan="6" class="text-center text-muted py-3">No entries on this statement.</td></tr>';
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
                fd.append('invoice_id', String(chargesModalStatementId));
                fd.append('entry_id', String(entryId));
                fd.append('rate_charge', value);
                const st = document.getElementById('chargesModalStatus');
                try {
                    const res = await fetch('php/update/update_box_banana_statement_charge.php', { method: 'POST', body: fd });
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
                fd.append('id', String(chargesModalStatementId));
                try {
                    const res = await fetch('php/update/regenerate_box_banana_file.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Rebuild failed');
                    st.className = 'small text-success me-auto';
                    st.textContent = data.message;
                    loadStatements();
                } catch (err) {
                    st.className = 'small text-danger me-auto';
                    st.textContent = err.message;
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = original;
                }
            });

            loadStatements();
        });
    </script>
</body>
</html>
