<?php
/**
 * Consignment Upload Background Processor (QUEUE MODE)
 * Creates Vend consignment, adds products, updates transfer to SENT,
 * and writes progress into consignment_upload_progress / consignment_product_progress
 *
 * CLI ONLY:
 *   php process-consignment-upload.php --job-id=123
 */
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$options = getopt('', ['job-id:']);
if (empty($options['job-id']) || !is_numeric($options['job-id'])) {
    echo "Usage: php process-consignment-upload.php --job-id=<id>\n";
    exit(1);
}
$jobId = (int)$options['job-id'];

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php'; // $pdo
if (!isset($pdo) || !$pdo instanceof PDO) {
    fwrite(STDERR, "DB unavailable\n");
    exit(1);
}

// Vend config
$VEND_TOKEN    = getenv('VEND_API_TOKEN') ?: (defined('VEND_TOKEN') ? VEND_TOKEN : '');
$VEND_BASE_URL = getenv('VEND_BASE_URL') ?: (defined('VEND_BASE_URL') ? VEND_BASE_URL : 'https://vapeshed.retail.lightspeed.app/api/2.0');
if (!$VEND_TOKEN) {
    fwrite(STDERR, "VEND_API_TOKEN not configured\n");
    exit(1);
}

$vendRequest = function (string $method, string $endpoint, ?array $payload = null) use ($VEND_BASE_URL, $VEND_TOKEN): array {
    $url = rtrim($VEND_BASE_URL, '/') . '/' . ltrim($endpoint, '/');
    $ch = curl_init($url);
$headers = [
    'Authorization: Bearer ' . $VEND_TOKEN, // ✅
    'Accept: application/json',
    'Content-Type: application/json',
    'User-Agent: CIS-QueueWorker/1.0',
];

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
    ];
    if ($payload !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
    curl_setopt_array($ch, $opts);

    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) return ['ok' => false, 'http' => 0, 'error' => $err];
    $json = json_decode($raw, true);
    if ($http < 200 || $http >= 300) return ['ok' => false, 'http' => $http, 'error' => $raw ?: ('HTTP ' . $http)];
    if ($json === null) return ['ok' => false, 'http' => $http, 'error' => 'Invalid JSON'];
    return ['ok' => true, 'http' => $http, 'data' => $json];
};

