<?php
declare(strict_types=1);

use Transfers\Lib\Db;
use Transfers\Lib\Security;
use Transfers\Lib\Validation as V;
use Transfers\Lib\Idempotency as Idem;
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
    // BOT BYPASS for testing
    $bot_bypass = ($_SERVER['BOT_BYPASS_AUTH'] ?? $_ENV['BOT_BYPASS_AUTH'] ?? $_GET['bot'] ?? false);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$bot_bypass) {
        http_response_code(405);
        echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit;
    }

    if (!$bot_bypass) {
        Security::assertCsrf($_POST['csrf'] ?? '');
    }

    $pdo = Db::pdo();
    $transferId = (int)($_POST['transfer_id'] ?? 0);
    if ($transferId <= 0) throw new RuntimeException('Missing transfer_id');

    $nonce = $_POST['nonce'] ?? bin2hex(random_bytes(8));
    $idemKey = Idem::makeKey('pack_submit', $transferId, $nonce);

    // idempotency pre-check
    $idem = Idem::begin($pdo, $idemKey);
    if ($idem['cached'] ?? false) {
        http_response_code($idem['status_code']);
        echo $idem['response_json']; exit;
    }

    $t0 = microtime(true);
    $pdo->beginTransaction();

    $transfer = Helpers::fetchTransfer($pdo, $transferId);
    // Allow OPEN or PACKING; move OPEN → PACKING
    Helpers::assertState($transfer, ['OPEN','PACKING','PACKAGED']);

    // Input: products table lines
    // lines[item_id] = { product_id, qty_planned, qty_packed }
    $lines = $_POST['lines'] ?? [];
    if (!is_array($lines) || empty($lines)) throw new RuntimeException('No lines provided');

    $deliveryModeRaw = $_POST['delivery_mode'] ?? 'manual_courier';
    if (!in_array($deliveryModeRaw, ['manual_courier','pickup','dropoff','internal_drive'], true)) {
        throw new RuntimeException('Invalid delivery_mode');
    }
    $deliveryMode = Helpers::normalizeDeliveryMode($deliveryModeRaw);

    $boxCount = V::positiveInt($_POST['box_count'] ?? 0);
    if ($boxCount < 1) throw new RuntimeException('Box count required');

    // per-box tracking
    $trackingByBox = $_POST['tracking'] ?? [];
    if (!is_array($trackingByBox) || count($trackingByBox) !== $boxCount) {
        throw new RuntimeException('Tracking numbers required per box');
    }
    foreach ($trackingByBox as $i => $trk) {
        $trackingByBox[$i] = V::nonEmpty((string)$trk, "tracking[$i]");
    }

    // optional per-box dims/weights
    $weights = $_POST['weight_grams'] ?? [];
    $dims    = $_POST['dims'] ?? []; // dims[i][l|w|h]

    // optional per-box allocations: parcel_allocations[box_index][item_id] = qty
    $alloc = $_POST['parcel_allocations'] ?? []; // array
    if (!is_array($alloc)) $alloc = [];

    // Compute totals + validate line quantities
    $totalItems = 0; $totalQty = 0;
    foreach ($lines as $itemId => $r) {
        $itemId = (int)$itemId;
        $qtyPacked = V::positiveInt($r['qty_packed'] ?? 0);
        $prodId    = V::nonEmpty((string)($r['product_id'] ?? ''), 'product_id');
        $totalItems++;
        $totalQty += $qtyPacked;

        // Bounds check against transfer_items
        $row = $pdo->prepare("SELECT id, qty_requested, qty_sent_total FROM transfer_items WHERE id = ? AND transfer_id = ?");
        $row->execute([$itemId, $transferId]);
        $ti = $row->fetch();
        if (!$ti) throw new RuntimeException("Invalid item $itemId for transfer $transferId");

        $newSent = (int)$ti['qty_sent_total'] + $qtyPacked;
        if ($newSent > (int)$ti['qty_requested']) {
            throw new RuntimeException("Packed qty exceeds requested for item $itemId");
        }

        $upd = $pdo->prepare("UPDATE transfer_items SET qty_sent_total = ? WHERE id = ?");
        $upd->execute([$newSent, $itemId]);
    }

    // move transfer state if needed
    if ($transfer['state'] === 'OPEN') {
        $pdo->prepare("UPDATE transfers SET state = 'PACKING', updated_at = NOW() WHERE id = ?")->execute([$transferId]);
        Log::unified($pdo, [
            'transfer_id'=>$transferId,
            'event_type'=>'PACKING_STARTED',
            'message'=>'Packing started from UI',
        ]);
    }

    // Create shipment header (one shipment for this pack action)
    $insShip = $pdo->prepare("INSERT INTO transfer_shipments
       (transfer_id, delivery_mode, status, packed_at, packed_by, created_at, nicotine_in_shipment)
       VALUES (?, ?, 'packed', NOW(), ?, NOW(), ?)");
    $nicotine = (int)($_POST['nicotine_in_shipment'] ?? 0);
    $insShip->execute([$transferId, $deliveryMode, Security::currentUserId(), $nicotine]);
    $shipmentId = (int)$pdo->lastInsertId();

    // Link items to shipment (sum packed posted now)
    $insShipItem = $pdo->prepare("INSERT INTO transfer_shipment_items (shipment_id, item_id, qty_sent, qty_received)
                                  VALUES (?, ?, ?, 0)
                                  ON DUPLICATE KEY UPDATE qty_sent = VALUES(qty_sent)");
    foreach ($lines as $itemId => $r) {
        $qtyPacked = V::positiveInt($r['qty_packed'] ?? 0);
        if ($qtyPacked > 0) {
            $insShipItem->execute([$shipmentId, (int)$itemId, $qtyPacked]);
        }
    }

    // Create parcels with manual tracking numbers
    $insParcel = $pdo->prepare("INSERT INTO transfer_parcels
        (shipment_id, box_number, tracking_number, courier, weight_grams, length_mm, width_mm, height_mm, status, created_at, parcel_number)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'labelled', NOW(), ?)");
    $courierName = $deliveryMode === 'manual' ? 'MANUAL' : strtoupper($deliveryMode);
    for ($i=0; $i<$boxCount; $i++) {
        $tg = (int)($weights[$i] ?? 0);
        $d  = $dims[$i] ?? [];
        $L  = (int)($d['l'] ?? 0);
        $W  = (int)($d['w'] ?? 0);
        $H  = (int)($d['h'] ?? 0);
        $insParcel->execute([$shipmentId, $i+1, $trackingByBox[$i], $courierName, $tg, $L, $W, $H, $i+1]);
    }

    // Get parcel ids back
    $parcels = $pdo->prepare("SELECT id, box_number FROM transfer_parcels WHERE shipment_id = ? ORDER BY box_number ASC");
    $parcels->execute([$shipmentId]);
    $parcelRows = $parcels->fetchAll();

    // Optional: parcel_items allocation
    if (!empty($alloc)) {
        $insPI = $pdo->prepare("INSERT INTO transfer_parcel_items (parcel_id, item_id, qty, qty_received) VALUES (?,?,?,0)
                                ON DUPLICATE KEY UPDATE qty = VALUES(qty)");
        foreach ($alloc as $idx => $items) {
            $idx = (int)$idx; // 0-based
            $parcelId = $parcelRows[$idx]['id'] ?? null;
            if (!$parcelId) continue;
            foreach ((array)$items as $itemId => $q) {
                $qty = (int)$q;
                if ($qty > 0) $insPI->execute([$parcelId, (int)$itemId, $qty]);
            }
        }
    }

    // Labels (manual tracking mirrors as "labels" for consistency)
    $insLbl = $pdo->prepare("INSERT INTO transfer_labels (transfer_id, carrier_code, tracking, label_url, spooled, created_by, created_at)
                             VALUES (?,?,?,?,0,?,NOW())
                             ON DUPLICATE KEY UPDATE label_url = VALUES(label_url)");
    foreach ($trackingByBox as $trk) {
        $insLbl->execute([$transferId, $courierName, $trk, '', Security::currentUserId()]);
    }

    // Update transfer aggregates
    $totWeightG = array_sum(array_map('intval', $weights ?: []));
    $pdo->prepare("UPDATE transfers
                   SET total_boxes = total_boxes + ?, total_weight_g = total_weight_g + ?, state = 'PACKAGED', updated_at = NOW()
                   WHERE id = ?")
        ->execute([$boxCount, $totWeightG, $transferId]);

    // Metrics + logs
    Log::metrics($pdo, $transferId, [
        'source_outlet_id'      => null,
        'destination_outlet_id' => null,
        'total_items'           => $totalItems,
        'total_quantity'        => $totalQty,
        'status'                => 'packed',
        'processing_time_ms'    => (int)((microtime(true)-$t0)*1000),
        'metadata'              => ['delivery_mode'=>$deliveryMode,'boxes'=>$boxCount]
    ]);

    Log::audit($pdo, [
        'entity_pk'=>$transferId, 'transfer_pk'=>$transferId, 'transfer_id'=>(string)$transferId,
        'vend_consignment_id'=> $transfer['vend_transfer_id'] ?? null, 'vend_transfer_id'=>$transfer['vend_transfer_id'] ?? null,
        'action'=>'PACK_SUBMIT', 'outlet_from'=>$transfer['outlet_from'], 'outlet_to'=>$transfer['outlet_to'],
        'data_after'=>json_encode(['boxes'=>$boxCount,'tracking'=>$trackingByBox], JSON_UNESCAPED_SLASHES)
    ]);

    Log::unified($pdo, [
        'transfer_id'=>$transferId,
        'shipment_id'=>$shipmentId,
        'event_type'=>'PACKAGED',
        'message'=>'Shipment packed (manual)',
        'event_data'=>json_encode(['delivery_mode'=>$deliveryMode,'box_count'=>$boxCount], JSON_UNESCAPED_SLASHES),
    ]);

    // Enqueue for consignment sync / downstream processing
    $qid = Queue::enqueue($pdo, 'vend_consignment_sync', $transferId, 'packaged', [
        'transfer_id'=>$transferId, 'shipment_id'=>$shipmentId, 'delivery_mode'=>$deliveryMode, 'tracking'=>$trackingByBox
    ]);

    $pdo->commit();

    $resp = [
        'ok' => true,
        'transfer_id' => $transferId,
        'shipment_id' => $shipmentId,
        'queue_log_id' => $qid,
        // Optional client-side redirect; UI will prefer this if present
        'redirect_url' => "/modules/consignments/?flash=pack_success&tx={$transferId}"
    ];
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
