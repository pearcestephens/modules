<?php
/**
 * Consignment Service Layer - WORKING VERSION
 *
 * Adapted to actual vend_consignments schema
 *
 * @package CIS\Consignments
 * @version 1.0.1
 */

declare(strict_types=1);

use PDO;
use PDOException;
use RuntimeException;

final class ConsignmentService
{
    private PDO $ro;
    private ?PDO $rw;

    public function __construct(PDO $ro, ?PDO $rw) {
        $this->ro = $ro;
        $this->rw = $rw;
    }

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
     */
    public function recent(int $limit = 50): array {
        $limit = max(1, min($limit, 200));

        $stmt = $this->ro->prepare(
            "SELECT id, vend_number as ref_code, status, state,
                    outlet_from, outlet_to,
                    created_at, updated_at,
                    total_count, line_item_count
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
     */
    public function get(int $id): ?array {
        $stmt = $this->ro->prepare(
            "SELECT c.*, c.vend_number as ref_code,
                    c.line_item_count as item_count
             FROM vend_consignments c
             WHERE c.id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Search consignments
     */
    public function search(array $criteria): array {
        $where = [];
        $params = [];

        if (!empty($criteria['ref_code'])) {
            $where[] = "vend_number LIKE :ref";
            $params[':ref'] = '%' . $criteria['ref_code'] . '%';
        }

        if (!empty($criteria['outlet_from'])) {
            $where[] = "outlet_from = :outlet_from";
            $params[':outlet_from'] = $criteria['outlet_from'];
        }

        if (!empty($criteria['outlet_to'])) {
            $where[] = "outlet_to = :outlet_to";
            $params[':outlet_to'] = $criteria['outlet_to'];
        }

        if (!empty($criteria['status'])) {
            $where[] = "status = :status";
            $params[':status'] = $criteria['status'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit = isset($criteria['limit']) ? (int)$criteria['limit'] : 50;

        $sql = "SELECT id, vend_number as ref_code, status, state,
                       outlet_from, outlet_to,
                       created_at, updated_at
                FROM vend_consignments
                {$whereClause}
                ORDER BY id DESC
                LIMIT {$limit}";

        $stmt = $this->ro->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get statistics
     */
    public function stats(?string $outlet = null): array {
        $where = $outlet ? "WHERE outlet_from = :outlet OR outlet_to = :outlet" : '';
        $params = $outlet ? [':outlet' => $outlet] : [];

        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'OPEN' THEN 1 ELSE 0 END) as open,
                    SUM(CASE WHEN status = 'SENT' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'RECEIVED' THEN 1 ELSE 0 END) as received,
                    SUM(total_count) as total_items
                FROM vend_consignments
                {$where}";

        $stmt = $this->ro->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Create new consignment (minimal implementation)
     */
    public function create(array $data): int {
        if (!$this->rw) {
            throw new RuntimeException('Write operations not available (read-only connection)');
        }

        $sql = "INSERT INTO vend_consignments
                (public_id, outlet_from, outlet_to, created_by, state, status, created_at)
                VALUES
                (:public_id, :outlet_from, :outlet_to, :created_by, 'DRAFT', 'OPEN', NOW())";

        $stmt = $this->rw->prepare($sql);
        $stmt->execute([
            ':public_id' => uniqid('CONS-', true),
            ':outlet_from' => $data['outlet_from'] ?? null,
            ':outlet_to' => $data['outlet_to'] ?? null,
            ':created_by' => $data['created_by'] ?? 0,
        ]);

        return (int)$this->rw->lastInsertId();
    }

    /**
     * Update consignment status
     */
    public function updateStatus(int $id, string $status): bool {
        if (!$this->rw) {
            throw new RuntimeException('Write operations not available (read-only connection)');
        }

        $stmt = $this->rw->prepare(
            "UPDATE vend_consignments
             SET status = :status, updated_at = NOW()
             WHERE id = :id"
        );

        return $stmt->execute([':id' => $id, ':status' => $status]);
    }
}
