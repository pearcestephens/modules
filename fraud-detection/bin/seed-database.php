<?php
/**
 * Data Seeding Script for Fraud Detection System
 *
 * Seeds initial data required for system operation:
 * - Fraud pattern library
 * - Communication fraud patterns
 * - Initial configuration
 * - Test data for development
 *
 * Usage:
 *   php seed-database.php
 *   php seed-database.php --environment=production
 *   php seed-database.php --patterns-only
 */

require_once __DIR__ . '/../../../shared/functions/db_connect.php';

// Parse command line arguments
$options = getopt('', ['environment::', 'patterns-only', 'test-data', 'help']);

if (isset($options['help'])) {
    showHelp();
    exit(0);
}

$environment = $options['environment'] ?? 'development';
$patternsOnly = isset($options['patterns-only']);
$includeTestData = isset($options['test-data']);

echo "🌱 Fraud Detection System - Database Seeder\n";
echo str_repeat("=", 60) . "\n";
echo "Environment: $environment\n";
echo "Patterns Only: " . ($patternsOnly ? 'Yes' : 'No') . "\n";
echo "Include Test Data: " . ($includeTestData ? 'Yes' : 'No') . "\n";
echo str_repeat("=", 60) . "\n\n";

try {
    $db = db_connect();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Seed fraud patterns
    seedFraudPatterns($db);

    // Seed communication patterns
    seedCommunicationPatterns($db);

    if (!$patternsOnly) {
        // Seed test data if requested
        if ($includeTestData && $environment !== 'production') {
            seedTestData($db);
        }
    }

    echo "\n✅ Database seeding complete!\n\n";

} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

/**
 * Seed fraud pattern library
 */
