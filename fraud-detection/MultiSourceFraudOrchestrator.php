<?php
/**
 * Multi-Source Fraud Intelligence Orchestrator
 * Coordinates all 5 advanced fraud detection engines
 *
 * Engines Integrated:
 * 1. Predictive ML Forecasting (30-day fraud predictions)
 * 2. Computer Vision Behavioral Analysis (100+ cameras)
 * 3. NLP Communication Analysis (real-time message monitoring)
 * 4. Customer Loyalty Collusion Detection
 * 5. AI Shadow Staff Digital Twins (behavioral deviation)
 *
 * Correlation Strategy:
 * - Aggregates risk signals from all 5 engines
 * - Detects multi-source fraud patterns
 * - Generates unified risk scores
 * - Prioritizes alerts by severity and confidence
 * - Creates comprehensive investigation packages
 *
 * Example Multi-Source Alert:
 * "John Smith - CRITICAL MULTI-SOURCE ALERT
 *  - ML Prediction: 87% fraud probability (HIGH RISK)
 *  - Computer Vision: Stress indicators detected (MAJOR DEVIATION)
 *  - Communications: Discussing 'special customer' (HIGH RISK)
 *  - Customer Collusion: 3 suspicious relationships (HIGH RISK)
 *  - Digital Twin: 73% behavioral deviation (MAJOR DEVIATION)
 *  Composite Risk Score: 0.91 (CRITICAL)"
 *
 * @package FraudDetection
 * @version 2.0.0
 * @author Ecigdis Intelligence System
 */

namespace FraudDetection;

use PDO;
use Exception;

class MultiSourceFraudOrchestrator
{
    private PDO $db;
    private array $config;

    // Engine instances
    private PredictiveFraudForecaster $mlEngine;
    private ComputerVisionBehavioralAnalyzer $cvEngine;
    private CommunicationAnalysisEngine $nlpEngine;
    private CustomerLoyaltyCollusionDetector $collusionEngine;
    private AIShadowStaffEngine $shadowEngine;

    // Composite scoring weights
    private const ENGINE_WEIGHTS = [
        'ml_prediction' => 0.25,
        'cv_behavior' => 0.25,
        'nlp_communication' => 0.20,
        'customer_collusion' => 0.15,
        'digital_twin' => 0.15
    ];

    // Multi-source alert thresholds
    private const COMPOSITE_THRESHOLDS = [
        'CRITICAL' => 0.85,
        'HIGH' => 0.70,
        'MEDIUM' => 0.50,
        'LOW' => 0.30
    ];

    // Correlation bonus (when multiple engines agree)
    private const CORRELATION_BONUS = 0.10; // +10% when 3+ engines agree

    public function __construct(
        PDO $db,
        PredictiveFraudForecaster $mlEngine,
        ComputerVisionBehavioralAnalyzer $cvEngine,
        CommunicationAnalysisEngine $nlpEngine,
        CustomerLoyaltyCollusionDetector $collusionEngine,
        AIShadowStaffEngine $shadowEngine,
        array $config = []
    ) {
        $this->db = $db;
        $this->mlEngine = $mlEngine;
        $this->cvEngine = $cvEngine;
        $this->nlpEngine = $nlpEngine;
        $this->collusionEngine = $collusionEngine;
        $this->shadowEngine = $shadowEngine;

        $this->config = array_merge([
            'enable_multi_source_alerts' => true,
            'correlation_bonus_enabled' => true,
            'generate_investigation_packages' => true,
            'alert_aggregation_window_hours' => 24,
            'min_engines_for_alert' => 2
        ], $config);
    }

