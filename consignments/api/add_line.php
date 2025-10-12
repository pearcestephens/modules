<?php
declare(strict_types=1);

use Transfers\Lib\Db;
use Transfers\Lib\Security;
use Transfers\Lib\Idempotency as Idem;
use Transfers\Lib\Log;

require_once __DIR__.'/../lib/Db.php';
require_once __DIR__.'/../lib/Security.php';
require_once __DIR__.'/../lib/Idempotency.php';
require_once __DIR__.'/../lib/Log.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }
    Security::assertCsrf($_POST['csrf'] ?? '');

    $pdo = Db::pdo();
    $transferId = (int)($_POST['transfer_id'] ?? 0);
    $productId  = trim((string)($_POST['product_id'] ?? ''));
    $requested  = max(0, (int)($_POST['qty_requested'] ?? 0));
    $nonce      = $_POST['nonce'] ?? bin2hex(random_bytes(8));
    if ($transferId <= 0 || $productId === '') throw new RuntimeException('transfer_id and product_id required');

    $idemKey = Idem::makeKey('add_line', $transferId, $productId.'|'.$nonce);
    $idem = Idem::begin($pdo, $idemKey);
    if ($idem['cached'] ?? false) { http_response_code($idem['status_code']); echo $idem['response_json']; exit; }

    $pdo->beginTransaction();

    // Ensure transfer exists
    $t = $pdo->prepare("SELECT * FROM transfers WHERE id = ? AND deleted_at IS NULL");
    $t->execute([$transferId]);
    if (!$t->fetch()) throw new RuntimeException('Transfer not found');

    // Upsert transfer_items (unique transfer_id + product_id)
    $sel = $pdo->prepare("SELECT id, qty_requested, qty_sent_total, qty_received_total FROM transfer_items WHERE transfer_id = ? AND product_id = ? AND deleted_at IS NULL LIMIT 1");
    $sel->execute([$transferId, $productId]);
    $row = $sel->fetch();

    if ($row) {
        $itemId = (int)$row['id'];
        if ($requested > 0) {
            $upd = $pdo->prepare("UPDATE transfer_items SET qty_requested = GREATEST(qty_requested, ?) WHERE id = ?");
            $upd->execute([$requested, $itemId]);
        }
    } else {
        $ins = $pdo->prepare("INSERT INTO transfer_items (transfer_id, product_id, qty_requested, qty_sent_total, qty_received_total, confirmation_status, created_at) VALUES (?,?,?,?,?, 'pending', NOW())");
        $ins->execute([$transferId, $productId, $requested, 0, 0]);
        $itemId = (int)$pdo->lastInsertId();
    }

    // Optional mirror into stock_products_to_transfer for legacy tooling
    // (If exists and helpful; ignore errors)
    try {
        $sp = $pdo->prepare("INSERT INTO stock_products_to_transfer (transfer_id, product_id, qty_to_transfer, min_qty_to_remain, created_at, updated_at)
                             VALUES (?,?,?,?, NOW(), NOW())
                             ON DUPLICATE KEY UPDATE qty_to_transfer = GREATEST(qty_to_transfer, VALUES(qty_to_transfer)), updated_at = NOW()");
        $sp->execute([$transferId, $productId, $requested, 0]);
    } catch (\Throwable $e) { /* best-effort */ }

    // Audit + unified logs
    Log::audit($pdo, [
        'entity_pk'=>$transferId, 'transfer_pk'=>$transferId, 'transfer_id'=>(string)$transferId,
        'action'=>'ADD_LINE', 'outlet_from'=>null, 'outlet_to'=>null,
        'data_after'=>json_encode(['item_id'=>$itemId,'product_id'=>$productId,'qty_requested'=>$requested], JSON_UNESCAPED_SLASHES)
    ]);
    Log::unified($pdo, [
        'transfer_id'=>$transferId, 'event_type'=>'ADD_LINE',
        'message'=>"Line added: product {$productId}, req {$requested}",
    ]);

    $pdo->commit();

    $resp = ['ok'=>true,'item_id'=>$itemId,'product_id'=>$productId,'qty_requested'=>$requested];
    Idem::finish($pdo, $idemKey, 200, $resp);
    echo json_encode($resp);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if (isset($idemKey, $pdo)) Idem::finish($pdo, $idemKey, 500, ['ok'=>false,'error'=>$e->getMessage()]);
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
