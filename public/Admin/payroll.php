<?php include "php/session-check.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll - DataEncode System</title>
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
                <div class="content-header mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h2 class="fw-bold">Payroll</h2>
                        <p class="text-muted mb-0">Select a date range to view driver piece-rate totals.</p>
                    </div>
                    <a href="payroll-timesheet" class="btn btn-outline-primary">
                        <i class="bi bi-clipboard-data me-1"></i>Timesheet View
                    </a>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-calendar-range me-2"></i>Payroll Range</h5>
                    </div>
                    <div class="card-body">
                        <form id="payrollFilterForm" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="dateFrom" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="dateFrom" required>
                            </div>
                            <div class="col-md-4">
                                <label for="dateTo" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="dateTo" required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search me-1"></i>Load Payroll
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-primary"><i class="bi bi-people-fill"></i></div>
                            <div><h3 id="totalDrivers">0</h3><p>Total Drivers</p></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-success"><i class="bi bi-file-earmark-text"></i></div>
                            <div><h3 id="totalEntries">0</h3><p>Total Entries</p></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-warning"><i class="bi bi-cash-stack"></i></div>
                            <div><h3 id="grandTotal">0.00</h3><p>Grand Total Piece Rate</p></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Driver Payroll</h5>
                        <small class="text-muted">Based on `operations.created_date`</small>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="payrollTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Driver</th>
                                        <th>Driver ID</th>
                                        <th>Total Entries</th>
                                        <th>Total Piece Rate</th>
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
    <script src="assets/js/app.js"></script>
    <script>
        $(document).ready(function () {
            $("#paynav").attr({ "class" : "nav-link active" });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('payrollFilterForm');
            const dateFromInput = document.getElementById('dateFrom');
            const dateToInput = document.getElementById('dateTo');
            const totalDrivers = document.getElementById('totalDrivers');
            const totalEntries = document.getElementById('totalEntries');
            const grandTotal = document.getElementById('grandTotal');
            const initialParams = new URLSearchParams(window.location.search);
            const table = new DataTable('#payrollTable', {
                order: [[0, 'asc']]
            });

            const now = new Date();
            const today = `${String(now.getMonth() + 1).padStart(2, '0')}/${String(now.getDate()).padStart(2, '0')}/${now.getFullYear()}`;
            dateFromInput.value = initialParams.get('date_from') || today;
            dateToInput.value = initialParams.get('date_to') || today;

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function updateSummary(summary) {
                totalDrivers.textContent = String(summary.total_drivers ?? 0);
                totalEntries.textContent = String(summary.total_entries ?? 0);
                grandTotal.textContent = String(summary.grand_total_piece_rate ?? '0.00');
            }

            async function loadPayroll() {
                const dateFrom = dateFromInput.value;
                const dateTo = dateToInput.value;
                const params = new URLSearchParams({
                    date_from: dateFrom,
                    date_to: dateTo
                });

                const response = await fetch(`php/fetch/get_payroll_data.php?${params.toString()}`, {
                    cache: 'no-store'
                });
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to load payroll data.');
                }

                table.clear();
                (data.rows || []).forEach(row => {
                    const detailLink = `payroll-driver?driver_name=${encodeURIComponent(row.driver_name || '')}&driver_id=${encodeURIComponent(row.driver_id || '')}&date_from=${encodeURIComponent(dateFrom)}&date_to=${encodeURIComponent(dateTo)}`;
                    table.row.add([
                        `<a class="fw-semibold text-decoration-none" href="${detailLink}">${escapeHtml(row.driver_name)}</a>`,
                        escapeHtml(row.driver_id || '-'),
                        escapeHtml(row.total_entries),
                        `<span class="fw-semibold">${escapeHtml(row.total_piece_rate)}</span>`
                    ]);
                });
                table.draw();

                updateSummary(data.summary || {});
            }

            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                await loadPayroll();
            });

            loadPayroll().catch(console.error);
        });
    </script>
</body>
</html>
