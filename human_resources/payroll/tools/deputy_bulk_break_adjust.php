<?php
declare(strict_types=1);

/**
 * Bulk-adjust Deputy timesheet break minutes to a minimum threshold (default 30 minutes),
 * skipping excluded Operational Units (default: Whangarei, Rotorua).
 *
 * Usage (CLI):
 *   php deputy_bulk_break_adjust.php --start=2025-11-01 --end=2025-11-07 [--min-break=30] [--dry-run=1] [--include-approved=0] [--exclude-ou="Whangarei,Rotorua"]
 *
 * Notes:
 * - Requires env: DEPUTY_API_BASE_URL or DEPUTY_ENDPOINT, and DEPUTY_API_TOKEN or DEPUTY_TOKEN
 * - By default, approved timesheets are skipped to avoid Deputy update errors; pass --include-approved=1 to attempt replacement logic.
 */

use PayrollModule\Services\DeputyApiClient;

// Bootstrap minimal
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Absolute paths for includes
$MODULE_ROOT = dirname(__DIR__, 3); // .../modules

// DB connection (optional, for enumerating employees)
$dbFile = $MODULE_ROOT . '/config/database.php';
if (file_exists($dbFile)) {
    require_once $dbFile; // should define $pdo
}

// Load API client directly (no composer autoload here)
require_once dirname(__DIR__) . '/services/DeputyApiClient.php';

// Parse CLI args
$args = [];
foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--([^=]+)=(.*)$/', $arg, $m)) {
        $args[$m[1]] = $m[2];
    }
}

function arg(string $key, $default = null) {
    global $args;
    return $args[$key] ?? $default;
}

$startDate = (string) arg('start', '');
$endDate   = (string) arg('end', $startDate);
$minBreak  = (int) arg('min-break', 30);
$dryRun    = (int) arg('dry-run', 1) === 1;
$includeApproved = (int) arg('include-approved', 0) === 1;
$excludeOuCsv = (string) arg('exclude-ou', 'Whangarei,Rotorua');
$excludeOus = array_filter(array_map('trim', explode(',', $excludeOuCsv)));

if ($startDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    fwrite(STDERR, "ERROR: --start=YYYY-MM-DD is required\n");
    exit(1);
}
if ($endDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = $startDate;
}

$startTs = strtotime($startDate . ' 00:00:00');
$endTs   = strtotime($endDate . ' 00:00:00');
if ($startTs === false || $endTs === false || $endTs < $startTs) {
    fwrite(STDERR, "ERROR: Invalid date range\n");
    exit(1);
}

// Build list of deputy employee IDs
$employeeIds = [];
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SELECT deputy_id FROM users WHERE deputy_id IS NOT NULL AND deputy_id <> 0");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) ($row['deputy_id'] ?? 0);
            if ($id > 0) { $employeeIds[] = $id; }
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "WARNING: Failed to enumerate employees from DB: {$e->getMessage()}\n");
    }
}
// Fallback: allow single employee override
$onlyEmp = (int) arg('employee', 0);
if ($onlyEmp > 0) { $employeeIds = [$onlyEmp]; }
if (empty($employeeIds)) {
    fwrite(STDERR, "ERROR: No employees found (users.deputy_id). Pass --employee=ID to target a single Deputy ID.\n");
    exit(1);
}

// Initialize API client
$client = new DeputyApiClient();

// Iterate dates
$totalChecked = 0;
$totalUpdated = 0;
$totalSkipped = 0;
$details = [];

for ($dayTs = $startTs; $dayTs <= $endTs; $dayTs += 86400) {
    $day = date('Y-m-d', $dayTs);
    foreach ($employeeIds as $empId) {
        try {
            $rows = $client->fetchTimesheetsForDate($empId, $day);
        } catch (Throwable $e) {
            fwrite(STDERR, "WARN: fetch failed for employee {$empId} on {$day}: {$e->getMessage()}\n");
            continue;
        }
        foreach ($rows as $r) {
            $totalChecked++;
            $tsId = (int)($r['Id'] ?? 0);
            $start = (int)($r['StartTime'] ?? 0);
            $end   = (int)($r['EndTime'] ?? 0);
            $ouId  = (int)($r['OperationalUnitObject']['Id'] ?? ($r['OperationalUnit'] ?? 0));
            $ouName= (string)($r['OperationalUnitObject']['Name'] ?? ($r['OperationalUnitObject']['DisplayName'] ?? ''));
            if ($ouName === '' && $ouId > 0) {
                try {
                    $ou = $client->fetchOperationalUnit($ouId);
                    $ouName = (string)($ou['Name'] ?? ($ou['DisplayName'] ?? ''));
                } catch (Throwable $e) {
                    // ignore; fallback to empty name
                }
            }
            $approved = !empty($r['TimeApproved']);
            $breakSec = (int)($r['Breaks'] ?? 0);
            $breakMin = (int) ceil($breakSec / 60);

            // Exclusion: OU names
            $isExcludedOu = false;
            foreach ($excludeOus as $ename) {
                if ($ename !== '' && preg_match('/' . preg_quote($ename, '/') . '/i', $ouName)) {
                    $isExcludedOu = true; break;
                }
            }
            if ($isExcludedOu) { $totalSkipped++; continue; }

            // Skip approved unless explicitly allowed
            if ($approved && !$includeApproved) { $totalSkipped++; continue; }

            // Skip if already >= min
            if ($breakMin >= $minBreak) { $totalSkipped++; continue; }

            // Prepare update: set break to minBreak
            $newBreakMin = $minBreak;
            $details[] = [
                'date' => $day,
                'employee' => $empId,
                'timesheetId' => $tsId,
                'ou' => $ouName . " (#{$ouId})",
                'from' => $breakMin,
                'to' => $newBreakMin,
                'approved' => $approved ? 1 : 0,
            ];

            if ($dryRun) { continue; }

            try {
                // Update requires full details
                $client->updateTimesheet($tsId, $start, $end, $newBreakMin, $ouId, 'Bulk break minimum adjustment');
                $totalUpdated++;
            } catch (Throwable $e) {
                fwrite(STDERR, "ERROR: update failed for TS {$tsId} (emp {$empId}, {$day}): {$e->getMessage()}\n");
            }
        }
    }
}

// Output summary
echo "Bulk Break Adjust Summary\n";
echo "Range: {$startDate} to {$endDate}\n";
echo "Min Break: {$minBreak} min\n";
echo "Exclude OUs: " . implode(', ', $excludeOus) . "\n";
echo "Include Approved: " . ($includeApproved ? 'YES' : 'NO') . "\n";
echo "Dry Run: " . ($dryRun ? 'YES' : 'NO') . "\n";
echo "Checked: {$totalChecked}, Skipped: {$totalSkipped}, Updated: {$totalUpdated}\n\n";

if (!empty($details)) {
    echo "Planned Changes:" . PHP_EOL;
    foreach ($details as $d) {
        echo sprintf(
            " %s emp:%d ts:%d OU:%s break %d -> %d%s\n",
            $d['date'], $d['employee'], $d['timesheetId'], $d['ou'], $d['from'], $d['to'],
            $d['approved'] ? ' (approved)' : ''
        );
    }
}

exit(0);
