#!/usr/bin/env php
<?php
/**
 * Staff Mapping Generator - Xero Employees → Vend Customers
 *
 * Discovers staff from both Xero PayrollNZ and Vend, then creates mappings
 * based on email address matching (primary) and name matching (fallback).
 *
 * Usage:
 *   php generate-staff-mappings.php --dry=1       # Preview only
 *   php generate-staff-mappings.php --dry=0       # Write to database
 *   php generate-staff-mappings.php --export=csv  # Export to CSV
 *
 * @package CIS\StaffAccounts\CLI
 * @version 1.0.0
 */

declare(strict_types=1);

// Set paths - we're in modules/staff-accounts/cli/
// __DIR__ = /path/to/modules/staff-accounts/cli
// We need /path/to/modules
$modulesRoot = dirname(dirname(__DIR__));

// Set document root for Database class config loading
$_SERVER['DOCUMENT_ROOT'] = dirname($modulesRoot);

// Load base database class directly (minimal dependencies)
require_once $modulesRoot . '/base/Database.php';
require_once $modulesRoot . '/base/DatabasePDO.php';

use CIS\Base\Database;

// Parse arguments
$options = getopt('', ['dry:', 'export:', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Staff Mapping Generator

Matches Xero employees to Vend customers and populates cis_staff_vend_map table.

Usage:
  php generate-staff-mappings.php [OPTIONS]

Options:
  --dry=1         Preview only (default: 1)
  --dry=0         Write to database
  --export=csv    Export mapping to CSV file
  --help          Show this help

Examples:
  php generate-staff-mappings.php --dry=1
  php generate-staff-mappings.php --dry=0
  php generate-staff-mappings.php --export=csv --dry=0

HELP;
    exit(0);
}

$dryRun = !isset($options['dry']) || $options['dry'] === '1';
$exportCsv = isset($options['export']) && $options['export'] === 'csv';

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║         STAFF MAPPING GENERATOR - Xero → Vend                ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

if ($dryRun) {
    echo "🔍 MODE: DRY RUN (Preview only - no database writes)\n\n";
} else {
    echo "✍️  MODE: LIVE (Will write to cis_staff_vend_map table)\n\n";
}

$runId = 'staff_mapping_' . date('Ymd_His');
$logFile = "/home/master/applications/jcepnzzkmj/public_html/modules/private_html/ai_runs/payroll/20251103/{$runId}.json";

// Initialize log
$log = [
    'run_id' => $runId,
    'started_at' => date('Y-m-d H:i:s'),
    'dry_run' => $dryRun,
    'steps' => []
];

function logStep(string $step, array &$log): void {
    $log['steps'][] = ['time' => date('H:i:s'), 'step' => $step];
    echo "→ {$step}\n";
}

try {
    $pdo = Database::pdo();

    // STEP 1: Get Xero OAuth2 credentials
    logStep("Step 1: Retrieving Xero credentials from configuration...", $log);

    $stmt = $pdo->prepare("SELECT config_value FROM configuration WHERE config_label = 'xero_auth2'");
    $stmt->execute();
    $xeroAuthJson = $stmt->fetchColumn();

    if (!$xeroAuthJson) {
        throw new Exception("Xero OAuth2 credentials not found in configuration table");
    }

    $xeroAuth = json_decode($xeroAuthJson, true);
    $xeroToken = $xeroAuth['token'] ?? null;
    $xeroTenantId = $xeroAuth['tenant_id'] ?? null;

    if (!$xeroToken || !$xeroTenantId) {
        throw new Exception("Invalid Xero credentials structure");
    }

    echo "  ✓ Xero token: " . substr($xeroToken, 0, 20) . "...\n";
    echo "  ✓ Tenant ID: {$xeroTenantId}\n\n";

    // STEP 2: Fetch Xero employees
    logStep("Step 2: Fetching employees from Xero PayrollNZ API...", $log);

    $xeroUrl = "https://api.xero.com/payroll.xro/1.0/Employees";

    $ch = curl_init($xeroUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $xeroToken,
            'Xero-tenant-id: ' . $xeroTenantId,
            'Accept: application/json'
        ]
    ]);

    $xeroResponse = curl_exec($ch);
    $xeroHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($xeroHttpCode !== 200) {
        throw new Exception("Xero API error: HTTP {$xeroHttpCode} - {$xeroResponse}");
    }

    $xeroData = json_decode($xeroResponse, true);
    $xeroEmployees = $xeroData['Employees'] ?? [];

    echo "  ✓ Fetched " . count($xeroEmployees) . " employees from Xero\n\n";

    $log['xero_employees_count'] = count($xeroEmployees);

    // STEP 3: Fetch Vend customers
    logStep("Step 3: Fetching customers from Vend API...", $log);

    $stmt = $pdo->prepare("SELECT config_value FROM configuration WHERE config_label = 'vend_access_token'");
    $stmt->execute();
    $vendToken = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT config_value FROM configuration WHERE config_label = 'vend_domain_prefix'");
    $stmt->execute();
    $vendDomain = $stmt->fetchColumn();

    if (!$vendToken || !$vendDomain) {
        throw new Exception("Vend credentials not found in configuration table");
    }

    echo "  ✓ Vend domain: {$vendDomain}.vendhq.com\n";

    // Fetch Vend customers (paginated)
    $vendCustomers = [];
    $page = 1;
    $perPage = 200;

    do {
        $vendUrl = "https://{$vendDomain}.vendhq.com/api/2.0/customers?page_size={$perPage}&page={$page}";

        $ch = curl_init($vendUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $vendToken,
                'Accept: application/json'
            ]
        ]);

        $vendResponse = curl_exec($ch);
        $vendHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($vendHttpCode !== 200) {
            throw new Exception("Vend API error: HTTP {$vendHttpCode} - {$vendResponse}");
        }

        $vendData = json_decode($vendResponse, true);
        $pageCustomers = $vendData['data'] ?? [];
        $vendCustomers = array_merge($vendCustomers, $pageCustomers);

        $hasMore = ($vendData['version']['max'] ?? 0) > count($vendCustomers);
        $page++;

        echo "  → Fetched page {$page} (" . count($pageCustomers) . " customers)...\n";

        usleep(100000); // Rate limit: 100ms between requests

    } while ($hasMore && $page < 50); // Safety limit: 50 pages

    echo "  ✓ Fetched " . count($vendCustomers) . " customers from Vend\n\n";

    $log['vend_customers_count'] = count($vendCustomers);

    // STEP 4: Match employees to customers
    logStep("Step 4: Matching employees to customers...", $log);

    $mappings = [];
    $unmatchedEmployees = [];

    foreach ($xeroEmployees as $employee) {
        $employeeId = $employee['EmployeeID'] ?? null;
        $employeeEmail = strtolower(trim($employee['Email'] ?? ''));
        $employeeFirstName = $employee['FirstName'] ?? '';
        $employeeLastName = $employee['LastName'] ?? '';

        if (!$employeeId) continue;

        // Try to match by email (primary method)
        $matchedCustomer = null;

        if ($employeeEmail) {
            foreach ($vendCustomers as $customer) {
                $customerEmail = strtolower(trim($customer['email'] ?? ''));
                if ($customerEmail && $customerEmail === $employeeEmail) {
                    $matchedCustomer = $customer;
                    break;
                }
            }
        }

        // Fallback: match by name (first + last)
        if (!$matchedCustomer && $employeeFirstName && $employeeLastName) {
            $employeeFullName = strtolower(trim("{$employeeFirstName} {$employeeLastName}"));

            foreach ($vendCustomers as $customer) {
                $customerFirstName = trim($customer['first_name'] ?? '');
                $customerLastName = trim($customer['last_name'] ?? '');
                $customerFullName = strtolower(trim("{$customerFirstName} {$customerLastName}"));

                if ($customerFullName === $employeeFullName) {
                    $matchedCustomer = $customer;
                    break;
                }
            }
        }

        if ($matchedCustomer) {
            $mappings[] = [
                'xero_employee_id' => $employeeId,
                'vend_customer_id' => $matchedCustomer['id'],
                'email' => $employeeEmail,
                'first_name' => $employeeFirstName,
                'last_name' => $employeeLastName,
                'match_method' => $employeeEmail ? 'email' : 'name',
                'vend_customer_code' => $matchedCustomer['customer_code'] ?? null
            ];

            echo "  ✓ Matched: {$employeeFirstName} {$employeeLastName} → Vend Customer #{$matchedCustomer['customer_code']}\n";
        } else {
            $unmatchedEmployees[] = [
                'xero_employee_id' => $employeeId,
                'email' => $employeeEmail,
                'first_name' => $employeeFirstName,
                'last_name' => $employeeLastName
            ];

            echo "  ✗ NO MATCH: {$employeeFirstName} {$employeeLastName} ({$employeeEmail})\n";
        }
    }

    echo "\n";
    echo "  ✓ Matched: " . count($mappings) . " employees\n";
    echo "  ✗ Unmatched: " . count($unmatchedEmployees) . " employees\n\n";

    $log['matched_count'] = count($mappings);
    $log['unmatched_count'] = count($unmatchedEmployees);
    $log['mappings'] = $mappings;
    $log['unmatched'] = $unmatchedEmployees;

    // STEP 5: Write to database (if not dry run)
    if (!$dryRun && count($mappings) > 0) {
        logStep("Step 5: Writing mappings to cis_staff_vend_map...", $log);

        $stmt = $pdo->prepare("
            INSERT INTO cis_staff_vend_map (xero_employee_id, vend_customer_id, email, first_name, last_name)
            VALUES (:xero_employee_id, :vend_customer_id, :email, :first_name, :last_name)
            ON DUPLICATE KEY UPDATE
                vend_customer_id = VALUES(vend_customer_id),
                email = VALUES(email),
                first_name = VALUES(first_name),
                last_name = VALUES(last_name)
        ");

        $written = 0;
        foreach ($mappings as $mapping) {
            $stmt->execute([
                ':xero_employee_id' => $mapping['xero_employee_id'],
                ':vend_customer_id' => $mapping['vend_customer_id'],
                ':email' => $mapping['email'],
                ':first_name' => $mapping['first_name'],
                ':last_name' => $mapping['last_name']
            ]);
            $written++;
        }

        echo "  ✓ Wrote {$written} mappings to database\n\n";
        $log['database_writes'] = $written;
    } else {
        echo "  ⏭  Skipped database writes (dry run mode)\n\n";
    }

    // STEP 6: Export CSV (if requested)
    if ($exportCsv) {
        logStep("Step 6: Exporting to CSV...", $log);

        $csvFile = "/home/master/applications/jcepnzzkmj/public_html/modules/private_html/ai_runs/payroll/20251103/staff_mappings_{$runId}.csv";
        $fp = fopen($csvFile, 'w');

        // Header
        fputcsv($fp, ['xero_employee_id', 'vend_customer_id', 'email', 'first_name', 'last_name', 'match_method', 'vend_customer_code']);

        // Matched rows
        foreach ($mappings as $mapping) {
            fputcsv($fp, $mapping);
        }

        fclose($fp);

        echo "  ✓ Exported to: {$csvFile}\n\n";
        $log['csv_export'] = $csvFile;

        // Also export unmatched
        if (count($unmatchedEmployees) > 0) {
            $unmatchedCsv = "/home/master/applications/jcepnzzkmj/public_html/modules/private_html/ai_runs/payroll/20251103/staff_unmatched_{$runId}.csv";
            $fp = fopen($unmatchedCsv, 'w');

            fputcsv($fp, ['xero_employee_id', 'email', 'first_name', 'last_name', 'action_required']);

            foreach ($unmatchedEmployees as $unmatched) {
                fputcsv($fp, [
                    $unmatched['xero_employee_id'],
                    $unmatched['email'],
                    $unmatched['first_name'],
                    $unmatched['last_name'],
                    'Create Vend customer account or manually map'
                ]);
            }

            fclose($fp);

            echo "  ✓ Unmatched employees exported to: {$unmatchedCsv}\n\n";
            $log['unmatched_csv'] = $unmatchedCsv;
        }
    }

    // Final summary
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║                        SUMMARY                                ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n\n";
    echo "  Xero Employees:    " . count($xeroEmployees) . "\n";
    echo "  Vend Customers:    " . count($vendCustomers) . "\n";
    echo "  Matched:           " . count($mappings) . " (" . round(count($mappings) / max(count($xeroEmployees), 1) * 100, 1) . "%)\n";
    echo "  Unmatched:         " . count($unmatchedEmployees) . "\n";

    if (!$dryRun) {
        echo "  Database writes:   " . ($log['database_writes'] ?? 0) . "\n";
    }

    if ($exportCsv) {
        echo "  CSV exports:       " . (isset($log['csv_export']) ? 'Yes' : 'No') . "\n";
    }

    echo "\n";

    if (count($unmatchedEmployees) > 0) {
        echo "⚠️  ACTION REQUIRED:\n";
        echo "   " . count($unmatchedEmployees) . " employees need manual attention.\n";
        echo "   Either create Vend customer accounts for them, or manually map in database.\n\n";
    }

    $log['completed_at'] = date('Y-m-d H:i:s');
    $log['status'] = 'success';

    // Save log
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT));

    echo "📋 Log saved to: {$logFile}\n\n";

    exit(0);

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "   " . $e->getFile() . ":" . $e->getLine() . "\n\n";

    $log['error'] = $e->getMessage();
    $log['status'] = 'failed';
    $log['completed_at'] = date('Y-m-d H:i:s');

    if (isset($logFile)) {
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT));
    }

    exit(1);
}
