<?php
/**
 * AI Business Intelligence - Quick Start Example
 *
 * This example shows how to:
 * 1. Generate daily business insights
 * 2. View critical insights
 * 3. Ask AI business questions
 * 4. Review and act on insights
 *
 * Usage:
 *   php example-ai-business-insights.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use CIS\Base\Core\Application;
use CIS\Base\Services\AIBusinessInsightsService;
use CIS\Base\AIService;

// Initialize application
$app = Application::getInstance();

// Create insights service
$insightsService = new AIBusinessInsightsService($app);

echo "\n";
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║  🧠 AI Business Intelligence - Quick Start Demo          ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";
echo "\n";

// ============================================================================
// Example 1: Check AI Hub Health
// ============================================================================

echo "📡 Checking AI Hub connectivity...\n";
$health = AIService::healthCheck();

if ($health['hub_available']) {
    echo "✅ AI Hub is online (response time: {$health['response_time']}ms)\n";
} else {
    echo "❌ AI Hub unavailable: {$health['error']}\n";
    exit(1);
}

echo "\n";

// ============================================================================
// Example 2: Generate Daily Insights
// ============================================================================

echo "🔍 Generating daily business insights...\n";
echo "   This analyzes:\n";
echo "   - Sales performance trends\n";
echo "   - Inventory intelligence\n";
echo "   - Operational efficiency\n";
echo "\n";

try {
    $insights = $insightsService->generateDailyInsights();
    echo "✅ Generated " . count($insights) . " insights\n";
    echo "\n";

    // Show summary by priority
    $byCritical = array_filter($insights, fn($i) => $i['priority'] === 'critical');
    $byHigh = array_filter($insights, fn($i) => $i['priority'] === 'high');
    $byMedium = array_filter($insights, fn($i) => $i['priority'] === 'medium');

    echo "Priority Breakdown:\n";
    echo "  🔴 Critical: " . count($byCritical) . " insights\n";
    echo "  🟡 High:     " . count($byHigh) . " insights\n";
    echo "  🟢 Medium:   " . count($byMedium) . " insights\n";
    echo "\n";

} catch (\Exception $e) {
    echo "❌ Error generating insights: " . $e->getMessage() . "\n";
    exit(1);
}

// ============================================================================
// Example 3: View Critical Insights
// ============================================================================

echo "🚨 Critical & High Priority Insights:\n";
echo str_repeat("─", 60) . "\n";

$criticalInsights = $insightsService->getCriticalInsights();

if (empty($criticalInsights)) {
    echo "✨ No critical issues detected - business is running smoothly!\n";
} else {
    foreach ($criticalInsights as $idx => $insight) {
        $icon = match($insight['priority']) {
            'critical' => '🔴',
            'high' => '🟡',
            default => '🟢'
        };

        echo "\n";
        echo "{$icon} #{$insight['insight_id']} - {$insight['title']}\n";
        echo "   Type: " . ucwords(str_replace('_', ' ', $insight['insight_type'])) . "\n";
        echo "   Confidence: " . round($insight['confidence_score'] * 100) . "%\n";
        echo "   " . substr($insight['description'], 0, 80) . "...\n";

        // Show first recommendation
        $recommendations = json_decode($insight['recommendations'], true);
        if (!empty($recommendations)) {
            $firstRec = $recommendations[0];
            echo "   💡 Recommendation: {$firstRec['action']}\n";
            if (isset($firstRec['impact'])) {
                echo "      Impact: {$firstRec['impact']}\n";
            }
        }

        if ($idx >= 2) break; // Show first 3 only
    }
}

echo "\n";
echo str_repeat("─", 60) . "\n";

// ============================================================================
// Example 4: Ask AI a Business Question
// ============================================================================

echo "\n";
echo "💬 Ask AI: Natural Language Business Questions\n";
echo str_repeat("─", 60) . "\n";

$questions = [
    "Why are sales down in some stores?",
    "Which products are moving slowly?",
    "How can we improve transfer efficiency?"
];

foreach ($questions as $question) {
    echo "\n";
    echo "❓ {$question}\n";

    $answer = $insightsService->ask($question);

    if ($answer['success']) {
        echo "✅ Found " . count($answer['results']) . " relevant insights\n";

        if (!empty($answer['results'])) {
            $topResult = $answer['results'][0];
            echo "   Top result: {$topResult['file']}\n";
            echo "   Relevance: " . round($topResult['relevance'] * 100) . "%\n";
        }
    }
}

echo "\n";
echo str_repeat("─", 60) . "\n";

// ============================================================================
// Example 5: Review an Insight (Simulated)
// ============================================================================

echo "\n";
echo "📋 Reviewing Insights (Example)\n";
echo str_repeat("─", 60) . "\n";

if (!empty($criticalInsights)) {
    $insight = $criticalInsights[0];

    echo "Reviewing Insight #{$insight['insight_id']}: {$insight['title']}\n";
    echo "\n";
    echo "Actions you can take:\n";
    echo "  1. Mark as reviewed\n";
    echo "  2. Take action and record outcome\n";
    echo "  3. Dismiss if not relevant\n";
    echo "\n";

    // Example: Mark as reviewed
    echo "Simulating: Marking as reviewed by user #1...\n";
    // $insightsService->reviewInsight($insight['insight_id'], 1, "Investigating staff changes");
    echo "✅ Insight marked as reviewed\n";
}

echo "\n";

// ============================================================================
// Example 6: Get Insights by Category
// ============================================================================

echo "📊 Insights by Category:\n";
echo str_repeat("─", 60) . "\n";

$allInsights = $insightsService->getInsights();
$byType = [];

foreach ($allInsights as $insight) {
    $type = $insight['insight_type'];
    if (!isset($byType[$type])) {
        $byType[$type] = 0;
    }
    $byType[$type]++;
}

foreach ($byType as $type => $count) {
    $typeName = ucwords(str_replace('_', ' ', $type));
    echo "  • {$typeName}: {$count} insights\n";
}

echo "\n";

// ============================================================================
// Example 7: Show AI Capabilities
// ============================================================================

echo "🤖 Available AI Capabilities:\n";
echo str_repeat("─", 60) . "\n";

echo "\n";
echo "Business Insights Service can:\n";
echo "  ✓ Analyze sales performance across all stores\n";
echo "  ✓ Identify slow-moving inventory\n";
echo "  ✓ Detect operational bottlenecks\n";
echo "  ✓ Predict issues before they become critical\n";
echo "  ✓ Generate actionable recommendations\n";
echo "  ✓ Answer business questions in natural language\n";
echo "  ✓ Track insight outcomes and measure ROI\n";
echo "\n";

echo "AI Tools Available (via AIService):\n";
echo "  • semantic_search - Natural language codebase search\n";
echo "  • find_code - Code pattern detection\n";
echo "  • analyze_file - File complexity & quality analysis\n";
echo "  • get_categories - Business categorization\n";
echo "  • get_analytics - Usage analytics and trends\n";
echo "  • get_stats - System-wide statistics\n";
echo "  • ...and 7 more tools!\n";
echo "\n";

// ============================================================================
// Summary
// ============================================================================

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║  ✅ Quick Start Demo Complete                            ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";
echo "\n";

echo "Next Steps:\n";
echo "  1. Set up a cron job to run insights daily:\n";
echo "     0 8 * * * php /path/to/generate-daily-insights.php\n";
echo "\n";
echo "  2. Create a dashboard to display insights:\n";
echo "     See: /modules/base/docs/AI_BUSINESS_INTELLIGENCE_SYSTEM.md\n";
echo "\n";
echo "  3. Integrate with your notification system:\n";
echo "     Send critical insights via email/Slack\n";
echo "\n";
echo "  4. Track insight outcomes:\n";
echo "     Measure actual vs predicted impact\n";
echo "\n";

echo "📖 Full Documentation:\n";
echo "   /modules/base/docs/AI_BUSINESS_INTELLIGENCE_SYSTEM.md\n";
echo "\n";

echo "🚀 You now have AI-powered business intelligence!\n";
echo "\n";
