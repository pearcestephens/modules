#!/usr/bin/env php
<?php
/**
 * News Aggregator Cron Job
 *
 * Run this script via cron to automatically crawl news sources
 *
 * Usage:
 *   # Every hour
 *   0 * * * * /usr/bin/php /path/to/cron-crawler.php >> /var/log/cis-news-crawler.log 2>&1
 *
 *   # Every 30 minutes
 *   */30 * * * * /usr/bin/php /path/to/cron-crawler.php
 *
 * @package CIS_Themes
 * @subpackage NewsAggregator
 */

// CLI only
if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line\n");
}

// Load dependencies
$basePath = dirname(__DIR__, 2); // Go up to public_html
require_once $basePath . '/config/database.php'; // Adjust path as needed

// Database connection
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// Load service classes
require_once __DIR__ . '/AggregatorService.php';

use CIS\NewsAggregator\AggregatorService;

// Initialize service
$config = [
    'user_agent' => 'CIS News Aggregator Bot/1.0 (+https://staff.vapeshed.co.nz)',
    'timeout' => 15,
    'max_redirects' => 3,
    'image_cache_dir' => '/uploads/news-images/',
    'rate_limit_delay' => 2,
];

$aggregator = new AggregatorService($db, $config);

// Log start
$startTime = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Starting news aggregator crawl...\n";

// Run crawls
try {
    $results = $aggregator->runScheduledCrawls();

    // Log results
    $totalFound = 0;
    $totalNew = 0;
    $successCount = 0;
    $failCount = 0;

    foreach ($results as $sourceId => $result) {
        if ($result['success']) {
            $successCount++;
            $totalFound += $result['articles_found'];
            $totalNew += $result['articles_new'];
            echo "[SUCCESS] Source $sourceId: {$result['articles_new']} new articles (out of {$result['articles_found']} found) in {$result['execution_time']}s\n";
        } else {
            $failCount++;
            echo "[FAILED] Source $sourceId: {$result['error']}\n";
        }
    }

    $executionTime = round(microtime(true) - $startTime, 2);

    echo "\n";
    echo "========================================\n";
    echo "Crawl completed in {$executionTime}s\n";
    echo "Sources crawled: " . count($results) . "\n";
    echo "Successful: $successCount\n";
    echo "Failed: $failCount\n";
    echo "Total articles found: $totalFound\n";
    echo "New articles saved: $totalNew\n";
    echo "========================================\n";

    exit(0);

} catch (Exception $e) {
    $executionTime = round(microtime(true) - $startTime, 2);
    echo "[ERROR] Crawl failed after {$executionTime}s: " . $e->getMessage() . "\n";
    exit(1);
}
