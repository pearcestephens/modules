<?php
declare(strict_types=1);

use Transfers\Lib\Db;
use Transfers\Lib\Security;
use Transfers\Lib\Idempotency as Idem;
use Transfers\Lib\Validation as V;
use Transfers\Lib\Log;
use Transfers\Lib\Queue;
use Transfers\Lib\Helpers;

require_once __DIR__.'/../lib/Db.php';
require_once __DIR__.'/../lib/Security.php';
require_once __DIR__.'/../lib/Validation.php';
require_once __DIR__.'/../lib/Idempotency.php';
require_once __DIR__.'/../lib/Log.php';
require_once __DIR__.'/../lib/Queue.php';
require_once __DIR__.'/../lib/Helpers.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit;
    }
    Security::assertCsrf($_POST['csrf'] ?? '');

    $pdo = Db::pdo();
    $transferId = (int)($_POST['transfer_id'] ?? 0);
    if ($transferId <= 0) throw new RuntimeException('Missing transfer_id');

    $nonce = $_POST['nonce'] ?? bin2hex(random_bytes(8));
    $idemKey = Idem::makeKey('receive_submit', $transferId, $nonce);
    $idem = Idem::begin($pdo, $idemKey);
    if ($idem['cached'] ?? false) {
        http_response_code($idem['status_code']);
        echo $idem['response_json']; exit;
    }

    $t0 = microtime(true);
    $pdo->beginTransaction();

    $transfer = Helpers::fetchTransfer($pdo, $transferId);
    // Allow SENT|RECEIVING|PACKAGED|OPEN? (receive after packed/sent)
    Helpers::assertState($transfer, ['SENT','RECEIVING','PACKAGED','OPEN','PARTIAL','RECEIVED']);

    $lines = $_POST['lines'] ?? [];
    if (!is_array($lines) || empty($lines)) throw new RuntimeException('No lines provided');

    // Create receive header
    $pdo->prepare("INSERT INTO transfer_receipts (transfer_id, received_by, received_at, created_at) VALUES (?,?,NOW(), NOW())")
        ->execute([$transferId, Security::currentUserId()]);
    $receiptId = (int)$pdo->lastInsertId();

    // Read shipment/parcels for auto status updates (if provided, mark as received)
    $shipSel = $pdo->prepare("SELECT id FROM transfer_shipments WHERE transfer_id = ?");
    $shipSel->execute([$transferId]);
    $shipIds = array_map(fn($r)=>(int)$r['id'], $shipSel->fetchAll());

    $totalItems=0; $totalQty=0; $missingCount=0;
    $insRecItem = $pdo->prepare("INSERT INTO transfer_receipt_items (receipt_id, transfer_item_id, qty_received, condition, notes)
                                 VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE qty_received = VALUES(qty_received)");
    $updTI = $pdo->prepare("UPDATE transfer_items SET qty_received_total = LEAST(qty_sent_total, qty_received_total + ?) WHERE id = ?");
    foreach ($lines as $itemId => $r) {
        $itemId = (int)$itemId;
        $qtyRecv = V::positiveInt($r['qty_received'] ?? 0);
        $cond    = substr((string)($r['condition'] ?? 'ok'),0,32);
        $notes   = substr((string)($r['notes'] ?? ''),0,1000);

        // Fetch bounds
        $row = $pdo->prepare("SELECT id, qty_sent_total, qty_received_total FROM transfer_items WHERE id = ? AND transfer_id = ?");
        $row->execute([$itemId, $transferId]);
        $ti = $row->fetch();
        if (!$ti) throw new RuntimeException("Invalid item $itemId for transfer");

        $allowable = max(0, (int)$ti['qty_sent_total'] - (int)$ti['qty_received_total']);
        if ($qtyRecv > $allowable) $qtyRecv = $allowable; // clamp

        $insRecItem->execute([$receiptId, $itemId, $qtyRecv, $cond, $notes]);
        $updTI->execute([$qtyRecv, $itemId]);

        $totalItems++; $totalQty += $qtyRecv;
        if ($qtyRecv < (int)$ti['qty_sent_total']) $missingCount++;
    }

    // Mark parcels/shipment as received where appropriate
    if (!empty($shipIds)) {
        $in = implode(',', array_fill(0,count($shipIds),'?'));
        $pdo->prepare("UPDATE transfer_parcels SET status='received', received_at = NOW() WHERE shipment_id IN ($in)")
            ->execute($shipIds);
        $pdo->prepare("UPDATE transfer_shipments SET status='received', received_at = NOW(), received_by = ? WHERE id IN ($in)")
            ->execute(array_merge([Security::currentUserId()], $shipIds));
    }

    // Derive new transfer status/state
    // If every item qty_received_total == qty_sent_total → received; else partial
    $row = $pdo->prepare("SELECT SUM(qty_sent_total) s, SUM(qty_received_total) r FROM transfer_items WHERE transfer_id = ?");
    $row->execute([$transferId]);
    $agg = $row->fetch();
    $isComplete = ((int)$agg['s'] > 0 && (int)$agg['s'] === (int)$agg['r']);

    $pdo->prepare("UPDATE transfers SET status = ?, state = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$isComplete ? 'received' : 'partial', $isComplete ? 'RECEIVED' : 'RECEIVING', $transferId]);

    // Auto discrepancies for shortages
    if (!$isComplete) {
        $tiStmt = $pdo->prepare("SELECT id, product_id, qty_sent_total, qty_received_total FROM transfer_items WHERE transfer_id = ?");
        $tiStmt->execute([$transferId]);
        $insDisc = $pdo->prepare("INSERT INTO transfer_discrepancies (transfer_id, item_id, product_id, type, qty, notes, status, created_by, created_at)
                                  VALUES (?,?,?,?,?,?, 'open', ?, NOW())");
        while ($ti = $tiStmt->fetch()) {
            $diff = (int)$ti['qty_sent_total'] - (int)$ti['qty_received_total'];
            if ($diff > 0) {
                $insDisc->execute([$transferId, (int)$ti['id'], $ti['product_id'], 'missing', $diff, 'Auto-created on receive mismatch', Security::currentUserId()]);
            }
        }
    }

    // Metrics + logs
    Log::metrics($pdo, $transferId, [
        'total_items'=>$totalItems, 'total_quantity'=>$totalQty, 'status'=>$isComplete ? 'received' : 'partial',
        'processing_time_ms' => (int)((microtime(true)-$t0)*1000)
    ]);

    Log::audit($pdo, [
        'entity_pk'=>$transferId, 'transfer_pk'=>$transferId, 'transfer_id'=>(string)$transferId,
        'vend_transfer_id'=>$transfer['vend_transfer_id'] ?? null,
        'action'=>'RECEIVE_SUBMIT', 'outlet_from'=>$transfer['outlet_from'], 'outlet_to'=>$transfer['outlet_to'],
        'data_after'=>json_encode(['receipt_id'=>$receiptId,'total_received'=>$totalQty,'complete'=>$isComplete], JSON_UNESCAPED_SLASHES)
    ]);

    Log::unified($pdo, [
        'transfer_id'=>$transferId, 'event_type'=>$isComplete?'RECEIVED':'PARTIAL_RECEIVE',
        'message'=>$isComplete?'Transfer fully received':'Partial receipt recorded',
    ]);

    // Enqueue downstream sync
    $qid = Queue::enqueue($pdo, 'vend_consignment_sync', $transferId, 'received', [
        'transfer_id'=>$transferId, 'receipt_id'=>$receiptId, 'complete'=>$isComplete
    ]);

    $pdo->commit();
    $resp = ['ok'=>true,'transfer_id'=>$transferId,'receipt_id'=>$receiptId,'complete'=>$isComplete,'queue_log_id'=>$qid];
    Idem::finish($pdo, $idemKey, 200, $resp);
    echo json_encode($resp);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if (isset($idemKey, $pdo)) {
        Idem::finish($pdo, $idemKey, 500, ['ok'=>false,'error'=>$e->getMessage()]);
    }
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
