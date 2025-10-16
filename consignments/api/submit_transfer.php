<?php
declare(strict_types=1);

/**
 * Submit Transfer API — hardened, sane
 * - POST only; validates payload
 * - Checks transfer exists & allowed state
 * - 5-minute idempotency window
 * - Updates state with real old_state in audit
 * - Persists counted quantities; logs inventory move
 * - Creates Lightspeed/Vend consignment
 * - Writes submission and idempotency records
 *
 * Assumes:
 * - CISSecureDatabase::secureExecute(sql, params, mode) → PDOStatement
 * - CISSecureDatabase::secureTransaction(closure, isolationLevel)
 * - CISSecureAPI::processRequest(action, data, method) calls handleSubmitTransfer()
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/lib/SecureDatabase.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/lib/SecureAPI.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/shared/functions/config.php';

/** Entry point (invoked by CISSecureAPI for action: submit_transfer) */
function handleSubmitTransfer(array $data, string $method, string $requestId): array
{
    if (strtoupper($method) !== 'POST') {
        throw new Exception('Method not allowed; use POST');
    }
    if (!isset($data['transfer_id'])) {
        throw new Exception('transfer_id is required');
    }
    if (!isset($data['items']) || !is_array($data['items'])) {
        throw new Exception('items is required and must be an array');
    }

    $dbConfig = [
        'host'     => $_ENV['DB_HOST'] ?? 'localhost',
        'database' => $_ENV['DB_NAME'] ?? 'main_db',
        'username' => $_ENV['DB_USER'] ?? 'cis_user',
        'password' => $_ENV['DB_PASS'] ?? 'secure_password_123',
        'ssl'      => true,
        'ssl_ca'   => $_ENV['DB_SSL_CA'] ?? null,
        'port'     => (int)($_ENV['DB_PORT'] ?? 3306),
    ];
    $secureDB = new CISSecureDatabase($dbConfig);

    return $secureDB->secureTransaction(
        function ($db) use ($data, $requestId) {
            return processSecureTransferSubmission($db, $data, $requestId);
        },
        'SERIALIZABLE'
    );
}

/** Main submission workflow */
function processSecureTransferSubmission(CISSecureDatabase $db, array $data, string $requestId): array
{
    $transferId = (int)$data['transfer_id'];
    $items      = $data['items'] ?? [];
    $notes      = (string)($data['notes'] ?? '');

    // 1) Transfer exists & allowed state
    $transfer = validateTransferAccess($db, $transferId);

    // 2) Idempotency (5 minutes)
    checkIdempotency($db, $transferId);

    // 3) Validate transfer items
    $validatedItems = validateTransferItems($db, $transferId, $items);

    // 4) Update state (+audit)
    updateTransferState($db, $transferId, 'SUBMITTED', $notes, $requestId);

    // 5) Persist counted quantities (+inventory audit log)
    processInventoryUpdates($db, $transferId, $validatedItems, $requestId);

    // 6) Create Vend/X-Series consignment
    $consignmentResult = createLightspeedConsignment($db, $transfer, $validatedItems, $requestId);

    // 7) Log submission
    logTransferSubmission($db, $transferId, $validatedItems, $consignmentResult, $requestId);

    // 8) Mark idempotency
    createIdempotencyRecord($db, $transferId, $requestId);

    return [
        'transfer_id'     => $transferId,
        'state'           => 'SUBMITTED',
        'consignment_id'  => $consignmentResult['consignment_id'] ?? null,
        'items_processed' => count($validatedItems),
        'timestamp'       => date('Y-m-d H:i:s'),
        'request_id'      => $requestId,
    ];
}

/** Transfer must exist and be in OPEN or PACKING (category STOCK) */
function validateTransferAccess(CISSecureDatabase $db, int $transferId): array
{
    $sql = "SELECT id, transfer_category, state, outlet_from, outlet_to
            FROM transfers
            WHERE id = ? AND transfer_category = 'STOCK' AND state IN ('OPEN','PACKING')";
    $stmt = $db->secureExecute($sql, [$transferId], 'READ');
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception("Transfer not found or invalid state: {$transferId}");
    }
    return $row;
}

