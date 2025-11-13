<?php
/**
 * Sync Status API
 *
 * Returns real-time queue, webhook, DLQ, and cursor statistics.
 *
 * @package Consignments\Admin\API
 */

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

try {
    $pdo = getDbConnection();

    // Queue statistics
    $queueStmt = $pdo->query("
        SELECT
            status,
            COUNT(*) as count
        FROM queue_jobs
        WHERE status IN ('pending', 'processing', 'failed')
        GROUP BY status
    ");
    $queueStats = [
        'pending' => 0,
        'processing' => 0,
        'failed' => 0
    ];
    while ($row = $queueStmt->fetch(PDO::FETCH_ASSOC)) {
        $queueStats[$row['status']] = (int)$row['count'];
    }

    // Webhook statistics (last 24 hours)
    $webhookStmt = $pdo->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful,
            event_type,
            COUNT(*) as count
        FROM webhook_events
        WHERE received_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY event_type
    ");

    $webhookTotal = 0;
    $webhookSuccessful = 0;
    $webhookByType = [];

    while ($row = $webhookStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['event_type']) {
            $webhookByType[$row['event_type']] = (int)$row['count'];
        }
    }

    // Get totals
    $totalStmt = $pdo->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful
        FROM webhook_events
        WHERE received_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $totalRow = $totalStmt->fetch(PDO::FETCH_ASSOC);
    $webhookTotal = (int)($totalRow['total'] ?? 0);
    $webhookSuccessful = (int)($totalRow['successful'] ?? 0);
    $webhookSuccessRate = $webhookTotal > 0 ? $webhookSuccessful / $webhookTotal : 0;

    // DLQ statistics
    $dlqStmt = $pdo->query("
        SELECT
            COUNT(*) as count,
            MIN(failed_at) as oldest
        FROM queue_jobs_dlq
    ");
    $dlqRow = $dlqStmt->fetch(PDO::FETCH_ASSOC);
    $dlqStats = [
        'count' => (int)($dlqRow['count'] ?? 0),
        'oldest' => $dlqRow['oldest'] ?? null
    ];

    // Sync cursor
    $cursorStmt = $pdo->query("
        SELECT
            last_processed_id,
            updated_at
        FROM sync_cursors
        WHERE cursor_type = 'consignments'
        LIMIT 1
    ");
    $cursorRow = $cursorStmt->fetch(PDO::FETCH_ASSOC);
    $cursorStats = [
        'last_processed_id' => $cursorRow['last_processed_id'] ?? null,
        'updated_at' => $cursorRow['updated_at'] ?? null
    ];

    // Build response
    $response = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'queue' => $queueStats,
        'webhooks' => [
            'last_hour' => $webhookTotal,
            'success_rate' => $webhookSuccessRate,
            'by_type' => $webhookByType
        ],
        'dlq' => $dlqStats,
        'cursor' => $cursorStats
    ];

    echo json_encode($response);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch sync status',
        'message' => $e->getMessage()
    ]);
}