function seedFraudPatterns(\PDO $db): void
{
    echo "📚 Seeding fraud pattern library...\n";

    $patterns = [
        [
            'name' => 'Gradual Discount Escalation',
            'category' => 'discount_fraud',
            'description' => 'Staff member slowly increases discount percentages over time to avoid detection',
            'indicators' => [
                'discount_trend' => 'increasing',
                'time_pattern' => 'gradual',
                'customer_pattern' => 'repeat_customers'
            ],
            'detection_rules' => [
                'discount_increase_rate' => 5,
                'time_window_days' => 30,
                'threshold_deviation' => 0.3
            ],
            'severity' => 'HIGH'
        ],
        [
            'name' => 'After-Hours Inventory Manipulation',
            'category' => 'inventory_fraud',
            'description' => 'Adjusting inventory levels during off-peak hours when supervision is minimal',
            'indicators' => [
                'time_pattern' => 'after_hours',
                'action_type' => 'inventory_adjustment',
                'frequency' => 'recurring'
            ],
            'detection_rules' => [
                'after_hours_threshold' => '22:00',
                'before_hours_threshold' => '06:00',
                'frequency_threshold' => 3
            ],
            'severity' => 'CRITICAL'
        ],
        [
            'name' => 'Refund Fraud with Known Customer',
            'category' => 'refund_fraud',
            'description' => 'Processing fraudulent refunds for specific customers in collusion',
            'indicators' => [
                'customer_pattern' => 'repeat_customer',
                'refund_frequency' => 'high',
                'relationship' => 'suspicious'
            ],
            'detection_rules' => [
                'min_refunds_per_month' => 5,
                'customer_relationship_score' => 0.7,
                'refund_amount_threshold' => 500
            ],
            'severity' => 'HIGH'
        ],
        [
            'name' => 'Collusion Ring Network',
            'category' => 'collusion',
            'description' => 'Multiple staff members working together in coordinated fraud',
            'indicators' => [
                'network_pattern' => 'connected_staff',
                'transaction_coordination' => 'true',
                'communication_frequency' => 'high'
            ],
            'detection_rules' => [
                'min_connected_staff' => 3,
                'coordination_timeframe_minutes' => 30,
                'suspicious_communication_count' => 10
            ],
            'severity' => 'CRITICAL'
        ],
        [
            'name' => 'Cash Handling Discrepancies',
            'category' => 'cash_fraud',
            'description' => 'Consistent cash drawer discrepancies indicating theft',
            'indicators' => [
                'cash_variance' => 'negative',
                'frequency' => 'recurring',
                'pattern' => 'end_of_shift'
            ],
            'detection_rules' => [
                'variance_threshold' => -50,
                'frequency_per_month' => 4,
                'variance_trend' => 'consistent'
            ],
            'severity' => 'HIGH'
        ],
        [
            'name' => 'Void Transaction Abuse',
            'category' => 'transaction_fraud',
            'description' => 'Excessive use of void/cancel transactions to pocket cash',
            'indicators' => [
                'void_frequency' => 'high',
                'void_timing' => 'suspicious',
                'void_amount_pattern' => 'round_numbers'
            ],
            'detection_rules' => [
                'void_count_threshold' => 10,
                'time_window_days' => 7,
                'round_number_percentage' => 0.7
            ],
            'severity' => 'MEDIUM'
        ],
        [
            'name' => 'Product Substitution Fraud',
            'category' => 'inventory_fraud',
            'description' => 'Scanning cheaper items while giving expensive items to customers',
            'indicators' => [
                'price_variance' => 'negative',
                'product_category_mismatch' => 'true',
                'customer_pattern' => 'repeat'
            ],
            'detection_rules' => [
                'price_difference_threshold' => 50,
                'mismatch_frequency' => 5,
                'customer_repeat_threshold' => 3
            ],
            'severity' => 'HIGH'
        ],
        [
            'name' => 'Financial Stress Escalation',
            'category' => 'predictive',
            'description' => 'Staff member showing increasing signs of financial stress',
            'indicators' => [
                'credit_score_trend' => 'declining',
                'payroll_advance_requests' => 'increasing',
                'shift_pickup_frequency' => 'excessive'
            ],
            'detection_rules' => [
                'credit_score_drop' => 50,
                'advance_request_count' => 3,
                'extra_shift_threshold' => 8
            ],
            'severity' => 'MEDIUM'
        ],
        [
            'name' => 'Sweethearting',
            'category' => 'discount_fraud',
            'description' => 'Providing unauthorized discounts or free items to friends/family',
            'indicators' => [
                'discount_frequency' => 'high',
                'customer_relationship' => 'known',
                'discount_pattern' => 'consistent'
            ],
            'detection_rules' => [
                'discount_count_threshold' => 15,
                'relationship_score' => 0.8,
                'time_window_days' => 30
            ],
            'severity' => 'MEDIUM'
        ],
        [
            'name' => 'Data Manipulation',
            'category' => 'system_fraud',
            'description' => 'Unauthorized access or modification of system data',
            'indicators' => [
                'access_pattern' => 'unusual',
                'time_pattern' => 'after_hours',
                'data_modification' => 'true'
            ],
            'detection_rules' => [
                'unauthorized_access_count' => 5,
                'data_change_threshold' => 10,
                'access_time' => 'off_hours'
            ],
            'severity' => 'CRITICAL'
        ]
    ];

    $stmt = $db->prepare("
        INSERT INTO fraud_pattern_library (
            pattern_name,
            pattern_category,
            description,
            behavioral_signature,
            detection_rules,
            severity_level
        ) VALUES (
            :pattern_name,
            :pattern_category,
            :description,
            :behavioral_signature,
            :detection_rules,
            :severity_level
        )
        ON DUPLICATE KEY UPDATE
            description = VALUES(description),
            behavioral_signature = VALUES(behavioral_signature),
            detection_rules = VALUES(detection_rules),
            severity_level = VALUES(severity_level)
    ");

    $inserted = 0;
    foreach ($patterns as $pattern) {
        $stmt->execute([
            'pattern_name' => $pattern['name'],
            'pattern_category' => $pattern['category'],
            'description' => $pattern['description'],
            'behavioral_signature' => json_encode($pattern['indicators']),
            'detection_rules' => json_encode($pattern['detection_rules']),
            'severity_level' => $pattern['severity']
        ]);
        $inserted++;
        echo "  ✓ {$pattern['name']}\n";
    }

    echo "  Added $inserted fraud patterns\n\n";
}

/**
 * Seed communication fraud patterns
 */
function seedCommunicationPatterns(\PDO $db): void
{
    echo "💬 Seeding communication fraud patterns...\n";

    $patterns = [
        [
            'name' => 'Collusion Planning Language',
            'category' => 'collusion',
            'keywords' => ['cover for me', 'nobody needs to know', 'between us', 'our secret', 'special customer', 'take care of', 'hook up', 'help out'],
            'combinations' => [
                ['cover', 'transaction'],
                ['delete', 'record'],
                ['nobody', 'know']
            ],
            'severity' => 'HIGH'
        ],
        [
            'name' => 'Evidence Destruction Intent',
            'category' => 'evidence_destruction',
            'keywords' => ['delete', 'erase', 'clear the logs', 'wipe', 'destroy', 'get rid of', 'remove the', 'clean up'],
            'combinations' => [
                ['delete', 'log'],
                ['erase', 'record'],
                ['clear', 'history']
            ],
            'severity' => 'CRITICAL'
        ],
        [
            'name' => 'Financial Stress Indicators',
            'category' => 'financial_stress',
            'keywords' => ['need money', 'desperate', 'rent is due', 'payday loan', 'broke', 'cant afford', 'bills are piling', 'need extra cash'],
            'combinations' => [
                ['need', 'money'],
                ['extra', 'income'],
                ['financial', 'trouble']
            ],
            'severity' => 'MEDIUM'
        ],
        [
            'name' => 'Theft Planning',
            'category' => 'theft_planning',
            'keywords' => ['take some', 'pocket', 'no one will notice', 'easy money', 'quick cash', 'grab', 'snatch', 'slip out'],
            'combinations' => [
                ['take', 'inventory'],
                ['no one', 'notice'],
                ['easy', 'money']
            ],
            'severity' => 'CRITICAL'
        ],
        [
            'name' => 'Customer Discount Coordination',
            'category' => 'discount_fraud',
            'keywords' => ['big discount', 'hook you up', 'special price', 'employee discount', 'family price', 'take care of you', 'good deal'],
            'combinations' => [
                ['discount', 'code'],
                ['special', 'price'],
                ['hook', 'up']
            ],
            'severity' => 'HIGH'
        ],
        [
            'name' => 'Time Clock Fraud',
            'category' => 'time_fraud',
            'keywords' => ['clock in for me', 'punch my timecard', 'say I was here', 'cover my shift', 'mark me present'],
            'combinations' => [
                ['clock', 'for me'],
                ['punch', 'card'],
                ['mark', 'present']
            ],
            'severity' => 'MEDIUM'
        ],
        [
            'name' => 'Inventory Manipulation',
            'category' => 'inventory_fraud',
            'keywords' => ['adjust the count', 'fix the inventory', 'change the numbers', 'recount', 'miscount', 'short the stock'],
            'combinations' => [
                ['adjust', 'inventory'],
                ['change', 'count'],
                ['fix', 'numbers']
            ],
            'severity' => 'HIGH'
        ],
        [
            'name' => 'Refund Fraud Coordination',
            'category' => 'refund_fraud',
            'keywords' => ['fake receipt', 'process refund', 'no receipt return', 'store credit', 'easy return', 'quick refund'],
            'combinations' => [
                ['fake', 'receipt'],
                ['process', 'refund'],
                ['no', 'receipt']
            ],
            'severity' => 'HIGH'
        ],
        [
            'name' => 'Authority Evasion',
            'category' => 'evasion',
            'keywords' => ['boss is gone', 'managers not here', 'no cameras', 'blind spot', 'they dont check', 'no one watching'],
            'combinations' => [
                ['boss', 'gone'],
                ['no one', 'watching'],
                ['blind', 'spot']
            ],
            'severity' => 'MEDIUM'
        ],
        [
            'name' => 'Pressure/Coercion',
            'category' => 'coercion',
            'keywords' => ['you have to', 'if you dont', 'or else', 'better do it', 'no choice', 'make you', 'force'],
            'combinations' => [
                ['have to', 'help'],
                ['or else'],
                ['no', 'choice']
            ],
            'severity' => 'HIGH'
        ]
    ];

    $stmt = $db->prepare("
        INSERT INTO communication_fraud_patterns (
            pattern_name,
            pattern_category,
            keywords,
            keyword_combinations,
            severity_level
        ) VALUES (
            :pattern_name,
            :pattern_category,
            :keywords,
            :keyword_combinations,
            :severity_level
        )
        ON DUPLICATE KEY UPDATE
            keywords = VALUES(keywords),
            keyword_combinations = VALUES(keyword_combinations),
            severity_level = VALUES(severity_level)
    ");

    $inserted = 0;
    foreach ($patterns as $pattern) {
        $stmt->execute([
            'pattern_name' => $pattern['name'],
            'pattern_category' => $pattern['category'],
            'keywords' => json_encode($pattern['keywords']),
            'keyword_combinations' => json_encode($pattern['combinations']),
            'severity_level' => $pattern['severity']
        ]);
        $inserted++;
        echo "  ✓ {$pattern['name']}\n";
    }

    echo "  Added $inserted communication patterns\n\n";
}

/**
 * Seed test data for development
 */
function seedTestData(\PDO $db): void
{
    echo "🧪 Seeding test data...\n";

    // Add test cameras (if none exist)
    $cameraCount = $db->query("SELECT COUNT(*) FROM camera_network")->fetchColumn();

    if ($cameraCount == 0) {
        echo "  Adding test cameras...\n";

        $stmt = $db->prepare("
            INSERT INTO camera_network (
                camera_name, location, outlet_id, camera_type, resolution, fps, priority, online
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");

        $testCameras = [
            ['Test Cam 1 - Register', 'Cash Register 1', 1, 'fixed', '1920x1080', 30, 8],
            ['Test Cam 2 - Entrance', 'Main Entrance', 1, 'dome', '1920x1080', 30, 7],
            ['Test Cam 3 - Stock Room', 'Stock Room', 1, 'fixed', '1280x720', 15, 6],
            ['Test Cam 4 - Back Office', 'Back Office', 1, 'ptz', '1920x1080', 30, 5]
        ];

        foreach ($testCameras as $cam) {
            $stmt->execute($cam);
        }

        echo "    ✓ Added 4 test cameras\n";
    }

    echo "  Test data seeding complete\n\n";
}

/**
 * Show help message
 */
function showHelp(): void
{
    echo <<<HELP
🌱 Fraud Detection Database Seeder

Usage:
  php seed-database.php [options]

Options:
  --environment=ENV    Set environment (development, staging, production)
  --patterns-only      Only seed fraud patterns, skip test data
  --test-data          Include test/sample data (not for production)
  --help               Show this help message

Examples:
  # Seed all patterns for production
  php seed-database.php --environment=production --patterns-only

  # Seed patterns and test data for development
  php seed-database.php --test-data

  # Just seed fraud patterns
  php seed-database.php --patterns-only

HELP;
}
