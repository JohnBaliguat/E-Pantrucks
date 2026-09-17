<?php
include "php/session-check.php";
$currentRole = ucfirst(strtolower((string) ($_SESSION["user_type"] ?? "")));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>For Update - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/DataTables/datatables.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <div class="wrapper">
        <?php if ($currentRole === "User") { include dirname(__DIR__) . "/User/sidebar.php"; } else { include __DIR__ . "/sidebar.php"; } ?>

        <div id="content">
            <?php include "navbar.php"; ?>

            <div class="main-content">
                <div class="content-header mb-4">
                    <h2 class="fw-bold">For Update</h2>
                    <p class="text-muted">Records the billing team flagged as needing information updated. Click <strong>Open &amp; Edit</strong> to jump to the entry and fix it, then mark it Resolved.</p>
                </div>

                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-flag me-2"></i>Records to Update <span class="badge bg-warning text-dark ms-1" id="openBadge">0</span></h5>
                        <div class="d-flex align-items-center gap-2">
                            <select class="form-select form-select-sm" id="statusFilter" style="width:auto;">
                                <option value="open" selected>Open</option>
                                <option value="resolved">Resolved</option>
                                <option value="all">All</option>
                            </select>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="refreshFlags"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="flagsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Entry</th>
                                        <th>Type</th>
                                        <th>Waybill</th>
                                        <th>Customer</th>
                                        <th>Van</th>
                                        <th>Remarks</th>
                                        <th>Flagged By</th>
                                        <th>Flagged</th>
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
    <script src="assets/DataTables/datatables.min.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        $(document).ready(function () { $("#funav").attr({ "class": "nav-link active" }); });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const statusFilter = document.getElementById('statusFilter');
            const openBadge = document.getElementById('openBadge');

            function escapeHtml(value) {
                return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
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

            const table = new DataTable('#flagsTable', {
                order: [[7, 'desc']],
                pageLength: 25,
                columnDefs: [{ orderable: false, targets: [9] }]
            });

            function statusBadge(s) {
                if (s === 'open') return '<span class="badge bg-warning text-dark">Open</span>';
                if (s === 'resolved') return '<span class="badge bg-success">Resolved</span>';
                return `<span class="badge bg-secondary">${escapeHtml(s)}</span>`;
            }

            function rowFor(item) {
                const openEdit = `<a href="${escapeHtml(item.edit_url)}" class="btn btn-outline-primary btn-sm" title="Open the entry and load this record"><i class="bi bi-box-arrow-up-right me-1"></i>Open &amp; Edit</a>`;
                const resolve = item.status === 'open'
                    ? `<button type="button" class="btn btn-outline-success btn-sm btn-resolve-flag" data-id="${item.flag_id}" title="Mark as resolved"><i class="bi bi-check2-circle"></i></button>`
                    : '';
                const actions = `<div class="d-flex justify-content-end gap-2">${openEdit}${resolve}</div>`;
                return [
                    `<strong>#${item.entry_id}</strong>`,
                    escapeHtml(item.entry_type || '-'),
                    escapeHtml(item.waybill || '-'),
                    escapeHtml(item.shipper || '-'),
                    escapeHtml(item.van || '-'),
                    escapeHtml(item.remarks || '-'),
                    escapeHtml(item.flagged_by || '-'),
                    formatStamp(item.flagged_at),
                    statusBadge(item.status),
                    actions
                ];
            }

            async function loadFlags() {
                try {
                    const res = await fetch('php/fetch/get_update_flags.php?status=' + encodeURIComponent(statusFilter.value), { cache: 'no-store' });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Load failed');
                    table.clear();
                    (data.flags || []).forEach(f => table.row.add(rowFor(f)));
                    table.draw();
                    openBadge.textContent = String(data.open_count || 0);
                } catch (err) { console.error(err); }
            }

            statusFilter.addEventListener('change', loadFlags);
            document.getElementById('refreshFlags').addEventListener('click', loadFlags);

            document.querySelector('#flagsTable tbody').addEventListener('click', async function (e) {
                const btn = e.target.closest('.btn-resolve-flag');
                if (!btn) return;
                const confirm = await Swal.fire({
                    title: 'Mark as resolved?',
                    text: 'Only do this once the record has been updated.',
                    icon: 'question', showCancelButton: true, confirmButtonText: 'Resolve', confirmButtonColor: '#198754'
                });
                if (!confirm.isConfirmed) return;
                const fd = new FormData();
                fd.append('flag_id', btn.dataset.id);
                try {
                    const res = await fetch('php/update/resolve_update_flag.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Resolve failed');
                    await loadFlags();
                    Swal.fire({ title: 'Resolved', text: data.message, icon: 'success', timer: 1300, showConfirmButton: false });
                } catch (err) {
                    Swal.fire({ title: 'Error', text: err.message, icon: 'error' });
                }
            });

            loadFlags();
        });
    </script>
</body>
</html>
