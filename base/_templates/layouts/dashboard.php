<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= $pageTitle ?? 'CIS - Central Information System' ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">

    <!-- CIS Core CSS -->
    <link rel="stylesheet" href="/assets/css/cis-core.css">

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
        body.layout-dashboard {
            margin: 0;
            overflow-x: hidden;
        }

        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .dashboard-sidebar {
            width: 260px;
            background-color: var(--cis-dark);
            color: var(--cis-white);
            flex-shrink: 0;
            transition: margin-left 0.3s ease;
        }

        .dashboard-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .dashboard-header {
            background-color: var(--cis-white);
            border-bottom: 1px solid var(--cis-border-color);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--cis-shadow-sm);
            position: sticky;
            top: 0;
            z-index: var(--cis-z-sticky);
        }

        .dashboard-content {
            flex: 1;
            padding: 1.5rem;
            background-color: var(--cis-gray-100);
        }

        .dashboard-footer {
            background-color: var(--cis-white);
            border-top: 1px solid var(--cis-border-color);
            padding: 1rem 1.5rem;
            text-align: center;
            color: var(--cis-gray-600);
            font-size: var(--cis-font-size-sm);
        }

        /* Sidebar collapsed state */
        .sidebar-collapsed .dashboard-sidebar {
            margin-left: -260px;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .dashboard-sidebar {
                position: fixed;
                left: 0;
                top: 0;
                height: 100vh;
                z-index: var(--cis-z-fixed);
                margin-left: -260px;
            }

            .sidebar-open .dashboard-sidebar {
                margin-left: 0;
            }

            .dashboard-content {
                padding: 1rem;
            }

            .mobile-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: calc(var(--cis-z-fixed) - 1);
            }

            .sidebar-open .mobile-overlay {
                display: block;
            }
        }
    </style>

    <!-- Inline Styles -->
    <?php if (!empty($inlineStyles)): ?>
        <style><?= $inlineStyles ?></style>
    <?php endif; ?>
</head>
<body class="layout-dashboard">

    <div class="dashboard-wrapper" id="dashboardWrapper">

        <!-- Sidebar -->
        <aside class="dashboard-sidebar" id="dashboardSidebar">
            <?php include __DIR__ . '/../components/sidebar.php'; ?>
        </aside>

        <!-- Main Content Area -->
        <div class="dashboard-main">

            <!-- Header -->
            <header class="dashboard-header">
                <?php include __DIR__ . '/../components/header.php'; ?>
            </header>

            <!-- Breadcrumbs (optional) -->
            <?php if (!empty($breadcrumbs)): ?>
                <div class="container-fluid mt-3">
                    <?php include __DIR__ . '/../components/breadcrumbs.php'; ?>
                </div>
            <?php endif; ?>

            <!-- Page Content -->
            <main class="dashboard-content">
                <?= $content ?>
            </main>

            <!-- Footer -->
            <footer class="dashboard-footer">
                <?php include __DIR__ . '/../components/footer.php'; ?>
            </footer>

        </div>

    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <!-- ============================================================================
         JAVASCRIPT LIBRARIES - Modern Stack
         ============================================================================ -->

    <!-- jQuery 3.7.1 (Required for Bootstrap, DataTables, etc.) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap 5.3.2 JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Chart.js 4.4.0 (Charts & Graphs) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>

    <!-- DataTables 1.13.7 (Advanced Tables) -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <!-- Select2 4.1.0 (Enhanced Select Dropdowns) -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Flatpickr 4.6.13 (Date/Time Picker) -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <!-- Moment.js 2.29.4 (Date/Time Manipulation) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>

    <!-- Toastr 2.1.4 (Toast Notifications) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <!-- SweetAlert2 11.10.1 (Beautiful Modals) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Axios 1.6.2 (HTTP Requests) -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <!-- Lodash 4.17.21 (Utility Functions) -->
    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.21/lodash.min.js"></script>

    <!-- ============================================================================
         DASHBOARD SIDEBAR TOGGLE SCRIPT
         ============================================================================ -->

    <script>
        $(document).ready(function() {
            const $wrapper = $('#dashboardWrapper');
            const $sidebarToggle = $('#sidebarToggle');
            const $mobileOverlay = $('#mobileOverlay');

            // Toggle sidebar on mobile
            function toggleSidebar() {
                $wrapper.toggleClass('sidebar-open');
            }

            $sidebarToggle.on('click', toggleSidebar);
            $mobileOverlay.on('click', toggleSidebar);

            // Close sidebar when clicking a link on mobile
            $('.dashboard-sidebar a').on('click', function() {
                if (window.innerWidth <= 768) {
                    $wrapper.removeClass('sidebar-open');
                }
            });

            // Toggle sidebar collapse on desktop
            $('#sidebarCollapseToggle').on('click', function() {
                $wrapper.toggleClass('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', $wrapper.hasClass('sidebar-collapsed'));
            });

            // Restore sidebar state from localStorage
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                $wrapper.addClass('sidebar-collapsed');
            }

            // Initialize tooltips (Bootstrap 5)
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Initialize popovers (Bootstrap 5)
            const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
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
