<?php
declare(strict_types=1);

namespace Modules\Consignments\Repositories;

use Modules\Base\Repositories\BaseRepository;
use Modules\Consignments\Models\Transfer;
use Modules\Consignments\ValueObjects\TransferState;

/**
 * Transfer Repository - Clean data access for transfers
 * 
 * Encapsulates all database operations for transfers with proper
 * domain model mapping and query optimization.
 * 
 * @package Modules\Consignments\Repositories
 */
final class TransferRepository extends BaseRepository
{
    protected string $table = 'transfers';
    protected string $modelClass = Transfer::class;

    /**
     * Create a new transfer
     */
    public function create(Transfer $transfer): Transfer
    {
        $data = [
            'outlet_from' => $transfer->getOutletFrom(),
            'outlet_to' => $transfer->getOutletTo(),
            'kind' => $transfer->getKind(),
            'state' => $transfer->getState()->value,
            'delivery_mode' => $transfer->getDeliveryMode()->value,
            'created_by' => $transfer->getCreatedBy(),
            'metadata' => json_encode($transfer->getMetadata()),
            'created_at' => $transfer->getCreatedAt()->format('Y-m-d H:i:s'),
        ];

        $id = $this->insert($data);
        return $transfer->withId($id);
    }

    /**
     * Update existing transfer
     */
    public function update(Transfer $transfer): Transfer
    {
        $data = [
            'outlet_from' => $transfer->getOutletFrom(),
            'outlet_to' => $transfer->getOutletTo(),
            'kind' => $transfer->getKind(),
            'state' => $transfer->getState()->value,
            'delivery_mode' => $transfer->getDeliveryMode()->value,
            'freight_cost' => $transfer->getFreightCost(),
            'submitted_at' => $transfer->getSubmittedAt()?->format('Y-m-d H:i:s'),
            'submitted_by' => $transfer->getSubmittedBy(),
            'metadata' => json_encode($transfer->getMetadata()),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        $this->updateById($transfer->getId(), $data);
        return $transfer;
    }

    /**
     * Find transfer by ID with full hydration
     */
    public function findById(int $id): ?Transfer
    {
        $row = $this->selectOneWhere(['id' => $id, 'deleted_at' => null]);
        
        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * Find transfers with complex filtering
     */
    public function findWithFilters(array $filters, int $page = 1, int $limit = 20): array
    {
        $where = ['deleted_at' => null];
        $params = [];

        // Build dynamic WHERE clause
        if (!empty($filters['state'])) {
            $where[] = 'state = ?';
            $params[] = $filters['state'];
        }

        if (!empty($filters['kind'])) {
            $where[] = 'kind = ?';
            $params[] = $filters['kind'];
        }

        if (!empty($filters['outlet_from'])) {
            $where[] = 'outlet_from = ?';
            $params[] = $filters['outlet_from'];
        }

        if (!empty($filters['outlet_to'])) {
            $where[] = 'outlet_to = ?';
            $params[] = $filters['outlet_to'];
        }

        if (!empty($filters['created_after'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['created_after'];
        }

        if (!empty($filters['created_before'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['created_before'];
        }

        // Handle search query
        if (!empty($filters['search'])) {
            $where[] = '(CAST(id AS CHAR) LIKE ? OR metadata LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where) . 
               " ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Get transfers by state with optimized query
     */
    public function findByState(TransferState $state, int $limit = 100): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE state = ? AND deleted_at IS NULL 
                ORDER BY created_at DESC 
                LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$state->value, $limit]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Get pending transfers that need processing
     */
    public function findPendingTransfers(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE state IN ('submitted', 'processing') 
                AND deleted_at IS NULL 
                ORDER BY submitted_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Get transfer statistics for dashboard
     */
    public function getStatistics(): array
    {
        $sql = "SELECT 
                    state,
                    COUNT(*) as count,
                    COALESCE(SUM(freight_cost), 0) as total_freight
                FROM {$this->table} 
                WHERE deleted_at IS NULL 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY state";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Soft delete transfer
     */
    public function delete(int $id): bool
    {
        return $this->updateById($id, [
            'deleted_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Hydrate database row into Transfer domain model
     */
    protected function hydrate(array $row): Transfer
    {
        return new Transfer([
            'id' => (int)$row['id'],
            'outlet_from' => $row['outlet_from'],
            'outlet_to' => $row['outlet_to'],
            'kind' => $row['kind'],
            'state' => TransferState::from($row['state']),
            'delivery_mode' => DeliveryMode::from($row['delivery_mode']),
            'freight_cost' => $row['freight_cost'] ? (float)$row['freight_cost'] : null,
            'created_by' => (int)$row['created_by'],
            'submitted_by' => $row['submitted_by'] ? (int)$row['submitted_by'] : null,
            'metadata' => json_decode($row['metadata'] ?? '{}', true),
            'created_at' => new \DateTimeImmutable($row['created_at']),
            'updated_at' => $row['updated_at'] ? new \DateTimeImmutable($row['updated_at']) : null,
            'submitted_at' => $row['submitted_at'] ? new \DateTimeImmutable($row['submitted_at']) : null,
        ]);
    }
}