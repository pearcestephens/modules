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

    // Validate permissions, state, and payload details
    $db = get_db();

    // Check if transfer exists and get current state
    $stmt = $db->prepare("SELECT id, state, recipient_user_id FROM consignments WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $transferId);
    $stmt->execute();
    $result = $stmt->get_result();
    $transfer = $result->fetch_object();
    $stmt->close();

    if (!$transfer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Transfer not found']);
        exit;
    }

    // Validate state is OPEN or PACKING
    if (!in_array($transfer->state, ['OPEN', 'PACKING'])) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Transfer state is not valid for packing. Current state: ' . htmlspecialchars($transfer->state)]);
        exit;
    }

    // Check if user has permission to pack this transfer
    $userId = $_SESSION['user_id'] ?? 0;
    if ((int)$transfer->recipient_user_id !== $userId && !in_array($_SESSION['role'] ?? '', ['admin', 'manager'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    // Validate payload has required fields if provided
    if (!empty($data['items']) && !is_array($data['items'])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Items must be an array']);
        exit;
    }

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
