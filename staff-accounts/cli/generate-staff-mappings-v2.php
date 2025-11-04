#!/usr/bin/env php
<?php
/**
 * Generate Staff Mappings v2 - Xero Employees → Vend Customers
 *
 * Purpose: Populate cis_staff_vend_map table with employee mappings
 *
 * This version uses the proper Xero SDK infrastructure:
 * - Uses PayrollNzApi directly (loaded via xero-init.php)
 * - Matches by email (primary) or name (fallback)
 * - Writes mappings to cis_staff_vend_map table
 * - Exports unmatched employees to CSV for manual review
 *
 * Usage:
 *   php generate-staff-mappings-v2.php --dry=1           (preview without writing)
 *   php generate-staff-mappings-v2.php --dry=0           (execute write)
 *   php generate-staff-mappings-v2.php --dry=0 --export=csv
 *
 * @package CIS\Modules\StaffAccounts\CLI
 * @version 2.0.0
 */

declare(strict_types=1);

// === BOOTSTRAP SETUP (following sync-xero-payroll.php pattern) ===
if (!defined('CIS_CLI_MODE')) {
    define('CIS_CLI_MODE', true);
}

// Load bootstrap
require_once __DIR__ . '/../bootstrap.php';

// CRITICAL: Load Xero SDK manually for CLI (normally skipped in CLI mode)
if (!isset($payrollNzApi)) {
    require_once ROOT_PATH . '/assets/services/xero-sdk/xero-init.php';
}

use CIS\Modules\Base\Database;

