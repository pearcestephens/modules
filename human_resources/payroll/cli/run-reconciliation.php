<?php
/**
 * Payroll Reconciliation CLI
 *
 * Runs Deputy vs Xero reconciliation for specified date range.
 *
 * Usage:
 *   php cli/run-reconciliation.php --start=2025-01-01 --end=2025-01-07
 *
 * @package HumanResources\Payroll\CLI
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/env-loader.php';

$pdo = makeDbConnection();

require_once __DIR__ . '/../services/ReconciliationService.php';

$options = getopt('', ['start:', 'end:', 'help']);

if (isset($options['help']) || !isset($options['start']) || !isset($options['end'])) {
    echo <<<HELP
Payroll Reconciliation CLI

Usage:
  php run-reconciliation.php --start=YYYY-MM-DD --end=YYYY-MM-DD

Options:
  --start=DATE    Start date (required)
  --end=DATE      End date (required)
  --help          Show this help

Example:
  php run-reconciliation.php --start=2025-01-01 --end=2025-01-07

HELP;
    exit(isset($options['help']) ? 0 : 1);
}

$start = $options['start'];
$end = $options['end'];

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    fwrite(STDERR, "Error: Dates must be in YYYY-MM-DD format\n");
    exit(1);
}

echo "Running reconciliation from {$start} to {$end}...\n";

$service = new ReconciliationService($pdo);

try {
    $variances = $service->compareDeputyToXero($start, $end);

    if (empty($variances)) {
        echo "✅ No variances found - Deputy and Xero match perfectly\n";
    } else {
        echo "⚠️  Found " . count($variances) . " variance(s):\n\n";
        foreach ($variances as $variance) {
            echo "  Staff: {$variance['staff_name']}\n";
            echo "  Deputy Hours: {$variance['deputy_hours']}\n";
            echo "  Xero Hours: {$variance['xero_hours']}\n";
            echo "  Difference: {$variance['difference']} hours\n";
            echo "  ---\n";
        }
    }

    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
