<?php
/**
 * Consignments Module - Router Entry Point
 *
 * Routes requests to appropriate views within the Consignments module.
 * Uses BASE template system for consistent layout and full library stack.
 *
 * @package CIS\Consignments
 * @version 3.0.0
 * @created 2025-11-01
 * @updated 2025-11-04 - Converted to BASE template
 */

declare(strict_types=1);

// Load module bootstrap (includes base/bootstrap.php)
require_once __DIR__ . '/bootstrap.php';

// Initialize session if not already started
if (session_status() !== PHP_SESSION_ACTIVE) {
    CIS\Base\Session::init();
}

// Get route from query string
$route = CIS\Base\Router::getRoute();

// Route to appropriate view
switch ($route) {
    case 'transfer-manager':
    case 'index':
    case '':
    default:
        // Main Transfer Manager interface - EXACT existing view
        require_once __DIR__ . '/views/transfer-manager.php';
        break;

    case 'control-panel':
        // System monitoring dashboard - EXACT existing view
        require_once __DIR__ . '/views/control-panel.php';
        break;

    case 'purchase-orders':
        // Purchase orders list - EXACT existing view
        require_once __DIR__ . '/views/purchase-orders.php';
        break;

    case 'stock-transfers':
        // Stock transfers list - EXACT existing view
        require_once __DIR__ . '/views/stock-transfers.php';
        break;

    case 'freight':
        // Freight management - EXACT existing view
        require_once __DIR__ . '/views/freight.php';
        break;

    case 'queue-status':
        // Queue worker status - EXACT existing view
        require_once __DIR__ . '/views/queue-status.php';
        break;

    case 'admin-controls':
        // Admin control panel - EXACT existing view
        require_once __DIR__ . '/views/admin-controls.php';
        break;

    case 'ai-insights':
        // AI Insights & Recommendations Dashboard
        require_once __DIR__ . '/purchase-orders/ai-insights.php';
        break;
}