// === ARGUMENT PARSING ===
$options = getopt('', ['dry:', 'export:', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Generate Staff Mappings v2

Usage:
  php generate-staff-mappings-v2.php [OPTIONS]

Options:
  --dry=1|0        Dry run (1) or execute (0) - default: 1
  --export=csv     Export unmatched employees to CSV
  --help           Show this help message

Examples:
  php generate-staff-mappings-v2.php --dry=1
  php generate-staff-mappings-v2.php --dry=0
  php generate-staff-mappings-v2.php --dry=0 --export=csv

HELP;
    exit(0);
}

$dryRun = isset($options['dry']) ? (int)$options['dry'] === 1 : true;
$exportCsv = isset($options['export']) && $options['export'] === 'csv';

echo "╔═══════════════════════════════════════════════════════════════╗" . PHP_EOL;
echo "║    STAFF MAPPING GENERATOR v2 - Xero → Vend (using SDK)     ║" . PHP_EOL;
echo "╚═══════════════════════════════════════════════════════════════╝" . PHP_EOL;
echo PHP_EOL;
echo "Mode: " . ($dryRun ? "DRY RUN (preview only)" : "LIVE EXECUTION") . PHP_EOL;
echo PHP_EOL;

// === DATABASE CONNECTION ===
try {
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!$pdo) {
        Database::init();
        $pdo = Database::pdo();
    }
    echo "✅ Database connected" . PHP_EOL;
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// === VERIFY XERO SDK LOADED ===
if (!isset($payrollNzApi)) {
    echo "❌ Xero SDK not loaded. Check xero-init.php" . PHP_EOL;
    exit(1);
}

if (!isset($xeroTenantId)) {
    echo "❌ Xero Tenant ID not configured. Check xero-init.php" . PHP_EOL;
    exit(1);
}

echo "✅ Xero SDK loaded (tenant: {$xeroTenantId})" . PHP_EOL;

// === VEND CREDENTIALS ===
try {
    $stmt = $pdo->prepare("SELECT config_value FROM configuration WHERE config_label = 'vend_access_token'");
    $stmt->execute();
    $vendToken = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT config_value FROM configuration WHERE config_label = 'vend_domain_prefix'");
    $stmt->execute();
    $vendDomain = $stmt->fetchColumn();

    if (!$vendToken || !$vendDomain) {
        throw new Exception("Vend credentials not found in configuration table");
    }

    echo "✅ Vend credentials loaded (domain: {$vendDomain})" . PHP_EOL;
    echo PHP_EOL;
} catch (Exception $e) {
    echo "❌ Failed to load Vend credentials: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// === FETCH XERO EMPLOYEES (using PayrollNZ API) ===
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;
echo "STEP 1: Fetching Xero employees via PayrollNZ API..." . PHP_EOL;
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;

$xeroEmployees = [];

try {
    // Use PayrollNzApi->getEmployees() method
    $employeesResponse = $payrollNzApi->getEmployees($xeroTenantId);

    if (!$employeesResponse || !method_exists($employeesResponse, 'getEmployees')) {
        throw new Exception("Invalid response from getEmployees() - no getEmployees() method");
    }

    $employees = $employeesResponse->getEmployees();

    if (!$employees || !is_array($employees)) {
        throw new Exception("getEmployees() returned invalid data");
    }

    // Convert SDK Employee objects to array format
    foreach ($employees as $emp) {
        // Handle both getEmployeeID and getEmployeeId naming variations
        $employeeId = method_exists($emp, 'getEmployeeID') ? $emp->getEmployeeID() :
                     (method_exists($emp, 'getEmployeeId') ? $emp->getEmployeeId() : null);

        $firstName = method_exists($emp, 'getFirstName') ? $emp->getFirstName() : '';
        $lastName = method_exists($emp, 'getLastName') ? $emp->getLastName() : '';
        $email = method_exists($emp, 'getEmail') ? $emp->getEmail() : '';

        // Handle nulls from API
        $firstName = $firstName ?? '';
        $lastName = $lastName ?? '';
        $email = $email ?? '';

        if (!$employeeId) {
            echo "  ⚠️  Skipping employee with no ID: {$firstName} {$lastName}" . PHP_EOL;
            continue;
        }

        $xeroEmployees[] = [
            'EmployeeID' => $employeeId,
            'FirstName' => $firstName,
            'LastName' => $lastName,
            'Email' => $email ? strtolower(trim($email)) : '',
            'FullName' => trim("{$firstName} {$lastName}")
        ];
    }

    echo "✅ Fetched " . count($xeroEmployees) . " employees from Xero" . PHP_EOL;

    // Show sample
    if (count($xeroEmployees) > 0) {
        $sample = $xeroEmployees[0];
        echo "   Sample: {$sample['FullName']} <{$sample['Email']}> (ID: {$sample['EmployeeID']})" . PHP_EOL;
    }
    echo PHP_EOL;

} catch (Exception $e) {
    echo "❌ Failed to fetch Xero employees: " . $e->getMessage() . PHP_EOL;
    echo "   " . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    exit(1);
}

// === FETCH VEND CUSTOMERS (paginated) ===
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;
echo "STEP 2: Fetching Vend customers (paginated)..." . PHP_EOL;
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;

$vendCustomers = [];
$page = 1;
$pageSize = 200;

try {
    while (true) {
        $vendUrl = "https://{$vendDomain}.vendhq.com/api/2.0/customers?page_size={$pageSize}&page={$page}";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $vendUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$vendToken}",
                'Accept: application/json',
                'User-Agent: CIS-StaffAccounts/1.0'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Vend API returned HTTP {$httpCode}: " . substr($response, 0, 200));
        }

        $data = json_decode($response, true);
        $pageCustomers = $data['data'] ?? [];

        if (empty($pageCustomers)) {
            break; // No more results
        }

        // Extract relevant fields
        foreach ($pageCustomers as $customer) {
            $vendCustomers[] = [
                'id' => $customer['id'] ?? '',
                'email' => strtolower(trim($customer['email'] ?? '')),
                'first_name' => trim($customer['first_name'] ?? ''),
                'last_name' => trim($customer['last_name'] ?? ''),
                'full_name' => trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))
            ];
        }

        echo "  Page {$page}: Fetched " . count($pageCustomers) . " customers (total: " . count($vendCustomers) . ")" . PHP_EOL;

        // Check if we have more pages
        $pagination = $data['version']['max'] ?? 0;
        if (count($pageCustomers) < $pageSize) {
            break; // Last page
        }

        $page++;
        usleep(250000); // 250ms rate limit
    }

    echo "✅ Fetched " . count($vendCustomers) . " customers from Vend" . PHP_EOL;
    echo PHP_EOL;

} catch (Exception $e) {
    echo "❌ Failed to fetch Vend customers: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// === MATCH EMPLOYEES TO CUSTOMERS ===
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;
echo "STEP 3: Matching Xero employees to Vend customers..." . PHP_EOL;
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;

$matches = [];
$unmatched = [];

// Build Vend lookup indexes
$vendByEmail = [];
$vendByName = [];

foreach ($vendCustomers as $customer) {
    if (!empty($customer['email'])) {
        $vendByEmail[$customer['email']] = $customer;
    }
    if (!empty($customer['full_name'])) {
        $vendByName[strtolower($customer['full_name'])] = $customer;
    }
}

// Match each Xero employee
foreach ($xeroEmployees as $emp) {
    $match = null;
    $matchMethod = null;

    // Primary: Email match
    if (!empty($emp['Email']) && isset($vendByEmail[$emp['Email']])) {
        $match = $vendByEmail[$emp['Email']];
        $matchMethod = 'email';
    }
    // Fallback: Name match
    elseif (!empty($emp['FullName']) && isset($vendByName[strtolower($emp['FullName'])])) {
        $match = $vendByName[strtolower($emp['FullName'])];
        $matchMethod = 'name';
    }

    if ($match) {
        $matches[] = [
            'xero_employee_id' => $emp['EmployeeID'],
            'vend_customer_id' => $match['id'],
            'email' => $emp['Email'],
            'first_name' => $emp['FirstName'],
            'last_name' => $emp['LastName'],
            'match_method' => $matchMethod
        ];
        echo "  ✅ {$emp['FullName']} → matched via {$matchMethod}" . PHP_EOL;
    } else {
        $unmatched[] = $emp;
        echo "  ⚠️  {$emp['FullName']} <{$emp['Email']}> → NO MATCH" . PHP_EOL;
    }
}

echo PHP_EOL;
echo "Match Summary:" . PHP_EOL;
echo "  Matched: " . count($matches) . PHP_EOL;
echo "  Unmatched: " . count($unmatched) . PHP_EOL;
echo PHP_EOL;

// === WRITE TO DATABASE ===
if (!$dryRun && !empty($matches)) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;
    echo "STEP 4: Writing mappings to cis_staff_vend_map..." . PHP_EOL;
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;

    try {
        $pdo->beginTransaction();

        // Clear existing mappings (optional - comment out if you want to preserve)
        // $pdo->exec("TRUNCATE TABLE cis_staff_vend_map");

        $stmt = $pdo->prepare("
            INSERT INTO cis_staff_vend_map
                (xero_employee_id, vend_customer_id, email, first_name, last_name)
            VALUES
                (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                vend_customer_id = VALUES(vend_customer_id),
                email = VALUES(email),
                first_name = VALUES(first_name),
                last_name = VALUES(last_name)
        ");

        $insertCount = 0;
        foreach ($matches as $match) {
            $stmt->execute([
                $match['xero_employee_id'],
                $match['vend_customer_id'],
                $match['email'],
                $match['first_name'],
                $match['last_name']
            ]);
            $insertCount++;
        }

        $pdo->commit();

        echo "✅ Wrote {$insertCount} mappings to database" . PHP_EOL;
        echo PHP_EOL;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "❌ Database write failed: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
} elseif ($dryRun) {
    echo "ℹ️  DRY RUN: Skipping database write" . PHP_EOL;
    echo "   Run with --dry=0 to write mappings" . PHP_EOL;
    echo PHP_EOL;
}

// === EXPORT UNMATCHED TO CSV ===
if ($exportCsv && !empty($unmatched)) {
    $csvFile = __DIR__ . "/unmatched_employees_" . date('Ymd_His') . ".csv";

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;
    echo "STEP 5: Exporting unmatched employees to CSV..." . PHP_EOL;
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;

    try {
        $fp = fopen($csvFile, 'w');

        // Write header
        fputcsv($fp, ['Xero Employee ID', 'First Name', 'Last Name', 'Email', 'Full Name']);

        // Write unmatched employees
        foreach ($unmatched as $emp) {
            fputcsv($fp, [
                $emp['EmployeeID'],
                $emp['FirstName'],
                $emp['LastName'],
                $emp['Email'],
                $emp['FullName']
            ]);
        }

        fclose($fp);

        echo "✅ Exported " . count($unmatched) . " unmatched employees to:" . PHP_EOL;
        echo "   {$csvFile}" . PHP_EOL;
        echo PHP_EOL;

    } catch (Exception $e) {
        echo "❌ CSV export failed: " . $e->getMessage() . PHP_EOL;
    }
}

// === VERIFICATION ===
if (!$dryRun) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;
    echo "VERIFICATION: Checking cis_staff_vend_map table..." . PHP_EOL;
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;

    $stmt = $pdo->query("SELECT COUNT(*) FROM cis_staff_vend_map");
    $totalMappings = $stmt->fetchColumn();

    echo "✅ Total mappings in database: {$totalMappings}" . PHP_EOL;

    // Sample 5 mappings
    $stmt = $pdo->query("SELECT * FROM cis_staff_vend_map LIMIT 5");
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($samples)) {
        echo PHP_EOL . "Sample mappings:" . PHP_EOL;
        foreach ($samples as $s) {
            echo "  • {$s['first_name']} {$s['last_name']} <{$s['email']}>" . PHP_EOL;
            echo "    Xero: {$s['xero_employee_id']}" . PHP_EOL;
            echo "    Vend: {$s['vend_customer_id']}" . PHP_EOL;
        }
    }
    echo PHP_EOL;
}

// === SUMMARY ===
echo "╔═══════════════════════════════════════════════════════════════╗" . PHP_EOL;
echo "║                        SUMMARY                                ║" . PHP_EOL;
echo "╚═══════════════════════════════════════════════════════════════╝" . PHP_EOL;
echo "Xero Employees:    " . count($xeroEmployees) . PHP_EOL;
echo "Vend Customers:    " . count($vendCustomers) . PHP_EOL;
echo "Matches Found:     " . count($matches) . PHP_EOL;
echo "Unmatched:         " . count($unmatched) . PHP_EOL;
echo PHP_EOL;

if ($dryRun) {
    echo "ℹ️  DRY RUN MODE - No database changes made" . PHP_EOL;
    echo "   Run with --dry=0 to execute" . PHP_EOL;
} else {
    echo "✅ LIVE EXECUTION COMPLETE" . PHP_EOL;
}

echo PHP_EOL;
exit(0);
