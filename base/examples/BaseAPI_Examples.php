<?php
/**
 * BaseAPI Usage Examples
 *
 * This file demonstrates the full extent of the BaseAPI design pattern
 * with real-world examples showing best practices and advanced features.
 *
 * @package CIS\Base\Examples
 * @version 6.0.0
 */

declare(strict_types=1);

// ============================================================================
// EXAMPLE 1: Simple CRUD API
// ============================================================================

require_once __DIR__ . '/../lib/BaseAPI.php';

use CIS\Base\Lib\BaseAPI;

/**
 * Example 1: Basic User API
 *
 * Demonstrates:
 * - Simple CRUD operations
 * - Field validation
 * - Success/error responses
 */
class UserAPI extends BaseAPI {

    private array $users = []; // Simulated database

    /**
     * Get user by ID
     */
    protected function handleGetUser(array $data): array {
        // Validate required fields
        $this->validateRequired($data, ['user_id']);

        // Validate field types
        $this->validateTypes($data, ['user_id' => 'int']);

        $userId = (int)$data['user_id'];

        if (!isset($this->users[$userId])) {
            return $this->error(
                "User not found with ID: {$userId}",
                'USER_NOT_FOUND',
                [],
                404
            );
        }

        return $this->success(
            $this->users[$userId],
            'User retrieved successfully',
            ['cached' => false]
        );
    }

