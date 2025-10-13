<?php
declare(strict_types=1);

use Transfers\Lib\Db;
use Transfers\Lib\Security;
use Transfers\Lib\Log;

require_once __DIR__.'/../lib/Db.php';
require_once __DIR__.'/../lib/Security.php';
require_once __DIR__.'/../lib/Log.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }
    Security::assertCsrf($_POST['csrf'] ?? '');

    $pdo = Db::pdo();
    $transferId = (int)($_POST['transfer_id'] ?? 0);
    $itemId     = (int)($_POST['item_id'] ?? 0);
    if ($transferId <= 0 || $itemId <= 0) throw new RuntimeException('transfer_id and item_id required');

    $pdo->beginTransaction();

    // Check not received/sent already
    $line = $pdo->prepare("SELECT id, qty_sent_total, qty_received_total FROM transfer_items WHERE id = ? AND transfer_id = ? AND deleted_by IS NULL FOR UPDATE");
    $line->execute([$itemId,$transferId]);
    $li = $line->fetch();
    if (!$li) throw new RuntimeException('Line not found');
    if ((int)$li['qty_sent_total'] > 0 || (int)$li['qty_received_total'] > 0) {
        throw new RuntimeException('Cannot remove a line that has already been packed or received');
    }

    $pdo->prepare("UPDATE transfer_items SET deleted_at = NOW(), deleted_by = ? WHERE id = ?")
        ->execute([Security::currentUserId(), $itemId]);

    // best-effort legacy mirror
    try {
        $pdo->prepare("UPDATE stock_products_to_transfer SET deleted_at = NOW() WHERE transfer_id = ? AND product_id IN (SELECT product_id FROM transfer_items WHERE id = ?)")
            ->execute([$transferId, $itemId]);
    } catch (\Throwable $e) {}

    Log::audit($pdo, [
        'entity_pk'=>$transferId,'transfer_pk'=>$transferId,'transfer_id'=>(string)$transferId,
        'action'=>'REMOVE_LINE','data_after'=>json_encode(['item_id'=>$itemId], JSON_UNESCAPED_SLASHES)
    ]);

    $pdo->commit();
    echo json_encode(['ok'=>true]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
