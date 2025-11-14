<?php
/**
 * AI Shadow Staff Engine (Digital Twins)
 * Creates AI-powered behavioral baselines for each staff member
 * Detects anomalies by comparing real-time behavior to digital twin
 *
 * Concept:
 * - Every staff member has a "Digital Twin" - an AI model of their normal behavior
 * - The twin learns from 6 months of data across ALL behavioral dimensions
 * - Real-time behavior is compared to the twin
 * - Deviations trigger alerts: "John is behaving 73% differently than his Digital Twin"
 *
 * Learning Dimensions:
 * - Transaction patterns (timing, frequency, amounts)
 * - Discount behavior (frequency, amounts, timing)
 * - Physical behavior (movement, stress levels, camera interactions)
 * - Communication patterns (frequency, sentiment, recipients)
 * - Work schedule patterns (punctuality, breaks, overtime)
 * - System access patterns (screens accessed, time spent)
 * - Customer interaction patterns (service time, satisfaction)
 * - Inventory handling patterns (speed, accuracy, discrepancies)
 *
 * @package FraudDetection
 * @version 2.0.0
 * @author Ecigdis Intelligence System
 */

namespace FraudDetection;

use PDO;
use Exception;

class AIShadowStaffEngine
{
    private PDO $db;
    private array $config;

    // Learning periods
    private const BASELINE_LEARNING_PERIOD = 180; // 6 months
    private const MINIMUM_LEARNING_DAYS = 30; // Minimum data required
    private const RECALIBRATION_INTERVAL = 7; // Days between twin updates

    // Deviation thresholds
    private const MINOR_DEVIATION = 0.30;    // 30% difference
    private const MODERATE_DEVIATION = 0.50; // 50% difference
    private const MAJOR_DEVIATION = 0.70;    // 70% difference
    private const CRITICAL_DEVIATION = 0.85; // 85% difference

