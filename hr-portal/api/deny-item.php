<?php
/**
 * Deny Item API
 * Denies a payroll item flagged by AI
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

// Validate input
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['decision_id']) || !isset($input['reason'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing decision_id or reason']);
    exit;
}

try {
    // Initialize
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=jcepnzzkmj", "jcepnzzkmj", "wprKh9Jq63");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $aiEngine = new AIPayrollEngine($pdo);
    $dashboard = new PayrollDashboard($pdo, $aiEngine);

    // Deny the item
    $result = $dashboard->denyItem(
        (int)$input['decision_id'],
        (int)$_SESSION['user_id'],
        $input['reason']
    );

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to deny item',
        'message' => $e->getMessage()
    ]);
}
