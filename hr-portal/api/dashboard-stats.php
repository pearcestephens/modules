<?php
/**
 * Dashboard Stats API
 * Returns real-time statistics for the payroll dashboard
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../includes/AIPayrollEngine.php';
require_once __DIR__ . '/../includes/PayrollDashboard.php';

// Check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // Initialize
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=jcepnzzkmj", "jcepnzzkmj", "wprKh9Jq63");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $aiEngine = new AIPayrollEngine($pdo);
    $dashboard = new PayrollDashboard($pdo, $aiEngine);

    // Get stats
    $stats = $dashboard->getTodayStats();

    // Get AI insights
    $insights = $aiEngine->getInsights();

    // Return response
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'insights' => $insights,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch stats',
        'message' => $e->getMessage()
    ]);
}
