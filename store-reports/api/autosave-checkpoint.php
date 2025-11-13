<?php
/**
 * Autosave Checkpoint API
 * POST /api/autosave-checkpoint
 *
 * Creates autosave checkpoint for report
 * Supports debounced saves from mobile app
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../services/AutosaveService.php';

header('Content-Type: application/json');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Rate limiting
sr_rate_limit('autosave_checkpoint', 60, 30); // 30 saves per minute

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Validate required fields
$reportId = $input['report_id'] ?? null;
$checkpointData = $input['checkpoint_data'] ?? null;

if (!$reportId || !$checkpointData) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing required fields',
        'required' => ['report_id', 'checkpoint_data']
    ]);
    exit;
}

// Validate checkpoint data structure
if (!is_array($checkpointData)) {
    http_response_code(400);
    echo json_encode(['error' => 'checkpoint_data must be an object']);
    exit;
}

// Get authenticated user
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

try {
    $pdo = sr_pdo();

    // Verify report exists and belongs to user
    $stmt = $pdo->prepare("
        SELECT report_id, created_by, status, outlet_id
        FROM store_reports
        WHERE report_id = ?
    ");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        http_response_code(404);
        echo json_encode(['error' => 'Report not found']);
        exit;
    }

    // Check permissions (owner or manager)
    $isOwner = ($report['created_by'] == $userId);
    $isManager = false; // TODO: Check user role

    if (!$isOwner && !$isManager) {
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient permissions']);
        exit;
    }

    // Create autosave checkpoint
    $autosaveService = new AutosaveService($pdo);

    $checkpointId = $autosaveService->save(
        (int)$reportId,
        (int)$userId,
        $checkpointData,
        $input['device_id'] ?? null,
        $input['client_timestamp'] ?? time()
    );

    // Get checkpoint details
    $stmt = $pdo->prepare("
        SELECT
            checkpoint_id,
            checkpoint_data,
            device_id,
            created_at
        FROM store_report_autosave_checkpoints
        WHERE checkpoint_id = ?
    ");
    $stmt->execute([$checkpointId]);
    $checkpoint = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate data size
    $dataSize = strlen(json_encode($checkpoint['checkpoint_data']));

    // Log the autosave event
    $stmt = $pdo->prepare("
        INSERT INTO store_report_history (
            report_id, user_id, action, details
        ) VALUES (?, ?, 'autosave_checkpoint', ?)
    ");
    $stmt->execute([
        $reportId,
        $userId,
        json_encode([
            'checkpoint_id' => $checkpointId,
            'data_size' => $dataSize,
            'device_id' => $input['device_id'] ?? null
        ])
    ]);

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'checkpoint_id' => $checkpointId,
        'created_at' => $checkpoint['created_at'],
        'data_size' => $dataSize,
        'message' => 'Autosave checkpoint created'
    ]);

} catch (Exception $e) {
    sr_log_error('autosave_checkpoint_error', [
        'report_id' => $reportId,
        'user_id' => $userId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to create autosave checkpoint',
        'debug' => sr_is_dev() ? $e->getMessage() : null
    ]);
}
