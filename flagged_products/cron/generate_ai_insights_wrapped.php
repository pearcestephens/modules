#!/usr/bin/env php
<?php
/**
 * Flagged Products - Generate AI Insights (WRAPPED)
 *
 * Wrapped version with full Smart Cron V2 integration, performance logging,
 * circuit breaker, retry logic, and comprehensive monitoring.
 *
 * ChatGPT-powered insights for staff performance and patterns
 *
 * Schedule: Every hour (Priority 4)
 * Timeout: 600 seconds (10 minutes - allows for API calls)
 *
 * @package CIS\FlaggedProducts\Cron
 * @version 2.0.0
 */

declare(strict_types=1);

// Load wrapper
require_once __DIR__ . '/FlaggedProductsCronWrapper.php';

// Configure task
$wrapper = new FlaggedProductsCronWrapper(
    'flagged_products_generate_ai_insights',
    __DIR__ . '/generate_ai_insights.php',
    [
        'timeout' => 600,              // 10 minutes - AI API calls can be slow
        'max_retries' => 2,            // Retry on API failures
        'retry_delay' => 120,          // Wait 2 minutes between AI retries
        'memory_limit' => '256M',
        'enable_circuit_breaker' => true,
        'log_level' => 'INFO',
        'alert_on_failure' => false,   // Don't alert on AI failures (can be flaky)
    ]
);

// Execute
$success = $wrapper->execute();

// Exit with proper code for cron
exit($success ? 0 : 1);
