<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

try {
    echo json_encode(['status' => 'bootstrap loaded']);
    
    // Initialize PDO
    \CIS\Base\Database::init();
    echo json_encode(['status' => 'PDO initialized']);
    
    // Get connection
    $db = \CIS\Base\DatabasePDO::connection();
    echo json_encode(['status' => 'Connection obtained', 'db_type' => get_class($db)]);
    
    // Try a simple query
    $stmt = $db->prepare("SELECT 1");
    $stmt->execute();
    $result = $stmt->fetch();
    
    echo json_encode(['status' => 'Query successful', 'result' => $result]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'ERROR',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
