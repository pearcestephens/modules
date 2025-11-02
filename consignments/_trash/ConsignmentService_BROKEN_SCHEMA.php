<?php
/**
 * Consignment Service Layer
 *
 * Handles all consignment-related database operations using PDO prepared statements.
 * Provides a clean API for consignment CRUD operations with proper security.
 *
 * Design:
 * - Uses read-only and read-write PDO connections
 * - All queries use prepared statements (SQL injection safe)
 * - Type-safe parameters with strict typing
 * - Throws exceptions on RW operations when RW connection unavailable
 *
 * Usage:
 *   $svc = ConsignmentService::make();
 *   $recent = $svc->recent(50);
 *   $consignment = $svc->get(123);
 *   $newId = $svc->create([...]);
 *
 * @package CIS\Consignments
 * @version 1.0.0
 * @created 2025-10-31
 */

declare(strict_types=1);

use PDO;
use PDOException;
use RuntimeException;

final class ConsignmentService
{
    /**
     * @param PDO $ro Read-only database connection
     * @param PDO|null $rw Read-write database connection (may be null on RO-only nodes)
     */
    public function __construct(
        private PDO $ro,
        private ?PDO $rw
    ) {}

    /**
     * Factory method - creates service with connections from global helpers
     *
     * Expects these global functions to exist:
     * - db_ro(): PDO      Returns read-only connection
     * - db_rw_or_null(): ?PDO   Returns read-write connection or null
     *
     * @return self
     */
    public static function make(): self {
        if (!function_exists('db_ro')) {
            throw new RuntimeException('db_ro() function not available');
        }

        $ro = db_ro();
        $rw = function_exists('db_rw_or_null') ? db_rw_or_null() : null;

        return new self($ro, $rw);
    }

