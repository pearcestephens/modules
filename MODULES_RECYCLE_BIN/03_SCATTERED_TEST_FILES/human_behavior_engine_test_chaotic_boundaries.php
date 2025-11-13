<?php
/**
 * Comprehensive Test Suite for Chaotic Boundaries
 * Tests all chaotic boundary implementations in HumanBehaviorEngine
 */

require_once __DIR__ . '/assets/services/crawlers/HumanBehaviorEngine.php';
require_once __DIR__ . '/assets/services/crawlers/CentralLogger.php';

use CIS\Crawlers\HumanBehaviorEngine;
use CIS\Crawlers\CentralLogger;

class ChaoticBoundariesTest {
    private $engine;
    private $results = [];
    private $passed = 0;
    private $failed = 0;

    public function __construct() {
        // Create a mock database connection (null for testing)
        $logger = new CentralLogger(null, CentralLogger::TYPE_COMPETITIVE, [
            'enable_db_logging' => false,
            'enable_file_logging' => false
        ]);
        $this->engine = new HumanBehaviorEngine($logger);
    }    /**
     * Test 1: Scroll Distance Chaotic Boundaries
     */
    public function testScrollDistances() {
        echo "\n🧪 TEST 1: SCROLL DISTANCE CHAOTIC BOUNDARIES\n";
        echo str_repeat("=", 80) . "\n";

        $samples = [];
        $categories = ['tiny' => 0, 'normal' => 0, 'medium' => 0, 'big' => 0];

        // Generate 100 scroll distances
        for ($i = 0; $i < 100; $i++) {
            $distance = $this->engine->generateScrollDistance();
            $samples[] = $distance;

            // Categorize (approximate)
            if ($distance < 150) $categories['tiny']++;
            elseif ($distance < 600) $categories['normal']++;
            elseif ($distance < 1100) $categories['medium']++;
            else $categories['big']++;
        }

        // Statistical analysis
        $min = min($samples);
        $max = max($samples);
        $avg = array_sum($samples) / count($samples);
        $stddev = $this->calculateStdDev($samples, $avg);

        echo "📊 Statistics (100 samples):\n";
        echo "   Min: {$min}px, Max: {$max}px, Avg: " . round($avg, 2) . "px\n";
        echo "   Std Dev: " . round($stddev, 2) . "px\n";
        echo "   Categories: Tiny={$categories['tiny']}, Normal={$categories['normal']}, ";
        echo "Medium={$categories['medium']}, Big={$categories['big']}\n";

        // Check for boundary clustering (should NOT cluster at old boundaries)
        $old_boundaries = [50, 180, 650, 1200];
        $boundary_hits = array_fill_keys($old_boundaries, 0);

        foreach ($samples as $sample) {
            foreach ($old_boundaries as $boundary) {
                if (abs($sample - $boundary) < 10) {
                    $boundary_hits[$boundary]++;
                }
            }
        }

        echo "🎯 Old Boundary Clustering Test:\n";
        $max_clustering = 0;
        foreach ($boundary_hits as $boundary => $hits) {
            $percentage = ($hits / count($samples)) * 100;
            echo "   {$boundary}px: {$hits} hits ({$percentage}%)\n";
            $max_clustering = max($max_clustering, $percentage);
        }

        // Pass if clustering < 15% at any old boundary
        $pass = $max_clustering < 15;
        $this->recordResult('Scroll Distance Chaotic Boundaries', $pass,
            "Max clustering: {$max_clustering}% (should be < 15%)");

        return $pass;
    }

