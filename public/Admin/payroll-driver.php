<?php include "php/session-check.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Payroll Detail - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
    <style>
        .payroll-detail-table th,
        .payroll-detail-table td {
            white-space: nowrap;
            text-align: right;
            vertical-align: middle;
        }

        .payroll-detail-table th:first-child,
        .payroll-detail-table td:first-child {
            text-align: left;
            position: sticky;
            left: 0;
            background: #fff;
            z-index: 2;
        }

        .payroll-detail-table thead th {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 3;
        }

        .payroll-detail-table tfoot td {
            font-weight: 700;
            background: #f8f9fa;
        }

        .payroll-highlight {
            color: #b45309;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include "sidebar.php"; ?>

        <div id="content">
            <?php include "navbar.php"; ?>

            <div class="main-content">
                <div class="content-header mb-4 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div>
                        <h2 class="fw-bold mb-1">Driver Payroll Detail</h2>
                        <p class="text-muted mb-0" id="detailMeta">Loading payroll detail...</p>
                    </div>
                    <div>
                        <a class="btn btn-outline-secondary" id="backToPayroll" href="payroll">
                            <i class="bi bi-arrow-left me-1"></i>Back to Payroll
                        </a>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-primary"><i class="bi bi-person-badge"></i></div>
                            <div><h3 id="driverName">-</h3><p>Driver</p></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-success"><i class="bi bi-credit-card-2-front"></i></div>
                            <div><h3 id="driverId">-</h3><p>Driver ID</p></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-warning"><i class="bi bi-cash-stack"></i></div>
                            <div><h3 id="grandTotal">0.00</h3><p>Total Earning</p></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Daily Driver Payroll</h5>
                        <small class="text-muted">RV and Dry Van split empty/loaded earnings when different drivers are assigned.</small>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm payroll-detail-table" id="detailTable">
                                <thead id="detailHead"></thead>
                                <tbody id="detailBody"></tbody>
                                <tfoot id="detailFoot"></tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        $(document).ready(function () {
            $("#paynav").attr({ "class" : "nav-link active" });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const params = new URLSearchParams(window.location.search);
            const driverName = params.get('driver_name') || '';
            const driverId = params.get('driver_id') || '';
            const dateFrom = params.get('date_from') || '';
            const dateTo = params.get('date_to') || '';

            const detailMeta = document.getElementById('detailMeta');
            const head = document.getElementById('detailHead');
            const body = document.getElementById('detailBody');
            const foot = document.getElementById('detailFoot');

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function formatAmount(value) {
                const amount = Number(value || 0);
                if (!amount) {
                    return '-';
                }
                return amount.toLocaleString(undefined, {
                    minimumFractionDigits: 1,
                    maximumFractionDigits: 2
                });
            }

            document.getElementById('driverName').textContent = driverName || '-';
            document.getElementById('driverId').textContent = driverId || '-';
            document.getElementById('backToPayroll').href = `payroll?date_from=${encodeURIComponent(dateFrom)}&date_to=${encodeURIComponent(dateTo)}`;

            async function loadDetail() {
                const query = new URLSearchParams({
                    driver_name: driverName,
                    driver_id: driverId,
                    date_from: dateFrom,
                    date_to: dateTo
                });

                const response = await fetch(`php/fetch/get_payroll_driver_detail.php?${query.toString()}`, {
                    cache: 'no-store'
                });
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to load payroll detail.');
                }

                detailMeta.textContent = `${data.driver_name} | ${data.date_from} to ${data.date_to}`;
                document.getElementById('grandTotal').textContent = Number(data.grand_total || 0).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                head.innerHTML = `
                    <tr>
                        <th>Date</th>
                        ${data.segments.map(segment => `<th>${escapeHtml(segment)}</th>`).join('')}
                        <th>Daily Earning</th>
                    </tr>
                `;

                body.innerHTML = data.rows.map(row => `
                    <tr>
                        <td>${escapeHtml(row.display_date)}</td>
                        ${data.segments.map(segment => `<td>${formatAmount(row.segments[segment] || 0)}</td>`).join('')}
                        <td class="payroll-highlight">${formatAmount(row.daily_total || 0)}</td>
                    </tr>
                `).join('');

                foot.innerHTML = `
                    <tr>
                        <td>SubTotal</td>
                        ${data.segments.map(segment => `<td>${formatAmount(data.subtotals[segment] || 0)}</td>`).join('')}
                        <td class="payroll-highlight">${formatAmount(data.grand_total || 0)}</td>
                    </tr>
                `;
            }

            loadDetail().catch(error => {
                detailMeta.textContent = error.message;
            });
        });
    </script>
</body>
</html>
