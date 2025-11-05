<?php
/**
 * TransferService - Core transfer management business logic
 *
 * Handles all transfer operations including:
 * - Transfer CRUD operations
 * - Transfer listing and filtering
 * - Transfer item management
 * - Status management
 * - Statistics and reporting
 *
 * Extracted from TransferManagerAPI to follow proper MVC pattern.
 * Uses PDO with RO/RW separation for optimal performance.
 *
 * @package CIS\Consignments\Services
 * @version 1.0.0
 * @author CIS Development Team
 * @created 2025-11-05
 */

declare(strict_types=1);

namespace CIS\Consignments\Services;

use PDO;
use PDOException;
use InvalidArgumentException;

class TransferService
{
    /**
     * Read-only database connection
     * @var PDO
     */
    private PDO $ro;

    /**
     * Read-write database connection
     * @var PDO
     */
    private PDO $rw;

    /**
     * Valid transfer types
     * @var array<string>
     */
    private const VALID_TYPES = [
        'STOCK',
        'JUICE',
        'PURCHASE_ORDER',
        'INTERNAL',
        'RETURN',
        'STAFF'
    ];

    /**
     * Valid transfer statuses
     * @var array<string>
     */
    private const VALID_STATUSES = [
        'draft',
        'sent',
        'receiving',
        'received',
        'completed',
        'cancelled'
    ];

    /**
     * Constructor
     *
     * @param PDO $ro Read-only connection
     * @param PDO $rw Read-write connection
     */
    public function __construct(PDO $ro, PDO $rw)
    {
        $this->ro = $ro;
        $this->rw = $rw;
    }

    /**
     * Factory method using global database helpers
     *
     * @return self
     */
    public static function make(): self
    {
        // Use global database connection helpers (CIS pattern)
        $ro = db_ro();
        $rw = db_rw_or_null() ?? $ro;

        return new self($ro, $rw);
    }

    // ========================================================================
    // LISTING & RETRIEVAL
    // ========================================================================

    /**
     * List transfers with pagination and filtering
     *
     * @param array $filters {
     *     @type string|null $type Transfer category (STOCK, JUICE, etc.)
     *     @type string|null $state Transfer status (draft, sent, etc.)
     *     @type int|null $outlet Outlet ID (matches either from or to)
     *     @type string $q Search query (matches consignment number or notes)
     * }
     * @param int $page Page number (1-based)
     * @param int $perPage Results per page (1-100)
     * @return array {
     *     @type array $transfers Array of transfer records
     *     @type array $pagination {
     *         @type int $page Current page
     *         @type int $per_page Results per page
     *         @type int $total Total matching records
     *         @type int $total_pages Total pages
     *     }
     * }
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        // Validate inputs
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        // Extract filters
        $type = $filters['type'] ?? null;
        $state = $filters['state'] ?? null;
        $outlet = isset($filters['outlet']) ? (int)$filters['outlet'] : null;
        $q = trim($filters['q'] ?? '');

        // Build query using actual database schema
        $sql = "SELECT t.*,
                       o_from.name as from_name,
                       o_to.name as to_name,
                       s.name as supplier_name,
                       COALESCE((SELECT SUM(cp.count_ordered) FROM queue_consignment_products cp WHERE cp.consignment_id = t.id), 0) as total_qty
                FROM queue_consignments t
                LEFT JOIN vend_outlets o_from ON t.source_outlet_id = o_from.id
                LEFT JOIN vend_outlets o_to ON t.destination_outlet_id = o_to.id
                LEFT JOIN ls_suppliers s ON t.supplier_id = s.supplier_id
                WHERE 1=1";

        $params = [];

        // Type filter
        if ($type !== null && in_array($type, self::VALID_TYPES, true)) {
            $sql .= " AND t.transfer_category = :type";
            $params[':type'] = $type;
        }

        // Status filter
        if ($state !== null && in_array($state, self::VALID_STATUSES, true)) {
            $sql .= " AND t.status = :state";
            $params[':state'] = $state;
        }

        // Outlet filter (matches either from or to)
        if ($outlet !== null) {
            $sql .= " AND (t.source_outlet_id = :outlet OR t.destination_outlet_id = :outlet)";
            $params[':outlet'] = $outlet;
        }

        // Search filter
        if ($q !== '') {
            $searchTerm = "%{$q}%";
            $sql .= " AND (t.vend_consignment_id LIKE :search1 OR t.reference LIKE :search2 OR t.name LIKE :search3)";
            $params[':search1'] = $searchTerm;
            $params[':search2'] = $searchTerm;
            $params[':search3'] = $searchTerm;
        }

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as filtered";
        $stmt = $this->ro->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Get paginated results
        $sql .= " ORDER BY t.created_at DESC LIMIT :limit OFFSET :offset";
        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->ro->prepare($sql);
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        $stmt->execute();

        $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'transfers' => $transfers,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int)ceil($total / $perPage)
            ]
        ];
    }

    /**
     * Get transfer by ID with full details
     *
     * @param int $id Transfer ID
     * @return array|null Transfer record with items and notes, or null if not found
     */
    public function getById(int $id): ?array
    {
        // Get transfer with joins
        $sql = "SELECT t.*,
                       o_from.name as from_name,
                       o_to.name as to_name,
                       s.name as supplier_name,
                       u.name as created_by_name
                FROM queue_consignments t
                LEFT JOIN vend_outlets o_from ON t.source_outlet_id = o_from.id
                LEFT JOIN vend_outlets o_to ON t.destination_outlet_id = o_to.id
                LEFT JOIN ls_suppliers s ON t.supplier_id = s.supplier_id
                LEFT JOIN users u ON t.cis_user_id = u.id
                WHERE t.id = :id
                LIMIT 1";

        $stmt = $this->ro->prepare($sql);
        $stmt->execute([':id' => $id]);
        $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transfer) {
            return null;
        }

