<?php
/**
 * Check Existing Indexes
 *
 * Shows all current indexes on consignments tables before migration.
 * Use this to avoid duplicate index creation.
 *
 * Usage:
 *   php check-existing-indexes.php
 *
 * @package CIS\Consignments\Migrations
 * @version 1.0.0
 */

declare(strict_types=1);

// Set document root for base bootstrap
$_SERVER['DOCUMENT_ROOT'] = dirname(dirname(dirname(__DIR__)));

// Load base bootstrap for Database class
require_once __DIR__ . '/../../base/bootstrap.php';

// Get PDO connection from Database class
try {
    $pdo = CIS\Base\Database::pdo();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// ANSI color codes
const C_BLUE = "\033[0;34m";
const C_GREEN = "\033[0;32m";
const C_YELLOW = "\033[1;33m";
const C_RESET = "\033[0m";

function info(string $msg): void {
    echo C_BLUE . "[INFO] " . C_RESET . $msg . PHP_EOL;
}

function success(string $msg): void {
    echo C_GREEN . "[OK]   " . C_RESET . $msg . PHP_EOL;
}

function warn(string $msg): void {
    echo C_YELLOW . "[WARN] " . C_RESET . $msg . PHP_EOL;
}

try {
    echo PHP_EOL;
    info("Checking existing indexes on consignments tables...");
    echo PHP_EOL;

    // Check vend_consignments table (actual table name)
    info("=== VEND_CONSIGNMENTS TABLE ===");
    echo PHP_EOL;

    $stmt = $pdo->query("SHOW INDEXES FROM vend_consignments");
    $consignmentIndexes = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $keyName = $row['Key_name'];
        $columnName = $row['Column_name'];
        $unique = $row['Non_unique'] == 0 ? 'UNIQUE' : 'INDEX';
        $type = $row['Index_type'];

        if (!isset($consignmentIndexes[$keyName])) {
            $consignmentIndexes[$keyName] = [
                'type' => $unique,
                'index_type' => $type,
                'columns' => []
            ];
        }

        $consignmentIndexes[$keyName]['columns'][] = $columnName;
    }

    if (empty($consignmentIndexes)) {
        warn("No indexes found on consignments table!");
    } else {
        foreach ($consignmentIndexes as $indexName => $details) {
            $columns = implode(', ', $details['columns']);
            success("{$indexName} ({$details['type']}) on ({$columns}) - {$details['index_type']}");
        }
    }

    echo PHP_EOL;

    // Check vend_consignment_line_items table
    info("=== VEND_CONSIGNMENT_LINE_ITEMS TABLE ===");
    echo PHP_EOL;

    $stmt = $pdo->query("SHOW INDEXES FROM vend_consignment_line_items");
    $itemIndexes = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $keyName = $row['Key_name'];
        $columnName = $row['Column_name'];
        $unique = $row['Non_unique'] == 0 ? 'UNIQUE' : 'INDEX';
        $type = $row['Index_type'];

        if (!isset($itemIndexes[$keyName])) {
            $itemIndexes[$keyName] = [
                'type' => $unique,
                'index_type' => $type,
                'columns' => []
            ];
        }

        $itemIndexes[$keyName]['columns'][] = $columnName;
    }

    if (empty($itemIndexes)) {
        warn("No indexes found on vend_consignment_line_items table!");
    } else {
        foreach ($itemIndexes as $indexName => $details) {
            $columns = implode(', ', $details['columns']);
            success("{$indexName} ({$details['type']}) on ({$columns}) - {$details['index_type']}");
        }
    }

    echo PHP_EOL;

    // Summary
    echo str_repeat("=", 70) . PHP_EOL;
    echo "SUMMARY" . PHP_EOL;
    echo str_repeat("=", 70) . PHP_EOL;
    echo "Vend_consignments table indexes: " . count($consignmentIndexes) . PHP_EOL;
    echo "Vend_consignment_line_items table indexes: " . count($itemIndexes) . PHP_EOL;
    echo str_repeat("=", 70) . PHP_EOL;
    echo PHP_EOL;

    // Recommendations
    info("=== RECOMMENDATIONS ===");
    echo PHP_EOL;

    $needed = [];

    // Check for common indexes on vend_consignments
    $recommendedConsignmentIndexes = [
        'idx_status' => ['status'],
        'idx_outlet_id' => ['outlet_id'],
        'idx_destination_outlet_id' => ['destination_outlet_id'],
        'idx_due_at' => ['due_at'],
        'idx_name' => ['name']
    ];

    foreach ($recommendedConsignmentIndexes as $indexName => $columns) {
        if (!isset($consignmentIndexes[$indexName])) {
            $needed[] = "ALTER TABLE vend_consignments ADD INDEX {$indexName} (" . implode(', ', $columns) . ");";
        }
    }

    $recommendedItemIndexes = [
        'idx_transfer_id' => ['transfer_id'],
        'idx_product_id' => ['product_id'],
        'idx_received' => ['received']
    ];

    foreach ($recommendedItemIndexes as $indexName => $columns) {
        if (!isset($itemIndexes[$indexName])) {
            $needed[] = "ALTER TABLE vend_consignment_line_items ADD INDEX {$indexName} (" . implode(', ', $columns) . ");";
        }
    }

    if (empty($needed)) {
        success("All recommended indexes already exist!");
    } else {
        warn("Missing " . count($needed) . " recommended indexes:");
        echo PHP_EOL;
        foreach ($needed as $sql) {
            echo "  " . $sql . PHP_EOL;
        }
        echo PHP_EOL;
        info("Run add-consignment-indexes.sql to add missing indexes.");
    }

    echo PHP_EOL;

} catch (PDOException $e) {
    echo C_BLUE . "[ERROR]" . C_RESET . " Database error: " . $e->getMessage() . PHP_EOL;
    exit(1);
} catch (Throwable $e) {
    echo C_BLUE . "[ERROR]" . C_RESET . " Unexpected error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
