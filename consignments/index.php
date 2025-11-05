<?php
/**
 * Consignments Module - Main Router
 *
 * Routes requests to appropriate views within the consignments module.
 * This is the central entry point for all consignment-related pages.
 *
 * @package CIS\Consignments
 * @version 3.0.0
 * @updated 2025-11-05 - Added home page as default route
 */

declare(strict_types=1);

// Load bootstrap to initialize sessions and database
require_once __DIR__ . '/bootstrap.php';

// Determine which view to load based on route parameter
// Default to 'home' for dashboard landing page (no more dead breadcrumb spots!)
$route = $_GET['route'] ?? 'home';

switch ($route) {
    case 'home':
    case '':
        // Home dashboard - central hub with quick access to all features
        require_once __DIR__ . '/views/home.php';
        break;

    case 'transfer-manager':
        require_once __DIR__ . '/views/transfer-manager.php';
        break;

    case 'control-panel':
        require_once __DIR__ . '/views/control-panel.php';
        break;

    case 'purchase-orders':
        require_once __DIR__ . '/views/purchase-orders.php';
        break;

    case 'stock-transfers':
        require_once __DIR__ . '/views/stock-transfers.php';
        break;

    case 'receiving':
        require_once __DIR__ . '/views/receiving.php';
        break;

    case 'freight':
        require_once __DIR__ . '/views/freight.php';
        break;

    case 'queue-status':
        require_once __DIR__ . '/views/queue-status.php';
        break;

    case 'admin-controls':
        require_once __DIR__ . '/views/admin-controls.php';
        break;

    case 'ai-insights':
        require_once __DIR__ . '/views/ai-insights.php';
        break;

    default:
        // Invalid route - show 404 with link back to home
        header('HTTP/1.0 404 Not Found');
        echo '<h1>404 - Page Not Found</h1>';
        echo '<p>The requested page could not be found.</p>';
        echo '<p><a href="/modules/consignments/">Return to Consignments Home</a></p>';
        exit;
}
