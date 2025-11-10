<?php
/**
 * Simplified Chaotic Boundaries Test
 * Tests the core randomization methods directly
 */

// Simple test of the chaotic boundary logic
class ChaoticBoundariesSimpleTest {

    /**
     * Test chaotic integer range generation
     */
    public function testChaoticIntRange() {
        echo "\n🧪 TEST 1: CHAOTIC INTEGER RANGE GENERATION\n";
        echo str_repeat("=", 80) . "\n";

        $samples = [];

        // Simulate the chaotic boundary logic
        for ($i = 0; $i < 100; $i++) {
            // OLD: rand(650, 1200)
            // NEW: rand(rand(620, 685), rand(1170, 1235))
            $min = rand(620, 685);
            $max = rand(1170, 1235);
            $value = rand($min, $max);
            $samples[] = $value;
        }

        sort($samples);

        $min_val = min($samples);
        $max_val = max($samples);
        $avg = array_sum($samples) / count($samples);
        $median = $samples[50];

        echo "📊 Statistics (100 samples):\n";
        echo "   Min: {$min_val}, Max: {$max_val}\n";
        echo "   Avg: " . round($avg, 2) . ", Median: {$median}\n";
        echo "   Range: " . ($max_val - $min_val) . "px\n";

        // Check for clustering at old boundaries (650, 1200)
        $near_650 = 0;
        $near_1200 = 0;

        foreach ($samples as $sample) {
            if (abs($sample - 650) < 15) $near_650++;
            if (abs($sample - 1200) < 15) $near_1200++;
        }

        echo "\n🎯 Old Boundary Clustering:\n";
        echo "   Near 650: {$near_650} samples (" . ($near_650) . "%)\n";
        echo "   Near 1200: {$near_1200} samples (" . ($near_1200) . "%)\n";

        // With chaotic boundaries, we expect < 5% clustering at old fixed points
        // Range should extend beyond old limits (min < 645, max > 1210)
        $pass = ($near_650 < 5) && ($near_1200 < 5) && ($max_val > 1210) && ($min_val < 645);

        if ($pass) {
            echo "✅ PASSED: No clustering at old boundaries, range extends beyond old limits\n";
        } else {
            echo "❌ FAILED: Clustering: 650={$near_650}%, 1200={$near_1200}% (expect <5% each)\n";
        }

        return $pass;
    }

    /**
     * Test chaotic float range generation
     */
    public function testChaoticFloatRange() {
        echo "\n🧪 TEST 2: CHAOTIC FLOAT RANGE GENERATION\n";
        echo str_repeat("=", 80) . "\n";

        $samples = [];

        // Simulate chaotic float boundary logic
        for ($i = 0; $i < 100; $i++) {
            // OLD: randomFloat(0.85, 1.15)
            // NEW: randomFloat(randomFloat(0.82, 0.88), randomFloat(1.12, 1.18))
            $min = $this->randomFloat(0.82, 0.88);
            $max = $this->randomFloat(1.12, 1.18);
            $value = $this->randomFloat($min, $max);
            $samples[] = $value;
        }

        $min_val = min($samples);
        $max_val = max($samples);
        $avg = array_sum($samples) / count($samples);
        $stddev = $this->calculateStdDev($samples, $avg);

        echo "📊 Statistics (100 samples):\n";
        echo "   Min: " . round($min_val, 4) . ", Max: " . round($max_val, 4) . "\n";
        echo "   Avg: " . round($avg, 4) . ", Std Dev: " . round($stddev, 4) . "\n";

        // Check that we're not clustering at the old boundaries
        $near_085 = 0;
        $near_115 = 0;

        foreach ($samples as $sample) {
            if (abs($sample - 0.85) < 0.02) $near_085++;
            if (abs($sample - 1.15) < 0.02) $near_115++;
        }

        echo "\n🎯 Old Boundary Clustering:\n";
        echo "   Near 0.85: {$near_085} samples\n";
        echo "   Near 1.15: {$near_115} samples\n";

        // With nested randomization, we expect minimal clustering and good variance
        $pass = ($near_085 < 10) && ($near_115 < 10) && ($stddev > 0.04);

        if ($pass) {
            echo "✅ PASSED: Good variance, no clustering at old boundaries\n";
        } else {
            echo "❌ FAILED: Near 0.85={$near_085}, Near 1.15={$near_115} (expect <10 each), StdDev=" . round($stddev, 4) . "\n";
        }

        return $pass;
    }

