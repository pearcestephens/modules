<?php
/**
 * =============================================================================
 * VAPEULTRA MASTER TEMPLATE
 * =============================================================================
 *
 * Version: 2.0.0
 * Status: 🔒 PRODUCTION READY
 *
 * THE ONLY BASE TEMPLATE - All modules MUST inherit from this file.
 * This is the single source of truth for page structure.
 *
 * CONTENT BLOCKS (Customizable per page):
 * - head: HTML <head> section metadata
 * - header: Top navigation bar
 * - sidebar: Left navigation menu
 * - breadcrumb: Navigation trail
 * - subnav: Module-specific submenu
 * - content: Main page content (REQUIRED)
 * - sidebar-right: Right activity panel
 * - footer: Page footer
 * - modals: Modal container
 * - scripts: JavaScript files
 *
 * USAGE EXAMPLE:
 * ```php
 * $renderer->render('master', [
 *     'title' => 'Dashboard',
 *     'content' => $pageHTML,
 *     'showBreadcrumb' => true,
 *     'breadcrumb' => [...],
 *     'showSubnav' => true,
 *     'subnav' => [...]
 * ]);
 * ```
 *
 * =============================================================================
 */

// Extract variables for use in template
$pageTitle = $title ?? 'CIS - Staff Portal';
$bodyClass = $bodyClass ?? '';
$layout = $layout ?? 'full'; // full, minimal, print, error

// Block visibility flags (default to true)
$showHeader = $showHeader ?? true;
$showSidebar = $showSidebar ?? true;
$showBreadcrumb = $showBreadcrumb ?? false;
$showSubnav = $showSubnav ?? false;
$showSidebarRight = $showSidebarRight ?? false;
$showFooter = $showFooter ?? true;

// Navigation data
$breadcrumb = $breadcrumb ?? [];
$subnav = $subnav ?? [];
$navigation = $navigation ?? [];

// User data
$user = $user ?? null;

// Meta tags
$metaDescription = $metaDescription ?? 'CIS Staff Portal - Vape Shed Internal System';
$metaKeywords = $metaKeywords ?? 'vape, staff, portal, inventory, sales';

// CSS/JS assets
$additionalCSS = $additionalCSS ?? [];
$additionalJS = $additionalJS ?? [];

?>
<!DOCTYPE html>
<html lang="en" class="vape-theme">
<head>
    <!-- =====================================================================
         META TAGS & DOCUMENT INFO
         ===================================================================== -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($metaKeywords) ?>">
    <meta name="author" content="Ecigdis Limited">
    <meta name="robots" content="noindex, nofollow">

    <!-- Security Headers -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' cdn.jsdelivr.net code.jquery.com *.cloudflare.com; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net fonts.googleapis.com; font-src 'self' fonts.gstatic.com cdn.jsdelivr.net; img-src 'self' data: https: www.vapeshed.co.nz; connect-src 'self' https:;">

    <!-- Page Title -->
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <!-- =====================================================================
         PRECONNECT & DNS PREFETCH (Performance Optimization)
         ===================================================================== -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">

    <!-- =====================================================================
         FONTS
         ===================================================================== -->
    <!-- Inter Font Family (Primary) -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" as="style">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">

    <!-- =====================================================================
         CORE CSS FILES (VapeUltra Design System)
         Load Order: CRITICAL - Do not change
         ===================================================================== -->

    <!-- 0. VapeUltra Layout System (FIRST - Critical Structure) -->
    <link rel="stylesheet" href="/assets/vape-ultra/css/vape-layout.css">

    <!-- 1. Design System Variables (Foundation) -->
    <link rel="stylesheet" href="/assets/vape-ultra/css/variables.css">

    <!-- 2. Base Styles & Reset -->
    <link rel="stylesheet" href="/assets/vape-ultra/css/base.css">

    <!-- 3. Layout System -->
    <link rel="stylesheet" href="/assets/vape-ultra/css/layout.css">

    <!-- 4. Component Library -->
    <link rel="stylesheet" href="/assets/vape-ultra/css/components.css">

    <!-- 5. Utility Classes -->
    <link rel="stylesheet" href="/assets/vape-ultra/css/utilities.css">

    <!-- 6. Animations -->
    <link rel="stylesheet" href="/assets/vape-ultra/css/animations.css">

    <!-- =====================================================================
         THEME CSS FILES (Award-Winning Components)
         ===================================================================== -->
    <link rel="stylesheet" href="/assets/vape-ultra/css/silver-chrome-theme.css">
    <link rel="stylesheet" href="/assets/vape-ultra/css/store-cards-award-winning.css">
    <link rel="stylesheet" href="/assets/vape-ultra/css/award-winning-refinements.css">
    <link rel="stylesheet" href="/assets/vape-ultra/css/premium-dashboard-header.css">
    <link rel="stylesheet" href="/assets/vape-ultra/css/sidebar-award-winning.css">

    <!-- =====================================================================
         EXTERNAL DEPENDENCIES (CDN)
         ===================================================================== -->
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- =====================================================================
         ADDITIONAL PAGE-SPECIFIC CSS
         ===================================================================== -->
    <?php foreach ($additionalCSS as $cssFile): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($cssFile) ?>">
    <?php endforeach; ?>

    <!-- =====================================================================
         INLINE CRITICAL CSS (Above-the-fold optimization)
         ===================================================================== -->
    <style>
        /* Loading State - Shown while page initializes */
        .vape-page-loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--vape-white);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 1;
            transition: opacity 0.3s ease;
        }

        .vape-page-loading.loaded {
            opacity: 0;
            pointer-events: none;
        }

        .vape-spinner {
            width: 48px;
            height: 48px;
            border: 4px solid var(--vape-gray-200);
            border-top-color: var(--vape-primary-500);
            border-radius: 50%;
            animation: vape-spin 0.8s linear infinite;
        }

        @keyframes vape-spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>

