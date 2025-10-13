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
    $qty        = max(0, (int)($_POST['qty_requested'] ?? 0));
    if ($transferId <= 0 || $itemId <= 0) throw new RuntimeException('transfer_id and item_id required');

    $pdo->beginTransaction();

    $row = $pdo->prepare("SELECT id, transfer_id, qty_requested FROM transfer_items WHERE id = ? AND transfer_id = ? AND deleted_by IS NULL");
    $row->execute([$itemId, $transferId]);
    $line = $row->fetch();
    if (!$line) throw new RuntimeException('Line not found');

    $pdo->prepare("UPDATE transfer_items SET qty_requested = ? WHERE id = ?")
        ->execute([$qty, $itemId]);

    Log::audit($pdo, [
        'entity_pk'=>$transferId,'transfer_pk'=>$transferId,'transfer_id'=>(string)$transferId,
        'action'=>'UPDATE_LINE_QTY','data_after'=>json_encode(['item_id'=>$itemId,'qty_requested'=>$qty], JSON_UNESCAPED_SLASHES)
    ]);

    $pdo->commit();
    echo json_encode(['ok'=>true,'item_id'=>$itemId,'qty_requested'=>$qty]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
