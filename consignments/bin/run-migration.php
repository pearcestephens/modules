#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Database Migration Executor
 *
 * Executes email notification system migration and verifies results.
 *
 * Usage:
 *   php bin/run-migration.php [--dry-run] [--force]
 *
 * @package CIS\Consignments
 * @version 1.0.0
 */

// Ensure running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

// Parse options
$options = getopt('', ['dry-run', 'force', 'help']);

if (isset($options['help'])) {
    showHelp();
    exit(0);
}

$dryRun = isset($options['dry-run']);
$force = isset($options['force']);

echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "  EMAIL NOTIFICATION SYSTEM - DATABASE MIGRATION\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "\n";

// Bootstrap
require_once __DIR__ . '/../bootstrap.php';

try {
    // Get database connection
    $pdo = db();

    echo "✓ Database connection established\n";
    echo "  Database: " . getDatabaseName($pdo) . "\n";
    echo "\n";

    // Check if tables already exist
    echo "Checking existing tables...\n";
    $existingTables = checkExistingTables($pdo);

    if (!empty($existingTables) && !$force) {
        echo "\n";
        echo "⚠️  WARNING: The following tables already exist:\n";
        foreach ($existingTables as $table) {
            echo "   - {$table}\n";
        }
        echo "\n";
        echo "Use --force to proceed anyway (this will NOT drop existing tables)\n";
        exit(1);
    }

    if ($dryRun) {
        echo "\n";
        echo "🔍 DRY RUN MODE - No changes will be made\n";
        echo "\n";
        showMigrationPlan();
        exit(0);
    }

    // Load migration SQL
    $migrationFile = __DIR__ . '/../database/migrations/email-notification-system.sql';

    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: {$migrationFile}");
    }

    echo "Loading migration SQL...\n";
    $sql = file_get_contents($migrationFile);

    // Split into individual statements
    $statements = explodeSqlStatements($sql);

    echo "Found " . count($statements) . " SQL statements\n";
    echo "\n";

    // Execute migration
    echo "Executing migration...\n";
    echo str_repeat("─", 67) . "\n";

    $executed = 0;
    $failed = 0;

    foreach ($statements as $i => $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;

        $statementPreview = substr($statement, 0, 60) . '...';
        echo sprintf("[%d/%d] %s\n", $i + 1, count($statements), $statementPreview);

        try {
            $pdo->exec($statement);
            $executed++;
        } catch (PDOException $e) {
            echo "   ❌ FAILED: " . $e->getMessage() . "\n";
            $failed++;
        }
    }

    echo str_repeat("─", 67) . "\n";
    echo "\n";

    if ($failed > 0) {
        echo "⚠️  Migration completed with {$failed} errors\n";
        echo "   Executed: {$executed}\n";
        echo "   Failed: {$failed}\n";
        echo "\n";
        exit(1);
    }

    echo "✅ Migration completed successfully!\n";
    echo "   Executed: {$executed} statements\n";
    echo "\n";

    // Verify tables
    echo "Verifying migration...\n";
    echo str_repeat("─", 67) . "\n";

    $verification = verifyMigration($pdo);

    foreach ($verification as $check) {
        $icon = $check['success'] ? '✓' : '❌';
        echo "{$icon} {$check['name']}: {$check['message']}\n";
    }

    echo str_repeat("─", 67) . "\n";
    echo "\n";

    $allPassed = array_reduce($verification, fn($carry, $item) => $carry && $item['success'], true);

    if ($allPassed) {
        echo "🎉 All verification checks passed!\n";
        echo "\n";
        echo "Next steps:\n";
        echo "  1. Make worker executable: chmod +x bin/notification-worker.php\n";
        echo "  2. Test worker: php bin/notification-worker.php --stats\n";
        echo "  3. Run unit tests: vendor/bin/phpunit tests/Unit/EmailServiceTest.php\n";
        echo "\n";
        exit(0);
    } else {
        echo "⚠️  Some verification checks failed. Please review.\n";
        exit(1);
    }

} catch (Exception $e) {
    echo "\n";
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function getDatabaseName(PDO $pdo): string
{
    return $pdo->query("SELECT DATABASE()")->fetchColumn();
}

function checkExistingTables(PDO $pdo): array
{
    $tables = [
        'consignment_notification_queue',
        'consignment_email_templates',
        'consignment_email_template_config',
        'consignment_email_log'
    ];

    $existing = [];

    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt && $stmt->fetch()) {
            $existing[] = $table;
        }
    }

    return $existing;
}

