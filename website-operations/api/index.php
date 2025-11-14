<?php
/**
 * Website Operations API - Main Router
 *
 * Complete REST API for VapeShed and Ecigdis integration
 * Replaces the missing API system with enterprise-grade endpoints
 *
 * @package    WebsiteOperations
 * @version    2.0.0
 * @author     Ecigdis Development Team
 */

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../services/WebsiteOperationsService.php';

use Modules\WebsiteOperations\Services\WebsiteOperationsService;

// Initialize
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database connection
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Initialize service
$config = json_decode(file_get_contents(__DIR__ . '/../module.json'), true);
$service = new WebsiteOperationsService($db, $config);

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? $_GET['endpoint'] ?? '/';
$path = trim($path, '/');
$segments = explode('/', $path);

// Authentication (basic API key check)
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
if (!validateApiKey($apiKey)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized - Invalid API key']);
    exit;
}

// Route request
try {
    $response = routeRequest($service, $method, $segments);
    http_response_code($response['code'] ?? 200);
    echo json_encode($response['data'] ?? $response);
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Route API request to appropriate handler
 */
function routeRequest(WebsiteOperationsService $service, string $method, array $segments): array
{
    $resource = $segments[0] ?? 'health';
    $id = $segments[1] ?? null;
    $action = $segments[2] ?? null;

    // Health check endpoint
    if ($resource === 'health') {
        return ['code' => 200, 'data' => ['status' => 'ok', 'timestamp' => date('c')]];
    }

    // Route to resource handlers
    switch ($resource) {
        case 'orders':
            return handleOrders($service, $method, $id, $action);

        case 'products':
            return handleProducts($service, $method, $id, $action);

        case 'customers':
            return handleCustomers($service, $method, $id, $action);

        case 'inventory':
            return handleInventory($service, $method, $id, $action);

        case 'wholesale':
            return handleWholesale($service, $method, $id, $action);

        case 'dashboard':
            return handleDashboard($service, $method, $id, $action);

        case 'shipping':
            return handleShipping($service, $method, $id, $action);

        default:
            return ['code' => 404, 'data' => ['error' => 'Endpoint not found']];
    }
}

/**
 * Handle orders endpoints
 */
function handleOrders(WebsiteOperationsService $service, string $method, $id, $action): array
{
    switch ($method) {
        case 'GET':
            if ($id) {
                $order = $service->orders()->getOrderById((int)$id);
                return $order ?
                    ['code' => 200, 'data' => $order] :
                    ['code' => 404, 'data' => ['error' => 'Order not found']];
            }

            $filters = [
                'status' => $_GET['status'] ?? null,
                'channel' => $_GET['channel'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null
            ];

            $orders = $service->orders()->getRecentOrders(
                (int)($_GET['limit'] ?? 20),
                $_GET['outlet'] ?? 'all',
                $filters
            );

            return ['code' => 200, 'data' => $orders];

        case 'POST':
            $orderData = json_decode(file_get_contents('php://input'), true);
            $result = $service->orders()->createOrder($orderData);

            return $result['success'] ?
                ['code' => 201, 'data' => $result] :
                ['code' => 400, 'data' => $result];

        case 'PUT':
            if (!$id) {
                return ['code' => 400, 'data' => ['error' => 'Order ID required']];
            }

            $updates = json_decode(file_get_contents('php://input'), true);

            if (isset($updates['status'])) {
                $success = $service->orders()->updateOrderStatus(
                    (int)$id,
                    $updates['status'],
                    $updates['notes'] ?? ''
                );

                return $success ?
                    ['code' => 200, 'data' => ['success' => true]] :
                    ['code' => 500, 'data' => ['error' => 'Update failed']];
            }

            return ['code' => 400, 'data' => ['error' => 'No valid updates provided']];

        default:
            return ['code' => 405, 'data' => ['error' => 'Method not allowed']];
    }
}

/**
 * Handle products endpoints
 */
function handleProducts(WebsiteOperationsService $service, string $method, $id, $action): array
{
    switch ($method) {
        case 'GET':
            if ($id && $action === 'sync') {
                $channel = $_GET['channel'] ?? 'vapeshed';
                $result = $service->products()->syncProductToChannel((int)$id, $channel);
                return ['code' => 200, 'data' => $result];
            }

            if ($id) {
                $product = $service->products()->getProductById((int)$id);
                return $product ?
                    ['code' => 200, 'data' => $product] :
                    ['code' => 404, 'data' => ['error' => 'Product not found']];
            }

            $filters = [
                'search' => $_GET['search'] ?? null,
                'category' => $_GET['category'] ?? null,
                'status' => $_GET['status'] ?? null,
                'stock' => $_GET['stock'] ?? null,
                'channel' => $_GET['channel'] ?? null
            ];

            $page = (int)($_GET['page'] ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 50);

            $result = $service->products()->getProducts($filters, $page, $perPage);
            return ['code' => 200, 'data' => $result];

        case 'POST':
            $productData = json_decode(file_get_contents('php://input'), true);
            $result = $service->products()->createProduct($productData);

            return $result['success'] ?
                ['code' => 201, 'data' => $result] :
                ['code' => 400, 'data' => $result];

        case 'PUT':
            if (!$id) {
                return ['code' => 400, 'data' => ['error' => 'Product ID required']];
            }

            $updates = json_decode(file_get_contents('php://input'), true);
            $success = $service->products()->updateProduct((int)$id, $updates);

            return $success ?
                ['code' => 200, 'data' => ['success' => true]] :
                ['code' => 500, 'data' => ['error' => 'Update failed']];

        case 'DELETE':
            if (!$id) {
                return ['code' => 400, 'data' => ['error' => 'Product ID required']];
            }

            $success = $service->products()->deleteProduct((int)$id);

            return $success ?
                ['code' => 200, 'data' => ['success' => true]] :
                ['code' => 500, 'data' => ['error' => 'Delete failed']];

        default:
            return ['code' => 405, 'data' => ['error' => 'Method not allowed']];
    }
}

/**
 * Handle customers endpoints
 */
function handleCustomers(WebsiteOperationsService $service, string $method, $id, $action): array
{
    switch ($method) {
        case 'GET':
            if ($id) {
                $customer = $service->customers()->getCustomerById((int)$id);
                return $customer ?
                    ['code' => 200, 'data' => $customer] :
                    ['code' => 404, 'data' => ['error' => 'Customer not found']];
            }

            $filters = [
                'search' => $_GET['search'] ?? null,
                'is_wholesale' => $_GET['is_wholesale'] ?? null
            ];

            $page = (int)($_GET['page'] ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 50);

            $customers = $service->customers()->getCustomers($filters, $page, $perPage);
            return ['code' => 200, 'data' => $customers];

        case 'POST':
            $customerData = json_decode(file_get_contents('php://input'), true);
            $result = $service->customers()->createCustomer($customerData);

            return $result['success'] ?
                ['code' => 201, 'data' => $result] :
                ['code' => 400, 'data' => $result];

        default:
            return ['code' => 405, 'data' => ['error' => 'Method not allowed']];
    }
}

/**
 * Handle inventory endpoints
 */
function handleInventory(WebsiteOperationsService $service, string $method, $id, $action): array
{
    // Future implementation - integrated with Vend API
    return ['code' => 501, 'data' => ['message' => 'Inventory endpoints coming soon']];
}

/**
 * Handle wholesale endpoints
 */
function handleWholesale(WebsiteOperationsService $service, string $method, $id, $action): array
{
    switch ($method) {
        case 'GET':
            $filters = ['status' => $_GET['status'] ?? null];
            $accounts = $service->wholesale()->getWholesaleAccounts($filters);
            return ['code' => 200, 'data' => $accounts];

        case 'PUT':
            if (!$id || $action !== 'approve') {
                return ['code' => 400, 'data' => ['error' => 'Invalid request']];
            }

            $success = $service->wholesale()->approveAccount((int)$id);
            return $success ?
                ['code' => 200, 'data' => ['success' => true]] :
                ['code' => 500, 'data' => ['error' => 'Approval failed']];

        default:
            return ['code' => 405, 'data' => ['error' => 'Method not allowed']];
    }
}

/**
 * Handle dashboard endpoints
 */
function handleDashboard(WebsiteOperationsService $service, string $method, $id, $action): array
{
    if ($method !== 'GET') {
        return ['code' => 405, 'data' => ['error' => 'Method not allowed']];
    }

    $filters = [
        'date_range' => $_GET['date_range'] ?? '30d',
        'outlet' => $_GET['outlet'] ?? 'all'
    ];

    $data = $service->getDashboardData($filters);
    return ['code' => 200, 'data' => $data];
}

/**
 * Handle shipping endpoints
 */
function handleShipping(WebsiteOperationsService $service, string $method, $id, $action): array
{
    if ($method !== 'GET') {
        return ['code' => 405, 'data' => ['error' => 'Method not allowed']];
    }

    if ($action === 'savings') {
        $days = (int)($_GET['days'] ?? 30);
        $report = $service->orders()->getTotalShippingSavings($days);
        return ['code' => 200, 'data' => ['savings' => $report]];
    }

    return ['code' => 404, 'data' => ['error' => 'Endpoint not found']];
}

/**
 * Validate API key
 */
function validateApiKey(?string $key): bool
{
    if (!$key) return false;

    // Load valid API keys from config
    $validKeys = [
        getenv('VAPESHED_API_KEY'),
        getenv('ECIGDIS_API_KEY'),
        getenv('INTERNAL_API_KEY')
    ];

    return in_array($key, $validKeys);
}
