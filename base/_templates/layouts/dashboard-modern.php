<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= $pageTitle ?? 'CIS - Central Information System' ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">

    <!-- Font Awesome 6.7.1 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

    <!-- Bootstrap 5.3.2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- DataTables 1.13.7 CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

    <!-- Select2 4.1.0 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <!-- Flatpickr 4.6.13 (Date picker) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- Toastr 2.1.4 (Notifications) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <!-- Additional Page CSS -->
    <?php if (!empty($pageCSS)): ?>
        <?php foreach ($pageCSS as $css): ?>
            <link rel="stylesheet" href="<?= $css ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <style>
        :root {
            --cis-sidebar-width: 180px;
            --cis-sidebar-collapsed-width: 60px;
            --cis-header-height: 56px;
            --cis-sidebar-bg: #1a1d29;
            --cis-sidebar-hover: #252939;
            --cis-header-bg: #ffffff;
            --cis-primary: #007bff;
            --cis-border: #e9ecef;
            --cis-text-dark: #2c3e50;
            --cis-text-light: #6c757d;
            --cis-bg-light: #f8f9fa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: var(--cis-bg-light);
            overflow-x: hidden;
        }

        /* ============================================================================
           MODERN HEADER - Fixed Top, Clean Design
           ============================================================================ */

        .cis-header {
            position: fixed;
            top: 0;
            left: var(--cis-sidebar-width);
            right: 0;
            height: var(--cis-header-height);
            background: var(--cis-header-bg);
            border-bottom: 1px solid var(--cis-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            z-index: 1000;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        body.sidebar-collapsed .cis-header {
            left: var(--cis-sidebar-collapsed-width);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-toggle-btn {
            width: 36px;
            height: 36px;
            border: none;
            background: transparent;
            color: var(--cis-text-dark);
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .header-toggle-btn:hover {
            background: var(--cis-bg-light);
        }

        .header-breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 14px;
            color: var(--cis-text-light);
        }

        .header-breadcrumb a {
            color: var(--cis-text-light);
            text-decoration: none;
            transition: color 0.2s;
        }

        .header-breadcrumb a:hover {
            color: var(--cis-primary);
        }

        .header-breadcrumb .active {
            color: var(--cis-text-dark);
            font-weight: 500;
        }

        .header-search {
            flex: 1;
            max-width: 500px;
            margin: 0 2rem;
        }

        .header-search input {
            width: 100%;
            height: 36px;
            border: 1px solid var(--cis-border);
            border-radius: 8px;
            padding: 0 1rem 0 2.5rem;
            font-size: 14px;
            background: var(--cis-bg-light);
            transition: all 0.2s;
        }

        .header-search input:focus {
            outline: none;
            border-color: var(--cis-primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }

        .header-search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--cis-text-light);
            font-size: 14px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-icon-btn {
            width: 36px;
            height: 36px;
            border: none;
            background: transparent;
            color: var(--cis-text-dark);
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: all 0.2s;
        }

        .header-icon-btn:hover {
            background: var(--cis-bg-light);
        }

        .header-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            min-width: 16px;
            height: 16px;
            background: #dc3545;
            color: white;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .header-user:hover {
            background: var(--cis-bg-light);
        }

        .header-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
        }

        .header-user-info {
            display: flex;
            flex-direction: column;
        }

        .header-user-name {
            font-size: 13px;
            font-weight: 500;
            color: var(--cis-text-dark);
            line-height: 1.2;
        }

        .header-user-role {
            font-size: 11px;
            color: var(--cis-text-light);
            line-height: 1.2;
        }

        /* ============================================================================
           MODERN SIDEBAR - Thin, Clean, Icon-First Design
           ============================================================================ */

        .cis-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--cis-sidebar-width);
            height: 100vh;
            background: var(--cis-sidebar-bg);
            z-index: 1001;
            display: flex;
            flex-direction: column;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        body.sidebar-collapsed .cis-sidebar {
            width: var(--cis-sidebar-collapsed-width);
        }

        .sidebar-brand {
            height: var(--cis-header-height);
            display: flex;
            align-items: center;
            padding: 0 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            text-decoration: none;
            color: white;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        .sidebar-brand-icon {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: white;
            flex-shrink: 0;
        }

        .sidebar-brand-text {
            font-size: 16px;
            font-weight: 700;
            white-space: nowrap;
            opacity: 1;
            transition: opacity 0.3s;
        }

        body.sidebar-collapsed .sidebar-brand-text {
            opacity: 0;
        }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 0.75rem 0;
        }

        .sidebar-nav::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.1);
            border-radius: 2px;
        }

        .nav-section-title {
            padding: 1rem 1.25rem 0.5rem;
            font-size: 11px;
            font-weight: 600;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
            transition: opacity 0.3s;
        }

        body.sidebar-collapsed .nav-section-title {
            opacity: 0;
        }

        .nav-item {
            margin: 2px 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.625rem 0.75rem;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.2s;
            gap: 0.75rem;
            position: relative;
        }

        .nav-link:hover {
            background: var(--cis-sidebar-hover);
            color: white;
        }

        .nav-link.active {
            background: var(--cis-primary);
            color: white;
        }

        .nav-link-icon {
            width: 20px;
            font-size: 16px;
            text-align: center;
            flex-shrink: 0;
        }

        .nav-link-text {
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
            flex: 1;
            opacity: 1;
            transition: opacity 0.3s;
        }

        body.sidebar-collapsed .nav-link-text {
            opacity: 0;
        }

        .nav-link-arrow {
            font-size: 10px;
            transition: transform 0.2s, opacity 0.3s;
            flex-shrink: 0;
        }

        body.sidebar-collapsed .nav-link-arrow {
            opacity: 0;
        }

        .nav-item.open .nav-link-arrow {
            transform: rotate(180deg);
        }

        .nav-submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .nav-item.open .nav-submenu {
            max-height: 500px;
        }

        body.sidebar-collapsed .nav-submenu {
            display: none;
        }

        .nav-submenu a {
            display: flex;
            align-items: center;
            padding: 0.5rem 0.75rem 0.5rem 2.75rem;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 12px;
            border-radius: 6px;
            margin: 2px 0.5rem;
            transition: all 0.2s;
        }

        .nav-submenu a:hover {
            background: var(--cis-sidebar-hover);
            color: white;
        }

        .nav-submenu a.active {
            color: var(--cis-primary);
        }

        .sidebar-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        .sidebar-footer-text {
            font-size: 11px;
            color: rgba(255,255,255,0.4);
            white-space: nowrap;
            transition: opacity 0.3s;
        }

        body.sidebar-collapsed .sidebar-footer-text {
            opacity: 0;
        }

        /* ============================================================================
           MAIN CONTENT AREA
           ============================================================================ */

        .cis-main {
            margin-left: var(--cis-sidebar-width);
            margin-top: var(--cis-header-height);
            min-height: calc(100vh - var(--cis-header-height));
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body.sidebar-collapsed .cis-main {
            margin-left: var(--cis-sidebar-collapsed-width);
        }

        .cis-content {
            padding: 1.5rem;
        }

        .cis-footer {
            padding: 1.5rem;
            text-align: center;
            color: var(--cis-text-light);
            font-size: 13px;
            border-top: 1px solid var(--cis-border);
            background: white;
        }

        /* ============================================================================
           MOBILE RESPONSIVE
           ============================================================================ */

        @media (max-width: 768px) {
            .cis-sidebar {
                transform: translateX(-100%);
            }

            body.sidebar-open .cis-sidebar {
                transform: translateX(0);
            }

            .cis-header {
                left: 0 !important;
            }

            .cis-main {
                margin-left: 0 !important;
            }

            .header-search {
                display: none;
            }

            .header-user-info {
                display: none;
            }

            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 1000;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.3s;
            }

            body.sidebar-open .sidebar-overlay {
                opacity: 1;
                pointer-events: auto;
            }
        }

        /* ============================================================================
           TOOLTIP FOR COLLAPSED SIDEBAR
           ============================================================================ */

        .nav-tooltip {
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            margin-left: 0.5rem;
            padding: 0.375rem 0.75rem;
            background: var(--cis-text-dark);
            color: white;
            font-size: 12px;
            border-radius: 6px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s;
            z-index: 2000;
        }

        body.sidebar-collapsed .nav-link:hover .nav-tooltip {
            opacity: 1;
        }
    </style>

    <!-- Inline Styles -->
    <?php if (!empty($inlineStyles)): ?>
        <style><?= $inlineStyles ?></style>
    <?php endif; ?>
