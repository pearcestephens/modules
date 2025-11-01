<?php
/**
 * Security Configuration Unit Tests
 * 
 * Verifies all Phase 1 security fixes are properly implemented
 * using PHPUnit assertions and real code inspection.
 * 
 * @package CIS\HumanResources\Payroll\Tests\Unit
 */

namespace CIS\HumanResources\Payroll\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SecurityConfigTest extends TestCase
{
    private string $projectRoot;
    private string $payrollDir;
    private string $configDir;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 4);
        $this->payrollDir = $this->projectRoot . '/human_resources/payroll';
        $this->configDir = $this->projectRoot . '/config';
    }

    /**
     * @test
     * @group security
     * @group critical
     */
    public function it_has_centralized_database_config_file(): void
    {
        $configFile = $this->configDir . '/database.php';
        
        $this->assertFileExists(
            $configFile,
            "Centralized database config file must exist at config/database.php"
        );

        $config = require $configFile;

        $this->assertIsArray($config, "Database config must return an array");
        $this->assertArrayHasKey('cis', $config, "Config must have 'cis' database section");
        $this->assertArrayHasKey('vapeshed', $config, "Config must have 'vapeshed' database section");

        // Verify CIS config structure
        $cisConfig = $config['cis'];
        $this->assertArrayHasKey('host', $cisConfig);
        $this->assertArrayHasKey('database', $cisConfig);
        $this->assertArrayHasKey('username', $cisConfig);
        $this->assertArrayHasKey('password', $cisConfig);
        $this->assertArrayHasKey('charset', $cisConfig);
        $this->assertArrayHasKey('options', $cisConfig);

        // Verify it uses environment variables (not hardcoded)
        $configSource = file_get_contents($configFile);
        $this->assertStringContainsString(
            "env('DB_HOST'",
            $configSource,
            "Database config must load host from environment variable"
        );
        $this->assertStringContainsString(
            "env('DB_NAME'",
            $configSource,
            "Database config must load database name from environment variable"
        );
        $this->assertStringContainsString(
            "env('DB_USER'",
            $configSource,
            "Database config must load username from environment variable"
        );
        $this->assertStringContainsString(
            "env('DB_PASSWORD'",
            $configSource,
            "Database config must load password from environment variable"
        );
    }

    /**
     * @test
     * @group security
     * @group critical
     */
    public function it_has_no_hardcoded_database_credentials_in_index(): void
    {
        $indexFile = $this->payrollDir . '/index.php';
        $this->assertFileExists($indexFile);

        $content = file_get_contents($indexFile);

        // Check for specific hardcoded credentials that were in the original code
        $this->assertStringNotContainsString(
            'wprKh9Jq63',
            $content,
            "index.php must not contain hardcoded password 'wprKh9Jq63'"
        );

        $this->assertStringNotContainsString(
            'mysql:host=127.0.0.1;dbname=jcepnzzkmj',
            $content,
            "index.php must not contain hardcoded database DSN"
        );

        $this->assertStringNotContainsString(
            'new PDO("mysql',
            $content,
            "index.php must not instantiate PDO with inline credentials"
        );

        // Verify it loads config instead
        $this->assertStringContainsString(
            "require __DIR__ . '/../../config/database.php'",
            $content,
            "index.php must load database config from centralized file"
        );

        $this->assertStringContainsString(
            "\$dbConfig['cis']",
            $content,
            "index.php must use config array for database credentials"
        );
    }

    /**
     * @test
     * @group security
     * @group critical
     */
    public function it_has_no_hardcoded_credentials_in_tests(): void
    {
        $testFile = $this->payrollDir . '/tests/test_complete_integration.php';
        
        if (!file_exists($testFile)) {
            $this->markTestSkipped("Integration test file not found");
        }

        $content = file_get_contents($testFile);

        $this->assertStringNotContainsString(
            'wprKh9Jq63',
            $content,
            "Test file must not contain hardcoded password 'wprKh9Jq63'"
        );

        $this->assertStringNotContainsString(
            '"jcepnzzkmj", "jcepnzzkmj"',
            $content,
            "Test file must not contain hardcoded username/database"
        );

        // Verify tests use config
        $this->assertStringContainsString(
            "config/database.php",
            $content,
            "Tests must load database config from centralized file"
        );
    }

    /**
     * @test
     * @group security
     * @group critical
     */
    public function it_gates_debug_output_by_environment(): void
    {
        $indexFile = $this->payrollDir . '/index.php';
        $content = file_get_contents($indexFile);

        // Must NOT have always-on debug
        $this->assertStringNotContainsString(
            "ini_set('display_errors', '1');",
            $content,
            "index.php must not have always-on display_errors"
        );

        $this->assertStringNotContainsString(
            "error_reporting(E_ALL);",
            $content,
            "index.php must not have always-on error_reporting without env check"
        );

        // Must have environment-aware debug
        $this->assertStringContainsString(
            "\$appConfig = require __DIR__ . '/../../config/app.php'",
            $content,
            "index.php must load app config for environment awareness"
        );

        $this->assertStringContainsString(
            "\$appConfig['debug']",
            $content,
            "index.php must check debug flag from config"
        );

        $this->assertStringContainsString(
            "\$appConfig['env']",
            $content,
            "index.php must check environment from config"
        );

        $this->assertStringContainsString(
            "!== 'production'",
            $content,
            "index.php must disable debug in production environment"
        );
    }

    /**
     * @test
     * @group security
     * @group critical
     */
    public function it_has_enabled_permission_system_in_base_controller(): void
    {
        $controllerFile = $this->payrollDir . '/controllers/BaseController.php';
        $this->assertFileExists($controllerFile);

        $content = file_get_contents($controllerFile);

        // Must NOT have disabled permissions
        $this->assertStringNotContainsString(
            'TEMPORARILY DISABLED',
            $content,
            "BaseController must not have 'TEMPORARILY DISABLED' permission bypass"
        );

        $this->assertStringNotContainsString(
            'return true; // TEMP:',
            $content,
            "BaseController must not have temporary permission bypass"
        );

        // Must have real permission checking
        $this->assertStringContainsString(
            'function hasPermission',
            $content,
            "BaseController must have hasPermission() method"
        );

        $this->assertStringContainsString(
            "\$this->user['permissions']",
            $content,
            "BaseController must check user permissions array"
        );

        $this->assertStringContainsString(
            'in_array($permission',
            $content,
            "BaseController must validate permission exists in user's permission array"
        );
    }

    /**
     * @test
     * @group security
     * @group critical
     */
    public function it_enforces_permissions_on_routes(): void
    {
        $routesFile = $this->payrollDir . '/routes.php';
        $this->assertFileExists($routesFile);

        $content = file_get_contents($routesFile);

        // Critical routes that MUST have permission checks
        $criticalRoutes = [
            "'/payroll/dashboard'" => 'payroll.view_dashboard',
            "'/api/payroll/dashboard/data'" => 'payroll.view_dashboard',
            "'/payroll/payruns'" => 'payroll.view_payruns',
            "'/payroll/payrun/:periodKey'" => 'payroll.view_payruns',
        ];

        foreach ($criticalRoutes as $route => $expectedPermission) {
            // Find the route definition
            $routePattern = preg_quote($route, '/');
            $this->assertMatchesRegularExpression(
                "/{$routePattern}/",
                $content,
                "Route {$route} must be defined in routes.php"
            );

            // Verify permission is NOT commented out
            // Look for the pattern: 'permission' => 'payroll.xxx' (not commented)
            $permissionPattern = "/'permission'\s*=>\s*['\"]" . preg_quote($expectedPermission, '/') . "['\"]/";
            $this->assertMatchesRegularExpression(
                "/{$permissionPattern}/",
                $content,
                "Route {$route} must have active permission check for '{$expectedPermission}'"
            );

            // Ensure it's not commented out
            $lines = explode("\n", $content);
            $found = false;
            foreach ($lines as $line) {
                if (preg_match("/{$permissionPattern}/", $line) && !preg_match('/^\s*\/\//', $line)) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue(
                $found,
                "Permission check for {$route} must not be commented out"
            );
        }
    }

    /**
     * @test
     * @group security
     */
    public function it_has_required_controller_api_methods(): void
    {
        $controllerFile = $this->payrollDir . '/controllers/BaseController.php';
        $content = file_get_contents($controllerFile);

        // Check for jsonSuccess method
        $this->assertStringContainsString(
            'function jsonSuccess',
            $content,
            "BaseController must have jsonSuccess() method"
        );

        // Check for jsonError method
        $this->assertStringContainsString(
            'function jsonError',
            $content,
            "BaseController must have jsonError() method"
        );

        // Verify method signatures
        $this->assertMatchesRegularExpression(
            '/public\s+function\s+jsonSuccess\s*\(\s*string\s+\$message/i',
            $content,
            "jsonSuccess() must accept string message parameter"
        );

        $this->assertMatchesRegularExpression(
            '/public\s+function\s+jsonError\s*\(\s*string\s+\$message/i',
            $content,
            "jsonError() must accept string message parameter"
        );
    }

    /**
     * @test
     * @group syntax
     */
    public function it_has_valid_php_syntax_in_all_modified_files(): void
    {
        $filesToCheck = [
            $this->configDir . '/database.php',
            $this->payrollDir . '/index.php',
            $this->payrollDir . '/controllers/BaseController.php',
            $this->payrollDir . '/routes.php',
        ];

        foreach ($filesToCheck as $file) {
            $this->assertFileExists($file, "File must exist: {$file}");

            $output = [];
            $returnCode = 0;
            exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $returnCode);

            $this->assertEquals(
                0,
                $returnCode,
                "File must have valid PHP syntax: {$file}\nOutput: " . implode("\n", $output)
            );

            $this->assertStringContainsString(
                'No syntax errors detected',
                implode("\n", $output),
                "PHP lint must confirm no syntax errors in {$file}"
            );
        }
    }

    /**
     * @test
     * @group security
     */
    public function it_does_not_expose_sensitive_data_in_error_messages(): void
    {
        $indexFile = $this->payrollDir . '/index.php';
        $content = file_get_contents($indexFile);

        // Check that error handling doesn't expose credentials
        $this->assertStringNotContainsString(
            'echo $e->getMessage()',
            $content,
            "index.php must not echo raw exception messages that could contain credentials"
        );

        // Verify it uses error_log instead
        $this->assertStringContainsString(
            'error_log(',
            $content,
            "index.php must use error_log() for secure error handling"
        );
    }

    /**
     * @test
     * @group configuration
     */
    public function it_has_proper_pdo_options_in_database_config(): void
    {
        $configFile = $this->configDir . '/database.php';
        $config = require $configFile;

        $cisConfig = $config['cis'];
        $this->assertArrayHasKey('options', $cisConfig);

        $options = $cisConfig['options'];
        
        // Must use exceptions for error handling
        $this->assertArrayHasKey(
            \PDO::ATTR_ERRMODE,
            $options,
            "PDO config must set error mode"
        );
        $this->assertEquals(
            \PDO::ERRMODE_EXCEPTION,
            $options[\PDO::ATTR_ERRMODE],
            "PDO must use ERRMODE_EXCEPTION for secure error handling"
        );

        // Must use associative arrays
        $this->assertArrayHasKey(
            \PDO::ATTR_DEFAULT_FETCH_MODE,
            $options,
            "PDO config must set default fetch mode"
        );
        $this->assertEquals(
            \PDO::FETCH_ASSOC,
            $options[\PDO::ATTR_DEFAULT_FETCH_MODE],
            "PDO must use FETCH_ASSOC for consistent data handling"
        );

        // Must disable emulated prepares for security
        $this->assertArrayHasKey(
            \PDO::ATTR_EMULATE_PREPARES,
            $options,
            "PDO config must set emulated prepares"
        );
        $this->assertFalse(
            $options[\PDO::ATTR_EMULATE_PREPARES],
            "PDO must disable emulated prepares for real prepared statements"
        );
    }
}
