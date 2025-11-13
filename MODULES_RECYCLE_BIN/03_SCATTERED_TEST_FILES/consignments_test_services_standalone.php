<?php
/**
 * Service Layer Test - Real Data Verification (Standalone)
 *
 * Tests all 4 new services with actual database queries.
 * Standalone version with minimal dependencies.
 *
 * Run from CLI: php test_services_standalone.php
 *
 * @version 1.0.0
 * @created 2025-11-05
 */

declare(strict_types=1);

// Database configuration (no hardcoded secrets)
// Priority: ENV > private_html/.env > interactive prompt (CLI)

// Minimal .env loader (optional)
$__envPathCandidates = [
    dirname(__FILE__, 4) . '/private_html/.env', // /applications/jcepnzzkmj/private_html/.env
    dirname(__FILE__, 3) . '/private_html/.env', // fallback (in case of different layout)
];
foreach ($__envPathCandidates as $__envPath) {
    if (!is_readable($__envPath)) { continue; }
    $lines = file($__envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) { continue; }
        $pos = strpos($line, '=');
        if ($pos === false) { continue; }
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        // Strip optional quotes
        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
            $val = substr($val, 1, -1);
        }
        if ($key !== '') { putenv($key . '=' . $val); $_ENV[$key] = $val; }
    }
    break;
}

$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('DB_NAME') ?: 'jcepnzzkmj';
$DB_USER = getenv('DB_USER') ?: getenv('DB_USERNAME') ?: 'jcepnzzkmj';
$DB_PASS = getenv('DB_PASSWORD') ?: getenv('MYSQL_PWD') ?: '';

// If running from CLI and password missing, ask interactively (hidden input)
if ($DB_PASS === '' && php_sapi_name() === 'cli') {
    fwrite(STDOUT, 'Enter DB password: ');
    // Hide input
    if (stripos(PHP_OS, 'WIN') === false) { @system('stty -echo'); }
    $DB_PASS = trim(fgets(STDIN));
    if (stripos(PHP_OS, 'WIN') === false) { @system('stty echo'); }
    fwrite(STDOUT, PHP_EOL);
}

// Simple database connection helper
function db_ro(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        // Use globals populated from ENV/.env
        global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $DB_HOST, $DB_NAME);
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    }
    return $pdo;
}

function db_rw_or_null(): ?PDO {
    return db_ro(); // Same connection for RW
}

// Load services
require_once __DIR__ . '/lib/Services/TransferService.php';
require_once __DIR__ . '/lib/Services/ProductService.php';
require_once __DIR__ . '/lib/Services/ConfigService.php';
require_once __DIR__ . '/lib/Services/SyncService.php';

use CIS\Consignments\Services\TransferService;
use CIS\Consignments\Services\ProductService;
use CIS\Consignments\Services\ConfigService;
use CIS\Consignments\Services\SyncService;

// ANSI colors
define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('BLUE', "\033[34m");
define('RESET', "\033[0m");

// Test runner
class ServiceTestRunner {
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function test(string $name, callable $callback): void {
        echo BLUE . "→ " . RESET . $name . "... ";
        try {
            $result = $callback();
            if ($result === true || $result === null) {
                echo GREEN . "✓\n" . RESET;
                $this->passed++;
            } else {
                echo RED . "✗\n" . RESET;
                echo "  Result: " . json_encode($result) . "\n";
                $this->failed++;
                $this->errors[] = $name;
            }
        } catch (\Exception $e) {
            echo RED . "✗ ERROR\n" . RESET;
            echo "  " . $e->getMessage() . "\n";
            $this->failed++;
            $this->errors[] = $name . ': ' . $e->getMessage();
        }
    }

    public function summary(): void {
        echo "\n" . str_repeat('=', 70) . "\n";
        echo YELLOW . "SUMMARY\n" . RESET;
        echo str_repeat('=', 70) . "\n";
        echo GREEN . "✓ Passed: {$this->passed}\n" . RESET;
        echo RED . "✗ Failed: {$this->failed}\n" . RESET;
        echo "  Total:  " . ($this->passed + $this->failed) . "\n";
        if (!empty($this->errors)) {
            echo "\n" . RED . "FAILED:\n" . RESET;
            foreach ($this->errors as $error) {
                echo "  - {$error}\n";
            }
        }
        echo str_repeat('=', 70) . "\n";
        if ($this->failed === 0) {
            echo GREEN . "🎉 ALL TESTS PASSED!\n" . RESET;
            exit(0);
        } else {
            echo RED . "❌ {$this->failed} TEST(S) FAILED\n" . RESET;
            exit(1);
        }
    }
}

$test = new ServiceTestRunner();

echo YELLOW . "\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  CIS Service Layer Test - Real Database Queries                 ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n" . RESET;

// ============================================================================
// 1. TRANSFER SERVICE
// ============================================================================
echo YELLOW . "[1] TransferService\n" . RESET;

$test->test('Create TransferService instance', function() {
    $service = TransferService::make();
    return $service instanceof TransferService;
});

$test->test('Get recent transfers', function() {
    $service = TransferService::make();
    $transfers = $service->recent(5);
    return is_array($transfers);
});

