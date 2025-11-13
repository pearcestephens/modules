<?php
/**
 * Products API Endpoint
 *
 * Handles product search, listing, and catalog browsing with real-time pricing
 * Integrates with Lightspeed API for product data
 * Applies staff discount calculations automatically
 *
 * @package StaffAccounts
 * @version 1.0.0
 */

declare(strict_types=1);

// Bootstrap
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lightspeed-api.php';

// Authentication required
requireAuthentication();

// Get authenticated staff
$staff = getCurrentStaff();

// CORS headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get request parameters
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 50), 100); // Max 100 products
    $offset = (int)($_GET['offset'] ?? 0);
    $discount_type = $_GET['discount_type'] ?? 'staff';

    // Validate discount type
    if (!in_array($discount_type, ['staff', 'friends', 'family'])) {
        throw new InvalidArgumentException('Invalid discount type');
    }

    // Get discount rate for this staff member and type
    $discount_rate = getStaffDiscountRate($staff['id'], $discount_type);

    // Initialize Lightspeed API
    $lightspeed = new LightspeedAPI();

    // Build query parameters for Lightspeed
    $query_params = [
        'limit' => $limit,
        'offset' => $offset
    ];

    // Add search filter
    if (!empty($search)) {
        // Search in name, SKU, brand
        $query_params['name'] = '~' . $search; // ~ prefix for contains search
    }

    // Add category filter
    if (!empty($category) && $category !== 'all') {
        $query_params['category_id'] = $category;
    }

    // Fetch products from Lightspeed
    $products_response = $lightspeed->get('products', $query_params);

    if (!$products_response['success']) {
        throw new RuntimeException('Failed to fetch products from Lightspeed: ' . $products_response['error']);
    }

    $products = $products_response['data']['products'] ?? [];

    // Process products and apply discounts
    $processed_products = array_map(function($product) use ($discount_rate) {
        $retail_price = (float)($product['retail_price'] ?? 0);
        $staff_price = $retail_price * (1 - ($discount_rate / 100));

        return [
            'id' => $product['id'],
            'name' => $product['name'],
            'sku' => $product['sku'] ?? '',
            'brand' => $product['brand_name'] ?? '',
            'description' => $product['description'] ?? '',
            'category' => [
                'id' => $product['category_id'] ?? null,
                'name' => $product['category_name'] ?? ''
            ],
            'image_url' => $product['image_url'] ?? '/assets/images/no-image.jpg',
            'retail_price' => $retail_price,
            'staff_price' => round($staff_price, 2),
            'discount_rate' => $discount_rate,
            'discount_amount' => round($retail_price - $staff_price, 2),
            'stock_available' => (int)($product['inventory'] ?? 0),
            'in_stock' => ($product['inventory'] ?? 0) > 0,
            'supplier' => $product['supplier_name'] ?? '',
            'tags' => $product['tags'] ?? []
        ];
    }, $products);

    // Get pagination info
    $total_count = $products_response['data']['total_count'] ?? count($processed_products);
    $has_more = ($offset + $limit) < $total_count;

    // Build response
    $response = [
        'success' => true,
        'data' => [
            'products' => $processed_products,
            'discount' => [
                'type' => $discount_type,
                'rate' => $discount_rate,
                'staff_id' => $staff['id'],
                'staff_name' => $staff['first_name'] . ' ' . $staff['last_name']
            ],
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total_count,
                'has_more' => $has_more,
                'returned' => count($processed_products)
            ],
            'filters' => [
                'search' => $search,
                'category' => $category
            ]
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // Cache response for 5 minutes
    header('Cache-Control: private, max-age=300');

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => 'INVALID_REQUEST'
    ]);

} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => 'API_ERROR'
    ]);

    // Log error
    error_log(sprintf(
        '[STAFF_ACCOUNTS] Product API Error: %s | Staff: %d | Search: %s',
        $e->getMessage(),
        $staff['id'] ?? 0,
        $search
    ));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An unexpected error occurred',
        'error_code' => 'INTERNAL_ERROR'
    ]);

    // Log error
    error_log(sprintf(
        '[STAFF_ACCOUNTS] Unexpected Error: %s | File: %s | Line: %d',
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
}