/** Reject re-submits within 5 minutes (simple window) */
function checkIdempotency(CISSecureDatabase $db, int $transferId): void
{
    $sql = "SELECT request_id, created_at
            FROM transfer_idempotency
            WHERE transfer_id = ? AND action = 'SUBMIT'
            ORDER BY created_at DESC
            LIMIT 1";
    $stmt = $db->secureExecute($sql, [$transferId], 'READ');
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing && (time() - strtotime($existing['created_at']) < 300)) {
        throw new Exception('Duplicate submission detected (within 5 minutes)');
    }
}

/** Validate each item belongs to transfer and quantities are sane */
function validateTransferItems(CISSecureDatabase $db, int $transferId, array $items): array
{
    if (empty($items)) {
        throw new Exception('No items provided for transfer');
    }

    $validated = [];
    foreach ($items as $item) {
        $productId  = (int)($item['product_id'] ?? 0);
        $countedQty = (int)($item['counted_qty'] ?? -1);

        if ($productId <= 0 || $countedQty < 0) {
            throw new Exception("Invalid item data: product_id={$productId}, counted_qty={$countedQty}");
        }

        $sql = "SELECT id, product_id, qty_requested
                FROM transfer_items
                WHERE transfer_id = ? AND product_id = ?";
        $stmt = $db->secureExecute($sql, [$transferId, $productId], 'READ');
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception("Product {$productId} not in transfer {$transferId}");
        }

        // Guardrail: counted cannot be wildly above plan (same as original intent)
        $planned = (int)$row['qty_requested'];
        if ($countedQty > ($planned * 2)) {
            throw new Exception("Counted quantity {$countedQty} exceeds reasonable limit for product {$productId}");
        }

        $validated[] = [
            'transfer_item_id' => (int)$row['id'],
            'product_id'       => $productId,
            'planned_qty'      => $planned,
            'counted_qty'      => $countedQty,
            'variance'         => $countedQty - $planned,
        ];
    }
    return $validated;
}

/** Update state and write audit with the real old state */
function updateTransferState(CISSecureDatabase $db, int $transferId, string $newState, string $notes, string $requestId): void
{
    $old = $db->secureExecute(
        "SELECT state FROM transfers WHERE id = ?",
        [$transferId],
        'READ'
    )->fetch(PDO::FETCH_ASSOC);
    $oldState = $old['state'] ?? null;

    $db->secureExecute(
        "UPDATE transfers
         SET state = ?, updated_at = NOW(),
             notes = CONCAT(COALESCE(notes,''), ?, '\n--- Submitted via API (', ?, ') ---\n')
         WHERE id = ?",
        [$newState, (string)$notes, $requestId, $transferId],
        'WRITE'
    );

    $userId = (int)($_SESSION['user_id'] ?? 0);
    $db->secureExecute(
        "INSERT INTO transfer_audit_log
           (transfer_id, action, old_state, new_state, changed_by, change_reason, request_id, created_at)
         VALUES
           (?, 'STATE_CHANGE', ?, ?, ?, 'Transfer submitted via API', ?, NOW())",
        [$transferId, $oldState, $newState, $userId, $requestId],
        'WRITE'
    );

    $meta = json_encode([
        'method'       => 'API',
        'notes_length' => strlen((string)$notes),
        'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    ], JSON_UNESCAPED_SLASHES);

    $db->secureExecute(
        "INSERT INTO transfer_behavior_patterns
           (transfer_id, action_type, timestamp, user_id, metadata, request_id)
         VALUES
           (?, 'SUBMIT', NOW(), ?, ?, ?)",
        [$transferId, $userId, $meta, $requestId],
        'WRITE'
    );
}

/** Persist counted quantities and minimal inventory audit log */
function processInventoryUpdates(CISSecureDatabase $db, int $transferId, array $validatedItems, string $requestId): void
{
    $userId = (int)($_SESSION['user_id'] ?? 0);

    foreach ($validatedItems as $item) {
        $db->secureExecute(
            "UPDATE transfer_items
             SET qty_sent_total = ?, updated_at = NOW()
             WHERE id = ?",
            [$item['counted_qty'], $item['transfer_item_id']],
            'WRITE'
        );

        $db->secureExecute(
            "INSERT INTO inventory_audit_log
               (product_id, movement_type, quantity, reference_type, reference_id, created_by, request_id, created_at)
             VALUES
               (?, 'TRANSFER_SUBMIT', ?, 'TRANSFER', ?, ?, ?, NOW())",
            [$item['product_id'], $item['counted_qty'], $transferId, $userId, $requestId],
            'WRITE'
        );
    }
}

/** Create a Lightspeed/X-Series consignment */
function createLightspeedConsignment(CISSecureDatabase $db, array $transfer, array $validatedItems, string $requestId): array
{
    $accessToken = cis_vend_access_token(true);

    $payload = [
        'name'                  => "Transfer #{$transfer['id']} - " . date('Y-m-d H:i:s'),
        'type'                  => 'transfer', // outlet→outlet
        'source_outlet_id'      => $transfer['outlet_from'],
        'destination_outlet_id' => $transfer['outlet_to'],
        'products'              => [],
    ];
    foreach ($validatedItems as $it) {
        $payload['products'][] = [
            'product_id' => $it['product_id'],
            'count'      => $it['counted_qty'],
        ];
    }

    $resp = makeLightspeedAPICall(
        'https://api.vendhq.com/api/2.0/consignments',
        $payload,
        $accessToken,
        $requestId
    );

    // Some responses return id at root; others nest it
    $consignmentId = $resp['id'] ?? ($resp['consignment']['id'] ?? null);
    if (!$consignmentId) {
        throw new Exception('Failed to create consignment (no id in response)');
    }

    $db->secureExecute(
        "INSERT INTO lightspeed_consignments (transfer_id, consignment_id, status, created_at, request_id)
         VALUES (?, ?, 'CREATED', NOW(), ?)",
        [$transfer['id'], $consignmentId, $requestId],
        'WRITE'
    );

    return ['consignment_id' => $consignmentId, 'status' => 'CREATED'];
}

/** Minimal POST helper (accept any 2xx) */
function makeLightspeedAPICall(string $url, array $data, string $token, string $requestId): array
{
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'User-Agent: CIS-SecureAPI/2.0',
        'X-Request-ID: ' . $requestId,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data, JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS      => 0,
    ]);

    $raw  = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        throw new Exception('Lightspeed/Vend cURL error: ' . $err);
    }
    if ($http < 200 || $http >= 300) {
        throw new Exception('Lightspeed/Vend HTTP error: ' . $http);
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Lightspeed/Vend JSON decode error: ' . json_last_error_msg());
    }

    return $decoded;
}

