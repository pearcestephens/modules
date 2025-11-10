<?php
/**
 * Crawler Logs Dashboard
 *
 * Real-time monitoring of all crawler activity
 * Unified logs from News Aggregator, Competitive Intel, Transfer Engine
 *
 * @package CIS\Modules\CompetitiveIntel
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';

// Get recent sessions
$stmt = $db->query("
    SELECT * FROM crawler_sessions
    ORDER BY started_at DESC
    LIMIT 50
");
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent logs (if viewing specific session)
$sessionLogs = [];
if (isset($_GET['session_id'])) {
    $stmt = $db->prepare("
        SELECT * FROM crawler_logs
        WHERE session_id = ?
        ORDER BY timestamp DESC
        LIMIT 500
    ");
    $stmt->execute([$_GET['session_id']]);
    $sessionLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get error summary
$stmt = $db->query("
    SELECT
        crawler_type,
        COUNT(*) as error_count,
        MAX(timestamp) as last_error
    FROM crawler_logs
    WHERE level IN ('error', 'critical')
    AND timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY crawler_type
");
$errors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crawler Logs - Competitive Intelligence</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #0f1419; color: #e4e6eb; font-family: 'Courier New', monospace; }
        .card { background: #1a1f2e; border: 1px solid #2d3748; }
        .table { color: #e4e6eb; font-size: 0.85rem; }
        .table thead th { border-bottom: 2px solid #2d3748; }
        .table tbody td { border-bottom: 1px solid #2d3748; }
        .log-debug { color: #6c757d; }
        .log-info { color: #17a2b8; }
        .log-warning { color: #ffc107; }
        .log-error { color: #dc3545; }
        .log-critical { color: #ff0000; font-weight: bold; }
        .session-running { color: #28a745; }
        .session-completed { color: #17a2b8; }
        .session-failed { color: #dc3545; }
        pre { background: #000; color: #0f0; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="admin.php">
                <i class="bi bi-arrow-left"></i> Back to Control Panel
            </a>
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-file-text"></i> Crawler Logs
            </span>
        </div>
    </nav>

    <div class="container-fluid py-4">

        <!-- Error Summary -->
        <?php if (!empty($errors)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-danger">
                    <div class="card-body">
                        <h5 class="card-title text-danger">
                            <i class="bi bi-exclamation-triangle-fill"></i> Recent Errors (Last 7 Days)
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Crawler Type</th>
                                        <th>Error Count</th>
                                        <th>Last Error</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($errors as $error): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($error['crawler_type']); ?></td>
                                        <td><span class="badge bg-danger"><?php echo $error['error_count']; ?></span></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($error['last_error'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Crawler Sessions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-clock-history"></i> Recent Crawler Sessions
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Session ID</th>
                                        <th>Crawler Type</th>
                                        <th>Started</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sessions as $session): ?>
                                    <tr>
                                        <td>
                                            <code><?php echo htmlspecialchars($session['session_id']); ?></code>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($session['crawler_type']); ?></span>
                                        </td>
                                        <td><?php echo date('M j, g:i A', strtotime($session['started_at'])); ?></td>
                                        <td>
                                            <?php
                                            if ($session['completed_at']) {
                                                $start = new DateTime($session['started_at']);
                                                $end = new DateTime($session['completed_at']);
                                                $duration = $start->diff($end);
                                                echo $duration->format('%H:%I:%S');
                                            } else {
                                                echo '<span class="text-muted">Running...</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="session-<?php echo $session['status']; ?>">
                                                <i class="bi bi-circle-fill"></i> <?php echo ucfirst($session['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?session_id=<?php echo htmlspecialchars($session['session_id']); ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-file-earmark-text"></i> View Logs
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Session Logs Detail -->
        <?php if (!empty($sessionLogs)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-terminal"></i> Session Logs: <?php echo htmlspecialchars($_GET['session_id']); ?>
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Level</th>
                                        <th>Message</th>
                                        <th>Memory</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sessionLogs as $log): ?>
                                    <tr>
                                        <td style="white-space: nowrap;"><?php echo date('H:i:s', strtotime($log['timestamp'])); ?></td>
                                        <td>
                                            <span class="log-<?php echo $log['level']; ?>">
                                                <?php echo strtoupper($log['level']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($log['message']); ?>
                                            <?php if ($log['context'] && $log['context'] !== '{}'): ?>
                                                <br>
                                                <small class="text-muted">
                                                    <details>
                                                        <summary>Context</summary>
                                                        <pre><?php echo htmlspecialchars(json_encode(json_decode($log['context']), JSON_PRETTY_PRINT)); ?></pre>
                                                    </details>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td style="white-space: nowrap;">
                                            <small><?php echo round($log['memory_usage'] / 1048576, 1); ?> MB</small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
