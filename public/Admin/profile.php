<?php
include "php/session-check.php";

$profile = null;
$user_id = (int) ($_SESSION["user_id"] ?? 0);

if ($user_id > 0) {
    $stmt = $conn->prepare(
        'SELECT user_id, "user_idNumber", user_name, user_fname, user_lname, user_mname, user_email, user_type, user_image, "user_accountStat", user_code, user_address, user_city, user_country, user_bio, user_contact FROM "user" WHERE user_id = ?',
    );
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$fullName = trim(
    ($profile["user_fname"] ?? "") .
        " " .
        ($profile["user_mname"] ?? "") .
        " " .
        ($profile["user_lname"] ?? ""),
);
$fullName = preg_replace('/\s+/', ' ', $fullName);
$profileImage = !empty($profile["user_image"])
    ? $profile["user_image"]
    : "https://ui-avatars.com/api/?name=" .
        urlencode($fullName ?: ($_SESSION["user_name"] ?? "User")) .
        "&background=0D6EFD&color=fff&size=150";
$locationText = trim(
    ($profile["user_city"] ?? "") .
        (!empty($profile["user_city"]) && !empty($profile["user_country"]) ? ", " : "") .
        ($profile["user_country"] ?? ""),
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
</head>
<body>
    <div class="wrapper">
        <?php
        // Use the navigation that matches the logged-in role so each role sees
        // its own sidebar (e.g. Billing users get the Billing menu).
        $profileRole = strtolower((string) ($_SESSION["user_type"] ?? ""));
        $profileNavBase = $profileRole === "billing" ? __DIR__ . "/../Billing" : __DIR__;
        ?>
        <?php include $profileNavBase . "/sidebar.php"; ?>

        <div id="content">
            <?php include $profileNavBase . "/navbar.php"; ?>

            <div class="main-content">
                <div class="content-header mb-4">
                    <h2 class="fw-bold">My Profile</h2>
                    <p class="text-muted">Manage your account settings and preferences.</p>
                </div>

                <div class="row g-4">
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <div class="profile-avatar-large mb-3">
                                    <img id="profileImage" src="<?php echo htmlspecialchars(
                                        $profileImage,
                                    ); ?>" alt="Profile" class="rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                                    <button class="btn btn-sm btn-primary avatar-change-btn" data-bs-toggle="modal" data-bs-target="#uploadImageModal">
                                        <i class="bi bi-camera"></i>
                                    </button>
                                </div>
                                <h4 class="fw-bold mb-1" id="profileDisplayName"><?php echo htmlspecialchars(
                                    $fullName ?: "User",
                                ); ?></h4>
                                <p class="text-muted mb-3" id="profileDisplayRole"><?php echo htmlspecialchars(
                                    $profile["user_type"] ?? "-",
                                ); ?></p>
                                <div class="d-flex gap-2 justify-content-center mb-3">
                                    <span class="badge bg-<?php echo ($profile["user_accountStat"] ?? "") === "Active"
                                        ? "success"
                                        : "secondary"; ?>" id="profileDisplayStatus"><?php echo htmlspecialchars(
    $profile["user_accountStat"] ?? "Unknown",
); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header bg-white">
                                <h6 class="mb-0">Account Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="info-item">
                                    <i class="bi bi-envelope text-muted"></i>
                                    <div>
                                        <small class="text-muted d-block">Email</small>
                                        <span id="infoEmail"><?php echo htmlspecialchars(
                                            $profile["user_email"] ?? "-",
                                        ); ?></span>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <i class="bi bi-person-badge text-muted"></i>
                                    <div>
                                        <small class="text-muted d-block">ID Number</small>
                                        <span id="infoIdNumber"><?php echo htmlspecialchars(
                                            $profile["user_idNumber"] ?? "-",
                                        ); ?></span>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <i class="bi bi-phone text-muted"></i>
                                    <div>
                                        <small class="text-muted d-block">Contact</small>
                                        <span id="infoContact"><?php echo htmlspecialchars(
                                            $profile["user_contact"] ?? "-",
                                        ); ?></span>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <i class="bi bi-geo-alt text-muted"></i>
                                    <div>
                                        <small class="text-muted d-block">Location</small>
                                        <span id="infoLocation"><?php echo htmlspecialchars(
                                            $locationText ?: "-",
                                        ); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header bg-white">
                                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#personal">Personal Info</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#security">Security</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#preferences">Preferences</a>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content">
                                    <div class="tab-pane fade show active" id="personal">
                                        <form id="personalInfoForm">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="firstName" class="form-label">First Name</label>
                                                    <input type="text" class="form-control" name="firstName" id="firstName" value="<?php echo htmlspecialchars(
                                                        $profile["user_fname"] ?? "",
                                                    ); ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="lastName" class="form-label">Last Name</label>
                                                    <input type="text" class="form-control" name="lastName" id="lastName" value="<?php echo htmlspecialchars(
                                                        $profile["user_lname"] ?? "",
                                                    ); ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="middleName" class="form-label">Middle Name</label>
                                                    <input type="text" class="form-control" name="middleName" id="middleName" value="<?php echo htmlspecialchars(
                                                        $profile["user_mname"] ?? "",
                                                    ); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="username" class="form-label">Username</label>
                                                    <input type="text" class="form-control" name="username" id="username" value="<?php echo htmlspecialchars(
                                                        $profile["user_name"] ?? "",
                                                    ); ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="idNumber" class="form-label">ID Number</label>
                                                    <input type="text" class="form-control" name="idNumber" id="idNumber" value="<?php echo htmlspecialchars(
                                                        $profile["user_idNumber"] ?? "",
                                                    ); ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="email" class="form-label">Email</label>
                                                    <input type="email" class="form-control" name="email" id="email" value="<?php echo htmlspecialchars(
                                                        $profile["user_email"] ?? "",
                                                    ); ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="contact" class="form-label">Contact</label>
                                                    <input type="tel" class="form-control" name="contact" id="contact" value="<?php echo htmlspecialchars(
                                                        $profile["user_contact"] ?? "",
                                                    ); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="role" class="form-label">Role</label>
                                                    <input type="text" class="form-control" id="role" value="<?php echo htmlspecialchars(
                                                        $profile["user_type"] ?? "",
                                                    ); ?>" readonly>
                                                </div>
                                                <div class="col-12">
                                                    <label for="address" class="form-label">Address</label>
                                                    <input type="text" class="form-control" name="address" id="address" value="<?php echo htmlspecialchars(
                                                        $profile["user_address"] ?? "",
                                                    ); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="city" class="form-label">City</label>
                                                    <input type="text" class="form-control" name="city" id="city" value="<?php echo htmlspecialchars(
                                                        $profile["user_city"] ?? "",
                                                    ); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="country" class="form-label">Country</label>
                                                    <input type="text" class="form-control" name="country" id="country" value="<?php echo htmlspecialchars(
                                                        $profile["user_country"] ?? "",
                                                    ); ?>">
                                                </div>
                                                <div class="col-12">
                                                    <label for="bio" class="form-label">Bio</label>
                                                    <textarea class="form-control" name="bio" id="bio" rows="3"><?php echo htmlspecialchars(
                                                        $profile["user_bio"] ?? "",
                                                    ); ?></textarea>
                                                </div>
                                                <div class="col-12">
                                                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Save Changes</button>
                                                    <button type="reset" class="btn btn-secondary">Cancel</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="tab-pane fade" id="security">
                                        <h5 class="mb-4">Change Password</h5>
                                        <form id="passwordForm">
                                            <div class="mb-3">
                                                <label for="currentPassword" class="form-label">Current Password</label>
                                                <input type="password" class="form-control" name="currentPassword" id="currentPassword" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="newPassword" class="form-label">New Password</label>
                                                <input type="password" class="form-control" name="newPassword" id="newPassword" required>
                                                <div class="form-text">Password must be at least 8 characters long.</div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="confirmPassword" class="form-label">Confirm New Password</label>
                                                <input type="password" class="form-control" name="confirmPassword" id="confirmPassword" required>
                                            </div>
                                            <button type="submit" class="btn btn-primary"><i class="bi bi-shield-check me-2"></i>Update Password</button>
                                        </form>

                                        <hr class="my-4">

                                        <h5 class="mb-4">Two-Factor Authentication</h5>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1">Enable 2FA</h6>
                                                <p class="text-muted mb-0">Add an extra layer of security to your account</p>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="twoFactorAuth">
                                            </div>
                                        </div>

                                        <hr class="my-4">

                                        <h5 class="mb-4">Active Sessions</h5>
                                        <div class="session-item">
                                            <div class="d-flex align-items-start">
                                                <div class="session-icon">
                                                    <i class="bi bi-laptop"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">Windows PC - Chrome</h6>
                                                    <small class="text-muted">New York, USA - Current session</small>
                                                </div>
                                                <span class="badge bg-success">Active</span>
                                            </div>
                                        </div>
                                        <div class="session-item">
                                            <div class="d-flex align-items-start">
                                                <div class="session-icon">
                                                    <i class="bi bi-phone"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">iPhone 14 - Safari</h6>
                                                    <small class="text-muted">New York, USA - 2 hours ago</small>
                                                </div>
                                                <button class="btn btn-sm btn-outline-danger">Revoke</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="preferences">
                                        <form id="preferencesForm">
                                            <h5 class="mb-4">Notification Settings</h5>
                                            <div class="preference-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1">Email Notifications</h6>
                                                        <small class="text-muted">Receive email updates about your account activity</small>
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="emailNotif" id="emailNotif" checked>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="preference-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1">Push Notifications</h6>
                                                        <small class="text-muted">Get push notifications for important updates</small>
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="pushNotif" id="pushNotif" checked>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="preference-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1">Weekly Reports</h6>
                                                        <small class="text-muted">Receive weekly summary of your activities</small>
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="weeklyReport" id="weeklyReport">
                                                    </div>
                                                </div>
                                            </div>

                                            <hr class="my-4">

                                            <h5 class="mb-4">Display Settings</h5>
                                            <div class="mb-3">
                                                <label for="theme" class="form-label">Theme</label>
                                                <select class="form-select" name="theme" id="theme">
                                                    <option value="light" selected>Light</option>
                                                    <option value="dark">Dark</option>
                                                    <option value="auto">Auto</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="language" class="form-label">Language</label>
                                                <select class="form-select" name="language" id="language">
                                                    <option value="en" selected>English</option>
                                                    <option value="es">Spanish</option>
                                                    <option value="fr">French</option>
                                                    <option value="de">German</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="timezone" class="form-label">Timezone</label>
                                                <select class="form-select" name="timezone" id="timezone">
                                                    <option value="est" selected>Eastern Time (ET)</option>
                                                    <option value="pst">Pacific Time (PT)</option>
                                                    <option value="cst">Central Time (CT)</option>
                                                    <option value="mst">Mountain Time (MT)</option>
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Save Preferences</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Image Modal -->
    <div class="modal fade" id="uploadImageModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-image me-2"></i>Upload Profile Picture</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="imageUploadForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="profileImageInput" class="form-label">Select Image</label>
                            <input type="file" class="form-control" id="profileImageInput" name="profileImage" accept="image/*" required>
                            <small class="d-block mt-2 text-muted">
                                Allowed formats: JPG, PNG, GIF, WebP (Max 5MB)
                            </small>
                        </div>
                        <div id="imagePreview" class="text-center mb-3" style="display:none;">
                            <img id="previewImage" src="" alt="Preview" style="max-width: 200px; max-height: 200px;" class="rounded">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-2"></i>Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    <script src="assets/js/app.js"></script>

    <script>
        $(document).ready(function() {
            $("#pnav").attr({
					"class" : "nav-link active"
				});
            // Load profile data on page load
            loadProfileData();
            loadPreferences();

            // Load profile data from session
            function loadProfileData() {
                $.ajax({
                    type: 'GET',
                    url: 'php/update/profile.php',
                    data: { action: 'get-profile' },
                    dataType: 'json',
                    success: function(response) {
                        if(response.success) {
                            const user = response.user;
                            $('#firstName').val(user.user_fname);
                            $('#lastName').val(user.user_lname);
                            $('#middleName').val(user.user_mname || '');
                            $('#username').val(user.user_name || '');
                            $('#idNumber').val(user.user_idNumber || '');
                            $('#email').val(user.user_email);
                            $('#contact').val(user.user_contact || '');
                            $('#role').val(user.user_type);
                            $('#address').val(user.user_address || '');
                            $('#city').val(user.user_city || '');
                            $('#country').val(user.user_country || '');
                            $('#bio').val(user.user_bio || '');

                            const fullName = [user.user_fname, user.user_mname, user.user_lname].filter(Boolean).join(' ').replace(/\s+/g, ' ').trim();
                            $('#profileDisplayName').text(fullName || 'User');
                            $('#profileDisplayRole').text(user.user_type || '-');
                            $('#profileDisplayStatus').text(user.user_accountStat || 'Unknown')
                                .removeClass('bg-success bg-secondary')
                                .addClass(user.user_accountStat === 'Active' ? 'bg-success' : 'bg-secondary');
                            $('#infoEmail').text(user.user_email || '-');
                            $('#infoIdNumber').text(user.user_idNumber || '-');
                            $('#infoContact').text(user.user_contact || '-');
                            $('#infoLocation').text([user.user_city, user.user_country].filter(Boolean).join(', ') || '-');
                            
                            // Update profile image
                            let imgSrc = user.user_image
                                ? user.user_image
                                : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(fullName || user.user_name || 'User') + '&background=0D6EFD&color=fff&size=150';
                            $('#profileImage').attr('src', imgSrc);
                        }
                    }
                });
            }

            // Load user preferences
            function loadPreferences() {
                $.ajax({
                    type: 'GET',
                    url: 'php/update/preferences.php',
                    data: { action: 'get-preferences' },
                    dataType: 'json',
                    success: function(response) {
                        if(response.success) {
                            const prefs = response.preferences;
                            if(prefs.email_notifications) $('#emailNotif').prop('checked', true);
                            if(prefs.push_notifications) $('#pushNotif').prop('checked', true);
                            if(prefs.weekly_reports) $('#weeklyReport').prop('checked', true);
                            $('#theme').val(prefs.theme);
                            $('#language').val(prefs.language);
                            $('#timezone').val(prefs.timezone);
                        }
                    }
                });
            }

            // Image preview on file select
            $('#profileImageInput').on('change', function() {
                const file = this.files[0];
                
                if(file) {
                    // Check file size
                    if(file.size > 5 * 1024 * 1024) {
                        Swal.fire({
                            title: 'Error!',
                            text: 'File is too large. Maximum size is 5MB.',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                        $(this).val('');
                        $('#imagePreview').hide();
                        return;
                    }
                    
                    // Show preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#previewImage').attr('src', e.target.result);
                        $('#imagePreview').show();
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Handle Image Upload
            $('#imageUploadForm').on('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);

                $.ajax({
                    type: 'POST',
                    url: 'php/insert/image-upload.php',
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
                                // Update profile image
                                $('#profileImage').attr('src', response.image_path + '?' + new Date().getTime());
                                $('#imageUploadForm')[0].reset();
                                $('#imagePreview').hide();
                                
                                // Close modal
                                const modal = bootstrap.Modal.getInstance(document.getElementById('uploadImageModal'));
                                modal.hide();
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
                            text: 'An error occurred while uploading the image.',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                });
            });

            // Handle Personal Info Form Submit
            $('#personalInfoForm').on('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('action', 'update-personal');

                $.ajax({
                    type: 'POST',
                    url: 'php/update/profile.php',
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
                                loadProfileData();
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
                            text: 'An error occurred while updating profile.',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                });
            });

            // Handle Password Form Submit
            $('#passwordForm').on('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('action', 'change-password');

                $.ajax({
                    type: 'POST',
                    url: 'php/update/password.php',
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
                                $('#passwordForm')[0].reset();
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
                            text: 'An error occurred while changing password.',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                });
            });

            // Handle Preferences Form Submit
            $('#preferencesForm').on('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('action', 'update-preferences');

                $.ajax({
                    type: 'POST',
                    url: 'php/update/preferences.php',
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
                            text: 'An error occurred while saving preferences.',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                });
            });
        });
    </script>
