#!/usr/bin/env php
<?php
/**
 * ULTIMATE AI STACK - Quick Test Script
 *
 * Tests all configured providers and shows you which ones work.
 *
 * Usage:
 *   php test-ultimate-ai-stack.php
 *
 * Options:
 *   php test-ultimate-ai-stack.php --provider=openai       Test specific provider
 *   php test-ultimate-ai-stack.php --verbose               Show full responses
 *   php test-ultimate-ai-stack.php --benchmark             Run performance tests
 */

declare(strict_types=1);

// Bootstrap - Load environment and dependencies
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// Load Database class
require_once __DIR__ . '/../base/Database.php';

// Load AI Router
require_once __DIR__ . '/lib/Services/AI/UniversalAIRouter.php';

use CIS\Consignments\Services\AI\UniversalAIRouter;

// Parse CLI args
$options = getopt('', ['provider:', 'verbose', 'benchmark']);
$testProvider = $options['provider'] ?? null;
$verbose = isset($options['verbose']);
$benchmark = isset($options['benchmark']);

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║          ULTIMATE AI STACK - MULTI-PROVIDER TEST SUITE          ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Initialize router
try {
    $router = new UniversalAIRouter();
    echo "✅ Universal AI Router initialized\n\n";
} catch (Exception $e) {
    echo "❌ Failed to initialize router: {$e->getMessage()}\n";
    exit(1);
}

// Test prompts
$testPrompts = [
    'quick' => "What's 2+2?",
    'analysis' => "Analyze the best carrier options for a 45kg transfer from Auckland to Wellington. Consider cost, speed, and reliability.",
    'code' => "Write a PHP function to validate transfer quantities are positive integers.",
    'internal' => "What are the key steps in our transfer approval process?",
];

// Provider tests
$providers = ['openai', 'anthropic', 'intelligence_hub', 'claude_bot'];

if ($testProvider) {
    $providers = [$testProvider];
    echo "🎯 Testing single provider: $testProvider\n\n";
}

$results = [];

foreach ($providers as $provider) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Testing Provider: " . strtoupper($provider) . "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    $providerResults = [];

    foreach ($testPrompts as $type => $prompt) {
        echo "📝 Test: $type\n";
        echo "   Prompt: \"$prompt\"\n";

        $startTime = microtime(true);

        try {
            $response = $router->chat($prompt, [], [
                'provider' => $provider,
                'no_cache' => true, // Force fresh API call
            ]);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            echo "   ✅ Success ({$duration}ms)\n";

            if ($verbose) {
                echo "   Response: " . substr($response['message'], 0, 150) . "...\n";
                echo "   Model: {$response['metadata']['model']}\n";
                echo "   Tokens: {$response['metadata']['tokens_used']}\n";
                echo "   Cost: \${$response['metadata']['cost_usd']}\n";
            } else {
                echo "   Model: {$response['metadata']['model']} | ";
                echo "Tokens: {$response['metadata']['tokens_used']} | ";
                echo "Cost: \${$response['metadata']['cost_usd']}\n";
            }

            $providerResults[$type] = [
                'success' => true,
                'duration_ms' => $duration,
                'tokens' => $response['metadata']['tokens_used'],
                'cost' => $response['metadata']['cost_usd'],
            ];

        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            echo "   ❌ Failed: {$e->getMessage()}\n";

            $providerResults[$type] = [
                'success' => false,
                'duration_ms' => $duration,
                'error' => $e->getMessage(),
            ];
        }

        echo "\n";
    }

    $results[$provider] = $providerResults;
}

// Summary
echo "\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                         TEST SUMMARY                             ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";
echo "\n";

foreach ($results as $provider => $providerResults) {
    $successCount = count(array_filter($providerResults, fn($r) => $r['success']));
    $totalTests = count($providerResults);
    $successRate = round(($successCount / $totalTests) * 100, 1);

    $totalCost = array_sum(array_column(array_filter($providerResults, fn($r) => $r['success']), 'cost'));
    $avgDuration = round(array_sum(array_column($providerResults, 'duration_ms')) / $totalTests, 2);

    $status = $successCount === $totalTests ? '✅' : ($successCount > 0 ? '⚠️' : '❌');

    echo "$status " . strtoupper($provider) . "\n";
    echo "   Success Rate: $successCount/$totalTests ({$successRate}%)\n";
    echo "   Avg Duration: {$avgDuration}ms\n";
    echo "   Total Cost: \${$totalCost}\n";
    echo "\n";
}

