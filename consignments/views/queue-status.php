<?php
/**
 * Queue Status - Monitoring View
 *
 * Monitor the Lightspeed sync queue and job processing.
 *
 * @package CIS\Consignments
 * @version 3.0.0
 */

declare(strict_types=1);

// Page metadata
$pageTitle = 'Queue Status';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/'],
    ['label' => 'Queue Status', 'url' => '', 'active' => true]
];

// Get database connection
$pdo = CIS\Base\Database::pdo();

// Load queue statistics
$statsStmt = $pdo->query("
    SELECT
        status,
        COUNT(*) as count,
        MIN(created_at) as oldest,
        MAX(created_at) as newest
    FROM vend_consignment_queue
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY status
");

$queueStats = [];
$totalJobs = 0;
while ($row = $statsStmt->fetch(PDO::FETCH_ASSOC)) {
    $queueStats[$row['status']] = $row;
    $totalJobs += (int)$row['count'];
}

// Load recent jobs
$jobsStmt = $pdo->query("
    SELECT
        id,
        consignment_id,
        action,
        status,
        attempt_count,
        created_at,
        updated_at,
        completed_at,
        error_message
    FROM vend_consignment_queue
    ORDER BY created_at DESC
    LIMIT 50
");

$recentJobs = [];
while ($row = $jobsStmt->fetch(PDO::FETCH_ASSOC)) {
    $recentJobs[] = $row;
}

// Calculate health score
$healthScore = 100;
$pendingCount = (int)($queueStats['pending']['count'] ?? 0);
$failedCount = (int)($queueStats['failed']['count'] ?? 0);
$processingCount = (int)($queueStats['processing']['count'] ?? 0);

if ($pendingCount > 100) $healthScore -= 20;
if ($failedCount > 10) $healthScore -= 30;
if ($processingCount > 50) $healthScore -= 10;

$healthColor = $healthScore >= 80 ? 'success' : ($healthScore >= 60 ? 'warning' : 'danger');

// Start output buffering
ob_start();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">
            <i class="fas fa-tasks text-primary me-2"></i>
            Queue Status
        </h1>
        <p class="text-muted mb-0">Monitor Lightspeed sync queue and job processing</p>
    </div>
    <div>
        <button class="btn btn-primary" onclick="location.reload()">
            <i class="fas fa-sync me-2"></i>
            Refresh
        </button>
    </div>
</div>

<!-- Health Score -->
<div class="alert alert-<?= $healthColor ?> mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1">Queue Health Score</h4>
            <p class="mb-0">System is operating at <?= $healthScore ?>% capacity</p>
        </div>
        <div>
            <h1 class="display-4 mb-0"><?= $healthScore ?>%</h1>
        </div>
    </div>
</div>

<!-- Queue Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="text-muted">Total Jobs (24h)</h5>
                <h2 class="mb-0"><?= number_format($totalJobs) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="text-muted">Pending</h5>
                <h2 class="mb-0 text-warning"><?= number_format($pendingCount) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="text-muted">Processing</h5>
                <h2 class="mb-0 text-info"><?= number_format($processingCount) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="text-muted">Failed</h5>
                <h2 class="mb-0 text-danger"><?= number_format($failedCount) ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Recent Jobs -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Recent Jobs</h5>
    </div>
    <div class="card-body">
        <table class="table table-hover" id="jobsTable">
            <thead>
                <tr>
                    <th>Job ID</th>
                    <th>Consignment</th>
                    <th>Action</th>
                    <th>Status</th>
                    <th>Attempts</th>
                    <th>Created</th>
                    <th>Completed</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentJobs as $job): ?>
                    <tr>
                        <td><?= $job['id'] ?></td>
                        <td><?= htmlspecialchars($job['consignment_id']) ?></td>
                        <td><?= htmlspecialchars($job['action']) ?></td>
                        <td>
                            <?php
                            $statusBadge = 'secondary';
                            switch ($job['status']) {
                                case 'completed': $statusBadge = 'success'; break;
                                case 'failed': $statusBadge = 'danger'; break;
                                case 'processing': $statusBadge = 'info'; break;
                                case 'pending': $statusBadge = 'warning'; break;
                            }
                            ?>
                            <span class="badge bg-<?= $statusBadge ?>">
                                <?= htmlspecialchars($job['status']) ?>
                            </span>
                        </td>
                        <td><?= $job['attempt_count'] ?></td>
                        <td><?= date('H:i:s', strtotime($job['created_at'])) ?></td>
                        <td><?= $job['completed_at'] ? date('H:i:s', strtotime($job['completed_at'])) : '-' ?></td>
                        <td>
                            <?php if ($job['error_message']): ?>
                                <small class="text-danger" title="<?= htmlspecialchars($job['error_message']) ?>">
                                    <?= htmlspecialchars(substr($job['error_message'], 0, 50)) ?>...
                                </small>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Initialize DataTable
    $(document).ready(function() {
        $('#jobsTable').DataTable({
            order: [[5, 'desc']], // Sort by created date desc
            pageLength: 25,
            responsive: true
        });

        // Auto-refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
    });
</script>

<?php
// Get buffered content
$content = ob_get_clean();

// Include BASE dashboard layout
require_once dirname(dirname(__DIR__)) . '/base/_templates/layouts/dashboard.php';
