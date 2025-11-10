<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];
    $transferId = (int)($data['transfer_id'] ?? 0);
    if ($transferId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Missing transfer_id']);
        exit;
    }

    // Stub: record intent in a log (can be replaced by DB later)
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
    $entry = ['ts'=>date('c'), 'transfer_id'=>$transferId, 'action'=>'store_summary', 'payload'=>$data];
    file_put_contents($logDir.'/store_summary.log', json_encode($entry, JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND);

    echo json_encode(['success' => true, 'message' => 'Summary recorded']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