    /**
     * Get recent consignments (latest first)
     *
     * @param int $limit Max rows to return (1-200)
     * @return array Array of consignment rows
     */
    public function recent(int $limit = 50): array {
        $limit = max(1, min($limit, 200)); // Clamp to reasonable range

        $stmt = $this->ro->prepare(
            "SELECT id, ref_code, status, outlet_id AS origin_outlet_id, destination_outlet_id AS dest_outlet_id, created_at, updated_at
             FROM vend_consignments
             ORDER BY id DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get single consignment by ID with item count
     *
     * @param int $id Consignment ID
     * @return array|null Consignment row or null if not found
     */
    public function get(int $id): ?array {
        $stmt = $this->ro->prepare(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM vend_consignment_line_items i WHERE i.consignment_id = c.id) AS item_count
             FROM vend_consignments c
             WHERE c.id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get all items for a consignment
     *
     * @param int $consignmentId Consignment ID
     * @return array Array of item rows
     */
    public function items(int $consignmentId): array {
        $stmt = $this->ro->prepare(
            "SELECT id, consignment_id, product_id, sku, qty, packed_qty, status, created_at
             FROM vend_consignment_line_items
             WHERE consignment_id = :id
             ORDER BY id ASC"
        );
        $stmt->execute([':id' => $consignmentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create new consignment
     *
     * Required payload keys:
     * - ref_code: string
     * - origin_outlet_id: int
     * - dest_outlet_id: int
     * - created_by: int (user ID)
     *
     * Optional payload keys:
     * - status: string (defaults to 'draft')
     *
     * @param array $payload Consignment data
     * @return array ['id' => new_consignment_id]
     * @throws RuntimeException If RW connection unavailable
     */
    public function create(array $payload): array {
        $this->ensureRw();

        $sql = "INSERT INTO vend_consignments
                (ref_code, status, origin_outlet_id, dest_outlet_id, created_by, created_at)
                VALUES (:ref, :status, :o, :d, :by, NOW())";

        $stmt = $this->rw->prepare($sql);
        $stmt->execute([
            ':ref'    => (string)($payload['ref_code'] ?? ''),
            ':status' => (string)($payload['status'] ?? 'draft'),
            ':o'      => (int)($payload['origin_outlet_id'] ?? 0),
            ':d'      => (int)($payload['dest_outlet_id'] ?? 0),
            ':by'     => (int)($payload['created_by'] ?? 0),
        ]);

        $id = (int)$this->rw->lastInsertId();

        return ['id' => $id];
    }

    /**
     * Add item to consignment
     *
     * Required item keys:
     * - product_id: int
     * - sku: string
     * - qty: int
     *
     * Optional item keys:
     * - packed_qty: int (defaults to 0)
     * - status: string (defaults to 'pending')
     *
     * @param int $consignmentId Consignment ID
     * @param array $item Item data
     * @return array ['id' => new_item_id]
     * @throws RuntimeException If RW connection unavailable
     */
    public function addItem(int $consignmentId, array $item): array {
        $this->ensureRw();

        $sql = "INSERT INTO vend_consignment_line_items
                (consignment_id, product_id, sku, qty, packed_qty, status, created_at)
                VALUES (:cid, :pid, :sku, :qty, :pqty, :st, NOW())";

        $stmt = $this->rw->prepare($sql);
        $stmt->execute([
            ':cid'  => $consignmentId,
            ':pid'  => (int)($item['product_id'] ?? 0),
            ':sku'  => (string)($item['sku'] ?? ''),
            ':qty'  => (int)($item['qty'] ?? 0),
            ':pqty' => (int)($item['packed_qty'] ?? 0),
            ':st'   => (string)($item['status'] ?? 'pending'),
        ]);

        $itemId = (int)$this->rw->lastInsertId();

        return ['id' => $itemId];
    }

    /**
     * Update consignment status
     *
     * Common statuses: draft, sent, receiving, received, completed
     *
     * @param int $id Consignment ID
     * @param string $status New status
     * @return bool True if updated
     * @throws RuntimeException If RW connection unavailable
     */
    public function updateStatus(int $id, string $status): bool {
        $this->ensureRw();

        $stmt = $this->rw->prepare(
            "UPDATE vend_consignments
             SET status = :s, updated_at = NOW()
             WHERE id = :id
             LIMIT 1"
        );

        return $stmt->execute([':s' => $status, ':id' => $id]);
    }

    /**
     * Update item packed quantity
     *
     * @param int $itemId Item ID
     * @param int $packedQty New packed quantity
     * @return bool True if updated
     * @throws RuntimeException If RW connection unavailable
     */
    public function updateItemPackedQty(int $itemId, int $packedQty): bool {
        $this->ensureRw();

        $stmt = $this->rw->prepare(
            "UPDATE vend_consignment_line_items
             SET packed_qty = :pqty
             WHERE id = :id
             LIMIT 1"
        );

        return $stmt->execute([':pqty' => $packedQty, ':id' => $itemId]);
    }

    /**
     * Search consignments by ref_code or outlet
     *
     * @param string $refCode Partial ref_code to search
     * @param int|null $outletId Filter by origin or dest outlet
     * @param int $limit Max results
     * @return array Array of matching consignment rows
     */
    public function search(string $refCode = '', ?int $outletId = null, int $limit = 50): array {
        $limit = max(1, min($limit, 200));

        $where = [];
        $params = [];

        if ($refCode !== '') {
            $where[] = 'ref_code LIKE :ref';
            $params[':ref'] = '%' . $refCode . '%';
        }

        if ($outletId !== null) {
            $where[] = '(origin_outlet_id = :outlet OR dest_outlet_id = :outlet)';
            $params[':outlet'] = $outletId;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT id, ref_code, status, origin_outlet_id, dest_outlet_id, created_at
                FROM vend_consignments
                $whereClause
                ORDER BY id DESC
                LIMIT :lim";

        $stmt = $this->ro->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get consignment summary statistics
     *
     * @param int|null $outletId Filter by outlet (optional)
     * @return array Statistics array
     */
    public function stats(?int $outletId = null): array {
        $where = $outletId !== null
            ? 'WHERE origin_outlet_id = :outlet OR dest_outlet_id = :outlet'
            : '';

        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'receiving' THEN 1 ELSE 0 END) as receiving,
                    SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM vend_consignments
                $where";

        $stmt = $this->ro->prepare($sql);

        if ($outletId !== null) {
            $stmt->bindValue(':outlet', $outletId, PDO::PARAM_INT);
        }

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Ensure RW connection is available (throws if not)
     *
     * @return void
     * @throws RuntimeException If RW connection not configured
     */
    private function ensureRw(): void {
        if (!$this->rw) {
            throw new RuntimeException('Read-write database connection not configured');
        }
    }
}