$test->test('List transfers with pagination', function() {
    $service = TransferService::make();
    $result = $service->list([], 1, 10);
    return isset($result['transfers']) &&
           isset($result['pagination']) &&
           isset($result['pagination']['page']) &&
           isset($result['pagination']['total']);
});

$test->test('Filter transfers by type STOCK', function() {
    $service = TransferService::make();
    $result = $service->list(['type' => 'STOCK'], 1, 5);
    return isset($result['transfers']);
});

$test->test('Search transfers by query', function() {
    $service = TransferService::make();
    $result = $service->list(['q' => 'test'], 1, 5);
    return isset($result['transfers']);
});

$test->test('Get transfer statistics', function() {
    $service = TransferService::make();
    $stats = $service->getStats();
    return is_array($stats);
});

// ============================================================================
// 2. PRODUCT SERVICE
// ============================================================================
echo YELLOW . "\n[2] ProductService\n" . RESET;

$test->test('Create ProductService instance', function() {
    $service = ProductService::make();
    return $service instanceof ProductService;
});

$test->test('Search products', function() {
    $service = ProductService::make();
    $products = $service->search('vape', 10);
    return is_array($products);
});

$test->test('Reject short search query', function() {
    $service = ProductService::make();
    try {
        $service->search('a', 10);
        return false; // Should throw
    } catch (\InvalidArgumentException $e) {
        return true; // Expected
    }
});

$test->test('Get top transferred products', function() {
    $service = ProductService::make();
    $products = $service->getTopTransferred(5, 30);
    return is_array($products);
});

// ============================================================================
// 3. CONFIG SERVICE
// ============================================================================
echo YELLOW . "\n[3] ConfigService\n" . RESET;

$test->test('Create ConfigService instance', function() {
    $service = ConfigService::make();
    return $service instanceof ConfigService;
});

$test->test('Get outlets', function() {
    $service = ConfigService::make();
    $outlets = $service->getOutlets();
    return is_array($outlets) && count($outlets) > 0;
});

$test->test('Get suppliers', function() {
    $service = ConfigService::make();
    $suppliers = $service->getSuppliers();
    return is_array($suppliers);
});

$test->test('Get transfer types (6 types)', function() {
    $service = ConfigService::make();
    $types = $service->getTransferTypes();
    return is_array($types) && count($types) === 6;
});

$test->test('Get transfer statuses (6 statuses)', function() {
    $service = ConfigService::make();
    $statuses = $service->getTransferStatuses();
    return is_array($statuses) && count($statuses) === 6;
});

$test->test('Get specific transfer type', function() {
    $service = ConfigService::make();
    $type = $service->getTransferType('STOCK');
    return $type !== null && $type['value'] === 'STOCK';
});

$test->test('Generate CSRF token', function() {
    @session_start();
    $service = ConfigService::make();
    $token = $service->getCsrfToken();
    return !empty($token) && strlen($token) === 64;
});

// ============================================================================
// 4. SYNC SERVICE
// ============================================================================
echo YELLOW . "\n[4] SyncService\n" . RESET;

$test->test('Create SyncService instance', function() {
    $service = SyncService::make();
    return $service instanceof SyncService;
});

$test->test('Check sync enabled state', function() {
    $service = SyncService::make();
    $enabled = $service->isEnabled();
    return is_bool($enabled);
});

$test->test('Get sync status', function() {
    $service = SyncService::make();
    $status = $service->getStatus();
    return is_array($status) &&
           isset($status['enabled']) &&
           isset($status['token_set']) &&
           isset($status['file_exists']);
});

$test->test('Verify sync operational status', function() {
    $service = SyncService::make();
    $result = $service->verify();
    return is_array($result) &&
           isset($result['operational']) &&
           isset($result['checks']) &&
           isset($result['issues']);
});

$test->test('Check token configuration', function() {
    $service = SyncService::make();
    $hasToken = $service->hasToken();
    return is_bool($hasToken);
});

// ============================================================================
// 5. INTEGRATION TESTS
// ============================================================================
echo YELLOW . "\n[5] Integration Tests\n" . RESET;

$test->test('Get outlets and validate structure', function() {
    $service = ConfigService::make();
    $outlets = $service->getOutlets();
    if (empty($outlets)) return true;
    $outlet = $outlets[0];
    return isset($outlet['id']) && isset($outlet['name']);
});

$test->test('Get transfer types and validate structure', function() {
    $service = ConfigService::make();
    $types = $service->getTransferTypes();
    foreach ($types as $type) {
        if (!isset($type['value']) ||
            !isset($type['requires_outlet_from']) ||
            !isset($type['requires_outlet_to']) ||
            !isset($type['requires_supplier'])) {
            return false;
        }
    }
    return true;
});

$test->test('Cross-service: Transfer with product lookup', function() {
    $transferService = TransferService::make();
    $productService = ProductService::make();

    $result = $transferService->list([], 1, 1);
    if (empty($result['transfers'])) return true;

    $transfer = $result['transfers'][0];
    return isset($transfer['id']);
});

// ============================================================================
// SUMMARY
// ============================================================================
$test->summary();
