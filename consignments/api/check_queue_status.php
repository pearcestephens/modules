<?php
/**
 * Quick Queue Status Checker
 * 
 * Check if queue jobs are being created and if workers are processing them
 * 
 * Usage: https://staff.vapeshed.co.nz/modules/consignments/api/check_queue_status.php?transfer_id=27043
 */

header('Content-Type: application/json');

try {
    // Get transfer ID
    $transferId = (int)($_GET['transfer_id'] ?? 0);
    
    if ($transferId <= 0) {
        throw new Exception('Invalid transfer_id parameter');
    }
    
    // DB connection
    $dbHost = getenv('DB_HOST') ?: 'localhost';
    $dbName = getenv('DB_NAME') ?: 'jcepnzzkmj';
    $dbUser = getenv('DB_USER') ?: 'jcepnzzkmj';
    $dbPass = getenv('DB_PASS') ?: 'wprKh9Jq63';
    
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Check transfer state
    $stmt = $pdo->prepare("
        SELECT id, state, consignment_id, vend_transfer_id, created_at, updated_at
        FROM transfers
        WHERE id = ?
    ");
    $stmt->execute([$transferId]);
    $transfer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transfer) {
        throw new Exception("Transfer #{$transferId} not found");
    }
    
    // Check queue jobs for this transfer
    $stmt = $pdo->prepare("
        SELECT id, job_type, status, priority, attempts, last_error, 
               created_at, started_at, completed_at, failed_at
        FROM queue_jobs
        WHERE JSON_EXTRACT(payload, '$.transfer_id') = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$transferId]);
    $queueJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if queue_consignments record exists
    $stmt = $pdo->prepare("
        SELECT id, vend_consignment_id, status, sync_status, 
               last_sync_at, created_at, updated_at
        FROM queue_consignments
        WHERE transfer_id = ?
    ");
    $stmt->execute([$transferId]);
    $queueConsignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check latest queue_jobs status overall
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count
        FROM queue_jobs
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        GROUP BY status
    ");
    $recentJobStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if workers are running (approximation)
    $stmt = $pdo->query("
        SELECT COUNT(*) as active_workers
        FROM queue_jobs
        WHERE status = 'processing' 
        AND started_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $workerCheck = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Build response
    echo json_encode([
        'success' => true,
        'transfer' => [
            'id' => $transfer['id'],
            'state' => $transfer['state'],
            'has_consignment_id' => !empty($transfer['consignment_id']),
            'has_vend_id' => !empty($transfer['vend_transfer_id']),
            'updated_at' => $transfer['updated_at'],
        ],
        'queue_jobs' => [
            'total' => count($queueJobs),
            'jobs' => $queueJobs,
        ],
        'queue_consignment' => $queueConsignment ? [
            'exists' => true,
            'id' => $queueConsignment['id'],
            'vend_consignment_id' => $queueConsignment['vend_consignment_id'],
            'status' => $queueConsignment['status'],
            'sync_status' => $queueConsignment['sync_status'],
            'last_sync' => $queueConsignment['last_sync_at'],
        ] : [
            'exists' => false,
            'message' => 'No queue_consignments record found - this means the job hasn\'t been processed yet'
        ],
        'system_status' => [
            'recent_jobs_by_status' => $recentJobStats,
            'workers_active' => (int)$workerCheck['active_workers'] > 0,
            'active_worker_count' => (int)$workerCheck['active_workers'],
        ],
        'diagnosis' => [
            'transfer_submitted' => $transfer['state'] === 'SENT',
            'job_created' => count($queueJobs) > 0,
            'job_processed' => $queueConsignment !== false,
            'synced_to_lightspeed' => !empty($transfer['vend_transfer_id']),
        ],
        'next_steps' => getNextSteps($transfer, $queueJobs, $queueConsignment, $workerCheck)
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}

function getNextSteps($transfer, $jobs, $consignment, $workerCheck): array
{
    $steps = [];
    
    if ($transfer['state'] !== 'SENT') {
        $steps[] = "❌ Transfer is not in SENT state (current: {$transfer['state']})";
    } else {
        $steps[] = "✅ Transfer is in SENT state";
    }
    
    if (empty($jobs)) {
        $steps[] = "❌ NO QUEUE JOB CREATED - This is the problem! The submit script didn't create a queue job.";
        $steps[] = "🔧 Fix: Update submit_transfer_simple.php to create a queue_jobs entry";
    } else {
        $latestJob = $jobs[0];
        $steps[] = "✅ Queue job #{$latestJob['id']} created";
        
        if ($latestJob['status'] === 'pending') {
            if ($workerCheck['active_workers'] > 0) {
                $steps[] = "⏳ Job is pending, workers are active, should process soon";
            } else {
                $steps[] = "❌ Job is pending but NO WORKERS ARE RUNNING!";
                $steps[] = "🔧 Fix: Start the queue worker process";
            }
        } elseif ($latestJob['status'] === 'processing') {
            $steps[] = "⚙️ Job is currently being processed";
        } elseif ($latestJob['status'] === 'completed') {
            $steps[] = "✅ Job completed successfully";
        } elseif ($latestJob['status'] === 'failed') {
            $steps[] = "❌ Job failed: " . ($latestJob['last_error'] ?? 'Unknown error');
            $steps[] = "🔧 Check error logs and retry";
        }
    }
    
    if (!$consignment) {
        $steps[] = "⏳ No consignment record yet - job hasn't completed";
    } else {
        $steps[] = "✅ Consignment record exists (ID: {$consignment['id']})";
        $steps[] = "Lightspeed ID: " . ($consignment['vend_consignment_id'] ?? 'N/A');
        $steps[] = "Sync status: {$consignment['sync_status']}";
    }
    
    if (empty($transfer['vend_transfer_id'])) {
        $steps[] = "⏳ Not yet synced to Lightspeed (vend_transfer_id is NULL)";
    } else {
        $steps[] = "✅ Synced to Lightspeed! Vend ID: {$transfer['vend_transfer_id']}";
    }
    
    return $steps;
}
