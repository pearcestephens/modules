<?php
/**
 * Retry Job API
 *
 * Moves job from DLQ back to queue for retry.
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

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['dlq_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing dlq_id']);
        exit;
    }

    $dlqId = (int)$input['dlq_id'];
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    // Get job from DLQ
    $stmt = $pdo->prepare("
        SELECT
            job_type,
            payload,
            priority
        FROM queue_jobs_dlq
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$dlqId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Job not found in DLQ']);
        exit;
    }

    // Insert back into queue_jobs
    $insertStmt = $pdo->prepare("
        INSERT INTO queue_jobs (
            job_type,
            payload,
            priority,
            status,
            attempts,
            created_at
        ) VALUES (
            ?,
            ?,
            ?,
            'pending',
            0,
            NOW()
        )
    ");
    $insertStmt->execute([
        $job['job_type'],
        $job['payload'],
        $job['priority']
    ]);

    $newJobId = $pdo->lastInsertId();

    // Delete from DLQ
    $deleteStmt = $pdo->prepare("DELETE FROM queue_jobs_dlq WHERE id = ?");
    $deleteStmt->execute([$dlqId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Job queued for retry',
        'new_job_id' => $newJobId
    ]);

} catch (\Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to retry job',
        'message' => $e->getMessage()
    ]);
}
