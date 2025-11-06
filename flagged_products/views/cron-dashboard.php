<?php
/**
 * Flagged Products - Cron Dashboard
 *
 * Real-time monitoring of cron job performance with Smart Cron V2 integration
 *
 * @package CIS\Modules\FlaggedProducts
 */

declare(strict_types=1);

// Data is passed from controller - no SQL queries needed in view
// Available variables: $metrics, $recentExecutions, $performanceData, $healthData, $totalRuns, $totalSuccess, $healthScore

$pageTitle = 'Cron Job Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Flagged Products</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --danger-gradient: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dashboard-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 2rem;
            margin: 2rem auto;
            max-width: 1600px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: none;
            overflow: hidden;
            position: relative;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .stat-card.success::before {
            background: var(--success-gradient);
        }

        .stat-card.danger::before {
            background: var(--danger-gradient);
        }

        .stat-card.warning::before {
            background: var(--warning-gradient);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-value-success {
            background: var(--success-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-value-danger {
            background: var(--danger-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-value-warning {
            background: var(--warning-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .health-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .health-excellent {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .health-good {
            background: linear-gradient(135deg, #56CCF2 0%, #2F80ED 100%);
            color: white;
        }

        .health-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .health-critical {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
        }

        .job-row {
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
        }

        .job-row:hover {
            background-color: #f8f9fa;
            border-left-color: #667eea;
        }

        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-success {
            background: #d4edda;
            color: #155724;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }

        .chart-container {
            position: relative;
            height: 300px;
            padding: 1rem;
        }

        .pulse-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #38ef7d;
            animation: pulse 2s infinite;
            margin-right: 0.5rem;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.5;
                transform: scale(1.2);
            }
        }

        .section-header {
            border-bottom: 3px solid;
            border-image: linear-gradient(90deg, #667eea 0%, #764ba2 100%) 1;
            padding-bottom: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .refresh-btn {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .refresh-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .last-updated {
            font-size: 0.875rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-1">
                    <i class="fas fa-chart-line text-primary me-2"></i>
                    Cron Job Dashboard
                </h1>
                <p class="text-muted mb-0">
                    <span class="pulse-dot"></span>
                    Real-time monitoring of flagged products automation
                </p>
            </div>
            <div class="text-end">
                <button class="refresh-btn" onclick="location.reload()">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </button>
                <div class="last-updated mt-2">
                    <i class="fas fa-clock me-1"></i>
                    Last updated: <?php echo date('g:i A'); ?>
                </div>
            </div>
        </div>

        <!-- System Health Overview -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label mb-2">
                        <i class="fas fa-heartbeat me-1"></i>System Health
                    </div>
                    <div class="stat-value"><?php echo $healthScore; ?>%</div>
                    <div class="mt-3">
                        <?php
                        if ($healthScore >= 95) {
                            echo '<span class="health-badge health-excellent"><i class="fas fa-check-circle me-1"></i>Excellent</span>';
                        } elseif ($healthScore >= 85) {
                            echo '<span class="health-badge health-good"><i class="fas fa-thumbs-up me-1"></i>Good</span>';
                        } elseif ($healthScore >= 70) {
                            echo '<span class="health-badge health-warning"><i class="fas fa-exclamation-triangle me-1"></i>Warning</span>';
                        } else {
                            echo '<span class="health-badge health-critical"><i class="fas fa-times-circle me-1"></i>Critical</span>';
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="stat-card success">
                    <div class="stat-label mb-2">
                        <i class="fas fa-check-circle me-1"></i>Total Runs (7d)
                    </div>
                    <div class="stat-value stat-value-success">
                        <?php echo number_format($totalRuns); ?>
                    </div>
                    <div class="text-muted small mt-2">
                        <i class="fas fa-arrow-up text-success me-1"></i>
                        <?php echo number_format($totalSuccess); ?> successful
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="stat-card <?php echo array_sum(array_column($metrics, 'failed_runs')) > 0 ? 'danger' : ''; ?>">
                    <div class="stat-label mb-2">
                        <i class="fas fa-exclamation-triangle me-1"></i>Failed Runs
                    </div>
                    <div class="stat-value stat-value-danger">
                        <?php echo number_format(array_sum(array_column($metrics, 'failed_runs'))); ?>
                    </div>
                    <div class="text-muted small mt-2">
                        <?php
                        $failRate = $totalRuns > 0 ? round((array_sum(array_column($metrics, 'failed_runs')) / $totalRuns) * 100, 1) : 0;
                        echo "{$failRate}% failure rate";
                        ?>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="stat-card warning">
                    <div class="stat-label mb-2">
                        <i class="fas fa-tasks me-1"></i>Active Jobs
                    </div>
                    <div class="stat-value stat-value-warning">
                        <?php echo count($metrics); ?>
                    </div>
                    <div class="text-muted small mt-2">
                        <i class="fas fa-cog me-1"></i>All configured
                    </div>
                </div>
            </div>
        </div>

        <!-- Job Performance Summary -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="stat-card">
                    <h5 class="section-header">
                        <i class="fas fa-chart-bar me-2"></i>Job Performance (Last 7 Days)
                    </h5>
                    <div class="chart-container">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="stat-card">
                    <h5 class="section-header">
                        <i class="fas fa-tachometer-alt me-2"></i>Execution Time
                    </h5>
                    <div class="chart-container">
                        <canvas id="executionTimeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Job Details Table -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="stat-card">
                    <h5 class="section-header">
                        <i class="fas fa-cogs me-2"></i>Job Statistics
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th><i class="fas fa-tag me-1"></i>Job Name</th>
                                    <th class="text-center"><i class="fas fa-play-circle me-1"></i>Total Runs</th>
                                    <th class="text-center"><i class="fas fa-check me-1"></i>Success</th>
                                    <th class="text-center"><i class="fas fa-times me-1"></i>Failed</th>
                                    <th class="text-center"><i class="fas fa-clock me-1"></i>Avg Time</th>
                                    <th class="text-center"><i class="fas fa-memory me-1"></i>Avg Memory</th>
                                    <th><i class="fas fa-calendar-check me-1"></i>Last Run</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($metrics as $metric): ?>
                                    <tr class="job-row">
                                        <td>
                                            <strong><?php echo htmlspecialchars(str_replace('flagged_products_', '', $metric['task_name'])); ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary rounded-pill">
                                                <?php echo number_format($metric['total_runs']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success rounded-pill">
                                                <?php echo number_format($metric['successful_runs']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($metric['failed_runs'] > 0): ?>
                                                <span class="badge bg-danger rounded-pill">
                                                    <?php echo number_format($metric['failed_runs']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <code><?php echo htmlspecialchars($metric['avg_execution_time']); ?>s</code>
                                        </td>
                                        <td class="text-center">
                                            <code><?php echo htmlspecialchars($metric['avg_memory_mb']); ?> MB</code>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php
                                                $lastRun = new DateTime($metric['last_run']);
                                                $now = new DateTime();
                                                $diff = $now->diff($lastRun);

                                                if ($diff->days > 0) {
                                                    echo $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
                                                } elseif ($diff->h > 0) {
                                                    echo $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
                                                } elseif ($diff->i > 0) {
                                                    echo $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
                                                } else {
                                                    echo 'Just now';
                                                }
                                                ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Executions -->
        <div class="row">
            <div class="col-12">
                <div class="stat-card">
                    <h5 class="section-header">
                        <i class="fas fa-history me-2"></i>Recent Executions (Last 20)
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Job</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Execution Time</th>
                                    <th class="text-center">Memory</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentExecutions as $execution): ?>
                                    <tr>
                                        <td>
                                            <small><?php echo htmlspecialchars(str_replace('flagged_products_', '', $execution['task_name'])); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($execution['success']): ?>
                                                <span class="status-badge status-success">
                                                    <i class="fas fa-check"></i> Success
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge status-failed">
                                                    <i class="fas fa-times"></i> Failed
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <code class="small"><?php echo htmlspecialchars($execution['execution_time']); ?>s</code>
                                        </td>
                                        <td class="text-center">
                                            <code class="small"><?php echo htmlspecialchars($execution['memory_mb']); ?> MB</code>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars(date('M j, g:i A', strtotime($execution['created_at']))); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Links -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="/modules/flagged_products/" class="btn btn-outline-primary me-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Module
                </a>
                <a href="/assets/services/cron/dashboard.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-external-link-alt me-2"></i>Smart Cron Dashboard
                </a>
                <a href="#" onclick="location.reload()" class="btn btn-outline-secondary">
                    <i class="fas fa-sync-alt me-2"></i>Refresh Data
                </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Charts -->
    <script>
        // Performance Chart
        const performanceCtx = document.getElementById('performanceChart').getContext('2d');
        new Chart(performanceCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map(function($m) {
                    return str_replace('flagged_products_', '', $m['task_name']);
                }, $metrics)); ?>,
                datasets: [{
                    label: 'Successful',
                    data: <?php echo json_encode(array_column($metrics, 'successful_runs')); ?>,
                    backgroundColor: 'rgba(17, 153, 142, 0.8)',
                    borderColor: 'rgb(17, 153, 142)',
                    borderWidth: 2
                }, {
                    label: 'Failed',
                    data: <?php echo json_encode(array_column($metrics, 'failed_runs')); ?>,
                    backgroundColor: 'rgba(235, 51, 73, 0.8)',
                    borderColor: 'rgb(235, 51, 73)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Execution Time Chart
        const timeCtx = document.getElementById('executionTimeChart').getContext('2d');
        new Chart(timeCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_map(function($m) {
                    return str_replace('flagged_products_', '', $m['task_name']);
                }, $metrics)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($metrics, 'avg_execution_time')); ?>,
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(118, 75, 162, 0.8)',
                        'rgba(17, 153, 142, 0.8)',
                        'rgba(56, 239, 125, 0.8)',
                        'rgba(240, 147, 251, 0.8)'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 10,
                            font: {
                                size: 10
                            }
                        }
                    }
                }
            }
        });

        // Auto-refresh every 5 minutes
        setTimeout(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
