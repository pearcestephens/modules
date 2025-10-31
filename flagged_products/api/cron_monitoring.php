<?php
/**
 * Cron Monitoring Dashboard API
 * 
 * Provides real-time monitoring data for flagged products cron tasks
 * 
 * Endpoints:
 * - GET /api/cron/health - Overall health status
 * - GET /api/cron/metrics/{task} - Performance metrics for task
 * - GET /api/cron/alerts - Recent alerts
 * - GET /api/cron/history/{task} - Execution history
 * - POST /api/cron/alerts/{id}/acknowledge - Acknowledge alert
 * 
 * @package CIS\FlaggedProducts\API
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/CISLogger.php';
require_once __DIR__ . '/../cron/lib/CronMonitor.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Parse endpoint
    $endpoint = $_GET['endpoint'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Route to appropriate handler
    switch ($endpoint) {
        case 'health':
            handleHealthStatus();
            break;
            
        case 'metrics':
            handleMetrics();
            break;
            
        case 'alerts':
            handleAlerts();
            break;
            
        case 'history':
            handleHistory();
            break;
            
        case 'acknowledge':
            handleAcknowledge();
            break;
            
        case 'trends':
            handleTrends();
            break;
            
        case 'summary':
            handleSummary();
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Endpoint not found'
            ]);
    }
    
} catch (Exception $e) {
    CISLogger::error('cron_monitor_api', 'API error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Get overall health status for all tasks
 */
function handleHealthStatus(): void {
    $sql = "SELECT * FROM vw_cron_health_status ORDER BY last_run DESC";
    $tasks = sql_query_collection_safe($sql, []);
    
    // Add status color
    foreach ($tasks as &$task) {
        $task->status = 'healthy';
        $task->status_color = 'success';
        
        // Check if last run was successful
        if ($task->last_run !== $task->last_successful_run) {
            $task->status = 'failing';
            $task->status_color = 'danger';
        }
        
        // Check if no runs in last 2 hours (for frequent tasks)
        elseif (strtotime($task->last_run) < strtotime('-2 hours')) {
            $task->status = 'stale';
            $task->status_color = 'warning';
        }
        
        // Check success rate
        elseif ($task->success_rate_24h < 90) {
            $task->status = 'degraded';
            $task->status_color = 'warning';
        }
        
        // Check execution time
        elseif ($task->avg_execution_time_24h > 300) {
            $task->status = 'slow';
            $task->status_color = 'info';
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $tasks,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get performance metrics for a specific task
 */
function handleMetrics(): void {
    $taskName = $_GET['task'] ?? '';
    
    if (empty($taskName)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Task name required'
        ]);
        return;
    }
    
    $metrics = CronMonitor::getPerformanceMetrics($taskName);
    
    // Get recent execution times for chart
    $sql = "SELECT 
                DATE_FORMAT(started_at, '%Y-%m-%d %H:%i') as time,
                execution_time,
                success
            FROM flagged_products_cron_executions
            WHERE task_name = ?
            AND started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY started_at ASC";
    
    $executions = sql_query_collection_safe($sql, [$taskName]);
    
    // Get error/warning counts by hour
    $sql = "SELECT 
                HOUR(started_at) as hour,
                SUM(error_count) as errors,
                SUM(warning_count) as warnings
            FROM flagged_products_cron_executions
            WHERE task_name = ?
            AND started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY HOUR(started_at)
            ORDER BY hour";
    
    $issuesByHour = sql_query_collection_safe($sql, [$taskName]);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'metrics' => $metrics,
            'executions' => $executions,
            'issues_by_hour' => $issuesByHour
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get recent alerts
 */
function handleAlerts(): void {
    $limit = (int)($_GET['limit'] ?? 50);
    $severity = $_GET['severity'] ?? null;
    $acknowledged = isset($_GET['acknowledged']) ? (int)$_GET['acknowledged'] : null;
    
    $sql = "SELECT a.*, 
                   u.name as acknowledged_by_name
            FROM flagged_products_cron_alerts a
            LEFT JOIN users u ON a.acknowledged_by = u.id
            WHERE 1=1";
    
    $params = [];
    
    if ($severity) {
        $sql .= " AND a.severity = ?";
        $params[] = $severity;
    }
    
    if ($acknowledged !== null) {
        $sql .= " AND a.acknowledged = ?";
        $params[] = $acknowledged;
    }
    
    $sql .= " ORDER BY a.created_at DESC LIMIT ?";
    $params[] = $limit;
    
    $alerts = sql_query_collection_safe($sql, $params);
    
    // Parse JSON details
    foreach ($alerts as &$alert) {
        $alert->details = json_decode($alert->details ?? '{}');
    }
    
    // Get summary
    $summarySQL = "SELECT 
                      severity,
                      COUNT(*) as count,
                      SUM(CASE WHEN acknowledged = 0 THEN 1 ELSE 0 END) as unacknowledged
                   FROM flagged_products_cron_alerts
                   WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                   GROUP BY severity";
    
    $summary = sql_query_collection_safe($summarySQL, []);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'alerts' => $alerts,
            'summary' => $summary
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get execution history for a task
 */
function handleHistory(): void {
    $taskName = $_GET['task'] ?? '';
    $limit = (int)($_GET['limit'] ?? 100);
    
    if (empty($taskName)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Task name required'
        ]);
        return;
    }
    
    $history = CronMonitor::getExecutionHistory($taskName, $limit);
    
    // Parse JSON metrics
    foreach ($history as &$execution) {
        $execution->metrics = json_decode($execution->metrics ?? '{}');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $history,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Acknowledge an alert
 */
function handleAcknowledge(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Method not allowed'
        ]);
        return;
    }
    
    $alertId = (int)($_POST['alert_id'] ?? 0);
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if (!$alertId || !$userId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Alert ID and User ID required'
        ]);
        return;
    }
    
    $sql = "UPDATE flagged_products_cron_alerts
            SET acknowledged = 1,
                acknowledged_by = ?,
                acknowledged_at = NOW()
            WHERE id = ?";
    
    sql_query_update_or_insert_safe($sql, [$userId, $alertId]);
    
    CISLogger::info('cron_monitor_api', "Alert {$alertId} acknowledged by user {$userId}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Alert acknowledged'
    ]);
}

