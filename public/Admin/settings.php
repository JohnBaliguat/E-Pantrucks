<?php include "php/session-check.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/DataTables/datatables.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <div class="wrapper">
        <?php include "sidebar.php"; ?>

        <div id="content">
            <?php include "navbar.php"; ?>

            <div class="main-content">
                <div class="content-header mb-4">
                    <h2 class="fw-bold">Settings</h2>
                    <p class="text-muted">Insert, update, and delete master data for units, trailers, locations, trip rates, and SKU.</p>
                </div>

                <div class="card mb-4">
                    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <h5 class="mb-1"><i class="bi bi-toggles me-2"></i>Billing Display</h5>
                            <div class="text-muted small">Hide the <strong>RAW billing (Excel)</strong> and <strong>Summary Billing (Excel)</strong> download buttons in Billing &rarr; Generated Invoices for all users.</div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span id="hideExcelStatus" class="small text-muted"></span>
                            <div class="form-check form-switch fs-4 mb-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="toggleHideInvoiceExcel">
                                <label class="form-check-label small" for="toggleHideInvoiceExcel">Hide Excel buttons</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-6 col-xl">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-primary"><i class="bi bi-geo-alt-fill"></i></div>
                            <div><h3 id="locationCount">0</h3><p>Locations</p></div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-success"><i class="bi bi-bezier2"></i></div>
                            <div><h3 id="trailerCount">0</h3><p>Trailers</p></div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-warning"><i class="bi bi-cash-stack"></i></div>
                            <div><h3 id="tripRatesCount">0</h3><p>Trip Rates</p></div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-danger"><i class="bi bi-truck"></i></div>
                            <div><h3 id="unitsCount">0</h3><p>Units</p></div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-info"><i class="bi bi-upc-scan"></i></div>
                            <div><h3 id="skuCount">0</h3><p>SKU</p></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#locationsPane" type="button">Locations</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#trailersPane" type="button">Trailers</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tripRatesPane" type="button">Trip Rates</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#unitsPane" type="button">Units</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#skuPane" type="button">SKU</button></li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="locationsPane">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Locations</h5>
                                    <button class="btn btn-primary btn-add-item" data-entity="location"><i class="bi bi-plus-circle me-1"></i>Add Location</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle" id="locationsTable">
                                        <thead class="table-light">
                                            <tr><th>ID</th><th>Location Name</th><th>Latitude</th><th>Longitude</th><th>Actions</th></tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="trailersPane">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Trailers</h5>
                                    <button class="btn btn-primary btn-add-item" data-entity="trailer"><i class="bi bi-plus-circle me-1"></i>Add Trailer</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle" id="trailersTable">
                                        <thead class="table-light">
                                            <tr><th>ID</th><th>Trailer Name</th><th>Actions</th></tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="tripRatesPane">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Trip Rates</h5>
                                    <button class="btn btn-primary btn-add-item" data-entity="trip_rates"><i class="bi bi-plus-circle me-1"></i>Add Trip Rate</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle" id="tripRatesTable">
                                        <thead class="table-light">
                                            <tr><th>ID</th><th>Segment</th><th>Activity</th><th>Base Rate</th><th>Additional</th><th>Total Rates</th><th>Actions</th></tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="unitsPane">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Units</h5>
                                    <button class="btn btn-primary btn-add-item" data-entity="units"><i class="bi bi-plus-circle me-1"></i>Add Unit</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle" id="unitsTable">
                                        <thead class="table-light">
                                            <tr><th>ID</th><th>Unit Name</th><th>Unit STD</th><th>Unit Model</th><th>Unit Cluster</th><th>Actions</th></tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="skuPane">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">SKU</h5>
                                    <button class="btn btn-primary btn-add-item" data-entity="sku"><i class="bi bi-plus-circle me-1"></i>Add SKU</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle" id="skuTable">
                                        <thead class="table-light">
                                            <tr><th>ID</th><th>SKU Name</th><th>Shipper Segment</th><th>Farm</th><th>Roundtrip Distance</th><th>Actions</th></tr>
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

    <div class="modal fade" id="itemModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="itemModalTitle">Add Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="itemForm">
                    <div class="modal-body">
                        <input type="hidden" id="entityInput" name="entity">
                        <input type="hidden" id="recordIdInput" name="id">
                        <div class="row g-3" id="formFields"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/DataTables/datatables.min.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        $(document).ready(function () {
            $("#snav").attr({ "class" : "nav-link active" });
        });
    </script>
    <script>
        // Billing Display toggle: hide the Excel download buttons in Generated Invoices.
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.getElementById('toggleHideInvoiceExcel');
            const statusEl = document.getElementById('hideExcelStatus');
            if (!toggle) return;

            function setStatus(msg, kind) {
                statusEl.className = 'small ' + (kind || 'text-muted');
                statusEl.textContent = msg || '';
            }

            fetch('php/fetch/get_app_settings.php', { cache: 'no-store' })
                .then(r => r.json())
                .then(d => { if (d && d.success) toggle.checked = !!d.hide_invoice_excel_buttons; })
                .catch(() => setStatus('Could not load setting.', 'text-danger'));

            toggle.addEventListener('change', async function () {
                setStatus('Saving…');
                toggle.disabled = true;
                try {
                    const fd = new FormData();
                    fd.append('hide_invoice_excel_buttons', toggle.checked ? '1' : '0');
                    const res = await fetch('php/insert/save_app_settings.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Save failed');
                    toggle.checked = !!data.hide_invoice_excel_buttons;
                    setStatus(toggle.checked ? 'Excel buttons hidden.' : 'Excel buttons shown.', 'text-success');
                } catch (e) {
                    toggle.checked = !toggle.checked; // revert on failure
                    setStatus(e.message, 'text-danger');
                } finally {
                    toggle.disabled = false;
                }
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const entityConfig = {
                location: {
                    label: 'Location',
                    idField: 'location_id',
                    table: new DataTable('#locationsTable'),
                    fields: [
                        { name: 'location_name', label: 'Location Name', required: true },
                        { name: 'latitude', label: 'Latitude' },
                        { name: 'longitude', label: 'Longitude' }
                    ]
                },
                trailer: {
                    label: 'Trailer',
                    idField: 'trailer_id',
                    table: new DataTable('#trailersTable'),
                    fields: [
                        { name: 'trailer_name', label: 'Trailer Name', required: true }
                    ]
                },
                trip_rates: {
                    label: 'Trip Rate',
                    idField: 'id',
                    table: new DataTable('#tripRatesTable'),
                    fields: [
                        { name: 'segment', label: 'Segment', required: true },
                        { name: 'activity', label: 'Activity', required: true },
                        { name: 'baseRate', label: 'Base Rate' },
                        { name: 'additional', label: 'Additional' },
                        { name: 'totalRates', label: 'Total Rates', readonly: true }
                    ]
                },
                units: {
                    label: 'Unit',
                    idField: 'unit_id',
                    table: new DataTable('#unitsTable'),
                    fields: [
                        { name: 'unit_name', label: 'Unit Name', required: true },
                        { name: 'unit_std', label: 'Unit STD' },
                        { name: 'unit_model', label: 'Unit Model' },
                        { name: 'unit_cluster', label: 'Unit Cluster' }
                    ]
                },
                sku: {
                    label: 'SKU',
                    idField: 'sku_id',
                    table: new DataTable('#skuTable'),
                    fields: [
                        { name: 'sku_name', label: 'SKU Name', required: true },
                        { name: 'sku_shipper_segment', label: 'SKU Shipper Segment' },
                        { name: 'sku_farm', label: 'SKU Farm' },
                        { name: 'sku_rountripDistance', label: 'Roundtrip Distance' }
                    ]
                }
            };

            const modal = new bootstrap.Modal(document.getElementById('itemModal'));
            const entityInput = document.getElementById('entityInput');
            const recordIdInput = document.getElementById('recordIdInput');
            const formFields = document.getElementById('formFields');
            const itemModalTitle = document.getElementById('itemModalTitle');
            const itemForm = document.getElementById('itemForm');

            function parseNumber(value) {
                const parsed = Number.parseFloat(String(value ?? '').trim());
                return Number.isFinite(parsed) ? parsed : 0;
            }

            function formatComputedNumber(value) {
                const rounded = Math.round(value * 100) / 100;
                return Number.isInteger(rounded) ? String(rounded) : String(rounded);
            }

            function updateTripRatesTotal() {
                if (entityInput.value !== 'trip_rates') {
                    return;
                }

                const baseRateInput = document.getElementById('baseRate');
                const additionalInput = document.getElementById('additional');
                const totalRatesInput = document.getElementById('totalRates');

                if (!baseRateInput || !additionalInput || !totalRatesInput) {
                    return;
                }

                totalRatesInput.value = formatComputedNumber(
                    parseNumber(baseRateInput.value) + parseNumber(additionalInput.value)
                );
            }

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function encodeRow(row) {
                return encodeURIComponent(JSON.stringify(row));
            }

            function actionButtons(entity, row) {
                return `
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary btn-edit-item" data-entity="${entity}" data-row="${encodeRow(row)}"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-outline-danger btn-delete-item" data-entity="${entity}" data-id="${row[entityConfig[entity].idField]}"><i class="bi bi-trash"></i></button>
                    </div>
                `;
            }

            function renderTable(entity, rows) {
                const config = entityConfig[entity];
                const tableRows = rows.map(row => {
                    if (entity === 'location') {
                        return [
                            `<strong>#${row.location_id}</strong>`,
                            escapeHtml(row.location_name || ''),
                            escapeHtml(row.latitude || '-'),
                            escapeHtml(row.longitude || '-'),
                            actionButtons(entity, row)
                        ];
                    }

                    if (entity === 'trailer') {
                        return [
                            `<strong>#${row.trailer_id}</strong>`,
                            escapeHtml(row.trailer_name || ''),
                            actionButtons(entity, row)
                        ];
                    }

                    if (entity === 'trip_rates') {
                        return [
                            `<strong>#${row.id}</strong>`,
                            escapeHtml(row.segment || ''),
                            escapeHtml(row.activity || ''),
                            escapeHtml(row.baseRate || '-'),
                            escapeHtml(row.additional || '-'),
                            escapeHtml(row.totalRates || '-'),
                            actionButtons(entity, row)
                        ];
                    }

                    if (entity === 'sku') {
                        return [
                            `<strong>#${row.sku_id}</strong>`,
                            escapeHtml(row.sku_name || ''),
                            escapeHtml(row.sku_shipper_segment || '-'),
                            escapeHtml(row.sku_farm || '-'),
                            escapeHtml(row.sku_rountripDistance || '-'),
                            actionButtons(entity, row)
                        ];
                    }

                    return [
                        `<strong>#${row.unit_id}</strong>`,
                        escapeHtml(row.unit_name || ''),
                        escapeHtml(row.unit_std || '-'),
                        escapeHtml(row.unit_model || '-'),
                        escapeHtml(row.unit_cluster || '-'),
                        actionButtons(entity, row)
                    ];
                });

                config.table.clear();
                config.table.rows.add(tableRows);
                config.table.draw();
            }

            function updateCounts(data) {
                document.getElementById('locationCount').textContent = String((data.location || []).length);
                document.getElementById('trailerCount').textContent = String((data.trailer || []).length);
                document.getElementById('tripRatesCount').textContent = String((data.trip_rates || []).length);
                document.getElementById('unitsCount').textContent = String((data.units || []).length);
                document.getElementById('skuCount').textContent = String((data.sku || []).length);
            }

            async function loadData() {
                const response = await fetch('php/fetch/get_settings_data.php', { cache: 'no-store' });
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to load settings data.');
                }

                Object.keys(entityConfig).forEach(entity => {
                    renderTable(entity, data.data[entity] || []);
                });
                updateCounts(data.data || {});
            }

            function openForm(entity, row = null) {
                const config = entityConfig[entity];
                entityInput.value = entity;
                recordIdInput.value = row ? String(row[config.idField]) : '';
                itemModalTitle.textContent = `${row ? 'Edit' : 'Add'} ${config.label}`;
                formFields.innerHTML = config.fields.map(field => `
                    <div class="col-md-6">
                        <label class="form-label" for="${field.name}">${escapeHtml(field.label)}</label>
                        <input type="text" class="form-control" id="${field.name}" name="${field.name}" value="${escapeHtml(row ? (row[field.name] || '') : '')}" ${field.required ? 'required' : ''} ${field.readonly ? 'readonly' : ''}>
                    </div>
                `).join('');
                updateTripRatesTotal();
                modal.show();
            }

            async function saveItem(event) {
                event.preventDefault();
                const isEdit = recordIdInput.value.trim() !== '';
                const endpoint = isEdit ? 'php/update/settings_item.php' : 'php/insert/settings_item.php';
                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: new FormData(itemForm)
                });
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Save failed.');
                }

                modal.hide();
                itemForm.reset();
                await loadData();
                await Swal.fire({
                    title: 'Success',
                    text: data.message,
                    icon: 'success',
                    confirmButtonColor: '#0d6efd'
                });
            }

            async function deleteItem(entity, id) {
                const confirmResult = await Swal.fire({
                    title: 'Delete item?',
                    text: 'This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    confirmButtonColor: '#dc3545'
                });

                if (!confirmResult.isConfirmed) {
                    return;
                }

                const payload = new FormData();
                payload.append('entity', entity);
                payload.append('id', String(id));

                const response = await fetch('php/delete/settings_item.php', {
                    method: 'POST',
                    body: payload
                });
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Delete failed.');
                }

                await loadData();
                await Swal.fire({
                    title: 'Deleted',
                    text: data.message,
                    icon: 'success',
                    confirmButtonColor: '#0d6efd'
                });
            }

            document.querySelectorAll('.btn-add-item').forEach(button => {
                button.addEventListener('click', function () {
                    openForm(button.dataset.entity);
                });
            });

            document.addEventListener('click', async function (event) {
                const editButton = event.target.closest('.btn-edit-item');
                const deleteButton = event.target.closest('.btn-delete-item');

                if (editButton) {
                    openForm(editButton.dataset.entity, JSON.parse(decodeURIComponent(editButton.dataset.row)));
                }

                if (deleteButton) {
                    try {
                        await deleteItem(deleteButton.dataset.entity, deleteButton.dataset.id);
                    } catch (error) {
                        Swal.fire({
                            title: 'Error',
                            text: error.message,
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                }
            });

            itemForm.addEventListener('submit', async function (event) {
                try {
                    updateTripRatesTotal();
                    await saveItem(event);
                } catch (error) {
                    Swal.fire({
                        title: 'Error',
                        text: error.message,
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                }
            });

            document.addEventListener('input', function (event) {
                if (event.target && (event.target.id === 'baseRate' || event.target.id === 'additional')) {
                    updateTripRatesTotal();
                }
            });

            loadData().catch(error => {
                Swal.fire({
                    title: 'Error',
                    text: error.message,
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            });
        });
    </script>
</body>
</html>