        // Get items
        $transfer['items'] = $this->getItems($id);
        $transfer['item_count'] = count($transfer['items']);

        // Get notes
        $transfer['notes'] = $this->getNotes($id);

        return $transfer;
    }

    /**
     * Get transfer items
     *
     * @param int $transferId Transfer ID
     * @return array Array of item records
     */
    public function getItems(int $transferId): array
    {
        $sql = "SELECT cp.*,
                       p.name as product_name,
                       p.sku as product_sku,
                       p.price_including_tax as retail_price
                FROM queue_consignment_products cp
                LEFT JOIN vend_products p ON cp.vend_product_id = p.id
                WHERE cp.consignment_id = :id
                ORDER BY cp.id";

        $stmt = $this->ro->prepare($sql);
        $stmt->execute([':id' => $transferId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get transfer notes
     *
     * @param int $transferId Transfer ID
     * @return array Array of note records
     */
    public function getNotes(int $transferId): array
    {
        $sql = "SELECT n.*,
                       u.name as user_name
                FROM consignment_notes n
                LEFT JOIN users u ON n.user_id = u.id
                WHERE n.consignment_id = :id
                ORDER BY n.created_at DESC";

        $stmt = $this->ro->prepare($sql);
        $stmt->execute([':id' => $transferId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get recent transfers
     *
     * @param int $limit Maximum results (1-100)
     * @return array Array of transfer records
     */
    public function recent(int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));

        $sql = "SELECT t.id,
                       t.vend_consignment_id,
                       t.status,
                       t.transfer_category,
                       t.source_outlet_id,
                       t.destination_outlet_id,
                       t.created_at,
                       o_from.name as from_name,
                       o_to.name as to_name
                FROM queue_consignments t
                LEFT JOIN vend_outlets o_from ON t.source_outlet_id = o_from.id
                LEFT JOIN vend_outlets o_to ON t.destination_outlet_id = o_to.id
                ORDER BY t.id DESC
                LIMIT :limit";

        $stmt = $this->ro->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========================================================================
    // CREATION & MODIFICATION
    // ========================================================================

    /**
     * Create new transfer
     *
     * @param array $payload {
     *     @type string $consignment_category Transfer type
     *     @type int $outlet_from Source outlet ID
     *     @type int|null $outlet_to Destination outlet ID
     *     @type int|null $supplier_id Supplier ID (for PO)
     *     @type string|null $notes Transfer notes
     *     @type int $created_by User ID
     * }
     * @return int New transfer ID
     * @throws InvalidArgumentException If validation fails
     */
    public function create(array $payload): int
    {
        // Validate required fields
        if (empty($payload['transfer_category'])) {
            throw new InvalidArgumentException('Transfer category is required');
        }

        if (!in_array($payload['transfer_category'], self::VALID_TYPES, true)) {
            throw new InvalidArgumentException('Invalid transfer category');
        }

        if (empty($payload['source_outlet_id'])) {
            throw new InvalidArgumentException('Source outlet is required');
        }

        // Generate reference code
        $refCode = $this->generateRefCode($payload['transfer_category']);

        $sql = "INSERT INTO queue_consignments (
                    transfer_category,
                    source_outlet_id,
                    destination_outlet_id,
                    supplier_id,
                    name,
                    reference,
                    status,
                    vend_consignment_id,
                    cis_user_id,
                    created_at
                ) VALUES (
                    :category,
                    :source_outlet_id,
                    :destination_outlet_id,
                    :supplier_id,
                    :name,
                    :reference,
                    'OPEN',
                    :ref_code,
                    :cis_user_id,
                    NOW()
                )";

        $stmt = $this->rw->prepare($sql);
        $stmt->execute([
            ':category' => $payload['transfer_category'],
            ':source_outlet_id' => $payload['source_outlet_id'],
            ':destination_outlet_id' => $payload['destination_outlet_id'] ?? null,
            ':supplier_id' => $payload['supplier_id'] ?? null,
            ':name' => $payload['name'] ?? null,
            ':reference' => $refCode,
            ':ref_code' => $refCode,
            ':cis_user_id' => (int)($payload['cis_user_id'] ?? 0)
        ]);

        return (int)$this->rw->lastInsertId();
    }

    /**
     * Add item to transfer
     *
     * @param int $transferId Transfer ID
     * @param array $item {
     *     @type int $product_id Product ID
     *     @type int $qty_requested Quantity requested
     *     @type string|null $notes Item notes
     * }
     * @return int New item ID
     * @throws InvalidArgumentException If validation fails
     */
    public function addItem(int $transferId, array $item): int
    {
        if (empty($item['vend_product_id'])) {
            throw new InvalidArgumentException('Product ID is required');
        }

        if (empty($item['count_ordered']) || $item['count_ordered'] < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1');
        }

        $sql = "INSERT INTO queue_consignment_products (
                    consignment_id,
                    vend_product_id,
                    count_ordered,
                    count_received,
                    count_damaged,
                    product_name,
                    product_sku
                ) VALUES (
                    :consignment_id,
                    :vend_product_id,
                    :count_ordered,
                    0,
                    0,
                    :product_name,
                    :product_sku
                )";

        $stmt = $this->rw->prepare($sql);
        $stmt->execute([
            ':consignment_id' => $transferId,
            ':vend_product_id' => $item['vend_product_id'],
            ':count_ordered' => (int)$item['count_ordered'],
            ':product_name' => $item['product_name'] ?? null,
            ':product_sku' => $item['product_sku'] ?? null
        ]);

        return (int)$this->rw->lastInsertId();
    }

    /**
     * Update transfer item
     *
     * @param int $itemId Item ID
     * @param int $transferId Transfer ID (for verification)
     * @param array $updates Updates to apply (e.g., ['count_ordered' => 5])
     * @return bool Success
     * @throws RuntimeException If item not found
     */
    public function updateItem(int $itemId, int $transferId, array $updates): bool
    {
        $allowedFields = ['count_ordered', 'count_received', 'product_name', 'product_sku'];
        $setClauses = [];
        $params = [':item_id' => $itemId, ':transfer_id' => $transferId];

        foreach ($updates as $field => $value) {
            if (in_array($field, $allowedFields, true)) {
                $setClauses[] = "$field = :$field";
                $params[":$field"] = $value;
            }
        }

        if (empty($setClauses)) {
            throw new InvalidArgumentException('No valid fields to update');
        }

        $sql = "UPDATE queue_consignment_products
                SET " . implode(', ', $setClauses) . "
                WHERE id = :item_id AND consignment_id = :transfer_id
                LIMIT 1";

        $stmt = $this->rw->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException("Item #{$itemId} not found in transfer #{$transferId}");
        }

        return true;
    }

    /**
     * Delete transfer item
     *
     * @param int $itemId Item ID
     * @return bool Success
     * @throws RuntimeException If item not found
     */
    public function deleteItem(int $itemId): bool
    {
        $sql = "DELETE FROM queue_consignment_products WHERE id = :item_id LIMIT 1";
        $stmt = $this->rw->prepare($sql);
        $stmt->execute([':item_id' => $itemId]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException("Item #{$itemId} not found");
        }

        return true;
    }

    /**
     * Add note to transfer
     *
     * @param int $transferId Transfer ID
     * @param string $noteText Note text
     * @param int $userId User ID
     * @return int Note ID
     * @throws RuntimeException If note creation fails
     */
    public function addNote(int $transferId, string $noteText, int $userId): int
    {
        $sql = "INSERT INTO transfer_notes (transfer_id, user_id, note_text, created_at)
                VALUES (:transfer_id, :user_id, :note_text, NOW())";

        $stmt = $this->rw->prepare($sql);
        $result = $stmt->execute([
            ':transfer_id' => $transferId,
            ':user_id' => $userId,
            ':note_text' => $noteText
        ]);

        if (!$result) {
            throw new RuntimeException("Failed to add note to transfer #{$transferId}");
        }

        return (int)$this->rw->lastInsertId();
    }

    /**
     * Update transfer status
     *
     * @param int $id Transfer ID
     * @param string $status New status
     * @return bool Success
     * @throws InvalidArgumentException If status is invalid
     */
    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(
                'Invalid status. Allowed: ' . implode(', ', self::VALID_STATUSES)
            );
        }

        $sql = "UPDATE queue_consignments
                SET status = :status,
                    updated_at = NOW()
                WHERE id = :id
                LIMIT 1";

        $stmt = $this->rw->prepare($sql);
        return $stmt->execute([
            ':status' => $status,
            ':id' => $id
        ]);
    }

    /**
     * Delete transfer (soft delete by setting status to cancelled)
     *
     * @param int $id Transfer ID
     * @return bool Success
     */
    public function delete(int $id): bool
    {
        return $this->updateStatus($id, 'cancelled');
    }

    // ========================================================================
    // STATISTICS & REPORTING
    // ========================================================================

    /**
     * Get transfer statistics
     *
     * @param array $filters Optional filters (type, state, outlet, date range)
     * @return array Statistics data
     */
    public function getStats(array $filters = []): array
    {
        $whereClause = "WHERE 1=1";
        $params = [];

        if (!empty($filters['type'])) {
            $whereClause .= " AND transfer_category = :type";
            $params[':type'] = $filters['type'];
        }

        if (!empty($filters['state'])) {
            $whereClause .= " AND status = :state";
            $params[':state'] = $filters['state'];
        }

        if (!empty($filters['outlet'])) {
            $whereClause .= " AND (source_outlet_id = :outlet OR destination_outlet_id = :outlet)";
            $params[':outlet'] = $filters['outlet'];
        }

        $sql = "SELECT
                    COUNT(*) as total_transfers,
                    COUNT(DISTINCT CASE WHEN status = 'OPEN' THEN id END) as draft_count,
                    COUNT(DISTINCT CASE WHEN status = 'SENT' THEN id END) as sent_count,
                    COUNT(DISTINCT CASE WHEN status = 'DISPATCHED' THEN id END) as receiving_count,
                    COUNT(DISTINCT CASE WHEN status = 'RECEIVED' THEN id END) as received_count,
                    COUNT(DISTINCT CASE WHEN status = 'RECEIVED' THEN id END) as completed_count,
                    COUNT(DISTINCT CASE WHEN status = 'CANCELLED' THEN id END) as cancelled_count,
                    (SELECT COUNT(*) FROM queue_consignment_products cp
                     JOIN queue_consignments t ON cp.consignment_id = t.id
                     {$whereClause}) as total_items
                FROM queue_consignments
                {$whereClause}";

        $stmt = $this->ro->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // ========================================================================
    // UTILITY METHODS
    // ========================================================================

    /**
     * Generate unique reference code for transfer
     *
     * @param string $category Transfer category
     * @return string Reference code (e.g., "STOCK-20251105-001")
     */
    private function generateRefCode(string $category): string
    {
        $date = date('Ymd');
        $prefix = strtoupper(substr($category, 0, 3));

        // Get next sequence number for today
        $sql = "SELECT COUNT(*) as count
                FROM queue_consignments
                WHERE reference LIKE :pattern
                AND DATE(created_at) = CURDATE()";

        $stmt = $this->ro->prepare($sql);
        $stmt->execute([':pattern' => "{$prefix}-{$date}-%"]);
        $count = (int)$stmt->fetchColumn();

        $sequence = str_pad((string)($count + 1), 3, '0', STR_PAD_LEFT);

        return "{$prefix}-{$date}-{$sequence}";
    }

    /**
     * Validate transfer ownership or access
     *
     * @param int $id Transfer ID
     * @param int $userId User ID
     * @param array $userOutlets Array of outlet IDs user has access to
     * @return bool True if user has access
     */
    public function hasAccess(int $id, int $userId, array $userOutlets = []): bool
    {
        $transfer = $this->getById($id);

        if (!$transfer) {
            return false;
        }

        // User created the transfer
        if (isset($transfer['cis_user_id']) && $transfer['cis_user_id'] === $userId) {
            return true;
        }

        // User has access to source or destination outlet
        if (!empty($userOutlets)) {
            if (in_array($transfer['source_outlet_id'], $userOutlets, true)) {
                return true;
            }
            if (in_array($transfer['destination_outlet_id'], $userOutlets, true)) {
                return true;
            }
        }

        return false;
    }
}
