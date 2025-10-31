#!/usr/bin/env php
<?php
/**
 * Database Migration Runner
 * 
 * Runs SQL migration files against the database
 * 
 * Usage:
 *   php run-migration.php 001_create_employee_mappings.sql
 *   php run-migration.php --all
 * 
 * @package CIS\StaffAccounts\Database
 */

declare(strict_types=1);

// Load bootstrap (provides database connection)
require_once __DIR__ . '/../bootstrap.php';

// Parse command line arguments
$migrationFile = $argv[1] ?? null;

if (!$migrationFile) {
    echo "Usage: php run-migration.php <migration-file.sql>\n";
    echo "   or: php run-migration.php --all\n";
    exit(1);
}

// Get PDO connection
try {
    $pdo = cis_resolve_pdo();
    echo "✓ Database connection established\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Get migrations directory
$migrationsDir = __DIR__ . '/migrations/';

// Determine which migrations to run
$migrationsToRun = [];

if ($migrationFile === '--all') {
    // Run all migrations
    $files = glob($migrationsDir . '*.sql');
    sort($files);
    $migrationsToRun = $files;
    echo "Running all migrations...\n\n";
} else {
    // Run specific migration
    $fullPath = $migrationsDir . $migrationFile;
    if (!file_exists($fullPath)) {
        echo "✗ Migration file not found: {$fullPath}\n";
        exit(1);
    }
    $migrationsToRun = [$fullPath];
}

// Run each migration
$successCount = 0;
$errorCount = 0;

foreach ($migrationsToRun as $migrationPath) {
    $filename = basename($migrationPath);
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Running: {$filename}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    // Read SQL file
    $sql = file_get_contents($migrationPath);
    
    if (empty($sql)) {
        echo "✗ Migration file is empty\n";
        $errorCount++;
        continue;
    }
    
    // Split into individual statements (simple split on semicolon + newline)
    $statements = array_filter(
        array_map('trim', preg_split('/;\s*$/m', $sql)),
        fn($stmt) => !empty($stmt) && strpos($stmt, '--') !== 0
    );
    
    echo "Found " . count($statements) . " SQL statements\n\n";
    
    // Execute each statement
    $stmtCount = 0;
    foreach ($statements as $statement) {
        $stmtCount++;
        
        // Skip comments and empty lines
        $cleanStmt = trim($statement);
        if (empty($cleanStmt) || strpos($cleanStmt, '--') === 0) {
            continue;
        }
        
        // Show first 80 chars of statement
        $preview = substr(str_replace(["\n", "\r"], ' ', $cleanStmt), 0, 80);
        echo "[{$stmtCount}] {$preview}...\n";
        
        try {
            $pdo->exec($cleanStmt);
            echo "    ✓ Success\n";
        } catch (PDOException $e) {
            // Check if error is "table already exists"
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "    ⚠ Warning: " . $e->getMessage() . "\n";
            } else {
                echo "    ✗ Error: " . $e->getMessage() . "\n";
                $errorCount++;
            }
        }
    }
    
    echo "\n";
    $successCount++;
}

// Summary
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "MIGRATION SUMMARY\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Total migrations run: " . count($migrationsToRun) . "\n";
echo "Successful: {$successCount}\n";
echo "Errors: {$errorCount}\n";

if ($errorCount === 0) {
    echo "\n✓ All migrations completed successfully!\n";
    exit(0);
} else {
    echo "\n✗ Some migrations failed. Check errors above.\n";
    exit(1);
}
