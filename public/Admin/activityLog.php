<?php include "php/session-check.php"; ?>
<?php
require_once __DIR__ . "/../../php/helpers/activity_log_view.php";

$filters = activity_log_filters_from_input($_GET);
[$where, $params] = activity_log_build_where($filters);

$sql = 'SELECT activity_id, user_name, user_id_number, user_type, activity_type, activity_label,
               request_method, route_name, ip_address, device_name, browser_name, os_name,
               client_device_name, context_summary, details_json, created_at
        FROM user_activity_log';
if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC, activity_id DESC LIMIT 500';

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$typeStmt = $conn->query('SELECT DISTINCT activity_type FROM user_activity_log ORDER BY activity_type ASC');
$activityTypes = $typeStmt->fetchAll(PDO::FETCH_COLUMN);
$userTypeStmt = $conn->query('SELECT DISTINCT user_type FROM user_activity_log WHERE COALESCE(user_type, \'\') <> \'\' ORDER BY user_type ASC');
$userTypes = $userTypeStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - DataEncode System</title>
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
                <div class="content-header mb-4">
                    <h2 class="fw-bold">Activity Log</h2>
                    <p class="text-muted">Recent user activity, request details, and device information.</p>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <form class="row g-3" method="get" action="activity-log">
                            <div class="col-md-2">
                                <label class="form-label" for="user">User</label>
                                <input class="form-control" type="text" id="user" name="user" value="<?php echo htmlspecialchars($filters["user"]); ?>" placeholder="Name or ID number">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="user_type">User Type</label>
                                <select class="form-select" id="user_type" name="user_type">
                                    <option value="">All User Types</option>
                                    <?php foreach ($userTypes as $userType): ?>
                                        <option value="<?php echo htmlspecialchars((string) $userType); ?>" <?php echo $filters["user_type"] === (string) $userType ? "selected" : ""; ?>>
                                            <?php echo htmlspecialchars((string) $userType); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="activity">Activity</label>
                                <select class="form-select" id="activity" name="activity">
                                    <option value="">All Activities</option>
                                    <?php foreach ($activityTypes as $activityType): ?>
                                        <option value="<?php echo htmlspecialchars((string) $activityType); ?>" <?php echo $filters["activity"] === (string) $activityType ? "selected" : ""; ?>>
                                            <?php echo htmlspecialchars(activity_log_humanize_text((string) $activityType)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="date_from">Date From</label>
                                <input class="form-control" type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filters["date_from"]); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="date_to">Date To</label>
                                <input class="form-control" type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filters["date_to"]); ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button class="btn btn-primary d-flex align-items-center justify-content-center" style="height: 100%;" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
                                <a class="btn btn-success d-flex align-items-center justify-content-center" style="height: 100%;" href="php/fetch/export_activity_log.php?<?php echo htmlspecialchars(http_build_query($filters)); ?>">
                                    <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
                                </a>
                                <a class="btn btn-outline-secondary d-flex align-items-center justify-content-center" style="height: 100%;" href="activity-log">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="activityLogTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Date/Time</th>
                                        <th>User</th>
                                        <th>Type</th>
                                        <th>Activity</th>
                                        <th>Method</th>
                                        <th>Route</th>
                                        <th>IP</th>
                                        <th>Device</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $row): ?>
                                        <tr>
                                            <td><strong>#<?php echo (int) $row["activity_id"]; ?></strong></td>
                                            <td><?php echo htmlspecialchars((string) $row["created_at"]); ?></td>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($row["user_name"] ?: "-")); ?></div>
                                                <div class="small text-muted">
                                                    <?php echo htmlspecialchars((string) ($row["user_id_number"] ?: "-")); ?>
                                                    <?php if (!empty($row["user_type"])): ?>
                                                        | <?php echo htmlspecialchars((string) $row["user_type"]); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars(activity_log_type_label($row)); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($row["activity_label"] ?: "-")); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($row["request_method"] ?: "-")); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($row["route_name"] ?: "-")); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($row["ip_address"] ?: "-")); ?></td>
                                            <td>
                                                <div class="fw-semibold">
                                                    <?php
                                                    $deviceLabel = (string) ($row["device_name"] ?: "-");
                                                    echo htmlspecialchars($deviceLabel);
                                                    ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <?php echo htmlspecialchars((string) ($row["browser_name"] ?: "-")); ?>
                                                    <?php if (!empty($row["os_name"])): ?>
                                                        | <?php echo htmlspecialchars((string) $row["os_name"]); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="small"><?php echo htmlspecialchars(activity_log_human_details($row)); ?></div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/DataTables/datatables.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        $(document).ready(function () {
            $("#alnav").attr({ "class" : "nav-link active" });
            new DataTable('#activityLogTable', {
                order: [[1, 'desc']]
            });
        });
    </script>
</body>
</html>
