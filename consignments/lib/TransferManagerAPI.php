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

use CIS\Base\Lib\BaseAPI;
use mysqli;
use RuntimeException;

class TransferManagerAPI extends BaseAPI {

    /**
     * Database connection
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

        // Initialize database connection
        $this->initializeDatabase();

        // Set sync file path
        $this->syncFile = __DIR__ . '/../.sync_enabled';

        // Get Lightspeed token
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
        // Get sync state
        $syncEnabled = $this->getSyncState();

        // Get outlets
        $outlets = $this->getOutlets();

        // Get suppliers
        $suppliers = $this->getSuppliers();

        // Get CSRF token
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (!isset($_SESSION['tt_csrf'])) {
            $_SESSION['tt_csrf'] = bin2hex(random_bytes(32));
        }

        return $this->success([
            'csrf' => $_SESSION['tt_csrf'],
            'sync_enabled' => $syncEnabled,
            'outlets' => $outlets,
            'suppliers' => $suppliers,
            'user_id' => $_SESSION['user_id'] ?? null,
            'ls_token_set' => !empty($this->lsToken)
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

        // Write sync state to file
        $result = file_put_contents($this->syncFile, $enabled ? '1' : '0');

        if ($result === false) {
            return $this->error(
                'Failed to update sync state',
                'SYNC_UPDATE_FAILED',
                ['file' => $this->syncFile],
                500
            );
        }

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
        $syncEnabled = $this->getSyncState();

        return $this->success([
            'sync_enabled' => $syncEnabled,
            'ls_token_set' => !empty($this->lsToken),
            'sync_file_exists' => file_exists($this->syncFile),
            'sync_file_writable' => is_writable(dirname($this->syncFile))
        ], 'Sync status retrieved');
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
        $type = $data['type'] ?? null;
        $state = $data['state'] ?? null;
        $outlet = isset($data['outlet']) ? (int)$data['outlet'] : null;
        $q = $data['q'] ?? '';

        $offset = ($page - 1) * $perPage;

        // Build query
        $sql = "SELECT t.*,
                       o_from.outlet_name as from_name,
                       o_to.outlet_name as to_name,
                       s.supplier_name,
                       (SELECT COUNT(*) FROM consignment_items ci WHERE ci.consignment_id = t.id) as item_count,
                       (SELECT SUM(ci.qty_requested) FROM consignment_items ci WHERE ci.consignment_id = t.id) as total_qty
                FROM transfers t
                LEFT JOIN outlets o_from ON t.outlet_from = o_from.id
                LEFT JOIN outlets o_to ON t.outlet_to = o_to.id
                LEFT JOIN suppliers s ON t.supplier_id = s.id
                WHERE 1=1";

        $params = [];
        $types = '';

        if ($type) {
            $sql .= " AND t.consignment_category = ?";
            $params[] = $type;
            $types .= 's';
        }

        if ($state) {
            $sql .= " AND t.status = ?";
            $params[] = $state;
            $types .= 's';
        }

        if ($outlet) {
            $sql .= " AND (t.outlet_from = ? OR t.outlet_to = ?)";
            $params[] = $outlet;
            $params[] = $outlet;
            $types .= 'ii';
        }

        if ($q !== '') {
            $sql .= " AND (t.vend_consignment_number LIKE ? OR t.notes LIKE ?)";
            $searchTerm = "%{$q}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as filtered";
        $stmt = $this->db->prepare($countSql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];

        // Get paginated results
        $sql .= " ORDER BY t.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $transfers = [];
        while ($row = $result->fetch_assoc()) {
            $transfers[] = $row;
        }

        return $this->success(
            $transfers,
            'Transfers retrieved successfully',
            [
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage)
                ],
                'filters' => array_filter([
                    'type' => $type,
                    'state' => $state,
                    'outlet' => $outlet,
                    'search' => $q
                ])
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

        // Get transfer
        $sql = "SELECT t.*,
                       o_from.outlet_name as from_name,
                       o_to.outlet_name as to_name,
                       s.supplier_name,
                       u.name as created_by_name
                FROM transfers t
                LEFT JOIN outlets o_from ON t.outlet_from = o_from.id
                LEFT JOIN outlets o_to ON t.outlet_to = o_to.id
                LEFT JOIN suppliers s ON t.supplier_id = s.id
                LEFT JOIN users u ON t.created_by = u.id
                WHERE t.id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $transfer = $stmt->get_result()->fetch_assoc();

        if (!$transfer) {
            return $this->error(
                "Transfer not found with ID: {$id}",
                'NOT_FOUND',
                ['id' => $id],
                404
            );
        }

        // Get items
        $sql = "SELECT ci.*, p.name as product_name, p.sku
                FROM consignment_items ci
                LEFT JOIN products p ON ci.product_id = p.id
                WHERE ci.consignment_id = ?
                ORDER BY ci.id";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }

        // Get notes
        $sql = "SELECT n.*, u.name as user_name
                FROM transfer_notes n
                LEFT JOIN users u ON n.user_id = u.id
                WHERE n.transfer_id = ?
                ORDER BY n.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        $notes = [];
        while ($row = $result->fetch_assoc()) {
            $notes[] = $row;
        }

        $transfer['items'] = $items;
        $transfer['notes'] = $notes;
        $transfer['item_count'] = count($items);

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

        $searchTerm = "%{$q}%";

        $sql = "SELECT p.id as product_id, p.name, p.sku, p.retail_price,
                       COALESCE(SUM(i.quantity), 0) as stock
                FROM products p
                LEFT JOIN inventory i ON p.id = i.product_id
                WHERE (p.name LIKE ? OR p.sku LIKE ?)
                AND p.active = 1
                GROUP BY p.id
                ORDER BY p.name
                LIMIT ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('ssi', $searchTerm, $searchTerm, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
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

        $category = $this->validateString($data, 'consignment_category');
        $outletFrom = $this->validateInt($data, 'outlet_from');
        $outletTo = $this->validateInt($data, 'outlet_to');
        $supplierId = isset($data['supplier_id']) ? $this->validateInt($data, 'supplier_id') : null;
        $createdBy = $_SESSION['user_id'] ?? 1;

        // Validate outlets are different (unless supplier order)
        if ($category !== 'SUPPLIER' && $outletFrom === $outletTo) {
            return $this->error(
                'Source and destination outlets must be different',
                'VALIDATION_ERROR',
                ['outlet_from' => $outletFrom, 'outlet_to' => $outletTo],
                422
            );
        }

        $sql = "INSERT INTO transfers (consignment_category, outlet_from, outlet_to, supplier_id, status, created_by, created_at)
                VALUES (?, ?, ?, ?, 'DRAFT', ?, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('siiii', $category, $outletFrom, $outletTo, $supplierId, $createdBy);

        if (!$stmt->execute()) {
            return $this->error(
                'Failed to create transfer',
                'CREATE_FAILED',
                ['error' => $stmt->error],
                500
            );
        }

        $transferId = $this->db->insert_id;

        $this->logInfo('Transfer created', [
            'transfer_id' => $transferId,
            'category' => $category,
            'user_id' => $createdBy
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
        $productId = $this->validateInt($data, 'product_id');
        $qty = $this->validateInt($data, 'qty', null, 1);

        // Check if item already exists
        $sql = "SELECT id, qty_requested FROM consignment_items WHERE consignment_id = ? AND product_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('ii', $transferId, $productId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            // Update existing item
            $newQty = $existing['qty_requested'] + $qty;
            $sql = "UPDATE consignment_items SET qty_requested = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('ii', $newQty, $existing['id']);
            $stmt->execute();

            return $this->success([
                'item_id' => $existing['id'],
                'action' => 'updated',
                'qty_requested' => $newQty
            ], 'Item quantity updated');
        } else {
            // Insert new item
            $sql = "INSERT INTO consignment_items (consignment_id, product_id, qty_requested, created_at)
                    VALUES (?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('iii', $transferId, $productId, $qty);

            if (!$stmt->execute()) {
                return $this->error(
                    'Failed to add item to transfer',
                    'ADD_ITEM_FAILED',
                    ['error' => $stmt->error],
                    500
                );
            }

            return $this->success([
                'item_id' => $this->db->insert_id,
                'action' => 'created',
                'qty_requested' => $qty
            ], 'Item added to transfer', ['http_code' => 201]);
        }
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

        $sql = "UPDATE consignment_items
                SET qty_requested = ?
                WHERE id = ? AND consignment_id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('iii', $qtyRequested, $itemId, $transferId);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            return $this->error(
                'Item not found or no changes made',
                'UPDATE_FAILED',
                ['item_id' => $itemId],
                404
            );
        }

        return $this->success([
            'item_id' => $itemId,
            'qty_requested' => $qtyRequested,
            'updated' => true
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

        $sql = "DELETE FROM consignment_items WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $itemId);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            return $this->error(
                'Item not found',
                'NOT_FOUND',
                ['item_id' => $itemId],
                404
            );
        }

        return $this->success([
            'item_id' => $itemId,
            'deleted' => true
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
        $totalBoxes = isset($data['total_boxes']) ? $this->validateInt($data, 'total_boxes', null, 0) : null;

        $sql = "UPDATE transfers SET status = 'SENT', sent_at = NOW()";
        if ($totalBoxes !== null) {
            $sql .= ", total_boxes = ?";
        }
        $sql .= " WHERE id = ? AND status = 'DRAFT'";

        $stmt = $this->db->prepare($sql);
        if ($totalBoxes !== null) {
            $stmt->bind_param('ii', $totalBoxes, $id);
        } else {
            $stmt->bind_param('i', $id);
        }
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            return $this->error(
                'Transfer not found or not in DRAFT status',
                'UPDATE_FAILED',
                ['id' => $id],
                422
            );
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

        $sql = "INSERT INTO transfer_notes (transfer_id, user_id, note_text, created_at)
                VALUES (?, ?, ?, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('iis', $id, $userId, $noteText);

        if (!$stmt->execute()) {
            return $this->error(
                'Failed to add note',
                'CREATE_FAILED',
                ['error' => $stmt->error],
                500
            );
        }

        return $this->success([
            'note_id' => $this->db->insert_id,
            'transfer_id' => $id,
            'created_at' => date('Y-m-d H:i:s')
        ], 'Note added successfully', ['http_code' => 201]);
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Get sync state from file
     *
     * @return bool
     */
    private function getSyncState(): bool {
        if (!file_exists($this->syncFile)) {
            // Default to enabled
            file_put_contents($this->syncFile, '1');
            return true;
        }

        $content = trim(file_get_contents($this->syncFile));
        return $content === '1';
    }

    /**
     * Get outlets from database
     *
     * @return array
     */
    private function getOutlets(): array {
        $sql = "SELECT id, outlet_name, outlet_code FROM outlets WHERE active = 1 ORDER BY outlet_name";
        $result = $this->db->query($sql);

        $outlets = [];
        while ($row = $result->fetch_assoc()) {
            $outlets[] = $row;
        }

        return $outlets;
    }

    /**
     * Get suppliers from database
     *
     * @return array
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
