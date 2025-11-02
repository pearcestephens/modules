<?php
/**
 * Transfer Manager Integration Test
 *
 * Tests all Transfer Manager endpoints and functionality
 *
 * @package CIS\Consignments\Tests
 * @version 1.0.0
 */

declare(strict_types=1);

// Ensure document root for CLI execution
if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
}

// Load database credentials from legacy credentials.php when available, otherwise fall back to config/env
$credentialsFile = $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/credentials.php';
if (is_readable($credentialsFile)) {
    require_once $credentialsFile;
} else {
    $dbConfigPath = realpath(__DIR__ . '/../../config/database.php');
    if ($dbConfigPath && is_readable($dbConfigPath)) {
        $dbConfig = null;
        try {
            $dbConfig = require $dbConfigPath;
        } catch (Throwable $e) {
            // If env requirements are not met (e.g. DB_PASSWORD missing), fall back to environment variables below
            fwrite(STDERR, "[WARN] Unable to load database.php config: " . $e->getMessage() . "\n");
        }

        if (is_array($dbConfig) && isset($dbConfig['cis'])) {
            $cisConfig = $dbConfig['cis'];

            if (!defined('DB_HOST') && !empty($cisConfig['host'])) {
                define('DB_HOST', $cisConfig['host']);
            }
            if (!defined('DB_DATABASE') && !empty($cisConfig['database'])) {
                define('DB_DATABASE', $cisConfig['database']);
            }
            if (!defined('DB_USERNAME') && !empty($cisConfig['username'])) {
                define('DB_USERNAME', $cisConfig['username']);
            }
            if (!defined('DB_PASSWORD') && array_key_exists('password', $cisConfig)) {
                define('DB_PASSWORD', (string)$cisConfig['password']);
            }
        }
    }

    // Fallback to environment variables if constants still undefined
    $envMap = [
        'DB_HOST' => ['DB_HOST'],
        'DB_DATABASE' => ['DB_DATABASE', 'DB_NAME'],
        'DB_USERNAME' => ['DB_USERNAME', 'DB_USER'],
        'DB_PASSWORD' => ['DB_PASSWORD', 'DB_PASS'],
    ];

    foreach ($envMap as $constant => $candidates) {
        if (defined($constant)) {
            continue;
        }

        foreach ($candidates as $envKey) {
            $value = getenv($envKey);
            if ($value !== false && $value !== '') {
                define($constant, $value);
                continue 2;
            }
        }

        if (!defined($constant)) {
            define($constant, '');
        }
    }
}

// Load module bootstrap (requires document root)
require_once __DIR__ . '/../bootstrap.php';

class TransferManagerTest
{
    private const API_URL = '/modules/consignments/TransferManager/api.php';
    private const INDEX_URL = '/modules/consignments/';
    private string $csrfToken;
    private array $testResults = [];
    private $pdo;

    public function __construct()
    {
        // Initialize session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Get database connection
        $this->pdo = $this->getDatabaseConnection();
        $this->csrfToken = $this->generateCsrfToken();
    }

    /**
     * Get database connection using CIS credentials
     */
    private function getDatabaseConnection()
    {
        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $dbname = defined('DB_DATABASE') ? DB_DATABASE : 'jcepnzzkmj';
        $user = defined('DB_USERNAME') ? DB_USERNAME : 'jcepnzzkmj';
        $pass = defined('DB_PASSWORD') ? DB_PASSWORD : '';

        try {
            $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            echo "❌ Database connection failed: {$e->getMessage()}\n";
            exit(1);
        }
    }

    /**
     * Generate or return the Transfer Manager CSRF token
     */
    private function generateCsrfToken(): string
    {
        if (!isset($_SESSION['tt_csrf'])) {
            $_SESSION['tt_csrf'] = bin2hex(random_bytes(16));
        }

        return $_SESSION['tt_csrf'];
    }

    /**
     * Run all tests
     */
    public function runAllTests(): void
    {
        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║       Transfer Manager Integration Test Suite               ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n";
        echo "\n";

        // Environment checks
        echo "📋 Environment Checks\n";
        echo str_repeat("─", 60) . "\n";
        $this->checkEnvironment();

        // Database checks
        echo "\n📋 Database Checks\n";
        echo str_repeat("─", 60) . "\n";
        $this->checkDatabase();

        // Main UI page
        echo "\n📋 Main UI Page Tests\n";
        echo str_repeat("─", 60) . "\n";
        $this->testMainPage();

        // API endpoint tests
        echo "\n📋 API Endpoint Tests\n";
        echo str_repeat("─", 60) . "\n";
        $this->testApiInfo();
        $this->testSyncStatus();
        $this->testOutletList();
        $this->testSupplierList();
        $this->testProductSearch();

        // Lightspeed integration tests (if token available)
        if (getenv('LS_API_TOKEN') && getenv('LS_API_TOKEN') !== 'your_lightspeed_api_token_here') {
            echo "\n📋 Lightspeed API Tests\n";
            echo str_repeat("─", 60) . "\n";
            $this->testLightspeedConnection();
            $this->testConsignmentList();
        } else {
            echo "\n⚠️  Skipping Lightspeed tests (LS_API_TOKEN not configured)\n";
        }

        // Summary
        $this->printSummary();
    }