// Helpers: progress writers
$ensureProgressTables = function() use ($pdo) {
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
$progress = function(int $transferId, string $sessionId, string $status, string $message, array $extra = []) use ($pdo) {
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
        $status, $message, $message,
        $extra['completed'] ?? null, $extra['failed'] ?? null,
        $metrics, $transferId, $sessionId
    ]);
};
$productProgress = function(int $transferId, string $sessionId, string $pid, string $sku, string $name,
                            string $status, ?string $vendPid = null, ?string $err = null, ?int $ms = null) use ($pdo) {
    $stmt = $pdo->prepare("
      INSERT INTO consignment_product_progress
        (transfer_id, session_id, product_id, sku, name, status, vend_product_id, error_message, processing_time_ms, processed_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
      ON DUPLICATE KEY UPDATE
        status=VALUES(status), vend_product_id=VALUES(vend_product_id),
        error_message=VALUES(error_message), processing_time_ms=VALUES(processing_time_ms),
        processed_at=VALUES(processed_at)
    ");
    $stmt->execute([$transferId, $sessionId, $pid, $sku, $name, $status, $vendPid, $err, $ms]);
};

// Load job payload
$stmt = $pdo->prepare("SELECT payload FROM queue_jobs WHERE id = ? LIMIT 1");
$stmt->execute([$jobId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    fwrite(STDERR, "Job not found: {$jobId}\n");
    exit(1);
}
$payload = json_decode($row['payload'] ?? '{}', true);
$transferId = (int)($payload['transfer_id'] ?? 0);
$sessionId  = (string)($payload['session_id'] ?? '');

if ($transferId <= 0 || strlen($sessionId) < 8) {
    fwrite(STDERR, "Bad payload for job {$jobId}\n");
    exit(1);
}

$ensureProgressTables();

// mark job processing
try {
    $pdo->prepare("UPDATE queue_jobs SET status='processing', started_at = NOW(), updated_at=NOW() WHERE id=?")->execute([$jobId]);
} catch (Throwable $ignore) {
    // table may lack started_at; fallback
    $pdo->prepare("UPDATE queue_jobs SET status='processing', updated_at=NOW() WHERE id=?")->execute([$jobId]);
}

try {
    // Lock transfer
    $pdo->beginTransaction();
    $tStmt = $pdo->prepare("
      SELECT t.*, src.name AS source_name, dst.name AS destination_name
      FROM transfers t
      LEFT JOIN vend_outlets src ON src.id = t.outlet_from
      LEFT JOIN vend_outlets dst ON dst.id = t.outlet_to
      WHERE t.id = ? FOR UPDATE
    ");
    $tStmt->execute([$transferId]);
    $t = $tStmt->fetch(PDO::FETCH_OBJ);
    if (!$t) throw new Exception('Transfer not found');

    $iStmt = $pdo->prepare("
      SELECT ti.*, vp.name AS product_name, vp.sku, vp.product_id AS vend_product_id
      FROM transfer_items ti
      LEFT JOIN vend_products vp ON vp.id = ti.product_id
      WHERE ti.transfer_id = ?
    ");
    $iStmt->execute([$transferId]);
    $items = $iStmt->fetchAll(PDO::FETCH_OBJ);
    if (!$items) throw new Exception('No items on transfer');

    $toUpload = array_values(array_filter($items, fn($r) => (int)($r->qty_sent_total ?? 0) > 0));
    $total = count($toUpload);
    if ($total === 0) throw new Exception('All counted quantities are zero');

    $progress($transferId, $sessionId, 'connecting', 'Connecting to Lightspeed…', ['completed'=>0, 'failed'=>0, 'metrics'=>['mode'=>'queue']]);

    // Create consignment
    $payload = [
        'outlet_id'        => $t->outlet_to,
        'source_outlet_id' => $t->outlet_from,
        'type'             => 'OUTLET',
        'status'           => 'OPEN',
        'name'             => sprintf('Transfer #%d — %s → %s', $transferId, $t->source_name, $t->destination_name),
        'reference'        => 'CIS-' . $transferId,
    ];
    $create = $vendRequest('POST', 'consignments', $payload);
    if (!$create['ok']) {
        $progress($transferId, $sessionId, 'failed', 'Failed to create consignment', ['metrics'=>['http'=>$create['http'],'error'=>$create['error']]]);
        throw new Exception('Vend error (create consignment): ' . $create['error']);
    }
    $vendConsignmentId = $create['data']['id'] ?? null;
    if (!$vendConsignmentId) throw new Exception('Vend returned success but no consignment id');

    $progress($transferId, $sessionId, 'created', "Created consignment {$vendConsignmentId}");

    // queue_consignments link
    $qc = $pdo->prepare("
      INSERT INTO queue_consignments
          (transfer_id, vend_consignment_id, outlet_from_id, outlet_to_id, status, sync_status, created_at, updated_at)
      VALUES (?, ?, ?, ?, 'OPEN', 'synced', NOW(), NOW())
      ON DUPLICATE KEY UPDATE vend_consignment_id = VALUES(vend_consignment_id), updated_at=NOW()
    ");
    $qc->execute([$transferId, $vendConsignmentId, $t->outlet_from, $t->outlet_to]);

    // Add products
    $progress($transferId, $sessionId, 'adding_products', 'Adding products to consignment…', ['completed'=>0, 'failed'=>0]);
    $done=0; $fail=0;
    foreach ($toUpload as $row) {
        $start = microtime(true);
        $qty   = (int)$row->qty_sent_total;
        $name  = (string)$row->product_name;
        $sku   = (string)$row->sku;
        $vpId  = (string)$row->vend_product_id;

        if (!$vpId) {
            $productProgress($transferId, $sessionId, (string)$row->product_id, $sku, $name, 'failed', null, 'Missing vend_product_id', null);
            $fail++;
            $progress($transferId, $sessionId, 'adding_products', "Skipping {$name} — no vend_product_id", ['completed'=>$done, 'failed'=>$fail]);
            continue;
        }

        $resp = $vendRequest('POST', "consignments/{$vendConsignmentId}/products", [
            'product_id' => $vpId,
            'count'      => $qty
        ]);
        $ms = (int) round((microtime(true) - $start)*1000);

        if ($resp['ok']) {
            $productProgress($transferId, $sessionId, (string)$row->product_id, $sku, $name, 'completed', $vpId, null, $ms);
            $done++;
            $progress($transferId, $sessionId, 'adding_products', "Added {$name} ({$done}/{$total})", ['completed'=>$done, 'failed'=>$fail, 'metrics'=>['last_ms'=>$ms]]);
        } else {
            $productProgress($transferId, $sessionId, (string)$row->product_id, $sku, $name, 'failed', $vpId, $resp['error'], $ms);
            $fail++;
            $progress($transferId, $sessionId, 'adding_products', "Failed {$name} ({$done}/{$total})", ['completed'=>$done, 'failed'=>$fail, 'metrics'=>['http'=>$resp['http']]]);
        }

        usleep(120000); // 0.12s to play nice with rate limits
    }

    if ($done === 0) {
        $progress($transferId, $sessionId, 'failed', 'All product uploads failed');
        throw new Exception('No products were added to the consignment');
    }

    // Mark SENT locally + IN_TRANSIT remotely
    $progress($transferId, $sessionId, 'updating_state', 'Finalising consignment state…');
    $upd = $vendRequest('PUT', "consignments/{$vendConsignmentId}", ['status' => 'IN_TRANSIT']);
    if (!$upd['ok']) {
        // Non-fatal
        error_log("Vend state update failed (still marking SENT locally): " . $upd['error']);
    }

    // Link queue_consignments.id
    $link = $pdo->prepare("SELECT id FROM queue_consignments WHERE transfer_id=? ORDER BY id DESC LIMIT 1");
    $link->execute([$transferId]);
    $qcRow = $link->fetch(PDO::FETCH_ASSOC);

    $pdo->prepare("UPDATE transfers SET state='SENT', vend_transfer_id=?, consignment_id=?, sent_at=NOW(), updated_at=NOW() WHERE id=?")
        ->execute([$vendConsignmentId, $qcRow['id'] ?? null, $transferId]);

    $pdo->commit();
    $progress($transferId, $sessionId, 'completed', 'Consignment created & marked SENT', ['completed'=>$done, 'failed'=>$fail]);

    try {
        $pdo->prepare("UPDATE queue_jobs SET status='completed', completed_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$jobId]);
    } catch (Throwable $ignore) {
        $pdo->prepare("UPDATE queue_jobs SET status='completed', updated_at=NOW() WHERE id=?")->execute([$jobId]);
    }

    echo "OK\n";
    exit(0);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('QUEUE JOB ERROR: ' . $e->getMessage());
    try {
        $pdo->prepare("UPDATE queue_jobs SET status='failed', last_error=?, updated_at=NOW() WHERE id=?")->execute([$e->getMessage(), $jobId]);
    } catch (Throwable $ignore) {}
    echo "ERR: " . $e->getMessage() . "\n";
    exit(1);
}
