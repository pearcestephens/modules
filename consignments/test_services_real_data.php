<?php
/**
 * Service Layer Test - Real Data Verification
 *
 * Tests all 4 new services with actual database queries:
 * - TransferService
 * - ProductService
 * - ConfigService
 * - SyncService
 *
 * Run from CLI: php test_services_real_data.php
 *
 * @version 1.0.0
 * @created 2025-11-05
 */

declare(strict_types=1);

// Bootstrap
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/Services/TransferService.php';
require_once __DIR__ . '/lib/Services/ProductService.php';
require_once __DIR__ . '/lib/Services/ConfigService.php';
require_once __DIR__ . '/lib/Services/SyncService.php';

use CIS\Consignments\Services\TransferService;
use CIS\Consignments\Services\ProductService;
use CIS\Consignments\Services\ConfigService;
use CIS\Consignments\Services\SyncService;

// ANSI colors for output
define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('BLUE', "\033[34m");
define('RESET', "\033[0m");

/**
 * Test runner
 */
class ServiceTestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function test(string $name, callable $callback): void
    {
        echo BLUE . "Testing: " . RESET . $name . "...";

        try {
            $result = $callback();

            if ($result === true || $result === null) {
                echo GREEN . " ✓ PASS\n" . RESET;
                $this->passed++;
            } else {
                echo RED . " ✗ FAIL\n" . RESET;
                echo "  Result: " . json_encode($result) . "\n";
                $this->failed++;
                $this->errors[] = $name;
            }
        } catch (\Exception $e) {
            echo RED . " ✗ ERROR\n" . RESET;
            echo "  " . $e->getMessage() . "\n";
            $this->failed++;
            $this->errors[] = $name . ': ' . $e->getMessage();
        }
    }

    public function summary(): void
    {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo YELLOW . "TEST SUMMARY\n" . RESET;
        echo str_repeat('=', 60) . "\n";
        echo GREEN . "Passed: {$this->passed}\n" . RESET;
        echo RED . "Failed: {$this->failed}\n" . RESET;
        echo "Total:  " . ($this->passed + $this->failed) . "\n";

        if (!empty($this->errors)) {
            echo "\n" . RED . "FAILED TESTS:\n" . RESET;
            foreach ($this->errors as $error) {
                echo "  - {$error}\n";
            }
        }

        echo str_repeat('=', 60) . "\n";

        if ($this->failed === 0) {
            echo GREEN . "🎉 ALL TESTS PASSED!\n" . RESET;
            exit(0);
        } else {
            echo RED . "❌ SOME TESTS FAILED\n" . RESET;
            exit(1);
        }
    }
}

// Initialize test runner
$test = new ServiceTestRunner();

echo YELLOW . "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║  CIS Consignments - Service Layer Test (Real Data)        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n" . RESET;

// ============================================================================
// 1. TRANSFER SERVICE TESTS
// ============================================================================

echo YELLOW . "\n[1] TransferService Tests\n" . RESET;
echo str_repeat('-', 60) . "\n";

$test->test('TransferService::make() factory method', function() {
    $service = TransferService::make();
    return $service instanceof TransferService;
});

$test->test('TransferService::recent() - Get recent transfers', function() {
    $service = TransferService::make();
    $transfers = $service->recent(10);
    return is_array($transfers);
});

$test->test('TransferService::list() - List with pagination', function() {
    $service = TransferService::make();
    $result = $service->list([], 1, 10);
    return isset($result['transfers']) && isset($result['pagination']);
});

$test->test('TransferService::list() - Filter by type', function() {
    $service = TransferService::make();
    $result = $service->list(['type' => 'STOCK'], 1, 10);
    return isset($result['transfers']);
});

$test->test('TransferService::getStats() - Get statistics', function() {
    $service = TransferService::make();
    $stats = $service->getStats();
    return is_array($stats) && isset($stats['total_transfers']);
});

// Get a real transfer ID for detail test
$transferService = TransferService::make();
$recentTransfers = $transferService->recent(1);
$testTransferId = !empty($recentTransfers) ? $recentTransfers[0]['id'] : null;

if ($testTransferId) {
    $test->test('TransferService::getById() - Get transfer detail', function() use ($transferService, $testTransferId) {
        $transfer = $transferService->getById($testTransferId);
        return $transfer !== null && isset($transfer['items']);
    });
} else {
    echo YELLOW . "  (Skipped: No transfers in database)\n" . RESET;
}

// ============================================================================
// 2. PRODUCT SERVICE TESTS
// ============================================================================

echo YELLOW . "\n[2] ProductService Tests\n" . RESET;
echo str_repeat('-', 60) . "\n";

$test->test('ProductService::make() factory method', function() {
    $service = ProductService::make();
    return $service instanceof ProductService;
});

$test->test('ProductService::search() - Search products', function() {
    $service = ProductService::make();
    $products = $service->search('vape', 10);
    return is_array($products);
});

$test->test('ProductService::search() - Minimum 2 chars required', function() {
    $service = ProductService::make();
    try {
        $service->search('a', 10);
        return false; // Should throw exception
    } catch (\InvalidArgumentException $e) {
        return true; // Exception expected
    }
});

$test->test('ProductService::getTopTransferred() - Get top products', function() {
    $service = ProductService::make();
    $products = $service->getTopTransferred(10, 30);
    return is_array($products);
});

