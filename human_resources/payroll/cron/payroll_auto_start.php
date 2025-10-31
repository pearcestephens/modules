#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Automated Payroll Run Starter (Cron Job)
 *
 * Runs every Tuesday at 9:00 AM NZT
 *
 * Purpose:
 * - Automatically creates new pay run for the week
 * - Calculates pay period (previous Monday to Sunday)
 * - Sets payment date (following Monday)
 * - Sends notification to payroll staff
 *
 * Cron Schedule:
 * 0 9 * * 2 /usr/bin/php /path/to/payroll_auto_start.php >> /path/to/logs/payroll_cron.log 2>&1
 *
 * @package CIS\HumanResources\Payroll\Cron
 * @version 1.0.0
 * @created 2025-10-29
 */

// Change to app root
chdir(dirname(__DIR__, 4));

// Load dependencies
require_once __DIR__ . '/../../../app.php';
require_once __DIR__ . '/../lib/PayrollSnapshotManager.php';

// ============================================================================
// CONFIGURATION
// ============================================================================

$config = [
    'timezone' => 'Pacific/Auckland',
    'notification_emails' => [
        'payroll@vapeshed.co.nz',
        'pearce.stephens@ecigdis.co.nz'
    ],
    'dry_run' => false, // Set true for testing
    'log_file' => __DIR__ . '/../../../../logs/payroll_cron.log'
];

// Set timezone
date_default_timezone_set($config['timezone']);

// Start logging
$logFile = $config['log_file'];
$startTime = microtime(true);

function cron_log(string $message, string $level = 'INFO'): void {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] [{$level}] {$message}\n";
    echo $line;
    file_put_contents($logFile, $line, FILE_APPEND);
}

cron_log("========================================");
cron_log("Payroll Auto-Start Cron Job Started");
cron_log("========================================");

// ============================================================================
// CHECK IF TODAY IS TUESDAY
// ============================================================================

$today = date('Y-m-d');
$dayOfWeek = date('N'); // 1 (Monday) to 7 (Sunday)

if ($dayOfWeek != 2) {
    cron_log("Today is not Tuesday (day={$dayOfWeek}). Exiting.", 'INFO');
    exit(0);
}

cron_log("Confirmed: Today is Tuesday ({$today})");

// ============================================================================
// CALCULATE PAY PERIOD
// ============================================================================

// Pay period: Previous Monday to Sunday
$periodEnd = date('Y-m-d', strtotime('last Sunday'));
$periodStart = date('Y-m-d', strtotime($periodEnd . ' -6 days'));

// Payment date: Next Monday (6 days from today)
$paymentDate = date('Y-m-d', strtotime($today . ' +6 days'));

cron_log("Pay Period: {$periodStart} to {$periodEnd}");
cron_log("Payment Date: {$paymentDate}");

// ============================================================================
// CHECK IF RUN ALREADY EXISTS
// ============================================================================

