<?php
/**
 * Consignments API Router
 *
 * Clean REST-style API routing for consignment operations.
 * Handles AJAX requests from frontend.
 *
 * @package CIS\Consignments\API
 * @version 2.0.0
 */

declare(strict_types=1);

// Load bootstrap
require_once __DIR__ . '/../bootstrap.php';

// CORS headers for AJAX
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Authentication check
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Load controllers
use CIS\Consignments\Controllers\StockTransferController;
use CIS\Consignments\Controllers\PurchaseOrderController;

// Get request info
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    // Route to appropriate controller
    switch ($endpoint) {
        // Stock Transfer endpoints
        case 'stock-transfers/list':
            $controller = new StockTransferController($db);
            $controller->index();
            break;

        case 'stock-transfers/freight-quote':
            if (!$id) throw new InvalidArgumentException('Transfer ID required');
            $controller = new StockTransferController($db);
            $controller->getFreightQuote($id);
            break;

        case 'stock-transfers/create-label':
            if ($method !== 'POST') throw new BadMethodCallException('POST required');
            if (!$id) throw new InvalidArgumentException('Transfer ID required');
            $controller = new StockTransferController($db);
            $carrier = $_POST['carrier'] ?? '';
            $service = $_POST['service'] ?? '';
            $controller->createLabel($id, $carrier, $service);
            break;

        case 'stock-transfers/track':
            if (!$id) throw new InvalidArgumentException('Transfer ID required');
            $controller = new StockTransferController($db);
            $controller->trackShipment($id);
            break;

        // Purchase Order endpoints
        case 'purchase-orders/list':
            $controller = new PurchaseOrderController($db);
            $controller->index();
            break;

        case 'purchase-orders/freight-quote':
            if (!$id) throw new InvalidArgumentException('PO ID required');
            $controller = new PurchaseOrderController($db);
            $controller->getFreightQuote($id);
            break;

        case 'purchase-orders/create-label':
            if ($method !== 'POST') throw new BadMethodCallException('POST required');
            if (!$id) throw new InvalidArgumentException('PO ID required');
            $controller = new PurchaseOrderController($db);
            $controller->createFreightLabel($id);
            break;

        case 'purchase-orders/track':
            if (!$id) throw new InvalidArgumentException('PO ID required');
            $controller = new PurchaseOrderController($db);
            $controller->trackShipment($id);
            break;

        // Freight endpoints (generic)
        case 'freight/calculate':
            require_once __DIR__ . '/freight.php';
            break;

        case 'freight/rates':
            require_once __DIR__ . '/freight.php';
            break;

        case 'freight/containers':
            require_once __DIR__ . '/freight.php';
            break;

        // Unified Transfer Manager API (all transfer types)
        case 'transfers/init':
        case 'transfers/list':
        case 'transfers/detail':
        case 'transfers/create':
        case 'transfers/add_item':
        case 'transfers/update_item':
        case 'transfers/remove_item':
        case 'transfers/mark_sent':
        case 'transfers/mark_receiving':
        case 'transfers/receive_all':
        case 'transfers/cancel':
        case 'transfers/add_note':
        case 'transfers/search_products':
        case 'transfers/sync':
            // Route to unified API handler
            require_once __DIR__ . '/unified/index.php';
            exit;

        // Dashboard (default)
        case '':
        default:
            // Show API dashboard if no endpoint specified
            if (class_exists('CIS\Consignments\Services\APIService')) {
                require_once __DIR__ . '/../lib/Services/APIService.php';
                $apiService = new \CIS\Consignments\Services\APIService($db);
                $apiUsage = $apiService->getAPIUsageStats();
                require_once __DIR__ . '/views/dashboard.php';
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Unknown endpoint: ' . $endpoint
                ]);
            }
            exit;
    }

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (BadMethodCallException $e) {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    // Log error
    error_log("API Error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
} finally {
    // ✅ CRITICAL FIX: Always cleanup database connections to prevent connection leaks
    if (isset($db) && $db instanceof mysqli && !empty($db->thread_id)) {
        @$db->close();
    }
    if (isset($pdo)) {
        $pdo = null;
    }
}
