<?php
/**
 * Transfer Manager Service
 *
 * Core transfer management functionality extracted from TransferManager module.
 * Handles all transfer operations across stock transfers, purchase orders, and consignments.
 *
 * @package CIS\Consignments\Services
 * @version 1.0.0
 */

declare(strict_types=1);

namespace CIS\Consignments\Services;

use PDO;
use RuntimeException;
use InvalidArgumentException;

class TransferManagerService
{
    private PDO $db;
    private ConsignmentHelpers $helpers;
    private LightspeedSync $lightspeed;

    // Transfer types
    public const TYPE_STOCK_TRANSFER = 'STOCK_TRANSFER';
    public const TYPE_PURCHASE_ORDER = 'PURCHASE_ORDER';
    public const TYPE_SUPPLIER_RETURN = 'SUPPLIER_RETURN';
    public const TYPE_OUTLET_RETURN = 'OUTLET_RETURN';
    public const TYPE_ADJUSTMENT = 'ADJUSTMENT';

    // Transfer states
    public const STATE_DRAFT = 'DRAFT';
    public const STATE_OPEN = 'OPEN';
    public const STATE_SENT = 'SENT';
    public const STATE_RECEIVING = 'RECEIVING';
    public const STATE_RECEIVED = 'RECEIVED';
    public const STATE_CANCELLED = 'CANCELLED';

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->helpers = new ConsignmentHelpers($db);
        $this->lightspeed = new LightspeedSync($db);
    }

    /**
     * Initialize - Get configuration and stats
     */
    public function init(): array
    {
        return [
            'sync_enabled' => $this->getSyncEnabled(),
            'transfer_types' => [
                self::TYPE_STOCK_TRANSFER,
                self::TYPE_PURCHASE_ORDER,
                self::TYPE_SUPPLIER_RETURN,
                self::TYPE_OUTLET_RETURN,
                self::TYPE_ADJUSTMENT
            ],
            'transfer_states' => [
                self::STATE_DRAFT,
                self::STATE_OPEN,
                self::STATE_SENT,
                self::STATE_RECEIVING,
                self::STATE_RECEIVED,
                self::STATE_CANCELLED
            ],
            'outlets' => $this->getOutlets(),
            'stats' => $this->getTransferStats()
        ];
    }

    /**
     * List transfers with filters and pagination
     */
    public function listTransfers(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $offset = ($page - 1) * $perPage;

        // Build WHERE clause
        $where = ['t.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['type'])) {
            $where[] = 't.transfer_category = ?';
            $params[] = $filters['type'];
        }

        if (!empty($filters['state'])) {
            $where[] = 't.status = ?';
            $params[] = $filters['state'];
        }

        if (!empty($filters['outlet'])) {
            $where[] = '(t.source_outlet_id = ? OR t.destination_outlet_id = ?)';
            $params[] = $filters['outlet'];
            $params[] = $filters['outlet'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(t.name LIKE ? OR t.vend_consignment_number LIKE ?)';
            $search = '%' . $filters['q'] . '%';
            $params[] = $search;
            $params[] = $search;
        }

        $whereSQL = implode(' AND ', $where);

        // Get total count
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM vend_consignments t WHERE {$whereSQL}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Get paginated results
        $stmt = $this->db->prepare("
            SELECT
                t.*,
                o_from.name AS source_outlet_name,
                o_to.name AS destination_outlet_name,
                s.name AS supplier_name,
                (SELECT COUNT(*) FROM vend_consignment_line_items WHERE transfer_id = t.id AND deleted_at IS NULL) AS item_count,
                (SELECT SUM(quantity) FROM vend_consignment_line_items WHERE transfer_id = t.id AND deleted_at IS NULL) AS total_quantity
            FROM vend_consignments t
            LEFT JOIN outlets o_from ON t.source_outlet_id = o_from.id
            LEFT JOIN outlets o_to ON t.destination_outlet_id = o_to.id
            LEFT JOIN suppliers s ON t.supplier_id = s.id
            WHERE {$whereSQL}
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?
        ");

        $params[] = $perPage;
        $params[] = $offset;
        $stmt->execute($params);

        $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'transfers' => $transfers,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => (int) ceil($total / $perPage)
            ]
        ];
    }

    /**
     * Get transfer detail with all items and notes
     */
    public function getTransferDetail(int $transferId): array
    {
        // Get transfer
        $stmt = $this->db->prepare("
            SELECT
                t.*,
                o_from.name AS source_outlet_name,
                o_from.address AS source_outlet_address,
                o_to.name AS destination_outlet_name,
                o_to.address AS destination_outlet_address,
                s.name AS supplier_name,
                s.email AS supplier_email,
                s.phone AS supplier_phone
            FROM vend_consignments t
            LEFT JOIN outlets o_from ON t.source_outlet_id = o_from.id
            LEFT JOIN outlets o_to ON t.destination_outlet_id = o_to.id
            LEFT JOIN suppliers s ON t.supplier_id = s.id
            WHERE t.id = ? AND t.deleted_at IS NULL
        ");
        $stmt->execute([$transferId]);
        $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transfer) {
            throw new RuntimeException('Transfer not found');
        }

        // Get items
        $stmt = $this->db->prepare("
            SELECT
                i.*,
                p.name AS product_name,
                p.sku,
                p.supply_price,
                pd.weight AS product_weight,
                pd.length AS product_length,
                pd.width AS product_width,
                pd.height AS product_height
            FROM vend_consignment_line_items i
            LEFT JOIN vend_products p ON i.product_id = p.id
            LEFT JOIN product_dimensions pd ON p.id = pd.product_id
            WHERE i.transfer_id = ? AND i.deleted_at IS NULL
            ORDER BY p.name ASC
        ");
        $stmt->execute([$transferId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get notes
        $stmt = $this->db->prepare("
            SELECT
                n.*,
                u.name AS user_name
            FROM transfer_notes n
            LEFT JOIN users u ON n.user_id = u.id
            WHERE n.transfer_id = ?
            ORDER BY n.created_at DESC
        ");
        $stmt->execute([$transferId]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'transfer' => $transfer,
            'items' => $items,
            'notes' => $notes,
            'audit_trail' => $this->helpers->getAuditTrail($transferId)
        ];
    }

    /**
     * Create new transfer
     */
    public function createTransfer(array $data): array
    {
        // Validate
        $errors = $this->helpers->validateConsignment($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
        }

        // Insert transfer
        $stmt = $this->db->prepare("
            INSERT INTO vend_consignments (
                transfer_category,
                source_outlet_id,
                destination_outlet_id,
                supplier_id,
                status,
                name,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $data['transfer_category'] ?? self::TYPE_STOCK_TRANSFER,
            $data['source_outlet_id'] ?? null,
            $data['destination_outlet_id'] ?? null,
            $data['supplier_id'] ?? null,
            self::STATE_DRAFT,
            $data['name'] ?? null
        ]);

        $transferId = (int) $this->db->lastInsertId();

        // Log creation
        $this->helpers->logEvent($transferId, 'created', $data);

        return $this->getTransferDetail($transferId);
    }

    /**
     * Add item to transfer
     */
    public function addTransferItem(int $transferId, string $productId, int $quantity): array
    {
        // Check if item already exists
        $stmt = $this->db->prepare("
            SELECT id, quantity
            FROM vend_consignment_line_items
            WHERE transfer_id = ? AND product_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$transferId, $productId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing
            $newQty = $existing['quantity'] + $quantity;
            $stmt = $this->db->prepare("
                UPDATE vend_consignment_line_items
                SET quantity = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$newQty, $existing['id']]);
            $itemId = $existing['id'];
        } else {
            // Insert new
            $stmt = $this->db->prepare("
                INSERT INTO vend_consignment_line_items (
                    transfer_id,
                    product_id,
                    quantity,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$transferId, $productId, $quantity]);
            $itemId = (int) $this->db->lastInsertId();
        }

        $this->helpers->logEvent($transferId, 'item_added', [
            'product_id' => $productId,
            'quantity' => $quantity
        ]);

        return $this->getTransferDetail($transferId);
    }

    /**
     * Update transfer item quantity
     */
    public function updateTransferItem(int $transferId, int $itemId, int $quantity): array
    {
        if ($quantity <= 0) {
            return $this->removeTransferItem($itemId);
        }

        $stmt = $this->db->prepare("
            UPDATE vend_consignment_line_items
            SET quantity = ?, updated_at = NOW()
            WHERE id = ? AND transfer_id = ?
        ");
        $stmt->execute([$quantity, $itemId, $transferId]);

        $this->helpers->logEvent($transferId, 'item_updated', [
            'item_id' => $itemId,
            'quantity' => $quantity
        ]);

        return $this->getTransferDetail($transferId);
    }

    /**
     * Remove transfer item
     */
    public function removeTransferItem(int $itemId): array
    {
        $stmt = $this->db->prepare("
            SELECT transfer_id FROM vend_consignment_line_items WHERE id = ?
        ");
        $stmt->execute([$itemId]);
        $transferId = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare("
            UPDATE vend_consignment_line_items
            SET deleted_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$itemId]);

        $this->helpers->logEvent($transferId, 'item_removed', ['item_id' => $itemId]);

        return $this->getTransferDetail($transferId);
    }

    /**
     * Mark transfer as sent
     */
    public function markSent(int $transferId, ?int $totalBoxes = null): array
    {
        $this->helpers->updateStatus($transferId, self::STATE_SENT, 'Marked as sent');

        if ($totalBoxes) {
            $stmt = $this->db->prepare("
                UPDATE vend_consignments
                SET total_boxes = ?, sent_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$totalBoxes, $transferId]);
        }

        // Sync to Lightspeed if enabled
        if ($this->getSyncEnabled()) {
            try {
                $this->lightspeed->syncConsignment($transferId);
            } catch (\Exception $e) {
                // Log but don't fail
                $this->helpers->logEvent($transferId, 'sync_failed', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $this->getTransferDetail($transferId);
    }

    /**
     * Mark transfer as receiving
     */
    public function markReceiving(int $transferId): array
    {
        $this->helpers->updateStatus($transferId, self::STATE_RECEIVING, 'Started receiving');
        return $this->getTransferDetail($transferId);
    }

    /**
     * Receive all items
     */
    public function receiveAll(int $transferId): array
    {
        // Update all items to received
        $stmt = $this->db->prepare("
            UPDATE vend_consignment_line_items
            SET quantity_received = quantity,
                received_at = NOW()
            WHERE transfer_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$transferId]);

        $this->helpers->updateStatus($transferId, self::STATE_RECEIVED, 'All items received');

        // Sync to Lightspeed
        if ($this->getSyncEnabled()) {
            try {
                $this->lightspeed->syncConsignment($transferId);
            } catch (\Exception $e) {
                $this->helpers->logEvent($transferId, 'sync_failed', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $this->getTransferDetail($transferId);
    }

    /**
     * Cancel transfer
     */
    public function cancelTransfer(int $transferId, string $reason = ''): array
    {
        $this->helpers->updateStatus($transferId, self::STATE_CANCELLED, 'Cancelled: ' . $reason);

        $stmt = $this->db->prepare("
            UPDATE vend_consignments
            SET cancelled_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$transferId]);

        return $this->getTransferDetail($transferId);
    }

    /**
     * Add note to transfer
     */
    public function addNote(int $transferId, string $noteText, ?int $userId = null): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO transfer_notes (
                transfer_id,
                user_id,
                note_text,
                created_at
            ) VALUES (?, ?, ?, NOW())
        ");

        $stmt->execute([
            $transferId,
            $userId ?? $_SESSION['user_id'] ?? null,
            $noteText
        ]);

        $this->helpers->logEvent($transferId, 'note_added', ['note' => $noteText]);

        return $this->getTransferDetail($transferId);
    }

    /**
     * Search products
     */
    public function searchProducts(string $query, int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT
                p.*,
                pd.weight,
                pd.length,
                pd.width,
                pd.height
            FROM vend_products p
            LEFT JOIN product_dimensions pd ON p.id = pd.product_id
            WHERE (p.name LIKE ? OR p.sku LIKE ?)
                AND p.deleted_at IS NULL
            ORDER BY p.name ASC
            LIMIT ?
        ");

        $search = '%' . $query . '%';
        $stmt->execute([$search, $search, $limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get outlets
     */
    private function getOutlets(): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, address
            FROM outlets
            WHERE deleted_at IS NULL
            ORDER BY name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get transfer stats
     */
    private function getTransferStats(): array
    {
        $stmt = $this->db->query("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'DRAFT' THEN 1 ELSE 0 END) AS draft,
                SUM(CASE WHEN status = 'OPEN' THEN 1 ELSE 0 END) AS open,
                SUM(CASE WHEN status = 'SENT' THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN status = 'RECEIVING' THEN 1 ELSE 0 END) AS receiving,
                SUM(CASE WHEN status = 'RECEIVED' THEN 1 ELSE 0 END) AS received,
                SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled
            FROM vend_consignments
            WHERE deleted_at IS NULL
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get/set sync enabled state
     */
    public function getSyncEnabled(): bool
    {
        $file = __DIR__ . '/../../TransferManager/.sync_enabled';
        if (!file_exists($file)) {
            file_put_contents($file, '1');
            return true;
        }
        return trim(file_get_contents($file)) === '1';
    }

    public function setSyncEnabled(bool $enabled): void
    {
        $file = __DIR__ . '/../../TransferManager/.sync_enabled';
        file_put_contents($file, $enabled ? '1' : '0');

        $this->helpers->logEvent(0, 'sync_toggled', [
            'enabled' => $enabled
        ]);
    }
}
