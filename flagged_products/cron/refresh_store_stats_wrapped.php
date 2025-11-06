#!/usr/bin/env php
<?php
/**
 * Flagged Products - Refresh Store Stats (WRAPPED)
 *
 * Wrapped version with full Smart Cron V2 integration, performance logging,
 * circuit breaker, retry logic, and comprehensive monitoring.
 *
 * Caches store statistics for dashboard performance
 *
 * Schedule: Every 30 minutes (Priority 2 - HIGH FREQUENCY)
 * Timeout: 180 seconds (3 minutes)
 *
 * @package CIS\FlaggedProducts\Cron
 * @version 2.0.0
 */

declare(strict_types=1);

// Load wrapper
require_once __DIR__ . '/FlaggedProductsCronWrapper.php';

// Configure task
$wrapper = new FlaggedProductsCronWrapper(
    'flagged_products_refresh_store_stats',
    __DIR__ . '/refresh_store_stats.php',
    [
        'timeout' => 180,              // 3 minutes - fast cache refresh
        'max_retries' => 2,
        'retry_delay' => 20,           // Short delay - needs to run frequently
        'memory_limit' => '128M',      // Minimal memory for stats caching
        'enable_circuit_breaker' => true,
        'log_level' => 'INFO',
        'alert_on_failure' => true,
    ]
);

// Execute
$success = $wrapper->execute();

// Exit with proper code for cron
exit($success ? 0 : 1);
