#!/usr/bin/env php
<?php
/**
 * Scheduled Inventory Sync Check
 * Run via cron every 5 minutes:
 * Cron: 0,5,10,15,20,25,30,35,40,45,50,55 * * * * /usr/bin/php /path/to/scheduled_sync.php >> /var/log/inventory_sync.log 2>&1
 */

// Change to script directory
chdir(__DIR__);

// Load autoloader
require_once __DIR__ . '/../autoload.php';

use CIS\InventorySync\InventorySyncEngine;

// Start time
$start = microtime(true);

try {
    // Connect to database
    $pdo = new PDO(
        "mysql:host=" . (getenv('DB_HOST') ?: 'localhost') . ";dbname=" . (getenv('DB_NAME') ?: 'vend'),
        getenv('DB_USER') ?: 'root',
        getenv('DB_PASS') ?: ''
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Initialize sync engine
    $sync = new InventorySyncEngine($pdo);

    echo "[" . date('Y-m-d H:i:s') . "] Starting inventory sync check...\n";

    // Run sync check (all products)
    $report = $sync->checkSync();

    // Calculate duration
    $duration = round(microtime(true) - $start, 2);

    // Log results
    echo "[" . date('Y-m-d H:i:s') . "] Sync check completed in {$duration}s\n";
    echo "  State: {$report['sync_state']}\n";
    echo "  Products checked: {$report['products_checked']}\n";
    echo "  Perfect matches: {$report['perfect_matches']}\n";
    echo "  Minor drifts: {$report['minor_drifts']}\n";
    echo "  Major drifts: {$report['major_drifts']}\n";
    echo "  Critical issues: {$report['critical_issues']}\n";
    echo "  Auto-fixed: {$report['auto_fixed']}\n";
    echo "  Alerts triggered: {$report['alerts_triggered']}\n";

    // Send alert if critical issues
    if ($report['critical_issues'] > 0) {
        echo "\n⚠️  CRITICAL: {$report['critical_issues']} critical sync issues detected!\n";

        // Send email/SMS/Slack notification
        $to = getenv('ALERT_EMAIL') ?: 'admin@vapeshed.co.nz';
        $subject = "CRITICAL: Inventory Sync Issues Detected";
        $message = "Found {$report['critical_issues']} critical inventory sync issues.\n\n";
        $message .= "Products checked: {$report['products_checked']}\n";
        $message .= "Perfect matches: {$report['perfect_matches']}\n";
        $message .= "Major drifts: {$report['major_drifts']}\n";
        $message .= "Critical issues: {$report['critical_issues']}\n\n";
        $message .= "View details: https://staff.vapeshed.co.nz/inventory-sync/alerts\n";

        // Uncomment to enable email alerts
        // mail($to, $subject, $message);

        echo "Alert email would be sent to: $to\n";
    }

    // Warning if sync state not perfect
    if ($report['sync_state'] !== 'perfect' && $report['sync_state'] !== 'minor_drift') {
        echo "\n⚠️  WARNING: Sync state is {$report['sync_state']}\n";
    }

    // Success
    echo "\n✅ Sync check complete\n\n";
    exit(0);

} catch (PDOException $e) {
    echo "[" . date('Y-m-d H:i:s') . "] DATABASE ERROR: " . $e->getMessage() . "\n";
    exit(1);

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
