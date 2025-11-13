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

    // Verify transfer exists
    $db = get_db();
    $stmt = $db->prepare("SELECT id FROM consignments WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $tid);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Transfer not found']);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Enqueue real job for transfer processing pipeline
    $stmt = $db->prepare("
        INSERT INTO queue_jobs (type, payload, status, priority, created_at)
        VALUES ('transfer.process', ?, 'pending', 5, NOW())
    ");

    $payload = json_encode([
        'transfer_id' => $tid,
        'session_id' => $sid,
        'action' => 'process_pack_upload'
    ]);

    $stmt->bind_param('s', $payload);
    $stmt->execute();
    $jobId = $db->insert_id;
    $stmt->close();

    echo json_encode([
        'success' => true,
        'job_id' => $jobId,
        'transfer_id' => $tid,
        'session_id' => $sid,
        'message' => 'Transfer processing queued'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
}
