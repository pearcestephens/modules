#!/usr/bin/env php
<?php
/**
 * Flagged Products - Check Achievements (WRAPPED)
 *
 * Wrapped version with full Smart Cron V2 integration, performance logging,
 * circuit breaker, retry logic, and comprehensive monitoring.
 *
 * Awards badges and achievements to staff based on performance
 *
 * Schedule: Every 6 hours (Priority 3)
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
    'flagged_products_check_achievements',
    __DIR__ . '/check_achievements.php',
    [
        'timeout' => 300,              // 5 minutes
        'max_retries' => 2,
        'retry_delay' => 30,
        'memory_limit' => '256M',
        'enable_circuit_breaker' => true,
        'log_level' => 'INFO',
        'alert_on_failure' => true,
    ]
);

// Execute
$success = $wrapper->execute();

// Exit with proper code for cron
exit($success ? 0 : 1);
