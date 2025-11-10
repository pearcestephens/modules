<?php
declare(strict_types=1);

// Consignments – Pack Submit (stub for integration)
// Accepts JSON payload from pack UI and returns session + endpoints for upload/SSE

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }

    $transferId = (int)($data['transfer_id'] ?? 0);
    if ($transferId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Missing transfer_id']);
        exit;
    }

    // TODO: validate permissions, state (OPEN|PACKING), and payload details

    $sessionId = bin2hex(random_bytes(12));
    $base = '/modules/consignments/api';

    echo json_encode([
        'success' => true,
        'upload_session_id' => $sessionId,
        'progress_url' => $base . '/progress.php?sid=' . $sessionId,
        'upload_url' => $base . '/upload.php',
        'message' => 'Session initialized'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