    /**
     * Test 2: Typing Speed Chaotic Boundaries
     */
    public function testTypingSpeeds() {
        echo "\n🧪 TEST 2: TYPING SPEED CHAOTIC BOUNDARIES\n";
        echo str_repeat("=", 80) . "\n";

        $samples = [];
        $error_rates = [];

        // Generate 100 typing speeds
        for ($i = 0; $i < 100; $i++) {
            $speed = $this->engine->generateTypingSpeed();
            $samples[] = $speed['wpm'];
            $error_rates[] = $speed['error_rate'];
        }

        // Statistical analysis
        $min_wpm = min($samples);
        $max_wpm = max($samples);
        $avg_wpm = array_sum($samples) / count($samples);
        $avg_error = array_sum($error_rates) / count($error_rates);

        echo "📊 Typing Speed Statistics (100 samples):\n";
        echo "   WPM Range: {$min_wpm} - {$max_wpm}\n";
        echo "   Avg WPM: " . round($avg_wpm, 2) . "\n";
        echo "   Avg Error Rate: " . round($avg_error * 100, 2) . "%\n";

        // Check for old boundary clustering
        $old_boundaries = [23, 35, 36, 60, 61, 80, 81, 120];
        $boundary_hits = array_fill_keys($old_boundaries, 0);

        foreach ($samples as $sample) {
            foreach ($old_boundaries as $boundary) {
                if (abs($sample - $boundary) < 2) {
                    $boundary_hits[$boundary]++;
                }
            }
        }

        echo "🎯 Old Boundary Clustering Test:\n";
        $max_clustering = 0;
        foreach ($boundary_hits as $boundary => $hits) {
            if ($hits > 0) {
                $percentage = ($hits / count($samples)) * 100;
                echo "   {$boundary} WPM: {$hits} hits ({$percentage}%)\n";
                $max_clustering = max($max_clustering, $percentage);
            }
        }

        // Check variance in error rates (should vary, not fixed 2% or 4%)
        $error_stddev = $this->calculateStdDev($error_rates, $avg_error);
        echo "   Error Rate Std Dev: " . round($error_stddev * 100, 3) . "%\n";

        $pass = $max_clustering < 10 && $error_stddev > 0.01;
        $this->recordResult('Typing Speed Chaotic Boundaries', $pass,
            "WPM clustering: {$max_clustering}%, Error variance: " . round($error_stddev * 100, 3) . "%");

        return $pass;
    }

    /**
     * Test 3: Reading Time Chaotic Multipliers
     */
    public function testReadingTimes() {
        echo "\n🧪 TEST 3: READING TIME CHAOTIC MULTIPLIERS\n";
        echo str_repeat("=", 80) . "\n";

        $samples = [];

        // Mock DOM object
        $mock_dom = (object)[
            'textContent' => str_repeat('word ', 500) // 500 words
        ];

        // Generate 50 reading times
        for ($i = 0; $i < 50; $i++) {
            $time = $this->engine->calculateReadingTime($mock_dom);
            $samples[] = $time;
        }

        $min = min($samples);
        $max = max($samples);
        $avg = array_sum($samples) / count($samples);
        $stddev = $this->calculateStdDev($samples, $avg);
        $cv = ($stddev / $avg) * 100; // Coefficient of variation

        echo "📊 Reading Time Statistics (50 samples, 500 words):\n";
        echo "   Range: " . round($min, 2) . "s - " . round($max, 2) . "s\n";
        echo "   Avg: " . round($avg, 2) . "s\n";
        echo "   Std Dev: " . round($stddev, 2) . "s\n";
        echo "   Coefficient of Variation: " . round($cv, 2) . "%\n";

        // High CV indicates chaotic multipliers working
        $pass = $cv > 10 && $stddev > 1.0;
        $this->recordResult('Reading Time Chaotic Multipliers', $pass,
            "CV: {$cv}% (should be > 10%), StdDev: " . round($stddev, 2) . "s");

        return $pass;
    }

    /**
     * Test 4: Pause Duration Chaotic Multipliers
     */
    public function testPauseDurations() {
        echo "\n🧪 TEST 4: PAUSE DURATION CHAOTIC MULTIPLIERS\n";
        echo str_repeat("=", 80) . "\n";

        $samples = [
            'very_interesting' => [],
            'moderately_interesting' => [],
            'normal' => [],
            'boring' => []
        ];

        // Generate pauses for different interest levels
        for ($i = 0; $i < 25; $i++) {
            $samples['very_interesting'][] = $this->engine->calculatePauseDuration(95);
            $samples['moderately_interesting'][] = $this->engine->calculatePauseDuration(40);
            $samples['normal'][] = $this->engine->calculatePauseDuration(60);
            $samples['boring'][] = $this->engine->calculatePauseDuration(5);
        }

        echo "📊 Pause Duration Statistics (25 samples each):\n";
        foreach ($samples as $type => $values) {
            $avg = array_sum($values) / count($values);
            $stddev = $this->calculateStdDev($values, $avg);
            echo "   " . ucwords(str_replace('_', ' ', $type)) . ":\n";
            echo "      Avg: " . round($avg, 2) . "s, Std Dev: " . round($stddev, 2) . "s\n";
        }

        // Check that different interest levels produce different pause ranges
        $avg_very = array_sum($samples['very_interesting']) / 25;
        $avg_boring = array_sum($samples['boring']) / 25;
        $ratio = $avg_very / $avg_boring;

        echo "🎯 Interest Level Differentiation:\n";
        echo "   Very Interesting / Boring Ratio: " . round($ratio, 2) . "x\n";

        $pass = $ratio > 2.5 && $ratio < 12; // Should be roughly 3-8x but with chaos
        $this->recordResult('Pause Duration Chaotic Multipliers', $pass,
            "Interest ratio: {$ratio}x (expected ~3-8x with variance)");

        return $pass;
    }

