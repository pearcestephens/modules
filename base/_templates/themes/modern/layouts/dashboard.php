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

<!-- Main Content -->
<main class="cis-main">
    <div class="cis-content">
        <?= $content ?>
    </div>

    <footer class="cis-footer">
        <p>&copy; <?= date('Y') ?> Ecigdis Limited. All rights reserved. | CIS v3.0.0</p>
    </footer>
</main>

<?php require_once __DIR__ . '/../components/scripts.php'; ?>

</body>
</html>
