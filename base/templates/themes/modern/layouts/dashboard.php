<?php
/**
 * CIS Modern Dashboard Layout
 *
 * Modular, component-based template for all CIS modules.
 * Clean, modern, and maintainable.
 *
 * Required variables:
 * - $content: Main page content (HTML string)
 * - $pageTitle: Page title (string, optional)
 * - $breadcrumbs: Breadcrumb array (optional)
 * - $pageCSS: Array of additional CSS files (optional)
 * - $pageJS: Array of additional JS files (optional)
 * - $inlineScripts: Inline JavaScript code (optional)
 * - $notificationCount: Number for notification badge (optional)
 *
 * @package CIS\Templates\Modern
 * @version 3.0.0
 */

// Ensure content is provided
if (!isset($content)) {
    $content = '<div class="alert alert-danger">Error: No content provided to template</div>';
}

// Include head component
require_once __DIR__ . '/../components/head.php';
?>

<?php require_once __DIR__ . '/../components/sidebar.php'; ?>

<?php require_once __DIR__ . '/../components/header.php'; ?>

<!-- Main Content + Right Hover Sidebar -->
<main class="cis-main">
    <div class="cis-content">
        <?= $content ?>
    </div>

    <footer class="cis-footer">
        <p>&copy; <?= date('Y') ?> Ecigdis Limited. All rights reserved. | CIS v3.0.0</p>
    </footer>

    <!-- Right hover sidebar (compact, hidden by default) -->
    <aside class="cis-rightbar" id="cisRightbar" aria-hidden="true">
        <div class="rightbar-header">
            <span class="rightbar-title"><i class="fas fa-sliders-h"></i> Quick Panel</span>
            <button class="rightbar-close" id="rightbarClose" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="rightbar-content">
            <div class="rightbar-section">
                <div class="section-title"><i class="fas fa-bolt"></i> Actions</div>
                <div class="section-body">
                    <a class="rb-link" href="/modules/core/change-password.php"><i class="fas fa-key"></i> Change Password</a>
                    <a class="rb-link" href="/modules/core/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            <div class="rightbar-section">
                <div class="section-title"><i class="fas fa-info-circle"></i> System</div>
                <div class="section-body small text-muted">
                    <div>Theme: modern</div>
                    <div>Layout: compact rightbar</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Hover zones -->
    <div class="hover-zone hover-zone-right" id="hoverZoneRight" aria-hidden="true"></div>
    <div class="hover-zone hover-zone-left" id="hoverZoneLeft" aria-hidden="true"></div>
</main>

<?php require_once __DIR__ . '/../components/scripts.php'; ?>

</body>
</html>
