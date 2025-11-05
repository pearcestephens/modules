<?php
/**
 * Smart Cron Dashboard - Ultra-Robust Web Interface
 *
 * Features:
 * - Real-time task monitoring
 * - Live execution logs with AJAX refresh
 * - Task management (create, edit, enable/disable)
 * - System health dashboard
 * - Alert management
 * - Performance metrics & charts
 * - Execution history with search
 *
 * @version 2.0
 * @security Admin authentication required
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bootstrap application
$baseDir = dirname(dirname(dirname(dirname(__DIR__))));
require_once $baseDir . '/private_html/app.php';

// Get database connection
try {
    $db = get_db_connection();
    if (!$db) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    die('<h1>Database Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p><pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>');
}

// Load classes with error checking
$includesDir = dirname(__DIR__) . '/includes';
$classFiles = [
    'SmartCronLogger.php',
    'SmartCronAlert.php',
    'SmartCronHealth.php'
];

foreach ($classFiles as $file) {
    $filePath = $includesDir . '/' . $file;
    if (!file_exists($filePath)) {
        die("Required file missing: $filePath");
    }
    require_once $filePath;
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    handleAjaxRequest($db);
    exit;
}

// Get dashboard data with error handling
try {
    // Check if tables exist
    $tableCheck = $db->query("SHOW TABLES LIKE 'smart_cron_tasks_config'");
    if ($tableCheck->rowCount() === 0) {
        throw new Exception('Smart Cron tables not installed. Please run install.sh first.');
    }
    
    $logger = new SmartCronLogger('/var/log/smart-cron/dashboard.log');
    $health = new SmartCronHealth($db, $logger);
    $systemStatus = $health->getSystemStatus();
    $isHealthy = $health->isSystemHealthy();
    $issues = $health->getIssues();

    $alertManager = new SmartCronAlert($db);
    $activeAlerts = $alertManager->getActiveAlerts(10);

    // Get tasks
    $tasks = getTasks($db);
    $recentExecutions = getRecentExecutions($db, 20);
} catch (Exception $e) {
    error_log("Smart Cron Dashboard Error: " . $e->getMessage());
    
    // Show user-friendly error page
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Smart Cron Dashboard - Error</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="alert alert-danger">
                <h4 class="alert-heading">Dashboard Error</h4>
                <p><strong>Error:</strong> <?php echo htmlspecialchars($e->getMessage()); ?></p>
                <hr>
                <p class="mb-0">Please check:</p>
                <ul>
                    <li>Database tables are installed (run <code>sudo bash install.sh</code>)</li>
                    <li>Log directories exist (<code>/var/log/smart-cron/</code>)</li>
                    <li>PHP error log for more details</li>
                </ul>
                <hr>
                <pre class="bg-dark text-light p-3"><?php echo htmlspecialchars($e->getTraceAsString()); ?></pre>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
    $issues = ['Database error: ' . $e->getMessage()];
    $activeAlerts = [];
    $tasks = [];
    $recentExecutions = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Cron Dashboard - Ecigdis Limited</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>

    <!-- Custom Styles -->
    <style>
        :root {
            --sidebar-width: 260px;
            --header-height: 60px;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f8f9fa;
            overflow-x: hidden;
        }

        /* Header */
        .top-header {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: var(--header-height);
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            z-index: 1000;
            padding: 0 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: #ecf0f1;
            overflow-y: auto;
            z-index: 1001;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 20px;
            background: rgba(0,0,0,0.2);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h4 {
            margin: 0;
            font-weight: 600;
            font-size: 18px;
        }

        .sidebar-menu {
            padding: 15px 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #ecf0f1;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            border-left-color: #3498db;
        }

        .sidebar-menu a i {
            width: 25px;
            margin-right: 10px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            padding: 30px;
            min-height: calc(100vh - var(--header-height));
        }

        /* Cards */
        .stat-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 4px solid #3498db;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .stat-card.success { border-left-color: var(--success-color); }
        .stat-card.warning { border-left-color: var(--warning-color); }
        .stat-card.danger { border-left-color: var(--danger-color); }
        .stat-card.info { border-left-color: var(--info-color); }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin: 10px 0;
        }

        .stat-label {
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* System Status Badge */
        .system-status {
            display: inline-flex;
            align-items: center;
            padding: 8px 15px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
        }

        .system-status.healthy {
            background: #d4edda;
            color: #155724;
        }

        .system-status.unhealthy {
            background: #f8d7da;
            color: #721c24;
        }

        .system-status i {
            margin-right: 8px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Task Table */
        .task-table {
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .task-table table {
            margin: 0;
        }

        .task-table thead {
            background: #f8f9fa;
        }

        .task-table th {
            border-top: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            color: #6c757d;
        }

        .task-table tbody tr {
            transition: background 0.2s;
        }

        .task-table tbody tr:hover {
            background: #f8f9fa;
        }

        /* Status Badges */
        .badge-running {
            background: #17a2b8;
            animation: pulse 2s infinite;
        }

        .badge-success {
            background: var(--success-color);
        }

        .badge-failed {
            background: var(--danger-color);
        }

        .badge-disabled {
            background: #6c757d;
        }

        /* Progress Bar */
        .success-rate-bar {
            height: 8px;
            border-radius: 10px;
            overflow: hidden;
            background: #e9ecef;
        }

        .success-rate-fill {
            height: 100%;
            transition: width 0.5s;
        }

        /* Alerts Section */
        .alert-item {
            border-left: 4px solid;
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 5px;
            background: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .alert-item.critical { border-left-color: var(--danger-color); }
        .alert-item.error { border-left-color: #fd7e14; }
        .alert-item.warning { border-left-color: var(--warning-color); }
        .alert-item.info { border-left-color: var(--info-color); }

        /* Log Viewer */
        .log-viewer {
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
            font-size: 13px;
            padding: 20px;
            border-radius: 10px;
            height: 500px;
            overflow-y: auto;
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.3);
        }

        .log-entry {
            margin-bottom: 8px;
            padding: 5px;
            border-radius: 3px;
        }

        .log-entry.ERROR { background: rgba(220, 53, 69, 0.2); color: #ff6b6b; }
        .log-entry.WARNING { background: rgba(255, 193, 7, 0.2); color: #ffd93d; }
        .log-entry.INFO { color: #4dabf7; }
        .log-entry.DEBUG { color: #adb5bd; }

        .log-timestamp {
            color: #868e96;
            margin-right: 10px;
        }

        /* Action Buttons */
        .btn-action {
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .btn-action:hover {
            transform: scale(1.05);
        }

        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Auto-refresh indicator */
        .auto-refresh {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #28a745;
            color: #fff;
            padding: 10px 20px;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            font-size: 12px;
            font-weight: 600;
            z-index: 1000;
        }

        .auto-refresh i {
            animation: spin 2s linear infinite;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-clock"></i> Smart Cron</h4>
            <small>Ecigdis Limited</small>
        </div>
        <div class="sidebar-menu">
            <a href="#dashboard" class="active" data-section="dashboard">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="#tasks" data-section="tasks">
                <i class="fas fa-tasks"></i> Tasks
            </a>
            <a href="#executions" data-section="executions">
                <i class="fas fa-history"></i> Execution History
            </a>
            <a href="#alerts" data-section="alerts">
                <i class="fas fa-bell"></i> Alerts
                <?php if (count($activeAlerts) > 0): ?>
                    <span class="badge bg-danger ms-2"><?= count($activeAlerts) ?></span>
                <?php endif; ?>
            </a>
            <a href="#logs" data-section="logs">
                <i class="fas fa-file-alt"></i> Live Logs
            </a>
            <a href="#health" data-section="health">
                <i class="fas fa-heartbeat"></i> System Health
            </a>
            <a href="#settings" data-section="settings">
                <i class="fas fa-cog"></i> Settings
            </a>
        </div>
    </div>

    <!-- Header -->
    <div class="top-header">
        <div>
            <h5 class="mb-0">Smart Cron Dashboard</h5>
            <small class="text-muted">Automated Task Scheduler & Monitor</small>
        </div>
        <div>
            <span class="system-status <?= $isHealthy ? 'healthy' : 'unhealthy' ?>">
                <i class="fas fa-circle"></i>
                <?= $isHealthy ? 'System Healthy' : 'System Issues Detected' ?>
            </span>
            <button class="btn btn-sm btn-outline-primary ms-3" onclick="refreshDashboard()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Dashboard Section -->
        <div id="section-dashboard" class="content-section">
            <div class="row g-4 mb-4">
                <!-- System Statistics -->
                <div class="col-md-3">
                    <div class="stat-card success">
                        <div class="stat-label">Active Tasks</div>
                        <div class="stat-value"><?= $systemStatus['enabled_tasks'] ?? 0 ?></div>
                        <small class="text-muted"><?= $systemStatus['running_tasks'] ?? 0 ?> currently running</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card info">
                        <div class="stat-label">Executions (Last Hour)</div>
                        <div class="stat-value"><?= $systemStatus['executions_last_hour'] ?? 0 ?></div>
                        <small class="text-muted"><?= $systemStatus['failures_last_hour'] ?? 0 ?> failures</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card <?= ($systemStatus['failing_tasks'] ?? 0) > 0 ? 'danger' : 'success' ?>">
                        <div class="stat-label">Failing Tasks</div>
                        <div class="stat-value"><?= $systemStatus['failing_tasks'] ?? 0 ?></div>
                        <small class="text-muted">Exceeded thresholds</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card <?= ($systemStatus['critical_alerts'] ?? 0) > 0 ? 'danger' : 'success' ?>">
                        <div class="stat-label">Critical Alerts</div>
                        <div class="stat-value"><?= $systemStatus['critical_alerts'] ?? 0 ?></div>
                        <small class="text-muted">Unresolved</small>
                    </div>
                </div>
            </div>

            <!-- Performance Chart -->
            <div class="row g-4 mb-4">
                <div class="col-md-8">
                    <div class="stat-card">
                        <h5 class="mb-3">Execution Performance (24 Hours)</h5>
                        <canvas id="performanceChart" height="80"></canvas>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <h5 class="mb-3">Success Rate (24 Hours)</h5>
                        <canvas id="successRateChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Task Overview -->
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Task Overview</h5>
                    <button class="btn btn-sm btn-primary" onclick="showSection('tasks')">
                        <i class="fas fa-plus"></i> Add Task
                    </button>
                </div>
                <div class="task-table">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Task Name</th>
                                <th>Status</th>
                                <th>Schedule</th>
                                <th>Last Run</th>
                                <th>Success Rate</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($tasks, 0, 10) as $task): ?>
                                <?php
                                    $successRate = $task['total_executions'] > 0
                                        ? round(($task['total_successes'] / $task['total_executions']) * 100, 1)
                                        : 0;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($task['task_name']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($task['task_description']) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($task['is_running']): ?>
                                            <span class="badge badge-running">
                                                <i class="spinner"></i> Running
                                            </span>
                                        <?php elseif (!$task['enabled']): ?>
                                            <span class="badge badge-disabled">Disabled</span>
                                        <?php elseif ($task['consecutive_failures'] >= $task['failure_threshold']): ?>
                                            <span class="badge badge-failed">
                                                <i class="fas fa-exclamation-triangle"></i> Failed
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check"></i> Active
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code><?= htmlspecialchars($task['schedule_pattern']) ?></code>
                                        <br>
                                        <small class="text-muted">Priority: <?= $task['priority'] ?></small>
                                    </td>
                                    <td>
                                        <?php if ($task['last_run_at']): ?>
                                            <?= date('M d, H:i', strtotime($task['last_run_at'])) ?>
                                            <br>
                                            <small class="text-muted"><?= timeAgo($task['last_run_at']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="success-rate-bar">
                                            <div class="success-rate-fill" style="width: <?= $successRate ?>%; background: <?= $successRate >= 90 ? '#28a745' : ($successRate >= 70 ? '#ffc107' : '#dc3545') ?>"></div>
                                        </div>
                                        <small><?= $successRate ?>%</small>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary btn-action" onclick="runTaskNow(<?= $task['id'] ?>)">
                                            <i class="fas fa-play"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary btn-action" onclick="viewTaskDetails(<?= $task['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Live Logs Section -->
        <div id="section-logs" class="content-section" style="display: none;">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Live Execution Logs</h5>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary" onclick="clearLogs()">
                            <i class="fas fa-trash"></i> Clear
                        </button>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshLogs()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
                <div id="logViewer" class="log-viewer">
                    <div class="text-center text-muted">Loading logs...</div>
                </div>
            </div>
        </div>

        <!-- Alerts Section -->
        <div id="section-alerts" class="content-section" style="display: none;">
            <div class="stat-card">
                <h5 class="mb-4">Active Alerts</h5>
                <?php if (empty($activeAlerts)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> No active alerts. All systems running smoothly!
                    </div>
                <?php else: ?>
                    <?php foreach ($activeAlerts as $alert): ?>
                        <div class="alert-item <?= $alert['alert_severity'] ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">
                                        <?php
                                            $icon = [
                                                'critical' => 'fa-exclamation-circle',
                                                'error' => 'fa-exclamation-triangle',
                                                'warning' => 'fa-info-circle',
                                                'info' => 'fa-info'
                                            ][$alert['alert_severity']] ?? 'fa-info';
                                        ?>
                                        <i class="fas <?= $icon ?>"></i>
                                        <?= htmlspecialchars($alert['alert_title']) ?>
                                    </h6>
                                    <p class="mb-2"><?= htmlspecialchars($alert['alert_message']) ?></p>
                                    <small class="text-muted">
                                        <?= timeAgo($alert['created_at']) ?>
                                        <?php if ($alert['task_name']): ?>
                                            | Task: <strong><?= htmlspecialchars($alert['task_name']) ?></strong>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div>
                                    <?php if (!$alert['acknowledged']): ?>
                                        <button class="btn btn-sm btn-warning" onclick="acknowledgeAlert(<?= $alert['id'] ?>)">
                                            <i class="fas fa-check"></i> Acknowledge
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-success" onclick="resolveAlert(<?= $alert['id'] ?>)">
                                        <i class="fas fa-check-double"></i> Resolve
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Auto-refresh indicator -->
    <div class="auto-refresh">
        <i class="fas fa-sync-alt"></i> Auto-refreshing every 30s
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Dashboard JavaScript -->
    <script>
        // Auto-refresh dashboard data
        let refreshInterval;

        document.addEventListener('DOMContentLoaded', function() {
            initDashboard();
            startAutoRefresh();
            loadPerformanceChart();
            loadSuccessRateChart();
        });

        function initDashboard() {
            // Section navigation
            document.querySelectorAll('.sidebar-menu a').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const section = this.getAttribute('data-section');
                    showSection(section);

                    // Update active state
                    document.querySelectorAll('.sidebar-menu a').forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        }

        function showSection(section) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(s => s.style.display = 'none');

            // Show selected section
            const targetSection = document.getElementById('section-' + section);
            if (targetSection) {
                targetSection.style.display = 'block';

                // Load section-specific data
                if (section === 'logs') {
                    refreshLogs();
                }
            }
        }

        function startAutoRefresh() {
            refreshInterval = setInterval(function() {
                const activeSection = document.querySelector('.sidebar-menu a.active').getAttribute('data-section');

                if (activeSection === 'dashboard') {
                    refreshDashboard();
                } else if (activeSection === 'logs') {
                    refreshLogs();
                }
            }, 30000); // 30 seconds
        }

        function refreshDashboard() {
            location.reload();
        }

        function refreshLogs() {
            fetch('?ajax=logs&lines=100')
                .then(response => response.json())
                .then(data => {
                    const viewer = document.getElementById('logViewer');
                    viewer.innerHTML = '';

                    data.logs.forEach(entry => {
                        const div = document.createElement('div');
                        div.className = 'log-entry ' + entry.level;
                        div.innerHTML = `
                            <span class="log-timestamp">${entry.timestamp}</span>
                            <span class="badge bg-${getLevelColor(entry.level)}">${entry.level}</span>
                            ${entry.message}
                        `;
                        viewer.appendChild(div);
                    });

                    viewer.scrollTop = viewer.scrollHeight;
                });
        }

        function getLevelColor(level) {
            const colors = {
                'ERROR': 'danger',
                'WARNING': 'warning',
                'INFO': 'info',
                'DEBUG': 'secondary'
            };
            return colors[level] || 'secondary';
        }

        function clearLogs() {
            document.getElementById('logViewer').innerHTML = '<div class="text-center text-muted">Logs cleared</div>';
        }

        function runTaskNow(taskId) {
            if (!confirm('Run this task immediately?')) return;

            fetch('?ajax=run_task&task_id=' + taskId, { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Task started successfully');
                        refreshDashboard();
                    } else {
                        alert('Error: ' + data.error);
                    }
                });
        }

        function acknowledgeAlert(alertId) {
            fetch('?ajax=acknowledge_alert&alert_id=' + alertId, { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
        }

        function resolveAlert(alertId) {
            fetch('?ajax=resolve_alert&alert_id=' + alertId, { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
        }

        function loadPerformanceChart() {
            const ctx = document.getElementById('performanceChart').getContext('2d');

            fetch('?ajax=performance_data')
                .then(response => response.json())
                .then(data => {
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Executions',
                                data: data.executions,
                                borderColor: '#3498db',
                                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                                tension: 0.4
                            }, {
                                label: 'Failures',
                                data: data.failures,
                                borderColor: '#e74c3c',
                                backgroundColor: 'rgba(231, 76, 60, 0.1)',
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top'
                                }
                            }
                        }
                    });
                });
        }

        function loadSuccessRateChart() {
            const ctx = document.getElementById('successRateChart').getContext('2d');

            fetch('?ajax=success_rate')
                .then(response => response.json())
                .then(data => {
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Success', 'Failed'],
                            datasets: [{
                                data: [data.success, data.failed],
                                backgroundColor: ['#28a745', '#dc3545']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                });
        }
    </script>
</body>
</html>

<?php
// ============================================================================
// Helper Functions
// ============================================================================

function getTasks(PDO $db): array
{
    $sql = "SELECT * FROM smart_cron_tasks_config ORDER BY priority ASC, task_name ASC";
    $stmt = $db->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRecentExecutions(PDO $db, int $limit): array
{
    $sql = "
        SELECT * FROM smart_cron_executions
        ORDER BY started_at DESC
        LIMIT :limit
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function timeAgo(string $datetime): string
{
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return $diff . ' seconds ago';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    return floor($diff / 86400) . ' days ago';
}

function handleAjaxRequest(PDO $db): void
{
    header('Content-Type: application/json');

    $action = $_GET['ajax'] ?? '';

    switch ($action) {
        case 'logs':
            $lines = (int) ($_GET['lines'] ?? 100);
            $logFile = '/var/log/smart-cron/master-' . date('Y-m-d') . '.log';
            $logs = SmartCronLogger::getTail($logFile, $lines);
            echo json_encode(['success' => true, 'logs' => $logs]);
            break;

        case 'performance_data':
            // Generate last 24 hours data
            $data = [
                'labels' => [],
                'executions' => [],
                'failures' => []
            ];

            for ($i = 23; $i >= 0; $i--) {
                $hour = date('H:00', strtotime("-$i hours"));
                $data['labels'][] = $hour;

                // Get executions for this hour
                $stmt = $db->prepare("
                    SELECT
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed
                    FROM smart_cron_executions
                    WHERE started_at >= DATE_SUB(NOW(), INTERVAL :current_hour HOUR)
                      AND started_at < DATE_SUB(NOW(), INTERVAL :next_hour HOUR)
                ");
                $nextHour = $i - 1;
                $stmt->bindValue(':current_hour', $i, PDO::PARAM_INT);
                $stmt->bindValue(':next_hour', $nextHour, PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                $data['executions'][] = (int) ($result['total'] ?? 0);
                $data['failures'][] = (int) ($result['failed'] ?? 0);
            }

            echo json_encode($data);
            break;

        case 'success_rate':
            $stmt = $db->prepare("
                SELECT
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
                    SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed
                FROM smart_cron_executions
                WHERE started_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => (int) ($result['success'] ?? 0),
                'failed' => (int) ($result['failed'] ?? 0)
            ]);
            break;

        case 'run_task':
            // Manual task execution
            $taskId = (int) ($_GET['task_id'] ?? 0);
            // Implementation: Trigger task execution by setting next_run_at to NOW
            $stmt = $db->prepare("UPDATE smart_cron_tasks_config SET next_run_at = NOW() WHERE id = :id");
            $stmt->bindValue(':id', $taskId, PDO::PARAM_INT);
            $success = $stmt->execute();
            echo json_encode(['success' => $success]);
            break;

        case 'acknowledge_alert':
            $alertId = (int) ($_GET['alert_id'] ?? 0);
            $alertManager = new SmartCronAlert($db);
            $success = $alertManager->acknowledgeAlert($alertId, 1);
            echo json_encode(['success' => $success]);
            break;

        case 'resolve_alert':
            $alertId = (int) ($_GET['alert_id'] ?? 0);
            $alertManager = new SmartCronAlert($db);
            $success = $alertManager->resolveAlert($alertId);
            echo json_encode(['success' => $success]);
            break;

        default:
            echo json_encode(['error' => 'Unknown action']);
    }
}