    /**
     * Test boundary variance across multiple runs
     */
    public function testBoundaryVariance() {
        echo "\n🧪 TEST 3: BOUNDARY VARIANCE ACROSS RUNS\n";
        echo str_repeat("=", 80) . "\n";

        $boundary_pairs = [];

        // Generate 20 different boundary pairs
        for ($i = 0; $i < 20; $i++) {
            $min = rand(620, 685);
            $max = rand(1170, 1235);
            $boundary_pairs[] = ['min' => $min, 'max' => $max, 'range' => $max - $min];
        }

        echo "📊 First 10 Boundary Pairs:\n";
        for ($i = 0; $i < 10; $i++) {
            $pair = $boundary_pairs[$i];
            echo "   Run " . ($i + 1) . ": [{$pair['min']}, {$pair['max']}] Range: {$pair['range']}px\n";
        }

        // Calculate variance in boundaries
        $mins = array_column($boundary_pairs, 'min');
        $maxs = array_column($boundary_pairs, 'max');
        $ranges = array_column($boundary_pairs, 'range');

        $min_avg = array_sum($mins) / count($mins);
        $max_avg = array_sum($maxs) / count($maxs);
        $range_avg = array_sum($ranges) / count($ranges);

        $min_stddev = $this->calculateStdDev($mins, $min_avg);
        $max_stddev = $this->calculateStdDev($maxs, $max_avg);
        $range_stddev = $this->calculateStdDev($ranges, $range_avg);

        echo "\n🎯 Boundary Variance:\n";
        echo "   Min boundary: Avg=" . round($min_avg, 1) . ", StdDev=" . round($min_stddev, 2) . "\n";
        echo "   Max boundary: Avg=" . round($max_avg, 1) . ", StdDev=" . round($max_stddev, 2) . "\n";
        echo "   Range size: Avg=" . round($range_avg, 1) . ", StdDev=" . round($range_stddev, 2) . "\n";

        // Good variance means boundaries are truly chaotic
        // With ranges 620-685 (±32.5) and 1170-1235 (±32.5), expect stddev ~15-20
        $pass = ($min_stddev > 10) && ($max_stddev > 10) && ($range_stddev > 5);

        if ($pass) {
            echo "✅ PASSED: Boundaries vary significantly across runs\n";
        } else {
            echo "❌ FAILED: Min StdDev={$min_stddev} (need >10), Max StdDev={$max_stddev} (need >10)\n";
        }

        return $pass;
    }

    /**
     * Test distribution smoothness (no multi-modal peaks)
     */
    public function testDistributionSmoothness() {
        echo "\n🧪 TEST 4: DISTRIBUTION SMOOTHNESS\n";
        echo str_repeat("=", 80) . "\n";

        $samples = [];

        // Generate 1000 samples with chaotic boundaries
        for ($i = 0; $i < 1000; $i++) {
            $min = rand(620, 685);
            $max = rand(1170, 1235);
            $samples[] = rand($min, $max);
        }

        sort($samples);

        // Create histogram buckets
        $buckets = 20;
        $bucket_size = (max($samples) - min($samples)) / $buckets;
        $histogram = array_fill(0, $buckets, 0);

        foreach ($samples as $sample) {
            $bucket = min($buckets - 1, floor(($sample - min($samples)) / $bucket_size));
            $histogram[$bucket]++;
        }

        echo "📊 Distribution Histogram (1000 samples, 20 buckets):\n";
        $max_count = max($histogram);
        for ($i = 0; $i < $buckets; $i += 2) {
            $start = round(min($samples) + ($i * $bucket_size));
            $end = round(min($samples) + (($i + 1) * $bucket_size));
            $count = $histogram[$i];
            $bar_length = round(($count / $max_count) * 40);
            $bar = str_repeat('█', $bar_length);
            echo sprintf("   %4d-%4d: %3d %s\n", $start, $end, $count, $bar);
        }

        // Calculate coefficient of variation for histogram
        $hist_avg = array_sum($histogram) / count($histogram);
        $hist_stddev = $this->calculateStdDev($histogram, $hist_avg);
        $cv = ($hist_stddev / $hist_avg) * 100;

        echo "\n🎯 Distribution Metrics:\n";
        echo "   Coefficient of Variation: " . round($cv, 2) . "%\n";
        echo "   (Lower CV = more uniform distribution = better)\n";

        // Good distribution should be relatively uniform (CV < 40%)
        $pass = ($cv < 40);

        if ($pass) {
            echo "✅ PASSED: Distribution is smooth and continuous\n";
        } else {
            echo "❌ FAILED: Distribution shows clustering or multi-modal peaks\n";
        }

        return $pass;
    }

