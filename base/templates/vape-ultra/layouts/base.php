<?php
/**
 * Base Layout Template
 *
 * The foundation layout that all other layouts inherit from
 * Provides structure for header, content, footer injection
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 4));
}

$config = require __DIR__ . '/../config.php';
$pageTitle = $pageTitle ?? 'Vape Shed CIS Ultra';
$pageClass = $pageClass ?? 'page-default';
$layoutType = $layoutType ?? 'main';

// Module content will be injected here
$moduleContent = $moduleContent ?? '';
$moduleScripts = $moduleScripts ?? [];
$moduleStyles = $moduleStyles ?? [];

// Load dynamic asset loader
require_once __DIR__ . '/../includes/VapeUltraAssets.php';
$assetLoader = new VapeUltraAssets();

// Get module paths if set
$modulePaths = $modulePaths ?? [];

// Merge dynamic assets with module assets
$dynamicCSS = $assetLoader->getCSS($modulePaths);
$dynamicJS = $assetLoader->getJS($modulePaths);

// Combine all styles and scripts
$allStyles = array_merge($config['assets']['css'], $dynamicCSS, $moduleStyles);
$allScripts = array_merge($config['assets']['js'], $dynamicJS, $moduleScripts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="theme-color" content="#6366f1">

    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">

    <!-- CSS Stack (Core + Dynamic + Module) -->
    <?php foreach ($allStyles as $css): ?>
    <link rel="stylesheet" href="<?= $css ?>">
    <?php endforeach; ?>

    <!-- Critical inline CSS for immediate render -->
    <style>
        /* Prevent FOUC */
        body { opacity: 0; transition: opacity 0.2s; }
        body.loaded { opacity: 1; }

        /* Loading indicator */
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #f5f6f8;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.3s;
        }
        .page-loader.hidden { opacity: 0; pointer-events: none; }
    </style>
</head>
<body class="<?= htmlspecialchars($pageClass) ?>" data-layout="<?= htmlspecialchars($layoutType) ?>">
    <!-- Page Loader -->
    <div class="page-loader">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Right Sidebar Hover Trigger -->
    <div class="sidebar-right-trigger" id="sidebar-right-trigger"></div>

    <!-- Main Application Container -->
    <div id="app" class="app-container">
        <?php
        // Include the appropriate layout
        $layoutFile = __DIR__ . "/{$layoutType}.php";
        if (file_exists($layoutFile)) {
            include $layoutFile;
        } else {
            echo '<div class="alert alert-danger">Layout not found: ' . htmlspecialchars($layoutType) . '</div>';
        }
        ?>
    </div>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3"></div>

    <!-- JS Stack (Core + Dynamic + Module) -->
    <?php foreach ($allScripts as $js): ?>
    <script src="<?= $js ?>"></script>
    <?php endforeach; ?>

    <!-- Initialization Script -->
    <script>
        // Global app instance
        window.VapeUltra = window.VapeUltra || {};

        // Configuration
        VapeUltra.config = <?= json_encode($config) ?>;

        // Initialize on DOM ready
        document.addEventListener('DOMContentLoaded', function() {
            // Hide loader
            setTimeout(() => {
                document.querySelector('.page-loader')?.classList.add('hidden');
                document.body.classList.add('loaded');
            }, 300);

            // Initialize core systems
            if (typeof VapeUltra.Core !== 'undefined') {
                VapeUltra.Core.init();
            }

            // Trigger module init event
            document.dispatchEvent(new CustomEvent('vapeultra:ready'));
        });
    </script>

    <!-- Module inline scripts -->
    <?php if (isset($inlineScripts) && $inlineScripts): ?>
    <script><?= $inlineScripts ?></script>
    <?php endif; ?>
</body>
</html>
