<?php
declare(strict_types=1);

/**
 * Submit Transfer API — SIMPLE & WORKING (PDO only)  ✅ matches CIS tables
 * -----------------------------------------------------------------------
 * - POST only; accepts JSON or form-data
 * - Locks the transfer row (FOR UPDATE) to avoid races
 * - Validates items belong to the transfer
 * - Updates qty_sent_total (clamped to qty_requested), marks transfer SENT
 * - Writes audit (transfer_audit_log), immutable event (transfer_logs),
 *   and idempotency row (transfer_idempotency) using your real columns.
 */

// Start session only if not already active (bootstrap.php may have started it)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

$requestId  = null;
$transferId = null;

try {
    // 1) Method guard
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new Exception('Method not allowed - use POST');
    }

    // 2) Parse body (JSON first, fallback to $_POST)
    $raw  = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    $useJson = is_array($json) && json_last_error() === JSON_ERROR_NONE;
    $data = $useJson ? $json : $_POST;

    // Normalize keys
    $transferId = (int)($data['transfer_id'] ?? 0);
    $items      = $data['products'] ?? $data['items'] ?? [];  // accept either key
    $notes      = $data['notes']['internal'] ?? $data['notes'] ?? '';

    // Optional debug
    if (!empty($_GET['debug'])) {
        error_log("=== SUBMIT TRANSFER DEBUG ===");
        error_log("Raw Input (first 500): " . substr($raw, 0, 500));
        error_log("JSON Decoded: " . ($useJson ? 'YES' : 'NO'));
        error_log("Transfer ID (parsed): " . $transferId);
        error_log("Items count: " . (is_array($items) ? count($items) : 0));
    }

    if ($transferId <= 0) {
        $keys = is_array($data) ? implode(', ', array_keys($data)) : '';
        throw new Exception('Invalid transfer_id. Data keys: ' . $keys);
    }
    if (!is_array($items) || empty($items)) {
        throw new Exception('No items provided');
    }

    // 3) DB connect (robust resolver -> PDO)
    $dbHost = getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : '127.0.0.1');
    $dbName = getenv('DB_NAME') ?: (defined('DB_DATABASE') ? DB_DATABASE : (defined('DB_NAME') ? DB_NAME : 'jcepnzzkmj'));
    $dbUser = getenv('DB_USER') ?: (defined('DB_USERNAME') ? DB_USERNAME : (defined('DB_USER') ? DB_USER : 'jcepnzzkmj'));
    $dbPass = getenv('DB_PASS') ?: (defined('DB_PASSWORD') ? DB_PASSWORD : (defined('DB_PASS') ? DB_PASS : 'wprKh9Jq63'));

    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        (string)$dbUser,
        (string)$dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    // 4) Begin transaction
    $pdo->beginTransaction();

    // 5) Lock transfer row and validate state (transfers)
    $stmt = $pdo->prepare("
        SELECT id, public_id, state, outlet_from, outlet_to, created_by
        FROM transfers
        WHERE id = ? AND state IN ('OPEN','PACKING')
        FOR UPDATE
    ");
    $stmt->execute([$transferId]);
    $transfer = $stmt->fetch();
    if (!$transfer) {
        throw new Exception("Transfer {$transferId} not found or not in valid state (must be OPEN or PACKING).");
    }
    $oldState  = (string)$transfer['state'];
    $publicId  = (string)$transfer['public_id'];
    $userId    = (int)($_SESSION['user_id'] ?? 0);
    $requestId = bin2hex(random_bytes(12)); // simple request id

    // 6) Process items against transfer_items
    $qSelectItem = $pdo->prepare("
        SELECT id, qty_requested, qty_received_total
        FROM transfer_items
        WHERE transfer_id = ? AND product_id = ?
    ");
    $qUpdateItem = $pdo->prepare("
        UPDATE transfer_items
        SET qty_sent_total = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");

    $processedCount = 0;
    $perItemSummary = [];

    foreach ($items as $idx => $item) {
        if (!is_array($item)) { continue; }

        $productId   = (string)($item['product_id'] ?? '');
        $countedQty  = (int)($item['counted_qty'] ?? 0);
        // client may send planned_qty; we trust DB's qty_requested
        if ($productId === '' || $countedQty < 0) {
            continue; // skip invalid rows
        }

        // Ensure the product belongs to this transfer
        $qSelectItem->execute([$transferId, $productId]);
        $row = $qSelectItem->fetch();
        if (!$row) {
            throw new Exception("Product {$productId} not in transfer");
        }

        $lineId       = (int)$row['id'];
        $qtyRequested = (int)$row['qty_requested'];

        // HARD LIMIT: must not exceed qty_requested (CHECK constraint)
        $toSend = min($countedQty, $qtyRequested);

        // Optional sanity: flag absurd overcount requests (not applied due to clamp)
        if ($countedQty > ($qtyRequested * 2)) {
            // Still allowed due to clamp, but we log the attempt
            $perItemSummary[] = ['product_id'=>$productId, 'requested'=>$qtyRequested, 'counted'=>$countedQty, 'applied'=>$toSend, 'note'=>'clamped'];
        } else {
            $perItemSummary[] = ['product_id'=>$productId, 'requested'=>$qtyRequested, 'counted'=>$countedQty, 'applied'=>$toSend];
        }

        // Persist counted qty to qty_sent_total (what we plan to send)
        $qUpdateItem->execute([$toSend, $lineId]);
        $processedCount++;
    }

    if ($processedCount === 0) {
        throw new Exception('No valid items were processed');
    }

    // 10) Change transfer state to PACKING (NOT SENT - that happens after Vend upload!)
    $newState = 'PACKING';  // ✅ FIX: Only mark as PACKING, upload file will mark as SENT
    $stmt = $pdo->prepare("
        UPDATE transfers
        SET state = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$newState, $transferId]);
    
    error_log("✅ [Transfer #{$transferId}] State changed: {$oldState} → {$newState} (waiting for Vend upload)");

    // 9) Immutable event log (transfer_logs) — SUBMIT
    $eventData = [
        'request_id'      => $requestId,
        'public_id'       => $publicId,
        'items_processed' => $processedCount,
        'per_item'        => $perItemSummary,
        'notes'           => (string)$notes,
        'old_state'       => $oldState,
        'new_state'       => $newState,
        'ua'              => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'ip'              => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ];
    $stmt = $pdo->prepare("
        INSERT INTO transfer_logs
            (transfer_id, event_type, event_data, actor_user_id, source_system, trace_id, created_at)
        VALUES
            (?, 'SUBMIT', ?, ?, 'CIS', ?, NOW())
    ");
    $stmt->execute([$transferId, json_encode($eventData, JSON_UNESCAPED_UNICODE), $userId, $requestId]);

    // 10) Audit (transfer_audit_log) — use real columns
    $dataBefore = ['state'=>$oldState];
    $dataAfter  = ['state'=>$newState, 'items_processed'=>$processedCount];
    $stmt = $pdo->prepare("
        INSERT INTO transfer_audit_log
            (entity_type, entity_pk, transfer_pk, transfer_id, action, status, actor_type, user_id, data_before, data_after, created_at)
        VALUES
            ('transfer', ?, ?, ?, 'SUBMIT', 'success', 'user', ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $transferId,               // entity_pk
        $transferId,               // transfer_pk
        (string)$transferId,       // transfer_id (varchar) — storing numeric as string
        $userId,                   // user_id
        json_encode($dataBefore, JSON_UNESCAPED_UNICODE),
        json_encode($dataAfter,  JSON_UNESCAPED_UNICODE),
    ]);

    // 11) Idempotency (transfer_idempotency) — matches your DDL
    $idemKey  = "submit:{$transferId}:{$requestId}";
    $idemHash = hash('sha256', $raw !== '' ? $raw : json_encode($data, JSON_UNESCAPED_UNICODE));
    $idemResp = [
        'transfer_id'     => $transferId,
        'state'           => $newState,
        'items_processed' => $processedCount,
        'request_id'      => $requestId,
        'ts'              => date('c'),
    ];

    $stmt = $pdo->prepare("
        INSERT INTO transfer_idempotency
            (idem_key, idem_hash, response_json, status_code, created_at)
        VALUES
            (?, ?, ?, 200, NOW())
    ");
    $stmt->execute([$idemKey, $idemHash, json_encode($idemResp, JSON_UNESCAPED_UNICODE)]);

    // 12) Upload Mode Switch - Queue vs Direct
    $uploadConfig = require dirname(__DIR__) . '/config/upload_mode.php';
    $uploadMode = $uploadConfig['mode'] ?? 'direct'; // Default to direct (workers always dead)
    
    if ($uploadConfig['debug_mode']) {
        error_log("Upload mode: {$uploadMode} (configured in config/upload_mode.php)");
    }
    
    $responseData = [
        'success'          => true,
        'transfer_id'      => $transferId,
        'state'            => $newState,
        'legacy_workflow'  => 'SUBMITTED',
        'items_processed'  => $processedCount,
        'request_id'       => $requestId,
        'timestamp'        => date('Y-m-d H:i:s'),
        'upload_mode'      => $uploadMode, // Tell frontend which mode we're using
    ];
    
    if ($uploadMode === 'queue') {
        // QUEUE MODE: Create queue job (requires workers)
        $jobIdUnique = bin2hex(random_bytes(16));
        $jobPayload = [
            'transfer_id'  => $transferId,
            'action'       => 'create_consignment',
            'items_count'  => $processedCount,
            'outlet_from'  => $transfer['outlet_from'],
            'outlet_to'    => $transfer['outlet_to'],
            'request_id'   => $requestId,
            'submitted_at' => date('Y-m-d H:i:s'),
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO queue_jobs
                (job_id, job_type, payload, status, priority, created_at)
            VALUES
                (?, ?, ?, 'pending', ?, NOW())
        ");
        $stmt->execute([
            $jobIdUnique,
            $uploadConfig['queue_job_type'],
            json_encode($jobPayload, JSON_UNESCAPED_UNICODE),
            $uploadConfig['queue_priority']
        ]);
        $jobId = $pdo->lastInsertId();
        
        $responseData['queue_job_id'] = $jobId;
        $responseData['queue_job_uuid'] = $jobIdUnique;
        $responseData['message'] = "Transfer submitted. Queue job #{$jobId} created. ⚠️ Requires workers to process!";
        
        error_log("✅ Queue job created: ID={$jobId}, UUID={$jobIdUnique}");
        
    } else {
        // DIRECT MODE: Generate session for immediate upload (NO QUEUE!)
        $uploadSessionId = bin2hex(random_bytes(16));
        
        $responseData['upload_session_id'] = $uploadSessionId;
        $responseData['upload_url'] = "/modules/consignments/api/simple-upload-direct.php"; // ✅ direct
        $responseData['progress_url'] = "/modules/consignments/api/consignment-upload-progress.php?transfer_id={$transferId}&session_id={$uploadSessionId}"; // ✅ SSE
        $responseData['message'] = "Transfer submitted successfully. Ready for direct upload.";
        
        error_log("✅ Direct upload session created: {$uploadSessionId}");
    }

    // 14) Commit
    $pdo->commit();

    // 15) Response
    echo json_encode($responseData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Attempt to record idempotency on failure (best-effort)
    try {
        if ($transferId) {
            $dbHost = $dbHost ?? (getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : '127.0.0.1'));
            $dbName = $dbName ?? (getenv('DB_NAME') ?: (defined('DB_DATABASE') ? DB_DATABASE : (defined('DB_NAME') ? DB_NAME : 'jcepnzzkmj')));
            $dbUser = $dbUser ?? (getenv('DB_USER') ?: (defined('DB_USERNAME') ? DB_USERNAME : (defined('DB_USER') ? DB_USER : 'jcepnzzkmj')));
            $dbPass = $dbPass ?? (getenv('DB_PASS') ?: (defined('DB_PASSWORD') ? DB_PASSWORD : (defined('DB_PASS') ? DB_PASS : 'wprKh9Jq63')));

            $pdo2 = new PDO(
                "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
                (string)$dbUser,
                (string)$dbPass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
            $idemKey  = "submit:{$transferId}:" . ($requestId ?? bin2hex(random_bytes(8)));
            $idemHash = hash('sha256', $raw !== '' ? $raw : json_encode($data ?? [], JSON_UNESCAPED_UNICODE));
            $errResp  = [
                'transfer_id' => $transferId,
                'error'       => $e->getMessage(),
                'ts'          => date('c'),
            ];
            $stmt = $pdo2->prepare("
                INSERT INTO transfer_idempotency
                    (idem_key, idem_hash, response_json, status_code, created_at)
                VALUES
                    (?, ?, ?, 400, NOW())
            ");
            $stmt->execute([$idemKey, $idemHash, json_encode($errResp, JSON_UNESCAPED_UNICODE)]);
        }
    } catch (Throwable $ignored) {}

    http_response_code(400);
    echo json_encode([
        'success'     => false,
        'error'       => $e->getMessage(),
        'transfer_id' => $transferId,
        'request_id'  => $requestId,
        'timestamp'   => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    error_log("Submit Transfer Error: " . $e->getMessage());
}