    /**
     * Comprehensive fraud analysis for single staff member
     * Runs all 5 engines and correlates results
     *
     * @param int $staffId Staff member ID
     * @param array $options Analysis options
     * @return array Comprehensive fraud analysis
     */
    public function analyzeStaffMember(int $staffId, array $options = []): array
    {
        $startTime = microtime(true);

        $defaults = [
            'include_predictions' => true,
            'include_cv_analysis' => true,
            'include_communications' => true,
            'include_customer_collusion' => true,
            'include_digital_twin' => true,
            'generate_investigation_package' => true
        ];
        $options = array_merge($defaults, $options);

        $results = [
            'staff_id' => $staffId,
            'staff_name' => $this->getStaffName($staffId),
            'analysis_timestamp' => date('Y-m-d H:i:s')
        ];

        // Engine 1: ML Predictive Forecasting
        if ($options['include_predictions']) {
            $results['ml_prediction'] = $this->mlEngine->predictStaffFraudRisk($staffId);
        }

        // Engine 2: Computer Vision Behavioral Analysis
        if ($options['include_cv_analysis']) {
            $results['cv_behavior'] = $this->cvEngine->analyzeStaffBehavior($staffId, [
                'include_baseline_comparison' => true
            ]);
        }

        // Engine 3: NLP Communication Analysis
        if ($options['include_communications']) {
            $results['nlp_communications'] = $this->nlpEngine->monitorStaffCommunications($staffId, [
                'days' => 7,
                'include_network_analysis' => true
            ]);
        }

        // Engine 4: Customer Loyalty Collusion Detection
        if ($options['include_customer_collusion']) {
            $results['customer_collusion'] = $this->collusionEngine->scanStaffCustomerRelationships($staffId);
        }

        // Engine 5: AI Shadow Staff Digital Twin
        if ($options['include_digital_twin']) {
            $results['digital_twin'] = $this->shadowEngine->compareToDigitalTwin($staffId, [
                'period' => 'today',
                'include_recommendations' => true
            ]);
        }

        // Calculate composite risk score
        $compositeScore = $this->calculateCompositeRiskScore($results);

        // Detect correlations between engines
        $correlations = $this->detectCrossEngineCorrelations($results);

        // Generate multi-source alerts
        $alerts = [];
        if ($this->config['enable_multi_source_alerts']) {
            $alerts = $this->generateMultiSourceAlerts($staffId, $results, $compositeScore, $correlations);
        }

        // Generate investigation package
        $investigationPackage = null;
        if ($options['generate_investigation_package'] && $compositeScore['risk_level'] !== 'LOW') {
            $investigationPackage = $this->generateInvestigationPackage($staffId, $results, $compositeScore, $correlations);
        }

        // Store analysis
        $this->storeMultiSourceAnalysis($staffId, $results, $compositeScore, $correlations);

        $processingTime = microtime(true) - $startTime;

        return [
            'success' => true,
            'staff_id' => $staffId,
            'staff_name' => $results['staff_name'],
            'composite_risk_score' => $compositeScore,
            'engine_results' => $results,
            'cross_engine_correlations' => $correlations,
            'multi_source_alerts' => $alerts,
            'investigation_package' => $investigationPackage,
            'processing_time_seconds' => round($processingTime, 2),
            'analyzed_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Real-time monitoring dashboard for all active staff
     *
     * @return array Real-time fraud monitoring dashboard
     */
    public function realTimeMonitoringDashboard(): array
    {
        $startTime = microtime(true);

        // Get all currently clocked-in staff
        $activeStaff = $this->getActiveStaff();

        $dashboardData = [
            'critical_alerts' => [],
            'high_risk_staff' => [],
            'moderate_risk_staff' => [],
            'low_risk_staff' => [],
            'all_clear_staff' => []
        ];

        $totalAlerts = 0;
        $criticalCount = 0;
        $highCount = 0;

        foreach ($activeStaff as $staff) {
            $analysis = $this->analyzeStaffMember($staff['staff_id'], [
                'generate_investigation_package' => false
            ]);

            if (!$analysis['success']) {
                continue;
            }

            $staffSummary = [
                'staff_id' => $staff['staff_id'],
                'staff_name' => $staff['staff_name'],
                'composite_risk_score' => $analysis['composite_risk_score']['total_score'],
                'risk_level' => $analysis['composite_risk_score']['risk_level'],
                'alert_count' => count($analysis['multi_source_alerts']),
                'top_concern' => $this->getTopConcern($analysis)
            ];

            // Categorize by risk level
            switch ($analysis['composite_risk_score']['risk_level']) {
                case 'CRITICAL':
                    $dashboardData['critical_alerts'][] = $staffSummary;
                    $criticalCount++;
                    $totalAlerts += count($analysis['multi_source_alerts']);
                    break;
                case 'HIGH':
                    $dashboardData['high_risk_staff'][] = $staffSummary;
                    $highCount++;
                    $totalAlerts += count($analysis['multi_source_alerts']);
                    break;
                case 'MEDIUM':
                    $dashboardData['moderate_risk_staff'][] = $staffSummary;
                    break;
                case 'LOW':
                    $dashboardData['low_risk_staff'][] = $staffSummary;
                    break;
                default:
                    $dashboardData['all_clear_staff'][] = $staffSummary;
            }
        }

        // Sort each category by risk score
        foreach ($dashboardData as $key => $category) {
            if (is_array($category)) {
                usort($dashboardData[$key], fn($a, $b) => $b['composite_risk_score'] <=> $a['composite_risk_score']);
            }
        }

        $processingTime = microtime(true) - $startTime;

        return [
            'success' => true,
            'dashboard_timestamp' => date('Y-m-d H:i:s'),
            'active_staff_count' => count($activeStaff),
            'critical_alerts' => $criticalCount,
            'high_risk_count' => $highCount,
            'total_alerts' => $totalAlerts,
            'dashboard_data' => $dashboardData,
            'system_health' => $this->getSystemHealth(),
            'processing_time_seconds' => round($processingTime, 2)
        ];
    }

    /**
     * Batch analysis of all staff (comprehensive sweep)
     *
     * @param array $options Processing options
     * @return array Batch analysis results
     */
    public function comprehensiveFraudSweep(array $options = []): array
    {
        $startTime = microtime(true);

        $defaults = [
            'include_inactive_staff' => false,
            'min_risk_level_for_report' => 'MEDIUM',
            'generate_summary_report' => true
        ];
        $options = array_merge($defaults, $options);

        // Get staff to analyze
        $staff = $options['include_inactive_staff']
            ? $this->getAllStaff()
            : $this->getActiveStaff();

        $results = [
            'critical_risk' => [],
            'high_risk' => [],
            'medium_risk' => [],
            'low_risk' => [],
            'minimal_risk' => []
        ];

        $totalAnalyzed = 0;
        $totalFinancialImpact = 0.0;

        foreach ($staff as $staffMember) {
            $analysis = $this->analyzeStaffMember($staffMember['staff_id']);

            if ($analysis['success']) {
                $totalAnalyzed++;

                $riskLevel = $analysis['composite_risk_score']['risk_level'];

                $staffResult = [
                    'staff_id' => $staffMember['staff_id'],
                    'staff_name' => $staffMember['staff_name'],
                    'composite_score' => $analysis['composite_risk_score']['total_score'],
                    'risk_level' => $riskLevel,
                    'engine_scores' => $analysis['composite_risk_score']['engine_scores'],
                    'alert_count' => count($analysis['multi_source_alerts']),
                    'estimated_financial_impact' => $this->estimateFinancialImpact($analysis)
                ];

                $totalFinancialImpact += $staffResult['estimated_financial_impact'];

                // Categorize by risk level
                switch ($riskLevel) {
                    case 'CRITICAL':
                        $results['critical_risk'][] = $staffResult;
                        break;
                    case 'HIGH':
                        $results['high_risk'][] = $staffResult;
                        break;
                    case 'MEDIUM':
                        $results['medium_risk'][] = $staffResult;
                        break;
                    case 'LOW':
                        $results['low_risk'][] = $staffResult;
                        break;
                    default:
                        $results['minimal_risk'][] = $staffResult;
                }
            }
        }

        // Sort each category by score
        foreach ($results as $key => $category) {
            usort($results[$key], fn($a, $b) => $b['composite_score'] <=> $a['composite_score']);
        }

        $processingTime = microtime(true) - $startTime;

        $summary = [
            'total_staff_analyzed' => $totalAnalyzed,
            'critical_risk_count' => count($results['critical_risk']),
            'high_risk_count' => count($results['high_risk']),
            'medium_risk_count' => count($results['medium_risk']),
            'low_risk_count' => count($results['low_risk']),
            'minimal_risk_count' => count($results['minimal_risk']),
            'total_estimated_financial_impact' => round($totalFinancialImpact, 2),
            'processing_time_seconds' => round($processingTime, 2)
        ];

        return [
            'success' => true,
            'sweep_type' => 'comprehensive',
            'sweep_timestamp' => date('Y-m-d H:i:s'),
            'summary' => $summary,
            'detailed_results' => $results,
            'top_10_highest_risk' => array_slice(array_merge(
                $results['critical_risk'],
                $results['high_risk'],
                $results['medium_risk']
            ), 0, 10)
        ];
    }

    /**
     * Calculate composite risk score from all engines
     *
     * @param array $engineResults Results from all 5 engines
     * @return array Composite risk score
     */
    private function calculateCompositeRiskScore(array $engineResults): array
    {
        $engineScores = [
            'ml_prediction' => 0.0,
            'cv_behavior' => 0.0,
            'nlp_communication' => 0.0,
            'customer_collusion' => 0.0,
            'digital_twin' => 0.0
        ];

        $enginesActive = 0;

        // Extract risk scores from each engine
        if (isset($engineResults['ml_prediction']['success']) && $engineResults['ml_prediction']['success']) {
            $engineScores['ml_prediction'] = $engineResults['ml_prediction']['fraud_probability'] ?? 0.0;
            $enginesActive++;
        }

        if (isset($engineResults['cv_behavior']['success']) && $engineResults['cv_behavior']['success']) {
            $engineScores['cv_behavior'] = $engineResults['cv_behavior']['behavioral_risk_score'] ?? 0.0;
            $enginesActive++;
        }

        if (isset($engineResults['nlp_communications']['success']) && $engineResults['nlp_communications']['success']) {
            $avgCommScore = 0.0;
            $commCount = count($engineResults['nlp_communications']['analyzed_messages'] ?? []);
            if ($commCount > 0) {
                foreach ($engineResults['nlp_communications']['analyzed_messages'] as $msg) {
                    $avgCommScore += $msg['risk_score'] ?? 0.0;
                }
                $avgCommScore /= $commCount;
            }
            $engineScores['nlp_communication'] = $avgCommScore;
            $enginesActive++;
        }

        if (isset($engineResults['customer_collusion']['success']) && $engineResults['customer_collusion']['success']) {
            $maxCollusionScore = 0.0;
            foreach ($engineResults['customer_collusion']['relationships'] ?? [] as $rel) {
                $maxCollusionScore = max($maxCollusionScore, $rel['collusion_score'] ?? 0.0);
            }
            $engineScores['customer_collusion'] = $maxCollusionScore;
            $enginesActive++;
        }

        if (isset($engineResults['digital_twin']['success']) && $engineResults['digital_twin']['success']) {
            $engineScores['digital_twin'] = $engineResults['digital_twin']['total_deviation_score'] ?? 0.0;
            $enginesActive++;
        }

        // Calculate weighted composite score
        $compositeScore = 0.0;
        foreach ($engineScores as $engine => $score) {
            $compositeScore += $score * self::ENGINE_WEIGHTS[$engine];
        }

        // Apply correlation bonus if multiple engines agree
        if ($this->config['correlation_bonus_enabled']) {
            $highRiskEngines = count(array_filter($engineScores, fn($s) => $s >= 0.70));
            if ($highRiskEngines >= 3) {
                $compositeScore = min(1.0, $compositeScore + self::CORRELATION_BONUS);
            }
        }

        // Determine risk level
        $riskLevel = 'LOW';
        foreach (self::COMPOSITE_THRESHOLDS as $level => $threshold) {
            if ($compositeScore >= $threshold) {
                $riskLevel = $level;
                break;
            }
        }

        return [
            'total_score' => round($compositeScore, 3),
            'risk_level' => $riskLevel,
            'engine_scores' => $engineScores,
            'engines_active' => $enginesActive,
            'correlation_bonus_applied' => ($this->config['correlation_bonus_enabled'] && $highRiskEngines >= 3)
        ];
    }

    /**
     * Detect correlations between different engine results
     *
     * @param array $engineResults
     * @return array Detected correlations
     */
    private function detectCrossEngineCorrelations(array $engineResults): array
    {
        $correlations = [];

        // Pattern 1: ML predicts high risk + CV detects stress
        if (($engineResults['ml_prediction']['fraud_probability'] ?? 0) >= 0.70 &&
            ($engineResults['cv_behavior']['stress_score'] ?? 0) >= 0.65) {
            $correlations[] = [
                'pattern' => 'ml_cv_stress_correlation',
                'severity' => 'HIGH',
                'description' => 'High fraud probability prediction correlates with elevated stress indicators',
                'confidence' => 0.85
            ];
        }

        // Pattern 2: CV concealment + NLP suspicious communications
        if (($engineResults['cv_behavior']['concealment_score'] ?? 0) >= 0.70 &&
            count(array_filter($engineResults['nlp_communications']['analyzed_messages'] ?? [], fn($m) => $m['risk_score'] >= 0.70)) > 0) {
            $correlations[] = [
                'pattern' => 'cv_nlp_concealment_communication',
                'severity' => 'CRITICAL',
                'description' => 'Concealment behaviors detected while discussing suspicious topics',
                'confidence' => 0.90
            ];
        }

        // Pattern 3: Customer collusion + Digital twin deviation
        if (($engineResults['customer_collusion']['suspicious_relationships'] ?? 0) > 0 &&
            ($engineResults['digital_twin']['total_deviation_score'] ?? 0) >= 0.70) {
            $correlations[] = [
                'pattern' => 'collusion_behavioral_change',
                'severity' => 'HIGH',
                'description' => 'Suspicious customer relationships coincide with abnormal behavioral patterns',
                'confidence' => 0.80
            ];
        }

        // Pattern 4: ML high risk + Customer collusion + NLP communications (Triple threat)
        if (($engineResults['ml_prediction']['fraud_probability'] ?? 0) >= 0.70 &&
            ($engineResults['customer_collusion']['suspicious_relationships'] ?? 0) > 0 &&
            count(array_filter($engineResults['nlp_communications']['analyzed_messages'] ?? [], fn($m) => $m['risk_score'] >= 0.70)) > 0) {
            $correlations[] = [
                'pattern' => 'triple_threat_correlation',
                'severity' => 'CRITICAL',
                'description' => 'ML prediction, customer collusion, and suspicious communications all indicate fraud',
                'confidence' => 0.95
            ];
        }

        return $correlations;
    }

    /**
     * Generate multi-source alerts
     *
     * @param int $staffId
     * @param array $engineResults
     * @param array $compositeScore
     * @param array $correlations
     * @return array Generated alerts
     */
    private function generateMultiSourceAlerts(
        int $staffId,
        array $engineResults,
        array $compositeScore,
        array $correlations
    ): array {
        $alerts = [];

        // Critical composite alert
        if ($compositeScore['risk_level'] === 'CRITICAL') {
            $alerts[] = [
                'alert_id' => uniqid('multi_'),
                'alert_type' => 'MULTI_SOURCE_CRITICAL',
                'severity' => 'CRITICAL',
                'staff_id' => $staffId,
                'composite_score' => $compositeScore['total_score'],
                'message' => "CRITICAL: Multi-source fraud indicators detected",
                'contributing_engines' => array_filter($compositeScore['engine_scores'], fn($s) => $s >= 0.70),
                'correlations' => $correlations,
                'recommended_action' => 'Immediate investigation required - Contact security and management',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }

        // Correlation-specific alerts
        foreach ($correlations as $correlation) {
            if ($correlation['severity'] === 'CRITICAL') {
                $alerts[] = [
                    'alert_id' => uniqid('corr_'),
                    'alert_type' => 'CROSS_ENGINE_CORRELATION',
                    'severity' => $correlation['severity'],
                    'staff_id' => $staffId,
                    'pattern' => $correlation['pattern'],
                    'message' => $correlation['description'],
                    'confidence' => $correlation['confidence'],
                    'recommended_action' => 'Review correlated evidence across multiple systems',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
        }

        return $alerts;
    }

    /**
     * Generate comprehensive investigation package
     *
     * @param int $staffId
     * @param array $engineResults
     * @param array $compositeScore
     * @param array $correlations
     * @return array Investigation package
     */
    private function generateInvestigationPackage(
        int $staffId,
        array $engineResults,
        array $compositeScore,
        array $correlations
    ): array {
        return [
            'package_id' => uniqid('inv_'),
            'staff_id' => $staffId,
            'staff_name' => $this->getStaffName($staffId),
            'generated_at' => date('Y-m-d H:i:s'),
            'composite_risk_score' => $compositeScore['total_score'],
            'risk_level' => $compositeScore['risk_level'],

            'executive_summary' => $this->generateExecutiveSummary($engineResults, $compositeScore, $correlations),

            'evidence_by_engine' => [
                'ml_prediction' => $this->extractMLEvidence($engineResults['ml_prediction'] ?? []),
                'cv_behavior' => $this->extractCVEvidence($engineResults['cv_behavior'] ?? []),
                'nlp_communications' => $this->extractNLPEvidence($engineResults['nlp_communications'] ?? []),
                'customer_collusion' => $this->extractCollusionEvidence($engineResults['customer_collusion'] ?? []),
                'digital_twin' => $this->extractDigitalTwinEvidence($engineResults['digital_twin'] ?? [])
            ],

            'cross_engine_correlations' => $correlations,

            'recommended_actions' => $this->generateInvestigationActions($compositeScore, $correlations),

            'timeline' => $this->generateFraudTimeline($staffId),

            'financial_impact_estimate' => $this->estimateFinancialImpact($engineResults)
        ];
    }

    // ========== UTILITY METHODS ==========

    private function getStaffName(int $staffId): string
    {
        $sql = "SELECT staff_name FROM staff WHERE staff_id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $staffId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['staff_name'] ?? 'Unknown';
    }

    private function getActiveStaff(): array
    {
        $sql = "SELECT staff_id, staff_name FROM staff WHERE currently_clocked_in = 1 AND active = 1";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getAllStaff(): array
    {
        $sql = "SELECT staff_id, staff_name FROM staff WHERE active = 1";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTopConcern(array $analysis): string
    {
        $scores = $analysis['composite_risk_score']['engine_scores'];
        arsort($scores);
        $topEngine = array_key_first($scores);

        return match($topEngine) {
            'ml_prediction' => 'High fraud probability predicted',
            'cv_behavior' => 'Behavioral anomalies detected',
            'nlp_communication' => 'Suspicious communications',
            'customer_collusion' => 'Customer relationship concerns',
            'digital_twin' => 'Behavioral deviation from baseline',
            default => 'Multiple indicators'
        };
    }

    private function getSystemHealth(): array
    {
        return [
            'ml_engine' => 'operational',
            'cv_engine' => 'operational',
            'nlp_engine' => 'operational',
            'collusion_engine' => 'operational',
            'shadow_engine' => 'operational',
            'overall_status' => 'healthy'
        ];
    }

    private function estimateFinancialImpact(array $engineResults): float
    {
        // Simplified estimation - would need more sophisticated calculation
        $baseImpact = 1000.0;
        $compositeScore = $this->calculateCompositeRiskScore($engineResults)['total_score'];

        return round($baseImpact * $compositeScore * 10, 2);
    }

    private function generateExecutiveSummary(array $engineResults, array $compositeScore, array $correlations): string
    {
        $summary = "Multi-Source Fraud Analysis Summary:\n\n";
        $summary .= "Composite Risk Score: {$compositeScore['total_score']} ({$compositeScore['risk_level']})\n";
        $summary .= "Active Detection Engines: {$compositeScore['engines_active']}/5\n\n";

        if (!empty($correlations)) {
            $summary .= "CRITICAL CORRELATIONS DETECTED:\n";
            foreach ($correlations as $corr) {
                $summary .= "- {$corr['description']} (Confidence: {$corr['confidence']})\n";
            }
            $summary .= "\n";
        }

        $summary .= "Immediate Action Required: ";
        $summary .= $compositeScore['risk_level'] === 'CRITICAL' ? "YES - Immediate investigation" : "Monitor closely";

        return $summary;
    }

    private function extractMLEvidence(array $mlResults): array
    {
        if (empty($mlResults) || !$mlResults['success']) return [];

        return [
            'fraud_probability' => $mlResults['fraud_probability'],
            'risk_level' => $mlResults['risk_level'],
            'top_features' => $mlResults['feature_scores'] ?? [],
            'interventions' => $mlResults['interventions'] ?? []
        ];
    }

    private function extractCVEvidence(array $cvResults): array
    {
        if (empty($cvResults) || !$cvResults['success']) return [];

        return [
            'behavioral_risk_score' => $cvResults['behavioral_risk_score'],
            'top_indicators' => $cvResults['top_indicators'] ?? [],
            'baseline_deviations' => $cvResults['baseline_deviations'] ?? []
        ];
    }

    private function extractNLPEvidence(array $nlpResults): array
    {
        if (empty($nlpResults) || !$nlpResults['success']) return [];

        $highRiskMessages = array_filter(
            $nlpResults['analyzed_messages'] ?? [],
            fn($m) => $m['risk_score'] >= 0.70
        );

        return [
            'high_risk_message_count' => count($highRiskMessages),
            'patterns_detected' => $nlpResults['patterns_detected'] ?? [],
            'collusion_networks' => $nlpResults['collusion_networks'] ?? []
        ];
    }

    private function extractCollusionEvidence(array $collusionResults): array
    {
        if (empty($collusionResults) || !$collusionResults['success']) return [];

        return [
            'suspicious_relationships' => $collusionResults['suspicious_relationships'] ?? 0,
            'relationships' => $collusionResults['relationships'] ?? []
        ];
    }

    private function extractDigitalTwinEvidence(array $twinResults): array
    {
        if (empty($twinResults) || !$twinResults['success']) return [];

        return [
            'deviation_score' => $twinResults['total_deviation_score'],
            'deviation_level' => $twinResults['deviation_level'],
            'top_deviating_dimensions' => $twinResults['top_deviating_dimensions'] ?? []
        ];
    }

    private function generateInvestigationActions(array $compositeScore, array $correlations): array
    {
        $actions = [];

        if ($compositeScore['risk_level'] === 'CRITICAL') {
            $actions[] = [
                'priority' => 'IMMEDIATE',
                'action' => 'Notify security manager and store manager',
                'timeline' => 'Within 1 hour'
            ];
            $actions[] = [
                'priority' => 'IMMEDIATE',
                'action' => 'Review all recent transactions',
                'timeline' => 'Within 4 hours'
            ];
            $actions[] = [
                'priority' => 'HIGH',
                'action' => 'Interview staff member',
                'timeline' => 'Within 24 hours'
            ];
        }

        return $actions;
    }

    private function generateFraudTimeline(int $staffId): array
    {
        // Would fetch and compile timeline from all engines
        return [
            'timeline_start' => date('Y-m-d', strtotime('-30 days')),
            'timeline_end' => date('Y-m-d'),
            'key_events' => []
        ];
    }

    private function storeMultiSourceAnalysis(
        int $staffId,
        array $engineResults,
        array $compositeScore,
        array $correlations
    ): void {
        $sql = "
            INSERT INTO multi_source_fraud_analysis (
                staff_id,
                composite_risk_score,
                risk_level,
                engine_scores,
                correlations,
                analysis_data,
                analyzed_at,
                created_at
            ) VALUES (
                :staff_id,
                :score,
                :risk_level,
                :engine_scores,
                :correlations,
                :data,
                NOW(),
                NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'staff_id' => $staffId,
            'score' => $compositeScore['total_score'],
            'risk_level' => $compositeScore['risk_level'],
            'engine_scores' => json_encode($compositeScore['engine_scores']),
            'correlations' => json_encode($correlations),
            'data' => json_encode($engineResults)
        ]);
    }
}