    /**
     * Create new user
     */
    protected function handleCreateUser(array $data): array {
        // Validate required fields
        $this->validateRequired($data, ['name', 'email']);

        // Validate field types
        $this->validateTypes($data, [
            'name' => 'string',
            'email' => 'email',
            'age' => 'int'
        ]);

        // Sanitize input
        $name = $this->sanitize($data['name']);
        $email = $this->sanitize($data['email']);

        // Check if email exists
        foreach ($this->users as $user) {
            if ($user['email'] === $email) {
                return $this->error(
                    'Email already exists',
                    'DUPLICATE_EMAIL',
                    ['email' => $email],
                    400
                );
            }
        }

        // Create user
        $userId = count($this->users) + 1;
        $this->users[$userId] = [
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'age' => $data['age'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        return $this->success(
            $this->users[$userId],
            'User created successfully',
            ['user_id' => $userId]
        );
    }

    /**
     * Update user
     */
    protected function handleUpdateUser(array $data): array {
        $this->validateRequired($data, ['user_id']);
        $this->validateTypes($data, ['user_id' => 'int']);

        $userId = (int)$data['user_id'];

        if (!isset($this->users[$userId])) {
            return $this->error('User not found', 'USER_NOT_FOUND', [], 404);
        }

        // Update fields if provided
        if (isset($data['name'])) {
            $this->users[$userId]['name'] = $this->sanitize($data['name']);
        }
        if (isset($data['age'])) {
            $this->validateTypes($data, ['age' => 'int']);
            $this->users[$userId]['age'] = (int)$data['age'];
        }

        $this->users[$userId]['updated_at'] = date('Y-m-d H:i:s');

        return $this->success(
            $this->users[$userId],
            'User updated successfully'
        );
    }

    /**
     * Delete user
     */
    protected function handleDeleteUser(array $data): array {
        $this->validateRequired($data, ['user_id']);
        $this->validateTypes($data, ['user_id' => 'int']);

        $userId = (int)$data['user_id'];

        if (!isset($this->users[$userId])) {
            return $this->error('User not found', 'USER_NOT_FOUND', [], 404);
        }

        unset($this->users[$userId]);

        return $this->success(
            null,
            'User deleted successfully',
            ['user_id' => $userId]
        );
    }

    /**
     * List all users
     */
    protected function handleListUsers(array $data): array {
        $page = $data['page'] ?? 1;
        $perPage = $data['per_page'] ?? 10;

        $this->validateTypes($data, [
            'page' => 'int',
            'per_page' => 'int'
        ]);

        return $this->success(
            array_values($this->users),
            'Users listed successfully',
            [
                'total' => count($this->users),
                'page' => $page,
                'per_page' => $perPage
            ]
        );
    }
}

// ============================================================================
// EXAMPLE 2: Advanced API with Authentication
// ============================================================================

/**
 * Example 2: Secure Product API
 *
 * Demonstrates:
 * - Authentication
 * - Authorization
 * - Advanced validation
 * - Business logic
 */
class ProductAPI extends BaseAPI {

    private array $products = [];

    /**
     * Override constructor to require authentication
     */
    public function __construct(array $config = []) {
        // Require authentication for all endpoints
        $config['require_auth'] = true;
        parent::__construct($config);
    }

    /**
     * Override authentication method
     */
    protected function authenticate(): void {
        // Check session
        if (empty($_SESSION['userID'])) {
            throw new \Exception('Not authenticated', 401);
        }

        // Check if user is active
        if (isset($_SESSION['user_status']) && $_SESSION['user_status'] !== 'active') {
            throw new \Exception('Account is not active', 403);
        }

        $this->logInfo('User authenticated', [
            'user_id' => $_SESSION['userID']
        ]);
    }

    /**
     * Create product (requires admin role)
     */
    protected function handleCreateProduct(array $data): array {
        // Check if user is admin
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            return $this->error(
                'Admin privileges required',
                'FORBIDDEN',
                ['required_role' => 'admin'],
                403
            );
        }

        // Validate
        $this->validateRequired($data, ['name', 'price', 'sku']);
        $this->validateTypes($data, [
            'name' => 'string',
            'price' => 'float',
            'sku' => 'string',
            'stock' => 'int'
        ]);

        // Business validation
        if ($data['price'] < 0) {
            return $this->error(
                'Price cannot be negative',
                'INVALID_PRICE',
                ['price' => $data['price']],
                400
            );
        }

        // Check SKU uniqueness
        foreach ($this->products as $product) {
            if ($product['sku'] === $data['sku']) {
                return $this->error(
                    'SKU already exists',
                    'DUPLICATE_SKU',
                    ['sku' => $data['sku']],
                    400
                );
            }
        }

        // Create product
        $productId = count($this->products) + 1;
        $this->products[$productId] = [
            'id' => $productId,
            'name' => $this->sanitize($data['name']),
            'price' => (float)$data['price'],
            'sku' => $this->sanitize($data['sku']),
            'stock' => $data['stock'] ?? 0,
            'created_by' => $_SESSION['userID'],
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->logInfo('Product created', [
            'product_id' => $productId,
            'sku' => $data['sku'],
            'user_id' => $_SESSION['userID']
        ]);

        return $this->success(
            $this->products[$productId],
            'Product created successfully'
        );
    }

    /**
     * Search products
     */
    protected function handleSearchProducts(array $data): array {
        $query = $data['query'] ?? '';
        $minPrice = $data['min_price'] ?? null;
        $maxPrice = $data['max_price'] ?? null;

        $this->validateTypes($data, [
            'query' => 'string',
            'min_price' => 'float',
            'max_price' => 'float'
        ]);

        $results = [];

        foreach ($this->products as $product) {
            // Text search
            if ($query && stripos($product['name'], $query) === false &&
                stripos($product['sku'], $query) === false) {
                continue;
            }

            // Price filter
            if ($minPrice !== null && $product['price'] < $minPrice) {
                continue;
            }
            if ($maxPrice !== null && $product['price'] > $maxPrice) {
                continue;
            }

            $results[] = $product;
        }

        return $this->success(
            $results,
            'Search completed',
            [
                'total_results' => count($results),
                'query' => $query,
                'filters' => [
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice
                ]
            ]
        );
    }
}

// ============================================================================
// EXAMPLE 3: Complex Business Logic API
// ============================================================================

/**
 * Example 3: Order Processing API
 *
 * Demonstrates:
 * - Complex workflows
 * - Transaction management
 * - Error recovery
 * - Detailed logging
 */
class OrderAPI extends BaseAPI {

    private array $orders = [];
    private array $inventory = [];

    /**
     * Create order with inventory validation
     */
    protected function handleCreateOrder(array $data): array {
        $this->validateRequired($data, ['customer_id', 'items']);
        $this->validateTypes($data, [
            'customer_id' => 'int',
            'items' => 'json'
        ]);

        $items = json_decode($data['items'], true);

        if (empty($items)) {
            return $this->error(
                'Order must contain at least one item',
                'EMPTY_ORDER',
                [],
                400
            );
        }

        // Validate inventory for all items
        $reservedItems = [];

        try {
            foreach ($items as $item) {
                if (!isset($item['product_id']) || !isset($item['quantity'])) {
                    throw new \Exception('Each item must have product_id and quantity');
                }

                $productId = $item['product_id'];
                $quantity = $item['quantity'];

                // Check stock
                $available = $this->inventory[$productId] ?? 0;
                if ($available < $quantity) {
                    throw new \Exception(
                        "Insufficient stock for product {$productId}. Available: {$available}, Requested: {$quantity}"
                    );
                }

                // Reserve stock
                $this->inventory[$productId] -= $quantity;
                $reservedItems[] = ['product_id' => $productId, 'quantity' => $quantity];
            }

            // Create order
            $orderId = count($this->orders) + 1;
            $this->orders[$orderId] = [
                'id' => $orderId,
                'customer_id' => $data['customer_id'],
                'items' => $items,
                'status' => 'pending',
                'total' => $this->calculateTotal($items),
                'created_at' => date('Y-m-d H:i:s')
            ];

            $this->logInfo('Order created successfully', [
                'order_id' => $orderId,
                'customer_id' => $data['customer_id'],
                'items_count' => count($items),
                'total' => $this->orders[$orderId]['total']
            ]);

            return $this->success(
                $this->orders[$orderId],
                'Order created successfully',
                ['order_id' => $orderId]
            );

        } catch (\Exception $e) {
            // Rollback reserved inventory
            foreach ($reservedItems as $item) {
                $this->inventory[$item['product_id']] += $item['quantity'];
            }

            $this->logError('Order creation failed', [
                'customer_id' => $data['customer_id'],
                'error' => $e->getMessage()
            ]);

            return $this->error(
                'Order creation failed: ' . $e->getMessage(),
                'ORDER_CREATION_FAILED',
                ['reserved_items' => $reservedItems],
                400
            );
        }
    }

    /**
     * Calculate order total
     */
    private function calculateTotal(array $items): float {
        // Simplified calculation
        return array_reduce($items, function($total, $item) {
            return $total + ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
        }, 0.0);
    }

    /**
     * Update order status with workflow validation
     */
    protected function handleUpdateOrderStatus(array $data): array {
        $this->validateRequired($data, ['order_id', 'status']);
        $this->validateTypes($data, ['order_id' => 'int']);

        $orderId = (int)$data['order_id'];
        $newStatus = $data['status'];

        if (!isset($this->orders[$orderId])) {
            return $this->error('Order not found', 'ORDER_NOT_FOUND', [], 404);
        }

        $currentStatus = $this->orders[$orderId]['status'];

        // Validate status transition
        $allowedTransitions = [
            'pending' => ['processing', 'cancelled'],
            'processing' => ['shipped', 'cancelled'],
            'shipped' => ['delivered'],
            'delivered' => [],
            'cancelled' => []
        ];

        if (!isset($allowedTransitions[$currentStatus])) {
            return $this->error(
                'Invalid current status',
                'INVALID_STATUS',
                ['current_status' => $currentStatus],
                400
            );
        }

        if (!in_array($newStatus, $allowedTransitions[$currentStatus])) {
            return $this->error(
                "Cannot transition from '{$currentStatus}' to '{$newStatus}'",
                'INVALID_TRANSITION',
                [
                    'current_status' => $currentStatus,
                    'requested_status' => $newStatus,
                    'allowed_transitions' => $allowedTransitions[$currentStatus]
                ],
                400
            );
        }

        // Update status
        $this->orders[$orderId]['status'] = $newStatus;
        $this->orders[$orderId]['status_updated_at'] = date('Y-m-d H:i:s');

        $this->logInfo('Order status updated', [
            'order_id' => $orderId,
            'old_status' => $currentStatus,
            'new_status' => $newStatus
        ]);

        return $this->success(
            $this->orders[$orderId],
            "Order status updated to: {$newStatus}"
        );
    }
}

// ============================================================================
// EXAMPLE 4: File Upload API
// ============================================================================

/**
 * Example 4: File Upload API
 *
 * Demonstrates:
 * - File handling
 * - Size validation
 * - Type validation
 * - Security checks
 */
class FileAPI extends BaseAPI {

    private string $uploadPath = '/tmp/uploads';

    public function __construct(array $config = []) {
        parent::__construct($config);

        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }

    /**
     * Upload file with validation
     */
    protected function handleUploadFile(array $data): array {
        if (empty($_FILES['file'])) {
            return $this->error(
                'No file uploaded',
                'NO_FILE',
                [],
                400
            );
        }

        $file = $_FILES['file'];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $this->error(
                'File upload error: ' . $this->getUploadErrorMessage($file['error']),
                'UPLOAD_ERROR',
                ['error_code' => $file['error']],
                400
            );
        }

        // Validate file size (5MB max)
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return $this->error(
                'File too large. Max: ' . $this->formatBytes($maxSize),
                'FILE_TOO_LARGE',
                [
                    'file_size' => $this->formatBytes($file['size']),
                    'max_size' => $this->formatBytes($maxSize)
                ],
                400
            );
        }

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            return $this->error(
                'File type not allowed',
                'INVALID_FILE_TYPE',
                [
                    'detected_type' => $mimeType,
                    'allowed_types' => $allowedTypes
                ],
                400
            );
        }

