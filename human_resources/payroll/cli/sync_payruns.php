#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Sync Payruns CLI Script
 *
 * Usage:
 *   php sync_payruns.php [--limit=50]
 *
 * Options:
 *   --limit=N    Maximum number of payruns to sync (default: all)
 *   --help       Show this help message
 */

require_once __DIR__ . '/../../../base/bootstrap.php';
require_once __DIR__ . '/../lib/PayrollSyncService.php';

use HumanResources\Payroll\PayrollSyncService;
use CIS\Base\Database;

// Parse arguments
$options = getopt('', ['limit::', 'help']);

if (isset($options['help'])) {
    echo file_get_contents(__FILE__);
    exit(0);
}

$limit = isset($options['limit']) ? (int)$options['limit'] : null;

try {
    // Load environment variables
    $tenantId = getenv('XERO_TENANT_ID');
    $clientId = getenv('XERO_CLIENT_ID');
    $clientSecret = getenv('XERO_CLIENT_SECRET');

    if (!$tenantId || !$clientId || !$clientSecret) {
        throw new \RuntimeException(
            "Missing Xero credentials. Please set XERO_TENANT_ID, XERO_CLIENT_ID, and XERO_CLIENT_SECRET in .env"
        );
    }

    // Initialize Xero API client
    // Note: This assumes you have a Xero SDK factory or direct initialization
    // Adjust based on your actual Xero integration setup
    $config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()
        ->setClientId($clientId)
        ->setClientSecret($clientSecret);

    $api = new XeroAPI\XeroPHP\Api\PayrollNzApi(
        new GuzzleHttp\Client(),
        $config
    );

    // Get database connection
    $db = Database::pdo();

    // Initialize service
    $service = new PayrollSyncService($db, $api, $tenantId);

    // Run sync
    echo "Starting payrun sync" . ($limit ? " (limit: $limit)" : " (all)") . "...\n";
    $startTime = microtime(true);

    $stats = $service->syncPayruns($limit);

    $duration = round(microtime(true) - $startTime, 2);

    // Display results
    echo "\nSync completed in {$duration}s\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Payruns synced:    {$stats['payruns']}\n";
    echo "Payslips synced:   {$stats['payslips']}\n";
    echo "Deductions synced: {$stats['deductions']}\n";

    if (!empty($stats['errors'])) {
        echo "\nErrors encountered:\n";
        foreach ($stats['errors'] as $error) {
            echo "  ❌ $error\n";
        }
        exit(1);
    } else {
        echo "\n✅ All payruns synced successfully!\n";
        exit(0);
    }

} catch (\Throwable $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
