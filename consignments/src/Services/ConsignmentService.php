<?php
declare(strict_types=1);

namespace Consignments\Services;

use PDO;

final class ConsignmentService
{
    public function __construct(private PDO $ro, private PDO $rw) {}

    public static function make(): self
    {
        return new self(db_ro(), db_rw_or_null() ?? db_ro());
    }

    public function recent(int $limit = 50): array
    {
        $statement = $this->ro->prepare(
            'SELECT id, ref_code, status, origin_outlet_id, dest_outlet_id, created_at
             FROM consignments ORDER BY id DESC LIMIT :limit'
        );
        $statement->bindValue(':limit', max(1, min($limit, 200)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function get(int $id): ?array
    {
        $statement = $this->ro->prepare(
            'SELECT c.*, (SELECT COUNT(*) FROM consignment_items i WHERE i.consignment_id = c.id) AS item_count
             FROM consignments c WHERE c.id = :id LIMIT 1'
        );
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function items(int $id): array
    {
        $statement = $this->ro->prepare(
            'SELECT id, consignment_id, product_id, sku, qty, packed_qty, status
             FROM consignment_items WHERE consignment_id = :id ORDER BY id ASC'
        );
        $statement->execute([':id' => $id]);

        return $statement->fetchAll();
    }

    public function create(array $payload): int
    {
        $statement = $this->rw->prepare(
            'INSERT INTO consignments (ref_code, status, origin_outlet_id, dest_outlet_id, created_by, created_at)
             VALUES (:ref, :status, :origin, :dest, :created_by, NOW())'
        );
        $statement->execute([
            ':ref' => (string) ($payload['ref_code'] ?? ''),
            ':status' => (string) ($payload['status'] ?? 'draft'),
            ':origin' => (int) ($payload['origin_outlet_id'] ?? 0),
            ':dest' => (int) ($payload['dest_outlet_id'] ?? 0),
            ':created_by' => (int) ($payload['created_by'] ?? 0),
        ]);

        return (int) $this->rw->lastInsertId();
    }

    public function addItem(int $consignmentId, array $item): int
    {
        $statement = $this->rw->prepare(
            'INSERT INTO consignment_items (consignment_id, product_id, sku, qty, packed_qty, status, created_at)
             VALUES (:consignment, :product, :sku, :qty, :packed, :status, NOW())'
        );
        $statement->execute([
            ':consignment' => $consignmentId,
            ':product' => (int) ($item['product_id'] ?? 0),
            ':sku' => (string) ($item['sku'] ?? ''),
            ':qty' => (int) ($item['qty'] ?? 0),
            ':packed' => (int) ($item['packed_qty'] ?? 0),
            ':status' => (string) ($item['status'] ?? 'pending'),
        ]);

        return (int) $this->rw->lastInsertId();
    }

    public function setStatus(int $id, string $status): bool
    {
        $statement = $this->rw->prepare('UPDATE consignments SET status = :status WHERE id = :id LIMIT 1');

        return $statement->execute([':status' => $status, ':id' => $id]);
    }
}
