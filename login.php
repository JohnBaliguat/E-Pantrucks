<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
</head>
<body class="login-page">
    <div class="container-fluid">
        <div class="row min-vh-100">
            <div class="col-lg-6 d-none d-lg-flex login-left-panel">
                <div class="login-illustration">
                    <div class="illustration-content">
                       <img src="img/e-pantrucks logo2.png" alt="e-PANTRUCKS Logo" class="mb-4" style="max-width: 100%; height: auto;">
                        <p class="lead">Electronic Processing and Analytics for Truck Routing, Unified Cargo & Knowledge System</p>
                        <div class="feature-list mt-5">
                            <div class="feature-item">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>Fast & Efficient Data Entry</span>
                            </div>
                            <div class="feature-item">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>Real-time Analytics</span>
                            </div>
                            <div class="feature-item">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>Secure Data Management</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 d-flex align-items-center justify-content-center login-right-panel">
                <div class="login-form-container">
                    <div class="text-center mb-5">
                        <div class="logo-circle mb-3">
                            <i class="bi bi-database-fill-lock"></i>
                        </div>
                        <h2 class="fw-bold">Welcome Back</h2>
                        <p class="text-muted">Enter your credentials to access your account</p>
                    </div>

                    <?php
                    require_once "php/helpers/microsoft_auth.php";
                    $microsoftEnabled = microsoftAuthConfigured();
                    ?>

                    <form action="login-php.php" method="POST">
                        <?php if (isset($_GET['error'])) { ?>
                            <div class="alert alert-danger mb-3 py-3" role="alert">
                                <p class="mb-0 small"><?php echo htmlspecialchars($_GET['error']); ?></p>
                            </div>
                        <?php } ?>
                        <div class="mb-4">
                            <label for="uname" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="uname" name="uname" placeholder="Enter your username" required autocomplete="username" style="height: 50px;">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="pass" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="pass" name="pass" placeholder="Enter your password" required autocomplete="current-password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" name="login-btn" class="btn btn-primary w-100 py-3 mb-3">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                        </button>

                        <div class="login-divider">
                            <span>or</span>
                        </div>

                        <a
                            href="microsoft-login"
                            class="btn btn-microsoft w-100 py-3 mb-3 <?php echo $microsoftEnabled ? '' : 'enabled'; ?>"
                            <?php echo $microsoftEnabled ? '' : 'aria-disabled="false" tabindex="-1"'; ?>
                        >
                            <span class="microsoft-mark" aria-hidden="true">
                                <span></span><span></span><span></span><span></span>
                            </span>
                            <span>Sign in with Microsoft Work Account</span>
                        </a>

                        <?php if (!$microsoftEnabled) { ?>
                            <p class="small text-muted text-center mb-3">
                                Microsoft sign-in is unavailable until the Azure app credentials are configured.
                            </p>
                        <?php } ?>

                        <div class="text-center">
                            <small class="text-muted">Don't have an account? <a href="https://viber.click/09460456924" class="text-decoration-none" target="_blank">Contact Administrator</a></small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/jquery.min.js"></script>
</body>
</html>
