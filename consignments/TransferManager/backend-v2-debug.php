<?php
/**
 * Transfer Manager Backend API - Debug Version
 */
declare(strict_types=1);

// Enable error display
ini_set('display_errors', '1');
error_reporting(E_ALL);

try {
    // Test bootstrap
    echo "1. Loading bootstrap...\n";
    require_once __DIR__ . '/../bootstrap.php';
    echo "2. Bootstrap loaded\n";
    
    // Test TransferManagerAPI
    echo "3. Loading TransferManagerAPI...\n";
    use CIS\Consignments\Lib\TransferManagerAPI;
    echo "4. Class loaded\n";
    
    // Create instance
    echo "5. Creating API instance...\n";
    $api = new TransferManagerAPI();
    echo "6. Instance created\n";
    
    // Handle request
    echo "7. Handling request...\n";
    $api->handleRequest();
    echo "8. Request handled\n";
    
} catch (\Throwable $e) {
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'BOOTSTRAP_ERROR',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ], JSON_PRETTY_PRINT);
}
