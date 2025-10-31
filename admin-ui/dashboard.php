<?php
/**
 * Smart Cron V2 - Dashboard with Live Execution History & Metrics
 *
 * Real-time job monitoring, execution history, detailed metrics
 * Click any job to see full execution logs and performance data
 *
 * @package CIS\SmartCron\Dashboard
 * @version 2.0.0
 */

declare(strict_types=1);

// STANDALONE: No app.php dependency (avoids auth redirects)
// Database connection
try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4',
        'jcepnzzkmj',
        'wprKh9Jq63',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// Get job details if requested
if (isset($_GET['job_id'])) {
    $job_id = (int)$_GET['job_id'];

    // Get job info
    $stmt = $pdo->prepare("SELECT * FROM smart_cron_integrated_jobs WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        http_response_code(404);
        die('Job not found');
    }

    // Get recent executions (last 50)
    $stmt = $pdo->prepare("
        SELECT
            id,
            execution_uuid,
            started_at,
            completed_at,
            status,
            duration_seconds,
            memory_peak_mb,
            exit_code,
            error_message,
            output_snippet
        FROM smart_cron_executions
        WHERE task_name = ?
        ORDER BY started_at DESC
        LIMIT 50
    ");
    $stmt->execute([$job['job_name']]);
    $executions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate statistics
    $total_executions = count($executions);
    $successful = 0;
    $failed = 0;
    $total_duration = 0;
    $max_memory = 0;
    $avg_duration = 0;
    $avg_memory = 0;

    foreach ($executions as $exec) {
        if ($exec['status'] === 'success') {
            $successful++;
        } else {
            $failed++;
        }
        $total_duration += $exec['duration_seconds'];
        $max_memory = max($max_memory, $exec['memory_peak_mb']);
    }

    if ($total_executions > 0) {
        $avg_duration = round($total_duration / $total_executions, 3);
        $avg_memory = round(array_sum(array_column($executions, 'memory_peak_mb')) / $total_executions, 2);
    }

    $success_rate = $total_executions > 0 ? round(($successful / $total_executions) * 100, 1) : 0;

} else {
    // Get all jobs with recent execution stats
    $stmt = $pdo->query("
        SELECT
            j.id,
            j.job_name,
            j.job_path,
            j.schedule,
            j.is_enabled,
            j.created_at,
            COUNT(DISTINCT e.id) as total_executions,
            SUM(CASE WHEN e.status = 'success' THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN e.status = 'failed' THEN 1 ELSE 0 END) as failed,
            MAX(e.started_at) as last_execution,
            AVG(e.duration_seconds) as avg_duration,
            MAX(e.memory_peak_mb) as peak_memory
        FROM smart_cron_integrated_jobs j
        LEFT JOIN smart_cron_executions e ON j.job_name = e.task_name
        GROUP BY j.id, j.job_name
        ORDER BY j.is_enabled DESC, j.job_name ASC
    ");
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Cron V2 - Dashboard</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/bootstrap.min.css">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #3498db;
            --success: #2ecc71;
            --danger: #e74c3c;
            --warning: #f39c12;
            --dark: #2c3e50;
            --light: #ecf0f1;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #ecf0f1;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 20px;
        }

        .dashboard-header {
            background: rgba(52, 73, 94, 0.8);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            border-left: 5px solid var(--primary);
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }

        .dashboard-header h1 {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 10px;
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.2);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            background: rgba(255,255,255,0.15);
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: 700;
            color: var(--primary);
            margin: 10px 0;
        }

        .stat-label {
            font-size: 0.9em;
            color: #bdc3c7;
            text-transform: uppercase;
        }

        .jobs-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .job-card {
            background: rgba(44, 62, 80, 0.6);
            border: 1px solid rgba(52, 152, 219, 0.3);
            border-radius: 10px;
            padding: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
            backdrop-filter: blur(5px);
        }

        .job-card:hover {
            background: rgba(44, 62, 80, 0.8);
            border-color: var(--primary);
            box-shadow: 0 8px 24px rgba(52, 152, 219, 0.2);
            transform: translateY(-4px);
        }

        .job-card.disabled {
            opacity: 0.6;
            background: rgba(44, 62, 80, 0.3);
        }

        .job-status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .job-status-badge.enabled {
            background: rgba(46, 204, 113, 0.3);
            color: var(--success);
        }

        .job-status-badge.disabled {
            background: rgba(230, 126, 34, 0.3);
            color: var(--warning);
        }

        .job-name {
            font-size: 1.2em;
            font-weight: 700;
            color: var(--primary);
            margin: 10px 0;
        }

        .job-meta {
            font-size: 0.85em;
            color: #95a5a6;
            margin: 8px 0;
        }

        .job-metrics {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .metric {
            font-size: 0.85em;
        }

        .metric-label {
            color: #95a5a6;
            font-weight: 500;
        }

        .metric-value {
            color: var(--primary);
            font-weight: 700;
            font-size: 1.1em;
        }

        /* Modal Styles */
        .modal-content {
            background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
            border: 1px solid rgba(52, 152, 219, 0.3);
            color: #ecf0f1;
        }

        .modal-header {
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(52, 152, 219, 0.1);
        }

        .modal-title {
            color: var(--primary);
            font-weight: 700;
        }

        .execution-history {
            max-height: 400px;
            overflow-y: auto;
        }

        .execution-item {
            padding: 12px;
            margin-bottom: 10px;
            background: rgba(255,255,255,0.05);
            border-left: 4px solid rgba(52, 152, 219, 0.5);
            border-radius: 6px;
            font-size: 0.9em;
        }

        .execution-item.success {
            border-left-color: var(--success);
        }

        .execution-item.failed {
            border-left-color: var(--danger);
        }

        .execution-time {
            color: #95a5a6;
            font-size: 0.85em;
        }

        .execution-metrics-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 15px;
            padding: 15px;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
        }

        .metric-box {
            padding: 10px;
            background: rgba(52, 152, 219, 0.1);
            border-radius: 6px;
            border: 1px solid rgba(52, 152, 219, 0.3);
        }

        .metric-box-label {
            color: #95a5a6;
            font-size: 0.8em;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .metric-box-value {
            color: var(--primary);
            font-size: 1.3em;
            font-weight: 700;
        }

        .success-rate {
            color: var(--success);
        }

        .table-jobs {
            background: rgba(44, 62, 80, 0.4);
            border: 1px solid rgba(52, 152, 219, 0.2);
        }

        .table-jobs thead {
            background: rgba(52, 152, 219, 0.2);
        }

        .table-jobs tbody tr {
            border-bottom: 1px solid rgba(255,255,255,0.1);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .table-jobs tbody tr:hover {
            background: rgba(52, 152, 219, 0.15);
        }

        .badge-success {
            background: rgba(46, 204, 113, 0.3);
            color: var(--success);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
        }

        .badge-failed {
            background: rgba(231, 76, 60, 0.3);
            color: var(--danger);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
        }

        @media (max-width: 768px) {
            .dashboard-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .jobs-container {
                grid-template-columns: 1fr;
            }

            .execution-metrics-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Scrollbar Styling */
        .execution-history::-webkit-scrollbar {
            width: 8px;
        }

        .execution-history::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
            border-radius: 4px;
        }

        .execution-history::-webkit-scrollbar-thumb {
            background: rgba(52, 152, 219, 0.5);
            border-radius: 4px;
        }

        .execution-history::-webkit-scrollbar-thumb:hover {
            background: rgba(52, 152, 219, 0.8);
        }
    </style>
</head>
<body>

    <div class="container-fluid">

        <!-- Header -->
        <div class="dashboard-header">
            <h1><i class="fas fa-robot"></i> Smart Cron V2 Dashboard</h1>
            <p>Real-time job monitoring, execution history & performance metrics</p>

            <?php if (!isset($_GET['job_id'])): ?>
            <!-- Overall Stats -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <i class="fas fa-briefcase" style="font-size: 2em; color: var(--primary);"></i>
                    <div class="stat-label">Total Jobs</div>
                    <div class="stat-number"><?php echo count($jobs); ?></div>
                </div>

                <div class="stat-card">
                    <i class="fas fa-check-circle" style="font-size: 2em; color: var(--success);"></i>
                    <div class="stat-label">Enabled</div>
                    <div class="stat-number" style="color: var(--success);">
                        <?php echo count(array_filter($jobs, fn($j) => $j['is_enabled'])); ?>
                    </div>
                </div>

                <div class="stat-card">
                    <i class="fas fa-hourglass-end" style="font-size: 2em; color: var(--warning);"></i>
                    <div class="stat-label">Executions (24h)</div>
                    <div class="stat-number">
                        <?php
                        $stmt = $pdo->query("SELECT COUNT(*) FROM smart_cron_executions WHERE started_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                        echo $stmt->fetchColumn();
                        ?>
                    </div>
                </div>

                <div class="stat-card">
                    <i class="fas fa-heartbeat" style="font-size: 2em; color: var(--success);"></i>
                    <div class="stat-label">Success Rate</div>
                    <div class="stat-number success-rate">
                        <?php
                        $stmt = $pdo->query("
                            SELECT
                                COUNT(*) as total,
                                SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) as successful
                            FROM smart_cron_executions
                            WHERE started_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                        ");
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        echo $result['total'] > 0 ? round(($result['successful'] / $result['total']) * 100, 1) : 0;
                        ?>%
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Job Details Modal View (if job_id is set) -->
        <?php if (isset($_GET['job_id'])): ?>

        <div style="margin-bottom: 20px;">
            <a href="dashboard.php" class="btn btn-outline-light"><i class="fas fa-arrow-left"></i> Back to Jobs</a>
        </div>

        <div class="card" style="background: rgba(44, 62, 80, 0.6); border: 1px solid rgba(52, 152, 219, 0.3); border-radius: 10px;">
            <div class="card-header" style="background: rgba(52, 152, 219, 0.2); border-bottom: 1px solid rgba(52, 152, 219, 0.3); padding: 20px;">
                <h3 class="card-title" style="color: var(--primary); margin: 0;">
                    <i class="fas fa-tasks"></i> <?php echo htmlspecialchars($job['job_name']); ?>
                </h3>
            </div>

            <div class="card-body" style="padding: 20px;">

                <!-- Job Info -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <p><strong>Path:</strong> <code><?php echo htmlspecialchars($job['job_path']); ?></code></p>
                        <p><strong>Schedule:</strong> <code><?php echo htmlspecialchars($job['schedule'] ?? 'N/A'); ?></code></p>
                        <p><strong>Status:</strong>
                            <span class="badge <?php echo $job['is_enabled'] ? 'badge-success' : 'badge-failed'; ?>">
                                <?php echo $job['is_enabled'] ? 'Enabled' : 'Disabled'; ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Created:</strong> <?php echo date('M d, Y H:i:s', strtotime($job['created_at'])); ?></p>
                    </div>
                </div>

                <!-- Metrics -->
                <h5 style="color: var(--primary); margin-top: 30px; margin-bottom: 15px;">📊 Execution Metrics (Last 50 Executions)</h5>

                <div class="execution-metrics-grid">
                    <div class="metric-box">
                        <div class="metric-box-label">Total Executions</div>
                        <div class="metric-box-value"><?php echo $total_executions; ?></div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-box-label">Success Rate</div>
                        <div class="metric-box-value success-rate"><?php echo $success_rate; ?>%</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-box-label">Avg Duration</div>
                        <div class="metric-box-value"><?php echo $avg_duration; ?>s</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-box-label">Peak Memory</div>
                        <div class="metric-box-value"><?php echo $max_memory; ?> MB</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-box-label">Successful</div>
                        <div class="metric-box-value" style="color: var(--success);"><?php echo $successful; ?></div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-box-label">Failed</div>
                        <div class="metric-box-value" style="color: <?php echo $failed > 0 ? 'var(--danger)' : '#95a5a6'; ?>;">
                            <?php echo $failed; ?>
                        </div>
                    </div>
                </div>

                <!-- Execution History -->
                <h5 style="color: var(--primary); margin-top: 30px; margin-bottom: 15px;">📜 Execution History</h5>

                <div class="execution-history">
                    <?php if (empty($executions)): ?>
                    <p style="text-align: center; color: #95a5a6; padding: 20px;">No execution records found</p>
                    <?php else: ?>
                        <?php foreach ($executions as $exec): ?>
                        <div class="execution-item <?php echo $exec['status']; ?>">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <span>
                                    <strong><?php echo htmlspecialchars($exec['status']); ?></strong>
                                    <span class="execution-time"> • UUID: <?php echo substr($exec['execution_uuid'], 0, 8); ?>...</span>
                                </span>
                                <span class="execution-time"><?php echo $exec['started_at']; ?></span>
                            </div>

                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 8px; font-size: 0.85em;">
                                <div>
                                    <span style="color: #95a5a6;">Duration:</span>
                                    <strong style="color: var(--primary);"><?php echo $exec['duration_seconds']; ?>s</strong>
                                </div>
                                <div>
                                    <span style="color: #95a5a6;">Memory:</span>
                                    <strong style="color: var(--primary);"><?php echo $exec['memory_peak_mb']; ?> MB</strong>
                                </div>
                                <div>
                                    <span style="color: #95a5a6;">Exit Code:</span>
                                    <strong style="color: <?php echo $exec['exit_code'] == 0 ? 'var(--success)' : 'var(--danger)'; ?>;">
                                        <?php echo $exec['exit_code']; ?>
                                    </strong>
                                </div>
                                <div>
                                    <span style="color: #95a5a6;">Completed:</span>
                                    <strong style="color: var(--primary);"><?php echo $exec['completed_at'] ? 'Yes' : 'No'; ?></strong>
                                </div>
                            </div>

                            <?php if ($exec['error_message']): ?>
                            <div style="margin-top: 8px; padding: 8px; background: rgba(231, 76, 60, 0.1); border-radius: 4px; border-left: 2px solid var(--danger);">
                                <span style="color: #95a5a6; font-size: 0.8em;">Error:</span>
                                <p style="margin: 0; color: var(--danger); font-size: 0.85em;"><?php echo htmlspecialchars($exec['error_message']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($exec['output_snippet']): ?>
                            <div style="margin-top: 8px; padding: 8px; background: rgba(52, 152, 219, 0.1); border-radius: 4px; border-left: 2px solid var(--primary);">
                                <span style="color: #95a5a6; font-size: 0.8em;">Output:</span>
                                <pre style="margin: 0; font-size: 0.8em; color: #bdc3c7; white-space: pre-wrap; word-break: break-word;">
<?php echo htmlspecialchars($exec['output_snippet']); ?>
                                </pre>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <?php else: ?>

        <!-- Jobs Grid -->
        <h2 style="color: var(--primary); margin-bottom: 20px; margin-top: 30px;">
            <i class="fas fa-tasks"></i> Active Jobs (<?php echo count($jobs); ?>)
        </h2>

        <table class="table table-hover table-jobs">
            <thead>
                <tr>
                    <th>Job Name</th>
                    <th>Schedule</th>
                    <th>Status</th>
                    <th>Executions</th>
                    <th>Success Rate</th>
                    <th>Avg Duration</th>
                    <th>Peak Memory</th>
                    <th>Last Run</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jobs as $job): ?>
                <tr onclick="window.location.href='?job_id=<?php echo $job['id']; ?>'">
                    <td>
                        <strong><?php echo htmlspecialchars($job['job_name']); ?></strong>
                    </td>
                    <td>
                        <code style="background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 4px;">
                            <?php echo htmlspecialchars($job['schedule'] ?? 'N/A'); ?>
                        </code>
                    </td>
                    <td>
                        <span class="badge <?php echo $job['is_enabled'] ? 'badge-success' : 'badge-failed'; ?>">
                            <?php echo $job['is_enabled'] ? '✓ Enabled' : '✗ Disabled'; ?>
                        </span>
                    </td>
                    <td><?php echo $job['total_executions'] ?? 0; ?></td>
                    <td>
                        <strong style="color: <?php echo ($job['total_executions'] ?? 0) > 0 && $job['successful'] ? 'var(--success)' : '#95a5a6'; ?>;">
                            <?php echo ($job['total_executions'] ?? 0) > 0 ? round(($job['successful'] / $job['total_executions']) * 100, 1) : 0; ?>%
                        </strong>
                    </td>
                    <td><?php echo $job['avg_duration'] ? round($job['avg_duration'], 3) . 's' : 'N/A'; ?></td>
                    <td><?php echo $job['peak_memory'] ? $job['peak_memory'] . ' MB' : 'N/A'; ?></td>
                    <td><?php echo $job['last_execution'] ? date('M d H:i:s', strtotime($job['last_execution'])) : 'Never'; ?></td>
                    <td onclick="event.stopPropagation();">
                        <a href="?job_id=<?php echo $job['id']; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php endif; ?>

    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/js/bootstrap.bundle.min.js"></script>

    <script>
        // Auto-refresh every 30 seconds
        <?php if (isset($_GET['job_id'])): ?>
        setTimeout(function() {
            location.reload();
        }, 30000);
        <?php endif; ?>
    </script>

</body>
</html>
