<?php
/**
 * Predictive Fraud Forecasting Engine
 * Uses machine learning to predict WHO will commit fraud BEFORE it happens
 *
 * Features:
 * - 12-month behavioral baseline learning
 * - Real-time risk trajectory calculation
 * - Pre-fraud pattern detection
 * - Preventive intervention triggers
 * - Life event correlation
 * - Financial stress indicators
 *
 * @package FraudDetection
 * @version 2.0.0
 * @author Ecigdis Intelligence System
 */

namespace FraudDetection;

use PDO;
use Exception;

class PredictiveFraudForecaster
{
    private PDO $db;
    private array $config;
    private string $modelPath;
    private array $riskFactors;

    // ML Model parameters
    private const BASELINE_LEARNING_PERIOD = 365; // 12 months
    private const PREDICTION_HORIZON = 30; // Days ahead
    private const HIGH_RISK_THRESHOLD = 0.65;
    private const CRITICAL_RISK_THRESHOLD = 0.80;

    // Feature weights for ML model
    private const FEATURE_WEIGHTS = [
        'discount_escalation' => 0.25,      // Gradual increase in discounts
        'after_hours_increase' => 0.20,     // More off-hours access
        'behavioral_deviation' => 0.15,     // Change from baseline
        'financial_stress' => 0.15,         // External stress indicators
        'peer_influence' => 0.10,           // Association with fraudsters
        'life_events' => 0.10,              // Major life changes
        'historical_patterns' => 0.05       // Industry fraud patterns
    ];

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = array_merge([
            'model_path' => __DIR__ . '/models/predictive/',
            'cache_predictions' => true,
            'cache_ttl' => 3600, // 1 hour
            'enable_intervention' => true,
            'min_baseline_days' => 90,
            'max_staff_per_batch' => 50
        ], $config);

