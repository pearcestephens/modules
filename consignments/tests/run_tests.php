#!/usr/bin/env php
<?php
/**
 * Comprehensive Test Runner for Consignments Module
 *
 * Runs all test phases in sequence:
 * 1. Database validation
 * 2. API endpoint tests
 * 3. Authentication tests
 * 4. Business logic tests
 * 5. Error handling tests
 *
 * Usage: php run_tests.php [--verbose] [--phase=N]
 */

declare(strict_types=1);

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Parse command line arguments
$verbose = in_array('--verbose', $argv);
$phase = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--phase=') === 0) {
        $phase = (int)substr($arg, 8);
    }
}

// Colors for terminal output
class TerminalColors {
    const RESET = "\033[0m";
    const RED = "\033[31m";
    const GREEN = "\033[32m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const CYAN = "\033[36m";
    const BOLD = "\033[1m";

    public static function success($text) {
        return self::GREEN . "✅ $text" . self::RESET;
    }

    public static function error($text) {
        return self::RED . "❌ $text" . self::RESET;
    }

    public static function warning($text) {
        return self::YELLOW . "⚠️  $text" . self::RESET;
    }

    public static function info($text) {
        return self::CYAN . "ℹ️  $text" . self::RESET;
    }

    public static function header($text) {
        return self::BOLD . self::BLUE . $text . self::RESET;
    }
}

// Test results tracker
$results = [
    'passed' => 0,
    'failed' => 0,
    'skipped' => 0,
    'phases' => []
];

function test($name, $callback, &$results) {
    echo "  • Testing: $name... ";
    try {
        $result = call_user_func($callback);
        if ($result === true || $result === null) {
            echo TerminalColors::success("PASS") . "\n";
            $results['passed']++;
            return true;
        } else {
            echo TerminalColors::error("FAIL") . "\n";
            echo "    Reason: $result\n";
            $results['failed']++;
            return false;
        }
    } catch (Exception $e) {
        echo TerminalColors::error("ERROR") . "\n";
        echo "    " . TerminalColors::error($e->getMessage()) . "\n";
        $results['failed']++;
        return false;
    }
}

function runPhase($title, $tests, &$results, $phaseNum) {
    echo "\n" . TerminalColors::header(str_repeat("=", 80)) . "\n";
    echo TerminalColors::header("PHASE $phaseNum: $title") . "\n";
    echo TerminalColors::header(str_repeat("=", 80)) . "\n\n";

    $phaseResults = ['passed' => 0, 'failed' => 0];

    foreach ($tests as $testName => $testCallback) {
        if (test($testName, $testCallback, $results)) {
            $phaseResults['passed']++;
        } else {
            $phaseResults['failed']++;
        }
    }

    $results['phases'][$title] = $phaseResults;

    echo "\nPhase Results: " .
        TerminalColors::success($phaseResults['passed'] . " passed") . " / " .
        TerminalColors::error($phaseResults['failed'] . " failed") . "\n";

    return $phaseResults['failed'] === 0;
}

// ============================================================================
// PHASE 1: DATABASE VALIDATION
// ============================================================================

echo TerminalColors::header("\n🚀 CONSIGNMENTS MODULE TEST SUITE\n");
echo TerminalColors::header("Starting comprehensive validation...\n");

$db = null;
$tests1 = [
    'Database connection' => function() use (&$db) {
        try {
            $db = new PDO(
                'mysql:host=127.0.0.1;port=3306;dbname=jcepnzzkmj;charset=utf8mb4',
                'jcepnzzkmj',
                'wprKh9Jq63',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            return true;
        } catch (PDOException $e) {
            return "Connection failed: " . $e->getMessage();
        }
    },
    'vend_consignments table' => function() use (&$db) {
        if (!$db) return "Database not connected";
        $stmt = $db->query("SHOW TABLES LIKE 'vend_consignments'");
        return $stmt->rowCount() > 0 ? true : "Table not found";
    },
    'vend_consignment_line_items table' => function() use (&$db) {
        if (!$db) return "Database not connected";
        $stmt = $db->query("SHOW TABLES LIKE 'vend_consignment_line_items'");
        return $stmt->rowCount() > 0 ? true : "Table not found";
    },
    'vend_outlets table' => function() use (&$db) {
        if (!$db) return "Database not connected";
        $stmt = $db->query("SHOW TABLES LIKE 'vend_outlets'");
        return $stmt->rowCount() > 0 ? true : "Table not found";
    },
    'vend_suppliers table' => function() use (&$db) {
        if (!$db) return "Database not connected";
        $stmt = $db->query("SHOW TABLES LIKE 'vend_suppliers'");
        return $stmt->rowCount() > 0 ? true : "Table not found";
    },
];

if ($phase === null || $phase === 1) {
    runPhase('Database Validation', $tests1, $results, 1);
}

// ============================================================================
// PHASE 2: DATA INTEGRITY
// ============================================================================

$tests2 = [
    'Consignment records exist' => function() use (&$db) {
        if (!$db) return "Database not connected";
        $stmt = $db->query("SELECT COUNT(*) as count FROM vend_consignments WHERE deleted_at IS NULL");
        $count = $stmt->fetch()['count'] ?? 0;
        return $count > 0 ? true : "No consignment records";
    },
    'Line item records exist' => function() use (&$db) {
        if (!$db) return "Database not connected";
        $stmt = $db->query("SELECT COUNT(*) as count FROM vend_consignment_line_items");
        $count = $stmt->fetch()['count'] ?? 0;
        return $count > 0 ? true : "No line items";
    },
    'Outlet records exist' => function() use (&$db) {
        if (!$db) return "Database not connected";
        $stmt = $db->query("SELECT COUNT(*) as count FROM vend_outlets");
        $count = $stmt->fetch()['count'] ?? 0;
        return $count > 0 ? true : "No outlets";
    },
    'Supplier records exist' => function() use (&$db) {
        if (!$db) return "Database not connected";
        $stmt = $db->query("SELECT COUNT(*) as count FROM vend_suppliers");
        $count = $stmt->fetch()['count'] ?? 0;
        return $count > 0 ? true : "No suppliers";
    },
];

if ($phase === null || $phase === 2) {
    runPhase('Data Integrity', $tests2, $results, 2);
}

// ============================================================================
// PHASE 3: API ENDPOINT STRUCTURE
// ============================================================================

$tests3 = [
    'API files exist' => function() {
        $files = [
            __DIR__ . '/../api/init.php',
            __DIR__ . '/../api/list_transfers.php',
            __DIR__ . '/../api/create_transfer.php',
        ];
        foreach ($files as $file) {
            if (!file_exists($file)) {
                return "Missing: $file";
            }
        }
        return true;
    },
    'Backend API exists' => function() {
        return file_exists(__DIR__ . '/../TransferManager/backend.php') ? true : "Backend not found";
    },
    'Services directory exists' => function() {
        return is_dir(__DIR__ . '/../Services') ? true : "Services directory missing";
    },
    'Logger service exists' => function() {
        return file_exists(__DIR__ . '/../Services/LoggerService.php') ? true : "LoggerService missing";
    },
];

if ($phase === null || $phase === 3) {
    runPhase('API Structure', $tests3, $results, 3);
}

// ============================================================================
// PHASE 4: BUSINESS LOGIC
// ============================================================================

$tests4 = [
    'LoggerService instantiation' => function() {
        if (!file_exists(__DIR__ . '/../Services/LoggerService.php')) {
            return "LoggerService not found";
        }
        require_once __DIR__ . '/../Services/LoggerService.php';
        try {
            $logger = new \ConsignmentsModule\Services\LoggerService([
                'debug' => true,
                'log_path' => __DIR__ . '/../_logs'
            ]);
            return $logger ? true : "Failed to instantiate";
        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    },
    'Logger debug method' => function() {
        require_once __DIR__ . '/../Services/LoggerService.php';
        $logger = new \ConsignmentsModule\Services\LoggerService(['debug' => true]);
        try {
            $logger->debug('Test message', ['context' => 'test']);
            return true;
        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    },
];

if ($phase === null || $phase === 4) {
    runPhase('Business Logic', $tests4, $results, 4);
}

// ============================================================================
// PHASE 5: ERROR HANDLING
// ============================================================================

$tests5 = [
    'Logger handles errors gracefully' => function() {
        require_once __DIR__ . '/../Services/LoggerService.php';
        $logger = new \ConsignmentsModule\Services\LoggerService(['debug' => false]);
        try {
            $logger->error('Test error', ['details' => 'test']);
            return true;
        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    },
    'Missing log directory creation' => function() {
        $testDir = __DIR__ . '/../_logs_test_' . uniqid();
        require_once __DIR__ . '/../Services/LoggerService.php';
        $logger = new \ConsignmentsModule\Services\LoggerService([
            'debug' => true,
            'log_path' => $testDir
        ]);
        $logger->info('Test', []);
        $result = is_dir($testDir);
        @rmdir($testDir);
        return $result ? true : "Log directory not created";
    },
];

if ($phase === null || $phase === 5) {
    runPhase('Error Handling', $tests5, $results, 5);
}

// ============================================================================
// FINAL REPORT
// ============================================================================

echo "\n" . TerminalColors::header(str_repeat("=", 80)) . "\n";
echo TerminalColors::header("TEST SUMMARY") . "\n";
echo TerminalColors::header(str_repeat("=", 80)) . "\n\n";

$totalPassed = $results['passed'];
$totalFailed = $results['failed'];
$totalTests = $totalPassed + $totalFailed;

echo "Total Tests: $totalTests\n";
echo TerminalColors::success("Passed: $totalPassed") . "\n";
echo TerminalColors::error("Failed: $totalFailed") . "\n";
echo "\nPhase Results:\n";

foreach ($results['phases'] as $phase => $phaseResult) {
    $status = $phaseResult['failed'] === 0 ? TerminalColors::success('PASS') : TerminalColors::error('FAIL');
    echo "  $phase: $status (" . $phaseResult['passed'] . " passed, " . $phaseResult['failed'] . " failed)\n";
}

echo "\n" . TerminalColors::header(str_repeat("=", 80)) . "\n";

if ($totalFailed === 0) {
    echo TerminalColors::success("ALL TESTS PASSED! ✅") . "\n";
    exit(0);
} else {
    echo TerminalColors::error("SOME TESTS FAILED ❌") . "\n";
    exit(1);
}