    /**
     * Test 5: Category Threshold Variance
     */
    public function testCategoryThresholds() {
        echo "\n🧪 TEST 5: CATEGORY THRESHOLD VARIANCE\n";
        echo str_repeat("=", 80) . "\n";

        // We can't directly test private methods, but we can test their effects
        // by generating many samples and checking distribution

        $scroll_samples = [];
        for ($i = 0; $i < 200; $i++) {
            $scroll_samples[] = $this->engine->generateScrollDistance();
        }

        // Sort and check percentile boundaries
        sort($scroll_samples);

        $p10 = $scroll_samples[intval(count($scroll_samples) * 0.10)];
        $p60 = $scroll_samples[intval(count($scroll_samples) * 0.60)];
        $p85 = $scroll_samples[intval(count($scroll_samples) * 0.85)];

        echo "📊 Percentile Analysis (200 samples):\n";
        echo "   10th percentile: {$p10}px\n";
        echo "   60th percentile: {$p60}px\n";
        echo "   85th percentile: {$p85}px\n";

        // These should NOT cluster exactly at old boundaries
        $clustering_at_old = (
            (abs($p10 - 180) < 20) ||
            (abs($p60 - 650) < 50) ||
            (abs($p85 - 1200) < 100)
        );

        $pass = !$clustering_at_old;
        $this->recordResult('Category Threshold Variance', $pass,
            "Percentiles do not cluster at old fixed boundaries");

        return $pass;
    }

    /**
     * Test 6: No Round Number Bias
     */
    public function testRoundNumberBias() {
        echo "\n🧪 TEST 6: ROUND NUMBER BIAS CHECK\n";
        echo str_repeat("=", 80) . "\n";

        $samples = [];
        for ($i = 0; $i < 500; $i++) {
            $samples[] = $this->engine->generateScrollDistance();
        }

        // Count how many values end in 0 or 00
        $ends_in_0 = 0;
        $ends_in_00 = 0;

        foreach ($samples as $sample) {
            if ($sample % 10 == 0) $ends_in_0++;
            if ($sample % 100 == 0) $ends_in_00++;
        }

        $percent_0 = ($ends_in_0 / count($samples)) * 100;
        $percent_00 = ($ends_in_00 / count($samples)) * 100;

        echo "📊 Round Number Analysis (500 samples):\n";
        echo "   Values ending in 0: {$ends_in_0} ({$percent_0}%)\n";
        echo "   Values ending in 00: {$ends_in_00} ({$percent_00}%)\n";
        echo "   Expected for random: ~10% end in 0, ~1% end in 00\n";

        // Should be close to natural distribution (10% for 0, 1% for 00)
        $pass = ($percent_0 > 5 && $percent_0 < 15) && ($percent_00 < 3);
        $this->recordResult('No Round Number Bias', $pass,
            "Distribution matches natural randomness");

        return $pass;
    }