    /**
     * Behavioral dimensions tracked in digital twin
     */
    private const BEHAVIORAL_DIMENSIONS = [
        'transaction_patterns' => [
            'weight' => 0.20,
            'metrics' => ['avg_transaction_value', 'transaction_frequency', 'peak_hours', 'day_of_week_distribution']
        ],
        'discount_behavior' => [
            'weight' => 0.20,
            'metrics' => ['discount_frequency', 'avg_discount_amount', 'discount_timing', 'discount_reasons']
        ],
        'physical_behavior' => [
            'weight' => 0.15,
            'metrics' => ['movement_patterns', 'stress_indicators', 'camera_awareness', 'posture']
        ],
        'communication_patterns' => [
            'weight' => 0.10,
            'metrics' => ['message_frequency', 'sentiment', 'recipient_diversity', 'code_word_usage']
        ],
        'work_schedule' => [
            'weight' => 0.10,
            'metrics' => ['punctuality', 'break_patterns', 'overtime_frequency', 'shift_consistency']
        ],
        'system_access' => [
            'weight' => 0.10,
            'metrics' => ['screens_accessed', 'time_per_screen', 'access_timing', 'feature_usage']
        ],
        'customer_interaction' => [
            'weight' => 0.10,
            'metrics' => ['service_time', 'customer_satisfaction', 'complaint_rate', 'repeat_customer_rate']
        ],
        'inventory_handling' => [
            'weight' => 0.05,
            'metrics' => ['processing_speed', 'accuracy_rate', 'discrepancy_rate', 'item_categories']
        ]
    ];

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = array_merge([
            'enable_continuous_learning' => true,
            'alert_on_moderate_deviation' => false,
            'alert_on_major_deviation' => true,
            'store_daily_comparisons' => true,
            'generate_weekly_reports' => true,
            'auto_recalibrate' => true
        ], $config);
    }

    /**
     * Build or update digital twin for staff member
     *
     * @param int $staffId Staff member ID
     * @param bool $forceRebuild Force complete rebuild
     * @return array Digital twin profile
     */
    public function buildDigitalTwin(int $staffId, bool $forceRebuild = false): array
    {
        $startTime = microtime(true);

        // Check if twin exists and is current
        if (!$forceRebuild) {
            $existingTwin = $this->getExistingTwin($staffId);

            if ($existingTwin && $this->isTwinCurrent($existingTwin)) {
                return [
                    'success' => true,
                    'staff_id' => $staffId,
                    'action' => 'existing_twin_current',
                    'twin' => $existingTwin,
                    'next_recalibration' => $existingTwin['next_recalibration']
                ];
            }
        }

        // Verify sufficient data exists
        $dataAvailability = $this->checkDataAvailability($staffId);

        if ($dataAvailability['days_of_data'] < self::MINIMUM_LEARNING_DAYS) {
            return [
                'success' => false,
                'error' => 'Insufficient data for digital twin',
                'staff_id' => $staffId,
                'days_available' => $dataAvailability['days_of_data'],
                'days_required' => self::MINIMUM_LEARNING_DAYS
            ];
        }

        // Build behavioral profiles for each dimension
        $profiles = [];

        foreach (self::BEHAVIORAL_DIMENSIONS as $dimension => $config) {
            $profiles[$dimension] = $this->buildDimensionProfile($staffId, $dimension, $config['metrics']);
        }

        // Calculate composite behavioral signature
        $behavioralSignature = $this->calculateBehavioralSignature($profiles);

        // Store digital twin
        $twinId = $this->storeDigitalTwin($staffId, $profiles, $behavioralSignature);

        $processingTime = microtime(true) - $startTime;

        return [
            'success' => true,
            'staff_id' => $staffId,
            'twin_id' => $twinId,
            'action' => $forceRebuild ? 'rebuilt' : 'created',
            'learning_period_days' => min($dataAvailability['days_of_data'], self::BASELINE_LEARNING_PERIOD),
            'behavioral_dimensions' => count($profiles),
            'behavioral_signature' => $behavioralSignature,
            'profiles' => $profiles,
            'data_quality_score' => $dataAvailability['quality_score'],
            'processing_time_seconds' => round($processingTime, 2),
            'created_at' => date('Y-m-d H:i:s'),
            'next_recalibration' => date('Y-m-d', strtotime('+' . self::RECALIBRATION_INTERVAL . ' days'))
        ];
    }

    /**
     * Compare staff member's current behavior to their digital twin
     *
     * @param int $staffId Staff member ID
     * @param array $options Comparison options
     * @return array Deviation analysis
     */
    public function compareToDigitalTwin(int $staffId, array $options = []): array
    {
        $defaults = [
            'period' => 'today',           // today, this_week, this_month
            'include_recommendations' => true,
            'detailed_breakdown' => true
        ];
        $options = array_merge($defaults, $options);

        // Get digital twin
        $twin = $this->getExistingTwin($staffId);

        if (!$twin) {
            return [
                'success' => false,
                'error' => 'No digital twin exists for this staff member',
                'staff_id' => $staffId,
                'recommendation' => 'Build digital twin first'
            ];
        }

        // Get current behavior for comparison period
        $currentBehavior = $this->getCurrentBehavior($staffId, $options['period']);

        // Compare each dimension
        $dimensionDeviations = [];
        $totalDeviation = 0.0;

        foreach (self::BEHAVIORAL_DIMENSIONS as $dimension => $config) {
            $twinProfile = $twin['profiles'][$dimension] ?? [];
            $currentProfile = $currentBehavior[$dimension] ?? [];

            $deviation = $this->calculateDimensionDeviation(
                $twinProfile,
                $currentProfile,
                $config['metrics']
            );

            $weightedDeviation = $deviation['deviation_score'] * $config['weight'];
            $totalDeviation += $weightedDeviation;

            $dimensionDeviations[$dimension] = [
                'deviation_score' => $deviation['deviation_score'],
                'weighted_contribution' => $weightedDeviation,
                'severity' => $deviation['severity'],
                'anomalous_metrics' => $deviation['anomalous_metrics'],
                'twin_values' => $twinProfile,
                'current_values' => $currentProfile
            ];
        }

        // Determine overall deviation level
        $deviationLevel = $this->determineDeviationLevel($totalDeviation);

        // Generate alert if needed
        $alert = null;
        if ($this->shouldAlert($deviationLevel)) {
            $alert = $this->generateDeviationAlert($staffId, $totalDeviation, $deviationLevel, $dimensionDeviations);
        }

        // Generate recommendations
        $recommendations = [];
        if ($options['include_recommendations']) {
            $recommendations = $this->generateRecommendations($deviationLevel, $dimensionDeviations);
        }

        // Store comparison
        $this->storeComparison($staffId, $twin['twin_id'], $totalDeviation, $dimensionDeviations);

        return [
            'success' => true,
            'staff_id' => $staffId,
            'twin_id' => $twin['twin_id'],
            'comparison_period' => $options['period'],
            'total_deviation_score' => round($totalDeviation, 3),
            'deviation_percentage' => round($totalDeviation * 100, 1),
            'deviation_level' => $deviationLevel,
            'dimension_deviations' => $dimensionDeviations,
            'top_deviating_dimensions' => $this->getTopDeviations($dimensionDeviations, 3),
            'alert_generated' => $alert !== null,
            'alert_details' => $alert,
            'recommendations' => $recommendations,
            'compared_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Real-time deviation monitoring for all active staff
     *
     * @return array Real-time monitoring results
     */
    public function monitorAllActiveStaff(): array
    {
        // Get all currently clocked-in staff
        $sql = "
            SELECT staff_id, staff_name
            FROM staff
            WHERE currently_clocked_in = 1
                AND active = 1
        ";

        $stmt = $this->db->query($sql);
        $activeStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $monitoringResults = [];
        $alertCount = 0;
        $criticalAlertCount = 0;

        foreach ($activeStaff as $staff) {
            $comparison = $this->compareToDigitalTwin($staff['staff_id'], [
                'period' => 'today',
                'include_recommendations' => false,
                'detailed_breakdown' => false
            ]);

            if ($comparison['success']) {
                $monitoringResults[] = [
                    'staff_id' => $staff['staff_id'],
                    'staff_name' => $staff['staff_name'],
                    'deviation_score' => $comparison['total_deviation_score'],
                    'deviation_level' => $comparison['deviation_level'],
                    'alert_generated' => $comparison['alert_generated']
                ];

                if ($comparison['alert_generated']) {
                    $alertCount++;
                    if ($comparison['deviation_level'] === 'CRITICAL') {
                        $criticalAlertCount++;
                    }
                }
            }
        }

        // Sort by deviation score (highest first)
        usort($monitoringResults, function($a, $b) {
            return $b['deviation_score'] <=> $a['deviation_score'];
        });

        return [
            'success' => true,
            'active_staff_count' => count($activeStaff),
            'staff_monitored' => count($monitoringResults),
            'alerts_generated' => $alertCount,
            'critical_alerts' => $criticalAlertCount,
            'monitoring_results' => $monitoringResults,
            'highest_deviation' => $monitoringResults[0] ?? null,
            'monitored_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Build all digital twins (batch processing)
     *
     * @param array $options Processing options
     * @return array Batch processing results
     */
    public function buildAllDigitalTwins(array $options = []): array
    {
        $defaults = [
            'force_rebuild' => false,
            'min_data_quality' => 0.70
        ];
        $options = array_merge($defaults, $options);

        $startTime = microtime(true);

        // Get all staff members
        $sql = "SELECT staff_id, staff_name FROM staff WHERE active = 1";
        $stmt = $this->db->query($sql);
        $allStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [
            'successful' => [],
            'failed' => [],
            'skipped' => []
        ];

        foreach ($allStaff as $staff) {
            $result = $this->buildDigitalTwin($staff['staff_id'], $options['force_rebuild']);

            if ($result['success']) {
                if ($result['action'] === 'existing_twin_current') {
                    $results['skipped'][] = [
                        'staff_id' => $staff['staff_id'],
                        'staff_name' => $staff['staff_name'],
                        'reason' => 'Twin already current'
                    ];
                } else {
                    $results['successful'][] = [
                        'staff_id' => $staff['staff_id'],
                        'staff_name' => $staff['staff_name'],
                        'twin_id' => $result['twin_id'],
                        'data_quality' => $result['data_quality_score']
                    ];
                }
            } else {
                $results['failed'][] = [
                    'staff_id' => $staff['staff_id'],
                    'staff_name' => $staff['staff_name'],
                    'error' => $result['error']
                ];
            }
        }

        $processingTime = microtime(true) - $startTime;

        return [
            'success' => true,
            'total_staff' => count($allStaff),
            'twins_created' => count($results['successful']),
            'twins_skipped' => count($results['skipped']),
            'failures' => count($results['failed']),
            'results' => $results,
            'processing_time_seconds' => round($processingTime, 2),
            'completed_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Build profile for specific behavioral dimension
     *
     * @param int $staffId
     * @param string $dimension
     * @param array $metrics
     * @return array Dimension profile
     */
    private function buildDimensionProfile(int $staffId, string $dimension, array $metrics): array
    {
        $profile = [];

        switch ($dimension) {
            case 'transaction_patterns':
                $profile = $this->buildTransactionProfile($staffId);
                break;

            case 'discount_behavior':
                $profile = $this->buildDiscountProfile($staffId);
                break;

            case 'physical_behavior':
                $profile = $this->buildPhysicalProfile($staffId);
                break;

            case 'communication_patterns':
                $profile = $this->buildCommunicationProfile($staffId);
                break;

            case 'work_schedule':
                $profile = $this->buildWorkScheduleProfile($staffId);
                break;

            case 'system_access':
                $profile = $this->buildSystemAccessProfile($staffId);
                break;

            case 'customer_interaction':
                $profile = $this->buildCustomerInteractionProfile($staffId);
                break;

            case 'inventory_handling':
                $profile = $this->buildInventoryProfile($staffId);
                break;
        }

        return $profile;
    }

    private function buildTransactionProfile(int $staffId): array
    {
        $sql = "
            SELECT
                AVG(total_amount) as avg_transaction_value,
                COUNT(*) / :days as transactions_per_day,
                HOUR(transaction_date) as hour,
                DAYNAME(transaction_date) as day_name,
                COUNT(*) as count
            FROM lightspeed_transactions
            WHERE staff_id = :staff_id
                AND transaction_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY HOUR(transaction_date), DAYNAME(transaction_date)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'staff_id' => $staffId,
            'days' => self::BASELINE_LEARNING_PERIOD
        ]);

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate peak hours
        $hourlyDistribution = [];
        foreach ($data as $row) {
            $hour = $row['hour'];
            if (!isset($hourlyDistribution[$hour])) {
                $hourlyDistribution[$hour] = 0;
            }
            $hourlyDistribution[$hour] += $row['count'];
        }

        arsort($hourlyDistribution);
        $peakHours = array_slice(array_keys($hourlyDistribution), 0, 3);

        return [
            'avg_transaction_value' => round($data[0]['avg_transaction_value'] ?? 0, 2),
            'transactions_per_day' => round($data[0]['transactions_per_day'] ?? 0, 2),
            'peak_hours' => $peakHours,
            'hourly_distribution' => $hourlyDistribution
        ];
    }

    private function buildDiscountProfile(int $staffId): array
    {
        $sql = "
            SELECT
                COUNT(*) as total_transactions,
                SUM(CASE WHEN discount_amount > 0 THEN 1 ELSE 0 END) as discounted_transactions,
                AVG(discount_amount) as avg_discount,
                MAX(discount_amount) as max_discount,
                STDDEV(discount_amount) as discount_stddev
            FROM lightspeed_transactions
            WHERE staff_id = :staff_id
                AND transaction_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'staff_id' => $staffId,
            'days' => self::BASELINE_LEARNING_PERIOD
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $discountFrequency = $data['total_transactions'] > 0
            ? $data['discounted_transactions'] / $data['total_transactions']
            : 0.0;

        return [
            'discount_frequency' => round($discountFrequency, 3),
            'avg_discount_amount' => round($data['avg_discount'] ?? 0, 2),
            'max_discount_amount' => round($data['max_discount'] ?? 0, 2),
            'discount_stddev' => round($data['discount_stddev'] ?? 0, 2)
        ];
    }

    private function buildPhysicalProfile(int $staffId): array
    {
        $sql = "
            SELECT
                AVG(stress_level) as avg_stress,
                AVG(movement_frequency) as avg_movement,
                SUM(camera_awareness_events) as camera_checks,
                COUNT(*) as observation_count
            FROM cv_behavioral_detections
            WHERE staff_id = :staff_id
                AND detection_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'staff_id' => $staffId,
            'days' => self::BASELINE_LEARNING_PERIOD
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'avg_stress_level' => round($data['avg_stress'] ?? 0, 2),
            'avg_movement_frequency' => round($data['avg_movement'] ?? 0, 2),
            'camera_checks_per_day' => round(($data['camera_checks'] ?? 0) / self::BASELINE_LEARNING_PERIOD, 2)
        ];
    }

    private function buildCommunicationProfile(int $staffId): array
    {
        $sql = "
            SELECT
                COUNT(*) as total_messages,
                AVG(sentiment_score) as avg_sentiment,
                COUNT(DISTINCT recipient_id) as unique_recipients,
                SUM(CASE WHEN code_words_detected > 0 THEN 1 ELSE 0 END) as code_word_messages
            FROM communication_analysis
            WHERE staff_id = :staff_id
                AND message_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'staff_id' => $staffId,
            'days' => self::BASELINE_LEARNING_PERIOD
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'messages_per_day' => round(($data['total_messages'] ?? 0) / self::BASELINE_LEARNING_PERIOD, 2),
            'avg_sentiment' => round($data['avg_sentiment'] ?? 0, 2),
            'unique_recipients' => $data['unique_recipients'] ?? 0,
            'code_word_frequency' => round(($data['code_word_messages'] ?? 0) / max(1, $data['total_messages']), 3)
        ];
    }

    private function buildWorkScheduleProfile(int $staffId): array
    {
        $sql = "
            SELECT
                AVG(TIMESTAMPDIFF(MINUTE, scheduled_start, actual_start)) as avg_punctuality_minutes,
                AVG(break_duration_minutes) as avg_break_duration,
                SUM(CASE WHEN overtime_minutes > 0 THEN 1 ELSE 0 END) as overtime_days,
                COUNT(*) as total_shifts
            FROM staff_timesheet
            WHERE staff_id = :staff_id
                AND shift_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'staff_id' => $staffId,
            'days' => self::BASELINE_LEARNING_PERIOD
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'avg_punctuality_minutes' => round($data['avg_punctuality_minutes'] ?? 0, 1),
            'avg_break_duration' => round($data['avg_break_duration'] ?? 0, 1),
            'overtime_frequency' => round(($data['overtime_days'] ?? 0) / max(1, $data['total_shifts']), 3)
        ];
    }

    private function buildSystemAccessProfile(int $staffId): array
    {
        $sql = "
            SELECT
                screen_name,
                COUNT(*) as access_count,
                AVG(time_spent_seconds) as avg_time
            FROM system_access_log
            WHERE staff_id = :staff_id
                AND access_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY screen_name
            ORDER BY access_count DESC
            LIMIT 10
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'staff_id' => $staffId,
            'days' => self::BASELINE_LEARNING_PERIOD
        ]);

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $topScreens = [];
        foreach ($data as $row) {
            $topScreens[$row['screen_name']] = [
                'access_count' => $row['access_count'],
                'avg_time_seconds' => round($row['avg_time'], 1)
            ];
        }

        return [
            'top_screens' => $topScreens,
            'screen_diversity' => count($data)
        ];
    }

    private function buildCustomerInteractionProfile(int $staffId): array
    {
        $sql = "
            SELECT
                AVG(service_time_seconds) as avg_service_time,
                AVG(customer_satisfaction_score) as avg_satisfaction,
                SUM(complaint_count) as total_complaints,
                COUNT(*) as total_interactions
            FROM customer_interactions
            WHERE staff_id = :staff_id
                AND interaction_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'staff_id' => $staffId,
            'days' => self::BASELINE_LEARNING_PERIOD
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'avg_service_time_seconds' => round($data['avg_service_time'] ?? 0, 1),
            'avg_satisfaction_score' => round($data['avg_satisfaction'] ?? 0, 2),
            'complaint_rate' => round(($data['total_complaints'] ?? 0) / max(1, $data['total_interactions']), 3)
        ];
    }

    private function buildInventoryProfile(int $staffId): array
    {
        $sql = "
            SELECT
                AVG(processing_time_seconds) as avg_processing_time,
                SUM(accurate_count) / COUNT(*) as accuracy_rate,
                SUM(discrepancy_count) as total_discrepancies,
                COUNT(*) as total_processed
            FROM inventory_processing_log
            WHERE staff_id = :staff_id
                AND process_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'staff_id' => $staffId,
            'days' => self::BASELINE_LEARNING_PERIOD
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'avg_processing_time_seconds' => round($data['avg_processing_time'] ?? 0, 1),
            'accuracy_rate' => round($data['accuracy_rate'] ?? 0, 3),
            'discrepancy_rate' => round(($data['total_discrepancies'] ?? 0) / max(1, $data['total_processed']), 3)
        ];
    }

    private function calculateBehavioralSignature(array $profiles): string
    {
        // Create unique hash of all behavioral profiles
        $signatureData = json_encode($profiles);
        return hash('sha256', $signatureData);
    }

    private function getCurrentBehavior(int $staffId, string $period): array
    {
        // Similar to buildDimensionProfile but for current period only
        $days = match($period) {
            'today' => 1,
            'this_week' => 7,
            'this_month' => 30,
            default => 1
        };

        $behavior = [];

        foreach (self::BEHAVIORAL_DIMENSIONS as $dimension => $config) {
            $behavior[$dimension] = $this->buildDimensionProfile($staffId, $dimension, $config['metrics']);
        }

        return $behavior;
    }

    private function calculateDimensionDeviation(array $twinProfile, array $currentProfile, array $metrics): array
    {
        $deviations = [];
        $totalDeviation = 0.0;
        $metricCount = 0;

        foreach ($metrics as $metric) {
            if (isset($twinProfile[$metric]) && isset($currentProfile[$metric])) {
                $twinValue = $twinProfile[$metric];
                $currentValue = $currentProfile[$metric];

                // Calculate percentage deviation
                $deviation = $twinValue != 0
                    ? abs($currentValue - $twinValue) / abs($twinValue)
                    : ($currentValue != 0 ? 1.0 : 0.0);

                $deviations[$metric] = [
                    'twin_value' => $twinValue,
                    'current_value' => $currentValue,
                    'deviation' => round($deviation, 3)
                ];

                $totalDeviation += $deviation;
                $metricCount++;
            }
        }

        $avgDeviation = $metricCount > 0 ? $totalDeviation / $metricCount : 0.0;

        return [
            'deviation_score' => round($avgDeviation, 3),
            'severity' => $this->determineDeviationLevel($avgDeviation),
            'anomalous_metrics' => array_filter($deviations, fn($d) => $d['deviation'] >= self::MODERATE_DEVIATION),
            'metric_deviations' => $deviations
        ];
    }

    private function determineDeviationLevel(float $deviation): string
    {
        if ($deviation >= self::CRITICAL_DEVIATION) return 'CRITICAL';
        if ($deviation >= self::MAJOR_DEVIATION) return 'MAJOR';
        if ($deviation >= self::MODERATE_DEVIATION) return 'MODERATE';
        if ($deviation >= self::MINOR_DEVIATION) return 'MINOR';
        return 'NORMAL';
    }

    private function shouldAlert(string $level): bool
    {
        return match($level) {
            'CRITICAL' => true,
            'MAJOR' => $this->config['alert_on_major_deviation'],
            'MODERATE' => $this->config['alert_on_moderate_deviation'],
            default => false
        };
    }

    private function generateDeviationAlert(int $staffId, float $deviation, string $level, array $dimensions): array
    {
        $topDeviations = $this->getTopDeviations($dimensions, 3);

        return [
            'alert_id' => uniqid('shadow_'),
            'alert_type' => 'DIGITAL_TWIN_DEVIATION',
            'severity' => $level,
            'staff_id' => $staffId,
            'deviation_score' => $deviation,
            'deviation_percentage' => round($deviation * 100, 1),
            'message' => "Staff behavior deviates {$this->formatPercentage($deviation)} from their Digital Twin",
            'top_deviating_dimensions' => $topDeviations,
            'recommended_action' => $this->getRecommendedAction($level),
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    private function generateRecommendations(string $level, array $dimensions): array
    {
        $recommendations = [];

        if ($level === 'CRITICAL' || $level === 'MAJOR') {
            $recommendations[] = "Immediate supervisor review recommended";
            $recommendations[] = "Review recent transactions for anomalies";
            $recommendations[] = "Check for external stressors or personal issues";
        }

        // Dimension-specific recommendations
        foreach ($dimensions as $dimension => $data) {
            if ($data['severity'] === 'MAJOR' || $data['severity'] === 'CRITICAL') {
                $recommendations[] = $this->getDimensionRecommendation($dimension);
            }
        }

        return array_unique($recommendations);
    }

    private function getDimensionRecommendation(string $dimension): string
    {
        return match($dimension) {
            'transaction_patterns' => "Review recent sales transactions",
            'discount_behavior' => "Audit discount usage",
            'physical_behavior' => "Monitor physical stress indicators",
            'communication_patterns' => "Review recent communications",
            'work_schedule' => "Check attendance and punctuality",
            'system_access' => "Audit system access logs",
            'customer_interaction' => "Review customer feedback",
            'inventory_handling' => "Check inventory accuracy",
            default => "Investigate $dimension anomalies"
        };
    }

    private function getTopDeviations(array $dimensions, int $limit): array
    {
        $sorted = $dimensions;
        uasort($sorted, fn($a, $b) => $b['weighted_contribution'] <=> $a['weighted_contribution']);

        return array_slice($sorted, 0, $limit, true);
    }

    private function formatPercentage(float $value): string
    {
        return round($value * 100, 0) . '%';
    }

    private function getRecommendedAction(string $level): string
    {
        return match($level) {
            'CRITICAL' => 'Immediate investigation and supervisor notification',
            'MAJOR' => 'Review behavior and schedule conversation',
            'MODERATE' => 'Monitor closely over next few days',
            default => 'Continue normal monitoring'
        };
    }

    // Database operations

    private function getExistingTwin(int $staffId): ?array
    {
        $sql = "
            SELECT *
            FROM shadow_staff_profiles
            WHERE staff_id = :staff_id
            ORDER BY created_at DESC
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['staff_id' => $staffId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $result['profiles'] = json_decode($result['behavioral_profiles'], true);
            return $result;
        }

        return null;
    }

    private function isTwinCurrent(array $twin): bool
    {
        $lastUpdate = new \DateTime($twin['created_at']);
        $now = new \DateTime();
        $daysSinceUpdate = $now->diff($lastUpdate)->days;

        return $daysSinceUpdate < self::RECALIBRATION_INTERVAL;
    }

    private function checkDataAvailability(int $staffId): array
    {
        $sql = "
            SELECT
                MIN(transaction_date) as earliest_data,
                COUNT(*) as transaction_count
            FROM lightspeed_transactions
            WHERE staff_id = :staff_id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['staff_id' => $staffId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $daysOfData = 0;
        if ($data['earliest_data']) {
            $earliest = new \DateTime($data['earliest_data']);
            $now = new \DateTime();
            $daysOfData = $now->diff($earliest)->days;
        }

        $qualityScore = min(1.0, $daysOfData / self::BASELINE_LEARNING_PERIOD);

        return [
            'days_of_data' => $daysOfData,
            'transaction_count' => $data['transaction_count'],
            'quality_score' => round($qualityScore, 2)
        ];
    }

    private function storeDigitalTwin(int $staffId, array $profiles, string $signature): int
    {
        $sql = "
            INSERT INTO shadow_staff_profiles (
                staff_id,
                behavioral_profiles,
                behavioral_signature,
                learning_period_days,
                created_at,
                next_recalibration
            ) VALUES (
                :staff_id,
                :profiles,
                :signature,
                :learning_days,
                NOW(),
                DATE_ADD(NOW(), INTERVAL :recal_days DAY)
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'staff_id' => $staffId,
            'profiles' => json_encode($profiles),
            'signature' => $signature,
            'learning_days' => self::BASELINE_LEARNING_PERIOD,
            'recal_days' => self::RECALIBRATION_INTERVAL
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function storeComparison(int $staffId, int $twinId, float $deviation, array $dimensions): void
    {
        $sql = "
            INSERT INTO shadow_staff_comparisons (
                staff_id,
                twin_id,
                deviation_score,
                deviation_level,
                dimension_deviations,
                compared_at,
                created_at
            ) VALUES (
                :staff_id,
                :twin_id,
                :deviation,
                :level,
                :dimensions,
                NOW(),
                NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'staff_id' => $staffId,
            'twin_id' => $twinId,
            'deviation' => $deviation,
            'level' => $this->determineDeviationLevel($deviation),
            'dimensions' => json_encode($dimensions)
        ]);
    }
}
