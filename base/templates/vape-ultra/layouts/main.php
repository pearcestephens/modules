<?php
/**
 * Main Layout - Full Featured
 *
 * Grid layout with header, sidebar, main content, right sidebar, footer
 * This is where module content gets injected
 */
?>

<div class="app-grid">
    <!-- Header -->
    <header class="header" id="app-header">
        <?php include __DIR__ . '/../components/header.php'; ?>
    </header>

    <!-- Sidebar -->
    <aside class="sidebar" id="app-sidebar">
        <?php include __DIR__ . '/../components/sidebar.php'; ?>
    </aside>

    <!-- Main Content Area - MODULE INJECTION POINT -->
    <main class="main" id="app-main">
        <?php
        // This is where module content gets injected
        if (isset($moduleContent) && $moduleContent) {
            echo $moduleContent;
        } elseif (isset($contentFile) && file_exists($contentFile)) {
            include $contentFile;
        } else {
            echo '<div class="alert alert-info">No content loaded. Module should inject content here.</div>';
        }
        ?>
    </main>

    <!-- Right Sidebar (optional, can be hidden by modules) -->
    <?php if (!isset($hideRightSidebar) || !$hideRightSidebar): ?>
    <aside class="sidebar-right" id="app-sidebar-right">
        <?php
        if (isset($rightSidebarContent)) {
            echo $rightSidebarContent;
        } else {
            $sidebarPath = __DIR__ . '/../components/sidebar-right.php';
            if (file_exists($sidebarPath)) {
                include $sidebarPath;
            } else {
                echo '<div class="p-3 text-danger">Right sidebar file not found: ' . htmlspecialchars($sidebarPath) . '</div>';
            }
        }
        ?>
    </aside>
    <?php else: ?>
        <!-- Right sidebar hidden by module -->
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer" id="app-footer">
        <?php include __DIR__ . '/../components/footer.php'; ?>
    </footer>
</div>
