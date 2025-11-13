<?php
/**
 * Consignments Module - Main Router
 *
 * Routes requests to controllers (not directly to views).
 * Follows base/core design pattern with ?route= parameter.
 *
 * @package CIS\Consignments
 * @version 4.0.0
 * @updated 2025-11-13 - Refactored to use controllers
 */

declare(strict_types=1);

// Load bootstrap to initialize sessions and database
require_once __DIR__ . '/bootstrap.php';

// AUTHENTICATION MIDDLEWARE - Require user to be logged in
requireAuth();

// Load controllers
use CIS\Consignments\Controllers\StockTransferController;
use CIS\Consignments\Controllers\PurchaseOrderController;
use CIS\Consignments\Controllers\TransferManagerController;

// Get route and action
$route = $_GET['route'] ?? 'home';
$action = $_GET['action'] ?? 'index';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    switch ($route) {
        case 'home':
        case '':
            // Home dashboard - central hub with quick access to all features
            require_once __DIR__ . '/views/home.php';
            break;

        case 'stock-transfers':
            $controller = new StockTransferController($db);
            switch ($action) {
                case 'index':
                    $controller->index();
                    break;
                case 'pack':
                    if (!$id) throw new InvalidArgumentException('Transfer ID required');
                    $controller->pack($id);
                    break;
                case 'receive':
                    if (!$id) throw new InvalidArgumentException('Transfer ID required');
                    $controller->receive($id);
                    break;
                case 'freight-quote':
                    if (!$id) throw new InvalidArgumentException('Transfer ID required');
                    $controller->getFreightQuote($id);
                    break;
                case 'create-label':
                    if (!$id) throw new InvalidArgumentException('Transfer ID required');
                    $carrier = $_POST['carrier'] ?? '';
                    $service = $_POST['service'] ?? '';
                    $controller->createLabel($id, $carrier, $service);
                    break;
                case 'track':
                    if (!$id) throw new InvalidArgumentException('Transfer ID required');
                    $controller->trackShipment($id);
                    break;
                default:
                    throw new InvalidArgumentException('Unknown action: ' . $action);
            }
            break;

        case 'purchase-orders':
            $controller = new PurchaseOrderController($db);
            switch ($action) {
                case 'index':
                    $controller->index();
                    break;
                case 'view':
                    if (!$id) throw new InvalidArgumentException('PO ID required');
                    $controller->view($id);
                    break;
                case 'create':
                    $controller->create();
                    break;
                case 'freight-quote':
                    if (!$id) throw new InvalidArgumentException('PO ID required');
                    $controller->getFreightQuote($id);
                    break;
                case 'create-label':
                    if (!$id) throw new InvalidArgumentException('PO ID required');
                    $controller->createFreightLabel($id);
                    break;
                case 'track':
                    if (!$id) throw new InvalidArgumentException('PO ID required');
                    $controller->trackShipment($id);
                    break;
                case 'receive':
                    if (!$id) throw new InvalidArgumentException('PO ID required');
                    $controller->receive($id);
                    break;
                default:
                    throw new InvalidArgumentException('Unknown action: ' . $action);
            }
            break;

        case 'transfer-manager':
            $controller = new TransferManagerController($db);
            $controller->index();
            break;

        case 'control-panel':
            require_once __DIR__ . '/views/control-panel.php';
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

        case 'buttons-preview':
            // Button Design Lab (preview-only, scoped CSS, no global changes)
            require_once __DIR__ . '/views/buttons-preview.php';
            break;

        default:
            // Invalid route - show 404 with link back to home
            header('HTTP/1.0 404 Not Found');
            echo '<h1>404 - Page Not Found</h1>';
            echo '<p>The requested page could not be found.</p>';
            echo '<p><a href="/modules/consignments/">Return to Consignments Home</a></p>';
            exit;
    }

} catch (Exception $e) {
    // Log error
    error_log("Consignments Router Error: " . $e->getMessage());

    // Show user-friendly error
    header('HTTP/1.0 500 Internal Server Error');
    echo '<h1>Error</h1>';
    echo '<p>An error occurred: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><a href="/modules/consignments/">Return to Consignments Home</a></p>';
    exit;
}
