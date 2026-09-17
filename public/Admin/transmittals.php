<?php include "php/session-check.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transmittals - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/DataTables/datatables.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .status-badge { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.6rem; border-radius: 999px; font-size: 0.8rem; font-weight: 500; }
        .status-ready { background: #d1fae5; color: #065f46; }
        .status-expired { background: #fee2e2; color: #991b1b; }
        .status-missing { background: #fef3c7; color: #92400e; }
        .report-cell { display: flex; align-items: center; gap: 0.6rem; }
        .report-icon { color: #1d6f42; font-size: 1.25rem; }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include "sidebar.php"; ?>

        <div id="content">
            <?php include "navbar.php"; ?>

            <div class="main-content">
                <div class="content-header mb-4">
                    <h2 class="fw-bold">Transmittals</h2>
                    <p class="text-muted">Generated record transmittals you can download or remove. Files expire 6 months after generation.</p>
                </div>

                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-archive me-2"></i>Generated Transmittals</h5>
                        <a class="btn btn-outline-primary btn-sm" href="records">
                            <i class="bi bi-plus-circle me-1"></i>Generate New
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="transmittalsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Report</th>
                                        <th>Requested date</th>
                                        <th>Expiration date</th>
                                        <th>Size</th>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/DataTables/datatables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        $(document).ready(function () { $("#tnav").attr({ "class": "nav-link active" }); });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const table = new DataTable('#transmittalsTable', {
                order: [[1, 'desc']],
                pageLength: 25,
                columnDefs: [{ orderable: false, targets: [5] }]
            });

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }

            function formatStamp(value) {
                if (!value) return '-';
                const date = new Date(value);
                if (Number.isNaN(date.getTime())) return escapeHtml(value);
                const mm = String(date.getMonth() + 1).padStart(2, '0');
                const dd = String(date.getDate()).padStart(2, '0');
                const yy = String(date.getFullYear()).slice(-2);
                const hh = String(date.getHours()).padStart(2, '0');
                const mi = String(date.getMinutes()).padStart(2, '0');
                const ss = String(date.getSeconds()).padStart(2, '0');
                return `${mm}/${dd}/${yy} ${hh}:${mi}:${ss}`;
            }

            function formatBytes(bytes) {
                if (!bytes && bytes !== 0) return '-';
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + 'KB';
                return (bytes / (1024 * 1024)).toFixed(2) + 'MB';
            }

            function statusBadge(status) {
                if (status === 'ready') return '<span class="status-badge status-ready"><i class="bi bi-check-circle"></i>Ready</span>';
                if (status === 'expired') return '<span class="status-badge status-expired"><i class="bi bi-clock-history"></i>Expired</span>';
                if (status === 'missing') return '<span class="status-badge status-missing"><i class="bi bi-exclamation-triangle"></i>File missing</span>';
                return `<span class="status-badge">${escapeHtml(status)}</span>`;
            }

            function actionButtons(item) {
                const canDownload = item.status === 'ready';
                const downloadBtn = canDownload
                    ? `<a href="php/fetch/download_transmittal.php?id=${item.transmittal_id}" class="btn btn-outline-secondary btn-sm" title="Download"><i class="bi bi-download"></i></a>`
                    : `<button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Not available"><i class="bi bi-download"></i></button>`;
                return `
                    <div class="d-flex justify-content-end gap-2">
                        ${downloadBtn}
                        <button type="button" class="btn btn-outline-danger btn-sm btn-delete-transmittal" data-id="${item.transmittal_id}" title="Delete"><i class="bi bi-trash"></i></button>
                    </div>
                `;
            }

            function renderRow(item) {
                const recordSummary = item.record_count ? `${item.record_count} record${item.record_count === 1 ? '' : 's'}` : '';
                const reportCell = `
                    <div class="report-cell">
                        <i class="bi bi-file-earmark-excel-fill report-icon"></i>
                        <div>
                            <div class="fw-semibold">${escapeHtml(item.label)}</div>
                            <div class="text-muted small">${escapeHtml(item.file_name)}${recordSummary ? ' · ' + recordSummary : ''}</div>
                        </div>
                    </div>
                `;
                return [
                    reportCell,
                    `<i class="bi bi-download me-1 text-muted"></i>${formatStamp(item.requested_at)}`,
                    formatStamp(item.expires_at),
                    formatBytes(item.file_size_bytes),
                    statusBadge(item.status),
                    actionButtons(item)
                ];
            }

            async function loadTransmittals() {
                try {
                    const res = await fetch('php/fetch/get_transmittals.php', { cache: 'no-store' });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Load failed');
                    table.clear();
                    (data.transmittals || []).forEach(item => table.row.add(renderRow(item)));
                    table.draw();
                } catch (err) {
                    Swal.fire({ title: 'Error', text: err.message, icon: 'error' });
                }
            }

            document.querySelector('#transmittalsTable tbody').addEventListener('click', async function (e) {
                const btn = e.target.closest('.btn-delete-transmittal');
                if (!btn) return;
                const id = btn.dataset.id;
                const confirm = await Swal.fire({
                    title: 'Delete transmittal?',
                    text: 'The file and history of this transmittal will be removed.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    confirmButtonColor: '#dc3545'
                });
                if (!confirm.isConfirmed) return;

                const fd = new FormData();
                fd.append('id', id);
                try {
                    const res = await fetch('php/delete/transmittal.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Delete failed');
                    await loadTransmittals();
                    Swal.fire({ title: 'Deleted', text: data.message, icon: 'success' });
                } catch (err) {
                    Swal.fire({ title: 'Error', text: err.message, icon: 'error' });
                }
            });

            loadTransmittals();
        });
    </script>
</body>
</html>
