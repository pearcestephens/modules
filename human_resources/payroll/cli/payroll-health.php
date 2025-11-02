<?php
/**
 * Payroll Health Check CLI
 *
 * Comprehensive health diagnostics for payroll module.
 *
 * Usage:
 *   php cli/payroll-health.php
 *
 * @package HumanResources\Payroll\CLI
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../config/env-loader.php';

$host = env('DB_HOST', '127.0.0.1');
$port = env('DB_PORT', '3306');
$name = env('DB_DATABASE', 'jcepnzzkmj');
$user = env('DB_USERNAME', 'jcepnzzkmj');
$pass = env('DB_PASSWORD', '');

if (empty($pass)) {
    $pass = 'wprKh9Jq63';
}

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "❌ Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║            PAYROLL MODULE HEALTH CHECK                        ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// System Info
echo "📊 System Information:\n";
echo "  PHP Version: " . PHP_VERSION . "\n";
echo "  Timestamp: " . date('Y-m-d H:i:s T') . "\n";
echo "  Server: " . gethostname() . "\n\n";

// Database Connectivity
echo "🔌 Database Connectivity:\n";
try {
    $stmt = $pdo->query('SELECT 1');
    $result = $stmt->fetchColumn();
    echo "  ✅ Database ping: " . ($result === 1 ? 'OK' : 'FAILED') . "\n";
} catch (PDOException $e) {
    echo "  ❌ Database ping: FAILED - " . $e->getMessage() . "\n";
}

// Check Auth Flag (mock for now - would check actual config)
echo "\n🔐 Authentication Status:\n";
$authFlagFile = __DIR__ . '/../../config/payroll_auth_enabled.flag';
if (file_exists($authFlagFile)) {
    echo "  ✅ Auth flag file exists\n";
    echo "  📍 Location: {$authFlagFile}\n";
} else {
    echo "  ⚠️  Auth flag file not found (defaults to disabled)\n";
}

// Table Checks
echo "\n📋 Database Tables:\n";

$tables = [
    'deputy_timesheets',
    'payroll_activity_log',
    'payroll_rate_limits',
    'payroll_auth_audit_log',
    'payroll_runs',
    'payroll_snapshots',
];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM {$table}");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = number_format((int)$row['cnt']);
        echo "  ✅ {$table}: {$count} rows\n";
    } catch (PDOException $e) {
        echo "  ❌ {$table}: NOT FOUND\n";
    }
}

// Service Checks
echo "\n🛠️  Services:\n";

$services = [
    'PayrollDeputyService' => 'services/PayrollDeputyService.php',
    'PayrollXeroService' => 'services/PayrollXeroService.php',
    'ReconciliationService' => 'services/ReconciliationService.php',
    'HttpRateLimitReporter' => 'services/HttpRateLimitReporter.php',
    'PayrollAuthAuditService' => 'services/PayrollAuthAuditService.php',
];

foreach ($services as $name => $path) {
    $fullPath = __DIR__ . '/../' . $path;
    if (file_exists($fullPath)) {
        echo "  ✅ {$name}\n";
    } else {
        echo "  ❌ {$name}: NOT FOUND\n";
    }
}

// Health Endpoint Check
echo "\n🌐 Health Endpoint:\n";
$healthFile = __DIR__ . '/../health/index.php';
if (file_exists($healthFile)) {
    echo "  ✅ /health endpoint exists\n";
    echo "  📍 Location: {$healthFile}\n";
} else {
    echo "  ❌ /health endpoint: NOT FOUND\n";
}

// Recent Activity
echo "\n📈 Recent Activity (Last 24 hours):\n";
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt 
        FROM payroll_activity_log 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  Activity Log Entries: " . number_format((int)$row['cnt']) . "\n";
} catch (PDOException $e) {
    echo "  Activity Log: UNAVAILABLE\n";
}

try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt 
        FROM payroll_rate_limits 
        WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  Rate Limit Events: " . number_format((int)$row['cnt']) . "\n";
} catch (PDOException $e) {
    echo "  Rate Limits: UNAVAILABLE\n";
}

try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt 
        FROM payroll_auth_audit_log 
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  Auth Audit Events: " . number_format((int)$row['cnt']) . "\n";
} catch (PDOException $e) {
    echo "  Auth Audit: UNAVAILABLE\n";
}

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    HEALTH CHECK COMPLETE                       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";

exit(0);