/** Submission log */
function logTransferSubmission(CISSecureDatabase $db, int $transferId, array $items, array $consignmentResult, string $requestId): void
{
    $userId = (int)($_SESSION['user_id'] ?? 0);

    $metadata = json_encode([
        'items'       => $items,
        'consignment' => $consignmentResult,
        'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'ip'          => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ], JSON_UNESCAPED_SLASHES);

    $db->secureExecute(
        "INSERT INTO transfer_submissions_log
           (transfer_id, items_count, consignment_id, status, request_id, submitted_by, submitted_at, metadata)
         VALUES
           (?, ?, ?, 'SUCCESS', ?, ?, NOW(), ?)",
        [$transferId, count($items), $consignmentResult['consignment_id'] ?? null, $requestId, $userId, $metadata],
        'WRITE'
    );
}

/** Mark idempotency for this transfer submit */
function createIdempotencyRecord(CISSecureDatabase $db, int $transferId, string $requestId): void
{
    $db->secureExecute(
        "INSERT INTO transfer_idempotency (transfer_id, action, request_id, created_at, expires_at)
         VALUES (?, 'SUBMIT', ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR))",
        [$transferId, $requestId],
        'WRITE'
    );
}

/* ---------- Request bootstrap ---------- */

$securityConfig = [
    'enforce_https'       => true,
    'require_api_key'     => false, // session auth in this context
    'enable_csrf'         => true,
    'enable_rate_limiting'=> true,
    'log_all_requests'    => true,
    'allowed_origins'     => [],
    'environment'         => 'production',
];

try {
    $secureAPI = new CISSecureAPI($securityConfig);
    $result    = $secureAPI->processRequest('submit_transfer', $_POST, $_SERVER['REQUEST_METHOD']);

    header('Content-Type: application/json');
    echo json_encode($result, JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    error_log('Submit Transfer API error: ' . $e->getMessage());
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'success'   => false,
        'error'     => [
            'message' => 'Request failed',
            'detail'  => $e->getMessage(),
            'code'    => 'SUBMISSION_FAILED',
        ],
        'timestamp' => time(),
    ], JSON_UNESCAPED_SLASHES);
}
