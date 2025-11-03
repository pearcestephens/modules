<?php
/**
 * Payroll Module - Deep Audit & Verification Script
 *
 * Checks:
 * - File existence
 * - Syntax errors
 * - Class dependencies
 * - Database tables
 * - Missing methods
 * - Configuration
 *
 * Run: php audit_payroll.php
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "🔍 PAYROLL MODULE - DEEP AUDIT\n";
echo "================================\n\n";

$issues = [];
$warnings = [];
$passed = [];

// ============================================================================
// 1. FILE EXISTENCE CHECK
// ============================================================================

echo "📁 Checking file existence...\n";

$requiredFiles = [
    // Controllers
    'controllers/BaseController.php',
    'controllers/DashboardController.php',
    'controllers/PayRunController.php',
    'controllers/PayslipController.php',
    'controllers/BonusController.php',
    'controllers/LeaveController.php',
    'controllers/AmendmentController.php',
    'controllers/ReconciliationController.php',
    'controllers/WageDiscrepancyController.php',
    'controllers/VendPaymentController.php',
    'controllers/XeroController.php',
    'controllers/PayrollAutomationController.php',

    // Services
    'services/PayslipCalculationEngine.php',
    'services/BonusService.php',
    'services/PayslipService.php',
    'services/BankExportService.php',
    'services/PayrollDeputyService.php',
    'services/PayrollXeroService.php',

    // Libraries
    'lib/PayrollLogger.php',
    'lib/XeroTokenStore.php',
    'lib/Kernel.php',
    'lib/Router.php',
    'lib/Db.php',
    'lib/Validation.php',
    'lib/Response.php',
    'lib/Log.php',

    // Views
    'views/dashboard.php',
    'views/pay-run-list.php',
    'views/payslip-list.php',

    // Database
    'database/payroll_schema.sql',

    // API
    'api/routes.php',
];

foreach ($requiredFiles as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $passed[] = "✅ $file";
    } else {
        $issues[] = "❌ Missing: $file";
    }
}

echo count($passed) . " files found, " . count($issues) . " missing\n\n";

// ============================================================================
// 2. SYNTAX CHECK
// ============================================================================

echo "🔧 Checking PHP syntax...\n";

$phpFiles = [
    'controllers',
    'services',
    'lib',
    'api'
];

$syntaxErrors = 0;
foreach ($phpFiles as $dir) {
    $dirPath = __DIR__ . '/' . $dir;
    if (is_dir($dirPath)) {
        $files = glob($dirPath . '/*.php');
        foreach ($files as $file) {
            $output = [];
            $returnVar = 0;
            exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $returnVar);
            if ($returnVar !== 0) {
                $issues[] = "❌ Syntax error in " . basename($file) . ": " . implode("\n", $output);
                $syntaxErrors++;
            }
        }
    }
}

if ($syntaxErrors === 0) {
    $passed[] = "✅ All PHP files have valid syntax";
    echo "All PHP files valid\n\n";
} else {
    echo "$syntaxErrors syntax errors found\n\n";
}

// ============================================================================
// 3. CLASS DEPENDENCY CHECK
// ============================================================================

echo "🔗 Checking class dependencies...\n";

// Check if Deputy class exists
$deputyPath = __DIR__ . '/../../../assets/functions/deputy.php';
if (file_exists($deputyPath)) {
    $passed[] = "✅ Deputy library found";
} else {
    $warnings[] = "⚠️  Deputy library not found at expected path";
}

// Check PayrollLogger
if (file_exists(__DIR__ . '/lib/PayrollLogger.php')) {
    $passed[] = "✅ PayrollLogger exists";
} else {
    $issues[] = "❌ PayrollLogger missing";
}

// Check XeroTokenStore
if (file_exists(__DIR__ . '/lib/XeroTokenStore.php')) {
    $passed[] = "✅ XeroTokenStore exists";
} else {
    $issues[] = "❌ XeroTokenStore missing";
}

echo "Dependency check complete\n\n";

// ============================================================================
// 4. DATABASE CONNECTION TEST
// ============================================================================

echo "🗄️  Testing database connection...\n";

try {
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 3);
    $appPhp = rtrim($docRoot, '/') . '/app.php';

    if (file_exists($appPhp)) {
        require_once $appPhp;
        $passed[] = "✅ app.php loaded";

        // Try to get database connection
        if (function_exists('getDB')) {
            $db = getDB();
            $passed[] = "✅ Database connection successful";

            // Check critical tables
            $tables = [
                'payroll_staff',
                'deputy_timesheets',
                'pay_periods',
                'payslips',
                'payroll_activity_log',
                'oauth_tokens',
            ];

            foreach ($tables as $table) {
                $stmt = $db->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    $passed[] = "✅ Table exists: $table";
                } else {
                    $warnings[] = "⚠️  Table missing: $table (may need migration)";
                }
            }
        } else {
            $warnings[] = "⚠️  getDB() function not available";
        }
    } else {
        $warnings[] = "⚠️  app.php not found at $appPhp";
    }
} catch (\Exception $e) {
    $warnings[] = "⚠️  Database check failed: " . $e->getMessage();
}

echo "Database check complete\n\n";

// ============================================================================
// 5. ENVIRONMENT VARIABLES CHECK
// ============================================================================

echo "🔐 Checking environment configuration...\n";

$envVars = [
    'DEPUTY_API_TOKEN' => false, // optional
    'XERO_CLIENT_ID' => false,
    'XERO_CLIENT_SECRET' => false,
    'XERO_REDIRECT_URI' => false,
    'XERO_ENCRYPTION_KEY' => false,
];

foreach ($envVars as $var => $required) {
    $value = getenv($var);
    if (!empty($value)) {
        $passed[] = "✅ $var is set";
    } else {
        if ($required) {
            $issues[] = "❌ Required env var missing: $var";
        } else {
            $warnings[] = "⚠️  Optional env var not set: $var";
        }
    }
}

echo "Environment check complete\n\n";

// ============================================================================
// 6. METHOD EXISTENCE CHECK (CRITICAL SERVICES)
// ============================================================================

echo "🔬 Checking critical service methods...\n";

// Check PayrollDeputyService
if (file_exists(__DIR__ . '/services/PayrollDeputyService.php')) {
    $content = file_get_contents(__DIR__ . '/services/PayrollDeputyService.php');
    $requiredMethods = [
        'importTimesheets',
        'validateAndTransform',
        'filterDuplicates',
        'bulkInsert',
        'didStaffWorkAlone',
    ];

    foreach ($requiredMethods as $method) {
        if (strpos($content, "function $method") !== false) {
            $passed[] = "✅ PayrollDeputyService::$method() exists";
        } else {
            $issues[] = "❌ PayrollDeputyService::$method() missing";
        }
    }
}

// Check PayrollXeroService
if (file_exists(__DIR__ . '/services/PayrollXeroService.php')) {
    $content = file_get_contents(__DIR__ . '/services/PayrollXeroService.php');
    $requiredMethods = [
        'getAuthorizationUrl',
        'exchangeCodeForTokens',
        'refreshAccessToken',
        'syncEmployees',
        'listEmployees',
        'createPayRun',
    ];

    foreach ($requiredMethods as $method) {
        if (strpos($content, "function $method") !== false) {
            $passed[] = "✅ PayrollXeroService::$method() exists";
        } else {
            $issues[] = "❌ PayrollXeroService::$method() missing";
        }
    }
}

echo "Method check complete\n\n";

// ============================================================================
// 7. SUMMARY
// ============================================================================

echo "\n";
echo "====================================\n";
echo "📊 AUDIT SUMMARY\n";
echo "====================================\n\n";

echo "✅ PASSED: " . count($passed) . "\n";
echo "⚠️  WARNINGS: " . count($warnings) . "\n";
echo "❌ ISSUES: " . count($issues) . "\n\n";

if (!empty($issues)) {
    echo "🚨 CRITICAL ISSUES:\n";
    foreach ($issues as $issue) {
        echo "   $issue\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️  WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "   $warning\n";
    }
    echo "\n";
}

// ============================================================================
// 8. RECOMMENDATIONS
// ============================================================================

echo "💡 RECOMMENDATIONS:\n\n";

if (!empty($issues)) {
    echo "1. Fix critical issues before deploying\n";
}

if (count($warnings) > 5) {
    echo "2. Configure environment variables (.env file)\n";
}

echo "3. Run database migrations (payroll_schema.sql)\n";
echo "4. Test Deputy import: php test_deputy_import.php\n";
echo "5. Test Xero OAuth: php test_xero_oauth.php\n";
echo "6. Run integration tests\n";

echo "\n";

// ============================================================================
// 9. EXIT CODE
// ============================================================================

if (!empty($issues)) {
    echo "❌ AUDIT FAILED - Critical issues found\n";
    exit(1);
} elseif (!empty($warnings)) {
    echo "⚠️  AUDIT PASSED WITH WARNINGS\n";
    exit(0);
} else {
    echo "✅ AUDIT PASSED - All checks successful!\n";
    exit(0);
}
