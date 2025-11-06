#!/usr/bin/env php
<?php
/**
 * Flagged Products - Generate Daily Products (WRAPPED)
 *
 * Wrapped version with full Smart Cron V2 integration, performance logging,
 * circuit breaker, retry logic, and comprehensive monitoring.
 *
 * Schedule: Daily at 7:05 AM (Priority 1 - CRITICAL)
 * Timeout: 600 seconds (10 minutes)
 *
 * @package CIS\FlaggedProducts\Cron
 * @version 2.0.0
 */

declare(strict_types=1);

// Load wrapper
require_once __DIR__ . '/FlaggedProductsCronWrapper.php';

// Configure task
$wrapper = new FlaggedProductsCronWrapper(
    'flagged_products_generate_daily_products',
    __DIR__ . '/generate_daily_products.php',
    [
        'timeout' => 600,              // 10 minutes - this is the most critical task
        'max_retries' => 3,            // Try 3 times before giving up
        'retry_delay' => 60,           // Wait 60 seconds between retries
        'memory_limit' => '512M',      // Generous memory for processing all outlets
        'enable_circuit_breaker' => true,
        'log_level' => 'INFO',
        'alert_on_failure' => true,
    ]
);

// Execute
$success = $wrapper->execute();

// Exit with proper code for cron
exit($success ? 0 : 1);
