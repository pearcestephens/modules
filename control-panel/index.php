<?php
/**
 * CIS Control Panel - Main Router
 *
 * @package CIS\Modules\ControlPanel
 * @version 1.0.0
 */

require_once __DIR__ . '/bootstrap.php';

// Get requested page
$page = $_GET['page'] ?? 'dashboard';

// Validate page
$allowedPages = [
    'dashboard',
    'modules',
    'config',
    'backups',
    'environments',
    'documentation',
    'system-info',
    'logs'
];

if (!in_array($page, $allowedPages)) {
    $page = 'dashboard';
}

// Route to view
$viewFile = CONTROL_PANEL_VIEWS_PATH . '/' . $page . '.php';

if (file_exists($viewFile)) {
    require_once $viewFile;
} else {
    http_response_code(404);
    echo '<h1>404 - Page Not Found</h1>';
    echo '<p>The requested view does not exist: ' . htmlspecialchars($page) . '</p>';
}
