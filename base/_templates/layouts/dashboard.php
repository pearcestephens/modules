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
        
    </div>
    
    <!-- CIS Core JavaScript -->
    <script src="/assets/js/cis-core.js"></script>
    
    <!-- Dashboard JS (Vanilla JavaScript - No jQuery) -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const wrapper = document.getElementById('dashboardWrapper');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const mobileOverlay = document.getElementById('mobileOverlay');
            
            // Toggle sidebar on mobile
            const toggleSidebar = () => {
                wrapper.classList.toggle('sidebar-open');
            };
            
            if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
            if (mobileOverlay) mobileOverlay.addEventListener('click', toggleSidebar);
            
            // Close sidebar when clicking a link on mobile
            const sidebarLinks = document.querySelectorAll('.dashboard-sidebar a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth <= 768) {
                        wrapper.classList.remove('sidebar-open');
                    }
                });
            });
        });
                }
            });
            
            // Toggle sidebar collapse on desktop
            $('#sidebarCollapseToggle').on('click', function() {
                $('#dashboardWrapper').toggleClass('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', $('#dashboardWrapper').hasClass('sidebar-collapsed'));
            });
            
            // Restore sidebar state from localStorage
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                $('#dashboardWrapper').addClass('sidebar-collapsed');
            }
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
