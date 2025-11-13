<?php
/**
 * Direct HumanBehaviorEngine Method Tests
 * Tests actual methods using reflection to access private methods
 */

require_once __DIR__ . '/assets/services/crawlers/CentralLogger.php';
require_once __DIR__ . '/assets/services/crawlers/StubLogger.php';
require_once __DIR__ . '/assets/services/crawlers/HumanBehaviorEngine.php';

use CIS\Crawlers\HumanBehaviorEngine;
use CIS\Crawlers\StubLogger;

class HumanBehaviorEngineTest {
    private $engine;
    private $reflection;

    public function __construct() {
        $logger = new StubLogger();
        $this->engine = new HumanBehaviorEngine($logger);
        $this->reflection = new ReflectionClass($this->engine);
    }    /**
     * Test generateScrollDistance method
     */
    public function testGenerateScrollDistance() {
        echo "\n🧪 TEST 1: generateScrollDistance() Method\n";
        echo str_repeat("=", 80) . "\n";

        $method = $this->reflection->getMethod('generateScrollDistance');
        $method->setAccessible(true);

        $samples = [];
        for ($i = 0; $i < 200; $i++) {
            $samples[] = $method->invoke($this->engine);
        }

        sort($samples);

        $min = min($samples);
        $max = max($samples);
        $avg = array_sum($samples) / count($samples);
        $median = $samples[100];
        $stddev = $this->calculateStdDev($samples, $avg);

        echo "📊 Statistics (200 samples):\n";
        echo "   Min: {$min}px, Max: {$max}px\n";
        echo "   Avg: " . round($avg, 2) . "px, Median: {$median}px\n";
        echo "   Std Dev: " . round($stddev, 2) . "px\n";

        // Check for old boundary clustering
        $old_boundaries = [50, 180, 650, 1200, 2500];
        $clustering = [];
        foreach ($old_boundaries as $boundary) {
            $near = 0;
            foreach ($samples as $sample) {
                if (abs($sample - $boundary) < ($boundary * 0.05)) { // Within 5%
                    $near++;
                }
            }
            $clustering[$boundary] = $near;
            echo "   Near {$boundary}px: {$near} samples (" . round(($near / 200) * 100, 1) . "%)\n";
        }

        $max_clustering = max($clustering);
        $max_clustering_pct = ($max_clustering / 200) * 100;

        // Pass if no boundary has >10% clustering
        $pass = $max_clustering_pct < 10;

        if ($pass) {
            echo "✅ PASSED: Max clustering {$max_clustering_pct}% (expect <10%)\n";
        } else {
            echo "❌ FAILED: Max clustering {$max_clustering_pct}% (too high)\n";
        }

        return $pass;
    }