/**
 * Get performance trends
 */
function handleTrends(): void {
    $taskName = $_GET['task'] ?? '';
    $days = (int)($_GET['days'] ?? 30);
    
    $sql = "SELECT * FROM vw_cron_performance_trends";
    $params = [];
    
    if ($taskName) {
        $sql .= " WHERE task_name = ?";
        $params[] = $taskName;
    }
    
    $sql .= " AND snapshot_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
    $params[] = $days;
    
    $sql .= " ORDER BY snapshot_date ASC, task_name";
    
    $trends = sql_query_collection_safe($sql, $params);
    
    echo json_encode([
        'success' => true,
        'data' => $trends,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get comprehensive summary dashboard
 */
function handleSummary(): void {
    // Overall health
    $healthSQL = "SELECT * FROM vw_cron_health_status";
    $health = sql_query_collection_safe($healthSQL, []);
    
    // Active alerts count
    $alertsSQL = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN severity = 'CRITICAL' THEN 1 ELSE 0 END) as critical,
                    SUM(CASE WHEN severity = 'WARNING' THEN 1 ELSE 0 END) as warnings
                  FROM flagged_products_cron_alerts
                  WHERE acknowledged = 0
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    
    $alertsSummary = sql_query_single_safe($alertsSQL, []);
    
    // Recent failures
    $failuresSQL = "SELECT * FROM vw_cron_recent_failures LIMIT 10";
    $recentFailures = sql_query_collection_safe($failuresSQL, []);
    
    // Tasks running now
    $runningSQL = "SELECT DISTINCT task_name
                   FROM smart_cron_tasks
                   WHERE is_running = 1";
    
    $runningTasks = sql_query_collection_safe($runningSQL, []);
    
    // Calculate overall system health
    $healthyTasks = 0;
    $totalTasks = count($health);
    
    foreach ($health as $task) {
        if ($task->success_rate_24h >= 95 && $task->last_run === $task->last_successful_run) {
            $healthyTasks++;
        }
    }
    
    $systemHealth = $totalTasks > 0 ? round(($healthyTasks / $totalTasks) * 100, 1) : 100;
    $systemStatus = 'healthy';
    
    if ($systemHealth < 70) {
        $systemStatus = 'critical';
    } elseif ($systemHealth < 90) {
        $systemStatus = 'degraded';
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'system_health' => $systemHealth,
            'system_status' => $systemStatus,
            'tasks' => [
                'total' => $totalTasks,
                'healthy' => $healthyTasks,
                'running' => count($runningTasks)
            ],
            'alerts' => [
                'total' => (int)$alertsSummary->total,
                'critical' => (int)$alertsSummary->critical,
                'warnings' => (int)$alertsSummary->warnings
            ],
            'health_by_task' => $health,
            'recent_failures' => $recentFailures,
            'running_tasks' => $runningTasks
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
