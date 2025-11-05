<?php
/**
 * Transfer Manager Backend API - Standalone Version (No Dependencies)
 * For testing purposes - includes all necessary code inline
 */
declare(strict_types=1);

// Session & error handling
session_start();
header('Content-Type: application/json');
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Simple auth check
function isLoggedIn(): bool {
    return isset($_SESSION['login_id']) && !empty($_SESSION['login_id']);
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true) ?? $_POST;
$action = $data['action'] ?? $_GET['action'] ?? 'unknown';

// Response helper
function sendResponse(bool $success, string $message, $data = null, int $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c'),
        'request_id' => uniqid('req_', true),
        'meta' => [
            'duration_ms' => round((microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000, 2),
            'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . 'MB'
        ]
    ], JSON_PRETTY_PRINT);
    exit;
}

// Error handler
set_exception_handler(function($e) {
    sendResponse(false, 'Internal server error', [
        'error_code' => 'EXCEPTION',
        'error_message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
});

// Auth gate (bypass for localhost/testing)
$isTestMode = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) ||
               strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'PHPUnit') !== false;

if (!$isTestMode && !isLoggedIn()) {
    sendResponse(false, 'Authentication required', null, 401);
}

// Handle actions
switch ($action) {
    case 'init':
        sendResponse(true, 'Configuration loaded', [
            'outlets' => ['Outlet 1', 'Outlet 2'], // Mock data
            'suppliers' => ['Supplier 1', 'Supplier 2'],
            'transfer_types' => ['STOCK', 'JUICE', 'PURCHASE_ORDER', 'INTERNAL', 'RETURN', 'STAFF'],
            'csrf_token' => $_SESSION['tt_csrf'] ?? bin2hex(random_bytes(16)),
            'sync_enabled' => file_exists(__DIR__ . '/.sync_enabled')
        ]);
        break;

    case 'list_transfers':
        $page = (int)($data['page'] ?? 1);
        $limit = (int)($data['limit'] ?? 20);
        $type = $data['type'] ?? null;

        // Mock transfer data
        $mockTransfers = [
            ['id' => 1, 'type' => 'STOCK', 'status' => 'OPEN', 'outlet' => 'Outlet 1'],
            ['id' => 2, 'type' => 'JUICE', 'status' => 'SENT', 'outlet' => 'Outlet 2'],
            ['id' => 3, 'type' => 'PURCHASE_ORDER', 'status' => 'RECEIVED', 'supplier' => 'Supplier 1'],
        ];

        // Filter by type if provided
        if ($type) {
            $mockTransfers = array_filter($mockTransfers, fn($t) => $t['type'] === $type);
            $mockTransfers = array_values($mockTransfers);
        }

        sendResponse(true, 'Transfers retrieved', [
            'transfers' => $mockTransfers,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => count($mockTransfers),
                'pages' => 1
            ]
        ]);
        break;

    case 'search_products':
        $query = $data['query'] ?? '';

        // Mock product data
        $mockProducts = [
            ['id' => 1, 'name' => 'Product A', 'sku' => 'SKU001'],
            ['id' => 2, 'name' => 'Product B', 'sku' => 'SKU002'],
        ];

        // Simple search filter
        if ($query) {
            $mockProducts = array_filter($mockProducts, function($p) use ($query) {
                return stripos($p['name'], $query) !== false || stripos($p['sku'], $query) !== false;
            });
            $mockProducts = array_values($mockProducts);
        }

        sendResponse(true, 'Products found', [
            'products' => $mockProducts,
            'count' => count($mockProducts)
        ]);
        break;

    case 'verify_sync':
        sendResponse(true, 'Sync status retrieved', [
            'sync_enabled' => file_exists(__DIR__ . '/.sync_enabled'),
            'last_check' => date('c')
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action',
            'error' => [
                'code' => 'INVALID_ACTION',
                'message' => 'Unknown action: ' . $action
            ],
            'timestamp' => date('c'),
            'request_id' => uniqid('req_', true),
            'meta' => [
                'duration_ms' => round((microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000, 2),
                'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . 'MB'
            ]
        ], JSON_PRETTY_PRINT);
        exit;
}
