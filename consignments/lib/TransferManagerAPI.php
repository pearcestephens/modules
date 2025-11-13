<?php
/**
 * TransferManagerAPI - Enterprise-Grade Transfer Management API
 *
 * Extends BaseAPI to provide standardized endpoints for transfer operations.
 * Handles outlet transfers, supplier orders, stock movements, and Lightspeed sync.
 * Follows BASE module envelope design pattern.
 *
 * @package CIS\Consignments\Lib
 * @version 2.0.0
 * @author CIS Development Team
 * @created 2025-11-04
 */

declare(strict_types=1);

namespace CIS\Consignments\Lib;

require_once __DIR__ . '/../../base/lib/BaseAPI.php';
require_once __DIR__ . '/Services/TransferService.php';
require_once __DIR__ . '/Services/ProductService.php';
require_once __DIR__ . '/Services/ConfigService.php';
require_once __DIR__ . '/Services/SyncService.php';

use CIS\Base\Lib\BaseAPI;
use CIS\Consignments\Lib\Services\TransferService;
use CIS\Consignments\Lib\Services\ProductService;
use CIS\Consignments\Lib\Services\ConfigService;
use CIS\Consignments\Lib\Services\SyncService;
use mysqli;
use RuntimeException;

class TransferManagerAPI extends BaseAPI {

    /**
     * Transfer service
     * @var TransferService
     */
    private TransferService $transferService;

    /**
     * Product service
     * @var ProductService
     */
    private ProductService $productService;

    /**
     * Configuration service
     * @var ConfigService
     */
    private ConfigService $configService;

    /**
     * Sync service
     * @var SyncService
     */
    private SyncService $syncService;

    /**
     * Database connection (legacy - being phased out)
     * @var mysqli
     */
    private mysqli $db;

    /**
     * Sync state file path
     * @var string
     */
    private string $syncFile;

    /**
     * Lightspeed API token
     * @var string
     */
    private string $lsToken;

    /**
     * Constructor
     *
     * @param array $config Configuration options
     */
    public function __construct(array $config = []) {
        // Configure API settings
        $apiConfig = array_merge([
            'require_auth' => true, // All transfer operations require authentication
            'allowed_methods' => ['POST', 'GET'],
            'log_requests' => true,
            'log_responses' => true,
            'timezone' => 'Pacific/Auckland'
        ], $config);

        parent::__construct($apiConfig);

        // Initialize services
        $this->transferService = TransferService::make();
        $this->productService = ProductService::make();
        $this->configService = ConfigService::make();
        $this->syncService = SyncService::make();

        // Initialize database connection (legacy - keeping for now)
        $this->initializeDatabase();

        // Set sync file path (deprecated - now handled by SyncService)
        $this->syncFile = __DIR__ . '/../.sync_enabled';

        // Get Lightspeed token (deprecated - now handled by SyncService)
        $this->lsToken = $_ENV['LS_API_TOKEN'] ?? '';
    }

    /**
     * Initialize database connection
     */
    private function initializeDatabase(): void {
        $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
        $user = defined('DB_USERNAME') ? DB_USERNAME : (defined('DB_USER') ? DB_USER : 'jcepnzzkmj');
        $pass = defined('DB_PASSWORD') ? DB_PASSWORD : (defined('DB_PASS') ? DB_PASS : '');
        $name = defined('DB_DATABASE') ? DB_DATABASE : (defined('DB_NAME') ? DB_NAME : 'jcepnzzkmj');

        $this->db = new mysqli($host, $user, $pass, $name);

        if ($this->db->connect_error) {
            throw new RuntimeException('Database connection failed: ' . $this->db->connect_error);
        }

        $this->db->set_charset('utf8mb4');
    }

    /**
     * Cleanup database connection on object destruction
     * ✅ CRITICAL FIX: Prevents connection leaks
     */
    public function __destruct() {
        if ($this->db instanceof mysqli && !empty($this->db->thread_id)) {
            @$this->db->close();
        }
    }

