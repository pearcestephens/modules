<?php
/**
 * Batch Approve API
 * Approves all high-confidence items in one go
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

// Get minimum confidence (default 0.85)
$input = json_decode(file_get_contents('php://input'), true);
$minConfidence = $input['min_confidence'] ?? 0.85;

try {
    // Initialize
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=jcepnzzkmj", "jcepnzzkmj", "wprKh9Jq63");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $aiEngine = new AIPayrollEngine($pdo);
    $dashboard = new PayrollDashboard($pdo, $aiEngine);

    // Batch approve
    $result = $dashboard->batchApproveHighConfidence(
        (float)$minConfidence,
        (int)$_SESSION['user_id']
    );

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to batch approve',
        'message' => $e->getMessage()
    ]);
}
