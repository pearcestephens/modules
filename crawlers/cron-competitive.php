#!/usr/bin/env php
<?php
/**
 * Competitive Intelligence - Daily Cron Job
 *
 * Runs daily at 2:00 AM NZT
 * - Scans all competitors
 * - Extracts prices and specials
 * - Generates pricing recommendations
 * - Sends specials to news feed
 *
 * Crontab entry:
 * 0 2 * * * /usr/bin/php /home/129337.cloudwaysapps.com/jcepnzzkmj/public_html/modules/crawlers/cron-competitive.php >> /var/log/cis-crawlers/cron.log 2>&1
 *
 * @package CIS\Crawlers
 * @version 1.0.0
 */

// Set execution environment
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('memory_limit', '512M');
set_time_limit(0); // No time limit

// Database connection
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once __DIR__ . '/CentralLogger.php';
require_once __DIR__ . '/CompetitiveIntelCrawler.php';
require_once __DIR__ . '/DynamicPricingEngine.php';

use CIS\Crawlers\CentralLogger;
use CIS\Crawlers\CompetitiveIntelCrawler;
use CIS\Crawlers\DynamicPricingEngine;

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  COMPETITIVE INTELLIGENCE DAILY SCAN\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // PHASE 1: Competitive Scan
    echo "PHASE 1: Scanning Competitors...\n";
    echo "-----------------------------------\n";

    $crawler = new CompetitiveIntelCrawler($db, [
        'enable_chrome' => true,
        'max_concurrent' => 3,
        'send_specials_to_news' => true,
    ]);

    $scanResults = $crawler->executeDailyScan();

    echo "✓ Scan completed!\n";
    echo "  - Competitors scanned: {$scanResults['successful']} / {$scanResults['total_competitors']}\n";
    echo "  - Products found: {$scanResults['products_found']}\n";
    echo "  - Specials detected: {$scanResults['specials_found']}\n";
    echo "  - Failed: {$scanResults['failed']}\n";

    if (!empty($scanResults['errors'])) {
        echo "  - Errors:\n";
        foreach ($scanResults['errors'] as $error) {
            echo "    * {$error['competitor']}: {$error['error']}\n";
        }
    }

    echo "\n";

    // PHASE 2: Generate Pricing Recommendations
    echo "PHASE 2: Generating Pricing Recommendations...\n";
    echo "-----------------------------------------------\n";

    $pricingEngine = new DynamicPricingEngine($db, [
        'default_strategy' => DynamicPricingEngine::STRATEGY_MARGIN,
        'target_margin_percent' => 35,
        'auto_approve_under' => 5,
    ]);

    $recommendations = $pricingEngine->generateRecommendations();

    echo "✓ Recommendations generated!\n";
    echo "  - Total recommendations: " . count($recommendations) . "\n";

    $pending = array_filter($recommendations, fn($r) => $r['status'] === 'pending');
    $autoApproved = array_filter($recommendations, fn($r) => isset($r['auto_approved']) && $r['auto_approved']);

    echo "  - Pending review: " . count($pending) . "\n";
    echo "  - Auto-approved: " . count($autoApproved) . "\n";

    echo "\n";

    // PHASE 3: Apply Auto-Approved (if any)
    if (count($autoApproved) > 0) {
        echo "PHASE 3: Applying Auto-Approved Prices...\n";
        echo "------------------------------------------\n";

        $applyResults = $pricingEngine->applyApprovedRecommendations();

        echo "✓ Auto-approved prices processed!\n";
        echo "  - Applied: {$applyResults['applied']}\n";
        echo "  - Failed: {$applyResults['failed']}\n";
        echo "\n";
    }

    // Summary
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  SUMMARY\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Status: SUCCESS\n";
    echo "Session ID: " . $crawler->getSessionId() . "\n";
    echo "Products tracked: {$scanResults['products_found']}\n";
    echo "Specials found: {$scanResults['specials_found']}\n";
    echo "Pricing recommendations: " . count($recommendations) . "\n";
    echo "Completed: " . date('Y-m-d H:i:s') . "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    exit(0);

} catch (Exception $e) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  ERROR\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Status: FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    exit(1);
}
