<?php include "php/session-check.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/DataTables/datatables.min.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="wrapper">
        <?php include "sidebar.php"; ?>

        <div id="content">
            <?php include "navbar.php"; ?>

            <div class="main-content">
                <div class="content-header mb-4 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div>
                        <h2 class="fw-bold mb-1">Dashboard</h2>
                        <p class="text-muted mb-0">Live operational summary from your actual entries.</p>
                    </div>
                    <div class="text-lg-end">
                        <small class="text-muted d-block">Last refresh</small>
                        <strong id="lastUpdated">Waiting for data...</strong>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card stat-card-blue">
                            <div class="stat-icon">
                                <i class="bi bi-file-earmark-text"></i>
                            </div>
                            <div class="stat-content">
                                <h3 id="todayEntriesCount">0</h3>
                                <p>Today's Entries</p>
                                <small class="stat-change text-muted">All entry types combined</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card stat-card-green">
                            <div class="stat-icon">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="stat-content">
                                <h3 id="todayCompleteCount">0</h3>
                                <p>Complete Today</p>
                                <small class="stat-change text-success">Based on required fields per entry type</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card stat-card-orange">
                            <div class="stat-icon">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div class="stat-content">
                                <h3 id="todayPendingCount">0</h3>
                                <p>Pending Today</p>
                                <small class="stat-change text-warning">Entries with incomplete data</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card stat-card-purple">
                            <div class="stat-icon">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="stat-content">
                                <h3 id="activeUsersCount">0</h3>
                                <p>Active Users</p>
                                <small class="stat-change text-success">Users with active accounts</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-xl-8">
                        <div class="card">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Operations Count by Created Date</h5>
                                <small class="text-muted">All available operation dates</small>
                            </div>
                            <div class="card-body">
                                <canvas id="activityChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Recent Activity</h5>
                            </div>
                            <div class="card-body recent-activity-body">
                                <div class="activity-list" id="recentActivityList">
                                    <div class="text-muted">Loading activity...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-xl col-lg-4 col-md-6">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-primary">
                                <i class="bi bi-snow"></i>
                            </div>
                            <div>
                                <h3 id="rvCount">0</h3>
                                <p>RV Today</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl col-lg-4 col-md-6">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-success">
                                <i class="bi bi-box-seam"></i>
                            </div>
                            <div>
                                <h3 id="othersCount">0</h3>
                                <p>Others Today</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl col-lg-4 col-md-6">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-warning">
                                <i class="bi bi-truck-flatbed"></i>
                            </div>
                            <div>
                                <h3 id="dpcCount">0</h3>
                                <p>DPC_KDI Today</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl col-lg-4 col-md-6">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-info">
                                <i class="bi bi-truck-front"></i>
                            </div>
                            <div>
                                <h3 id="dryVanCount">0</h3>
                                <p>Dry Van Today</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl col-lg-4 col-md-6">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-danger">
                                <i class="bi bi-truck"></i>
                            </div>
                            <div>
                                <h3 id="cargoCount">0</h3>
                                <p>Cargo Today</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Latest Entries</h5>
                                <a href="entry" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="latestEntriesTable">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Waybill</th>
                                                <th>Entry Type</th>
                                                <th>Activity</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Actions</th>
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="assets/DataTables/datatables.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <script>
        $(document).ready(function () {
            $("#dnav").attr({
                "class" : "nav-link active"
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const latestEntriesTable = new DataTable('#latestEntriesTable', {
                order: [[0, 'desc']],
                pageLength: 8
            });

            const chartContext = document.getElementById('activityChart');
            let activityChart = null;

            const typeStyles = {
                created: { icon: 'bi-plus-circle', bg: 'bg-success' },
                updated: { icon: 'bi-pencil', bg: 'bg-primary' }
            };

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function formatDateTime(value) {
                if (!value) {
                    return '-';
                }

                const date = new Date(value);
                if (Number.isNaN(date.getTime())) {
                    return escapeHtml(value);
                }

                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const year = date.getFullYear();
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                const seconds = String(date.getSeconds()).padStart(2, '0');

                return `${month}/${day}/${year} ${hours}:${minutes}:${seconds}`;
            }

            function updateStats(stats) {
                document.getElementById('todayEntriesCount').textContent = String(stats.today_entries ?? 0);
                document.getElementById('todayCompleteCount').textContent = String(stats.today_complete ?? 0);
                document.getElementById('todayPendingCount').textContent = String(stats.today_pending ?? 0);
                document.getElementById('activeUsersCount').textContent = String(stats.active_users ?? 0);
                document.getElementById('rvCount').textContent = String(stats.by_type?.rv ?? 0);
                document.getElementById('othersCount').textContent = String(stats.by_type?.others ?? 0);
                document.getElementById('dpcCount').textContent = String(stats.by_type?.dpc_kdi ?? 0);
                document.getElementById('dryVanCount').textContent = String(stats.by_type?.dry_van ?? 0);
                document.getElementById('cargoCount').textContent = String(stats.by_type?.cargo_truck ?? 0);
            }

            function updateChart(chartData) {
                if (activityChart) {
                    activityChart.destroy();
                }

                activityChart = new Chart(chartContext, {
                    type: 'line',
                    data: {
                        labels: chartData.labels || [],
                        datasets: [{
                            label: 'Operations',
                            data: chartData.values || [],
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13, 110, 253, 0.12)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.35,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            }

            function updateRecentActivity(items) {
                const container = document.getElementById('recentActivityList');

                if (!items.length) {
                    container.innerHTML = '<div class="text-muted">No recent activity found.</div>';
                    return;
                }

                container.innerHTML = items.map(item => {
                    const style = typeStyles[item.type] || { icon: 'bi-clock-history', bg: 'bg-secondary' };
                    const subtitleParts = [
                        item.entry_type || '',
                        item.waybill ? `WB: ${escapeHtml(item.waybill)}` : `#${item.entry_id}`,
                        item.actor ? `by ${escapeHtml(item.actor)}` : ''
                    ].filter(Boolean);

                    return `
                        <div class="activity-item">
                            <div class="activity-icon ${style.bg}">
                                <i class="bi ${style.icon}"></i>
                            </div>
                            <div class="activity-content">
                                <p class="mb-1"><strong>${escapeHtml(item.title)}</strong></p>
                                <small class="text-muted d-block">${subtitleParts.join(' | ')}</small>
                                <small class="text-muted">${formatDateTime(item.timestamp)}</small>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            function updateLatestEntries(rows) {
                latestEntriesTable.clear();

                rows.forEach(row => {
                    const badgeClass = row.status === 'Complete' ? 'bg-success' : 'bg-warning text-dark';
                    const waybill = row.waybill ? escapeHtml(row.waybill) : '<span class="text-muted">No waybill</span>';

                    latestEntriesTable.row.add([
                        `<strong>#${row.entry_id}</strong>`,
                        waybill,
                        `<span class="badge bg-primary">${escapeHtml(row.entry_type)}</span>`,
                        escapeHtml(row.activity || row.segment || '-'),
                        `<span class="badge ${badgeClass}">${escapeHtml(row.status)}</span>`,
                        formatDateTime(row.created_date),
                        `<a class="btn btn-sm btn-light" href="${escapeHtml(row.route)}"><i class="bi bi-eye"></i></a>`
                    ]);
                });

                latestEntriesTable.draw();
            }

            async function loadDashboard() {
                try {
                    const response = await fetch('php/fetch/get_dashboard_data.php', { cache: 'no-store' });
                    const data = await response.json();

                    if (!data.success) {
                        throw new Error(data.message || 'Failed to load dashboard data.');
                    }

                    updateStats(data.stats || {});
                    updateChart(data.chart || {});
                    updateRecentActivity(data.recent_activities || []);
                    updateLatestEntries(data.latest_entries || []);
                    document.getElementById('lastUpdated').textContent = formatDateTime(data.generated_at);
                } catch (error) {
                    document.getElementById('lastUpdated').textContent = 'Refresh failed';
                    console.error(error);
                }
            }

            loadDashboard();
            window.setInterval(loadDashboard, 30000);
        });
    </script>
</body>
</html>
