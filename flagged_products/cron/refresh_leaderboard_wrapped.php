#!/usr/bin/env php
<?php
/**
 * Flagged Products - Refresh Leaderboard (WRAPPED)
 *
 * Wrapped version with full Smart Cron V2 integration, performance logging,
 * circuit breaker, retry logic, and comprehensive monitoring.
 *
 * Schedule: Daily at 2:00 AM (Priority 3)
 * Timeout: 300 seconds (5 minutes)
 *
 * @package CIS\FlaggedProducts\Cron
 * @version 2.0.0
 */

declare(strict_types=1);

// Load wrapper
require_once __DIR__ . '/FlaggedProductsCronWrapper.php';

// Configure task
$wrapper = new FlaggedProductsCronWrapper(
    'flagged_products_refresh_leaderboard',
    __DIR__ . '/refresh_leaderboard.php',
    [
        'timeout' => 300,              // 5 minutes
        'max_retries' => 2,            // Less critical, 2 retries sufficient
        'retry_delay' => 30,           // Shorter delay for leaderboard
        'memory_limit' => '256M',      // Less memory needed
        'enable_circuit_breaker' => true,
        'log_level' => 'INFO',
        'alert_on_failure' => true,
    ]
);

// Execute
$success = $wrapper->execute();

// Exit with proper code for cron
exit($success ? 0 : 1);