    /**
     * Test generateTypingSpeed method
     */
    public function testGenerateTypingSpeed() {
        echo "\n🧪 TEST 2: generateTypingSpeed() Method\n";
        echo str_repeat("=", 80) . "\n";

        $method = $this->reflection->getMethod('generateTypingSpeed');
        $method->setAccessible(true);

        $wpms = [];
        $errors = [];

        for ($i = 0; $i < 200; $i++) {
            $result = $method->invoke($this->engine);
            $wpms[] = $result['wpm'];
            $errors[] = $result['error_rate'];
        }

        $min_wpm = min($wpms);
        $max_wpm = max($wpms);
        $avg_wpm = array_sum($wpms) / count($wpms);
        $stddev_wpm = $this->calculateStdDev($wpms, $avg_wpm);

        $min_err = min($errors);
        $max_err = max($errors);
        $avg_err = array_sum($errors) / count($errors);
        $stddev_err = $this->calculateStdDev($errors, $avg_err);

        echo "📊 WPM Statistics (200 samples):\n";
        echo "   Min: " . round($min_wpm, 2) . " WPM, Max: " . round($max_wpm, 2) . " WPM\n";
        echo "   Avg: " . round($avg_wpm, 2) . " WPM, Std Dev: " . round($stddev_wpm, 2) . "\n";

        echo "📊 Error Rate Statistics:\n";
        echo "   Min: " . round($min_err * 100, 2) . "%, Max: " . round($max_err * 100, 2) . "%\n";
        echo "   Avg: " . round($avg_err * 100, 2) . "%, Std Dev: " . round($stddev_err * 100, 3) . "%\n";

        // Check for old boundary clustering
        $old_wpm_boundaries = [23, 35, 36, 60, 61, 80, 81, 120];
        $wpm_clustering = [];
        foreach ($old_wpm_boundaries as $boundary) {
            $near = 0;
            foreach ($wpms as $wpm) {
                if (abs($wpm - $boundary) < 2) {
                    $near++;
                }
            }
            if ($near > 0) {
                $wpm_clustering[$boundary] = $near;
                echo "   Near {$boundary} WPM: {$near} samples (" . round(($near / 200) * 100, 1) . "%)\n";
            }
        }

        $max_wpm_clustering = empty($wpm_clustering) ? 0 : max($wpm_clustering);
        $max_wpm_clustering_pct = ($max_wpm_clustering / 200) * 100;

        // Pass if: WPM range >80, good variance, no clustering >5%, error rate varies
        $pass = ($max_wpm - $min_wpm) > 80 &&
                $stddev_wpm > 15 &&
                $max_wpm_clustering_pct < 5 &&
                $stddev_err > 0.01;

        if ($pass) {
            echo "✅ PASSED: Wide WPM range, good variance, minimal clustering\n";
        } else {
            echo "❌ FAILED: Range=" . round($max_wpm - $min_wpm, 1) . " (need >80), ";
            echo "StdDev=" . round($stddev_wpm, 2) . " (need >15), ";
            echo "Clustering=" . round($max_wpm_clustering_pct, 1) . "% (need <5%)\n";
        }

        return $pass;
    }

    /**
     * Test calculatePauseDuration method
     */
    public function testCalculatePauseDuration() {
        echo "\n🧪 TEST 3: calculatePauseDuration() Method\n";
        echo str_repeat("=", 80) . "\n";

        $method = $this->reflection->getMethod('calculatePauseDuration');
        $method->setAccessible(true);

        $interest_levels = [
            'very_interesting' => 95,
            'interesting' => 50,
            'normal' => 60,
            'boring' => 5
        ];

        $results = [];
        foreach ($interest_levels as $name => $interest) {
            $samples = [];
            for ($i = 0; $i < 50; $i++) {
                $samples[] = $method->invoke($this->engine, $interest);
            }

            $avg = array_sum($samples) / count($samples);
            $stddev = $this->calculateStdDev($samples, $avg);
            $min = min($samples);
            $max = max($samples);

            $results[$name] = [
                'avg' => $avg,
                'stddev' => $stddev,
                'min' => $min,
                'max' => $max,
                'samples' => $samples
            ];

            echo "📊 {$name} (interest={$interest}):\n";
            echo "   Avg: " . round($avg, 2) . "s, Std Dev: " . round($stddev, 2) . "s\n";
            echo "   Range: " . round($min, 2) . "s - " . round($max, 2) . "s\n";
        }

        // Check that interest levels produce different averages
        $ratio_very_boring = $results['very_interesting']['avg'] / $results['boring']['avg'];
        $ratio_int_norm = $results['interesting']['avg'] / $results['normal']['avg'];

        echo "\n🎯 Interest Level Differentiation:\n";
        echo "   Very Interesting / Boring: " . round($ratio_very_boring, 2) . "x\n";
        echo "   Interesting / Normal: " . round($ratio_int_norm, 2) . "x\n";

        // Pass if differentiation exists and variance is present
        $pass = $ratio_very_boring > 2 &&
                $ratio_very_boring < 15 &&
                $results['very_interesting']['stddev'] > 0.5 &&
                $results['boring']['stddev'] > 0.05;

        if ($pass) {
            echo "✅ PASSED: Interest levels differentiated with good variance\n";
        } else {
            echo "❌ FAILED: Ratio=" . round($ratio_very_boring, 2) . " (need 2-15x)\n";
        }

        return $pass;
    }