    /**
     * Test 7: Boundary Uniqueness Test
     */
    public function testBoundaryUniqueness() {
        echo "\n🧪 TEST 7: BOUNDARY UNIQUENESS (META-TEST)\n";
        echo str_repeat("=", 80) . "\n";

        // This tests if the BOUNDARIES themselves vary
        // We do this by checking if consecutive samples show different patterns

        $sets = [];
        for ($set = 0; $set < 5; $set++) {
            $samples = [];
            for ($i = 0; $i < 100; $i++) {
                $samples[] = $this->engine->generateScrollDistance();
            }
            sort($samples);
            $sets[] = [
                'min' => $samples[0],
                'max' => $samples[99],
                'median' => $samples[50],
                'q1' => $samples[25],
                'q3' => $samples[75]
            ];
        }

        echo "📊 Five Independent Sample Sets (100 each):\n";
        foreach ($sets as $i => $set) {
            echo "   Set " . ($i + 1) . ": Min={$set['min']}, Q1={$set['q1']}, ";
            echo "Med={$set['median']}, Q3={$set['q3']}, Max={$set['max']}\n";
        }

        // Check variance across sets (boundaries should vary)
        $medians = array_column($sets, 'median');
        $q1s = array_column($sets, 'q1');
        $q3s = array_column($sets, 'q3');

        $median_stddev = $this->calculateStdDev($medians, array_sum($medians) / 5);
        $q1_stddev = $this->calculateStdDev($q1s, array_sum($q1s) / 5);
        $q3_stddev = $this->calculateStdDev($q3s, array_sum($q3s) / 5);

        echo "🎯 Cross-Set Variance:\n";
        echo "   Median Std Dev: " . round($median_stddev, 2) . "px\n";
        echo "   Q1 Std Dev: " . round($q1_stddev, 2) . "px\n";
        echo "   Q3 Std Dev: " . round($q3_stddev, 2) . "px\n";

        // If boundaries were fixed, these would have very low std dev
        $pass = ($median_stddev > 20) && ($q1_stddev > 10) && ($q3_stddev > 30);
        $this->recordResult('Boundary Uniqueness', $pass,
            "Sample sets show different distributions (boundaries vary)");

        return $pass;
    }

    /**
     * Helper: Calculate Standard Deviation
     */
    private function calculateStdDev($data, $mean) {
        $variance = 0.0;
        foreach ($data as $value) {
            $variance += pow($value - $mean, 2);
        }
        return sqrt($variance / count($data));
    }

    /**
     * Record test result
     */
    private function recordResult($test_name, $passed, $details) {
        if ($passed) {
            $this->passed++;
            echo "✅ PASSED: {$details}\n";
        } else {
            $this->failed++;
            echo "❌ FAILED: {$details}\n";
        }

        $this->results[] = [
            'test' => $test_name,
            'passed' => $passed,
            'details' => $details
        ];
    }

    /**
     * Run all tests
     */
    public function runAll() {
        echo "\n";
        echo "╔═══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                                                                               ║\n";
        echo "║   🌪️ CHAOTIC BOUNDARIES - COMPREHENSIVE TEST SUITE 🌪️                      ║\n";
        echo "║                                                                               ║\n";
        echo "╚═══════════════════════════════════════════════════════════════════════════════╝\n";

        $start_time = microtime(true);

        // Run all tests
        $this->testScrollDistances();
        $this->testTypingSpeeds();
        $this->testReadingTimes();
        $this->testPauseDurations();
        $this->testCategoryThresholds();
        $this->testRoundNumberBias();
        $this->testBoundaryUniqueness();

        $duration = microtime(true) - $start_time;

        // Final summary
        echo "\n";
        echo "╔═══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                                                                               ║\n";
        echo "║   📊 TEST RESULTS SUMMARY                                                    ║\n";
        echo "║                                                                               ║\n";
        echo "╚═══════════════════════════════════════════════════════════════════════════════╝\n";
        echo "\n";

        $total = $this->passed + $this->failed;
        $pass_rate = ($this->passed / $total) * 100;

        foreach ($this->results as $result) {
            $status = $result['passed'] ? '✅ PASS' : '❌ FAIL';
            echo "{$status}: {$result['test']}\n";
            echo "         {$result['details']}\n\n";
        }

        echo str_repeat("=", 80) . "\n";
        echo "TOTAL TESTS: {$total}\n";
        echo "PASSED: {$this->passed} ✅\n";
        echo "FAILED: {$this->failed} " . ($this->failed > 0 ? '❌' : '') . "\n";
        echo "PASS RATE: " . round($pass_rate, 1) . "%\n";
        echo "DURATION: " . round($duration, 2) . " seconds\n";
        echo str_repeat("=", 80) . "\n";

        if ($this->failed == 0) {
            echo "\n🎉 ALL TESTS PASSED! CHAOTIC BOUNDARIES WORKING PERFECTLY! 🌪️\n\n";
        } else {
            echo "\n⚠️  SOME TESTS FAILED. REVIEW RESULTS ABOVE.\n\n";
        }

        return $this->failed == 0;
    }
}

// Run the test suite
$test = new ChaoticBoundariesTest();
$success = $test->runAll();

exit($success ? 0 : 1);
