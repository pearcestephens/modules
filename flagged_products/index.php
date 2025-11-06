<?php
/**
 * Flagged Products Module - Entry Point
 *
 * Routes requests to appropriate controllers
 *
 * @package CIS\Modules\FlaggedProducts
 * @version 1.0.0
 */

declare(strict_types=1);

// Bootstrap module
$config = require __DIR__ . '/bootstrap.php';

use CIS\FlaggedProducts\Controllers\FlaggedProductController;

// Initialize controller
$controller = new FlaggedProductController($config);

// Simple routing
$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'outlet':
        $controller->outlet();
        break;

    case 'cron-dashboard':
    case 'cron':
        $controller->cronDashboard();
        break;

    case 'index':
    default:
        $controller->index();
        break;
}
