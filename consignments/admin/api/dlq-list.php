<?php
/**
 * DLQ List API
 *
 * Returns list of jobs in dead letter queue.
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

    $stmt = $pdo->query("
        SELECT 
            id,
            job_type,
            failed_at,
            attempts,
            error_message,
            payload
        FROM queue_jobs_dlq
        ORDER BY failed_at DESC
        LIMIT 50
    ");

    $jobs = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $jobs[] = [
            'id' => (int)$row['id'],
            'job_type' => $row['job_type'],
            'failed_at' => $row['failed_at'],
            'attempts' => (int)$row['attempts'],
            'error_message' => substr($row['error_message'], 0, 100) . '...',
            'payload' => $row['payload']
        ];
    }

    echo json_encode([
        'success' => true,
        'jobs' => $jobs,
        'count' => count($jobs)
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch DLQ',
        'message' => $e->getMessage()
    ]);
}
