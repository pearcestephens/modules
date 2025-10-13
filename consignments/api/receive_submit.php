<?php
/**
 * Receive Submit API Endpoint - Enhanced for BASE Receive System
 * 
 * Handles final submission of receive operations (complete/partial) with
 * Lightspeed integration, inventory updates, and enterprise-grade validation.
 * 
 * @package CIS\Consignments\API
 * @version 2.0.0 - Enhanced for BASE system
 * @created 2025-10-12
 */

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

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// CORS headers for AJAX requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Support both traditional POST and JSON input for BASE system
    $input_data = null;
    $is_json_request = false;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success'=>false,'error'=>'Method not allowed. Use POST.']); 
        exit;
    }
    
    // Check if this is a JSON request (from BASE system)
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($content_type, 'application/json') !== false) {
        $is_json_request = true;
        $json_input = file_get_contents('php://input');
        $input_data = json_decode($json_input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON data: ' . json_last_error_msg());
        }
    } else {
        // Traditional form POST
        $input_data = $_POST;
    }
    
    // CSRF check for traditional requests
    if (!$is_json_request) {
        Security::assertCsrf($input_data['csrf'] ?? '');
    }

    $pdo = Db::pdo();
    
    // Extract transfer details
    $transferId = (int)($input_data['transfer_id'] ?? 0);
    if ($transferId <= 0) throw new RuntimeException('Missing transfer_id');
    
    // For BASE system JSON requests, handle different data structure
    if ($is_json_request && isset($input_data['action']) && $input_data['action'] === 'submit') {
        // BASE system format
        $transfer_mode = $input_data['transfer_mode'] ?? '';
        $items = $input_data['items'] ?? [];
        $totals = $input_data['totals'] ?? [];
        $status = $input_data['status'] ?? '';
        $notes = $input_data['notes'] ?? '';
        
        // Convert BASE format to traditional format
        $lines = [];
        foreach ($items as $item) {
            $lines[] = [
                'item_id' => $item['item_id'],
                'product_id' => $item['product_id'],
                'qty_requested' => $item['qty_requested'],
                'qty_received' => $item['qty_received']
            ];
        }
        $input_data['lines'] = $lines;
        $input_data['action'] = $status; // COMPLETE or PARTIAL
    } else {
        // Traditional format
        $lines = $input_data['lines'] ?? [];
    }

    $nonce = $input_data['nonce'] ?? bin2hex(random_bytes(8));
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
    Helpers::assertState($transfer, ['SENT','RECEIVING','PACKAGED','OPEN','PARTIAL','RECEIVED','PACKED','IN_TRANSIT','PARTIAL_RECEIVED']);

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

    $pdo->prepare("UPDATE transfers SET state = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$isComplete ? 'RECEIVED' : 'RECEIVING', $transferId]);

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
    
    // Format response for BASE system compatibility
    if ($is_json_request) {
        $response = [
            'success' => true,
            'message' => $isComplete ? 'Receive completed successfully' : 'Partial receive saved successfully',
            'data' => [
                'transfer_id' => $transferId,
                'receipt_id' => $receiptId,
                'complete' => $isComplete,
                'queue_log_id' => $qid,
                'items_processed' => $totalItems,
                'total_quantity' => $totalQty,
                'missing_count' => $missingCount,
                'final_status' => $isComplete ? 'RECEIVED' : 'PARTIAL_RECEIVED'
            ],
            'redirect_url' => "/modules/consignments/?transfer_id={$transferId}&view=details",
            'request_id' => uniqid('req_', true)
        ];
        
        // Add warnings for BASE system
        $warnings = [];
        if ($missingCount > 0) {
            $warnings[] = "{$missingCount} item(s) had shortages";
        }
        if (!empty($warnings)) {
            $response['warnings'] = $warnings;
        }
        
        $resp = $response;
    } else {
        // Traditional format with optional redirect_url for UI convenience
        $flash = $isComplete ? 'receive_complete' : 'receive_partial';
        $resp = [
            'ok' => true,
            'transfer_id' => $transferId,
            'receipt_id' => $receiptId,
            'complete' => $isComplete,
            'queue_log_id' => $qid,
            'redirect_url' => "/modules/consignments/?flash={$flash}&tx={$transferId}"
        ];
    }
    
    Idem::finish($pdo, $idemKey, 200, $resp);
    echo json_encode($resp);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if (isset($idemKey, $pdo)) {
        Idem::finish($pdo, $idemKey, 500, ['success'=>false,'error'=>$e->getMessage()]);
    }
    http_response_code(500);
    
    // Format error response for BASE system compatibility
    if ($is_json_request ?? false) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'request_id' => uniqid('req_', true)
        ]);
    } else {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
}
