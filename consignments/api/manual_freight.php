<?php
declare(strict_types=1);

/**
 * Manual Freight Console
 * - Manual: boxes read-only; show warning if user attempts to lower below tracking count (handled in UI)
 * - Pickup/Dropoff: boxes are writable
 * - Saves transfer_shipments + transfer_parcels and updates transfers.total_boxes
 */

header('Content-Type: application/json');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method Not Allowed']); exit; }

    require_once dirname(__DIR__) . '/bootstrap.php';
    $j = json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST;

    $tid   = (int)($j['transfer_id'] ?? 0);
    $mode  = (string)($j['mode'] ?? 'manual');
    $cour  = trim((string)($j['courier_name'] ?? ''));
    $nums  = $j['tracking_numbers'] ?? [];
    if (!is_array($nums)) $nums = [];
    $nums  = array_values(array_unique(array_filter(array_map('trim',$nums))));

    if ($tid <= 0 || !in_array($mode, ['manual','pickup','dropoff'], true)) {
        http_response_code(400); echo json_encode(['success'=>false,'error'=>'Invalid input']); exit;
    }

    $pdo = cis_resolve_pdo();
    $pdo->beginTransaction();

    // upsert shipment
    $s = $pdo->prepare("SELECT id FROM transfer_shipments WHERE transfer_id=? FOR UPDATE");
    $s->execute([$tid]);
    $sid = (int)($s->fetchColumn() ?: 0);

    if ($sid === 0) {
        $pdo->prepare("INSERT INTO transfer_shipments (transfer_id, delivery_mode, status, carrier_name, created_at, updated_at)
                       VALUES (?, ?, 'packed', ?, NOW(), NOW())")->execute([$tid, $mode, $cour ?: null]);
        $sid = (int)$pdo->lastInsertId();
    } else {
        $pdo->prepare("UPDATE transfer_shipments SET delivery_mode=?, carrier_name=?, updated_at=NOW() WHERE id=?")
            ->execute([$mode, $cour ?: null, $sid]);
    }

    // reconcile parcels with tracking numbers
    $cur = $pdo->prepare("SELECT id, tracking_number FROM transfer_parcels WHERE shipment_id=? AND deleted_at IS NULL ORDER BY box_number ASC");
    $cur->execute([$sid]);
    $existing = $cur->fetchAll(PDO::FETCH_KEY_PAIR) ?: []; // id => tracking

    // delete removed
    $toDel = [];
    foreach ($existing as $pid=>$tn) if (!in_array($tn, $nums, true)) $toDel[] = (int)$pid;
    if ($toDel) {
        $pdo->prepare("UPDATE transfer_parcels SET deleted_at=NOW(), status='cancelled' WHERE id IN (".implode(',',array_map('intval',$toDel)).")")->execute();
    }

    // insert new, renumber boxes
    $pdo->prepare("DELETE FROM transfer_parcels WHERE shipment_id=? AND deleted_at IS NULL")->execute([$sid]);
    $insP = $pdo->prepare("INSERT INTO transfer_parcels (shipment_id, box_number, tracking_number, status, created_at, updated_at, parcel_number)
                           VALUES (?, ?, ?, 'labelled', NOW(), NOW(), ?)");
    $boxNo = 0;
    foreach ($nums as $n) { $boxNo++; $insP->execute([$sid, $boxNo, $n, $boxNo]); }

    $totalBoxes = count($nums);
    // Manual mode: boxes reflect tracking count strictly (read-only in UI already)
    // Pickup/Dropoff: if 'boxes' provided, keep max(tracking count, boxes)
    if (in_array($mode, ['pickup','dropoff'], true)) {
        $b = isset($j['boxes']) ? max(0, (int)$j['boxes']) : $totalBoxes;
        $totalBoxes = max($totalBoxes, $b);
    }

    $pdo->prepare("UPDATE transfers SET total_boxes=? , updated_at=NOW() WHERE id=?")->execute([$totalBoxes, $tid]);

    $pdo->prepare("INSERT INTO transfer_logs (transfer_id, event_type, severity, event_data, created_at)
                   VALUES (?, 'FREIGHT_UPDATED', 'info', ?, NOW())")
        ->execute([$tid, json_encode(['mode'=>$mode,'courier'=>$cour,'boxes'=>$totalBoxes,'numbers'=>$nums], JSON_UNESCAPED_UNICODE)]);

    $pdo->commit();
    echo json_encode(['success'=>true, 'shipment_id'=>$sid, 'boxes'=>$totalBoxes]);
} catch (Throwable $e) {
    if (!empty($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
