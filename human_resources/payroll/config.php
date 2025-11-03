<?php
/**
 * Payroll Module - Configuration
 *
 * Central configuration for payroll processing rules
 *
 * @package Payroll
 * @version 1.0.0
 * @created 2025-11-02
 */

declare(strict_types=1);

return [
    // Pay period settings
    'WEEK_START' => 'Tuesday',
    'WEEK_END' => 'Monday',

    // Timezone (NZ Standard Time)
    'TZ' => 'Pacific/Auckland',

    // Health check - tables that must exist
    'HEALTH_TABLES' => [
        'deputy_timesheets',
        'payroll_activity_log',
        'payroll_rate_limits',
        'payroll_runs',
        'payroll_applications',
        'payroll_dlq',
        'staff_identity_map',
        'payroll_residuals',
        'staff_leave_balances',
        'payroll_bonus_events',
    ],

    // Bonus caps (in cents)
    'BONUS_CAPS' => [
        'GOOGLE_REVIEW' => 5000, // $50.00 max per review
        'MANUAL' => null,        // No cap
        'PERFORMANCE' => null,   // No cap
    ],

    // Leave conversion rules
    'LEAVE_CONVERSIONS' => [
        'HOURS_TO_DAYS' => 8.0,  // 1 day = 8 hours
        'DAYS_TO_HOURS' => 0.125, // 1 hour = 0.125 days
    ],

    // DLQ thresholds
    'DLQ_ALERT_THRESHOLD' => 10, // Alert if more than 10 items in DLQ

    // Drift detection thresholds
    'DRIFT_THRESHOLD_CENTS' => 100, // Alert if drift > $1.00
    'DRIFT_THRESHOLD_HOURS' => 0.25, // Alert if drift > 15 minutes

    // Rate limit retry defaults
    'RATE_LIMIT_DEFAULT_RETRY' => 60, // seconds

    // Replay settings
    'REPLAY_BATCH_SIZE' => 50,
    'REPLAY_DELAY_MS' => 500,

    // Windowing rules
    'WINDOW_TOLERANCE_DAYS' => 7, // Accept timesheets within 7 days of period

    // Authentication
    'AUTH_REQUIRED' => env('PAYROLL_AUTH_ENABLED', 'false') === 'true',
];