try {
    $pdo = get_pdo_connection();

    $stmt = $pdo->prepare("
        SELECT id, run_number, status
        FROM payroll_runs
        WHERE period_start = ? AND period_end = ?
    ");
    $stmt->execute([$periodStart, $periodEnd]);

    if ($existing = $stmt->fetch(PDO::FETCH_ASSOC)) {
        cron_log("Pay run already exists: Run #{$existing['run_number']} (ID: {$existing['id']}, Status: {$existing['status']})", 'WARNING');
        cron_log("Skipping creation to avoid duplicates.");

        // Send notification about existing run
        send_notification(
            $config['notification_emails'],
            "Payroll Run Already Exists - Week of {$periodStart}",
            "A pay run for {$periodStart} to {$periodEnd} already exists (Run #{$existing['run_number']}).\n\n" .
            "Status: {$existing['status']}\n" .
            "Please review at: https://staff.vapeshed.co.nz/payroll-process.php?run_id={$existing['id']}"
        );

        exit(0);
    }

    cron_log("No existing run found. Creating new pay run...");

} catch (Exception $e) {
    cron_log("Database error: " . $e->getMessage(), 'ERROR');
    exit(1);
}

// ============================================================================
// CREATE NEW PAY RUN
// ============================================================================

if ($config['dry_run']) {
    cron_log("DRY RUN MODE - Would create pay run but not actually doing it", 'INFO');
    exit(0);
}

try {
    // Get Xero tenant ID from config
    $xeroTenantId = 'YOUR_XERO_TENANT_ID'; // TODO: Get from config or env

    // Initialize snapshot manager
    $snapshotManager = new PayrollSnapshotManager($pdo, $xeroTenantId, null);

    // Create pay run
    $result = $snapshotManager->startPayRun(
        $periodStart,
        $periodEnd,
        $paymentDate,
        "Auto-created by cron on {$today}"
    );

    $runId = $result['run_id'];
    $runUuid = $result['run_uuid'];
    $runNumber = $result['run_number'];

    cron_log("✅ Pay run created successfully!");
    cron_log("   Run ID: {$runId}");
    cron_log("   Run UUID: {$runUuid}");
    cron_log("   Run Number: {$runNumber}");

    // Create initial revision
    $revisionId = $snapshotManager->createRevision(
        $runId,
        'load_payroll',
        'Pay run auto-created by cron scheduler',
        0,
        0.0
    );

    cron_log("   Revision ID: {$revisionId}");

    // Log to payroll audit
    payroll_log('info', "Auto-created pay run #{$runNumber} via cron", [
        'run_id' => $runId,
        'period' => "{$periodStart} to {$periodEnd}",
        'payment_date' => $paymentDate,
        'source' => 'cron'
    ]);

} catch (Exception $e) {
    cron_log("Failed to create pay run: " . $e->getMessage(), 'ERROR');
    cron_log("Stack trace: " . $e->getTraceAsString(), 'ERROR');

    // Send error notification
    send_notification(
        $config['notification_emails'],
        "❌ Payroll Auto-Start Failed - {$today}",
        "Failed to create payroll run for {$periodStart} to {$periodEnd}.\n\n" .
        "Error: {$e->getMessage()}\n\n" .
        "Please create the pay run manually at: https://staff.vapeshed.co.nz/payroll-process.php"
    );

    exit(1);
}

// ============================================================================
// SEND SUCCESS NOTIFICATION
// ============================================================================

$duration = round(microtime(true) - $startTime, 2);

$emailBody = <<<EMAIL
✅ Payroll Run Auto-Created

A new payroll run has been automatically created:

Run Number:    #{$runNumber}
Run ID:        {$runId}
Pay Period:    {$periodStart} to {$periodEnd}
Payment Date:  {$paymentDate}

Next Steps:
1. Go to: https://staff.vapeshed.co.nz/payroll-process.php?run_id={$runId}
2. Click "Load Payroll" to import timesheets from Deputy
3. Review bonuses and hours
4. Push to Xero when ready

⏱️ Completed in {$duration} seconds

---
This is an automated message from the CIS Payroll System.
EMAIL;

send_notification(
    $config['notification_emails'],
    "✅ Payroll Run Created - Week of {$periodStart}",
    $emailBody
);

cron_log("Notification sent to: " . implode(', ', $config['notification_emails']));
cron_log("========================================");
cron_log("Payroll Auto-Start Completed Successfully");
cron_log("Duration: {$duration}s");
cron_log("========================================");

exit(0);

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get PDO connection
 */
function get_pdo_connection(): PDO {
    $host = '127.0.0.1';
    $dbname = 'jcepnzzkmj';
    $username = 'jcepnzzkmj';
    $password = 'wprKh9Jq63';

    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    return $pdo;
}

/**
 * Send email notification
 */
function send_notification(array $recipients, string $subject, string $body): void {
    $headers = [
        'From: CIS Payroll System <noreply@vapeshed.co.nz>',
        'Reply-To: payroll@vapeshed.co.nz',
        'X-Mailer: PHP/' . phpversion(),
        'Content-Type: text/plain; charset=UTF-8'
    ];

    foreach ($recipients as $email) {
        $sent = mail($email, $subject, $body, implode("\r\n", $headers));

        if ($sent) {
            cron_log("Email sent to: {$email}");
        } else {
            cron_log("Failed to send email to: {$email}", 'WARNING');
        }
    }
}

/**
 * Payroll logging function (if not already available)
 */
if (!function_exists('payroll_log')) {
    function payroll_log(string $level, string $message, array $context = []): void {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO payroll_audit_log (created_at, level, message, ctx_json)
                VALUES (NOW(), ?, ?, ?)
            ");
            $stmt->execute([$level, $message, json_encode($context)]);
        } catch (Exception $e) {
            cron_log("Failed to write to payroll_audit_log: " . $e->getMessage(), 'WARNING');
        }
    }
}
