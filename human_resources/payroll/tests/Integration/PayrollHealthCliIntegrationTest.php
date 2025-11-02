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
class PayrollHealthCliTest extends TestCase
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

        $this->assertStringContainsString('SYSTEM INFO', $fullOutput);
        $this->assertStringContainsString('PHP Version:', $fullOutput);
        $this->assertStringContainsString('Timestamp:', $fullOutput);
        $this->assertStringContainsString('Hostname:', $fullOutput);
    }

    public function testOutputContainsPhpVersion(): void
    {
        $output = [];
        exec("php {$this->cliPath} 2>&1", $output);

        $fullOutput = implode("\n", $output);

        // Should contain PHP version (e.g., 8.1.33)
        $this->assertMatchesRegularExpression('/PHP Version:\s+\d+\.\d+\.\d+/', $fullOutput);
    }

    public function testOutputContainsDatabaseSection(): void
    {
        $output = [];
        exec("php {$this->cliPath} 2>&1", $output);

        $fullOutput = implode("\n", $output);

        $this->assertStringContainsString('DATABASE CONNECTIVITY', $fullOutput);
        $this->assertStringContainsString('Status:', $fullOutput);
    }

    public function testOutputContainsAuthFlagSection(): void
    {
        $output = [];
        exec("php {$this->cliPath} 2>&1", $output);

        $fullOutput = implode("\n", $output);

        $this->assertStringContainsString('AUTHENTICATION FLAG', $fullOutput);
        $this->assertStringContainsString('File:', $fullOutput);
    }

    public function testOutputContainsTableHealthSection(): void
    {
        $output = [];
        exec("php {$this->cliPath} 2>&1", $output);

        $fullOutput = implode("\n", $output);

        $this->assertStringContainsString('TABLE HEALTH', $fullOutput);
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

        $this->assertStringContainsString('SERVICE AVAILABILITY', $fullOutput);
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

        $this->assertStringContainsString('HEALTH ENDPOINT', $fullOutput);
        $this->assertStringContainsString('Location:', $fullOutput);
    }

    public function testOutputContainsRecentActivitySection(): void
    {
        $output = [];
        exec("php {$this->cliPath} 2>&1", $output);

        $fullOutput = implode("\n", $output);

        $this->assertStringContainsString('RECENT ACTIVITY', $fullOutput);
        $this->assertStringContainsString('Last 24 Hours', $fullOutput);
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

        // Should contain section dividers (━ characters)
        $dividerCount = substr_count($fullOutput, '━');

        $this->assertGreaterThan(5, $dividerCount, 'Should have multiple section dividers');
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
        $output1 = [];
        $output2 = [];

        exec("php {$this->cliPath} 2>&1", $output1);
        exec("php {$this->cliPath} 2>&1", $output2);

        $this->assertNotEmpty($output1);
        $this->assertNotEmpty($output2);

        // Both should contain system info
        $fullOutput1 = implode("\n", $output1);
        $fullOutput2 = implode("\n", $output2);

        $this->assertStringContainsString('SYSTEM INFO', $fullOutput1);
        $this->assertStringContainsString('SYSTEM INFO', $fullOutput2);
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