function explodeSqlStatements(string $sql): array
{
    // Remove comments
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    // Split by semicolon (crude but works for this migration)
    $statements = explode(';', $sql);

    return array_filter(array_map('trim', $statements));
}

function verifyMigration(PDO $pdo): array
{
    $results = [];

    // Check tables exist
    $tables = [
        'consignment_notification_queue',
        'consignment_email_templates',
        'consignment_email_template_config',
        'consignment_email_log'
    ];

    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        $exists = ($stmt && $stmt->fetch());

        $results[] = [
            'name' => "Table {$table}",
            'success' => $exists,
            'message' => $exists ? 'exists' : 'NOT FOUND'
        ];
    }

    // Check templates inserted
    $stmt = $pdo->query("SELECT COUNT(*) FROM consignment_email_templates");
    $templateCount = (int)$stmt->fetchColumn();

    $results[] = [
        'name' => 'Email templates',
        'success' => $templateCount >= 9,
        'message' => "{$templateCount} templates (expected 9)"
    ];

    // Check config inserted
    $stmt = $pdo->query("SELECT COUNT(*) FROM consignment_email_template_config");
    $configCount = (int)$stmt->fetchColumn();

    $results[] = [
        'name' => 'Config entries',
        'success' => $configCount >= 8,
        'message' => "{$configCount} entries (expected 8)"
    ];

    // Check indexes
    $stmt = $pdo->query("SHOW INDEX FROM consignment_notification_queue WHERE Key_name = 'idx_status_priority'");
    $hasIndex = (bool)$stmt->fetch();

    $results[] = [
        'name' => 'Queue indexes',
        'success' => $hasIndex,
        'message' => $hasIndex ? 'created' : 'missing'
    ];

    return $results;
}

function showMigrationPlan(): void
{
    echo "Migration will:\n";
    echo "  1. Create 4 tables:\n";
    echo "     - consignment_notification_queue\n";
    echo "     - consignment_email_templates\n";
    echo "     - consignment_email_template_config\n";
    echo "     - consignment_email_log\n";
    echo "\n";
    echo "  2. Insert 9 email templates:\n";
    echo "     - po_created_internal\n";
    echo "     - po_pending_approval\n";
    echo "     - po_approved\n";
    echo "     - po_rejected\n";
    echo "     - consignment_received\n";
    echo "     - discrepancy_alert\n";
    echo "     - po_created_supplier\n";
    echo "     - po_amended_supplier\n";
    echo "     - shipment_request_supplier\n";
    echo "\n";
    echo "  3. Insert 8 configuration entries:\n";
    echo "     - company_name, company_address, support_email\n";
    echo "     - support_phone, logo_url, primary_color\n";
    echo "     - secondary_color, footer_text\n";
    echo "\n";
}

function showHelp(): void
{
    echo <<<HELP

Email Notification System - Database Migration Executor
========================================================

Executes the email notification system database migration.

USAGE:
  php bin/run-migration.php [options]

OPTIONS:
  --dry-run    Show what would be executed without making changes
  --force      Proceed even if tables already exist
  --help       Show this help message

EXAMPLES:
  # Preview migration
  php bin/run-migration.php --dry-run

  # Execute migration
  php bin/run-migration.php

  # Force execution even if tables exist
  php bin/run-migration.php --force


HELP;
}
