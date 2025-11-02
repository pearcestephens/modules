<?php
/**
 * Payroll Rate Limit Reporter CLI
 *
 * Display rate limit telemetry for Deputy and Xero integrations.
 *
 * Usage:
 *   php cli/rate-limit-report.php --days=7
 *
 * @package HumanResources\Payroll\CLI
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/env-loader.php';

$pdo = makeDbConnection();

$options = getopt('', ['days:', 'service:', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Rate Limit Reporter

Usage:
  php rate-limit-report.php [OPTIONS]

Options:
  --days=N         Show last N days (default: 7)
  --service=NAME   Filter by service (deputy, xero)
  --help           Show this help

Examples:
  php rate-limit-report.php --days=7
  php rate-limit-report.php --service=deputy --days=30

HELP;
    exit(0);
}

$days = isset($options['days']) ? (int)$options['days'] : 7;
$service = $options['service'] ?? null;

$sql = 'SELECT 
    service,
    endpoint,
    http_status,
    retry_after_sec,
    occurred_at
FROM payroll_rate_limits
WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';

$params = [$days];

if ($service) {
    $sql .= ' AND service = ?';
    $params[] = $service;
}

$sql .= ' ORDER BY occurred_at DESC';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($rows)) {
        echo "✅ No rate limits in the last {$days} day(s)\n";
        exit(0);
    }
    
    echo "Rate Limit Events (last {$days} day(s)):\n";
    echo str_repeat('=', 80) . "\n\n";
    
    foreach ($rows as $row) {
        echo "Service: {$row['service']}\n";
        echo "Endpoint: {$row['endpoint']}\n";
        echo "Status: {$row['http_status']}\n";
        echo "Retry After: " . ($row['retry_after_sec'] ?? 'N/A') . " seconds\n";
        echo "Occurred: {$row['occurred_at']}\n";
        echo str_repeat('-', 80) . "\n";
    }
    
    echo "\nTotal events: " . count($rows) . "\n";
    
} catch (\Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
