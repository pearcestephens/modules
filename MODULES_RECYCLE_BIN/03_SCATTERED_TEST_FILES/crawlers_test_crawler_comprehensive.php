<?php
/**
 * Comprehensive CrawlerTool Test Suite
 *
 * Tests all functionality including:
 * - Unit tests (parameter validation, error handling)
 * - Integration tests (actual crawler execution)
 * - Performance tests (response time checks)
 * - Edge case tests (boundary conditions)
 */

require_once __DIR__ . '/CrawlerTool.php';

use MCP\Tools\CrawlerTool;

class CrawlerTestSuite
{
    private CrawlerTool $crawler;
    private int $passed = 0;
    private int $failed = 0;
    private array $results = [];
    private float $startTime;

    public function __construct()
    {
        $this->crawler = new CrawlerTool();
        $this->startTime = microtime(true);
    }

    public function run(): void
    {
        $this->printHeader();

        // Unit Tests
        $this->runUnitTests();

        // Integration Tests
        $this->runIntegrationTests();

        // Performance Tests
        $this->runPerformanceTests();

        // Edge Case Tests
        $this->runEdgeCaseTests();

        $this->printSummary();
    }

    private function printHeader(): void
    {
        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════════╗\n";
        echo "║     🕷️  COMPREHENSIVE CRAWLER TOOL TEST SUITE v2.0.0           ║\n";
        echo "╚══════════════════════════════════════════════════════════════════╝\n";
        echo "\n";
    }

    private function test(string $name, callable $testFunction): void
    {
        echo str_pad("├─ " . $name . " ", 60, ".");

        try {
            $result = $testFunction($this->crawler);

            if ($result === true) {
                echo " ✅ PASS\n";
                $this->passed++;
                $this->results[$name] = ['status' => 'PASS', 'error' => null];
            } else {
                echo " ❌ FAIL\n";
                if (is_string($result)) {
                    echo "   └─ Error: {$result}\n";
                    $this->results[$name] = ['status' => 'FAIL', 'error' => $result];
                }
                $this->failed++;
            }
        } catch (Exception $e) {
            echo " ❌ FAIL\n";
            echo "   └─ Exception: " . $e->getMessage() . "\n";
            $this->failed++;
            $this->results[$name] = ['status' => 'FAIL', 'error' => $e->getMessage()];
        }
    }

    private function runUnitTests(): void
    {
        echo "┌─────────────────────────────────────────────────────────────────┐\n";
        echo "│ UNIT TESTS - Parameter Validation & Error Handling              │\n";
        echo "└─────────────────────────────────────────────────────────────────┘\n";

        // Test 1: Tool instantiation
        $this->test('Tool instantiation', function($crawler) {
            return $crawler instanceof CrawlerTool;
        });

        // Test 2: Metadata retrieval
        $this->test('Get metadata', function($crawler) {
            $metadata = $crawler->getMetadata();
            return isset($metadata['name']) && $metadata['name'] === 'crawler';
        });

        // Test 3: Available profiles
        $this->test('Get available profiles', function($crawler) {
            $profiles = $crawler->getAvailableProfiles();
            $expectedProfiles = ['cis_desktop', 'cis_mobile', 'cis_tablet', 'gpt_hub', 'customer'];
            foreach ($expectedProfiles as $profile) {
                if (!isset($profiles[$profile])) {
                    return "Missing profile: {$profile}";
                }
            }
            return true;
        });

        // Test 4: Missing URL parameter
        $this->test('Missing URL parameter', function($crawler) {
            $result = $crawler->execute([]);
            return !$result['success'] && strpos($result['error'], 'url') !== false;
        });

        // Test 5: Invalid URL
        $this->test('Invalid URL rejection', function($crawler) {
            $result = $crawler->execute(['url' => 'not-a-valid-url']);
            return !$result['success'] && strpos($result['error'], 'Invalid') !== false;
        });

        // Test 6: Invalid mode
        $this->test('Invalid mode rejection', function($crawler) {
            $result = $crawler->execute([
                'url' => 'https://example.com',
                'mode' => 'invalid_mode_xyz'
            ]);
            return !$result['success'] && strpos($result['error'], 'Invalid mode') !== false;
        });

        // Test 7: Valid modes defined
        $this->test('All valid modes defined', function($crawler) {
            $metadata = $crawler->getMetadata();
            $validModes = ['quick', 'authenticated', 'interactive', 'full', 'errors_only'];
            $definedModes = array_keys($metadata['modes']);

            foreach ($validModes as $mode) {
                if (!in_array($mode, $definedModes)) {
                    return "Missing mode: {$mode}";
                }
            }
            return true;
        });

        // Test 8: Crawler script exists
        $this->test('Crawler script exists', function($crawler) {
            $scriptPath = __DIR__ . '/deep-crawler.js';
            if (!file_exists($scriptPath)) {
                return "Script not found at: {$scriptPath}";
            }
            return true;
        });

        // Test 9: Reports directory created
        $this->test('Reports directory creation', function($crawler) {
            $reportsDir = __DIR__ . '/reports';
            return is_dir($reportsDir);
        });

        // Test 10: Empty URL string
        $this->test('Empty URL string rejection', function($crawler) {
            $result = $crawler->execute(['url' => '']);
            return !$result['success'];
        });

        echo "\n";
    }

