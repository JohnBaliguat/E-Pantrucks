<?php include "php/session-check.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Records - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/DataTables/datatables.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
    <style>
        #recordsTable .drag-handle { cursor: grab; color: #94a3b8; }
        #recordsTable .drag-handle:active { cursor: grabbing; }
        #recordsTable tbody tr.dragging { opacity: 0.45; }
        #recordsTable tbody tr.drag-over td { border-top: 2px solid #2563eb; }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include "sidebar.php"; ?>

        <div id="content">
            <?php include "navbar.php"; ?>

            <div class="main-content">
                <div class="content-header mb-4">
                    <h2 class="fw-bold">Records</h2>
                    <p class="text-muted">View saved records by entry type and export the filtered result to Excel.</p>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Record Filters</h5>
                    </div>
                    <div class="card-body">
                        <form id="recordsFilterForm" class="row g-3 align-items-end">
                            <div class="col-md-2">
                                <label for="dateFrom" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="dateFrom" required>
                            </div>
                            <div class="col-md-2">
                                <label for="dateTo" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="dateTo" required>
                            </div>
                            <div class="col-md-2">
                                <label for="transmittalDate" class="form-label">Transmittal Date</label>
                                <input type="date" class="form-control" id="transmittalDate" required>
                            </div>
                            <div class="col-md-2">
                                <label for="entryType" class="form-label">Entry Type</label>
                                <select class="form-select" id="entryType">
                                    <option value="ALL">All Entries</option>
                                    <option value="RV ENTRY">RV Entry</option>
                                    <option value="DRY VAN ENTRY">Dry Van Entry</option>
                                    <option value="OTHERS ENTRY">Others Entry</option>
                                    <option value="DPC_KDs & OPM ENTRY">DPC_KDs & OPM Entry</option>
                                    <option value="CARGO TRUCK ENTRY">Cargo Truck Entry</option>
                                </select>
                            </div>
                            <div class="col-md-2 position-relative">
                                <label for="customerFilter" class="form-label">Customer</label>
                                <input type="text" class="form-control" id="customerFilter" placeholder="All customers" autocomplete="off">
                                <ul id="customerSuggestions" class="list-group position-absolute w-100" style="z-index: 1050; display: none; max-height: 220px; overflow-y: auto;"></ul>
                            </div>
                            <div class="col-md-2">
                                <label for="createdByFilter" class="form-label">Created By</label>
                                <select class="form-select" id="createdByFilter">
                                    <option value="">All encoders</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-outline-primary" id="previewButton">
                                    <i class="bi bi-search me-1"></i>Preview
                                </button>
                                <button type="submit" class="btn btn-primary" id="generateBtn">
                                    <i class="bi bi-file-earmark-excel me-1"></i>Generate Transmittal
                                </button>
                                <a href="transmittals" class="btn btn-outline-secondary" title="View past transmittals">
                                    <i class="bi bi-archive me-1"></i>History
                                </a>
                                <a href="#" class="btn btn-outline-secondary" id="downloadTemplateBtn" title="Download CSV template for the selected entry type">
                                    <i class="bi bi-file-earmark-arrow-down me-1"></i>Template
                                </a>
                                <button type="button" class="btn btn-outline-success" id="importDataBtn" title="Import records from a CSV file">
                                    <i class="bi bi-upload me-1"></i>Import
                                </button>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="includeTransmittedCheck">
                                    <label class="form-check-label" for="includeTransmittedCheck">
                                        Include already-transmitted records (allow re-export)
                                    </label>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-primary"><i class="bi bi-archive"></i></div>
                            <div><h3 id="totalRecords">0</h3><p>Total Records</p></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-success"><i class="bi bi-tags"></i></div>
                            <div><h3 id="selectedType">All Entries</h3><p>Selected Entry</p></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-warning"><i class="bi bi-calendar-range"></i></div>
                            <div><h3 id="coveredDates">-</h3><p>Covered Dates</p></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Record List</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info py-2 px-3 small d-flex align-items-center mb-3" id="reorderHint">
                            <i class="bi bi-grip-vertical me-2"></i>
                            Drag the <strong class="mx-1">⠿</strong> handle to arrange records in the order you want them to appear in the generated transmittal.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="recordsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="width:36px;"></th>
                                        <th>ID</th>
                                        <th>Entry Type</th>
                                        <th>Customer / Location</th>
                                        <th>Trip Receipt No.</th>
                                        <th>Trip Receipt MTY</th>
                                        <th>Van</th>
                                        <th>Driver</th>
                                        <th>Driver 2</th>
                                        <th>Status</th>
                                        <th>Remarks</th>
                                        <th>Encoded By</th>
                                        <th>Created</th>
                                        <th>Updated</th>
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

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel"><i class="bi bi-upload me-2"></i>Import Records</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Upload an Excel file (.xlsx) formatted for <strong id="importEntryTypeLabel">—</strong>. Download the template first if you haven't already.</p>
                    <div class="mb-3">
                        <label for="importFileInput" class="form-label">Excel File (.xlsx)</label>
                        <input type="file" class="form-control" id="importFileInput" accept=".xlsx">
                    </div>
                    <div id="importResultAlert" class="d-none"></div>
                    <div id="importErrorList" class="d-none">
                        <p class="fw-semibold mb-1">Skipped rows:</p>
                        <ul id="importErrorItems" class="mb-0 small text-danger"></ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="importSubmitBtn">
                        <i class="bi bi-upload me-1"></i>Import
                    </button>
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
            $("#rnav").attr({ "class" : "nav-link active" });
        });
    </script>
    <script>
        (function () {
            const entryTypeInput     = document.getElementById('entryType');
            const downloadTemplateBtn = document.getElementById('downloadTemplateBtn');
            const importDataBtn      = document.getElementById('importDataBtn');
            const importModal        = new bootstrap.Modal(document.getElementById('importModal'));
            const importEntryLabel   = document.getElementById('importEntryTypeLabel');
            const importFileInput    = document.getElementById('importFileInput');
            const importSubmitBtn    = document.getElementById('importSubmitBtn');
            const importResultAlert  = document.getElementById('importResultAlert');
            const importErrorList    = document.getElementById('importErrorList');
            const importErrorItems   = document.getElementById('importErrorItems');

            function isSpecificType() {
                return entryTypeInput.value !== 'ALL';
            }

            function updateButtons() {
                const specific = isSpecificType();
                if (specific) {
                    const params = new URLSearchParams({ entry_type: entryTypeInput.value });
                    downloadTemplateBtn.href = 'php/fetch/download_import_template.php?' + params.toString();
                    downloadTemplateBtn.classList.remove('disabled');
                    importDataBtn.disabled = false;
                } else {
                    downloadTemplateBtn.href = '#';
                    downloadTemplateBtn.classList.add('disabled');
                    importDataBtn.disabled = true;
                }
            }

            entryTypeInput.addEventListener('change', updateButtons);
            updateButtons();

            // Prevent clicking disabled template link
            downloadTemplateBtn.addEventListener('click', function (e) {
                if (!isSpecificType()) e.preventDefault();
            });

            // Open import modal
            importDataBtn.addEventListener('click', function () {
                importEntryLabel.textContent = entryTypeInput.value;
                importFileInput.value = '';
                importResultAlert.className = 'd-none';
                importResultAlert.textContent = '';
                importErrorList.classList.add('d-none');
                importErrorItems.innerHTML = '';
                importSubmitBtn.disabled = false;
                importSubmitBtn.innerHTML = '<i class="bi bi-upload me-1"></i>Import';
                importModal.show();
            });

            // Submit import
            importSubmitBtn.addEventListener('click', async function () {
                if (!importFileInput.files.length) {
                    importResultAlert.className = 'alert alert-warning';
                    importResultAlert.textContent = 'Please select an Excel (.xlsx) file.';
                    return;
                }

                importSubmitBtn.disabled = true;
                importSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Importing…';
                importResultAlert.className = 'd-none';
                importErrorList.classList.add('d-none');
                importErrorItems.innerHTML = '';

                const fd = new FormData();
                fd.append('action', 'import-entries');
                fd.append('entry_type', entryTypeInput.value);
                fd.append('xlsx_file', importFileInput.files[0]);

                try {
                    const res = await fetch('php/insert/import_entries.php', { method: 'POST', body: fd });
                    const data = await res.json();

                    if (data.success) {
                        importResultAlert.className = 'alert alert-success';
                        importResultAlert.textContent = data.message;
                    } else {
                        importResultAlert.className = 'alert alert-danger';
                        importResultAlert.textContent = data.message || 'Import failed.';
                    }

                    if (data.errors && data.errors.length) {
                        importErrorList.classList.remove('d-none');
                        data.errors.forEach(function (e) {
                            const li = document.createElement('li');
                            li.textContent = e;
                            importErrorItems.appendChild(li);
                        });
                    }
                } catch (err) {
                    importResultAlert.className = 'alert alert-danger';
                    importResultAlert.textContent = 'Network error: ' + err.message;
                }

                importSubmitBtn.disabled = false;
                importSubmitBtn.innerHTML = '<i class="bi bi-upload me-1"></i>Import';
            });
        })();
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('recordsFilterForm');
            const dateFromInput = document.getElementById('dateFrom');
            const dateToInput = document.getElementById('dateTo');
            const transmittalDateInput = document.getElementById('transmittalDate');
            const entryTypeInput = document.getElementById('entryType');
            const customerFilterInput = document.getElementById('customerFilter');
            const customerSuggestions = document.getElementById('customerSuggestions');
            const createdByFilter = document.getElementById('createdByFilter');
            const previewButton = document.getElementById('previewButton');

            // Populate the Created-By dropdown.
            (async function loadEncoders() {
                try {
                    const res = await fetch('php/fetch/get_encoders.php', { cache: 'no-store' });
                    if (!res.ok) {
                        console.error('[encoders] HTTP', res.status, await res.text());
                        return;
                    }
                    const raw = await res.text();
                    let data;
                    try {
                        data = JSON.parse(raw);
                    } catch (parseErr) {
                        console.error('[encoders] Non-JSON response:', raw);
                        return;
                    }
                    console.log('[encoders] response', data);
                    if (!data.success) {
                        console.error('[encoders] success=false', data);
                        return;
                    }
                    (data.encoders || []).forEach(function (e) {
                        const opt = document.createElement('option');
                        opt.value = e.id;
                        opt.textContent = e.label;
                        createdByFilter.appendChild(opt);
                    });
                } catch (err) {
                    console.error('[encoders] fetch failed', err);
                }
            })();

            function showDropdown(list) { list.style.display = 'block'; }
            function hideDropdown(list) { list.style.display = 'none'; list.innerHTML = ''; }

            function attachCustomerKeyboardNav() {
                let activeIndex = 0;
                customerFilterInput.addEventListener('keydown', function (e) {
                    const items = customerSuggestions.querySelectorAll('li');
                    if (e.key === 'Escape') { hideDropdown(customerSuggestions); return; }
                    if (!items.length) return;
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        activeIndex = (activeIndex + 1) % items.length;
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        activeIndex = (activeIndex - 1 + items.length) % items.length;
                    } else if (e.key === 'Enter' || e.key === 'Tab') {
                        const active = items[activeIndex];
                        if (active) {
                            e.preventDefault();
                            customerFilterInput.value = active.dataset.pickValue;
                            hideDropdown(customerSuggestions);
                        }
                        return;
                    }
                    items.forEach(i => i.classList.remove('active-suggestion'));
                    if (items[activeIndex]) {
                        items[activeIndex].classList.add('active-suggestion');
                        items[activeIndex].scrollIntoView({ block: 'nearest' });
                    }
                });
                customerFilterInput.addEventListener('input', function () { activeIndex = 0; });
            }
            attachCustomerKeyboardNav();

            let customerDebounce;
            customerFilterInput.addEventListener('input', function () {
                clearTimeout(customerDebounce);
                const q = this.value.trim();
                if (!q) { hideDropdown(customerSuggestions); return; }
                customerDebounce = setTimeout(async function () {
                    try {
                        const res = await fetch('php/fetch/get_customers.php?q=' + encodeURIComponent(q), { cache: 'no-store' });
                        const data = await res.json();
                        const customers = data.customers || [];
                        customerSuggestions.innerHTML = '';
                        if (!customers.length) { hideDropdown(customerSuggestions); return; }
                        customers.forEach(function (c, index) {
                            const li = document.createElement('li');
                            li.className = 'list-group-item list-group-item-action' + (index === 0 ? ' active-suggestion' : '');
                            li.textContent = c;
                            li.dataset.pickValue = c;
                            li.addEventListener('mousedown', function (e) {
                                e.preventDefault();
                                customerFilterInput.value = c;
                                hideDropdown(customerSuggestions);
                            });
                            customerSuggestions.appendChild(li);
                        });
                        showDropdown(customerSuggestions);
                    } catch (_) {}
                }, 300);
            });

            document.addEventListener('mousedown', function (e) {
                if (!e.target.closest('#customerFilter') && !e.target.closest('#customerSuggestions')) {
                    hideDropdown(customerSuggestions);
                }
            });
            const totalRecords = document.getElementById('totalRecords');
            const selectedType = document.getElementById('selectedType');
            const coveredDates = document.getElementById('coveredDates');

            // Manual arrangement requires all rows in the DOM and no column-sort
            // fighting the user's drag order, so paging/ordering are disabled.
            const table = new DataTable('#recordsTable', {
                paging: false,
                ordering: false,
                info: true
            });

            // Backing list that holds records in the user-arranged order. This is
            // the source of truth for both rendering and the export order.
            let previewRecords = [];

            const today = new Date().toISOString().slice(0, 10);
            dateFromInput.value = today;
            dateToInput.value = today;
            transmittalDateInput.value = today;

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
                if (Number.isNaN(date.getTime())) {
                    return escapeHtml(value);
                }

                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const year = date.getFullYear();
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                const seconds = String(date.getSeconds()).padStart(2, '0');

                return `${month}/${day}/${year} ${hours}:${minutes}:${seconds}`;
            }

            function buildParams() {
                const params = new URLSearchParams({
                    date_from: dateFromInput.value,
                    date_to: dateToInput.value,
                    entry_type: entryTypeInput.value
                });
                const customer = customerFilterInput.value.trim();
                if (customer !== '') {
                    params.set('customer', customer);
                }
                const createdBy = createdByFilter.value;
                if (createdBy !== '') {
                    params.set('created_by', createdBy);
                }
                return params;
            }

            function renderMultiLine(values, emptyLabel = '-') {
                if (!Array.isArray(values) || !values.length) {
                    return `<span class="text-muted">${escapeHtml(emptyLabel)}</span>`;
                }

                return values.map(value => `<div>${escapeHtml(value)}</div>`).join('');
            }

            function updateSummary(records) {
                totalRecords.textContent = String(records.length);
                selectedType.textContent = entryTypeInput.value === 'ALL' ? 'All Entries' : entryTypeInput.value;
                coveredDates.textContent = `${dateFromInput.value} to ${dateToInput.value}`;
            }

            function renderPreview() {
                table.clear();

                previewRecords.forEach(record => {
                    table.row.add([
                        '<span class="drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></span>',
                        `<strong>#${record.entry_id}</strong>`,
                        escapeHtml(record.entry_type),
                        record.customer ? escapeHtml(record.customer) : '<span class="text-muted">-</span>',
                        record.waybill ? escapeHtml(record.waybill) : '<span class="text-muted">-</span>',
                        record.waybill_empty ? escapeHtml(record.waybill_empty) : '<span class="text-muted">-</span>',
                        record.van ? escapeHtml(record.van) : '<span class="text-muted">-</span>',
                        record.driver ? escapeHtml(record.driver) : '<span class="text-muted">-</span>',
                        record.driver2 ? escapeHtml(record.driver2) : '<span class="text-muted">-</span>',
                        record.status ? escapeHtml(record.status) : '<span class="text-muted">-</span>',
                        record.remarks ? escapeHtml(record.remarks) : '<span class="text-muted">-</span>',
                        record.created_by_name ? escapeHtml(record.created_by_name) : '<span class="text-muted">-</span>',
                        formatStamp(record.created_date),
                        formatStamp(record.modified_date || record.created_date)
                    ]);
                });

                table.draw();

                // With ordering disabled, row iteration order matches insertion
                // order, so we can tag each <tr> with its entry_id for drag logic.
                table.rows().every(function (rowIdx) {
                    const node = this.node();
                    const record = previewRecords[rowIdx];
                    if (!node || !record) return;
                    node.setAttribute('draggable', 'true');
                    node.dataset.entryId = String(record.entry_id);
                });

                updateSummary(previewRecords);
            }

            function reorderRecords(srcId, targetId) {
                srcId = Number(srcId);
                targetId = Number(targetId);
                if (!Number.isFinite(srcId) || !Number.isFinite(targetId) || srcId === targetId) {
                    return;
                }
                const from = previewRecords.findIndex(r => r.entry_id === srcId);
                const to = previewRecords.findIndex(r => r.entry_id === targetId);
                if (from < 0 || to < 0) return;
                const [moved] = previewRecords.splice(from, 1);
                previewRecords.splice(to, 0, moved);
                renderPreview();
            }

            // Native HTML5 drag-and-drop reordering (no external plugin needed).
            (function attachRowDragReorder() {
                const tbody = document.querySelector('#recordsTable tbody');
                if (!tbody) return;
                let dragSrcId = null;

                tbody.addEventListener('dragstart', function (e) {
                    const tr = e.target.closest('tr');
                    if (!tr || !tr.dataset.entryId) return;
                    dragSrcId = tr.dataset.entryId;
                    tr.classList.add('dragging');
                    if (e.dataTransfer) {
                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/plain', dragSrcId);
                    }
                });

                tbody.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
                    const tr = e.target.closest('tr');
                    tbody.querySelectorAll('tr.drag-over').forEach(r => r.classList.remove('drag-over'));
                    if (tr && tr.dataset.entryId) tr.classList.add('drag-over');
                });

                tbody.addEventListener('drop', function (e) {
                    e.preventDefault();
                    const tr = e.target.closest('tr');
                    tbody.querySelectorAll('tr.drag-over').forEach(r => r.classList.remove('drag-over'));
                    if (!tr || !tr.dataset.entryId || dragSrcId == null) return;
                    reorderRecords(dragSrcId, tr.dataset.entryId);
                    dragSrcId = null;
                });

                tbody.addEventListener('dragend', function () {
                    tbody.querySelectorAll('tr.dragging').forEach(r => r.classList.remove('dragging'));
                    tbody.querySelectorAll('tr.drag-over').forEach(r => r.classList.remove('drag-over'));
                    dragSrcId = null;
                });
            })();

            async function loadRecords() {
                const response = await fetch(`php/fetch/get_records.php?${buildParams().toString()}`, {
                    cache: 'no-store'
                });
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to load records.');
                }

                previewRecords = (data.records || []).slice();
                renderPreview();
            }

            previewButton.addEventListener('click', function () {
                loadRecords().catch(error => {
                    coveredDates.textContent = error.message;
                });
            });

            const includeTransmittedCheck = document.getElementById('includeTransmittedCheck');
            const generateBtn = document.getElementById('generateBtn');

            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                const fd = new FormData();
                fd.append('date_from', dateFromInput.value);
                fd.append('date_to', dateToInput.value);
                fd.append('transmittal_date', document.getElementById('transmittalDate').value);
                fd.append('entry_type', entryTypeInput.value);
                fd.append('customer', customerFilterInput.value.trim());
                fd.append('created_by', createdByFilter.value);
                fd.append('include_transmitted', includeTransmittedCheck.checked ? '1' : '0');
                // Send the user-arranged order so the export honors it.
                fd.append('entry_order', previewRecords.map(r => r.entry_id).join(','));

                const originalLabel = generateBtn.innerHTML;
                generateBtn.disabled = true;
                generateBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating…';

                try {
                    const res = await fetch('php/insert/generate_transmittal.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.success) {
                        await Swal.fire({ title: 'Generation failed', text: data.message, icon: 'warning' });
                        return;
                    }

                    const result = await Swal.fire({
                        title: 'Transmittal ready',
                        html: `<div class="text-start">
                            <div><strong>${data.transmittal.record_count}</strong> record${data.transmittal.record_count === 1 ? '' : 's'} included.</div>
                            <div class="text-muted small mt-1">${data.transmittal.file_name}</div>
                        </div>`,
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonText: 'Download now',
                        cancelButtonText: 'View history'
                    });

                    if (result.isConfirmed) {
                        window.location.href = 'php/fetch/download_transmittal.php?id=' + encodeURIComponent(data.transmittal.transmittal_id);
                    } else {
                        window.location.href = 'transmittals';
                    }
                } catch (err) {
                    await Swal.fire({ title: 'Error', text: 'Unable to generate transmittal. ' + err.message, icon: 'error' });
                } finally {
                    generateBtn.disabled = false;
                    generateBtn.innerHTML = originalLabel;
                }
            });

            loadRecords().catch(console.error);
        });
    </script>
</body>
</html>
