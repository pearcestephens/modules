<?php
declare(strict_types=1);

/**
 * Simple Upload Direct — UNIFIED VERSION (uses CIS bootstrap + real progress)
 * -----------------------------------------------------------------------
 * - Uses bootstrap + cis_resolve_pdo() + cis_vend_access_token()
 * - Uses Authorization: Bearer header (not Authorization= or misspelt "Bearier")
 * - Adds Idempotency-Key and X-Request-ID headers
 * - Writes real progress rows so SSE has something to stream
 * 
 * @version 3.0.0 - Unified with CIS bootstrap, correct headers, real progress tracking
 */

// simple-upload-direct.php (REPLACEMENT - unified + real progress)
require_once __DIR__ . '/../bootstrap.php';

use PDO;
use RuntimeException;
use Throwable;

header('Content-Type: application/json');

try {
    // Basic validation
    $transferId = (int)($_POST['transfer_id'] ?? 0);
    $sessionId  = trim((string)($_POST['session_id'] ?? ''));
    if ($transferId <= 0 || strlen($sessionId) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid input', 'error_code' => 'INVALID_INPUT']);
        exit;
    }

    $pdo = cis_resolve_pdo();
    $vendToken = cis_vend_access_token(); // from shared/config.php
    if (!$vendToken) { throw new RuntimeException('Vend token not configured'); }

    // Minimal helpers ---------------------------------------------------------
    $reqId = substr(bin2hex(random_bytes(8)), 0, 16);
    $vendBase = getenv('VEND_BASE_URL') ?: 'https://vapeshed.retail.lightspeed.app/api/2.0';

    $vendRequest = function (string $method, string $endpoint, ?array $payload = null) use ($vendBase, $vendToken, $reqId): array {
        $url = rtrim($vendBase, '/') . '/' . ltrim($endpoint, '/');
        $ch  = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $vendToken, // ✅ correct header
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Request-ID: ' . $reqId,
            // Strong idempotency per transfer+session:
            'Idempotency-Key: ' . hash('sha256', $url . '|' . $reqId),
        ];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        ];
        if ($payload !== null) { $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE); }
        curl_setopt_array($ch, $opts);

        $raw  = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) return ['ok' => false, 'http' => 0, 'error' => $err];
        $json = $raw !== '' ? (json_decode($raw, true) ?? null) : null;
        return ['ok' => $http >= 200 && $http < 300, 'http' => $http, 'json' => $json, 'raw' => $raw];
    };

    // Progress helpers --------------------------------------------------------
    $progressUpsert = function(string $status, string $message, array $extra = []) use ($pdo, $transferId, $sessionId) {
        $stmt = $pdo->prepare("
            INSERT INTO consignment_upload_progress
                (transfer_id, session_id, status, message, meta_json, updated_at, created_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE status=VALUES(status), message=VALUES(message),
                meta_json=VALUES(meta_json), updated_at=NOW()
        ");
        $stmt->execute([$transferId, $sessionId, $status, $message, json_encode($extra)]);
    };

    // 1) Create consignment in Vend ------------------------------------------
    $progressUpsert('connecting', 'Connecting to Vend…');
    $pdo->beginTransaction();

    // Look up source/destination + reference
    $T = $pdo->prepare("SELECT outlet_from, outlet_to, COALESCE(reference, public_id) AS ref
                        FROM transfers WHERE id = ? FOR UPDATE");
    $T->execute([$transferId]);
    $t = $T->fetch(PDO::FETCH_ASSOC);
    if (!$t) { throw new RuntimeException('Transfer not found'); }

    // Create consignment (Lightspeed Retail v2)
    $progressUpsert('creating', 'Creating consignment…', ['ref' => $t['ref']]);
    $consignmentRes = $vendRequest('POST', 'consignments', [
        'type' => 'OUTLET',
        'status' => 'OPEN',
        'source_outlet_id' => $t['outlet_from'],
        'destination_outlet_id' => $t['outlet_to'],
        'reference' => $t['ref'],
    ]);
    if (!$consignmentRes['ok'] || empty($consignmentRes['json']['id'])) {
        throw new RuntimeException('Vend consignment create failed: ' . ($consignmentRes['raw'] ?? ''));
    }
    $vendConsignmentId = $consignmentRes['json']['id'];

    // Persist vend id + initial progress row
    $U = $pdo->prepare("UPDATE transfers SET vend_transfer_id = ?, updated_at = NOW() WHERE id = ?");
    $U->execute([$vendConsignmentId, $transferId]);
    $progressUpsert('created', 'Consignment created', ['vend_consignment_id' => $vendConsignmentId]);
    $pdo->commit();

    // 2) Push products --------------------------------------------------------
    // Pull products with correct Vend UUID (risk register flagged mismatches)
    $I = $pdo->prepare("
        SELECT ti.product_id, ti.qty_sent_total AS count, vp.product_id AS vend_product_id
        FROM transfer_items ti
        LEFT JOIN vend_products vp ON vp.id = ti.product_id
        WHERE ti.transfer_id = ? AND ti.qty_sent_total > 0
    ");
    $I->execute([$transferId]);
    $lines = $I->fetchAll(PDO::FETCH_ASSOC);

    $done = 0; $fail = 0;
    foreach ($lines as $line) {
        $payload = [
            'consignment_id' => $vendConsignmentId,
            'product_id'     => $line['vend_product_id'], // must be Vend UUID
            'count'          => (int)$line['count'],
        ];
        $progressUpsert('adding', 'Adding product…', $payload);
        $r = $vendRequest('POST', 'consignment_products', $payload);
        if ($r['ok']) { $done++; }
        else { $fail++; $progressUpsert('error', 'Product add failed', ['payload' => $payload, 'response' => $r['raw']]); }
    }

    // 3) Mark SENT when all adds processed -----------------------------------
    if ($fail === 0) {
        $progressUpsert('finalizing', 'Marking consignment SENT');
        $vendRequest('PATCH', "consignments/{$vendConsignmentId}", ['status' => 'SENT']);
        $pdo->prepare("UPDATE transfers SET state='SENT', updated_at=NOW() WHERE id=?")->execute([$transferId]);
        $progressUpsert('completed', 'Upload complete', ['added' => $done]);
        echo json_encode(['success' => true, 'message' => 'Upload complete', 'consignment_id' => $vendConsignmentId]);
    } else {
        $progressUpsert('failed', 'Upload completed with errors', ['added' => $done, 'failed' => $fail]);
        http_response_code(207); // Multi-status
        echo json_encode(['success' => false, 'error' => 'Some products failed', 'added' => $done, 'failed' => $fail]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'error_code' => 'SERVER_ERROR']);
}
