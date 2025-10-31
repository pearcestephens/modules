<?php
/**
 * Bank Transactions Module Layout Wrapper
 *
 * Wraps view content with the CIS dashboard layout
 */

// Get pageTitle from context, fallback to default
$pageTitle = $pageTitle ?? 'Bank Transactions';
$pageCSS = $pageCSS ?? [];
$pageJS = $pageJS ?? [];

// Add module CSS
if (!in_array('/modules/bank-transactions/assets/css/dashboard.css', $pageCSS)) {
    $pageCSS[] = '/modules/bank-transactions/assets/css/dashboard.css';
}

// Add module JS
if (!in_array('/modules/bank-transactions/assets/js/dashboard.js', $pageJS)) {
    $pageJS[] = '/modules/bank-transactions/assets/js/dashboard.js';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= htmlspecialchars($pageTitle) ?> - CIS</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">

    <!-- CIS Core CSS -->
    <link rel="stylesheet" href="/assets/css/cis-core.css">

    <!-- Font Awesome 6.7.1 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

    <!-- Page-Specific CSS -->
    <?php foreach ($pageCSS as $css): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
    <?php endforeach; ?>
</head>
<body class="layout-dashboard">
    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <aside class="dashboard-sidebar">
            <!-- Logo -->
            <div class="sidebar-logo">
                <a href="/"><i class="fas fa-cube"></i> CIS</a>
            </div>

            <!-- Navigation -->
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-title">Bank Transactions</div>
                    <ul class="nav-list">
                        <li><a href="/modules/bank-transactions/?route=dashboard"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                        <li><a href="/modules/bank-transactions/?route=transactions"><i class="fas fa-list"></i> Transactions</a></li>
                        <li><a href="/modules/bank-transactions/?route=matching"><i class="fas fa-link"></i> Matching</a></li>
                    </ul>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="dashboard-main">
            <!-- Header -->
            <header class="dashboard-header cis-header">
                <div class="header-left">
                    <button class="mobile-menu-toggle" id="mobileMenuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="header-search">
                        <input type="text" placeholder="Search..." class="form-control form-control-sm">
                    </div>
                </div>

                <div class="header-right">
                    <button id="notificationsBtn" class="btn btn-icon">
                        <i class="fas fa-bell"></i>
                        <span class="badge badge-danger badge-notification">3</span>
                    </button>

                    <div class="user-menu">
                        <button id="userMenuBtn" class="btn btn-icon">
                            <i class="fas fa-user-circle"></i>
                            <span class="user-name">User</span>
                        </button>
                        <div id="userMenu" class="dropdown-menu">
                            <a href="/modules/base/profile" class="dropdown-item">
                                <i class="fas fa-user"></i> Profile
                            </a>
                            <a href="/modules/base/settings" class="dropdown-item">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="/logout" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Breadcrumbs (optional) -->
            <?php if (!empty($breadcrumbs)): ?>
                <div class="breadcrumb-container">
                    <?php include __DIR__ . '/../../base/_templates/components/breadcrumbs.php'; ?>
                </div>
            <?php endif; ?>

            <!-- Page Content -->
            <main class="dashboard-content">
                <?= $content ?>
            </main>

            <!-- Footer -->
            <footer class="dashboard-footer">
                <p>&copy; 2025 Ecigdis Limited (The Vape Shed). All rights reserved.</p>
            </footer>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- CIS Core JS -->
    <script src="/assets/js/cis-core.js"></script>

    <!-- Page-Specific JS -->
    <?php foreach ($pageJS as $js): ?>
        <script src="<?= htmlspecialchars($js) ?>"></script>
    <?php endforeach; ?>

    <script>
        $(document).ready(function() {
            // User menu dropdown toggle
            $('#userMenuBtn').on('click', function(e) {
                e.stopPropagation();
                $('#userMenu').toggleClass('show');
            });

            // Close dropdown when clicking outside
            $(document).on('click', function() {
                $('#userMenu').removeClass('show');
            });

            // Prevent dropdown close when clicking inside
            $('#userMenu').on('click', function(e) {
                e.stopPropagation();
            });

            // Mobile menu toggle
            $('#mobileMenuToggle').on('click', function() {
                $('body').toggleClass('sidebar-open');
            });

            // Close sidebar when clicking outside on mobile
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.dashboard-sidebar, #mobileMenuToggle').length) {
                    $('body').removeClass('sidebar-open');
                }
            });
        });
    </script>
</body>
</html>