    // ========================================================================
    // INITIALIZATION & CONFIGURATION
    // ========================================================================

    /**
     * Initialize - Get configuration and sync state
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleInit(array $data): array {
        // Get sync state from service
        $syncEnabled = $this->syncService->isEnabled();

        // Get outlets from service
        $outlets = $this->configService->getOutlets(true);

        // Get suppliers from service
        $suppliers = $this->configService->getSuppliers(true);

        // Get CSRF token from service
        $csrf = $this->configService->getCsrfToken();

        // Get current user from service
        $user = $this->configService->getCurrentUser();

        return $this->success([
            'csrf' => $csrf,
            'sync_enabled' => $syncEnabled,
            'outlets' => $outlets,
            'suppliers' => $suppliers,
            'user_id' => $user['id'] ?? null,
            'ls_token_set' => $this->syncService->hasToken()
        ], 'Initialization successful');
    }

    /**
     * Toggle Lightspeed sync
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleToggleSync(array $data): array {
        $this->validateCSRF($data);
        $this->validateRequired($data, ['enabled']);

        $enabled = filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN);

        // Update sync state via service
        $this->syncService->toggle($enabled);

        $this->logInfo('Sync state updated', [
            'enabled' => $enabled,
            'user_id' => $_SESSION['user_id'] ?? null
        ]);

        return $this->success([
            'sync_enabled' => $enabled,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'Sync state updated successfully');
    }

    /**
     * Verify sync status
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleVerifySync(array $data): array {
        // Get comprehensive sync status from service
        $status = $this->syncService->getStatus();

        return $this->success($status, 'Sync status retrieved');
    }

    // ========================================================================
    // TRANSFER LISTING & SEARCH
    // ========================================================================

    /**
     * List transfers with pagination and filtering
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleListTransfers(array $data): array {
        $page = $this->validateInt($data, 'page', 1, 1);
        $perPage = $this->validateInt($data, 'perPage', 25, 1, 100);

        // Build filters from request
        $filters = array_filter([
            'type' => $data['type'] ?? null,
            'state' => $data['state'] ?? null,
            'outlet' => isset($data['outlet']) ? (int)$data['outlet'] : null,
            'q' => $data['q'] ?? ''
        ], fn($v) => $v !== null && $v !== '');

        // Use service to get transfers
        $result = $this->transferService->list($filters, $page, $perPage);

        return $this->success(
            $result['transfers'],
            'Transfers retrieved successfully',
            [
                'pagination' => $result['pagination'],
                'filters' => $filters
            ]
        );
    }

    /**
     * Get transfer detail with items
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleGetTransferDetail(array $data): array {
        $this->validateRequired($data, ['id']);
        $id = $this->validateInt($data, 'id');

        // Use service to get transfer with items and notes
        try {
            $transfer = $this->transferService->getById($id);
        } catch (\RuntimeException $e) {
            return $this->error(
                "Transfer not found with ID: {$id}",
                'NOT_FOUND',
                ['id' => $id],
                404
            );
        }

        return $this->success($transfer, 'Transfer detail retrieved successfully');
    }

    /**
     * Search products for adding to transfer
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleSearchProducts(array $data): array {
        $this->validateRequired($data, ['q']);

        $q = $this->validateString($data, 'q', '', 2, 100);
        $limit = $this->validateInt($data, 'limit', 30, 1, 100);
        $outletId = isset($data['outlet_id']) ? $this->validateInt($data, 'outlet_id') : null;

        // Use service to search products
        try {
            $products = $this->productService->search($q, $limit, $outletId);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', ['query' => $q], 400);
        }

        return $this->success([
            'results' => $products,
            'count' => count($products),
            'query' => $q
        ], 'Product search completed');
    }

    // ========================================================================
    // TRANSFER CREATION & MANAGEMENT
    // ========================================================================

    /**
     * Create new transfer
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleCreateTransfer(array $data): array {
        $this->validateCSRF($data);
        $this->validateRequired($data, ['consignment_category', 'outlet_from', 'outlet_to']);

        // Build payload for service
        $payload = [
            'transfer_category' => $this->validateString($data, 'consignment_category'),
            'source_outlet_id' => $this->validateInt($data, 'outlet_from'),
            'destination_outlet_id' => $this->validateInt($data, 'outlet_to'),
            'supplier_id' => isset($data['supplier_id']) ? $this->validateInt($data, 'supplier_id') : null,
            'cis_user_id' => $_SESSION['user_id'] ?? 1
        ];

        // Use service to create transfer
        try {
            $transferId = $this->transferService->create($payload);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', $payload, 422);
        } catch (\RuntimeException $e) {
            return $this->error('Failed to create transfer', 'CREATE_FAILED', ['error' => $e->getMessage()], 500);
        }

        $this->logInfo('Transfer created', [
            'transfer_id' => $transferId,
            'category' => $payload['transfer_category'],
            'user_id' => $payload['cis_user_id']
        ]);

        // Get created transfer
        return $this->handleGetTransferDetail(['id' => $transferId]);
    }

    /**
     * Add item to transfer
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleAddTransferItem(array $data): array {
        $this->validateCSRF($data);
        $this->validateRequired($data, ['id', 'product_id', 'qty']);

        $transferId = $this->validateInt($data, 'id');

        // Build item payload
        $item = [
            'vend_product_id' => $this->validateInt($data, 'product_id'),
            'count_ordered' => $this->validateInt($data, 'qty', null, 1)
        ];

        // Use service to add item
        try {
            $itemId = $this->transferService->addItem($transferId, $item);
        } catch (\RuntimeException $e) {
            return $this->error('Failed to add item to transfer', 'ADD_ITEM_FAILED', ['error' => $e->getMessage()], 500);
        }

        return $this->success([
            'item_id' => $itemId,
            'action' => 'created',
            'qty_requested' => $item['count_ordered']
        ], 'Item added to transfer', ['http_code' => 201]);
    }

    /**
     * Update transfer item quantity
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleUpdateTransferItem(array $data): array {
        $this->validateCSRF($data);
        $this->validateRequired($data, ['id', 'item_id', 'qty_requested']);

        $transferId = $this->validateInt($data, 'id');
        $itemId = $this->validateInt($data, 'item_id');
        $qtyRequested = $this->validateInt($data, 'qty_requested', null, 0);

        // Use service to update item
        try {
            $updated = $this->transferService->updateItem($itemId, $transferId, ['count_ordered' => $qtyRequested]);
        } catch (\RuntimeException $e) {
            return $this->error('Item not found or no changes made', 'UPDATE_FAILED', ['item_id' => $itemId], 404);
        }

        return $this->success([
            'item_id' => $itemId,
            'qty_requested' => $qtyRequested,
            'updated' => $updated
        ], 'Item quantity updated');
    }

    /**
     * Remove item from transfer
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleRemoveTransferItem(array $data): array {
        $this->validateCSRF($data);
        $this->validateRequired($data, ['item_id']);

        $itemId = $this->validateInt($data, 'item_id');

        // Use service to delete item
        try {
            $deleted = $this->transferService->deleteItem($itemId);
        } catch (\RuntimeException $e) {
            return $this->error('Item not found', 'NOT_FOUND', ['item_id' => $itemId], 404);
        }

        return $this->success([
            'item_id' => $itemId,
            'deleted' => $deleted
        ], 'Item removed from transfer');
    }

    /**
     * Mark transfer as sent
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleMarkSent(array $data): array {
        $this->validateCSRF($data);
        $this->validateRequired($data, ['id']);

        $id = $this->validateInt($data, 'id');

        // Use service to update status
        try {
            $this->transferService->updateStatus($id, 'SENT');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', ['id' => $id], 422);
        } catch (\RuntimeException $e) {
            return $this->error('Transfer not found or not in valid status', 'UPDATE_FAILED', ['id' => $id], 422);
        }

        $this->logInfo('Transfer marked as sent', ['transfer_id' => $id]);

        return $this->success([
            'id' => $id,
            'status' => 'SENT',
            'sent_at' => date('Y-m-d H:i:s')
        ], 'Transfer marked as sent');
    }

    /**
     * Add note to transfer
     *
     * @param array $data Request data
     * @return array Response envelope
     */
    protected function handleAddNote(array $data): array {
        $this->validateCSRF($data);
        $this->validateRequired($data, ['id', 'note_text']);

        $id = $this->validateInt($data, 'id');
        $noteText = $this->validateString($data, 'note_text', null, 1, 1000);
        $userId = $_SESSION['user_id'] ?? 1;

        // Use service to add note
        try {
            $noteId = $this->transferService->addNote($id, $noteText, $userId);
        } catch (\RuntimeException $e) {
            return $this->error('Failed to add note', 'CREATE_FAILED', ['error' => $e->getMessage()], 500);
        }

        return $this->success([
            'note_id' => $noteId,
            'transfer_id' => $id,
            'created_at' => date('Y-m-d H:i:s')
        ], 'Note added successfully', ['http_code' => 201]);
    }

