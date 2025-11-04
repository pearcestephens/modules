#!/usr/bin/env php
<?php
/**
 * Xero Payroll Sync CLI Script
 *
 * Syncs payroll data from Xero API and caches it in database
 * Run this manually or via cron to keep payroll data up to date
 *
 * Usage:
 *   php sync-xero-payroll.php
 *   php sync-xero-payroll.php --months=3
 *   php sync-xero-payroll.php --force
 *
 * @package CIS\Modules\StaffAccounts\CLI
 */

declare(strict_types=1);

// Enable CLI mode (but we need Xero SDK, so load it manually)
if (!defined('CIS_CLI_MODE')) {
    define('CIS_CLI_MODE', true);
}

// Load bootstrap
require_once __DIR__ . '/../bootstrap.php';

// CRITICAL: Load Xero SDK manually for CLI (normally skipped in CLI mode)
if (!isset($payrollNzApi)) {
    require_once ROOT_PATH . '/assets/services/xero-sdk/xero-init.php';
}

use CIS\Modules\StaffAccounts\XeroPayrollService;

// Parse command line arguments
$options = getopt('', ['months:', 'force', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Xero Payroll Sync Script

Usage:
  php sync-xero-payroll.php [OPTIONS]

Options:
  --months=N    Sync last N months (default: 6)
  --force       Force re-sync even if cached
  --help        Show this help message

Examples:
  php sync-xero-payroll.php
  php sync-xero-payroll.php --months=3
  php sync-xero-payroll.php --force

HELP;
    exit(0);
}

$months = isset($options['months']) ? (int)$options['months'] : 6;
$force = isset($options['force']);

echo "=== Xero Payroll Sync ===" . PHP_EOL;
echo "Syncing last {$months} months" . ($force ? ' (FORCED)' : '') . PHP_EOL;
echo PHP_EOL;

try {
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!$pdo) {
        throw new Exception('PDO connection not available');
    }

    // Check if Xero SDK is loaded
    if (!isset($payrollNzApi)) {
        throw new Exception('Xero SDK not loaded. Check xero-init.php');
    }

    if (!isset($xeroTenantId)) {
        throw new Exception('Xero Tenant ID not configured. Check xero-init.php');
    }

    $service = new XeroPayrollService($pdo, $payrollNzApi, $xeroTenantId);

    $startTime = microtime(true);
    $result = $service->syncPayrollsFromXero($months);
    $duration = round(microtime(true) - $startTime, 2);

    if ($result['success']) {
        echo "✅ Sync completed in {$duration}s" . PHP_EOL;
        echo "   Synced: {$result['synced']} payrolls" . PHP_EOL;
        echo "   Cached: {$result['cached']} payrolls" . PHP_EOL;
        echo "   Total:  " . ($result['synced'] + $result['cached']) . " payrolls" . PHP_EOL;

        if (!empty($result['errors'])) {
            echo PHP_EOL . "⚠️  Errors encountered:" . PHP_EOL;
            foreach ($result['errors'] as $error) {
                echo "   - {$error}" . PHP_EOL;
            }
        }

        exit(0);
    } else {
        echo "❌ Sync failed: " . ($result['error'] ?? 'Unknown error') . PHP_EOL;
        exit(1);
    }

} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . PHP_EOL;
    echo "   " . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    exit(1);
}