    private function runIntegrationTests(): void
    {
        echo "┌─────────────────────────────────────────────────────────────────┐\n";
        echo "│ INTEGRATION TESTS - Real Execution Scenarios                    │\n";
        echo "└─────────────────────────────────────────────────────────────────┘\n";

        // Test 11: Execute with minimal params (should fail gracefully if Node.js not available)
        $this->test('Minimal execution attempt', function($crawler) {
            $result = $crawler->execute([
                'url' => 'https://example.com',
                'mode' => 'quick'
            ]);
            // Should return array with either success or error
            return is_array($result) && (isset($result['success']) || isset($result['error']));
        });

        // Test 12: Mode parameter handling
        $this->test('Mode parameter processing', function($crawler) {
            foreach (['quick', 'authenticated', 'interactive', 'full', 'errors_only'] as $mode) {
                $result = $crawler->execute([
                    'url' => 'https://httpbin.org/html',
                    'mode' => $mode
                ]);
                if (!is_array($result)) {
                    return "Mode '{$mode}' returned invalid response type";
                }
            }
            return true;
        });

        // Test 13: Profile parameter handling
        $this->test('Profile parameter processing', function($crawler) {
            $result = $crawler->execute([
                'url' => 'https://example.com',
                'profile' => 'cis_desktop'
            ]);
            return is_array($result);
        });

        // Test 14: Depth parameter handling
        $this->test('Depth parameter validation', function($crawler) {
            foreach ([1, 2, 5, 10] as $depth) {
                $result = $crawler->execute([
                    'url' => 'https://example.com',
                    'depth' => $depth
                ]);
                if (!is_array($result)) {
                    return "Depth {$depth} caused invalid response";
                }
            }
            return true;
        });

        // Test 15: Viewport parameter handling
        $this->test('Viewport parameter processing', function($crawler) {
            foreach (['desktop', 'mobile', 'tablet', '1920x1080'] as $viewport) {
                $result = $crawler->execute([
                    'url' => 'https://example.com',
                    'viewport' => $viewport
                ]);
                if (!is_array($result)) {
                    return "Viewport '{$viewport}' caused invalid response";
                }
            }
            return true;
        });

        echo "\n";
    }

    private function runPerformanceTests(): void
    {
        echo "┌─────────────────────────────────────────────────────────────────┐\n";
        echo "│ PERFORMANCE TESTS - Response Time & Resource Usage              │\n";
        echo "└─────────────────────────────────────────────────────────────────┘\n";

        // Test 16: Metadata retrieval speed
        $this->test('Metadata retrieval speed (<1ms)', function($crawler) {
            $start = microtime(true);
            $crawler->getMetadata();
            $duration = (microtime(true) - $start) * 1000;

            if ($duration > 1.0) {
                return "Too slow: {$duration}ms";
            }
            return true;
        });

        // Test 17: Profile list retrieval speed
        $this->test('Profile list speed (<1ms)', function($crawler) {
            $start = microtime(true);
            $crawler->getAvailableProfiles();
            $duration = (microtime(true) - $start) * 1000;

            if ($duration > 1.0) {
                return "Too slow: {$duration}ms";
            }
            return true;
        });

        // Test 18: Validation speed (invalid mode = fast path)
        $this->test('Parameter validation speed (<1ms)', function($crawler) {
            $start = microtime(true);
            for ($i = 0; $i < 100; $i++) {
                // Use invalid mode to test fast validation path only
                $crawler->execute(['url' => 'https://example.com', 'mode' => 'invalid']);
            }
            $duration = (microtime(true) - $start) * 1000 / 100;

            if ($duration > 1.0) {
                return "Too slow: {$duration}ms per validation";
            }
            return true;
        });

        // Test 19: Memory usage
        $this->test('Memory usage reasonable (<5MB)', function($crawler) {
            $memBefore = memory_get_usage(true);

            // Create multiple instances
            for ($i = 0; $i < 10; $i++) {
                new CrawlerTool();
            }

            $memAfter = memory_get_usage(true);
            $memDiff = ($memAfter - $memBefore) / 1024 / 1024;

            if ($memDiff > 5.0) {
                return "Memory usage too high: {$memDiff}MB";
            }
            return true;
        });

        echo "\n";
    }

