<?php

/**
 * Multi-Source Fraud Detection Analyzer
 *
 * Correlates data from MULTIPLE sources to detect fraudulent behavior:
 *
 * PRIMARY SOURCES:
 * 1. Lightspeed/Vend - Sales, voids, refunds, discounts (MOST IMPORTANT)
 * 2. CIS - Cash register reconciliation, deposits, banking
 * 3. Security/CCTV - Camera events, person detection
 *
 * SECONDARY SOURCES:
 * 4. Email scanning - Outlet inbox monitoring (future)
 * 5. System access logs - Login patterns, unusual access
 * 6. Location tracking - Badge scans, Deputy data
 *
 * @package FraudDetection
 * @version 2.0.0
 */

namespace FraudDetection;

use PDO;
use Exception;

class MultiSourceFraudAnalyzer
{
    private PDO $pdo;
    private array $config;
    private array $analysisResults = [];

    // Fraud pattern thresholds
    private const VOID_THRESHOLD = 3;           // Voids per day
    private const REFUND_THRESHOLD = 5;         // Refunds per week
    private const DISCOUNT_THRESHOLD_PERCENT = 15; // Avg discount %
    private const CASH_SHORTAGE_THRESHOLD = 50; // Dollars
    private const AFTER_HOURS_MINUTES = 30;     // Minutes after close

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'analysis_window_days' => 30,
            'confidence_threshold' => 0.75,
            'enable_email_scanning' => false, // Future feature
            'enable_deep_camera_correlation' => true, // NEW: Deep camera analysis
        ], $config);
    }

    /**
     * Run comprehensive fraud analysis for a staff member
     * Pulls data from ALL available sources
     *
     * @param int $staffId
     * @param array $options
     * @return array Analysis results with fraud score
     */
    public function analyzeStaff(int $staffId, array $options = []): array
    {
        $this->analysisResults = [
            'staff_id' => $staffId,
            'analysis_timestamp' => date('Y-m-d H:i:s'),
            'sources_analyzed' => [],
            'fraud_indicators' => [],
            'fraud_score' => 0.0,
            'risk_level' => 'low',
            'recommendations' => []
        ];

        try {
            // PRIORITY 1: Lightspeed/Vend Transaction Analysis
            $this->analyzeLightspeedTransactions($staffId);

            // PRIORITY 2: CIS Cash Register Analysis
            $this->analyzeCISCashActivity($staffId);

            // PRIORITY 3: Security Camera Correlation
            $this->analyzeSecurityEvents($staffId);

            // PRIORITY 4: System Access Patterns
            $this->analyzeSystemAccess($staffId);

            // PRIORITY 5: Location & Behavioral Patterns
            $this->analyzeLocationPatterns($staffId);

            // PRIORITY 6: DEEP Camera-Transaction Correlation (NEW!)
            if ($this->config['enable_deep_camera_correlation']) {
                $this->analyzeDeepCameraCorrelation($staffId);
            }

            // PRIORITY 7: LIGHTSPEED DEEP DIVE ANALYSIS (COMPREHENSIVE POS FRAUD)
            $this->analyzeLightspeedDeepDive($staffId);

            // Calculate final fraud score
            $this->calculateFraudScore();

            // Generate recommendations
            $this->generateRecommendations();

            // Store analysis results
            $this->storeAnalysisResults();

            return $this->analysisResults;

        } catch (Exception $e) {
            error_log("Fraud analysis failed for staff {$staffId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * PRIORITY 1: Analyze Lightspeed/Vend transaction patterns
     * This is THE MOST IMPORTANT data source
     */
    private function analyzeLightspeedTransactions(int $staffId): void
    {
        $days = $this->config['analysis_window_days'];

        try {
            // Get void patterns
            $voids = $this->getLightspeedVoids($staffId, $days);
            if ($voids['count'] > self::VOID_THRESHOLD * ($days / 7)) {
                $this->addFraudIndicator(
                    'excessive_voids',
                    'lightspeed',
                    "High void frequency: {$voids['count']} voids in {$days} days",
                    0.8,
                    $voids
                );
            }

            // Get refund patterns
            $refunds = $this->getLightspeedRefunds($staffId, $days);
            if ($refunds['count'] > self::REFUND_THRESHOLD * ($days / 7)) {
                $this->addFraudIndicator(
                    'excessive_refunds',
                    'lightspeed',
                    "High refund frequency: {$refunds['count']} refunds in {$days} days",
                    0.75,
                    $refunds
                );
            }

            // Get discount patterns
            $discounts = $this->getLightspeedDiscounts($staffId, $days);
            if ($discounts['avg_discount_percent'] > self::DISCOUNT_THRESHOLD_PERCENT) {
                $this->addFraudIndicator(
                    'excessive_discounts',
                    'lightspeed',
                    "High discount usage: {$discounts['avg_discount_percent']}% average",
                    0.7,
                    $discounts
                );
            }

            // Check for after-hours transactions
            $afterHours = $this->getLightspeedAfterHoursTransactions($staffId, $days);
            if ($afterHours['count'] > 0) {
                $this->addFraudIndicator(
                    'after_hours_transactions',
                    'lightspeed',
                    "After-hours transactions detected: {$afterHours['count']}",
                    0.85,
                    $afterHours
                );
            }

            // Check for rapid-fire transactions (potential skimming)
            $rapidFire = $this->getLightspeedRapidFireTransactions($staffId, $days);
            if ($rapidFire['count'] > 5) {
                $this->addFraudIndicator(
                    'rapid_fire_transactions',
                    'lightspeed',
                    "Rapid consecutive transactions detected: {$rapidFire['count']} instances",
                    0.65,
                    $rapidFire
                );
            }

            $this->analysisResults['sources_analyzed'][] = 'lightspeed_transactions';

        } catch (Exception $e) {
            error_log("Lightspeed analysis failed: " . $e->getMessage());
        }
    }

    /**
     * PRIORITY 2: Analyze CIS cash register activity
     * Cash ups, deposits, banking - critical for theft detection
     */
    private function analyzeCISCashActivity(int $staffId): void
    {
        $days = $this->config['analysis_window_days'];

        try {
            // Cash register shortages
            $shortages = $this->getCISCashShortages($staffId, $days);
            if ($shortages['total_shortage'] > self::CASH_SHORTAGE_THRESHOLD) {
                $this->addFraudIndicator(
                    'cash_shortages',
                    'cis_cash',
                    "Cash shortages total: \${$shortages['total_shortage']}",
                    0.9,
                    $shortages
                );
            }

            // Deposit discrepancies
            $depositIssues = $this->getCISDepositDiscrepancies($staffId, $days);
            if ($depositIssues['count'] > 0) {
                $this->addFraudIndicator(
                    'deposit_discrepancies',
                    'cis_cash',
                    "Deposit discrepancies: {$depositIssues['count']} instances",
                    0.85,
                    $depositIssues
                );
            }

            // Banking transaction anomalies
            $bankingAnomalies = $this->getCISBankingAnomalies($staffId, $days);
            if ($bankingAnomalies['count'] > 0) {
                $this->addFraudIndicator(
                    'banking_anomalies',
                    'cis_cash',
                    "Banking anomalies detected: {$bankingAnomalies['count']}",
                    0.8,
                    $bankingAnomalies
                );
            }

            $this->analysisResults['sources_analyzed'][] = 'cis_cash_activity';

        } catch (Exception $e) {
            error_log("CIS cash analysis failed: " . $e->getMessage());
        }
    }

    /**
     * PRIORITY 3: Analyze security camera events
     */
    private function analyzeSecurityEvents(int $staffId): void
    {
        $days = $this->config['analysis_window_days'];

        try {
            // Get security events correlated to this staff member
            $stmt = $this->pdo->prepare("
                SELECT
                    se.event_type,
                    se.zone,
                    se.alert_level,
                    COUNT(*) as event_count,
                    GROUP_CONCAT(DISTINCT se.camera_name) as cameras
                FROM security_events se
                JOIN security_event_staff_correlation sc ON se.id = sc.security_event_id
                WHERE sc.staff_id = :staff_id
                AND se.received_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY se.event_type, se.zone, se.alert_level
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($events as $event) {
                if ($event['alert_level'] === 'critical' || $event['alert_level'] === 'high') {
                    $this->addFraudIndicator(
                        'security_alert',
                        'security_camera',
                        "{$event['event_type']} in {$event['zone']}: {$event['event_count']} times",
                        0.7,
                        $event
                    );
                }
            }

            $this->analysisResults['sources_analyzed'][] = 'security_events';

        } catch (Exception $e) {
            error_log("Security event analysis failed: " . $e->getMessage());
        }
    }

    /**
     * PRIORITY 4: Analyze system access patterns
     */
    private function analyzeSystemAccess(int $staffId): void
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    action_category,
                    COUNT(*) as action_count,
                    COUNT(DISTINCT DATE(accessed_at)) as days_active
                FROM system_access_log
                WHERE staff_id = :staff_id
                AND accessed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY action_category
            ");
            $stmt->execute(['staff_id' => $staffId]);
            $accessPatterns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Check for anomalies in access_anomalies table
            $stmt = $this->pdo->prepare("
                SELECT anomaly_type, severity, COUNT(*) as count
                FROM access_anomalies
                WHERE staff_id = :staff_id
                AND detected_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY anomaly_type, severity
            ");
            $stmt->execute(['staff_id' => $staffId]);
            $anomalies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($anomalies as $anomaly) {
                if ($anomaly['severity'] === 'high') {
                    $this->addFraudIndicator(
                        'system_access_anomaly',
                        'system_access',
                        "{$anomaly['anomaly_type']}: {$anomaly['count']} occurrences",
                        0.6,
                        $anomaly
                    );
                }
            }

            $this->analysisResults['sources_analyzed'][] = 'system_access';

        } catch (Exception $e) {
            error_log("System access analysis failed: " . $e->getMessage());
        }
    }

    /**
     * PRIORITY 5: Analyze location and behavioral patterns
     */
    private function analyzeLocationPatterns(int $staffId): void
    {
        try {
            // Check for location anomalies (being at unexpected outlets)
            $stmt = $this->pdo->prepare("
                SELECT
                    outlet_id,
                    COUNT(*) as visit_count,
                    MAX(recorded_at) as last_visit
                FROM staff_location_history
                WHERE staff_id = :staff_id
                AND recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY outlet_id
            ");
            $stmt->execute(['staff_id' => $staffId]);
            $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get staff's assigned outlets from Deputy
            $assignedOutlets = $this->getStaffAssignedOutlets($staffId);

            foreach ($locations as $location) {
                if (!in_array($location['outlet_id'], $assignedOutlets)) {
                    $this->addFraudIndicator(
                        'unauthorized_outlet_access',
                        'location_tracking',
                        "Staff detected at non-assigned outlet {$location['outlet_id']}",
                        0.5,
                        $location
                    );
                }
            }

            $this->analysisResults['sources_analyzed'][] = 'location_patterns';

        } catch (Exception $e) {
            error_log("Location pattern analysis failed: " . $e->getMessage());
        }
    }

    /**
     * Helper: Get Lightspeed void transactions
     */
    private function getLightspeedVoids(int $staffId, int $days): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as count,
                SUM(total_price) as total_amount,
                AVG(total_price) as avg_amount
            FROM vend_sales
            WHERE user_id = :staff_id
            AND status = 'VOIDED'
            AND sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Helper: Get Lightspeed refund transactions
     */
    private function getLightspeedRefunds(int $staffId, int $days): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as count,
                SUM(ABS(total_price)) as total_amount,
                AVG(ABS(total_price)) as avg_amount
            FROM vend_sales
            WHERE user_id = :staff_id
            AND total_price < 0
            AND status = 'CLOSED'
            AND sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Helper: Get Lightspeed discount patterns
     */
    private function getLightspeedDiscounts(int $staffId, int $days): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as count,
                AVG(total_discount) as avg_discount,
                AVG((total_discount / total_price) * 100) as avg_discount_percent
            FROM vend_sales
            WHERE user_id = :staff_id
            AND total_discount > 0
            AND sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Helper: Get after-hours Lightspeed transactions
     */
    private function getLightspeedAfterHoursTransactions(int $staffId, int $days): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as count,
                GROUP_CONCAT(DISTINCT DATE(sale_date)) as dates
            FROM vend_sales
            WHERE user_id = :staff_id
            AND (HOUR(sale_date) < 6 OR HOUR(sale_date) >= 22)
            AND sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Helper: Get rapid-fire transaction patterns
     */
    private function getLightspeedRapidFireTransactions(int $staffId, int $days): array
    {
        // Find transactions within 30 seconds of each other
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM (
                SELECT
                    s1.id,
                    s1.sale_date,
                    COUNT(s2.id) as rapid_count
                FROM vend_sales s1
                JOIN vend_sales s2 ON s2.user_id = s1.user_id
                    AND s2.sale_date BETWEEN s1.sale_date
                    AND DATE_ADD(s1.sale_date, INTERVAL 30 SECOND)
                    AND s2.id != s1.id
                WHERE s1.user_id = :staff_id
                AND s1.sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY s1.id, s1.sale_date
                HAVING rapid_count >= 2
            ) AS rapid_transactions
        ");
        $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Helper: Get CIS cash shortages
     */
    private function getCISCashShortages(int $staffId, int $days): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as count,
                SUM(ABS(variance_amount)) as total_shortage,
                AVG(ABS(variance_amount)) as avg_shortage
            FROM cash_register_reconciliation
            WHERE staff_id = :staff_id
            AND variance_amount < 0
            AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Helper: Get CIS deposit discrepancies
     */
    private function getCISDepositDiscrepancies(int $staffId, int $days): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as count,
                SUM(ABS(discrepancy_amount)) as total_discrepancy
            FROM store_deposits
            WHERE staff_id = :staff_id
            AND discrepancy_amount != 0
            AND deposit_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Helper: Get CIS banking anomalies
     */
    private function getCISBankingAnomalies(int $staffId, int $days): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as count,
                GROUP_CONCAT(DISTINCT transaction_type) as anomaly_types
            FROM banking_transactions
            WHERE staff_id = :staff_id
            AND is_flagged = 1
            AND transaction_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Helper: Get staff's assigned outlets from Deputy
     */
    private function getStaffAssignedOutlets(int $staffId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT outlet_id
                FROM deputy_location_mapping
                WHERE staff_id = :staff_id
            ");
            $stmt->execute(['staff_id' => $staffId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Add fraud indicator to results
     */
    private function addFraudIndicator(
        string $type,
        string $source,
        string $description,
        float $weight,
        array $data
    ): void {
        $this->analysisResults['fraud_indicators'][] = [
            'type' => $type,
            'source' => $source,
            'description' => $description,
            'weight' => $weight,
            'data' => $data,
            'detected_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Calculate final fraud score (0-100)
     */
    private function calculateFraudScore(): void
    {
        $totalWeight = 0;
        $weightedScore = 0;

        foreach ($this->analysisResults['fraud_indicators'] as $indicator) {
            $totalWeight += $indicator['weight'];
            $weightedScore += $indicator['weight'] * 100;
        }

        if ($totalWeight > 0) {
            $this->analysisResults['fraud_score'] = min(100, $weightedScore / count($this->analysisResults['fraud_indicators']));
        }

        // Determine risk level
        $score = $this->analysisResults['fraud_score'];
        $this->analysisResults['risk_level'] = match (true) {
            $score >= 80 => 'critical',
            $score >= 60 => 'high',
            $score >= 40 => 'medium',
            default => 'low'
        };
    }

    /**
     * Generate actionable recommendations
     */
    private function generateRecommendations(): void
    {
        $recommendations = [];

        foreach ($this->analysisResults['fraud_indicators'] as $indicator) {
            switch ($indicator['type']) {
                case 'excessive_voids':
                    $recommendations[] = "Review all void transactions for legitimacy";
                    $recommendations[] = "Implement manager approval for voids";
                    break;
                case 'cash_shortages':
                    $recommendations[] = "Conduct immediate cash audit";
                    $recommendations[] = "Review cash handling procedures";
                    break;
                case 'after_hours_transactions':
                    $recommendations[] = "Investigate after-hours access";
                    $recommendations[] = "Review security camera footage";
                    break;
            }
        }

        $this->analysisResults['recommendations'] = array_unique($recommendations);
    }

    /**
     * PRIORITY 6: DEEP Camera-Transaction Correlation
     *
     * Uses AdvancedCameraTransactionCorrelator to perform deep analysis:
     * - Till activity vs camera visibility
     * - Login/logout vs physical presence
     * - Cash transactions vs camera confirmation
     * - Ghost transactions and ghost presence detection
     */
    private function analyzeDeepCameraCorrelation(int $staffId): void
    {
        try {
            require_once __DIR__ . '/AdvancedCameraTransactionCorrelator.php';

            $correlator = new AdvancedCameraTransactionCorrelator($this->pdo, [
                'enable_deep_analysis' => true,
                'alert_on_mismatch' => true,
                'store_detailed_logs' => true,
            ]);

            $correlationResults = $correlator->analyzeStaffCorrelation(
                $staffId,
                $this->config['analysis_window_days']
            );

            // Add correlation results to analysis
            $this->analysisResults['camera_correlation'] = $correlationResults;

            // Extract fraud indicators from correlation
            $correlationScore = $correlationResults['correlation_score'] ?? 100;
            $riskLevel = $correlationResults['risk_level'] ?? 'low';

            // Low correlation score = high fraud risk
            if ($correlationScore < 50) {
                $this->addFraudIndicator(
                    'poor_camera_correlation',
                    'deep_camera_analysis',
                    "Very low camera-transaction correlation: {$correlationScore}%",
                    0.95,
                    $correlationResults['summary']
                );
            } elseif ($correlationScore < 70) {
                $this->addFraudIndicator(
                    'moderate_camera_correlation',
                    'deep_camera_analysis',
                    "Low camera-transaction correlation: {$correlationScore}%",
                    0.75,
                    $correlationResults['summary']
                );
            }

            // Ghost transactions
            $ghostTransactions = $correlationResults['summary']['ghost_transactions'] ?? 0;
            if ($ghostTransactions > 0) {
                $this->addFraudIndicator(
                    'ghost_transactions',
                    'deep_camera_analysis',
                    "Ghost transactions detected: {$ghostTransactions} transactions without camera confirmation",
                    0.9,
                    ['count' => $ghostTransactions]
                );
            }

            // Ghost presence
            $ghostPresence = $correlationResults['summary']['ghost_presence'] ?? 0;
            if ($ghostPresence > 3) {
                $this->addFraudIndicator(
                    'ghost_presence',
                    'deep_camera_analysis',
                    "Ghost presence detected: {$ghostPresence} instances of presence without transactions",
                    0.7,
                    ['count' => $ghostPresence]
                );
            }

            // Add specific mismatches as fraud indicators
            foreach ($correlationResults['mismatches'] ?? [] as $mismatch) {
                if ($mismatch['severity'] >= 0.8) {
                    $this->addFraudIndicator(
                        'camera_transaction_mismatch_' . $mismatch['type'],
                        'deep_camera_analysis',
                        $mismatch['description'],
                        $mismatch['severity'],
                        $mismatch
                    );
                }
            }

            $this->analysisResults['sources_analyzed'][] = 'deep_camera_correlation';

        } catch (Exception $e) {
            error_log("Deep camera correlation failed: " . $e->getMessage());
        }
    }

    /**
     * PRIORITY 7: LIGHTSPEED DEEP DIVE ANALYSIS
     *
     * Comprehensive POS data analysis covering ALL fraud vectors:
     * - Payment type fraud (unusual/random payment types)
     * - Customer account fraud (fake accounts, credit manipulation)
     * - Inventory fraud (adjustments, transfers, shrinkage)
     * - Register closure fraud (till discrepancies)
     * - Banking fraud (missing deposits, delays)
     * - Transaction manipulation (voids, refunds, discounts)
     * - Reconciliation fraud (daily/weekly gaps)
     */
    private function analyzeLightspeedDeepDive(int $staffId): void
    {
        try {
            require_once __DIR__ . '/LightspeedDeepDiveAnalyzer.php';

            $deepDive = new LightspeedDeepDiveAnalyzer($this->pdo, [
                'analysis_window_days' => $this->config['analysis_window_days'],
                'enable_all_checks' => true,
                'alert_on_critical' => true,
            ]);

            $deepDiveResults = $deepDive->analyzeStaff(
                $staffId,
                $this->config['analysis_window_days']
            );

            // Add deep-dive results to main analysis
            $this->analysisResults['lightspeed_deep_dive'] = $deepDiveResults;

            // Extract fraud indicators
            foreach ($deepDiveResults['fraud_indicators'] ?? [] as $indicator) {
                // Add each indicator with full context
                $this->addFraudIndicator(
                    $indicator['type'],
                    $indicator['category'],
                    $indicator['description'],
                    $indicator['severity'],
                    $indicator['data']
                );
            }

            // Add section summaries
            foreach ($deepDiveResults['sections'] ?? [] as $sectionName => $sectionData) {
                if (count($sectionData['issues_found'] ?? []) > 0) {
                    $this->analysisResults['sources_analyzed'][] = "lightspeed_{$sectionName}";
                }
            }

            // Critical alerts
            if (count($deepDiveResults['critical_alerts'] ?? []) > 0) {
                foreach ($deepDiveResults['critical_alerts'] as $alert) {
                    $this->analysisResults['recommendations'][] =
                        "CRITICAL: " . $alert['description'];
                }
            }

            // Add risk score contribution
            $deepDiveScore = $deepDiveResults['risk_score'] ?? 0;
            if ($deepDiveScore > 60) {
                $this->addFraudIndicator(
                    'high_risk_lightspeed_behavior',
                    'lightspeed_deep_dive',
                    "Overall Lightspeed risk score: {$deepDiveScore}/100",
                    min(1.0, $deepDiveScore / 100),
                    ['risk_level' => $deepDiveResults['risk_level']]
                );
            }

        } catch (Exception $e) {
            error_log("Lightspeed deep-dive analysis failed: " . $e->getMessage());
        }
    }

    /**
     * Store analysis results in database
     */
    private function storeAnalysisResults(): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO fraud_analysis_results
                (staff_id, fraud_score, risk_level, indicators_count,
                 analysis_data, created_at)
                VALUES
                (:staff_id, :fraud_score, :risk_level, :indicators_count,
                 :analysis_data, NOW())
            ");
            $stmt->execute([
                'staff_id' => $this->analysisResults['staff_id'],
                'fraud_score' => $this->analysisResults['fraud_score'],
                'risk_level' => $this->analysisResults['risk_level'],
                'indicators_count' => count($this->analysisResults['fraud_indicators']),
                'analysis_data' => json_encode($this->analysisResults)
            ]);
        } catch (Exception $e) {
            error_log("Failed to store analysis results: " . $e->getMessage());
        }
    }
}
