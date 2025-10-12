<?php
declare(strict_types=1);

use Transfers\Lib\Db;
use Transfers\Lib\Security;

require_once __DIR__.'/../lib/Db.php';
require_once __DIR__.'/../lib/Security.php';

header('Content-Type: application/json');

try {
    Security::assertCsrf($_POST['csrf'] ?? '');
    $q = trim((string)($_POST['q'] ?? ''));
    $outletId = trim((string)($_POST['outlet_from'] ?? ''));

    if (strlen($q) < 2) { echo json_encode(['ok'=>true, 'data'=>[]]); exit; }

    $pdo = Db::pdo();
    // CORRECTED: Use proper active flags and field names
    $sql = "SELECT v.id AS product_id, v.sku, v.name, v.brand, v.supplier_id,
                   COALESCE(i.current_amount, 0) as stock,
                   v.price_including_tax, v.avg_weight_grams
            FROM vend_products v
            LEFT JOIN vend_inventory i ON i.product_id = v.id AND i.outlet_id = :outlet
            WHERE v.is_active = 1 AND v.is_deleted = 0
              AND v.has_inventory = 1
              AND (v.sku LIKE :q OR v.name LIKE :q)
            ORDER BY v.name LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':q'=>'%'.$q.'%', ':outlet'=>$outletId ?: '']);
    $rows = $stmt->fetchAll() ?: [];

    echo json_encode(['ok'=>true, 'data'=>$rows]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
