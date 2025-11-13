<?php
/**
 * Autosave Recovery API
 * GET /api/autosave-recover
 *
 * Recovers latest autosave checkpoint for a report
 * Handles conflict detection and resolution
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../services/AutosaveService.php';

header('Content-Type: application/json');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get query parameters
$reportId = $_GET['report_id'] ?? null;
$checkpointId = $_GET['checkpoint_id'] ?? null;
$deviceId = $_GET['device_id'] ?? null;

if (!$reportId) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing required parameter: report_id'
    ]);
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
        SELECT report_id, created_by, status, updated_at, outlet_id
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

    // Check permissions
    $isOwner = ($report['created_by'] == $userId);
    $isManager = false; // TODO: Check user role

    if (!$isOwner && !$isManager) {
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient permissions']);
        exit;
    }

    // Initialize autosave service
    $autosaveService = new AutosaveService($pdo);

    // Recover checkpoint
    if ($checkpointId) {
        // Recover specific checkpoint
        $checkpoint = $autosaveService->recover((int)$reportId, (int)$userId, (int)$checkpointId);
    } else {
        // Recover latest checkpoint for device or user
        $checkpoint = $autosaveService->recover((int)$reportId, (int)$userId, null, $deviceId);
    }

    if (!$checkpoint) {
        http_response_code(404);
        echo json_encode([
            'error' => 'No autosave checkpoint found',
            'report_id' => $reportId
        ]);
        exit;
    }

    // Check for conflicts
    $conflicts = $autosaveService->detectConflicts((int)$reportId);

    // Get all available checkpoints
    $allCheckpoints = $autosaveService->listCheckpoints((int)$reportId);

    // Log recovery event
    $stmt = $pdo->prepare("
        INSERT INTO store_report_history (
            report_id, user_id, action, details
        ) VALUES (?, ?, 'autosave_recovered', ?)
    ");
    $stmt->execute([
        $reportId,
        $userId,
        json_encode([
            'checkpoint_id' => $checkpoint['checkpoint_id'],
            'device_id' => $deviceId,
            'had_conflicts' => !empty($conflicts)
        ])
    ]);

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'checkpoint' => [
            'checkpoint_id' => $checkpoint['checkpoint_id'],
            'checkpoint_data' => $checkpoint['checkpoint_data'],
            'created_at' => $checkpoint['created_at'],
            'device_id' => $checkpoint['device_id'],
            'user_id' => $checkpoint['user_id']
        ],
        'conflicts' => $conflicts,
        'available_checkpoints' => array_map(function($cp) {
            return [
                'checkpoint_id' => $cp['checkpoint_id'],
                'created_at' => $cp['created_at'],
                'device_id' => $cp['device_id'],
                'data_size' => strlen(json_encode($cp['checkpoint_data']))
            ];
        }, $allCheckpoints),
        'report_last_modified' => $report['updated_at'],
        'message' => 'Autosave checkpoint recovered'
    ]);

} catch (Exception $e) {
    sr_log_error('autosave_recover_error', [
        'report_id' => $reportId,
        'user_id' => $userId,
        'checkpoint_id' => $checkpointId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to recover autosave checkpoint',
        'debug' => sr_is_dev() ? $e->getMessage() : null
    ]);
}