<body class="vape-body layout-<?= $layout ?> <?= $bodyClass ?>" data-layout="<?= $layout ?>">

    <!-- =====================================================================
         LOADING OVERLAY (Removed after page load)
         ===================================================================== -->
    <div class="vape-page-loading" id="pageLoader">
        <div class="vape-spinner"></div>
    </div>

    <!-- =====================================================================
         MAIN APPLICATION WRAPPER
         ===================================================================== -->
    <div class="vape-app-wrapper" id="appWrapper">

        <!-- =================================================================
             HEADER BLOCK (Top Navigation)
             ================================================================= -->
        <?php if ($showHeader): ?>
        <header class="vape-header" id="appHeader" role="banner">
            <?php include __DIR__ . '/../components/header.php'; ?>
        </header>
        <?php endif; ?>

        <!-- =================================================================
             SIDEBAR BLOCK (Left Navigation)
             ================================================================= -->
        <?php if ($showSidebar): ?>
        <aside class="vape-sidebar" id="appSidebar" role="navigation" aria-label="Main navigation">
            <?php include __DIR__ . '/../components/sidebar.php'; ?>
        </aside>
        <?php endif; ?>

        <!-- =================================================================
             MAIN CONTENT AREA
             ================================================================= -->
        <main class="vape-main" id="appMain" role="main">

            <!-- =============================================================
                 BREADCRUMB BLOCK (Navigation Trail)
                 ============================================================= -->
            <?php if ($showBreadcrumb && !empty($breadcrumb)): ?>
            <nav class="vape-breadcrumb" aria-label="Breadcrumb">
                <?php include __DIR__ . '/../components/breadcrumb.php'; ?>
            </nav>
            <?php endif; ?>

            <!-- =============================================================
                 SUB-NAVIGATION BLOCK (Module-Specific Menu)
                 ============================================================= -->
            <?php if ($showSubnav && !empty($subnav)): ?>
            <nav class="vape-subnav" id="appSubnav" aria-label="Section navigation">
                <?php include __DIR__ . '/../components/subnav.php'; ?>
            </nav>
            <?php endif; ?>

            <!-- =============================================================
                 PAGE CONTENT BLOCK (Main Content - REQUIRED)
                 ============================================================= -->
            <div class="vape-content" id="appContent">
                <?php if (isset($content)): ?>
                    <?= $content ?>
                <?php else: ?>
                    <div class="vape-alert vape-alert-error">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Error:</strong> No content provided to template.
                    </div>
                <?php endif; ?>
            </div>

        </main>

        <!-- =================================================================
             RIGHT SIDEBAR BLOCK (Activity Panel - Optional)
             ================================================================= -->
        <?php if ($showSidebarRight): ?>
        <aside class="vape-sidebar-right" id="appSidebarRight" role="complementary" aria-label="Activity panel">
            <?php include __DIR__ . '/../components/sidebar-right.php'; ?>
        </aside>
        <?php endif; ?>

        <!-- =================================================================
             FOOTER BLOCK
             ================================================================= -->
        <?php if ($showFooter): ?>
        <footer class="vape-footer" id="appFooter" role="contentinfo">
            <?php include __DIR__ . '/../components/footer.php'; ?>
        </footer>
        <?php endif; ?>

    </div>
    <!-- End .vape-app-wrapper -->

    <!-- =====================================================================
         MODAL CONTAINER (For dynamic modals)
         ===================================================================== -->
    <div class="vape-modal-container" id="modalContainer" aria-live="polite"></div>

    <!-- =====================================================================
         TOAST CONTAINER (For notifications)
         ===================================================================== -->
    <div class="vape-toast-container" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

    <!-- =====================================================================
         EXTERNAL JAVASCRIPT DEPENDENCIES (CDN)
         ===================================================================== -->

    <!-- Bootstrap 5 JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

    <!-- jQuery (Required for legacy components - will be removed in v3.0) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

    <!-- Axios (HTTP client) -->
    <script src="https://cdn.jsdelivr.net/npm/axios@1.6.0/dist/axios.min.js"></script>

    <!-- Lodash (Utility library) -->
    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.21/lodash.min.js"></script>

    <!-- Moment.js (Date/time handling) -->
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>

    <!-- Chart.js (Data visualization) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- SweetAlert2 (Beautiful alerts) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.all.min.js"></script>

    <!-- =====================================================================
         VAPEULTRA JAVASCRIPT LIBRARY
         Load Order: CRITICAL - Do not change
         ===================================================================== -->

    <!-- 1. Global Error Handler (FIRST - Catches all errors) -->
    <script src="/assets/vape-ultra/js/global-error-handler.js"></script>

    <!-- 2. AJAX Client (HTTP requests with interceptors) -->
    <script src="/assets/vape-ultra/js/ajax-client.js"></script>

    <!-- 3. Modal System (Dialogs and confirmations) -->
    <script src="/assets/vape-ultra/js/modal-system.js"></script>

    <!-- 4. Toast System (Notifications) -->
    <script src="/assets/vape-ultra/js/toast-system.js"></script>

    <!-- 5. Core Application -->
    <script src="/assets/vape-ultra/js/core.js"></script>

    <!-- 6. Utility Functions -->
    <script src="/assets/vape-ultra/js/utils.js"></script>

    <!-- 7. API Client -->
    <script src="/assets/vape-ultra/js/api.js"></script>

    <!-- 8. UI Components -->
    <script src="/assets/vape-ultra/js/components.js"></script>

    <!-- 9. Notifications -->
    <script src="/assets/vape-ultra/js/notifications.js"></script>

    <!-- 10. Charts -->
    <script src="/assets/vape-ultra/js/charts.js"></script>

    <!-- =====================================================================
         ADDITIONAL PAGE-SPECIFIC JAVASCRIPT
         ===================================================================== -->
    <?php foreach ($additionalJS as $jsFile): ?>
    <script src="<?= htmlspecialchars($jsFile) ?>"></script>
    <?php endforeach; ?>

    <!-- =====================================================================
         INITIALIZATION SCRIPT
         ===================================================================== -->
    <script>
    /**
     * VapeUltra Application Initialization
     * Runs after DOM is ready and all scripts are loaded
     */
    document.addEventListener('DOMContentLoaded', function() {

        // Initialize VapeUltra Core
        if (typeof VapeUltra !== 'undefined') {
            VapeUltra.init({
                debug: <?= json_encode(DEBUG ?? false) ?>,
                apiBase: '/api',
                user: <?= json_encode($user ?? null) ?>,
                csrf: '<?= $_SESSION['csrf_token'] ?? '' ?>',
                layout: '<?= $layout ?>'
            });

            console.log('✅ VapeUltra initialized successfully');
        } else {
            console.error('❌ VapeUltra core not loaded');
        }

        // Remove page loader after initialization
        setTimeout(function() {
            const loader = document.getElementById('pageLoader');
            if (loader) {
                loader.classList.add('loaded');
                setTimeout(function() {
                    loader.remove();
                }, 300);
            }
        }, 100);

        // Log page load performance
        if (window.performance) {
            const perfData = window.performance.timing;
            const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
            console.log('📊 Page load time:', pageLoadTime + 'ms');
        }
    });

    /**
     * Global error handler
     * Catches uncaught errors and displays user-friendly messages
     */
    window.addEventListener('error', function(event) {
        console.error('Uncaught error:', event.error);

        if (typeof VapeUltra !== 'undefined' && VapeUltra.toast) {
            VapeUltra.toast.error('An unexpected error occurred. Please refresh the page.');
        }
    });

    /**
     * Promise rejection handler
     * Catches unhandled promise rejections
     */
    window.addEventListener('unhandledrejection', function(event) {
        console.error('Unhandled promise rejection:', event.reason);

        if (typeof VapeUltra !== 'undefined' && VapeUltra.toast) {
            VapeUltra.toast.error('An error occurred processing your request.');
        }
    });
    </script>

    <!-- =====================================================================
         PAGE-SPECIFIC INLINE SCRIPTS (Optional)
         ===================================================================== -->
    <?php if (isset($inlineJS)): ?>
    <script>
    <?= $inlineJS ?>
    </script>
    <?php endif; ?>

</body>
</html>
