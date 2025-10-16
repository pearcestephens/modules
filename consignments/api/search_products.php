<?php
declare(strict_types=1);

/**
 * Product search (power mode)
 * POST JSON: { q: string, outlet_id?: string, limit?: int }
 * Returns: { success: true, results: [{product_id,name,sku,thumb,stock}] }
 * - Fixes HY093 on MariaDB 10.5 by interpolating validated LIMIT
 */

header('Content-Type: application/json');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method Not Allowed']); exit; }
    require_once dirname(__DIR__) . '/bootstrap.php';

    $j = json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST;
    $q = trim((string)($j['q'] ?? ''));
    $outlet = trim((string)($j['outlet_id'] ?? ''));
    $limit = (int)($j['limit'] ?? 20);
    if ($limit < 1) $limit = 20; if ($limit > 50) $limit = 50;

    if ($q === '') { echo json_encode(['success'=>true, 'results'=>[]]); exit; }

    $pdo = cis_resolve_pdo();
    // Interpolate LIMIT (validated int) to avoid HY093 on MariaDB 10.5
    $sql = "
        SELECT v.id AS product_id,
               v.name,
               v.sku,
               v.image_thumbnail_url AS thumb,
               COALESCE(vi.current_amount, 0) AS stock
          FROM vend_products v
     LEFT JOIN vend_inventory vi ON vi.product_id = v.id AND vi.outlet_id = :outlet
         WHERE (v.name LIKE :q OR v.sku LIKE :q)
           AND (v.is_deleted IS NULL OR v.is_deleted = 0)
      ORDER BY v.name ASC
      LIMIT {$limit}
    ";
    $stmt = $pdo->prepare($sql);
    $like = '%'.$q.'%';
    $stmt->bindValue(':outlet', $outlet, PDO::PARAM_STR);
    $stmt->bindValue(':q', $like, PDO::PARAM_STR);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['success'=>true, 'results'=>$rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
