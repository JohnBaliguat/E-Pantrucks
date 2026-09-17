<?php include "php/session-check.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
</head>
<body>
    <div class="wrapper">
        <?php include "sidebar.php"; ?>

        <div id="content">
            <?php include "navbar.php"; ?>

            <div class="main-content">
                <div class="content-header mb-4">
                    <h2 class="fw-bold">User Dashboard</h2>
                    <p class="text-muted">Quick access for encoding, monitoring, and your account.</p>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <a href="entry" class="text-decoration-none">
                            <div class="stat-card stat-card-blue">
                                <div class="stat-icon"><i class="bi bi-table"></i></div>
                                <div class="stat-content">
                                    <h3>Data Entry</h3>
                                    <p>Create and update entries</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="monitoring" class="text-decoration-none">
                            <div class="stat-card stat-card-green">
                                <div class="stat-icon"><i class="bi bi-broadcast-pin"></i></div>
                                <div class="stat-content">
                                    <h3>Monitoring</h3>
                                    <p>Track incomplete entries</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="profile" class="text-decoration-none">
                            <div class="stat-card stat-card-orange">
                                <div class="stat-icon"><i class="bi bi-person"></i></div>
                                <div class="stat-content">
                                    <h3>Profile</h3>
                                    <p>Manage your account</p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Today's Entry Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="stat-card-simple">
                                    <div class="stat-icon-simple bg-primary"><i class="bi bi-file-earmark-text"></i></div>
                                    <div><h3 id="todayEntriesCount">0</h3><p>Today's Entries</p></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card-simple">
                                    <div class="stat-icon-simple bg-success"><i class="bi bi-check-circle"></i></div>
                                    <div><h3 id="todayCompleteCount">0</h3><p>Complete Today</p></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card-simple">
                                    <div class="stat-icon-simple bg-warning"><i class="bi bi-clock-history"></i></div>
                                    <div><h3 id="todayPendingCount">0</h3><p>Pending Today</p></div>
                                </div>
                            </div>
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
            $("#dnav").attr({ "class" : "nav-link active" });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', async function () {
            try {
                const response = await fetch('php/fetch/get_dashboard_data.php', { cache: 'no-store' });
                const data = await response.json();
                if (data.success) {
                    document.getElementById('todayEntriesCount').textContent = String(data.stats?.today_entries ?? 0);
                    document.getElementById('todayCompleteCount').textContent = String(data.stats?.today_complete ?? 0);
                    document.getElementById('todayPendingCount').textContent = String(data.stats?.today_pending ?? 0);
                }
            } catch (error) {
                console.error(error);
            }
        });
    </script>
</body>
</html>