</head>
<body>

    <!-- Sidebar -->
    <aside class="cis-sidebar">
        <a href="/" class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <i class="fas fa-cube"></i>
            </div>
            <span class="sidebar-brand-text">CIS Portal</span>
        </a>

        <nav class="sidebar-nav">
            <!-- Dashboard -->
            <div class="nav-item">
                <a href="/" class="nav-link active">
                    <i class="fas fa-home nav-link-icon"></i>
                    <span class="nav-link-text">Dashboard</span>
                    <span class="nav-tooltip">Dashboard</span>
                </a>
            </div>

            <!-- Navigation Section -->
            <div class="nav-section-title">Main Menu</div>

            <!-- Consignments -->
            <div class="nav-item">
                <a href="/modules/consignments/" class="nav-link">
                    <i class="fas fa-boxes nav-link-icon"></i>
                    <span class="nav-link-text">Consignments</span>
                    <span class="nav-tooltip">Consignments</span>
                </a>
            </div>

            <!-- Inventory -->
            <div class="nav-item">
                <a href="#" class="nav-link" data-toggle="submenu">
                    <i class="fas fa-warehouse nav-link-icon"></i>
                    <span class="nav-link-text">Inventory</span>
                    <i class="fas fa-chevron-down nav-link-arrow"></i>
                    <span class="nav-tooltip">Inventory</span>
                </a>
                <div class="nav-submenu">
                    <a href="/modules/inventory/count.php">Stock Count</a>
                    <a href="/modules/transfers/list.php">Transfers</a>
                    <a href="/product-browser.php">Product Browser</a>
                </div>
            </div>

            <!-- Purchase Orders -->
            <div class="nav-item">
                <a href="/modules/purchase_orders/list.php" class="nav-link">
                    <i class="fas fa-file-invoice nav-link-icon"></i>
                    <span class="nav-link-text">Purchase Orders</span>
                    <span class="nav-tooltip">Purchase Orders</span>
                </a>
            </div>

            <!-- Suppliers -->
            <div class="nav-item">
                <a href="/modules/suppliers/list.php" class="nav-link">
                    <i class="fas fa-truck nav-link-icon"></i>
                    <span class="nav-link-text">Suppliers</span>
                    <span class="nav-tooltip">Suppliers</span>
                </a>
            </div>

            <!-- Reports Section -->
            <div class="nav-section-title">Reports & Analytics</div>

            <!-- Sales & Reports -->
            <div class="nav-item">
                <a href="#" class="nav-link" data-toggle="submenu">
                    <i class="fas fa-chart-line nav-link-icon"></i>
                    <span class="nav-link-text">Sales & Reports</span>
                    <i class="fas fa-chevron-down nav-link-arrow"></i>
                    <span class="nav-tooltip">Sales & Reports</span>
                </a>
                <div class="nav-submenu">
                    <a href="/my-store-reports.php">Store Reports</a>
                    <a href="/daily-store-reconciliations.php">Reconciliations</a>
                    <a href="/individual-performance.php">Performance</a>
                </div>
            </div>

            <!-- Finance -->
            <div class="nav-item">
                <a href="#" class="nav-link" data-toggle="submenu">
                    <i class="fas fa-dollar-sign nav-link-icon"></i>
                    <span class="nav-link-text">Finance</span>
                    <i class="fas fa-chevron-down nav-link-arrow"></i>
                    <span class="nav-tooltip">Finance</span>
                </a>
                <div class="nav-submenu">
                    <a href="/harp-financials.php">HARP Financials</a>
                    <a href="/bank-transactions.php">Bank Transactions</a>
                    <a href="/cash-expenses.php">Cash Expenses</a>
                </div>
            </div>

            <!-- HR Section -->
            <div class="nav-section-title">People</div>

            <!-- HR & Staff -->
            <div class="nav-item">
                <a href="#" class="nav-link" data-toggle="submenu">
                    <i class="fas fa-users nav-link-icon"></i>
                    <span class="nav-link-text">HR & Staff</span>
                    <i class="fas fa-chevron-down nav-link-arrow"></i>
                    <span class="nav-tooltip">HR & Staff</span>
                </a>
                <div class="nav-submenu">
                    <a href="/hr-overview.php">HR Overview</a>
                    <a href="/my-hr.php">My HR</a>
                    <a href="/my-leave-requests.php">Leave Requests</a>
                </div>
            </div>

            <!-- System Section -->
            <div class="nav-section-title">System</div>

            <!-- Settings -->
            <div class="nav-item">
                <a href="/cis-configuration.php" class="nav-link">
                    <i class="fas fa-cog nav-link-icon"></i>
                    <span class="nav-link-text">Settings</span>
                    <span class="nav-tooltip">Settings</span>
                </a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <i class="fas fa-info-circle"></i>
            <span class="sidebar-footer-text">CIS v3.0.0</span>
        </div>
    </aside>

    <!-- Mobile Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Header -->
    <header class="cis-header">
        <div class="header-left">
            <button class="header-toggle-btn" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>

            <?php if (!empty($breadcrumbs)): ?>
                <nav class="header-breadcrumb">
                    <?php foreach ($breadcrumbs as $index => $crumb): ?>
                        <?php if ($index > 0): ?>
                            <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <?php endif; ?>
                        <?php if (!empty($crumb['active'])): ?>
                            <span class="active"><?= htmlspecialchars($crumb['label']) ?></span>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($crumb['url']) ?>"><?= htmlspecialchars($crumb['label']) ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
        </div>

        <div class="header-search" style="position: relative;">
            <i class="fas fa-search header-search-icon"></i>
            <input type="text" placeholder="Search anything... (Ctrl+K)" id="globalSearch">
        </div>

        <div class="header-right">
            <button class="header-icon-btn" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if (!empty($notificationCount) && $notificationCount > 0): ?>
                    <span class="header-badge"><?= $notificationCount ?></span>
                <?php endif; ?>
            </button>

            <button class="header-icon-btn" title="Messages">
                <i class="fas fa-envelope"></i>
            </button>

            <div class="header-user" id="headerUserMenu">
                <div class="header-avatar">
                    <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="header-user-info">
                    <div class="header-user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></div>
                    <div class="header-user-role"><?= htmlspecialchars($_SESSION['user_role'] ?? 'Staff') ?></div>
                </div>
                <i class="fas fa-chevron-down" style="font-size: 10px; color: var(--cis-text-light);"></i>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="cis-main">
        <div class="cis-content">
            <?= $content ?>
        </div>

        <footer class="cis-footer">
            <p>&copy; <?= date('Y') ?> Ecigdis Limited. All rights reserved. | CIS v3.0.0</p>
        </footer>
    </main>

    <!-- ============================================================================
         JAVASCRIPT LIBRARIES - Modern Stack (Keeping existing)
         ============================================================================ -->

    <!-- jQuery 3.7.1 -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap 5.3.2 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Chart.js 4.4.0 -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>

    <!-- DataTables 1.13.7 -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <!-- Select2 4.1.0 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Flatpickr 4.6.13 -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <!-- Moment.js 2.29.4 -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>

    <!-- Toastr 2.1.4 -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <!-- SweetAlert2 11.10.1 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Axios 1.6.2 -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <!-- Lodash 4.17.21 -->
    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.21/lodash.min.js"></script>

    <!-- ============================================================================
         MODERN CIS TEMPLATE SCRIPTS
         ============================================================================ -->

    <script>
        $(document).ready(function() {
            // Sidebar toggle (desktop collapse)
            $('#sidebarToggle').on('click', function() {
                if (window.innerWidth > 768) {
                    $('body').toggleClass('sidebar-collapsed');
                    localStorage.setItem('sidebarCollapsed', $('body').hasClass('sidebar-collapsed'));
                } else {
                    $('body').toggleClass('sidebar-open');
                }
            });

            // Restore sidebar state
            if (localStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth > 768) {
                $('body').addClass('sidebar-collapsed');
            }

            // Mobile overlay click
            $('#sidebarOverlay').on('click', function() {
                $('body').removeClass('sidebar-open');
            });

            // Submenu toggle
            $('.nav-link[data-toggle="submenu"]').on('click', function(e) {
                e.preventDefault();
                const $item = $(this).closest('.nav-item');

                // Close other submenus
                $('.nav-item.open').not($item).removeClass('open');

                // Toggle current
                $item.toggleClass('open');
            });

            // Set active link
            const currentPath = window.location.pathname;
            $('.nav-link, .nav-submenu a').each(function() {
                const href = $(this).attr('href');
                if (href && href !== '#' && currentPath.includes(href)) {
                    $(this).addClass('active');
                    $(this).closest('.nav-item').addClass('open');
                }
            });

            // Global search keyboard shortcut (Ctrl+K)
            $(document).on('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    $('#globalSearch').focus();
                }
            });

            // Initialize Bootstrap tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Initialize Bootstrap popovers
            const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });

            // Toastr default config
            toastr.options = {
                closeButton: true,
                progressBar: true,
                positionClass: 'toast-top-right',
                timeOut: 3000
            };
        });
    </script>

    <!-- Additional Page JS -->
    <?php if (!empty($pageJS)): ?>
        <?php foreach ($pageJS as $js): ?>
            <script src="<?= $js ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Inline Scripts -->
    <?php if (!empty($inlineScripts)): ?>
        <script><?= $inlineScripts ?></script>
    <?php endif; ?>

</body>
</html>