    /**
     * Check environment variables
     */
    private function checkEnvironment(): void
    {
        $required = [
            'DB_HOST' => getenv('DB_HOST'),
            'DB_NAME' => getenv('DB_NAME'),
            'DB_USER' => getenv('DB_USER'),
            'DB_PASS' => getenv('DB_PASS'),
            'LS_API_TOKEN' => getenv('LS_API_TOKEN'),
            'TRANSFER_MANAGER_PIN' => getenv('TRANSFER_MANAGER_PIN'),
        ];

        foreach ($required as $var => $value) {
            if (empty($value) || str_contains($value, 'your_')) {
                $this->recordTest("ENV: {$var}", false, "Not configured");
            } else {
                $masked = $var === 'DB_PASS' ? '********' :
                         ($var === 'LS_API_TOKEN' ? substr($value, 0, 10) . '...' :
                         ($var === 'TRANSFER_MANAGER_PIN' ? '****' : $value));
                $this->recordTest("ENV: {$var}", true, $masked);
            }
        }
    }

    /**
     * Check database tables exist
     */
    private function checkDatabase(): void
    {
        try {
            $tables = [
                'vend_outlets',
                'vend_suppliers',
                'vend_products',
                'vend_consignment_queue',
                'stock_transfers',
                'stock_transfer_items',
            ];

            foreach ($tables as $table) {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$table}");
                $count = $stmt->fetchColumn();
                $this->recordTest("Table: {$table}", true, "{$count} rows");
            }

        } catch (Exception $e) {
            $this->recordTest("Database Connection", false, $e->getMessage());
        }
    }

    /**
     * Test main UI page loads
     */
    private function testMainPage(): void
    {
        $indexPath = __DIR__ . '/../index.php';

        if (!file_exists($indexPath)) {
            $this->recordTest("index.php exists", false, "File not found");
            return;
        }

        $this->recordTest("index.php exists", true);

        // Check for required includes
        $content = file_get_contents($indexPath);

        $checks = [
            'bootstrap.php' => str_contains($content, 'bootstrap.php'),
            'loadTransferManagerInit()' => str_contains($content, 'loadTransferManagerInit'),
            'frontend-content.php' => str_contains($content, 'frontend-content.php'),
            'TT_CONFIG' => str_contains($content, 'TT_CONFIG'),
            'Bootstrap 5.3.3' => str_contains($content, 'bootstrap@5.3.3'),
        ];

        foreach ($checks as $name => $passed) {
            $this->recordTest("index.php contains {$name}", $passed);
        }

        // Check JS modules are referenced
        $jsModules = [
            '00-config-init.js',
            '01-core-helpers.js',
            '02-ui-components.js',
            '03-transfer-functions.js',
            '04-list-refresh.js',
            '05-detail-modal.js',
            '06-event-listeners.js',
            '07-init.js',
            '08-dom-ready.js',
        ];

        foreach ($jsModules as $module) {
            $exists = file_exists(__DIR__ . "/../TransferManager/js/{$module}");
            $this->recordTest("JS module: {$module}", $exists);
        }
    }

    /**
     * Test API info endpoint
     */
    private function testApiInfo(): void
    {
        $response = $this->callApi('api_info');

        if ($response === null) {
            $this->recordTest("API: api_info", false, "No response");
            return;
        }

        $passed = isset($response['version']) &&
                  isset($response['endpoints']) &&
                  is_array($response['endpoints']);

        $this->recordTest("API: api_info", $passed,
            $passed ? "v{$response['version']}, " . count($response['endpoints']) . " endpoints" : "Invalid structure");
    }

    /**
     * Test sync status endpoint
     */
    private function testSyncStatus(): void
    {
        $response = $this->callApi('get_sync_status');

        if ($response === null) {
            $this->recordTest("API: get_sync_status", false, "No response");
            return;
        }

        $passed = isset($response['enabled']) && is_bool($response['enabled']);

        $this->recordTest("API: get_sync_status", $passed,
            $passed ? ($response['enabled'] ? 'Enabled' : 'Disabled') : "Invalid structure");
    }

    /**
     * Test outlet list endpoint
     */
    private function testOutletList(): void
    {
        $response = $this->callApi('list_outlets');

        if ($response === null) {
            $this->recordTest("API: list_outlets", false, "No response");
            return;
        }

        $passed = isset($response['outlets']) && is_array($response['outlets']);

        $this->recordTest("API: list_outlets", $passed,
            $passed ? count($response['outlets']) . " outlets" : "Invalid structure");
    }

    /**
     * Test supplier list endpoint
     */
    private function testSupplierList(): void
    {
        $response = $this->callApi('list_suppliers');

        if ($response === null) {
            $this->recordTest("API: list_suppliers", false, "No response");
            return;
        }

        $passed = isset($response['suppliers']) && is_array($response['suppliers']);

        $this->recordTest("API: list_suppliers", $passed,
            $passed ? count($response['suppliers']) . " suppliers" : "Invalid structure");
    }

    /**
     * Test product search endpoint
     */
    private function testProductSearch(): void
    {
        $response = $this->callApi('list_products', ['search' => 'vape']);

        if ($response === null) {
            $this->recordTest("API: list_products", false, "No response");
            return;
        }

        $passed = isset($response['products']) && is_array($response['products']);

        $this->recordTest("API: list_products", $passed,
            $passed ? count($response['products']) . " products" : "Invalid structure");
    }

    /**
     * Test Lightspeed API connection
     */
    private function testLightspeedConnection(): void
    {
        $token = getenv('LS_API_TOKEN');
        $baseUrl = getenv('LS_BASE_URL') ?: 'https://vapeshed.retail.lightspeed.app/api/2.0';

        $ch = curl_init("{$baseUrl}/consignments");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $passed = $httpCode === 200;

        $this->recordTest("Lightspeed API Connection", $passed, "HTTP {$httpCode}");
    }

    /**
     * Test consignment list via API
     */
    private function testConsignmentList(): void
    {
        $response = $this->callApi('list_consignments', ['limit' => 5]);

        if ($response === null) {
            $this->recordTest("API: list_consignments", false, "No response");
            return;
        }

        $passed = isset($response['consignments']) && is_array($response['consignments']);

        $this->recordTest("API: list_consignments", $passed,
            $passed ? count($response['consignments']) . " consignments" : "Invalid structure");
    }

    /**
     * Call Transfer Manager API
     */
    private function callApi(string $action, array $additionalData = []): ?array
    {
        $pin = getenv('TRANSFER_MANAGER_PIN');
        if (empty($pin) || $pin === 'your_secure_pin_here') {
            return null;
        }

        $apiPath = realpath(__DIR__ . '/../TransferManager/api.php');
        if (!$apiPath || !file_exists($apiPath)) {
            return null;
        }

        $data = array_merge([
            'action' => $action,
            'pin' => $pin,
            'csrf_token' => $this->csrfToken,
        ], $additionalData);

        $jsonInput = json_encode($data);

        $descriptorSpec = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $envDefaults = [
            'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'],
            'TRANSFER_MANAGER_PIN' => $pin,
            'LS_API_TOKEN' => getenv('LS_API_TOKEN'),
            'LS_BASE_URL' => getenv('LS_BASE_URL') ?: 'https://vapeshed.retail.lightspeed.app/api/2.0',
        ];

        $env = array_merge($_ENV, array_filter($envDefaults, fn($value) => $value !== null));

        $process = proc_open(
            escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($apiPath),
            $descriptorSpec,
            $pipes,
            dirname($apiPath),
            $env
        );

        if (!is_resource($process)) {
            return null;
        }

        fwrite($pipes[0], $jsonInput);
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $this->recordTest("API process ({$action})", false, trim($errorOutput));
            return null;
        }

        $response = json_decode($output, true);
        if (!$response || !isset($response['success'])) {
            return null;
        }

        return $response['success'] ? ($response['data'] ?? null) : null;
    }

    /**
     * Record test result
     */
    private function recordTest(string $name, bool $passed, string $details = ''): void
    {
        $this->testResults[] = [
            'name' => $name,
            'passed' => $passed,
            'details' => $details,
        ];

        $icon = $passed ? '✅' : '❌';
        $status = $passed ? 'PASS' : 'FAIL';

        printf("  %s %-50s %s\n", $icon, $name, $details ? "({$details})" : '');
    }

    /**
     * Print test summary
     */
    private function printSummary(): void
    {
        $total = count($this->testResults);
        $passed = count(array_filter($this->testResults, fn($r) => $r['passed']));
        $failed = $total - $passed;

        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║                      Test Summary                            ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n";
        echo "\n";

        printf("  Total Tests:  %d\n", $total);
        printf("  ✅ Passed:    %d (%.1f%%)\n", $passed, ($passed / $total) * 100);

        if ($failed > 0) {
            printf("  ❌ Failed:    %d (%.1f%%)\n", $failed, ($failed / $total) * 100);

            echo "\n  Failed Tests:\n";
            foreach ($this->testResults as $result) {
                if (!$result['passed']) {
                    printf("    - %s: %s\n", $result['name'], $result['details'] ?: 'No details');
                }
            }
        }

        echo "\n";

        if ($passed === $total) {
            echo "  🎉 All tests passed!\n\n";
            exit(0);
        } else {
            echo "  ⚠️  Some tests failed. Review the output above.\n\n";
            exit(1);
        }
    }
}

// Run tests
try {
    $tester = new TransferManagerTest();
    $tester->runAllTests();
} catch (Exception $e) {
    echo "\n❌ Fatal Error: {$e->getMessage()}\n";
    echo "   {$e->getFile()}:{$e->getLine()}\n\n";
    exit(1);
}
