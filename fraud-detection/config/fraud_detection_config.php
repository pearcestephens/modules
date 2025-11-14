<?php

/**
 * Fraud Detection Configuration
 *
 * This file contains ALL configurable settings for the fraud detection system.
 * Modify these values to tune the system for your business.
 *
 * IMPORTANT: Changes to thresholds affect fraud detection sensitivity.
 * Test thoroughly after making changes.
 */

return [

    // ============================================
    // GLOBAL SETTINGS
    // ============================================

    'global' => [
        'enabled' => true,                      // Master on/off switch
        'dry_run_mode' => false,                // If true, analyze but don't store results
        'analysis_window_days' => 30,           // How many days to analyze
        'min_transaction_count' => 5,           // Minimum transactions before analysis (avoid false positives for new staff)
        'grace_period_days' => 14,              // New staff get this many days before fraud detection activates
        'confidence_threshold' => 0.75,         // Minimum confidence to report (0.0-1.0)
        'enable_all_sections' => true,          // Enable all 7 fraud sections
    ],

    // ============================================
    // SECTION 1: PAYMENT TYPE FRAUD THRESHOLDS
    // ============================================

    'payment_type_fraud' => [
        'enabled' => true,

        // Unusual payment type detection
        'unusual_payment_type_threshold' => 5,          // Uses per week to be considered "normal"
        'unusual_payment_type_severity' => 0.70,        // Severity score (0.0-1.0)

        // Random payment type (low frequency, high value)
        'random_payment_min_value' => 100,              // Minimum transaction value to flag
        'random_payment_max_uses_per_week' => 1,        // Maximum uses per week before suspicious
        'random_payment_severity' => 0.75,

        // Split payment patterns
        'split_payment_daily_threshold' => 5,           // Max split payments per day
        'split_payment_severity' => 0.65,

        // Cash vs card ratio
        'cash_ratio_variance_threshold' => 20,          // % difference from outlet average
        'cash_ratio_severity' => 0.60,

        // Standard payment types (anything else is "unusual")
        'standard_payment_types' => [
            'CASH',
            'EFTPOS',
            'CREDIT_CARD',
            'DEBIT_CARD',
            'ACCOUNT',
            'BANK_TRANSFER',
        ],
    ],

    // ============================================
    // SECTION 2: CUSTOMER ACCOUNT FRAUD THRESHOLDS
    // ============================================

    'customer_account_fraud' => [
        'enabled' => true,

        // Account sales frequency
        'account_sales_per_week_threshold' => 3,        // Max account sales per week to same customer
        'account_sales_severity' => 0.80,

        // Store credit usage
        'store_credit_total_threshold' => 500,          // Total store credit used in period
        'store_credit_severity' => 0.75,

        // Loyalty points
        'loyalty_redemption_vs_earned_ratio' => 2.0,    // Redeemed vs earned ratio threshold
        'loyalty_manipulation_severity' => 0.80,

        // Random customer assignment
        'unique_customer_percentage_threshold' => 80,    // % of sales with unique customers
        'random_assignment_severity' => 0.70,

        // Suspicious customer names (regex patterns)
        'suspicious_customer_patterns' => [
            '/test/i',
            '/dummy/i',
            '/fake/i',
            '/random/i',
            '/temp/i',
            '/asdf/i',
            '/qwerty/i',
            '/zzz/i',
            '/aaa/i',
            '/xxx/i',
            '/cash\s*customer/i',
            '/walk\s*in/i',
            '/walkin/i',
            '/no\s*name/i',
        ],
        'suspicious_customer_severity' => 0.85,
    ],

    // ============================================
    // SECTION 3: INVENTORY MOVEMENT FRAUD THRESHOLDS
    // ============================================

    'inventory_fraud' => [
        'enabled' => true,

        // Stock adjustments
        'adjustment_qty_threshold' => 10,               // Qty adjusted in single adjustment
        'adjustment_severity' => 0.70,

        // Adjustments without reason
        'missing_reason_severity' => 0.80,

        // Stock transfers
        'transfer_count_threshold' => 10,               // Number of transfers in period
        'transfer_value_threshold' => 5000,             // Total value of transfers
        'transfer_severity' => 0.65,

        // Receiving discrepancies
        'receiving_discrepancy_threshold' => 20,        // Total items discrepancy
        'receiving_severity' => 0.75,

        // Shrinkage
        'shrinkage_value_threshold' => 500,             // Total shrinkage value
        'shrinkage_severity' => 0.80,
    ],

    // ============================================
    // SECTION 4: CASH REGISTER CLOSURE FRAUD THRESHOLDS
    // ============================================

    'register_closure_fraud' => [
        'enabled' => true,

        // Cash variances
        'cash_variance_threshold' => 50,                // Variance amount (dollars)
        'cash_shortage_severity' => 0.90,               // Shortages more serious
        'cash_overage_severity' => 0.60,                // Overages less serious

        // Skimming pattern detection
        'small_shortage_min' => 5,                      // Minimum for "small" shortage
        'small_shortage_max' => 20,                     // Maximum for "small" shortage
        'skimming_pattern_count' => 5,                  // Number of small shortages to trigger pattern
        'skimming_pattern_severity' => 0.85,

        // Float manipulation
        'float_stddev_threshold' => 50,                 // Standard deviation of float amounts
        'float_manipulation_severity' => 0.75,
    ],

    // ============================================
    // SECTION 5: BANKING & DEPOSIT FRAUD THRESHOLDS
    // ============================================

    'banking_fraud' => [
        'enabled' => true,

        // Deposit discrepancies
        'deposit_discrepancy_threshold' => 50,          // Dollars
        'deposit_discrepancy_severity' => 0.85,

        // Delayed deposits
        'deposit_delay_threshold_days' => 3,            // Days
        'deposit_delay_severity' => 0.70,

        // Missing deposits
        'missing_deposit_threshold' => 100,             // Cash sales without deposit
        'missing_deposit_severity' => 0.90,

        // Weekly reconciliation
        'weekly_reconciliation_gap' => 200,             // Dollars
        'weekly_gap_severity' => 0.80,
    ],

    // ============================================
    // SECTION 6: TRANSACTION MANIPULATION THRESHOLDS
    // ============================================

    'transaction_manipulation' => [
        'enabled' => true,

        // Void patterns
        'void_percentage_threshold' => 10,              // % of transactions
        'void_severity' => 0.80,

        // Immediate voids (suspicious timing)
        'immediate_void_minutes' => 5,                  // Minutes after sale
        'immediate_void_count_threshold' => 5,          // Count to trigger alert
        'immediate_void_severity' => 0.75,

        // Refund patterns
        'refund_percentage_threshold' => 15,            // % of sales
        'refund_severity' => 0.75,

        // Discount abuse
        'discount_percentage_threshold' => 20,          // Average discount %
        'discount_severity' => 0.70,

        // Price overrides
        'price_override_count_threshold' => 20,         // Count in period
        'price_override_severity' => 0.70,
    ],

    // ============================================
    // SECTION 7: RECONCILIATION FRAUD THRESHOLDS
    // ============================================

    'reconciliation_fraud' => [
        'enabled' => true,

        // Daily reconciliation
        'daily_reconciliation_gap' => 50,               // Dollars
        'daily_gap_severity' => 0.80,

        // Cross-outlet anomalies
        'cross_outlet_avg_transaction_ratio' => 2.0,    // Max/Min avg transaction ratio
        'cross_outlet_severity' => 0.65,
    ],

    // ============================================
    // OUTLET-SPECIFIC OVERRIDES
    // ============================================

    'outlet_overrides' => [
        // Example: Outlet 1 has higher cash ratio due to location
        // 'outlet_1' => [
        //     'payment_type_fraud' => [
        //         'cash_ratio_variance_threshold' => 30,  // Higher tolerance
        //     ],
        // ],

        // Example: Outlet 5 is high-volume, more splits expected
        // 'outlet_5' => [
        //     'payment_type_fraud' => [
        //         'split_payment_daily_threshold' => 10,
        //     ],
        // ],
    ],

    // ============================================
    // STAFF-SPECIFIC EXCLUSIONS
    // ============================================

    'staff_exclusions' => [
        // Managers and supervisors who legitimately do unusual things
        'excluded_staff_ids' => [
            // 1,  // Manager - processes refunds frequently
            // 5,  // Stock Manager - does inventory adjustments
        ],

        // Partial exclusions (exclude specific checks for specific staff)
        'partial_exclusions' => [
            // Example: Stock manager can do adjustments without triggering alerts
            // 5 => [
            //     'sections' => ['inventory_fraud'],
            // ],

            // Example: Manager can do price overrides
            // 1 => [
            //     'indicators' => ['excessive_price_overrides'],
            // ],
        ],
    ],

    // ============================================
    // WHITELISTING & FALSE POSITIVE EXCLUSIONS
    // ============================================

    'whitelisting' => [
        // Legitimate payment types that might look unusual
        'whitelisted_payment_types' => [
            // 'GIFT_VOUCHER',
            // 'PROMOTIONAL_CREDIT',
        ],

        // Legitimate customer accounts (corporate accounts, etc.)
        'whitelisted_customer_ids' => [
            // 'CORP_ACCOUNT_123',
        ],

        // Products that legitimately have high adjustments (perishables, etc.)
        'whitelisted_product_ids' => [
            // 'PROD_456',
        ],

        // Known legitimate reasons for stock adjustments
        'legitimate_adjustment_reasons' => [
            'damaged',
            'expired',
            'sample',
            'promotional',
            'display',
            'manager approved',
        ],
    ],

    // ============================================
    // ALERT CONFIGURATION
    // ============================================

    'alerts' => [
        'enabled' => true,

        // Risk levels that trigger alerts
        'alert_risk_levels' => ['high', 'critical'],    // low, medium, high, critical

        // Minimum risk score to alert
        'alert_risk_score_threshold' => 80,             // 0-100

        // Email alerts
        'email_alerts' => [
            'enabled' => true,
            'recipients' => [
                'security@company.com',
                'manager@company.com',
            ],
            'from_address' => 'fraud-detection@company.com',
            'subject_prefix' => '[FRAUD ALERT]',
        ],

        // Slack alerts (future)
        'slack_alerts' => [
            'enabled' => false,
            'webhook_url' => '',
            'channel' => '#fraud-alerts',
        ],

        // SMS alerts (future)
        'sms_alerts' => [
            'enabled' => false,
            'provider' => 'twilio',
            'recipients' => [
                // '+64211234567',
            ],
        ],

        // Alert throttling (prevent spam)
        'alert_throttle' => [
            'max_alerts_per_staff_per_day' => 3,        // Max alerts for same staff member
            'cooldown_hours' => 6,                      // Hours between alerts for same staff
        ],
    ],

    // ============================================
    // PERFORMANCE & OPTIMIZATION
    // ============================================

    'performance' => [
        // Batch processing
        'batch_size' => 50,                             // Staff analyzed per batch
        'batch_delay_seconds' => 2,                     // Delay between batches

        // Query optimization
        'use_query_cache' => true,
        'cache_ttl_seconds' => 3600,                    // 1 hour

        // Memory limits
        'max_memory_mb' => 512,                         // Max memory for analysis
        'memory_check_frequency' => 10,                 // Check every N staff
    ],

    // ============================================
    // AUDIT & LOGGING
    // ============================================

    'audit' => [
        'enabled' => true,

        // Log all analysis runs
        'log_analysis_runs' => true,
        'log_file' => '/var/log/fraud_detection/analysis.log',

        // Log all investigations
        'log_investigations' => true,

        // Retention
        'analysis_retention_days' => 365,               // Keep analysis results for 1 year
        'log_retention_days' => 90,                     // Keep logs for 90 days
    ],

    // ============================================
    // LEARNING & ADAPTATION
    // ============================================

    'learning' => [
        'enabled' => true,

        // Track false positives
        'track_false_positives' => true,

        // Auto-adjust thresholds based on false positive rate
        'auto_tune_thresholds' => false,                // Disabled by default - requires testing
        'auto_tune_target_false_positive_rate' => 0.05, // Target 5% FP rate

        // Seasonal adjustments (e.g., Christmas has more refunds)
        'seasonal_adjustments' => [
            'enabled' => false,
            'periods' => [
                // 'christmas' => [
                //     'start' => '12-01',
                //     'end' => '01-07',
                //     'multipliers' => [
                //         'refund_percentage_threshold' => 1.5,  // 50% higher threshold
                //     ],
                // ],
            ],
        ],
    ],

    // ============================================
    // INTEGRATION SETTINGS
    // ============================================

    'integration' => [
        // Camera correlation
        'enable_camera_correlation' => true,
        'camera_correlation_weight' => 0.3,             // Weight in overall score

        // Location tracking
        'enable_location_tracking' => true,
        'location_tracking_weight' => 0.2,

        // System access logs
        'enable_access_logs' => true,
        'access_logs_weight' => 0.1,
    ],

    // ============================================
    // DEVELOPMENT & TESTING
    // ============================================

    'development' => [
        'debug_mode' => false,                          // Verbose logging
        'test_mode' => false,                           // Use test database
        'sample_data_only' => false,                    // Only analyze sample staff
        'sample_staff_ids' => [1, 2, 3],               // Staff to analyze in sample mode
    ],

];
