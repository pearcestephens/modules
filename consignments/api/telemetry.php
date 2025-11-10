<?php
declare(strict_types=1);
/**
 * Consignments Telemetry Endpoint (lightweight)
 * Records client-side UX events for packing/receiving analytics.
 *
 * POST JSON { event, transfer_id, user_id?, payload, ts }
 */

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];

    $event = trim((string)($data['event'] ?? ''));
    $transferId = (int)($data['transfer_id'] ?? 0);
    $payload = $data['payload'] ?? [];
    $ts = (string)($data['ts'] ?? date('c'));

    if ($event === '' || $transferId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    // Minimal persistence: append to a module-scoped log (rotated by ops)
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
    $line = json_encode([
        'ts' => $ts,
        'event' => $event,
        'transfer_id' => $transferId,
        'user_id' => $_SESSION['staff_id'] ?? null,
        'payload' => $payload,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ], JSON_UNESCAPED_SLASHES);
    file_put_contents($logDir . '/telemetry.log', $line . "\n", FILE_APPEND);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