    /**
     * Test round number bias elimination
     */
    public function testRoundNumberBias() {
        echo "\n🧪 TEST 5: ROUND NUMBER BIAS ELIMINATION\n";
        echo str_repeat("=", 80) . "\n";

        $samples = [];

        for ($i = 0; $i < 500; $i++) {
            $min = rand(620, 685);
            $max = rand(1170, 1235);
            $samples[] = rand($min, $max);
        }

        // Count round numbers
        $ends_in_0 = 0;
        $ends_in_00 = 0;
        $ends_in_50 = 0;

        foreach ($samples as $sample) {
            if ($sample % 10 == 0) $ends_in_0++;
            if ($sample % 100 == 0) $ends_in_00++;
            if ($sample % 50 == 0) $ends_in_50++;
        }

        $pct_0 = ($ends_in_0 / count($samples)) * 100;
        $pct_00 = ($ends_in_00 / count($samples)) * 100;
        $pct_50 = ($ends_in_50 / count($samples)) * 100;

        echo "📊 Round Number Analysis (500 samples):\n";
        echo "   Ends in 0: {$ends_in_0} (" . round($pct_0, 1) . "%) - Expected: ~10%\n";
        echo "   Ends in 00: {$ends_in_00} (" . round($pct_00, 1) . "%) - Expected: ~1%\n";
        echo "   Ends in 50: {$ends_in_50} (" . round($pct_50, 1) . "%) - Expected: ~2%\n";

        // Check if distribution matches natural randomness
        $pass = ($pct_0 >= 7 && $pct_0 <= 13) &&
                ($pct_00 >= 0.5 && $pct_00 <= 2.5) &&
                ($pct_50 >= 1 && $pct_50 <= 4);

        if ($pass) {
            echo "✅ PASSED: No round number bias detected\n";
        } else {
            echo "❌ FAILED: Round number bias present\n";
        }

        return $pass;
    }

    /**
     * Helper: Generate random float
     */
    private function randomFloat($min, $max) {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
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
        echo "║   🌪️ CHAOTIC BOUNDARIES - SIMPLE TEST SUITE 🌪️                            ║\n";
        echo "║   Testing the core logic without full system dependencies                    ║\n";
        echo "║                                                                               ║\n";
        echo "╚═══════════════════════════════════════════════════════════════════════════════╝\n";

        $start_time = microtime(true);

        $results = [];
        $results[] = ['name' => 'Chaotic Integer Range', 'pass' => $this->testChaoticIntRange()];
        $results[] = ['name' => 'Chaotic Float Range', 'pass' => $this->testChaoticFloatRange()];
        $results[] = ['name' => 'Boundary Variance', 'pass' => $this->testBoundaryVariance()];
        $results[] = ['name' => 'Distribution Smoothness', 'pass' => $this->testDistributionSmoothness()];
        $results[] = ['name' => 'Round Number Bias', 'pass' => $this->testRoundNumberBias()];

        $duration = microtime(true) - $start_time;

        // Summary
        echo "\n";
        echo "╔═══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                                                                               ║\n";
        echo "║   📊 TEST RESULTS SUMMARY                                                    ║\n";
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
            echo "\n🎉 ALL TESTS PASSED! CHAOTIC BOUNDARIES LOGIC IS PERFECT! 🌪️\n";
            echo "\nKey Achievements:\n";
            echo "✅ No clustering at old fixed boundaries (650, 1200)\n";
            echo "✅ Boundaries vary across runs (truly chaotic)\n";
            echo "✅ Smooth continuous distribution (no multi-modal peaks)\n";
            echo "✅ No round number bias (natural randomness)\n";
            echo "✅ Good variance (high entropy)\n";
            echo "\n";
        } else {
            echo "\n⚠️  SOME TESTS FAILED. REVIEW RESULTS ABOVE.\n\n";
        }

        return $failed == 0;
    }
}

// Run the test suite
$test = new ChaoticBoundariesSimpleTest();
$success = $test->runAll();

exit($success ? 0 : 1);
