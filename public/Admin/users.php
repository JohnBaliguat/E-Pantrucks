<?php
include "php/session-check.php";
include "php/config/config.php";

// Fetch all users
$sql =
    'SELECT user_id, user_name, user_fname, user_lname, user_mname, user_email, user_pass, user_type, user_image, "user_accountStat", user_code, "user_idNumber" FROM "user"';
$result = $conn->query($sql);
$users = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $users[] = $row;
    }
}

// Count statistics
$totalUsers = count($users);
$activeUsers = 0;
$inactiveUsers = 0;

foreach ($users as $user) {
    if ($user["user_accountStat"] === "Active") {
        $activeUsers++;
    } else {
        $inactiveUsers++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - DataEncode System</title>
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
                    <h2 class="fw-bold">User Management</h2>
                    <p class="text-muted">Manage system users, roles, and permissions.</p>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-primary">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div>
                                <h3><?php echo $totalUsers; ?></h3>
                                <p>Total Users</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-success">
                                <i class="bi bi-person-check-fill"></i>
                            </div>
                            <div>
                                <h3><?php echo $activeUsers; ?></h3>
                                <p>Active Users</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-warning">
                                <i class="bi bi-person-plus-fill"></i>
                            </div>
                            <div>
                                <h3><?php echo count(
                                    array_filter($users, function ($u) {
                                        return strtotime(
                                            $u["user_code"] ?? "now",
                                        ) > strtotime("-7 days");
                                    }),
                                ); ?></h3>
                                <p>New This Week</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-danger">
                                <i class="bi bi-person-dash-fill"></i>
                            </div>
                            <div>
                                <h3><?php echo $inactiveUsers; ?></h3>
                                <p>Inactive Users</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-0">All Users</h5>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex gap-2 justify-content-md-end">
                                    <div class="input-group" style="max-width: 250px;" hidden>
                                        <input type="text" class="form-control" id="searchUsers" placeholder="Search users...">
                                        <button class="btn btn-outline-secondary" type="button">
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-funnel"></i> Filter
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" data-filter="all">All Users</a></li>
                                            <li><a class="dropdown-item" href="#" data-filter="active">Active</a></li>
                                            <li><a class="dropdown-item" href="#" data-filter="inactive">Inactive</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="#" data-filter="admin">Admin</a></li>
                                            <li><a class="dropdown-item" href="#" data-filter="subadmin">Sub Admin</a></li>
                                            <li><a class="dropdown-item" href="#" data-filter="manager">Manager</a></li>
                                            <li><a class="dropdown-item" href="#" data-filter="user">User</a></li>
                                            <li><a class="dropdown-item" href="#" data-filter="billing">Billing</a></li>
                                            <li><a class="dropdown-item" href="#" data-filter="billingadmin">Billing Admin</a></li>
                                        </ul>
                                    </div>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                        <i class="bi bi-person-plus"></i> Add User
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="usersTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>
                                            <input type="checkbox" class="form-check-input" id="selectAllUsers">
                                        </th>
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Last Active</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user) { ?>
                                    <tr data-user-id="<?php echo $user[
                                        "user_id"
                                    ]; ?>"
                                        data-user-name="<?php echo htmlspecialchars(
                                            $user["user_name"],
                                        ); ?>"
                                        data-user-fname="<?php echo htmlspecialchars(
                                            $user["user_fname"],
                                        ); ?>"
                                        data-user-lname="<?php echo htmlspecialchars(
                                            $user["user_lname"],
                                        ); ?>"
                                        data-user-mname="<?php echo htmlspecialchars(
                                            $user["user_mname"],
                                        ); ?>"
                                        data-user-email="<?php echo htmlspecialchars(
                                            $user["user_email"],
                                        ); ?>"
                                        data-user-type="<?php echo htmlspecialchars(
                                            $user["user_type"],
                                        ); ?>"
                                        data-user-status="<?php echo htmlspecialchars(
                                            $user["user_accountStat"],
                                        ); ?>"
                                        data-user-idnumber="<?php echo htmlspecialchars(
                                            $user["user_idNumber"] ?? "",
                                        ); ?>">
                                        <td><input type="checkbox" class="form-check-input"></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="https://ui-avatars.com/api/?name=<?php echo $user[
                                                    "user_fname"
                                                ] .
                                                    "+" .
                                                    $user[
                                                        "user_lname"
                                                    ]; ?>&background=0D6EFD&color=fff" alt="User" class="rounded-circle me-2" width="40" height="40">
                                                <div>
                                                    <strong><?php echo htmlspecialchars(
                                                        $user["user_fname"] .
                                                            " " .
                                                            $user["user_lname"],
                                                    ); ?></strong>
                                                    <br><small class="text-muted">Username: <?php echo htmlspecialchars(
                                                        $user["user_name"],
                                                    ); ?></small>
                                                    <br><small class="text-muted">ID No.: <?php echo htmlspecialchars(
                                                        $user["user_idNumber"] ?? "-",
                                                    ); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars(
                                            $user["user_email"],
                                        ); ?></td>
                                        <td><span class="badge bg-<?php echo $user[
                                            "user_type"
                                        ] === "Admin"
                                            ? "danger"
                                            : "info"; ?>"><?php echo htmlspecialchars(
    $user["user_type"],
); ?></span></td>
                                        <td>-</td>
                                        <td><span class="badge bg-<?php echo $user[
                                            "user_accountStat"
                                        ] === "Active"
                                            ? "success"
                                            : "secondary"; ?>"><?php echo htmlspecialchars(
    $user["user_accountStat"],
); ?></span></td>
                                        <td>-</td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary btn-edit-user" title="Edit" data-bs-toggle="modal" data-bs-target="#editUserModal"><i class="bi bi-pencil"></i></button>
                                                <?php if ($user['user_type'] === 'User'): ?>
                                                <button class="btn btn-outline-info btn-manage-access" title="Manage Access"><i class="bi bi-shield-lock"></i></button>
                                                <?php endif; ?>
                                                <button class="btn btn-outline-warning btn-change-password" title="Change Password"><i class="bi bi-key"></i></button>
                                                <button class="btn btn-outline-danger btn-delete-user" title="Delete"><i class="bi bi-trash"></i></button>
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

    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addUserForm">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="newFirstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" name="firstName" id="newFirstName" required>
                            </div>
                            <div class="col-md-6">
                                <label for="newLastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="lastName" id="newLastName" required>
                            </div>
                            <div class="col-md-6">
                                <label for="newMiddleName" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" name="middleName" id="newMiddleName">
                            </div>
                            <div class="col-md-6">
                                <label for="newUsername" class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" id="newUsername" required>
                            </div>
                            <div class="col-md-6">
                                <label for="newIdNumber" class="form-label">ID Number</label>
                                <input type="text" class="form-control" name="idNumber" id="newIdNumber" required>
                            </div>
                            <div class="col-md-6">
                                <label for="newEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="newEmail" required>
                            </div>
                            <div class="col-md-6">
                                <label for="newRole" class="form-label">Role</label>
                                <select class="form-select" name="role" id="newRole" required>
                                    <option value="">Select role</option>
                                    <option value="Admin">Administrator</option>
                                    <option value="SubAdmin">Sub Admin</option>
                                    <option value="Manager">Manager</option>
                                    <option value="User">User</option>
                                    <option value="Billing">Billing</option>
                                    <option value="BillingAdmin">Billing Admin</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="newPassword" class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" id="newPassword" required>
                            </div>
                            <div class="col-md-6">
                                <label for="newConfirmPassword" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" name="confirmPassword" id="newConfirmPassword" required>
                            </div>
                            <div class="col-md-6">
                                <label for="newStatus" class="form-label">Status</label>
                                <select class="form-select" name="status" id="newStatus" required>
                                    <option value="Active" selected>Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editUserForm">
                    <div class="modal-body">
                        <input type="hidden" name="userId" id="editUserId">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="editFirstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" name="firstName" id="editFirstName" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editLastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="lastName" id="editLastName" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editMiddleName" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" name="middleName" id="editMiddleName">
                            </div>
                            <div class="col-md-6">
                                <label for="editUsername" class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" id="editUsername" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editIdNumber" class="form-label">ID Number</label>
                                <input type="text" class="form-control" name="idNumber" id="editIdNumber" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="editEmail" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editRole" class="form-label">Role</label>
                                <select class="form-select" name="role" id="editRole" required>
                                    <option value="Admin">Administrator</option>
                                    <option value="SubAdmin">Sub Admin</option>
                                    <option value="Manager">Manager</option>
                                    <option value="User">User</option>
                                    <option value="Billing">Billing</option>
                                    <option value="BillingAdmin">Billing Admin</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="editStatus" class="form-label">Status</label>
                                <select class="form-select" name="status" id="editStatus" required>
                                    <option value="Active" selected>Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
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

    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="deleteUserForm">
                    <div class="modal-body">
                        <input type="hidden" name="userId" id="deleteUserId">
                        <p>Are you sure you want to delete this user? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="manageAccessModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-shield-lock me-2"></i>Manage User Access</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="manageAccessForm">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="accessUserId">
                        <p class="text-muted mb-3">Select the pages <strong id="accessUserName"></strong> can access:</p>
                        <div class="d-flex flex-column gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="pages[]" value="dashboard" id="accessDashboard">
                                <label class="form-check-label" for="accessDashboard">
                                    <i class="bi bi-speedometer2 me-1"></i> Dashboard
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="pages[]" value="entry" id="accessEntry">
                                <label class="form-check-label" for="accessEntry">
                                    <i class="bi bi-table me-1"></i> Data Entry
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="pages[]" value="monitoring" id="accessMonitoring">
                                <label class="form-check-label" for="accessMonitoring">
                                    <i class="bi bi-broadcast-pin me-1"></i> Monitoring
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="pages[]" value="records" id="accessRecords">
                                <label class="form-check-label" for="accessRecords">
                                    <i class="bi bi-journal-text me-1"></i> Records
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="pages[]" value="transmittals" id="accessTransmittals">
                                <label class="form-check-label" for="accessTransmittals">
                                    <i class="bi bi-archive me-1"></i> Transmittals
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="pages[]" value="for-update" id="accessForUpdate">
                                <label class="form-check-label" for="accessForUpdate">
                                    <i class="bi bi-flag me-1"></i> For Update
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Access</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-key me-2"></i>Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="changePasswordForm">
                    <div class="modal-body">
                        <input type="hidden" name="userId" id="changePasswordUserId">
                        <p class="text-muted mb-3">Setting a new password for <strong id="changePasswordUserName"></strong>.</p>
                        <div class="mb-3">
                            <label for="newPasswordInput" class="form-label">New Password</label>
                            <input type="password" class="form-control" name="newPassword" id="newPasswordInput" minlength="8" required>
                            <div class="form-text">Minimum 8 characters.</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirmPasswordInput" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" name="confirmPassword" id="confirmPasswordInput" minlength="8" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Password</button>
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
            $("#unav").attr({
					"class" : "nav-link active"
				});
         });
        let table = new DataTable('#usersTable');
        const addUserModal = new bootstrap.Modal(document.getElementById('addUserModal'));
        const editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
        const deleteUserModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
        const changePasswordModal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
        const manageAccessModal = new bootstrap.Modal(document.getElementById('manageAccessModal'));

        // Handle Add User Form
        $('#addUserForm').on('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'add-user');

            $.ajax({
                type: 'POST',
                url: 'php/insert/user.php',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonColor: '#0d6efd'
                        }).then((result) => {
                            if(result.isConfirmed) {
                                $('#addUserForm')[0].reset();
                                addUserModal.hide();
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: response.message,
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred while adding the user.',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                }
            });
        });

        // Handle Edit User Form
        $('#editUserForm').on('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'update-user');

            $.ajax({
                type: 'POST',
                url: 'php/update/user.php',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonColor: '#0d6efd'
                        }).then((result) => {
                            if(result.isConfirmed) {
                                editUserModal.hide();
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: response.message,
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred while updating the user.',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                }
            });
        });

        // Handle Delete User Form
        $('#deleteUserForm').on('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'delete-user');

            $.ajax({
                type: 'POST',
                url: 'php/delete/user.php',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        Swal.fire({
                            title: 'Deleted!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonColor: '#0d6efd'
                        }).then((result) => {
                            if(result.isConfirmed) {
                                deleteUserModal.hide();
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: response.message,
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred while deleting the user.',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                }
            });
        });

        // Edit button click handler
        $(document).on('click', '.btn-edit-user', function() {
            const row = $(this).closest('tr');
            const userId = row.data('user-id');
            const fname = row.data('user-fname') || '';
            const lname = row.data('user-lname') || '';
            const mname = row.data('user-mname') || '';
            const username = row.data('user-name') || '';
            const idNumber = row.data('user-idnumber') || '';
            const userEmail = row.data('user-email') || '';
            const userType = row.data('user-type') || '';
            const userStatus = row.data('user-status') || '';

            // Populate edit form
            $('#editUserId').val(userId);
            $('#editFirstName').val(fname);
            $('#editLastName').val(lname);
            $('#editMiddleName').val(mname);
            $('#editUsername').val(username);
            $('#editIdNumber').val(idNumber);
            $('#editEmail').val(userEmail);
            $('#editRole').val(userType);
            $('#editStatus').val(userStatus);
        });

        // Change password button click handler
        $(document).on('click', '.btn-change-password', function() {
            const row = $(this).closest('tr');
            const userId = row.data('user-id');
            const fname = row.data('user-fname') || '';
            const lname = row.data('user-lname') || '';

            $('#changePasswordUserId').val(userId);
            $('#changePasswordUserName').text(fname + ' ' + lname);
            $('#changePasswordForm')[0].reset();
            changePasswordModal.show();
        });

        // Handle Change Password Form
        $('#changePasswordForm').on('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'reset-user-password');

            $.ajax({
                type: 'POST',
                url: 'php/update/user.php',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonColor: '#0d6efd'
                        }).then(() => {
                            $('#changePasswordForm')[0].reset();
                            changePasswordModal.hide();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: response.message,
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred while updating the password.',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                }
            });
        });

        // Manage Access button click handler
        $(document).on('click', '.btn-manage-access', function() {
            const row = $(this).closest('tr');
            const userId = row.data('user-id');
            const fname = row.data('user-fname') || '';
            const lname = row.data('user-lname') || '';

            $('#accessUserId').val(userId);
            $('#accessUserName').text(fname + ' ' + lname);
            $('#manageAccessForm input[type="checkbox"]').prop('checked', false);

            $.ajax({
                type: 'GET',
                url: 'php/fetch/get_user_access.php',
                data: { user_id: userId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        if (response.pages.length === 0) {
                            $('#manageAccessForm input[type="checkbox"]').prop('checked', true);
                        } else {
                            response.pages.forEach(function(page) {
                                $('#manageAccessForm input[value="' + page + '"]').prop('checked', true);
                            });
                        }
                    }
                    manageAccessModal.show();
                },
                error: function() {
                    $('#manageAccessForm input[type="checkbox"]').prop('checked', true);
                    manageAccessModal.show();
                }
            });
        });

        // Handle Manage Access Form submit
        $('#manageAccessForm').on('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'update-access');

            $.ajax({
                type: 'POST',
                url: 'php/update/user_access.php',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonColor: '#0d6efd'
                        }).then(() => {
                            manageAccessModal.hide();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: response.message,
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred while updating access.',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                }
            });
        });

        // Delete button click handler
        $(document).on('click', '.btn-delete-user', function(e) {
            e.preventDefault();
            const row = $(this).closest('tr');
            const userId = row.data('user-id');

            $('#deleteUserId').val(userId);
            deleteUserModal.show();
        });
    </script>

</body>
</html>
