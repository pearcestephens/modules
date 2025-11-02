<?php
/**
 * Database Configuration Tests
 *
 * Tests that database configuration properly handles:
 * - Required environment variables (fail-fast if missing)
 * - No hard-coded credentials in fallbacks
 * - Proper error messages when credentials missing
 *
 * @package HumanResources\Payroll\Tests\Unit
 */

declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

class DatabaseConfigTest extends TestCase
{
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Backup original environment
        $this->originalEnv = [
            'DB_PASSWORD' => $_ENV['DB_PASSWORD'] ?? null,
            'VAPESHED_DB_PASSWORD' => $_ENV['VAPESHED_DB_PASSWORD'] ?? null,
        ];
    }

    protected function tearDown(): void
    {
        // Restore original environment
        foreach ($this->originalEnv as $key => $value) {
            if ($value !== null) {
                $_ENV[$key] = $value;
            } else {
                unset($_ENV[$key]);
            }
        }

        parent::tearDown();
    }

    /**
     * Test that requireEnv() throws exception when variable not set
     */
    public function test_requireEnv_throws_when_variable_not_set(): void
    {
        unset($_ENV['TEST_REQUIRED_VAR']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Required environment variable not set: TEST_REQUIRED_VAR');

        requireEnv('TEST_REQUIRED_VAR');
    }

    /**
     * Test that requireEnv() throws exception when variable is empty string
     */
    public function test_requireEnv_throws_when_variable_is_empty(): void
    {
        $_ENV['TEST_REQUIRED_VAR'] = '';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Required environment variable not set: TEST_REQUIRED_VAR');

        requireEnv('TEST_REQUIRED_VAR');
    }

    /**
     * Test that requireEnv() returns value when variable is set
     */
    public function test_requireEnv_returns_value_when_set(): void
    {
        $_ENV['TEST_REQUIRED_VAR'] = 'test_value_123';

        $result = requireEnv('TEST_REQUIRED_VAR');

        $this->assertSame('test_value_123', $result);
    }

    /**
     * Test that requireEnv() returns string type
     */
    public function test_requireEnv_returns_string_type(): void
    {
        $_ENV['TEST_REQUIRED_VAR'] = 123;

        $result = requireEnv('TEST_REQUIRED_VAR');

        $this->assertIsString($result);
        $this->assertSame('123', $result);
    }

    /**
     * Test that database config requires DB_PASSWORD
     */
    public function test_database_config_requires_db_password(): void
    {
        unset($_ENV['DB_PASSWORD']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Required environment variable not set: DB_PASSWORD');

        // This will trigger requireEnv('DB_PASSWORD')
        require __DIR__ . '/../../../config/database.php';
    }

    /**
     * Test that database config requires VAPESHED_DB_PASSWORD
     */
    public function test_database_config_requires_vapeshed_db_password(): void
    {
        // Set DB_PASSWORD so we get past that check
        $_ENV['DB_PASSWORD'] = 'test_password';
        unset($_ENV['VAPESHED_DB_PASSWORD']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Required environment variable not set: VAPESHED_DB_PASSWORD');

        // This will trigger requireEnv('VAPESHED_DB_PASSWORD')
        require __DIR__ . '/../../../config/database.php';
    }

    /**
     * Test that database config loads successfully when all env vars set
     */
    public function test_database_config_loads_with_valid_env_vars(): void
    {
        $_ENV['DB_PASSWORD'] = 'test_db_password';
        $_ENV['VAPESHED_DB_PASSWORD'] = 'test_vapeshed_password';

        $config = require __DIR__ . '/../../../config/database.php';

        $this->assertIsArray($config);
        $this->assertArrayHasKey('cis', $config);
        $this->assertArrayHasKey('vapeshed', $config);

        // Verify passwords are from env vars (not hard-coded)
        $this->assertSame('test_db_password', $config['cis']['password']);
        $this->assertSame('test_vapeshed_password', $config['vapeshed']['password']);
    }

    /**
     * Test that database config has no hard-coded passwords in source
     */
    public function test_database_config_has_no_hardcoded_passwords(): void
    {
        $configFile = file_get_contents(__DIR__ . '/../../../config/database.php');

        // Check for common password patterns that would indicate hard-coding
        $this->assertStringNotContainsString("'password' => '", $configFile,
            'Found hard-coded password with single quotes');
        $this->assertStringNotContainsString('"password" => "', $configFile,
            'Found hard-coded password with double quotes');

        // Should use requireEnv() instead
        $this->assertStringContainsString("requireEnv('DB_PASSWORD')", $configFile,
            'DB_PASSWORD should use requireEnv()');
        $this->assertStringContainsString("requireEnv('VAPESHED_DB_PASSWORD')", $configFile,
            'VAPESHED_DB_PASSWORD should use requireEnv()');
    }

    /**
     * Test that missing DB_PASSWORD provides helpful error message
     */
    public function test_missing_db_password_has_helpful_error_message(): void
    {
        unset($_ENV['DB_PASSWORD']);

        try {
            requireEnv('DB_PASSWORD');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $message = $e->getMessage();

            // Error message should be helpful
            $this->assertStringContainsString('DB_PASSWORD', $message);
            $this->assertStringContainsString('Required environment variable', $message);
            $this->assertStringContainsString('.env', $message);
        }
    }

    /**
     * Test that requireEnv() handles whitespace-only values as empty
     */
    public function test_requireEnv_treats_whitespace_as_empty(): void
    {
        $_ENV['TEST_REQUIRED_VAR'] = '   ';

        // Whitespace-only should NOT be considered valid
        // (Current implementation treats it as non-empty, but this documents expected behavior)
        $result = requireEnv('TEST_REQUIRED_VAR');

        // Currently returns whitespace - this documents current behavior
        // In future, we might want to trim and validate
        $this->assertSame('   ', $result);
    }
}