// Get a real product ID for detail test
$productService = ProductService::make();
$searchResults = $productService->search('vape', 1);
$testProductId = !empty($searchResults) ? $searchResults[0]['product_id'] : null;

if ($testProductId) {
    $test->test('ProductService::getById() - Get product detail', function() use ($productService, $testProductId) {
        $product = $productService->getById($testProductId);
        return $product !== null;
    });

    $test->test('ProductService::getInventoryByOutlets() - Get inventory', function() use ($productService, $testProductId) {
        $inventory = $productService->getInventoryByOutlets($testProductId);
        return is_array($inventory);
    });
} else {
    echo YELLOW . "  (Skipped: No products found)\n" . RESET;
}

// ============================================================================
// 3. CONFIG SERVICE TESTS
// ============================================================================

echo YELLOW . "\n[3] ConfigService Tests\n" . RESET;
echo str_repeat('-', 60) . "\n";

$test->test('ConfigService::make() factory method', function() {
    $service = ConfigService::make();
    return $service instanceof ConfigService;
});

$test->test('ConfigService::getOutlets() - Get all outlets', function() {
    $service = ConfigService::make();
    $outlets = $service->getOutlets();
    return is_array($outlets) && count($outlets) > 0;
});

$test->test('ConfigService::getSuppliers() - Get all suppliers', function() {
    $service = ConfigService::make();
    $suppliers = $service->getSuppliers();
    return is_array($suppliers);
});

$test->test('ConfigService::getTransferTypes() - Get transfer types', function() {
    $service = ConfigService::make();
    $types = $service->getTransferTypes();
    return is_array($types) && count($types) === 6; // STOCK, JUICE, PO, INTERNAL, RETURN, STAFF
});

$test->test('ConfigService::getTransferStatuses() - Get statuses', function() {
    $service = ConfigService::make();
    $statuses = $service->getTransferStatuses();
    return is_array($statuses) && count($statuses) === 6; // draft, sent, receiving, received, completed, cancelled
});

$test->test('ConfigService::getTransferType() - Get specific type', function() {
    $service = ConfigService::make();
    $type = $service->getTransferType('STOCK');
    return $type !== null && $type['value'] === 'STOCK';
});

$test->test('ConfigService::getCsrfToken() - Generate CSRF token', function() {
    $service = ConfigService::make();
    $token = $service->getCsrfToken();
    return !empty($token) && strlen($token) === 64; // 32 bytes = 64 hex chars
});

// ============================================================================
// 4. SYNC SERVICE TESTS
// ============================================================================

echo YELLOW . "\n[4] SyncService Tests\n" . RESET;
echo str_repeat('-', 60) . "\n";

$test->test('SyncService::make() factory method', function() {
    $service = SyncService::make();
    return $service instanceof SyncService;
});

$test->test('SyncService::isEnabled() - Check sync state', function() {
    $service = SyncService::make();
    $enabled = $service->isEnabled();
    return is_bool($enabled);
});

$test->test('SyncService::getStatus() - Get comprehensive status', function() {
    $service = SyncService::make();
    $status = $service->getStatus();
    return is_array($status) &&
           isset($status['enabled']) &&
           isset($status['token_set']) &&
           isset($status['file_exists']);
});

$test->test('SyncService::verify() - Verify operational status', function() {
    $service = SyncService::make();
    $result = $service->verify();
    return is_array($result) &&
           isset($result['operational']) &&
           isset($result['checks']) &&
           isset($result['issues']);
});

$test->test('SyncService::hasToken() - Check token configuration', function() {
    $service = SyncService::make();
    $hasToken = $service->hasToken();
    return is_bool($hasToken);
});

$test->test('SyncService::getMaskedToken() - Get masked token', function() {
    $service = SyncService::make();
    $masked = $service->getMaskedToken();
    return $masked === null || is_string($masked);
});

// ============================================================================
// 5. INTEGRATION TESTS
// ============================================================================

echo YELLOW . "\n[5] Integration Tests\n" . RESET;
echo str_repeat('-', 60) . "\n";

$test->test('Integration: List transfers with product details', function() {
    $transferService = TransferService::make();
    $productService = ProductService::make();

    $result = $transferService->list([], 1, 5);

    if (empty($result['transfers'])) {
        return true; // No transfers to test, but not a failure
    }

    $transfer = $result['transfers'][0];
    $items = $transferService->getItems($transfer['id']);

    if (empty($items)) {
        return true; // No items, but not a failure
    }

    $productId = $items[0]['product_id'];
    $product = $productService->getById($productId);

    return $product !== null;
});

$test->test('Integration: Get outlets and create transfer structure', function() {
    $configService = ConfigService::make();
    $transferService = TransferService::make();

    $outlets = $configService->getOutlets();

    if (count($outlets) < 2) {
        return true; // Need at least 2 outlets, but not a failure
    }

    // Just verify we can access the data structure
    return isset($outlets[0]['id']) && isset($outlets[0]['name']);
});

$test->test('Integration: Get transfer types and validate structure', function() {
    $configService = ConfigService::make();

    $types = $configService->getTransferTypes();

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

// ============================================================================
// PRINT SUMMARY
// ============================================================================

$test->summary();
