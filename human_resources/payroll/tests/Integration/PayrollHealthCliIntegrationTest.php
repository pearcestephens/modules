<?php

declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for payroll-health.php CLI tool
 *
 * Tests complete health check execution and output validation
 *
 * @covers cli/payroll-health.php
 */
class PayrollHealthCliIntegrationTest extends TestCase
{
    private string $cliPath;

    protected function setUp(): void
    {
        $this->cliPath = __DIR__ . '/../../cli/payroll-health.php';

        $this->assertTrue(
            file_exists($this->cliPath),
            'Health CLI script should exist'
        );
    }

    public function testCliScriptIsExecutable(): void
    {
        $this->assertTrue(
            is_readable($this->cliPath),
            'Health CLI should be readable'
        );
    }

    public function testCliScriptHasValidPhpSyntax(): void
    {
        $output = [];
        $returnVar = 0;

        exec("php -l {$this->cliPath} 2>&1", $output, $returnVar);

        $this->assertEquals(0, $returnVar, 'CLI script should have valid PHP syntax');
        $this->assertStringContainsString('No syntax errors', implode("\n", $output));
    }

    public function testCliScriptExecutesWithoutErrors(): void
    {
        $output = [];
        $returnVar = 0;

        exec("php {$this->cliPath} 2>&1", $output, $returnVar);

        $this->assertEquals(0, $returnVar, 'CLI script should execute without errors');
        $this->assertNotEmpty($output, 'CLI script should produce output');
    }

    public function testOutputContainsHeaderSection(): void
    {
        $output = [];
        exec("php {$this->cliPath} 2>&1", $output);

        $fullOutput = implode("\n", $output);

        $this->assertStringContainsString('PAYROLL MODULE HEALTH CHECK', $fullOutput);
        $this->assertStringContainsString('╔', $fullOutput, 'Should contain box drawing characters');
        $this->assertStringContainsString('╚', $fullOutput, 'Should contain box drawing characters');
    }

    public function testOutputContainsSystemInfo(): void
    {
        $output = [];
        exec("php {$this->cliPath} 2>&1", $output);


        $fullOutput = implode("\n", $output);

        $this->assertStringContainsString('System Information', $fullOutput);
        $this->assertStringContainsString('PHP Version:', $fullOutput);
        $this->assertStringContainsString('Timestamp:', $fullOutput);
        $this->assertStringContainsString('Server:', $fullOutput);
    }

    public function testOutputContainsPhpVersion(): void
    {
        exec("php {$this->cliPath} 2>&1", $output);
        $fullOutput = implode("\n", $output);

        $this->assertMatchesRegularExpression(
            '/PHP Version:\s+\d+\.\d+\.\d+/',
            $fullOutput,
            'Output should contain PHP version in X.Y.Z format'
        );
    }

    public function testOutputContainsDatabaseSection(): void
    {
        $output = [];
        exec("php {$this->cliPath} 2>&1", $output);
        $fullOutput = implode("\n", $output);

        $this->assertStringContainsString('Database Connectivity', $fullOutput);
    }

    public function testOutputContainsAuthFlagSection(): void
    {
        $output = [];
        exec("php {$this->cliPath} 2>&1", $output);
        $fullOutput = implode("\n", $output);

        $this->assertStringContainsString('Authentication Status', $fullOutput);
    }

    public function testOutputContainsTableHealthSection(): void
    {
        $output = [];
        exec("php {$this->cliPath} 2>&1", $output);
        $fullOutput = implode("\n", $output);

        $this->assertStringContainsString('Database Tables', $fullOutput);
    }

    public function testOutputContainsExpectedTables(): void
    {
        $output = [];
        exec("php {$this->cliPath} 2>&1", $output);

        $fullOutput = implode("\n", $output);

        $expectedTables = [
            'deputy_timesheets',
            'payroll_activity_log',
            'payroll_rate_limits',
            'payroll_auth_audit_log',
        ];

        foreach ($expectedTables as $table) {
            $this->assertStringContainsString($table, $fullOutput, "Should check {$table} table");
        }
    }

    public function testOutputContainsServiceSection(): void
    {
        $output = [];
        exec("php {$this->cliPath} 2>&1", $output);
        $fullOutput = implode("\n", $output);

        $this->assertStringContainsString('Services', $fullOutput);
    }

