<?php include "php/session-check.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/alert/node_modules/sweetalert2/dist/sweetalert2.min.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
</head>
<body>
    <div class="wrapper">
        <?php include "sidebar.php"; ?>

        <div id="content">
            <?php include "navbar.php"; ?>

            <div class="main-content">
                <div class="content-header mb-4">
                    <h2 class="fw-bold">Billing</h2>
                    <p class="text-muted">Select a date range and export billing rows with `billing_sku` to CSV.</p>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-calendar-range me-2"></i>Billing Export Range</h5>
                    </div>
                    <div class="card-body">
                        <form id="billingForm" class="row g-3 align-items-end" novalidate>
                            <div class="col-md-4">
                                <label for="dateFrom" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="dateFrom" required>
                            </div>
                            <div class="col-md-4">
                                <label for="dateTo" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="dateTo" required>
                            </div>
                            <div class="col-md-4 d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary" id="previewButton">
                                    <i class="bi bi-search me-1"></i>Preview
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-download me-1"></i>Generate CSV
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-primary"><i class="bi bi-file-earmark-spreadsheet"></i></div>
                            <div><h3 id="totalRows">0</h3><p>Billing Rows</p></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-success"><i class="bi bi-upc-scan"></i></div>
                            <div><h3 id="totalSkus">0</h3><p>Total Billing SKU</p></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-warning"><i class="bi bi-clock-history"></i></div>
                            <div><h3 id="coveredDates">-</h3><p>Covered Dates</p></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/alert/node_modules/sweetalert2/dist/sweetalert2.min.js"></script>
    <script>
        $(document).ready(function () {
            $("#bnav").attr({ "class" : "nav-link active" });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('billingForm');
            const previewButton = document.getElementById('previewButton');
            const dateFromInput = document.getElementById('dateFrom');
            const dateToInput = document.getElementById('dateTo');
            const totalRows = document.getElementById('totalRows');
            const totalSkus = document.getElementById('totalSkus');
            const coveredDates = document.getElementById('coveredDates');

            const now = new Date();
            const today = `${String(now.getMonth() + 1).padStart(2, '0')}/${String(now.getDate()).padStart(2, '0')}/${now.getFullYear()}`;
            dateFromInput.value = today;
            dateToInput.value = today;

            function validateDates() {
                if (!dateFromInput.value) {
                    Swal.fire('Missing field', 'Please select a Date From.', 'warning');
                    return false;
                }
                if (!dateToInput.value) {
                    Swal.fire('Missing field', 'Please select a Date To.', 'warning');
                    return false;
                }
                if (dateFromInput.value > dateToInput.value) {
                    Swal.fire('Invalid range', 'Date From must not be later than Date To.', 'warning');
                    return false;
                }
                return true;
            }

            function buildParams() {
                return new URLSearchParams({
                    date_from: dateFromInput.value,
                    date_to: dateToInput.value
                });
            }

            async function loadSummary() {
                const response = await fetch(`php/fetch/get_billing_summary.php?${buildParams().toString()}`, {
                    cache: 'no-store'
                });
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to load billing summary.');
                }

                totalRows.textContent = String(data.summary.total_rows ?? 0);
                totalSkus.textContent = String(data.summary.total_skus ?? 0);
                coveredDates.textContent = data.summary.first_date && data.summary.last_date
                    ? `${data.summary.first_date} to ${data.summary.last_date}`
                    : '-';
            }

            previewButton.addEventListener('click', function () {
                if (!validateDates()) return;
                loadSummary().catch(error => {
                    Swal.fire('Error', error.message, 'error');
                });
            });

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                if (!validateDates()) return;
                const url = `php/fetch/export_billing_csv.php?${buildParams().toString()}`;
                window.location.href = url;
            });

            loadSummary().catch(console.error);
        });
    </script>
</body>
</html>