        // Generate safe filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeFilename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $destination = $this->uploadPath . '/' . $safeFilename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return $this->error(
                'Failed to save file',
                'SAVE_FAILED',
                [],
                500
            );
        }

        $fileInfo = [
            'filename' => $safeFilename,
            'original_name' => $file['name'],
            'size' => $file['size'],
            'size_formatted' => $this->formatBytes($file['size']),
            'mime_type' => $mimeType,
            'uploaded_at' => date('Y-m-d H:i:s'),
            'path' => $destination
        ];

        $this->logInfo('File uploaded successfully', $fileInfo);

        return $this->success(
            $fileInfo,
            'File uploaded successfully'
        );
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];

        return $errors[$errorCode] ?? 'Unknown error';
    }
}

// ============================================================================
// USAGE EXAMPLES
// ============================================================================

/*
// Example 1: Simple User API
$userAPI = new UserAPI();
$_POST['action'] = 'create_user';
$_POST['name'] = 'John Doe';
$_POST['email'] = 'john@example.com';
$_POST['age'] = '30';
$userAPI->handleRequest();

// Example 2: Authenticated Product API
session_start();
$_SESSION['userID'] = 123;
$_SESSION['user_role'] = 'admin';
$_SESSION['user_status'] = 'active';

$productAPI = new ProductAPI();
$_POST['action'] = 'create_product';
$_POST['name'] = 'Widget';
$_POST['price'] = '19.99';
$_POST['sku'] = 'WDG-001';
$_POST['stock'] = '100';
$productAPI->handleRequest();

// Example 3: Complex Order API
$orderAPI = new OrderAPI();
$_POST['action'] = 'create_order';
$_POST['customer_id'] = '456';
$_POST['items'] = json_encode([
    ['product_id' => 1, 'quantity' => 2, 'price' => 19.99],
    ['product_id' => 2, 'quantity' => 1, 'price' => 29.99]
]);
$orderAPI->handleRequest();

// Example 4: File Upload
$fileAPI = new FileAPI();
$_POST['action'] = 'upload_file';
$_FILES['file'] = [
    'name' => 'document.pdf',
    'type' => 'application/pdf',
    'tmp_name' => '/tmp/phpXXXXXX',
    'error' => UPLOAD_ERR_OK,
    'size' => 102400
];
$fileAPI->handleRequest();
*/
