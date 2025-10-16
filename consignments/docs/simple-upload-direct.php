<?php
/**
 * DIRECT VEND UPLOAD — LIVE + TRACED + SSE
 * Creates a Vend consignment, streams real progress, and writes
 * transfer/vend/queue links + per-product breadcrumbs.
 *
 * POST: transfer_id (int), session_id (string)
 */
declare(strict_types=1);

if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');

try {
    // ---- 0) Validate input
    if (empty($_POST['transfer_id']) || !is_numeric($_POST['transfer_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid transfer_id', 'error_code' => 'INVALID_INPUT']);
        exit;
    }
    if (empty($_POST['session_id']) || strlen($_POST['session_id']) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid session_id', 'error_code' => 'INVALID_INPUT']);
        exit;
    }

    $transferId = (int) $_POST['transfer_id'];
    $sessionId  = trim($_POST['session_id']);

    // ---- 1) DB & config
    require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php'; // provides $pdo
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('Database connection not available');
    }

    // Lightspeed / Vend config (prefer env; fallback constant if defined)
    $VEND_TOKEN    = getenv('VEND_API_TOKEN') ?: (defined('VEND_TOKEN') ? VEND_TOKEN : '');
    $VEND_BASE_URL = getenv('VEND_BASE_URL') ?: (defined('VEND_BASE_URL') ? VEND_BASE_URL : 'https://vapeshed.retail.lightspeed.app/api/2.0');

    if (!$VEND_TOKEN) {
        throw new Exception('VEND_API_TOKEN not configured');
    }

    // ---- 2) Helpers: HTTP + Progress
    $vendRequest = function (string $method, string $endpoint, ?array $payload = null) use ($VEND_BASE_URL, $VEND_TOKEN): array {
        $url = rtrim($VEND_BASE_URL, '/') . '/' . ltrim($endpoint, '/');
        $ch = curl_init($url);

$headers = [
    'Authorization: Bearer ' . $VEND_TOKEN, // ✅ real header
    'Accept: application/json',
    'Content-Type: application/json',
    'User-Agent: CIS-DirectUploader/1.0'
];


        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        ];
        if ($payload !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        error_log("🌐 VEND {$method} {$url} → {$http}");
        if ($payload) error_log('📤 ' . substr(json_encode($payload), 0, 800));
        if ($raw)     error_log('📥 ' . substr($raw, 0, 800));
        if ($err)     error_log('❌ CURL: ' . $err);

        if ($err) return ['ok' => false, 'http' => 0,   'error' => $err];
        $json = json_decode($raw, true);
        if ($http < 200 || $http >= 300) {
            return ['ok' => false, 'http' => $http, 'error' => $raw ?: ('HTTP ' . $http)];
        }
        if ($json === null) {
            return ['ok' => false, 'http' => $http, 'error' => 'Invalid JSON response'];
        }
        return ['ok' => true, 'http' => $http, 'data' => $json];
    };

    $ensureProgressTables = function(PDO $pdo) {
        // Safe guards if migration hasn’t run
        $pdo->exec("
          CREATE TABLE IF NOT EXISTS consignment_upload_progress (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            transfer_id INT UNSIGNED NOT NULL,
            session_id VARCHAR(64) NOT NULL,
            status ENUM('pending','connecting','created','adding_products','updating_state','completed','failed') NOT NULL DEFAULT 'pending',
            total_products INT UNSIGNED NOT NULL DEFAULT 0,
            completed_products INT UNSIGNED NOT NULL DEFAULT 0,
            failed_products INT UNSIGNED NOT NULL DEFAULT 0,
            current_operation VARCHAR(255) NULL,
            last_message TEXT NULL,
            performance_metrics JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_transfer_session (transfer_id, session_id),
            INDEX idx_status (status),
            INDEX idx_updated (updated_at)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
          CREATE TABLE IF NOT EXISTS consignment_product_progress (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            transfer_id INT UNSIGNED NOT NULL,
            session_id VARCHAR(64) NOT NULL,
            product_id VARCHAR(100) NOT NULL,
            sku VARCHAR(100) NOT NULL,
            name VARCHAR(255) NOT NULL,
            status ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
            vend_product_id VARCHAR(100) NULL,
            error_message TEXT NULL,
            processing_time_ms INT UNSIGNED NULL,
            processed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_t_s_p (transfer_id, session_id, product_id),
            INDEX idx_status (status),
            INDEX idx_processed (processed_at)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    };

    $progressInit = function(PDO $pdo, int $transferId, string $sessionId, int $total) {
        $stmt = $pdo->prepare("
          INSERT INTO consignment_upload_progress
            (transfer_id, session_id, status, total_products, completed_products, failed_products, current_operation, last_message, created_at, updated_at)
          VALUES
            (?, ?, 'connecting', ?, 0, 0, 'Connecting to Lightspeed…', 'Connecting to API', NOW(), NOW())
          ON DUPLICATE KEY UPDATE
            status='connecting', total_products=VALUES(total_products),
            completed_products=0, failed_products=0,
            current_operation='Connecting to Lightspeed…',
            last_message='Connecting to API', updated_at=NOW()
        ");
        $stmt->execute([$transferId, $sessionId, $total]);
    };

    $progress = function(PDO $pdo, int $transferId, string $sessionId, string $status, string $message, array $extra = []) {
        $stmt = $pdo->prepare("
          UPDATE consignment_upload_progress
          SET status = ?, current_operation = ?, last_message = ?,
              completed_products = COALESCE(?, completed_products),
              failed_products = COALESCE(?, failed_products),
              performance_metrics = COALESCE(?, performance_metrics),
              updated_at = NOW()
          WHERE transfer_id = ? AND session_id = ?
        ");
        $metrics = !empty($extra['metrics']) ? json_encode($extra['metrics']) : null;
        $stmt->execute([
            $status,
            $message,
            $message,
            $extra['completed'] ?? null,
            $extra['failed']    ?? null,
            $metrics,
            $transferId,
            $sessionId
        ]);
    };

    $productProgress = function(PDO $pdo, int $transferId, string $sessionId, string $pid, string $sku, string $name,
                                string $status, ?string $vendPid = null, ?string $err = null, ?int $ms = null) {
        $stmt = $pdo->prepare("
          INSERT INTO consignment_product_progress
            (transfer_id, session_id, product_id, sku, name, status, vend_product_id, error_message, processing_time_ms, processed_at)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
          ON DUPLICATE KEY UPDATE
            status=VALUES(status),
            vend_product_id=VALUES(vend_product_id),
            error_message=VALUES(error_message),
            processing_time_ms=VALUES(processing_time_ms),
            processed_at=VALUES(processed_at)
        ");
        $stmt->execute([$transferId, $sessionId, $pid, $sku, $name, $status, $vendPid, $err, $ms]);
    };

    $ensureProgressTables($pdo);

    // ---- 3) Load transfer + items (use outlet_from/outlet_to; use Vend product UUID for API)
    $pdo->beginTransaction();

    $tStmt = $pdo->prepare("
      SELECT t.*, src.name AS source_name, dst.name AS destination_name
      FROM transfers t
      LEFT JOIN vend_outlets src ON src.id = t.outlet_from
      LEFT JOIN vend_outlets dst ON dst.id = t.outlet_to
      WHERE t.id = ? FOR UPDATE
    ");
    $tStmt->execute([$transferId]);
    $transfer = $tStmt->fetch(PDO::FETCH_OBJ);
    if (!$transfer) throw new Exception("Transfer not found");

    if (!in_array($transfer->state, ['OPEN','PACKING','SENT'], true)) {
        throw new Exception("Transfer must be OPEN or PACKING (current: {$transfer->state})");
    }

    // Items: db product id = vend_products.id; Vend API requires vend_products.product_id (UUID)
    $iStmt = $pdo->prepare("
      SELECT ti.*, vp.name AS product_name, vp.sku, vp.product_id AS vend_product_id
      FROM transfer_items ti
      LEFT JOIN vend_products vp ON vp.id = ti.product_id
      WHERE ti.transfer_id = ?
    ");
    $iStmt->execute([$transferId]);
    $items = $iStmt->fetchAll(PDO::FETCH_OBJ);
    if (!$items || count($items) === 0) throw new Exception('No items found on transfer');

    // Total products to try (only >0 quantities)
    $toUpload = array_values(array_filter($items, fn($r) => (int)($r->qty_sent_total ?? 0) > 0));
    $totalProducts = count($toUpload);
    if ($totalProducts === 0) throw new Exception('All counted quantities are zero');

    $progressInit($pdo, $transferId, $sessionId, $totalProducts);

    // ---- 4) Create Vend consignment
    $progress($pdo, $transferId, $sessionId, 'connecting', 'Connecting to Lightspeed…');

    $payload = [
        'outlet_id'        => $transfer->outlet_to,    // destination
        'source_outlet_id' => $transfer->outlet_from,  // source
        'type'             => 'OUTLET',
        'status'           => 'OPEN',
        'name'             => sprintf('Transfer #%d — %s → %s', $transferId, $transfer->source_name, $transfer->destination_name),
        'reference'        => 'CIS-' . $transferId,
    ];
    $create = $vendRequest('POST', 'consignments', $payload);
    if (!$create['ok']) {
        $progress($pdo, $transferId, $sessionId, 'failed', 'Failed to create consignment', ['metrics' => ['http' => $create['http'], 'error' => $create['error']]]);
        throw new Exception('Vend error (create consignment): ' . $create['error']);
    }

    $vendConsignmentId = $create['data']['id'] ?? null;
    if (!$vendConsignmentId) {
        $progress($pdo, $transferId, $sessionId, 'failed', 'Vend missing consignment id');
        throw new Exception('Vend returned success but no consignment id');
    }

    $progress($pdo, $transferId, $sessionId, 'created', "Created consignment {$vendConsignmentId}");

    // queue_consignments link (shadow)
    $qcStmt = $pdo->prepare("
      INSERT INTO queue_consignments
          (transfer_id, vend_consignment_id, outlet_from_id, outlet_to_id, status, sync_status, created_at, updated_at)
      VALUES (?, ?, ?, ?, 'OPEN', 'synced', NOW(), NOW())
      ON DUPLICATE KEY UPDATE vend_consignment_id = VALUES(vend_consignment_id), updated_at = NOW()
    ");
    $qcStmt->execute([$transferId, $vendConsignmentId, $transfer->outlet_from, $transfer->outlet_to]);

    // link back to transfers
    $linkStmt = $pdo->prepare("SELECT id FROM queue_consignments WHERE transfer_id = ? ORDER BY id DESC LIMIT 1");
    $linkStmt->execute([$transferId]);
    $queueConsignment = $linkStmt->fetch(PDO::FETCH_OBJ);

    // ---- 5) Add products
    $progress($pdo, $transferId, $sessionId, 'adding_products', 'Adding products to consignment…');

    $done = 0; $fail = 0;
    foreach ($toUpload as $row) {
        $start = microtime(true);

        $qty = (int)$row->qty_sent_total;
        $name = (string)$row->product_name;
        $sku  = (string)$row->sku;
        $vendPid = (string)$row->vend_product_id; // UUID required by API

        if (!$vendPid) {
            $productProgress($pdo, $transferId, $sessionId, (string)$row->product_id, $sku, $name, 'failed', null, 'Missing vend_product_id');
            $fail++;
            $progress($pdo, $transferId, $sessionId, 'adding_products', "Skipping {$name} — no vend_product_id", [
                'completed' => $done,
                'failed'    => $fail
            ]);
            continue;
        }

        try {
            $resp = $vendRequest('POST', "consignments/{$vendConsignmentId}/products", [
                'product_id' => $vendPid,
                'count'      => $qty
            ]);
            $ms = (int) round((microtime(true) - $start) * 1000);

            if ($resp['ok']) {
                $productProgress($pdo, $transferId, $sessionId, (string)$row->product_id, $sku, $name, 'completed', $vendPid, null, $ms);
                $done++;
                $progress($pdo, $transferId, $sessionId, 'adding_products', "Added {$name} ({$done}/{$totalProducts})", [
                    'completed' => $done,
                    'failed'    => $fail,
                    'metrics'   => ['last_ms' => $ms]
                ]);
            } else {
                $productProgress($pdo, $transferId, $sessionId, (string)$row->product_id, $sku, $name, 'failed', $vendPid, $resp['error'], null);
                $fail++;
                $progress($pdo, $transferId, $sessionId, 'adding_products', "Failed {$name} ({$done}/{$totalProducts})", [
                    'completed' => $done,
                    'failed'    => $fail,
                    'metrics'   => ['http' => $resp['http']]
                ]);
            }

            usleep(120000); // 0.12s pacing

        } catch (Throwable $e) {
            $productProgress($pdo, $transferId, $sessionId, (string)$row->product_id, $sku, $name, 'failed', null, $e->getMessage(), null);
            $fail++;
            $progress($pdo, $transferId, $sessionId, 'adding_products', "Error {$name} ({$done}/{$totalProducts})", [
                'completed' => $done,
                'failed'    => $fail
            ]);
        }
    }

    if ($done === 0) {
        $progress($pdo, $transferId, $sessionId, 'failed', 'All product uploads failed');
        throw new Exception('No products were added to the consignment');
    }

    // ---- 6) Mark transfer SENT, update Vend consignment to IN_TRANSIT
    $progress($pdo, $transferId, $sessionId, 'updating_state', 'Finalising consignment state…');

    $upd = $vendRequest('PUT', "consignments/{$vendConsignmentId}", ['status' => 'IN_TRANSIT']);
    if (!$upd['ok']) {
        // Non-fatal: leave OPEN if Vend rejects state; still mark SENT on our side
        error_log("⚠️ Vend state update failed: " . $upd['error']);
    }

    $uStmt = $pdo->prepare("UPDATE transfers SET state='SENT', vend_transfer_id=?, consignment_id=?, sent_at=NOW(), updated_at=NOW() WHERE id=?");
    $uStmt->execute([$vendConsignmentId, $queueConsignment->id ?? null, $transferId]);

    $pdo->commit();

    $progress($pdo, $transferId, $sessionId, 'completed', 'Consignment created & marked SENT', [
        'completed' => $done,
        'failed'    => $fail
    ]);

    http_response_code(200);
    echo json_encode([
        'success'         => true,
        'message'         => 'Consignment created successfully',
        'transfer_id'     => $transferId,
        'session_id'      => $sessionId,
        'consignment_id'  => $vendConsignmentId,
        'vend_url'        => "https://vapeshed.retail.lightspeed.app/app/2.0/consignments/{$vendConsignmentId}",
        'products_added'  => $done,
        'products_failed' => $fail
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('❌ DIRECT UPLOAD ERROR: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'error_code' => 'UPLOAD_FAILED']);
}
