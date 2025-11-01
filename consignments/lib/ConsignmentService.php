<?php
declare(strict_types=1);

namespace Consignments;

use PDO;

final class ConsignmentService
{
    public function __construct(private PDO $ro, private PDO $rw) {}

    public static function make(): self { return new self(db_ro(), db_rw_or_null() ?? db_ro()); }

    public function recent(int $limit=50): array {
        $st = $this->ro->prepare("SELECT id, ref_code, status, origin_outlet_id, dest_outlet_id, created_at
                                  FROM consignments ORDER BY id DESC LIMIT :lim");
        $st->bindValue(':lim', max(1, min($limit, 200)), PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    public function get(int $id): ?array {
        $st = $this->ro->prepare("SELECT c.*, (SELECT COUNT(*) FROM consignment_items i WHERE i.consignment_id=c.id) item_count
                                  FROM consignments c WHERE c.id=:id LIMIT 1");
        $st->execute([':id'=>$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function items(int $id): array {
        $st = $this->ro->prepare("SELECT id, consignment_id, product_id, sku, qty, packed_qty, status
                                  FROM consignment_items WHERE consignment_id=:id ORDER BY id ASC");
        $st->execute([':id'=>$id]);
        return $st->fetchAll();
    }

    public function create(array $p): int {
        $st = $this->rw->prepare("INSERT INTO consignments
          (ref_code, status, origin_outlet_id, dest_outlet_id, created_by, created_at)
           VALUES (:ref,:st,:o,:d,:by,NOW())");
        $st->execute([
            ':ref'=>(string)($p['ref_code'] ?? ''), ':st'=>(string)($p['status'] ?? 'draft'),
            ':o'=>(int)($p['origin_outlet_id'] ?? 0), ':d'=>(int)($p['dest_outlet_id'] ?? 0),
            ':by'=>(int)($p['created_by'] ?? 0),
        ]);
        return (int)$this->rw->lastInsertId();
    }

    public function addItem(int $cid, array $i): int {
        $st = $this->rw->prepare("INSERT INTO consignment_items
          (consignment_id, product_id, sku, qty, packed_qty, status, created_at)
           VALUES (:cid,:pid,:sku,:qty,:pqty,:st,NOW())");
        $st->execute([
            ':cid'=>$cid, ':pid'=>(int)($i['product_id'] ?? 0), ':sku'=>(string)($i['sku'] ?? ''),
            ':qty'=>(int)($i['qty'] ?? 0), ':pqty'=>(int)($i['packed_qty'] ?? 0),
            ':st'=>(string)($i['status'] ?? 'pending'),
        ]);
        return (int)$this->rw->lastInsertId();
    }

    public function setStatus(int $id, string $st): bool {
        $stt = $this->rw->prepare("UPDATE consignments SET status=:st WHERE id=:id LIMIT 1");
        return $stt->execute([':st'=>$st, ':id'=>$id]);
    }
}
