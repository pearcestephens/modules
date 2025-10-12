<?php
declare(strict_types=1);

require_once __DIR__ . '/module_bootstrap.php';

use Transfers\Lib\Db;

header('Content-Type: application/json');

try {
    // Test 1: Global $con access
    global $con;
    $result1 = $con->query("SELECT 1 as test");
    $row1 = $result1->fetch_assoc();
    
    // Test 2: Via Db helper
    $result2 = Db::query("SELECT 2 as test");
    $row2 = $result2->fetch_assoc();
    
    // Test 3: Via Db::mysqli()
    $result3 = Db::mysqli()->query("SELECT 3 as test");
    $row3 = $result3->fetch_assoc();
    
    // Test 4: Session validation
    $sessionId = Db::getSessionId();
    $validSession = $sessionId ? Db::validateSession($sessionId) : false;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'global_con_test' => $row1,
            'db_helper_test' => $row2,
            'db_mysqli_test' => $row3,
            'session_id' => $sessionId,
            'session_valid' => $validSession,
            'connection_ping' => Db::ping()
        ],
        'meta' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'test_type' => 'quick_database_test'
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_PRETTY_PRINT);
}
?>