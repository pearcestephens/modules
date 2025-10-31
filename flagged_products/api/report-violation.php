<?php
/**
 * Report Security Violation API
 * 
 * Receives security violation reports from anti-cheat JavaScript
 * Logs to database for manager review
 * 
 * @package CIS\FlaggedProducts
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/CISLogger.php';
require_once __DIR__ . '/../lib/AntiCheat.php';

// Security check
if (!isset($_SESSION['userID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['violation'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$userId = $_SESSION['userID'];
$violation = $input['violation'];
$context = $input['context'] ?? [];

try {
    // Log to audit table
    $sql = "INSERT INTO audit_log 
            (user_id, activity_type, details, ip_address, created_at) 
            VALUES (?, ?, ?, ?, NOW())";
    
    $details = json_encode([
        'violation_type' => $violation['type'] ?? 'unknown_violation',
        'severity' => $violation['severity'] ?? 'warning',
        'context' => $context,
        'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    sql_query_update_or_insert_safe($sql, [
        $userId,
        'flagged_products_violation',
        $details,
        $ipAddress
    ]);
    
    // Log to CIS Logger
    CISLogger::security('flagged_products', $violation['type'] ?? 'violation', [
        'user_id' => $userId,
        'violation' => $violation,
        'context' => $context
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Violation logged',
        'logged_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    CISLogger::error('flagged_products', 'Failed to log violation: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to log violation'
    ]);
}
