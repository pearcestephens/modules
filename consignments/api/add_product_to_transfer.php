<?php
declare(strict_types=1);

/**
 * Add (or increment) a product on a transfer.
 * POST JSON: { transfer_id:int, product_id:string, qty:int, mode:'increment'|'set' }
 * Returns: { success:true, qty_requested:int }
 */

header('Content-Type: application/json');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method Not Allowed']); exit; }

    require_once dirname(__DIR__) . '/bootstrap.php';
    $raw = file_get_contents('php://input') ?: '';
    $j = json_decode($raw, true);
    if (!is_array($j)) { $j = $_POST; }

    $tid  = (int)($j['transfer_id'] ?? 0);
    $pid  = trim((string)($j['product_id'] ?? ''));
    $qty  = max(1, (int)($j['qty'] ?? 1));
    $mode = (string)($j['mode'] ?? 'increment');

    if ($tid <= 0 || $pid === '') { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Invalid input']); exit; }

    $pdo = cis_resolve_pdo();
    $pdo->beginTransaction();

    // Ensure transfer OPEN/PACKING
    $chk = $pdo->prepare("SELECT id, state FROM transfers WHERE id=? FOR UPDATE");
    $chk->execute([$tid]);
    $t = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$t || !in_array($t['state'], ['OPEN','PACKING'], true)) { throw new RuntimeException('Transfer not editable'); }

    if ($mode === 'set') {
        $sql = "
          INSERT INTO transfer_items (transfer_id, product_id, qty_requested, qty_sent_total, created_at, updated_at)
          VALUES (?, ?, ?, 0, NOW(), NOW())
          ON DUPLICATE KEY UPDATE qty_requested = VALUES(qty_requested), updated_at = NOW()
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tid, $pid, $qty]);
        $qtyReq = $qty;
    } else {
        // increment
        $sql = "
          INSERT INTO transfer_items (transfer_id, product_id, qty_requested, qty_sent_total, created_at, updated_at)
          VALUES (?, ?, ?, 0, NOW(), NOW())
          ON DUPLICATE KEY UPDATE qty_requested = qty_requested + VALUES(qty_requested), updated_at = NOW()
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tid, $pid, $qty]);

        $g = $pdo->prepare("SELECT qty_requested FROM transfer_items WHERE transfer_id=? AND product_id=?");
        $g->execute([$tid, $pid]);
        $qtyReq = (int)$g->fetchColumn();
    }

    $pdo->commit();
    echo json_encode(['success'=>true, 'qty_requested'=>$qtyReq]);
} catch (Throwable $e) {
    if (!empty($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
