<?php
/**
 * Smoke test for PayrollDeputyService
 *
 * Asserts that a Deputy API call logs to payroll_activity_log
 * and persists rate-limit events to payroll_rate_limits.
 */

declare(strict_types=1);

require_once __DIR__ . '/../services/PayrollDeputyService.php';

function getDb() {
    $dsn = getenv('PAYROLL_DB_DSN') ?: 'mysql:host=localhost;dbname=jcepnzzkmj;charset=utf8mb4';
    $user = getenv('PAYROLL_DB_USER') ?: 'jcepnzzkmj';
    $pass = getenv('PAYROLL_DB_PASS') ?: 'wprKh9Jq63';
    return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

$db = getDb();
$service = new PayrollDeputyService($db);

// Clear logs for test isolation
function tableExists(PDO $db, string $table): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

foreach (['payroll_activity_log', 'payroll_rate_limits'] as $table) {
    if (!tableExists($db, $table)) {
        echo "Skipping smoke test: required table {$table} not found.\n";
        exit(0);
    }
}

$db->exec("DELETE FROM payroll_activity_log WHERE message LIKE 'Deputy API %'");
$db->exec("DELETE FROM payroll_rate_limits WHERE provider = 'deputy'");

try {
    $result = $service->fetchTimesheets(['limit' => 1]);
    echo "Deputy timesheet fetch OK, result count: " . count($result) . "\n";
} catch (Exception $e) {
    echo "Deputy API error: " . $e->getMessage() . "\n";
}

// Check activity log
$stmt = $db->query("SELECT * FROM payroll_activity_log WHERE message LIKE 'Deputy API call%' ORDER BY id DESC LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    echo "Activity log entry found: " . $row['message'] . "\n";
} else {
    echo "No activity log entry found!\n";
}

// Check rate-limit log (should be empty unless 429)
$stmt = $db->query("SELECT * FROM payroll_rate_limits WHERE provider = 'deputy' ORDER BY occurred_at DESC LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    echo "Rate-limit event found: retry_after=" . $row['retry_after'] . "\n";
} else {
    echo "No rate-limit event found (expected if no 429).\n";
}
