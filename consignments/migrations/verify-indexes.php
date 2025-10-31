<?php
/**
 * Index Verification Script
 *
 * Verifies that all required indexes exist and analyzes their effectiveness.
 * Run this after executing add-consignment-indexes.sql
 *
 * Usage:
 *   php verify-indexes.php
 *
 * @package CIS\Consignments\Migrations
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// ANSI color codes for output
const COLOR_RED = "\033[0;31m";
const COLOR_GREEN = "\033[0;32m";
const COLOR_YELLOW = "\033[1;33m";
const COLOR_BLUE = "\033[0;34m";
const COLOR_RESET = "\033[0m";

function log_info(string $msg): void {
    echo COLOR_BLUE . "[INFO] " . COLOR_RESET . $msg . PHP_EOL;
}

function log_success(string $msg): void {
    echo COLOR_GREEN . "[PASS] " . COLOR_RESET . $msg . PHP_EOL;
}

function log_warn(string $msg): void {
    echo COLOR_YELLOW . "[WARN] " . COLOR_RESET . $msg . PHP_EOL;
}

function log_error(string $msg): void {
    echo COLOR_RED . "[FAIL] " . COLOR_RESET . $msg . PHP_EOL;
}

// Expected indexes
$expectedIndexes = [
    'consignments' => [
        'PRIMARY',
        'idx_status',
        'idx_origin',
        'idx_dest',
        'idx_created',
        'idx_outlet_status',
        'idx_dest_status',
        'idx_created_status',
        'idx_ref_code'
    ],
    'consignment_items' => [
        'PRIMARY',
        'idx_consignment',
        'idx_product',
        'idx_sku',
        'idx_status',
        'idx_consignment_status'
    ]
];

try {
    $pdo = db_ro();

    echo PHP_EOL;
    log_info("Starting index verification...");
    echo PHP_EOL;

    $allPassed = true;
    $totalIndexes = 0;
    $foundIndexes = 0;

    foreach ($expectedIndexes as $table => $indexes) {
        log_info("Checking table: {$table}");

        // Get actual indexes
        $stmt = $pdo->query("SHOW INDEXES FROM `{$table}`");
        $actualIndexes = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $actualIndexes[] = $row['Key_name'];
        }

        $actualIndexes = array_unique($actualIndexes);

        // Check each expected index
        foreach ($indexes as $expectedIndex) {
            $totalIndexes++;

            if (in_array($expectedIndex, $actualIndexes)) {
                log_success("✓ Index exists: {$expectedIndex}");
                $foundIndexes++;
            } else {
                log_error("✗ Missing index: {$expectedIndex}");
                $allPassed = false;
            }
        }

        // Check for unexpected indexes (informational)
        $unexpectedIndexes = array_diff($actualIndexes, $indexes);
        if (!empty($unexpectedIndexes)) {
            foreach ($unexpectedIndexes as $idx) {
                log_warn("Additional index found: {$idx}");
            }
        }

        echo PHP_EOL;
    }

    // Summary
    echo str_repeat("=", 60) . PHP_EOL;
    echo "VERIFICATION SUMMARY" . PHP_EOL;
    echo str_repeat("=", 60) . PHP_EOL;
    echo "Total expected indexes: {$totalIndexes}" . PHP_EOL;
    echo "Found indexes: {$foundIndexes}" . PHP_EOL;
    echo "Missing indexes: " . ($totalIndexes - $foundIndexes) . PHP_EOL;
    echo str_repeat("=", 60) . PHP_EOL;
    echo PHP_EOL;

    if ($allPassed) {
        log_success("All indexes verified successfully!");
        echo PHP_EOL;
        log_info("Running query performance analysis...");
        echo PHP_EOL;

        // Test index usage with EXPLAIN
        $testQueries = [
            "SELECT * FROM consignments WHERE status = 'sent' LIMIT 10",
            "SELECT * FROM consignments WHERE origin_outlet_id = 1 AND status = 'sent' LIMIT 10",
            "SELECT * FROM consignments WHERE ref_code LIKE 'CON%' LIMIT 10",
            "SELECT * FROM consignment_items WHERE consignment_id = 1",
            "SELECT * FROM consignments ORDER BY created_at DESC LIMIT 10"
        ];

        foreach ($testQueries as $query) {
            echo str_repeat("-", 60) . PHP_EOL;
            log_info("Query: {$query}");

            $stmt = $pdo->query("EXPLAIN {$query}");
            $explain = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($explain) {
                $keyUsed = $explain['key'] ?? 'NULL';
                $type = $explain['type'] ?? 'unknown';
                $rows = $explain['rows'] ?? '?';

                if ($keyUsed !== 'NULL' && $keyUsed !== null) {
                    log_success("Using index: {$keyUsed} (type: {$type}, rows: {$rows})");
                } else {
                    log_warn("No index used (type: {$type}, rows: {$rows})");
                }
            }
        }

        echo PHP_EOL;
        log_success("Performance analysis complete!");
        exit(0);
    } else {
        log_error("Index verification failed. Please run the migration script.");
        exit(1);
    }

} catch (PDOException $e) {
    log_error("Database error: " . $e->getMessage());
    exit(1);
} catch (Throwable $e) {
    log_error("Unexpected error: " . $e->getMessage());
    exit(1);
}
