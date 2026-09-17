<?php
include "php/session-check.php";
include "php/config/config.php";

$sql =
    'SELECT driver_id, driver_lname, driver_fname, driver_mname, "driver_IdNumber", "driver_dailyRate", "driver_hourlyRate" FROM drivers ORDER BY driver_lname ASC, driver_fname ASC';
$result = $conn->query($sql);
$drivers = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $drivers[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Management - DataEncode System</title>
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
                    <h2 class="fw-bold">Driver Management</h2>
                    <p class="text-muted">Register and manage driver information, ID numbers, and rates.</p>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-primary">
                                <i class="bi bi-person-vcard-fill"></i>
                            </div>
                            <div>
                                <h3><?php echo count($drivers); ?></h3>
                                <p>Total Drivers</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Registered Drivers</h5>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDriverModal">
                            <i class="bi bi-person-plus"></i> Add Driver
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="driversTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>ID Number</th>
                                        <th>Daily Rate</th>
                                        <th>Hourly Rate</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($drivers as $driver) { ?>
                                    <tr
                                        data-driver-id="<?php echo (int) $driver["driver_id"]; ?>"
                                        data-driver-lname="<?php echo htmlspecialchars($driver["driver_lname"]); ?>"
                                        data-driver-fname="<?php echo htmlspecialchars($driver["driver_fname"]); ?>"
                                        data-driver-mname="<?php echo htmlspecialchars($driver["driver_mname"]); ?>"
                                        data-driver-idnumber="<?php echo htmlspecialchars($driver["driver_IdNumber"]); ?>"
                                        data-driver-dailyrate="<?php echo htmlspecialchars($driver["driver_dailyRate"]); ?>"
                                        data-driver-hourlyrate="<?php echo htmlspecialchars($driver["driver_hourlyRate"]); ?>"
                                    >
                                        <td>
                                            <strong><?php echo htmlspecialchars(
                                                $driver["driver_lname"] .
                                                    ", " .
                                                    $driver["driver_fname"],
                                            ); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars(
                                                $driver["driver_mname"] ?: "-",
                                            ); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($driver["driver_IdNumber"]); ?></td>
                                        <td><?php echo number_format((float) ($driver["driver_dailyRate"] ?? 0), 2); ?></td>
                                        <td><?php echo number_format((float) ($driver["driver_hourlyRate"] ?? 0), 2); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary btn-edit-driver" title="Edit" data-bs-toggle="modal" data-bs-target="#editDriverModal">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-danger btn-delete-driver" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addDriverModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add Driver</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addDriverForm">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="addDriverLastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="addDriverLastName" name="lastName" required>
                            </div>
                            <div class="col-md-6">
                                <label for="addDriverFirstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="addDriverFirstName" name="firstName" required>
                            </div>
                            <div class="col-md-6">
                                <label for="addDriverMiddleName" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="addDriverMiddleName" name="middleName">
                            </div>
                            <div class="col-md-6">
                                <label for="addDriverIdNumber" class="form-label">ID Number</label>
                                <input type="text" class="form-control" id="addDriverIdNumber" name="idNumber" required>
                            </div>
                            <div class="col-md-6">
                                <label for="addDriverDailyRate" class="form-label">Daily Rate</label>
                                <input type="number" class="form-control" id="addDriverDailyRate" name="dailyRate" min="0" step="0.01" required>
                            </div>
                            <div class="col-md-6">
                                <label for="addDriverHourlyRate" class="form-label">Hourly Rate</label>
                                <input type="number" class="form-control" id="addDriverHourlyRate" name="hourlyRate" min="0" step="0.01" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Driver</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editDriverModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Driver</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editDriverForm">
                    <div class="modal-body">
                        <input type="hidden" id="editDriverId" name="driverId">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="editDriverLastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="editDriverLastName" name="lastName" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editDriverFirstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="editDriverFirstName" name="firstName" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editDriverMiddleName" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="editDriverMiddleName" name="middleName">
                            </div>
                            <div class="col-md-6">
                                <label for="editDriverIdNumber" class="form-label">ID Number</label>
                                <input type="text" class="form-control" id="editDriverIdNumber" name="idNumber" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editDriverDailyRate" class="form-label">Daily Rate</label>
                                <input type="number" class="form-control" id="editDriverDailyRate" name="dailyRate" min="0" step="0.01" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editDriverHourlyRate" class="form-label">Hourly Rate</label>
                                <input type="number" class="form-control" id="editDriverHourlyRate" name="hourlyRate" min="0" step="0.01" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/DataTables/datatables.min.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    <script>
        $(document).ready(function () {
            $("#drnav").attr({ "class": "nav-link active" });
            new DataTable('#driversTable');
        });

        const addDriverModal = new bootstrap.Modal(document.getElementById('addDriverModal'));
        const editDriverModal = new bootstrap.Modal(document.getElementById('editDriverModal'));

        $('#addDriverForm').on('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'add-driver');

            $.ajax({
                type: 'POST',
                url: 'php/insert/driver.php',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Success', response.message, 'success').then(() => {
                            $('#addDriverForm')[0].reset();
                            addDriverModal.hide();
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'An error occurred while adding the driver.', 'error');
                }
            });
        });

        $(document).on('click', '.btn-edit-driver', function() {
            const row = $(this).closest('tr');
            $('#editDriverId').val(row.data('driver-id'));
            $('#editDriverLastName').val(row.data('driver-lname'));
            $('#editDriverFirstName').val(row.data('driver-fname'));
            $('#editDriverMiddleName').val(row.data('driver-mname'));
            $('#editDriverIdNumber').val(row.data('driver-idnumber'));
            $('#editDriverDailyRate').val(row.data('driver-dailyrate'));
            $('#editDriverHourlyRate').val(row.data('driver-hourlyrate'));
        });

        $('#editDriverForm').on('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'update-driver');

            $.ajax({
                type: 'POST',
                url: 'php/update/driver.php',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Success', response.message, 'success').then(() => {
                            editDriverModal.hide();
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'An error occurred while updating the driver.', 'error');
                }
            });
        });

        $(document).on('click', '.btn-delete-driver', function() {
            const row = $(this).closest('tr');
            const driverId = row.data('driver-id');

            Swal.fire({
                title: 'Delete driver?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (!result.isConfirmed) return;

                $.ajax({
                    type: 'POST',
                    url: 'php/delete/driver.php',
                    data: {
                        action: 'delete-driver',
                        driverId: driverId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted', response.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'An error occurred while deleting the driver.', 'error');
                    }
                });
            });
        });
    </script>
</body>
</html>
