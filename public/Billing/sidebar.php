<?php
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            // Billing Admin has the same billing access as Billing, including Master Data.
            $canViewMasterData = true;
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
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard" id="dnav">
                                <i class="bi bi-speedometer2"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="customer-billing" id="cbnav">
                                <i class="bi bi-receipt"></i>
                                <span>Billing</span>
                            </a>
                        </li>
                        <?php if ($canViewMasterData): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="master-data" id="mdnav">
                                <i class="bi bi-database-gear"></i>
                                <span>Master Data</span>
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
