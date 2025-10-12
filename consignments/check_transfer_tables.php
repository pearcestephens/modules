<?php
declare(strict_types=1);

require_once __DIR__ . '/module_bootstrap.php';

use Transfers\Lib\Db;

header('Content-Type: application/json');

try {
    // Check what tables exist
    $result = Db::query("SHOW TABLES LIKE '%transfer%'");
    $tables = [];
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    // Check if stock_transfers table exists
    $result = Db::query("SHOW TABLES LIKE 'stock_transfers'");
    $stockTransfers = $result->fetch_array();
    
    if ($stockTransfers) {
        // Get stock_transfers structure
        $result = Db::query("DESCRIBE stock_transfers");
        $structure = [];
        while ($row = $result->fetch_assoc()) {
            $structure[] = $row;
        }
        
        // Get a sample transfer
        $result = Db::query("SELECT * FROM stock_transfers WHERE id = 13218 LIMIT 1");
        $sample = $result->fetch_assoc();
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'all_transfer_tables' => $tables,
            'stock_transfers_exists' => !empty($stockTransfers),
            'stock_transfers_structure' => $structure ?? [],
            'sample_transfer_13218' => $sample ?? null
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>