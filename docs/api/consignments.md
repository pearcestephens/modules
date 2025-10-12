# consignments Module - API Documentation

**Generated**: 2025-10-12 19:19:24
**Module**: consignments
**Endpoints**: 9

## Base URL
`/modules/consignments/api/`

## Endpoints

### add_line

**File**: `add_line.php`
**Description**: Ensure transfer exists
**Methods**: POST
**Parameters**:
- `csrf'] ?? '');

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
        $itemId = (int)$row['id` (string): Auto-detected parameter

### autosave

**File**: `autosave.php`
**Description**: Auto-generated endpoint documentation
**Methods**: POST
**Parameters**:
- `csrf'] ?? '');
    $transferId = (int)($_POST['transfer_id'] ?? 0);
    $stateJson  = (string)($_POST['state_json` (string): Auto-detected parameter

### autosave_load

**File**: `autosave_load.php`
**Description**: Auto-generated endpoint documentation
**Methods**: POST
**Parameters**:
- `csrf'] ?? '');
    $transferId = (int)($_POST['transfer_id'] ?? 0);
    if ($transferId <= 0) throw new RuntimeException('transfer_id required');

    $pdo = Db::pdo();
    $stmt = $pdo->prepare("SELECT state_json FROM transfer_ui_sessions WHERE transfer_id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$transferId, Security::currentUserId()]);
    $row = $stmt->fetch();

    echo json_encode(['ok'=>true, 'state'=> $row['state_json'] ?? '{}` (string): Auto-detected parameter

### pack_lock

**File**: `pack_lock.php`
**Description**: Auto-generated endpoint documentation
**Methods**: POST
**Parameters**:
- `csrf'] ?? '');
    $pdo = Db::pdo();

    $transferId = (int)($_POST['transfer_id'] ?? 0);
    $op = $_POST['op'] ?? 'acquire';
    $ttlMin = max(1, (int)($_POST['ttl_min` (string): Auto-detected parameter

### pack_submit

**File**: `pack_submit.php`
**Description**: idempotency pre-check
**Methods**: POST
**Parameters**:
- `csrf'] ?? '');

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
                   SET total_boxes = total_boxes + ?, total_weight_g = total_weight_g + ?, state = 'PACKAGED', status = IF(status='draft','open',status), updated_at = NOW()
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
        'action'=>'PACK_SUBMIT', 'outlet_from'=>$transfer['outlet_from'], 'outlet_to'=>$transfer['outlet_to` (string): Auto-detected parameter

### receive_submit

**File**: `receive_submit.php`
**Description**: Allow SENT|RECEIVING|PACKAGED|OPEN? (receive after packed/sent)
**Methods**: POST
**Parameters**:
- `csrf'] ?? '');

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
        'action'=>'RECEIVE_SUBMIT', 'outlet_from'=>$transfer['outlet_from'], 'outlet_to'=>$transfer['outlet_to` (string): Auto-detected parameter

### remove_line

**File**: `remove_line.php`
**Description**: Check not received/sent already
**Methods**: POST
**Parameters**:
- `csrf'] ?? '');

    $pdo = Db::pdo();
    $transferId = (int)($_POST['transfer_id'] ?? 0);
    $itemId     = (int)($_POST['item_id'] ?? 0);
    if ($transferId <= 0 || $itemId <= 0) throw new RuntimeException('transfer_id and item_id required');

    $pdo->beginTransaction();

    // Check not received/sent already
    $line = $pdo->prepare("SELECT id, qty_sent_total, qty_received_total FROM transfer_items WHERE id = ? AND transfer_id = ? AND deleted_at IS NULL FOR UPDATE");
    $line->execute([$itemId,$transferId]);
    $li = $line->fetch();
    if (!$li) throw new RuntimeException('Line not found');
    if ((int)$li['qty_sent_total'] > 0 || (int)$li['qty_received_total` (string): Auto-detected parameter

### search_products

**File**: `search_products.php`
**Description**: Example: find by SKU or name; join a hypothetical outlet inventory view if you have it.
**Methods**: POST
**Parameters**:
- `csrf'] ?? '');
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
    $stmt->execute([':q'=>'%'.$q.'%', ':outlet'=>$outletId ?: '` (string): Auto-detected parameter

### update_line_qty

**File**: `update_line_qty.php`
**Description**: Auto-generated endpoint documentation
**Methods**: POST
**Parameters**:
- `csrf'] ?? '');

    $pdo = Db::pdo();
    $transferId = (int)($_POST['transfer_id'] ?? 0);
    $itemId     = (int)($_POST['item_id'] ?? 0);
    $qty        = max(0, (int)($_POST['qty_requested` (string): Auto-detected parameter

