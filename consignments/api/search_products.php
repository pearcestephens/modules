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
    // Example: find by SKU or name; join a hypothetical outlet inventory view if you have it.
    $sql = "SELECT v.id AS product_id, v.sku, v.name, COALESCE(i.on_hand,0) as stock
            FROM vend_products v
            LEFT JOIN vend_inventory i ON i.product_id = v.id AND i.outlet_id = :outlet
            WHERE v.deleted_at IS NULL
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