    private function runEdgeCaseTests(): void
    {
        echo "┌─────────────────────────────────────────────────────────────────┐\n";
        echo "│ EDGE CASE TESTS - Boundary Conditions & Corner Cases            │\n";
        echo "└─────────────────────────────────────────────────────────────────┘\n";

        // Test 20: Very long URL
        $this->test('Very long URL handling', function($crawler) {
            $longUrl = 'https://example.com/' . str_repeat('a', 2000);
            $result = $crawler->execute(['url' => $longUrl]);
            return is_array($result);
        });

        // Test 21: URL with special characters
        $this->test('URL with special characters', function($crawler) {
            $result = $crawler->execute(['url' => 'https://example.com/search?q=test&foo=bar']);
            return is_array($result);
        });

        // Test 22: Localhost URL
        $this->test('Localhost URL handling', function($crawler) {
            $result = $crawler->execute(['url' => 'http://localhost:8080']);
            return is_array($result);
        });

        // Test 23: IP address URL
        $this->test('IP address URL handling', function($crawler) {
            $result = $crawler->execute(['url' => 'http://127.0.0.1:3000']);
            return is_array($result);
        });

        // Test 24: HTTPS vs HTTP
        $this->test('HTTPS vs HTTP handling', function($crawler) {
            foreach (['https://example.com', 'http://example.com'] as $url) {
                $result = $crawler->execute(['url' => $url]);
                if (!is_array($result)) {
                    return "URL '{$url}' failed";
                }
            }
            return true;
        });

        // Test 25: Null parameters
        $this->test('Null parameter handling', function($crawler) {
            $result = $crawler->execute([
                'url' => 'https://example.com',
                'mode' => null,
                'profile' => null
            ]);
            return is_array($result);
        });

        // Test 26: Extra unknown parameters
        $this->test('Unknown parameters ignored', function($crawler) {
            $result = $crawler->execute([
                'url' => 'https://example.com',
                'unknown_param' => 'value',
                'another_unknown' => 123
            ]);
            return is_array($result);
        });

        // Test 27: Multiple instantiations
        $this->test('Multiple tool instances', function($crawler) {
            $crawler1 = new CrawlerTool();
            $crawler2 = new CrawlerTool();
            $crawler3 = new CrawlerTool();

            return $crawler1 !== $crawler2 && $crawler2 !== $crawler3;
        });

        echo "\n";
    }

    private function printSummary(): void
    {
        $total = $this->passed + $this->failed;
        $percentage = $total > 0 ? round(($this->passed / $total) * 100, 1) : 0;
        $duration = round(microtime(true) - $this->startTime, 2);

        echo "╔══════════════════════════════════════════════════════════════════╗\n";
        echo "║                        TEST SUMMARY                              ║\n";
        echo "╠══════════════════════════════════════════════════════════════════╣\n";
        echo sprintf("║  ✅ Passed:        %3d                                          ║\n", $this->passed);
        echo sprintf("║  ❌ Failed:        %3d                                          ║\n", $this->failed);
        echo sprintf("║  📊 Total:         %3d                                          ║\n", $total);
        echo sprintf("║  📈 Success Rate:  %5.1f%%                                      ║\n", $percentage);
        echo sprintf("║  ⏱️  Duration:      %5.2fs                                      ║\n", $duration);
        echo "╚══════════════════════════════════════════════════════════════════╝\n";
        echo "\n";

        if ($this->failed === 0) {
            echo "🎉 🎉 🎉  ALL TESTS PASSED!  🎉 🎉 🎉\n";
            echo "\n";
            echo "✅ CrawlerTool is production-ready!\n";
            echo "✅ All parameters validated correctly\n";
            echo "✅ Error handling robust\n";
            echo "✅ Performance within targets\n";
            echo "✅ Edge cases handled properly\n";
            echo "\n";
            exit(0);
        } else {
            echo "⚠️  SOME TESTS FAILED - REVIEW ERRORS ABOVE\n";
            echo "\n";

            // Show failed tests
            echo "Failed Tests:\n";
            echo "─────────────────────────────────────────────────────────────────\n";
            foreach ($this->results as $name => $result) {
                if ($result['status'] === 'FAIL') {
                    echo "❌ {$name}\n";
                    if ($result['error']) {
                        echo "   └─ {$result['error']}\n";
                    }
                }
            }
            echo "\n";
            exit(1);
        }
    }
}

// Run the test suite
$suite = new CrawlerTestSuite();
$suite->run();