    /**
     * Test calculateReadingTime method
     */
    public function testCalculateReadingTime() {
        echo "\n🧪 TEST 4: calculateReadingTime() Method\n";
        echo str_repeat("=", 80) . "\n";

        $method = $this->reflection->getMethod('calculateReadingTime');
        $method->setAccessible(true);

        // Create mock DOM with 500 words
        $mock_dom = (object)[
            'textContent' => str_repeat('word ', 500)
        ];

        $samples = [];
        for ($i = 0; $i < 100; $i++) {
            $samples[] = $method->invoke($this->engine, $mock_dom);
        }

        $min = min($samples);
        $max = max($samples);
        $avg = array_sum($samples) / count($samples);
        $stddev = $this->calculateStdDev($samples, $avg);
        $cv = ($stddev / $avg) * 100;

        echo "📊 Reading Time Statistics (100 samples, 500 words):\n";
        echo "   Min: " . round($min, 2) . "s, Max: " . round($max, 2) . "s\n";
        echo "   Avg: " . round($avg, 2) . "s, Std Dev: " . round($stddev, 2) . "s\n";
        echo "   Coefficient of Variation: " . round($cv, 2) . "%\n";

        // High CV indicates chaotic multipliers working
        // Reasonable reading time for 500 words: 60-180 seconds
        $pass = $cv > 8 &&
                $stddev > 5 &&
                $avg > 40 &&
                $avg < 200;

        if ($pass) {
            echo "✅ PASSED: High variance with reasonable reading times\n";
        } else {
            echo "❌ FAILED: CV=" . round($cv, 2) . "% (need >8%), StdDev=" . round($stddev, 2) . "s (need >5s)\n";
        }

        return $pass;
    }

    /**
     * Helper: Calculate standard deviation
     */
    private function calculateStdDev($data, $mean) {
        $variance = 0.0;
        foreach ($data as $value) {
            $variance += pow($value - $mean, 2);
        }
        return sqrt($variance / count($data));
    }

    /**
     * Run all tests
     */
    public function runAll() {
        echo "\n";
        echo "╔═══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                                                                               ║\n";
        echo "║   🔬 HUMAN BEHAVIOR ENGINE - METHOD TESTS 🔬                                ║\n";
        echo "║   Testing actual HumanBehaviorEngine methods                                 ║\n";
        echo "║                                                                               ║\n";
        echo "╚═══════════════════════════════════════════════════════════════════════════════╝\n";

        $start_time = microtime(true);

        $results = [];
        $results[] = ['name' => 'generateScrollDistance()', 'pass' => $this->testGenerateScrollDistance()];
        $results[] = ['name' => 'generateTypingSpeed()', 'pass' => $this->testGenerateTypingSpeed()];
        $results[] = ['name' => 'calculatePauseDuration()', 'pass' => $this->testCalculatePauseDuration()];
        $results[] = ['name' => 'calculateReadingTime()', 'pass' => $this->testCalculateReadingTime()];

        $duration = microtime(true) - $start_time;

        // Summary
        echo "\n";
        echo "╔═══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                                                                               ║\n";
        echo "║   📊 METHOD TEST RESULTS SUMMARY                                             ║\n";
        echo "║                                                                               ║\n";
        echo "╚═══════════════════════════════════════════════════════════════════════════════╝\n";
        echo "\n";

        $passed = 0;
        $failed = 0;

        foreach ($results as $result) {
            $status = $result['pass'] ? '✅ PASS' : '❌ FAIL';
            echo "{$status}: {$result['name']}\n";
            if ($result['pass']) $passed++;
            else $failed++;
        }

        $total = count($results);
        $pass_rate = ($passed / $total) * 100;

        echo "\n" . str_repeat("=", 80) . "\n";
        echo "TOTAL TESTS: {$total}\n";
        echo "PASSED: {$passed} ✅\n";
        echo "FAILED: {$failed} " . ($failed > 0 ? '❌' : '') . "\n";
        echo "PASS RATE: " . round($pass_rate, 1) . "%\n";
        echo "DURATION: " . round($duration, 3) . " seconds\n";
        echo str_repeat("=", 80) . "\n";

        if ($failed == 0) {
            echo "\n🎉 ALL METHOD TESTS PASSED! HUMAN BEHAVIOR ENGINE WORKING PERFECTLY! 🌪️\n\n";
        } else {
            echo "\n⚠️  SOME TESTS FAILED. REVIEW RESULTS ABOVE.\n\n";
        }

        return $failed == 0;
    }
}

// Run the test suite
$test = new HumanBehaviorEngineTest();
$success = $test->runAll();

exit($success ? 0 : 1);