// Benchmark mode
if ($benchmark) {
    echo "\n";
    echo "╔══════════════════════════════════════════════════════════════════╗\n";
    echo "║                    PERFORMANCE BENCHMARK                         ║\n";
    echo "╚══════════════════════════════════════════════════════════════════╝\n";
    echo "\n";

    $benchPrompt = "Recommend the best carrier for a 30kg transfer.";
    $iterations = 10;

    echo "Running $iterations iterations of: \"$benchPrompt\"\n\n";

    foreach ($providers as $provider) {
        echo "Provider: " . strtoupper($provider) . "\n";

        $times = [];
        $costs = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);

            try {
                $response = $router->chat($benchPrompt, [], [
                    'provider' => $provider,
                    'no_cache' => true,
                ]);

                $times[] = (microtime(true) - $start) * 1000;
                $costs[] = $response['metadata']['cost_usd'];

                echo ".";
            } catch (Exception $e) {
                echo "X";
            }
        }

        echo "\n";

        if (!empty($times)) {
            $avgTime = round(array_sum($times) / count($times), 2);
            $minTime = round(min($times), 2);
            $maxTime = round(max($times), 2);
            $totalCost = array_sum($costs);

            echo "   Avg: {$avgTime}ms | Min: {$minTime}ms | Max: {$maxTime}ms\n";
            echo "   Total Cost: \${$totalCost}\n";
        } else {
            echo "   All requests failed\n";
        }

        echo "\n";
    }
}

// Recommendations
echo "\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                       RECOMMENDATIONS                            ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$workingProviders = array_filter($results, function($providerResults) {
    return count(array_filter($providerResults, fn($r) => $r['success'])) > 0;
});

if (empty($workingProviders)) {
    echo "❌ NO PROVIDERS WORKING\n";
    echo "\n";
    echo "Action items:\n";
    echo "1. Check your .env file has correct API keys\n";
    echo "2. Verify API keys are valid and not expired\n";
    echo "3. Check network connectivity\n";
    echo "4. Review error messages above\n";
} else {
    echo "✅ Working Providers: " . count($workingProviders) . "/" . count($results) . "\n";
    echo "\n";

    // Find best for each task
    $bestForSpeed = null;
    $bestForCost = null;
    $bestForQuality = null;

    foreach ($workingProviders as $provider => $providerResults) {
        $avgDuration = array_sum(array_column($providerResults, 'duration_ms')) / count($providerResults);
        $totalCost = array_sum(array_column(array_filter($providerResults, fn($r) => $r['success']), 'cost'));

        if ($bestForSpeed === null || $avgDuration < $bestForSpeed['duration']) {
            $bestForSpeed = ['provider' => $provider, 'duration' => $avgDuration];
        }

        if ($bestForCost === null || $totalCost < $bestForCost['cost']) {
            $bestForCost = ['provider' => $provider, 'cost' => $totalCost];
        }
    }

    if ($bestForSpeed) {
        echo "⚡ Fastest: " . strtoupper($bestForSpeed['provider']) . " (" . round($bestForSpeed['duration'], 2) . "ms avg)\n";
    }

    if ($bestForCost) {
        echo "💰 Cheapest: " . strtoupper($bestForCost['provider']) . " (\$" . number_format($bestForCost['cost'], 6) . " total)\n";
    }

    echo "\n";
    echo "Recommended configuration:\n";
    echo "- Quick queries: Use " . strtoupper($bestForSpeed['provider'] ?? 'intelligence_hub') . "\n";
    echo "- Complex analysis: Use anthropic (if available) or " . strtoupper($bestForSpeed['provider'] ?? 'openai') . "\n";
    echo "- Cost optimization: Use " . strtoupper($bestForCost['provider'] ?? 'intelligence_hub') . "\n";
    echo "- Internal context: Use intelligence_hub (has RAG & MCP)\n";
}

echo "\n";
echo "Done! 🎉\n";
echo "\n";