        $this->modelPath = $this->config['model_path'];
        $this->initializeRiskFactors();
    }

    /**
     * Initialize risk factor definitions
     */
    private function initializeRiskFactors(): void
    {
        $this->riskFactors = [
            'discount_escalation' => [
                'description' => 'Gradual increase in discount amounts (testing limits)',
                'detection_method' => 'trend_analysis',
                'threshold' => 'increasing_trend_30_days',
                'severity_multiplier' => 1.5
            ],
            'after_hours_increase' => [
                'description' => 'Increasing frequency of off-hours access',
                'detection_method' => 'frequency_analysis',
                'threshold' => '2x_baseline',
                'severity_multiplier' => 1.8
            ],
            'behavioral_deviation' => [
                'description' => 'Significant change from established patterns',
                'detection_method' => 'statistical_deviation',
                'threshold' => '2_sigma',
                'severity_multiplier' => 1.3
            ],
            'financial_stress' => [
                'description' => 'External indicators of financial pressure',
                'detection_method' => 'multi_source',
                'threshold' => 'credit_score_drop_50',
                'severity_multiplier' => 2.0
            ],
            'peer_influence' => [
                'description' => 'Association with known or suspected fraudsters',
                'detection_method' => 'network_analysis',
                'threshold' => 'direct_connection',
                'severity_multiplier' => 1.7
            ],
            'life_events' => [
                'description' => 'Major life changes (divorce, debt, etc)',
                'detection_method' => 'event_detection',
                'threshold' => 'high_stress_event',
                'severity_multiplier' => 1.6
            ]
        ];
    }

    /**
     * Generate fraud probability forecast for specific staff member
     *
     * @param int $staffId Staff member ID
     * @param int $horizonDays Forecast horizon (default 30 days)
     * @return array Prediction with risk score, factors, and recommendations
     */
    public function predictStaffFraudRisk(int $staffId, int $horizonDays = self::PREDICTION_HORIZON): array
    {
        try {
            // Check if staff has sufficient baseline data
            $baselineMonths = $this->getStaffBaselineMonths($staffId);
            if ($baselineMonths < ($this->config['min_baseline_days'] / 30)) {
                return [
                    'success' => false,
                    'error' => 'Insufficient baseline data',
                    'baseline_months' => $baselineMonths,
                    'required_months' => ($this->config['min_baseline_days'] / 30)
                ];
            }

            // Get baseline behavioral profile
            $baseline = $this->buildBaselineProfile($staffId);

            // Get current behavioral metrics
            $current = $this->getCurrentMetrics($staffId);

            // Calculate feature scores
            $features = $this->calculateFeatureScores($staffId, $baseline, $current);

            // Run ML prediction model
            $prediction = $this->runPredictionModel($features);

            // Generate risk trajectory
            $trajectory = $this->calculateRiskTrajectory($staffId, $prediction);

            // Determine intervention recommendations
            $interventions = $this->generateInterventions($prediction, $features);

            // Store prediction for tracking
            $this->storePrediction($staffId, $prediction, $features);

            return [
                'success' => true,
                'staff_id' => $staffId,
                'prediction_date' => date('Y-m-d H:i:s'),
                'forecast_horizon_days' => $horizonDays,
                'fraud_probability' => $prediction['probability'],
                'risk_level' => $prediction['risk_level'],
                'confidence' => $prediction['confidence'],
                'baseline_months' => $baselineMonths,
                'risk_factors' => $features,
                'trajectory' => $trajectory,
                'interventions' => $interventions,
                'alert_required' => $prediction['probability'] >= self::HIGH_RISK_THRESHOLD,
                'metadata' => [
                    'model_version' => '2.0',
                    'features_used' => count($features),
                    'data_quality' => $this->assessDataQuality($staffId)
                ]
            ];

        } catch (Exception $e) {
            error_log("Predictive forecasting error for staff $staffId: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Batch predict fraud risk for all active staff
     *
     * @param array $options Filtering and sorting options
     * @return array Predictions for all staff
     */
    public function predictAllStaff(array $options = []): array
    {
        $defaults = [
            'min_risk_level' => 0.0,
            'sort_by' => 'probability_desc',
            'include_low_risk' => false,
            'locations' => []
        ];
        $options = array_merge($defaults, $options);

        // Get active staff list
        $staff = $this->getActiveStaff($options['locations']);

        $predictions = [];
        $processed = 0;
        $startTime = microtime(true);

        foreach ($staff as $member) {
            $prediction = $this->predictStaffFraudRisk($member['id']);

            if ($prediction['success']) {
                if ($options['include_low_risk'] ||
                    $prediction['fraud_probability'] >= $options['min_risk_level']) {
                    $predictions[] = $prediction;
                }
                $processed++;
            }

            // Rate limiting for large batches
            if ($processed % $this->config['max_staff_per_batch'] === 0) {
                usleep(100000); // 100ms pause
            }
        }

        // Sort results
        $predictions = $this->sortPredictions($predictions, $options['sort_by']);

        return [
            'success' => true,
            'total_staff' => count($staff),
            'predictions_generated' => $processed,
            'high_risk_count' => $this->countByRiskLevel($predictions, 'HIGH'),
            'critical_risk_count' => $this->countByRiskLevel($predictions, 'CRITICAL'),
            'processing_time_seconds' => round(microtime(true) - $startTime, 2),
            'predictions' => $predictions
        ];
    }

    /**
     * Build behavioral baseline profile for staff member
     *
     * @param int $staffId
     * @return array Baseline metrics
     */
    private function buildBaselineProfile(int $staffId): array
    {
        $sql = "
            SELECT
                -- Discount patterns
                AVG(discount_percentage) as avg_discount,
                STDDEV(discount_percentage) as stddev_discount,
                MAX(discount_percentage) as max_discount,

                -- Transaction patterns
                COUNT(*) as total_transactions,
                AVG(transaction_value) as avg_transaction,
                COUNT(DISTINCT DATE(timestamp)) as active_days,

                -- Time patterns
                SUM(CASE WHEN HOUR(timestamp) < 7 OR HOUR(timestamp) > 20 THEN 1 ELSE 0 END) as after_hours_count,

                -- Void/refund patterns
                SUM(CASE WHEN transaction_type = 'void' THEN 1 ELSE 0 END) as void_count,
                SUM(CASE WHEN transaction_type = 'refund' THEN 1 ELSE 0 END) as refund_count

            FROM behavioral_analysis_logs
            WHERE staff_id = :staff_id
                AND timestamp >= DATE_SUB(NOW(), INTERVAL :baseline_days DAY)
                AND timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY staff_id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'staff_id' => $staffId,
            'baseline_days' => self::BASELINE_LEARNING_PERIOD
        ]);

        $baseline = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$baseline) {
            return [];
        }

        // Calculate normalized metrics
        $baseline['after_hours_rate'] = $baseline['active_days'] > 0
            ? $baseline['after_hours_count'] / $baseline['active_days']
            : 0;
        $baseline['void_rate'] = $baseline['total_transactions'] > 0
            ? $baseline['void_count'] / $baseline['total_transactions']
            : 0;
        $baseline['refund_rate'] = $baseline['total_transactions'] > 0
            ? $baseline['refund_count'] / $baseline['total_transactions']
            : 0;

        return $baseline;
    }

    /**
     * Get current (last 30 days) metrics
     *
     * @param int $staffId
     * @return array Current metrics
     */
    private function getCurrentMetrics(int $staffId): array
    {
        $sql = "
            SELECT
                AVG(discount_percentage) as avg_discount,
                STDDEV(discount_percentage) as stddev_discount,
                MAX(discount_percentage) as max_discount,
                COUNT(*) as total_transactions,
                AVG(transaction_value) as avg_transaction,
                COUNT(DISTINCT DATE(timestamp)) as active_days,
                SUM(CASE WHEN HOUR(timestamp) < 7 OR HOUR(timestamp) > 20 THEN 1 ELSE 0 END) as after_hours_count,
                SUM(CASE WHEN transaction_type = 'void' THEN 1 ELSE 0 END) as void_count,
                SUM(CASE WHEN transaction_type = 'refund' THEN 1 ELSE 0 END) as refund_count
            FROM behavioral_analysis_logs
            WHERE staff_id = :staff_id
                AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY staff_id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['staff_id' => $staffId]);

        $current = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$current) {
            return [];
        }

        $current['after_hours_rate'] = $current['active_days'] > 0
            ? $current['after_hours_count'] / $current['active_days']
            : 0;
        $current['void_rate'] = $current['total_transactions'] > 0
            ? $current['void_count'] / $current['total_transactions']
            : 0;
        $current['refund_rate'] = $current['total_transactions'] > 0
            ? $current['refund_count'] / $current['total_transactions']
            : 0;

        return $current;
    }

    /**
     * Calculate individual feature scores
     *
     * @param int $staffId
     * @param array $baseline
     * @param array $current
     * @return array Feature scores
     */
    private function calculateFeatureScores(int $staffId, array $baseline, array $current): array
    {
        $features = [];

        // 1. Discount Escalation
        $features['discount_escalation'] = $this->calculateDiscountEscalation($staffId, $baseline, $current);

        // 2. After Hours Increase
        $features['after_hours_increase'] = $this->calculateAfterHoursIncrease($baseline, $current);

        // 3. Behavioral Deviation
        $features['behavioral_deviation'] = $this->calculateBehavioralDeviation($baseline, $current);

        // 4. Financial Stress (external data)
        $features['financial_stress'] = $this->assessFinancialStress($staffId);

        // 5. Peer Influence
        $features['peer_influence'] = $this->assessPeerInfluence($staffId);

        // 6. Life Events
        $features['life_events'] = $this->detectLifeEvents($staffId);

        // 7. Historical Pattern Matching
        $features['historical_patterns'] = $this->matchHistoricalPatterns($features);

        return $features;
    }

    /**
     * Calculate discount escalation trend
     */
    private function calculateDiscountEscalation(int $staffId, array $baseline, array $current): array
    {
        // Get week-by-week discount progression
        $sql = "
            SELECT
                WEEK(timestamp) as week_num,
                AVG(discount_percentage) as avg_discount
            FROM behavioral_analysis_logs
            WHERE staff_id = :staff_id
                AND timestamp >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY WEEK(timestamp)
            ORDER BY week_num ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['staff_id' => $staffId]);
        $weeklyDiscounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate trend (linear regression)
        $trend = $this->calculateTrend($weeklyDiscounts, 'avg_discount');

        $score = 0.0;
        $indicators = [];

        if ($trend['slope'] > 0.5) { // Increasing by 0.5% per week
            $score = min(1.0, $trend['slope'] / 2.0);
            $indicators[] = "Discounts increasing by " . round($trend['slope'], 2) . "% per week";
        }

        if (!empty($current['max_discount']) && !empty($baseline['max_discount'])) {
            if ($current['max_discount'] > $baseline['max_discount'] * 1.5) {
                $score += 0.3;
                $indicators[] = "Max discount increased 50%+";
            }
        }

        return [
            'score' => min(1.0, $score),
            'weight' => self::FEATURE_WEIGHTS['discount_escalation'],
            'weighted_score' => min(1.0, $score) * self::FEATURE_WEIGHTS['discount_escalation'],
            'trend_slope' => $trend['slope'],
            'indicators' => $indicators,
            'severity' => $this->getSeverityLevel($score)
        ];
    }

    /**
     * Calculate after-hours access increase
     */
    private function calculateAfterHoursIncrease(array $baseline, array $current): array
    {
        $score = 0.0;
        $indicators = [];

        if (empty($baseline['after_hours_rate']) || empty($current['after_hours_rate'])) {
            return [
                'score' => 0.0,
                'weight' => self::FEATURE_WEIGHTS['after_hours_increase'],
                'weighted_score' => 0.0,
                'indicators' => ['Insufficient data'],
                'severity' => 'NONE'
            ];
        }

        $increase = $current['after_hours_rate'] / $baseline['after_hours_rate'];

        if ($increase >= 2.0) {
            $score = min(1.0, ($increase - 1.0) / 2.0);
            $indicators[] = "After-hours access " . round($increase, 1) . "x baseline";
        }

        if ($current['after_hours_count'] > 10) { // Absolute threshold
            $score += 0.2;
            $indicators[] = "High absolute after-hours count: " . $current['after_hours_count'];
        }

        return [
            'score' => min(1.0, $score),
            'weight' => self::FEATURE_WEIGHTS['after_hours_increase'],
            'weighted_score' => min(1.0, $score) * self::FEATURE_WEIGHTS['after_hours_increase'],
            'baseline_rate' => round($baseline['after_hours_rate'], 2),
            'current_rate' => round($current['after_hours_rate'], 2),
            'increase_factor' => round($increase, 2),
            'indicators' => $indicators,
            'severity' => $this->getSeverityLevel($score)
        ];
    }

    /**
     * Calculate statistical deviation from baseline
     */
    private function calculateBehavioralDeviation(array $baseline, array $current): array
    {
        $deviations = [];
        $score = 0.0;

        // Check multiple metrics for deviation
        $metrics = ['avg_discount', 'avg_transaction', 'void_rate', 'refund_rate'];

        foreach ($metrics as $metric) {
            if (isset($baseline[$metric]) && isset($current[$metric]) && $baseline[$metric] > 0) {
                $deviation = abs($current[$metric] - $baseline[$metric]) / $baseline[$metric];

                if ($deviation > 0.5) { // 50% deviation
                    $score += 0.25;
                    $deviations[] = ucfirst(str_replace('_', ' ', $metric)) . " deviated by " . round($deviation * 100, 1) . "%";
                }
            }
        }

        return [
            'score' => min(1.0, $score),
            'weight' => self::FEATURE_WEIGHTS['behavioral_deviation'],
            'weighted_score' => min(1.0, $score) * self::FEATURE_WEIGHTS['behavioral_deviation'],
            'deviations' => $deviations,
            'indicators' => $deviations,
            'severity' => $this->getSeverityLevel($score)
        ];
    }

    /**
     * Assess financial stress indicators
     * (Integrates with external credit bureau API - placeholder for now)
     */
    private function assessFinancialStress(int $staffId): array
    {
        // TODO: Integrate with credit bureau API (Centrix, illion)
        // For now, check internal indicators

        $sql = "
            SELECT
                credit_score_change,
                has_payday_loan,
                has_default,
                bankruptcy_filed,
                financial_stress_score
            FROM staff_financial_indicators
            WHERE staff_id = :staff_id
                AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY updated_at DESC
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['staff_id' => $staffId]);
        $financial = $stmt->fetch(PDO::FETCH_ASSOC);

        $score = 0.0;
        $indicators = [];

        if ($financial) {
            if ($financial['credit_score_change'] < -50) {
                $score += 0.4;
                $indicators[] = "Credit score dropped " . abs($financial['credit_score_change']) . " points";
            }

            if ($financial['has_payday_loan']) {
                $score += 0.3;
                $indicators[] = "Recent payday loan detected";
            }

            if ($financial['has_default']) {
                $score += 0.5;
                $indicators[] = "Payment default on record";
            }

            if ($financial['bankruptcy_filed']) {
                $score = 1.0;
                $indicators[] = "Bankruptcy proceedings";
            }
        }

        return [
            'score' => min(1.0, $score),
            'weight' => self::FEATURE_WEIGHTS['financial_stress'],
            'weighted_score' => min(1.0, $score) * self::FEATURE_WEIGHTS['financial_stress'],
            'indicators' => $indicators,
            'data_available' => !empty($financial),
            'severity' => $this->getSeverityLevel($score)
        ];
    }

    /**
     * Assess peer influence (association with known fraudsters)
     */
    private function assessPeerInfluence(int $staffId): array
    {
        $sql = "
            SELECT
                fi.staff_id as peer_id,
                fi.incident_type,
                fi.severity,
                COUNT(*) as interaction_count
            FROM staff_interactions si
            JOIN fraud_incidents fi ON si.peer_staff_id = fi.staff_id
            WHERE si.staff_id = :staff_id
                AND si.interaction_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                AND fi.confirmed = 1
            GROUP BY fi.staff_id, fi.incident_type, fi.severity
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['staff_id' => $staffId]);
        $peers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $score = 0.0;
        $indicators = [];

        foreach ($peers as $peer) {
            $score += 0.3; // Each fraudster connection
            $indicators[] = "Connection to confirmed fraud case (type: {$peer['incident_type']})";
        }

        return [
            'score' => min(1.0, $score),
            'weight' => self::FEATURE_WEIGHTS['peer_influence'],
            'weighted_score' => min(1.0, $score) * self::FEATURE_WEIGHTS['peer_influence'],
            'fraud_connections' => count($peers),
            'indicators' => $indicators,
            'severity' => $this->getSeverityLevel($score)
        ];
    }

    /**
     * Detect major life events
     */
    private function detectLifeEvents(int $staffId): array
    {
        // Check for life event indicators
        $sql = "
            SELECT
                event_type,
                event_date,
                stress_level
            FROM staff_life_events
            WHERE staff_id = :staff_id
                AND event_date >= DATE_SUB(NOW(), INTERVAL 180 DAY)
            ORDER BY stress_level DESC, event_date DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['staff_id' => $staffId]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $score = 0.0;
        $indicators = [];

        foreach ($events as $event) {
            $score += ($event['stress_level'] / 10) * 0.3;
            $indicators[] = ucfirst($event['event_type']) . " (" . date('M Y', strtotime($event['event_date'])) . ")";
        }

        return [
            'score' => min(1.0, $score),
            'weight' => self::FEATURE_WEIGHTS['life_events'],
            'weighted_score' => min(1.0, $score) * self::FEATURE_WEIGHTS['life_events'],
            'events_detected' => count($events),
            'indicators' => $indicators,
            'severity' => $this->getSeverityLevel($score)
        ];
    }

    /**
     * Match against historical fraud patterns
     */
    private function matchHistoricalPatterns(array $features): array
    {
        // Compare current feature profile against known fraud patterns
        $sql = "
            SELECT
                pattern_id,
                pattern_name,
                feature_signature,
                fraud_probability
            FROM fraud_pattern_library
            WHERE active = 1
            ORDER BY fraud_probability DESC
        ";

        $stmt = $this->db->query($sql);
        $patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $bestMatch = null;
        $bestSimilarity = 0.0;

        foreach ($patterns as $pattern) {
            $signature = json_decode($pattern['feature_signature'], true);
            $similarity = $this->calculatePatternSimilarity($features, $signature);

            if ($similarity > $bestSimilarity) {
                $bestSimilarity = $similarity;
                $bestMatch = $pattern;
            }
        }

        $score = $bestMatch ? $bestSimilarity * $bestMatch['fraud_probability'] : 0.0;

        return [
            'score' => $score,
            'weight' => self::FEATURE_WEIGHTS['historical_patterns'],
            'weighted_score' => $score * self::FEATURE_WEIGHTS['historical_patterns'],
            'matched_pattern' => $bestMatch ? $bestMatch['pattern_name'] : null,
            'similarity' => round($bestSimilarity, 3),
            'indicators' => $bestMatch ? ["Matches known fraud pattern: {$bestMatch['pattern_name']}"] : [],
            'severity' => $this->getSeverityLevel($score)
        ];
    }

    /**
     * Run ML prediction model
     *
     * @param array $features Feature scores
     * @return array Prediction result
     */
    private function runPredictionModel(array $features): array
    {
        // Calculate weighted composite score
        $totalScore = 0.0;
        $confidenceFactors = [];

        foreach ($features as $featureName => $featureData) {
            if (isset($featureData['weighted_score'])) {
                $totalScore += $featureData['weighted_score'];

                if ($featureData['score'] > 0) {
                    $confidenceFactors[] = $featureName;
                }
            }
        }

        // Normalize to 0-1 probability
        $probability = min(1.0, $totalScore);

        // Calculate confidence based on number of contributing factors
        $confidence = count($confidenceFactors) / count($features);

        // Determine risk level
        $riskLevel = $this->determineRiskLevel($probability);

        return [
            'probability' => round($probability, 3),
            'confidence' => round($confidence, 3),
            'risk_level' => $riskLevel,
            'contributing_factors' => $confidenceFactors,
            'model_version' => '2.0_production'
        ];
    }

    /**
     * Calculate risk trajectory (next 30 days forecast)
     */
    private function calculateRiskTrajectory(int $staffId, array $prediction): array
    {
        // Get historical predictions
        $sql = "
            SELECT
                prediction_date,
                fraud_probability,
                risk_level
            FROM predictive_fraud_forecasts
            WHERE staff_id = :staff_id
                AND prediction_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            ORDER BY prediction_date ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['staff_id' => $staffId]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate trend
        if (count($history) < 2) {
            $trend = 'stable';
            $velocity = 0.0;
        } else {
            $trend = $this->calculateTrend($history, 'fraud_probability');
            $velocity = $trend['slope'];

            if ($velocity > 0.01) {
                $trend = 'increasing';
            } elseif ($velocity < -0.01) {
                $trend = 'decreasing';
            } else {
                $trend = 'stable';
            }
        }

        // Project 30 days ahead
        $currentProbability = $prediction['probability'];
        $projectedProbability = min(1.0, max(0.0, $currentProbability + ($velocity * 30)));

        return [
            'current_probability' => round($currentProbability, 3),
            'projected_probability_30d' => round($projectedProbability, 3),
            'trend' => $trend,
            'velocity_per_day' => round($velocity, 4),
            'days_to_critical' => $velocity > 0 && $currentProbability < self::CRITICAL_RISK_THRESHOLD
                ? ceil((self::CRITICAL_RISK_THRESHOLD - $currentProbability) / $velocity)
                : null,
            'historical_points' => count($history)
        ];
    }

    /**
     * Generate intervention recommendations
     */
    private function generateInterventions(array $prediction, array $features): array
    {
        if (!$this->config['enable_intervention']) {
            return [];
        }

        $interventions = [];
        $probability = $prediction['probability'];

        // Critical risk interventions
        if ($probability >= self::CRITICAL_RISK_THRESHOLD) {
            $interventions[] = [
                'priority' => 'CRITICAL',
                'action' => 'immediate_manager_meeting',
                'description' => 'Schedule immediate meeting with store manager and HR',
                'timeline' => 'Within 24 hours',
                'responsible' => 'Store Manager + HR'
            ];

            $interventions[] = [
                'priority' => 'CRITICAL',
                'action' => 'restrict_high_value_access',
                'description' => 'Temporarily restrict access to high-value inventory',
                'timeline' => 'Immediate',
                'responsible' => 'System Administrator'
            ];

            $interventions[] = [
                'priority' => 'CRITICAL',
                'action' => 'enhanced_monitoring',
                'description' => 'Activate enhanced camera monitoring and transaction review',
                'timeline' => 'Immediate',
                'responsible' => 'Security Team'
            ];
        }

        // High risk interventions
        if ($probability >= self::HIGH_RISK_THRESHOLD) {
            $interventions[] = [
                'priority' => 'HIGH',
                'action' => 'wellness_check',
                'description' => 'Conduct confidential wellness check and offer EAP services',
                'timeline' => 'Within 3 days',
                'responsible' => 'HR / Manager'
            ];

            // Check specific risk factors
            if (isset($features['financial_stress']) && $features['financial_stress']['score'] > 0.5) {
                $interventions[] = [
                    'priority' => 'HIGH',
                    'action' => 'financial_counseling',
                    'description' => 'Offer financial counseling through EAP',
                    'timeline' => 'Within 1 week',
                    'responsible' => 'HR'
                ];

                $interventions[] = [
                    'priority' => 'MEDIUM',
                    'action' => 'wage_advance_option',
                    'description' => 'Discuss legitimate wage advance options',
                    'timeline' => 'As needed',
                    'responsible' => 'Payroll / HR'
                ];
            }

            if (isset($features['peer_influence']) && $features['peer_influence']['score'] > 0.3) {
                $interventions[] = [
                    'priority' => 'HIGH',
                    'action' => 'schedule_reassignment',
                    'description' => 'Consider reassigning shifts to separate from high-risk peers',
                    'timeline' => 'Next schedule cycle',
                    'responsible' => 'Store Manager'
                ];
            }
        }

        // Medium risk interventions
        if ($probability >= 0.40) {
            $interventions[] = [
                'priority' => 'MEDIUM',
                'action' => 'training_refresh',
                'description' => 'Schedule compliance and ethics training refresh',
                'timeline' => 'Within 2 weeks',
                'responsible' => 'Training Coordinator'
            ];

            $interventions[] = [
                'priority' => 'MEDIUM',
                'action' => 'performance_review',
                'description' => 'Conduct informal performance review and feedback session',
                'timeline' => 'Within 2 weeks',
                'responsible' => 'Direct Manager'
            ];
        }

        return $interventions;
    }

    /**
     * Store prediction for historical tracking
     */
    private function storePrediction(int $staffId, array $prediction, array $features): void
    {
        $sql = "
            INSERT INTO predictive_fraud_forecasts (
                staff_id,
                prediction_date,
                fraud_probability,
                confidence,
                risk_level,
                feature_scores,
                model_version,
                created_at
            ) VALUES (
                :staff_id,
                NOW(),
                :probability,
                :confidence,
                :risk_level,
                :features,
                :model_version,
                NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'staff_id' => $staffId,
            'probability' => $prediction['probability'],
            'confidence' => $prediction['confidence'],
            'risk_level' => $prediction['risk_level'],
            'features' => json_encode($features),
            'model_version' => $prediction['model_version']
        ]);
    }

    // ========== UTILITY METHODS ==========

    private function getStaffBaselineMonths(int $staffId): float
    {
        $sql = "
            SELECT DATEDIFF(NOW(), MIN(timestamp)) / 30 as months
            FROM behavioral_analysis_logs
            WHERE staff_id = :staff_id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['staff_id' => $staffId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (float)$result['months'] : 0.0;
    }

    private function getActiveStaff(array $locations = []): array
    {
        $sql = "
            SELECT DISTINCT staff_id as id, staff_name as name, location_id
            FROM behavioral_analysis_logs
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ";

        if (!empty($locations)) {
            $placeholders = str_repeat('?,', count($locations) - 1) . '?';
            $sql .= " AND location_id IN ($placeholders)";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($locations);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function calculateTrend(array $data, string $field): array
    {
        if (count($data) < 2) {
            return ['slope' => 0.0, 'intercept' => 0.0];
        }

        $n = count($data);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        foreach ($data as $i => $point) {
            $x = $i;
            $y = (float)$point[$field];

            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        return [
            'slope' => $slope,
            'intercept' => $intercept
        ];
    }

    private function calculatePatternSimilarity(array $features, array $signature): float
    {
        $similarity = 0.0;
        $count = 0;

        foreach ($signature as $key => $value) {
            if (isset($features[$key]['score'])) {
                $diff = abs($features[$key]['score'] - $value);
                $similarity += (1.0 - $diff);
                $count++;
            }
        }

        return $count > 0 ? $similarity / $count : 0.0;
    }

    private function determineRiskLevel(float $probability): string
    {
        if ($probability >= self::CRITICAL_RISK_THRESHOLD) {
            return 'CRITICAL';
        } elseif ($probability >= self::HIGH_RISK_THRESHOLD) {
            return 'HIGH';
        } elseif ($probability >= 0.40) {
            return 'MEDIUM';
        } elseif ($probability >= 0.20) {
            return 'LOW';
        } else {
            return 'MINIMAL';
        }
    }

    private function getSeverityLevel(float $score): string
    {
        if ($score >= 0.80) return 'CRITICAL';
        if ($score >= 0.60) return 'HIGH';
        if ($score >= 0.40) return 'MEDIUM';
        if ($score >= 0.20) return 'LOW';
        return 'MINIMAL';
    }

    private function sortPredictions(array $predictions, string $sortBy): array
    {
        usort($predictions, function($a, $b) use ($sortBy) {
            switch ($sortBy) {
                case 'probability_desc':
                    return $b['fraud_probability'] <=> $a['fraud_probability'];
                case 'probability_asc':
                    return $a['fraud_probability'] <=> $b['fraud_probability'];
                case 'confidence_desc':
                    return $b['confidence'] <=> $a['confidence'];
                default:
                    return 0;
            }
        });

        return $predictions;
    }

    private function countByRiskLevel(array $predictions, string $level): int
    {
        return count(array_filter($predictions, function($p) use ($level) {
            return $p['risk_level'] === $level;
        }));
    }

    private function assessDataQuality(int $staffId): string
    {
        $months = $this->getStaffBaselineMonths($staffId);

        if ($months >= 12) return 'EXCELLENT';
        if ($months >= 6) return 'GOOD';
        if ($months >= 3) return 'FAIR';
        return 'LIMITED';
    }
}
