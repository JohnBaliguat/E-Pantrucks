<?php
$_sid_uid = intval($_SESSION['user_id'] ?? 0);
$_sid_pages = [];
$_sid_has_config = false;

$conn->exec("CREATE TABLE IF NOT EXISTS user_access (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    page_name VARCHAR(50) NOT NULL,
    CONSTRAINT unique_user_page UNIQUE (user_id, page_name)
)");

$_sid_stmt = $conn->prepare("SELECT page_name FROM user_access WHERE user_id = ?");
$_sid_stmt->execute([$_sid_uid]);
while ($_sid_row = $_sid_stmt->fetch(PDO::FETCH_ASSOC)) {
    $_sid_pages[] = $_sid_row['page_name'];
    $_sid_has_config = true;
}

function _can_access(string $page): bool {
    global $_sid_pages, $_sid_has_config;
    return !$_sid_has_config || in_array($page, $_sid_pages);
}
?>
<nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="bi bi-database-fill-gear"></i>
                    <span>e-PANTRUCKS</span>
                </div>
            </div>

            <div class="sidebar-menu">
                <div class="menu-section">
                    <small class="menu-title">MAIN</small>
                    <ul class="nav flex-column">
                        <?php if (_can_access('dashboard')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard" id="dnav">
                                <i class="bi bi-speedometer2"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (_can_access('entry')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="entry" id="enav">
                                <i class="bi bi-table"></i>
                                <span>Data Entry</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (_can_access('monitoring')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="monitoring" id="mnav">
                                <i class="bi bi-broadcast-pin"></i>
                                <span>Monitoring</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (_can_access('trip-receipt-status')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="trip-receipt-status" id="trsnav">
                                <i class="bi bi-clipboard-check"></i>
                                <span>Trip Receipt Status</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (_can_access('records')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="records" id="rnav">
                                <i class="bi bi-journal-text"></i>
                                <span>Records</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (_can_access('transmittals')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="transmittals" id="tnav">
                                <i class="bi bi-archive"></i>
                                <span>Transmittals</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (_can_access('for-update')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="for-update" id="funav">
                                <i class="bi bi-flag"></i>
                                <span>For Update</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="menu-section">
                    <small class="menu-title">ACCOUNT</small>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="profile" id="pnav">
                                <i class="bi bi-person"></i>
                                <span>Profile</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout">
                                <i class="bi bi-box-arrow-left"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
