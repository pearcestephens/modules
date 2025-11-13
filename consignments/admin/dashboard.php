<?php
/**
 * Admin Sync Dashboard
 *
 * Real-time monitoring for queue, webhooks, and sync status.
 * Displays health metrics, errors, and provides retry functionality.
 *
 * @package Consignments\Admin
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

$pageTitle = 'Sync Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Consignments Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .metric-card {
            transition: transform 0.2s;
        }
        .metric-card:hover {
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
        }
        .pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        #errorLog {
            max-height: 400px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 0.85rem;
        }
        .log-entry {
            border-bottom: 1px solid #dee2e6;
            padding: 0.5rem;
        }
        .log-entry:last-child {
            border-bottom: none;
        }
        .log-error { color: #dc3545; }
        .log-warning { color: #ffc107; }
        .log-info { color: #17a2b8; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <span class="navbar-brand mb-0 h1">
            <i class="fas fa-sync-alt"></i> Consignments Sync Dashboard
        </span>
        <span class="navbar-text">
            <i class="fas fa-clock"></i> <span id="lastUpdate">--:--:--</span>
        </span>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Overview Metrics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card metric-card border-primary">
                    <div class="card-body">
                        <h5 class="card-title text-primary">
                            <i class="fas fa-tasks"></i> Queue Health
                        </h5>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="h3 mb-0" id="queuePending">-</div>
                                <small class="text-muted">Pending</small>
                            </div>
                            <div>
                                <div class="h3 mb-0 text-warning" id="queueProcessing">-</div>
                                <small class="text-muted">Processing</small>
                            </div>
                            <div>
                                <div class="h3 mb-0 text-danger" id="queueFailed">-</div>
                                <small class="text-muted">Failed</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card metric-card border-success">
                    <div class="card-body">
                        <h5 class="card-title text-success">
                            <i class="fas fa-webhook"></i> Webhooks (24h)
                        </h5>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="h3 mb-0" id="webhookCount">-</div>
                                <small class="text-muted">Total</small>
                            </div>
                            <div>
                                <div class="h3 mb-0" id="webhookSuccessRate">-%</div>
                                <small class="text-muted">Success</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card metric-card border-danger">
                    <div class="card-body">
                        <h5 class="card-title text-danger">
                            <i class="fas fa-exclamation-triangle"></i> Dead Letter Queue
                        </h5>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="h3 mb-0" id="dlqCount">-</div>
                                <small class="text-muted">Jobs</small>
                            </div>
                            <div>
                                <small class="text-muted" id="dlqOldest">Oldest: -</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card metric-card border-info">
                    <div class="card-body">
                        <h5 class="card-title text-info">
                            <i class="fas fa-database"></i> Sync Cursor
                        </h5>
                        <div>
                            <div class="h5 mb-1">ID: <span id="cursorId">-</span></div>
                            <small class="text-muted">Updated: <span id="cursorUpdated">-</span></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-chart-line"></i> Queue Activity (Last Hour)
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="queueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-chart-pie"></i> Webhook Events by Type
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="webhookChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- DLQ Viewer -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-skull-crossbones"></i> Dead Letter Queue</span>
                        <button class="btn btn-sm btn-light" onclick="refreshDLQ()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Job ID</th>
                                        <th>Type</th>
                                        <th>Failed At</th>
                                        <th>Attempts</th>
                                        <th>Error</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="dlqTable">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">
                                            <i class="fas fa-spinner fa-spin"></i> Loading...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Log -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-bug"></i> Recent Errors (Last 100)</span>
                        <button class="btn btn-sm btn-dark" onclick="refreshErrors()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div id="errorLog">
                            <div class="log-entry text-center text-muted">
                                <i class="fas fa-spinner fa-spin"></i> Loading errors...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
        let queueChart, webhookChart;

        // Initialize charts
        function initCharts() {
            const queueCtx = document.getElementById('queueChart').getContext('2d');
            queueChart = new Chart(queueCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Pending',
                        data: [],
                        borderColor: 'rgb(54, 162, 235)',
                        tension: 0.1
                    }, {
                        label: 'Processing',
                        data: [],
                        borderColor: 'rgb(255, 205, 86)',
                        tension: 0.1
                    }, {
                        label: 'Failed',
                        data: [],
                        borderColor: 'rgb(255, 99, 132)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            const webhookCtx = document.getElementById('webhookChart').getContext('2d');
            webhookChart = new Chart(webhookCtx, {
                type: 'doughnut',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: [
                            'rgb(54, 162, 235)',
                            'rgb(255, 205, 86)',
                            'rgb(75, 192, 192)',
                            'rgb(153, 102, 255)',
                            'rgb(255, 159, 64)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        // Fetch sync status
        function fetchSyncStatus() {
            $.ajax({
                url: 'api/sync-status.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    updateMetrics(data);
                    updateCharts(data);
                    updateLastUpdate();
                },
                error: function(xhr, status, error) {
                    console.error('Failed to fetch sync status:', error);
                }
            });
        }

        // Update metric cards
        function updateMetrics(data) {
            $('#queuePending').text(data.queue.pending || 0);
            $('#queueProcessing').text(data.queue.processing || 0);
            $('#queueFailed').text(data.queue.failed || 0);

            $('#webhookCount').text(data.webhooks.last_hour || 0);
            $('#webhookSuccessRate').text(Math.round((data.webhooks.success_rate || 0) * 100) + '%');

            $('#dlqCount').text(data.dlq.count || 0);
            $('#dlqOldest').text('Oldest: ' + (data.dlq.oldest || 'N/A'));

            $('#cursorId').text(data.cursor.last_processed_id || 'N/A');
            $('#cursorUpdated').text(data.cursor.updated_at || 'N/A');
        }

        // Update charts
        function updateCharts(data) {
            // Queue chart (mock data for now - would come from API)
            const now = new Date();
            queueChart.data.labels.push(now.toLocaleTimeString());
            queueChart.data.datasets[0].data.push(data.queue.pending || 0);
            queueChart.data.datasets[1].data.push(data.queue.processing || 0);
            queueChart.data.datasets[2].data.push(data.queue.failed || 0);

            // Keep last 20 data points
            if (queueChart.data.labels.length > 20) {
                queueChart.data.labels.shift();
                queueChart.data.datasets.forEach(dataset => dataset.data.shift());
            }
            queueChart.update();

            // Webhook chart
            if (data.webhooks.by_type) {
                webhookChart.data.labels = Object.keys(data.webhooks.by_type);
                webhookChart.data.datasets[0].data = Object.values(data.webhooks.by_type);
                webhookChart.update();
            }
        }

        // Refresh DLQ table
        function refreshDLQ() {
            $.ajax({
                url: 'api/dlq-list.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    const tbody = $('#dlqTable');
                    tbody.empty();

                    if (data.jobs && data.jobs.length > 0) {
                        data.jobs.forEach(job => {
                            tbody.append(`
                                <tr>
                                    <td>${job.id}</td>
                                    <td><span class="badge badge-secondary">${job.job_type}</span></td>
                                    <td>${job.failed_at}</td>
                                    <td><span class="badge badge-danger">${job.attempts}</span></td>
                                    <td><small>${job.error_message}</small></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="retryJob(${job.id})">
                                            <i class="fas fa-redo"></i> Retry
                                        </button>
                                    </td>
                                </tr>
                            `);
                        });
                    } else {
                        tbody.html('<tr><td colspan="6" class="text-center text-success">No failed jobs</td></tr>');
                    }
                }
            });
        }

        // Retry job from DLQ
        function retryJob(jobId) {
            if (!confirm('Retry this job?')) return;

            $.ajax({
                url: 'api/retry-job.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ dlq_id: jobId }),
                success: function(response) {
                    alert('Job queued for retry');
                    refreshDLQ();
                    fetchSyncStatus();
                },
                error: function(xhr) {
                    alert('Failed to retry job: ' + xhr.responseText);
                }
            });
        }

        // Refresh error log
        function refreshErrors() {
            $.ajax({
                url: 'api/error-log.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    const logDiv = $('#errorLog');
                    logDiv.empty();

                    if (data.errors && data.errors.length > 0) {
                        data.errors.forEach(error => {
                            const levelClass = error.level === 'error' ? 'log-error' :
                                             error.level === 'warning' ? 'log-warning' : 'log-info';
                            logDiv.append(`
                                <div class="log-entry ${levelClass}">
                                    <strong>[${error.timestamp}]</strong> ${error.message}
                                </div>
                            `);
                        });
                    } else {
                        logDiv.html('<div class="log-entry text-center text-success">No recent errors</div>');
                    }
                }
            });
        }

        // Update last update time
        function updateLastUpdate() {
            const now = new Date();
            $('#lastUpdate').text(now.toLocaleTimeString());
        }

        // Initialize on page load
        $(document).ready(function() {
            initCharts();
            fetchSyncStatus();
            refreshDLQ();
            refreshErrors();

            // Auto-refresh every 10 seconds
            setInterval(fetchSyncStatus, 10000);
            setInterval(refreshDLQ, 30000);
            setInterval(refreshErrors, 30000);
        });
    </script>
</body>
</html>
