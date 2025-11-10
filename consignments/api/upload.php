<?php
declare(strict_types=1);

// Upload kickoff stub – accepts transfer_id/session and responds OK
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
        exit;
    }
    $tid = (int)($_POST['transfer_id'] ?? 0);
    $sid = (string)($_POST['session_id'] ?? '');
    if ($tid <= 0 || $sid === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit;
    }
    // TODO: enqueue real job and start pipeline
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
