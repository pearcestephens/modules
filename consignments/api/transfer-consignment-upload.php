<?php
/**
 * Transfer Consignment Upload API
 * --------------------------------
 * - POST only (JSON body supported; form-data fallback)
 * - Validates transfer existence and state
 * - Creates Vend/Lightspeed consignment and uploads products
 * - Writes progress rows consumed by SSE endpoint
 * - Idempotent per transfer/session pair; rejects concurrent uploads
 */
declare(strict_types=1);

use JsonException;
use PDO;
use RuntimeException;
use Throwable;

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/shared/functions/config.php';

if (!function_exists('respond')) {
    /**
     * Emit JSON response and terminate.
     */
    function respond(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(405, ['ok' => false, 'error' => 'Method not allowed']);
    }

    $raw = file_get_contents('php://input') ?: '';
    $json = [];
    if ($raw !== '') {
        try {
            $json = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $decodeErr) {
            respond(400, ['ok' => false, 'error' => 'Invalid JSON payload']);
        }
    }

    $transferId = (int)($json['transfer_id'] ?? $_POST['transfer_id'] ?? 0);
    $sessionId  = trim((string)($json['session_id'] ?? $_POST['session_id'] ?? ''));
    $mode       = (string)($json['mode'] ?? $_POST['mode'] ?? 'create_and_upload');

    if ($transferId <= 0) {
        respond(400, ['ok' => false, 'error' => 'transfer_id is required']);
    }
    if ($sessionId === '' || strlen($sessionId) < 6) {
        respond(400, ['ok' => false, 'error' => 'session_id is required']);
    }
    if ($mode !== 'create_and_upload') {
        respond(400, ['ok' => false, 'error' => 'Unsupported mode']);
    }

    $pdo = cis_resolve_pdo();
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Database connection unavailable');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $vendToken = cis_vend_access_token();
    if ($vendToken === null || $vendToken === '') {
        throw new RuntimeException('Vend access token missing. Configure vend_access_token in cis_config.');
    }
    $vendBase = cis_config_get('vend_base_url', getenv('VEND_BASE_URL') ?: 'https://vapeshed.retail.lightspeed.app/api/2.0');

    $existingStmt = $pdo->prepare('SELECT session_id, status FROM consignment_upload_progress WHERE transfer_id = ? ORDER BY updated_at DESC LIMIT 1');
    $existingStmt->execute([$transferId]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing && $existing['session_id'] !== $sessionId && in_array($existing['status'], ['connecting','created','adding_products','updating_state'], true)) {
        respond(409, ['ok' => false, 'error' => 'Upload already in progress for this transfer.']);
    }
    if ($existing && $existing['session_id'] === $sessionId && $existing['status'] === 'completed') {
        respond(200, ['ok' => true, 'message' => 'Upload already completed', 'transfer_id' => $transferId, 'session_id' => $sessionId]);
    }

    $ensureProgressTables = static function (PDO $pdo): void {
        $pdo->exec('CREATE TABLE IF NOT EXISTS consignment_upload_progress (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            transfer_id INT UNSIGNED NOT NULL,
            session_id VARCHAR(64) NOT NULL,
            status ENUM("pending","connecting","created","adding_products","updating_state","completed","failed") NOT NULL DEFAULT "pending",
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

        $pdo->exec('CREATE TABLE IF NOT EXISTS consignment_product_progress (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            transfer_id INT UNSIGNED NOT NULL,
            session_id VARCHAR(64) NOT NULL,
            product_id VARCHAR(100) NOT NULL,
            sku VARCHAR(100) NOT NULL,
            name VARCHAR(255) NOT NULL,
            status ENUM("pending","processing","completed","failed") NOT NULL DEFAULT "pending",
            vend_product_id VARCHAR(100) NULL,
            error_message TEXT NULL,
            processing_time_ms INT UNSIGNED NULL,
            processed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_t_s_p (transfer_id, session_id, product_id),
            INDEX idx_status (status),
            INDEX idx_processed (processed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
    };

    $progressInit = static function (PDO $pdo, int $tId, string $sess, int $total): void {
        $stmt = $pdo->prepare('INSERT INTO consignment_upload_progress
            (transfer_id, session_id, status, total_products, completed_products, failed_products, current_operation, last_message, created_at, updated_at)
            VALUES (?, ?, "connecting", ?, 0, 0, "Connecting to Lightspeed…", "Connecting to API", NOW(), NOW())
            ON DUPLICATE KEY UPDATE status="connecting", total_products=VALUES(total_products), completed_products=0, failed_products=0,
                                     current_operation=VALUES(current_operation), last_message=VALUES(last_message), updated_at=NOW()');
        $stmt->execute([$tId, $sess, $total]);
    };

    $progress = static function (PDO $pdo, int $tId, string $sess, string $status, string $message, array $extra = []): void {
        $stmt = $pdo->prepare('UPDATE consignment_upload_progress
            SET status = ?, current_operation = ?, last_message = ?,
                completed_products = COALESCE(?, completed_products),
                failed_products = COALESCE(?, failed_products),
                performance_metrics = COALESCE(?, performance_metrics),
                updated_at = NOW()
            WHERE transfer_id = ? AND session_id = ?');

        $metricsJson = isset($extra['metrics']) ? json_encode($extra['metrics']) : null;
        $stmt->execute([
            $status,
            $message,
            $message,
            $extra['completed'] ?? null,
            $extra['failed'] ?? null,
            $metricsJson,
            $tId,
            $sess,
        ]);
    };

    $productProgress = static function (PDO $pdo, int $tId, string $sess, string $productId, string $sku, string $name,
        string $status, ?string $vendPid = null, ?string $error = null, ?int $ms = null): void {
        $stmt = $pdo->prepare('INSERT INTO consignment_product_progress
            (transfer_id, session_id, product_id, sku, name, status, vend_product_id, error_message, processing_time_ms, processed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE status=VALUES(status), vend_product_id=VALUES(vend_product_id),
                                    error_message=VALUES(error_message), processing_time_ms=VALUES(processing_time_ms), processed_at=VALUES(processed_at)');

        $stmt->execute([$tId, $sess, $productId, $sku, $name, $status, $vendPid, $error, $ms]);
    };

    $vendRequest = static function (string $method, string $endpoint, ?array $payload, string $token, string $base) {
        $url = rtrim($base, '/') . '/' . ltrim($endpoint, '/');
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: CIS-TransferUploader/2.0'
        ];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        ];
        if ($payload !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
        $err  = curl_error($ch);
        curl_close($ch);

        error_log(sprintf('🌐 VEND %s %s → %d', strtoupper($method), $url, $http));
        if ($payload) {
            error_log('📤 ' . substr(json_encode($payload), 0, 800));
        }
        if ($raw) {
            error_log('📥 ' . substr($raw, 0, 800));
        }
        if ($err) {
            error_log('❌ CURL: ' . $err);
        }

        if ($err) {
            return ['ok' => false, 'http' => 0, 'error' => $err];
        }
        $decoded = json_decode((string)$raw, true);
        if ($http < 200 || $http >= 300) {
            return ['ok' => false, 'http' => $http, 'error' => $raw ?: ('HTTP ' . $http)];
        }
        if ($decoded === null) {
            return ['ok' => false, 'http' => $http, 'error' => 'Invalid JSON response'];
        }
        return ['ok' => true, 'http' => $http, 'data' => $decoded];
    };

    $ensureProgressTables($pdo);

    $pdo->beginTransaction();

    $transferStmt = $pdo->prepare('SELECT t.*, src.name AS source_name, dst.name AS destination_name
        FROM transfers t
        LEFT JOIN vend_outlets src ON src.id = t.outlet_from
        LEFT JOIN vend_outlets dst ON dst.id = t.outlet_to
        WHERE t.id = ? FOR UPDATE');
    $transferStmt->execute([$transferId]);
    $transfer = $transferStmt->fetch(PDO::FETCH_OBJ);
    if (!$transfer) {
        throw new RuntimeException('Transfer not found');
    }
    if (!in_array($transfer->state, ['OPEN', 'PACKING', 'SENT'], true)) {
        throw new RuntimeException(sprintf('Transfer state %s is not eligible for upload', $transfer->state));
    }

    $itemStmt = $pdo->prepare('SELECT ti.*, vp.name AS product_name, vp.sku, vp.product_id AS vend_product_id
        FROM transfer_items ti
        LEFT JOIN vend_products vp ON vp.id = ti.product_id
        WHERE ti.transfer_id = ?');
    $itemStmt->execute([$transferId]);
    $items = $itemStmt->fetchAll(PDO::FETCH_OBJ);
    if (!$items) {
        throw new RuntimeException('No transfer items found');
    }

    $uploadable = array_values(array_filter($items, static fn ($row) => (int)($row->qty_sent_total ?? 0) > 0));
    $totalProducts = count($uploadable);
    if ($totalProducts === 0) {
        throw new RuntimeException('All counted quantities are zero');
    }

    $progressInit($pdo, $transferId, $sessionId, $totalProducts);
    $progress($pdo, $transferId, $sessionId, 'connecting', 'Connecting to Lightspeed…');

    $payload = [
        'outlet_id'        => $transfer->outlet_to,
        'source_outlet_id' => $transfer->outlet_from,
        'type'             => 'OUTLET',
        'status'           => 'OPEN',
        'name'             => sprintf('Transfer #%d — %s → %s', $transferId, (string)$transfer->source_name, (string)$transfer->destination_name),
        'reference'        => 'CIS-' . $transferId,
    ];
    $create = $vendRequest('POST', 'consignments', $payload, $vendToken, $vendBase);
    if (!$create['ok']) {
        $progress($pdo, $transferId, $sessionId, 'failed', 'Failed to create consignment', ['metrics' => ['http' => $create['http'], 'error' => $create['error']]]);
        throw new RuntimeException('Vend create consignment failed: ' . $create['error']);
    }

    $vendConsignmentId = $create['data']['id'] ?? null;
    if (!$vendConsignmentId) {
        $progress($pdo, $transferId, $sessionId, 'failed', 'Vend returned empty consignment ID');
        throw new RuntimeException('Vend returned success but missing consignment id');
    }

    $progress($pdo, $transferId, $sessionId, 'created', 'Created consignment ' . $vendConsignmentId);

    $queueStmt = $pdo->prepare('INSERT INTO queue_consignments
        (transfer_id, vend_consignment_id, outlet_from_id, outlet_to_id, status, sync_status, created_at, updated_at)
        VALUES (?, ?, ?, ?, "OPEN", "synced", NOW(), NOW())
        ON DUPLICATE KEY UPDATE vend_consignment_id = VALUES(vend_consignment_id), updated_at = NOW()');
    $queueStmt->execute([$transferId, $vendConsignmentId, $transfer->outlet_from, $transfer->outlet_to]);

    $linkStmt = $pdo->prepare('SELECT id FROM queue_consignments WHERE transfer_id = ? ORDER BY id DESC LIMIT 1');
    $linkStmt->execute([$transferId]);
    $queueConsignment = $linkStmt->fetch(PDO::FETCH_OBJ);

    $progress($pdo, $transferId, $sessionId, 'adding_products', 'Adding products to consignment…');

    $completed = 0;
    $failed = 0;

    foreach ($uploadable as $row) {
        $started = microtime(true);
        $qty = (int)$row->qty_sent_total;
        $name = (string)($row->product_name ?? 'Unknown');
        $sku  = (string)($row->sku ?? '');
        $vendPid = (string)($row->vend_product_id ?? '');

        if ($vendPid === '') {
            $productProgress($pdo, $transferId, $sessionId, (string)$row->product_id, $sku, $name, 'failed', null, 'Missing vend_product_id');
            $failed++;
            $progress($pdo, $transferId, $sessionId, 'adding_products', 'Skipping ' . $name . ' — no vend product id', [
                'completed' => $completed,
                'failed'    => $failed,
            ]);
            continue;
        }

        try {
            $resp = $vendRequest('POST', "consignments/{$vendConsignmentId}/products", [
                'product_id' => $vendPid,
                'count'      => $qty,
            ], $vendToken, $vendBase);

            $elapsed = (int) round((microtime(true) - $started) * 1000);

            if ($resp['ok']) {
                $productProgress($pdo, $transferId, $sessionId, (string)$row->product_id, $sku, $name, 'completed', $vendPid, null, $elapsed);
                $completed++;
                $progress($pdo, $transferId, $sessionId, 'adding_products', sprintf('Added %s (%d/%d)', $name, $completed, $totalProducts), [
                    'completed' => $completed,
                    'failed'    => $failed,
                    'metrics'   => ['last_ms' => $elapsed],
                ]);
            } else {
                $productProgress($pdo, $transferId, $sessionId, (string)$row->product_id, $sku, $name, 'failed', $vendPid, $resp['error'], null);
                $failed++;
                $progress($pdo, $transferId, $sessionId, 'adding_products', sprintf('Failed %s (%d/%d)', $name, $completed, $totalProducts), [
                    'completed' => $completed,
                    'failed'    => $failed,
                    'metrics'   => ['http' => $resp['http']],
                ]);
            }

            usleep(120000); // Guard API rate
        } catch (Throwable $productErr) {
            $productProgress($pdo, $transferId, $sessionId, (string)$row->product_id, $sku, $name, 'failed', null, $productErr->getMessage(), null);
            $failed++;
            $progress($pdo, $transferId, $sessionId, 'adding_products', sprintf('Error %s (%d/%d)', $name, $completed, $totalProducts), [
                'completed' => $completed,
                'failed'    => $failed,
            ]);
        }
    }

    if ($completed === 0) {
        $progress($pdo, $transferId, $sessionId, 'failed', 'All product uploads failed');
        throw new RuntimeException('No products were added to the consignment');
    }

    $progress($pdo, $transferId, $sessionId, 'updating_state', 'Finalising consignment state…');

    $update = $vendRequest('PUT', "consignments/{$vendConsignmentId}", ['status' => 'IN_TRANSIT'], $vendToken, $vendBase);
    if (!$update['ok']) {
        error_log('⚠️ Vend state update failed: ' . $update['error']);
    }

    $updateTransfer = $pdo->prepare('UPDATE transfers SET state = "SENT", vend_transfer_id = ?, consignment_id = ?, sent_at = NOW(), updated_at = NOW() WHERE id = ?');
    $updateTransfer->execute([$vendConsignmentId, $queueConsignment->id ?? null, $transferId]);

    $pdo->commit();

    $progress($pdo, $transferId, $sessionId, 'completed', 'Consignment created & marked SENT', [
        'completed' => $completed,
        'failed'    => $failed,
    ]);

    respond(200, [
        'ok'               => true,
        'message'          => 'Consignment created successfully',
        'transfer_id'      => $transferId,
        'session_id'       => $sessionId,
        'consignment_id'   => $vendConsignmentId,
        'vend_url'         => sprintf('https://vapeshed.retail.lightspeed.app/app/2.0/consignments/%s', $vendConsignmentId),
        'products_added'   => $completed,
        'products_failed'  => $failed,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('❌ Transfer Consignment Upload error: ' . $e->getMessage());

    try {
        if (isset($pdo) && $pdo instanceof PDO && isset($transferId, $sessionId)) {
            $stmt = $pdo->prepare('UPDATE consignment_upload_progress SET status = "failed", last_message = ?, current_operation = ?, updated_at = NOW() WHERE transfer_id = ? AND session_id = ?');
            $stmt->execute([$e->getMessage(), $e->getMessage(), $transferId ?? 0, $sessionId ?? '']);
        }
    } catch (Throwable $progressErr) {
        error_log('⚠️ Failed to write failure status: ' . $progressErr->getMessage());
    }

    $status = ($e instanceof RuntimeException) ? 400 : 500;
    respond($status, ['ok' => false, 'error' => $e->getMessage()]);
}