    public function testOutputContainsExpectedServices(): void
    {
        $output = [];
        exec("php {$this->cliPath} 2>&1", $output);

        $fullOutput = implode("\n", $output);

        $expectedServices = [
            'PayrollDeputyService',
            'PayrollXeroService',
            'ReconciliationService',
            'HttpRateLimitReporter',
            'PayrollAuthAuditService',
        ];

        foreach ($expectedServices as $service) {
            $this->assertStringContainsString($service, $fullOutput, "Should check {$service}");
        }
    }

    public function testOutputContainsHealthEndpointSection(): void
    {
        $output = [];
        exec("php {$this->cliPath} 2>&1", $output);
        $fullOutput = implode("\n", $output);

        $this->assertStringContainsString('Health Endpoint', $fullOutput);
        $this->assertStringContainsString('Location:', $fullOutput);
    }

    public function testOutputContainsRecentActivitySection(): void
    {
        $output = [];
        exec("php {$this->cliPath} 2>&1", $output);
        $fullOutput = implode("\n", $output);

        $this->assertStringContainsString('Recent Activity', $fullOutput);
    }

    public function testOutputUsesStatusIndicators(): void
    {
        $output = [];
        exec("php {$this->cliPath} 2>&1", $output);

        $fullOutput = implode("\n", $output);

        // Should contain emoji or status indicators
        $hasStatusIndicators =
            strpos($fullOutput, '✅') !== false ||
            strpos($fullOutput, '❌') !== false ||
            strpos($fullOutput, 'OK') !== false ||
            strpos($fullOutput, 'FOUND') !== false;

        $this->assertTrue($hasStatusIndicators, 'Should contain status indicators');
    }

    public function testOutputIsFormattedWithSectionDividers(): void
    {
        $output = [];
        exec("php {$this->cliPath} 2>&1", $output);
        $fullOutput = implode("\n", $output);

        // Check for box drawing characters used in formatting
        $boxChars = ['═', '║', '╔', '╚'];
        $foundBoxChars = false;
        foreach ($boxChars as $char) {
            if (str_contains($fullOutput, $char)) {
                $foundBoxChars = true;
                break;
            }
        }

        $this->assertTrue($foundBoxChars, 'Should have formatted section dividers with box drawing chars');
    }

    public function testExecutionTimeIsReasonable(): void
    {
        $startTime = microtime(true);

        exec("php {$this->cliPath} 2>&1");

        $executionTime = microtime(true) - $startTime;

        $this->assertLessThan(5.0, $executionTime, 'Health check should complete in under 5 seconds');
    }

    public function testScriptCanRunMultipleTimes(): void
    {
        // First run
        $output1 = [];
        exec("php {$this->cliPath} 2>&1", $output1);
        $fullOutput1 = implode("\n", $output1);

        // Second run
        $output2 = [];
        exec("php {$this->cliPath} 2>&1", $output2);
        $fullOutput2 = implode("\n", $output2);

        // Both should contain system info
        $this->assertStringContainsString('System Information', $fullOutput1);
        $this->assertStringContainsString('System Information', $fullOutput2);
    }

    public function testOutputContainsTimestamp(): void
    {
        $output = [];
        exec("php {$this->cliPath} 2>&1", $output);

        $fullOutput = implode("\n", $output);

        // Should contain a timestamp in format YYYY-MM-DD HH:MM:SS
        $this->assertMatchesRegularExpression(
            '/\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}/',
            $fullOutput,
            'Should contain properly formatted timestamp'
        );
    }

    public function testScriptDoesNotExposeSecrets(): void
    {
        $output = [];
        exec("php {$this->cliPath} 2>&1", $output);

        $fullOutput = implode("\n", $output);

        // Should NOT contain sensitive data
        $sensitivePatterns = [
            '/password/i',
            '/api[_-]?key/i',
            '/secret/i',
            '/token/i',
            '/wprKh9Jq63/', // Actual password
        ];

        foreach ($sensitivePatterns as $pattern) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                $fullOutput,
                "Should not expose sensitive information matching {$pattern}"
            );
        }
    }
}
