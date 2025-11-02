<?php
/**
 * Payroll Activity Log Viewer CLI
 *
 * Display recent payroll activity log entries with filtering.
 *
 * Usage:
 *   php cli/activity-log.php --hours=24 --level=error
 *
 * @package HumanResources\Payroll\CLI
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/env-loader.php';

$pdo = makeDbConnection();

$options = getopt('', ['hours:', 'level:', 'category:', 'limit:', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Payroll Activity Log Viewer

Usage:
  php activity-log.php [OPTIONS]

Options:
  --hours=N        Show last N hours (default: 24)
  --level=LEVEL    Filter by level (info, warning, error)
  --category=CAT   Filter by category (deputy, xero, recon)
  --limit=N        Max entries to show (default: 50)
  --help           Show this help

Examples:
  php activity-log.php --hours=24
  php activity-log.php --level=error --hours=72
  php activity-log.php --category=deputy --limit=100

HELP;
    exit(0);
}

$hours = isset($options['hours']) ? (int)$options['hours'] : 24;
$level = $options['level'] ?? null;
$category = $options['category'] ?? null;
$limit = isset($options['limit']) ? (int)$options['limit'] : 50;

$sql = 'SELECT
    log_level,
    category,
    action,
    message,
    details,
    created_at
FROM payroll_activity_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)';

$params = [$hours];

if ($level) {
    $sql .= ' AND log_level = ?';
    $params[] = strtoupper($level);
}

if ($category) {
    $sql .= ' AND category LIKE ?';
    $params[] = '%' . $category . '%';
}

$sql .= ' ORDER BY created_at DESC LIMIT ?';
$params[] = $limit;

try {
    $stmt = $pdo->prepare($sql);

    foreach ($params as $i => $val) {
        $stmt->bindValue($i + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo "No activity log entries found for the specified criteria\n";
        exit(0);
    }

    echo "Payroll Activity Log (last {$hours} hour(s)):\n";
    echo str_repeat('=', 100) . "\n\n";

    foreach ($rows as $row) {
        $levelColor = match($row['log_level']) {
            'ERROR' => "\033[31m", // Red
            'WARNING' => "\033[33m", // Yellow
            'INFO' => "\033[32m", // Green
            default => ""
        };
        $reset = "\033[0m";

        echo "{$levelColor}[{$row['log_level']}]{$reset} ";
        echo "{$row['created_at']} | ";
        echo "{$row['category']} | ";
        echo "{$row['action']}\n";
        echo "  Message: {$row['message']}\n";

        if ($row['details']) {
            $details = json_decode($row['details'], true);
            if ($details) {
                echo "  Details: " . json_encode($details, JSON_PRETTY_PRINT) . "\n";
            }
        }

        echo str_repeat('-', 100) . "\n";
    }

    echo "\nTotal entries: " . count($rows) . "\n";

} catch (\Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
