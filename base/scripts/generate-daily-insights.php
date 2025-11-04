#!/usr/bin/env php
<?php
/**
 * Generate Daily AI Business Insights
 *
 * This script should be run daily via cron to generate fresh business insights
 *
 * Cron schedule (run at 8 AM daily):
 *   0 8 * * * /usr/bin/php /path/to/generate-daily-insights.php >> /path/to/logs/ai-insights.log 2>&1
 *
 * @package CIS\Base
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use CIS\Base\Core\Application;
use CIS\Base\Services\AIBusinessInsightsService;
use CIS\Base\AIService;

// Get application instance
$app = Application::getInstance();
$logger = $app->make(\CIS\Base\Core\Logger::class);

$startTime = microtime(true);

echo "[" . date('Y-m-d H:i:s') . "] Starting daily AI business insights generation\n";
$logger->info('AI Insights: Daily generation started');

try {
    // Check AI Hub health first
    echo "[" . date('Y-m-d H:i:s') . "] Checking AI Hub connectivity...\n";
    $health = AIService::healthCheck();

    if (!$health['hub_available']) {
        throw new \RuntimeException("AI Hub unavailable: " . ($health['error'] ?? 'Unknown error'));
    }

    echo "[" . date('Y-m-d H:i:s') . "] ✓ AI Hub online (response time: {$health['response_time']}ms)\n";

    // Create insights service
    $insightsService = new AIBusinessInsightsService($app);

    // Generate insights
    echo "[" . date('Y-m-d H:i:s') . "] Generating business insights...\n";
    $insights = $insightsService->generateDailyInsights();

    $executionTime = round(microtime(true) - $startTime, 2);

    // Summary
    $byCritical = count(array_filter($insights, fn($i) => $i['priority'] === 'critical'));
    $byHigh = count(array_filter($insights, fn($i) => $i['priority'] === 'high'));
    $byMedium = count(array_filter($insights, fn($i) => $i['priority'] === 'medium'));

    echo "[" . date('Y-m-d H:i:s') . "] ✓ Generated " . count($insights) . " insights in {$executionTime}s\n";
    echo "[" . date('Y-m-d H:i:s') . "]   - Critical: {$byCritical}\n";
    echo "[" . date('Y-m-d H:i:s') . "]   - High:     {$byHigh}\n";
    echo "[" . date('Y-m-d H:i:s') . "]   - Medium:   {$byMedium}\n";

    // Log summary
    $logger->info('AI Insights: Daily generation completed', [
        'total_insights' => count($insights),
        'critical' => $byCritical,
        'high' => $byHigh,
        'medium' => $byMedium,
        'execution_time' => $executionTime
    ]);

    // Send notifications for critical insights
    if ($byCritical > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] ⚠️  {$byCritical} critical insights require immediate attention\n";

        $criticalInsights = $insightsService->getCriticalInsights();

        foreach ($criticalInsights as $insight) {
            if ($insight['priority'] === 'critical') {
                echo "[" . date('Y-m-d H:i:s') . "]    🔴 {$insight['title']}\n";

                // TODO: Send notification (email, Slack, etc.)
                // $notificationService->sendCriticalAlert($insight);
            }
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] ✓ Daily insights generation completed successfully\n";
    exit(0);

} catch (\Exception $e) {
    $executionTime = round(microtime(true) - $startTime, 2);

    echo "[" . date('Y-m-d H:i:s') . "] ✗ Error: {$e->getMessage()}\n";
    echo "[" . date('Y-m-d H:i:s') . "]   Execution time: {$executionTime}s\n";

    $logger->error('AI Insights: Daily generation failed', [
        'error' => $e->getMessage(),
        'execution_time' => $executionTime,
        'trace' => $e->getTraceAsString()
    ]);

    exit(1);
}