    // ========================================================================
    // HELPER METHODS (Validation)
    // ========================================================================

    /**
     * Get suppliers from database
     *
     * @return array
     * @deprecated Use ConfigService::getSuppliers() instead
     */
    private function getSuppliers(): array {
        $sql = "SELECT id, supplier_name, supplier_code FROM suppliers WHERE active = 1 ORDER BY supplier_name";
        $result = $this->db->query($sql);

        $suppliers = [];
        while ($row = $result->fetch_assoc()) {
            $suppliers[] = $row;
        }

        return $suppliers;
    }

    /**
     * Validate CSRF token
     *
     * @param array $data Request data
     * @throws \Exception
     */
    private function validateCSRF(array $data): void {
        $token = $data['csrf'] ?? '';

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $valid = isset($_SESSION['tt_csrf']) && hash_equals($_SESSION['tt_csrf'], (string)$token);

        if (!$valid) {
            $this->logWarning('CSRF validation failed', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);

            throw new \Exception('Invalid CSRF token');
        }
    }

    /**
     * Validate integer parameter
     */
    private function validateInt(
        array $data,
        string $key,
        ?int $default = null,
        ?int $min = null,
        ?int $max = null,
        bool $nullable = false
    ): ?int {
        if (!isset($data[$key])) {
            if ($nullable || $default !== null) {
                return $default;
            }
            throw new \Exception("Missing required parameter: {$key}");
        }

        $value = filter_var($data[$key], FILTER_VALIDATE_INT);

        if ($value === false) {
            throw new \Exception("Invalid integer value for parameter: {$key}");
        }

        if ($min !== null && $value < $min) {
            throw new \Exception("{$key} must be at least {$min}");
        }

        if ($max !== null && $value > $max) {
            throw new \Exception("{$key} must be at most {$max}");
        }

        return $value;
    }

    /**
     * Validate string parameter
     */
    private function validateString(
        array $data,
        string $key,
        ?string $default = null,
        ?int $minLength = null,
        ?int $maxLength = null
    ): ?string {
        if (!isset($data[$key])) {
            return $default;
        }

        $value = (string)$data[$key];

        if ($minLength !== null && strlen($value) < $minLength) {
            throw new \Exception("{$key} must be at least {$minLength} characters");
        }

        if ($maxLength !== null && strlen($value) > $maxLength) {
            throw new \Exception("{$key} must be at most {$maxLength} characters");
        }

        return $value;
    }

    /**
     * Validate required fields
     */
    private function validateRequired(array $data, array $required): void {
        $missing = [];

        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new \Exception('Missing required fields: ' . implode(', ', $missing));
        }
    }
}
