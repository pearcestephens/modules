#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Comprehensive Test Runner for PAYROLL R2
 *
 * Runs all unit, integration, and web tests with detailed reporting
 *
 * Usage:
 *   php run-all-tests.php
 *   php run-all-tests.php --unit
 *   php run-all-tests.php --integration
 *   php run-all-tests.php --web
 *   php run-all-tests.php --fast (skips slow tests)
 */

class TestRunner
{
    private array $results = [];
    private float $startTime;
    private int $totalTests = 0;
    private int $passedTests = 0;
    private int $failedTests = 0;

    private array $testSuites = [
        'unit' => [
            'tests/Unit/PayrollAuthAuditServiceTest.php',
            'tests/Unit/Migrations/Migration003Test.php',
        ],
        'integration' => [
            'tests/Integration/PayrollAuthAuditIntegrationTest.php',
            'tests/Integration/PayrollHealthCliIntegrationTest.php',
        ],
        'web' => [
            'tests/Web/HealthEndpointTest.php',
        ],
    ];

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    public function run(array $options = []): void
    {
        $this->printHeader();

        $suites = $this->determineSuitesToRun($options);

        foreach ($suites as $suiteName) {
            $this->runTestSuite($suiteName);
        }

        $this->printSummary();
    }

    private function determineSuitesToRun(array $options): array
    {
        if (isset($options['unit'])) {
            return ['unit'];
        }
        if (isset($options['integration'])) {
            return ['integration'];
        }
        if (isset($options['web'])) {
            return ['web'];
        }

        // Run all by default
        return array_keys($this->testSuites);
    }

    private function runTestSuite(string $suiteName): void
    {
        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║  " . str_pad(strtoupper($suiteName) . " TESTS", 58) . "  ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n";
        echo "\n";

        if (!isset($this->testSuites[$suiteName])) {
            echo "⚠️  Test suite '{$suiteName}' not found\n";
            return;
        }

        foreach ($this->testSuites[$suiteName] as $testFile) {
            $this->runTestFile($testFile, $suiteName);
        }
    }

    private function runTestFile(string $testFile, string $suiteName): void
    {
        $fullPath = __DIR__ . '/' . $testFile;

        if (!file_exists($fullPath)) {
            echo "⚠️  Test file not found: {$testFile}\n";
            return;
        }

        echo "Running: " . basename($testFile) . "\n";

        // Syntax check first
        $syntaxCheck = $this->checkSyntax($fullPath);
        if (!$syntaxCheck['ok']) {
            echo "  ❌ Syntax error: {$syntaxCheck['error']}\n";
            $this->failedTests++;
            return;
        }

        // Run the test
        $result = $this->executeTest($fullPath, $suiteName);

        if ($result['ok']) {
            echo "  ✅ PASSED ({$result['tests']} tests, {$result['assertions']} assertions, {$result['time']}s)\n";
            $this->passedTests += $result['tests'];
            $this->totalTests += $result['tests'];
        } else {
            echo "  ❌ FAILED\n";
            echo "  Error: {$result['error']}\n";
            $this->failedTests += $result['tests'];
            $this->totalTests += $result['tests'];
        }

        $this->results[] = [
            'suite' => $suiteName,
            'file' => basename($testFile),
            'result' => $result,
        ];
    }

    private function checkSyntax(string $file): array
    {
        exec("php -l {$file} 2>&1", $output, $returnVar);

        return [
            'ok' => $returnVar === 0,
            'error' => $returnVar === 0 ? null : implode("\n", $output),
        ];
    }

    private function executeTest(string $file, string $suiteName): array
    {
        // For unit/integration tests, we can require and run them
        // For web tests, we may need to skip if no web server

        if ($suiteName === 'web' && getenv('SKIP_WEB_TESTS') === 'true') {
            return [
                'ok' => true,
                'tests' => 0,
                'assertions' => 0,
                'time' => 0,
                'skipped' => true,
            ];
        }

        // Use PHPUnit if available, otherwise basic execution
        if ($this->hasPhpUnit()) {
            return $this->runWithPhpUnit($file);
        } else {
            return $this->runWithBasicExecution($file);
        }
    }

    private function hasPhpUnit(): bool
    {
        exec('which phpunit 2>/dev/null', $output, $returnVar);
        return $returnVar === 0;
    }

    private function runWithPhpUnit(string $file): array
    {
        $startTime = microtime(true);

        exec("phpunit --colors=never {$file} 2>&1", $output, $returnVar);

        $executionTime = round(microtime(true) - $startTime, 2);
        $outputString = implode("\n", $output);

        // Parse PHPUnit output
        $tests = 0;
        $assertions = 0;

        if (preg_match('/OK \((\d+) tests?, (\d+) assertions?\)/', $outputString, $matches)) {
            $tests = (int)$matches[1];
            $assertions = (int)$matches[2];
        } elseif (preg_match('/Tests: (\d+)/', $outputString, $matches)) {
            $tests = (int)$matches[1];
        }

        return [
            'ok' => $returnVar === 0,
            'tests' => $tests,
            'assertions' => $assertions,
            'time' => $executionTime,
            'error' => $returnVar === 0 ? null : $outputString,
        ];
    }

    private function runWithBasicExecution(string $file): array
    {
        $startTime = microtime(true);

        exec("php {$file} 2>&1", $output, $returnVar);

        $executionTime = round(microtime(true) - $startTime, 2);

        return [
            'ok' => $returnVar === 0,
            'tests' => 1,
            'assertions' => 1,
            'time' => $executionTime,
            'error' => $returnVar === 0 ? null : implode("\n", $output),
        ];
    }

    private function printHeader(): void
    {
        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║  PAYROLL R2 - COMPREHENSIVE TEST SUITE                      ║\n";
        echo "║  Testing: Auth Audit, Health CLI, Migrations, Web APIs      ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n";
    }

    private function printSummary(): void
    {
        $totalTime = round(microtime(true) - $this->startTime, 2);

        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║  TEST SUMMARY                                                ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n";
        echo "\n";

        echo "Total Tests:    {$this->totalTests}\n";
        echo "Passed:         " . $this->colorize($this->passedTests, 'green') . "\n";
        echo "Failed:         " . $this->colorize($this->failedTests, $this->failedTests > 0 ? 'red' : 'green') . "\n";
        echo "Execution Time: {$totalTime}s\n";
        echo "\n";

        if ($this->failedTests === 0) {
            echo "✅ ALL TESTS PASSED!\n";
            echo "\n";
            exit(0);
        } else {
            echo "❌ SOME TESTS FAILED\n";
            echo "\n";
            exit(1);
        }
    }

    private function colorize(string|int $text, string $color): string
    {
        $colors = [
            'green' => "\033[32m",
            'red' => "\033[31m",
            'yellow' => "\033[33m",
            'reset' => "\033[0m",
        ];

        return $colors[$color] . $text . $colors['reset'];
    }
}

// Parse command line arguments
$options = [];
foreach ($argv as $arg) {
    if (strpos($arg, '--') === 0) {
        $options[substr($arg, 2)] = true;
    }
}

// Run tests
$runner = new TestRunner();
$runner->run($options);
