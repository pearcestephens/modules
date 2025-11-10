<?php
/**
 * Error Log API
 *
 * Returns recent errors from webhook_events and queue_jobs.
 *
 * @package Consignments\Admin\API
 */

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

// Check authentication
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

try {
    $pdo = getDbConnection();

    // Get webhook failures
    $webhookStmt = $pdo->query("
        SELECT
            'webhook' as source,
            event_id as identifier,
            received_at as timestamp,
            'error' as level,
            CONCAT('Webhook failed: ', event_type) as message
        FROM webhook_events
        WHERE status = 'failed'
        ORDER BY received_at DESC
        LIMIT 50
    ");

    // Get queue failures
    $queueStmt = $pdo->query("
        SELECT
            'queue' as source,
            id as identifier,
            updated_at as timestamp,
            'error' as level,
            CONCAT('Job failed: ', job_type, ' - ', COALESCE(error_message, 'Unknown error')) as message
        FROM queue_jobs
        WHERE status = 'failed'
        ORDER BY updated_at DESC
        LIMIT 50
    ");

    $errors = [];

    // Combine webhook errors
    while ($row = $webhookStmt->fetch(PDO::FETCH_ASSOC)) {
        $errors[] = $row;
    }

    // Combine queue errors
    while ($row = $queueStmt->fetch(PDO::FETCH_ASSOC)) {
        $errors[] = $row;
    }

    // Sort by timestamp descending
    usort($errors, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });

    // Limit to 100
    $errors = array_slice($errors, 0, 100);

    echo json_encode([
        'success' => true,
        'errors' => $errors,
        'count' => count($errors)
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch error log',
        'message' => $e->getMessage()
    ]);
}
