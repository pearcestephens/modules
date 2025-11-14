<?php

/**
 * Behavioral Analytics Engine
 *
 * Real-time analysis of staff behavior patterns across all data sources to identify
 * fraudulent activities, theft, unauthorized discounts, and suspicious patterns.
 *
 * Integrates with:
 * - CIS Database (sales, inventory, transfers)
 * - Deputy Payroll/Scheduling
 * - Vend POS System
 * - Transaction Logs
 * - Camera Network
 *
 * @package FraudDetection
 * @version 1.0.0
 */

namespace FraudDetection;

use PDO;
use DateTime;
use DateInterval;
use Exception;

class BehavioralAnalyticsEngine
{
    private PDO $pdo;
    private array $config;
    private $logger;
    private array $storeCache = [];
    private array $staffCache = [];
    private array $riskScores = [];

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge($this->defaultConfig(), $config);
        $this->initializeLogger();
    }

    private function defaultConfig(): array
    {
        return [
            // Risk thresholds
            'high_risk_threshold' => 0.75,
            'medium_risk_threshold' => 0.50,
            'low_risk_threshold' => 0.25,

            // Analytics windows
            'daily_analysis_window' => 24,
            'weekly_analysis_window' => 7,
            'monthly_analysis_window' => 30,

            // Transaction anomaly detection
            'discount_threshold_percentage' => 15.0,
            'void_transaction_threshold' => 5,
            'refund_anomaly_threshold' => 3,

            // Inventory anomalies
            'shrinkage_alert_threshold' => 50,
            'after_hours_access_alert' => true,
            'unusual_stock_movement_threshold' => 20.0,

            // Staff patterns
            'time_theft_check_enabled' => true,
            'peer_comparison_enabled' => true,
            'repeat_offender_weight' => 2.5,

            // Camera integration
            'enable_camera_targeting' => true,
            'min_confidence_for_targeting' => 0.75,
            'tracking_duration_minutes' => 60,
            'alert_retention_days' => 30,
        ];
    }

    private function initializeLogger(): void
    {
        $logPath = __DIR__ . '/../../logs/behavioral-analytics.log';
        $this->logger = new class ($logPath) {
            private $path;

            public function __construct($path)
            {
                $this->path = $path;
                @mkdir(dirname($path), 0755, true);
            }

            public function log($level, $message, $context = [])
            {
                $timestamp = date('Y-m-d H:i:s');
                $contextStr = $context ? json_encode($context) : '';
                file_put_contents($this->path, "[$timestamp] [$level] $message $contextStr\n", FILE_APPEND);
            }

            public function info($message, $context = []) { $this->log('INFO', $message, $context); }
            public function warning($message, $context = []) { $this->log('WARNING', $message, $context); }
            public function error($message, $context = []) { $this->log('ERROR', $message, $context); }
        };
    }

    /**
     * Run comprehensive behavioral analysis for all staff
     * Returns array of staff members with risk profiles and recommendations
     */
    public function analyzeAllStaff(string $timeWindow = 'daily'): array
    {
        $this->logger->info("Starting comprehensive behavioral analysis", ['time_window' => $timeWindow]);

        try {
            $staffMembers = $this->getAllActiveStaff();
            $results = [];

            foreach ($staffMembers as $staff) {
                $analysis = $this->analyzeStaffMember($staff['id'], $timeWindow);
                if ($analysis['risk_score'] > $this->config['low_risk_threshold']) {
                    $results[] = $analysis;
                }
            }

            // Sort by risk score descending
            usort($results, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);

            $this->logger->info("Behavioral analysis complete", [
                'total_staff' => count($staffMembers),
                'flagged_count' => count($results),
                'highest_risk' => $results[0]['risk_score'] ?? 0
            ]);

            return $results;
        } catch (Exception $e) {
            $this->logger->error("Behavioral analysis failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Analyze individual staff member for fraudulent patterns
     */
    public function analyzeStaffMember(int $staffId, string $timeWindow = 'daily'): array
    {
        try {
            $staff = $this->getStaffMember($staffId);
            if (!$staff) {
                throw new Exception("Staff member not found: $staffId");
            }

            $windowDays = $this->getWindowDays($timeWindow);
            $startDate = (new DateTime())->sub(new DateInterval("P{$windowDays}D"));

            $analysis = [
                'staff_id' => $staffId,
                'staff_name' => $staff['name'],
                'store_id' => $staff['store_id'],
                'store_name' => $staff['store_name'],
                'analysis_period' => $timeWindow,
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'risk_factors' => [],
                'raw_scores' => [],
                'recommendations' => [],
            ];

            // Run all analytical modules
            $analysis['raw_scores']['discount_anomalies'] = $this->analyzeDiscountPatterns($staffId, $startDate);
            $analysis['raw_scores']['void_transactions'] = $this->analyzeVoidTransactions($staffId, $startDate);
            $analysis['raw_scores']['refund_patterns'] = $this->analyzeRefundPatterns($staffId, $startDate);
            $analysis['raw_scores']['inventory_anomalies'] = $this->analyzeInventoryAnomalies($staffId, $startDate);
            $analysis['raw_scores']['after_hours_access'] = $this->analyzeAfterHoursActivity($staffId, $startDate);
            $analysis['raw_scores']['time_theft'] = $this->analyzeTimeFraud($staffId, $startDate);
            $analysis['raw_scores']['peer_comparison'] = $this->compareToPeerGroup($staffId, $startDate);
            $analysis['raw_scores']['repeat_offender'] = $this->checkRepeatOffenderHistory($staffId);

            // Calculate composite risk score
            $analysis['risk_score'] = $this->calculateCompositeRisk($analysis['raw_scores']);
            $analysis['risk_level'] = $this->getRiskLevel($analysis['risk_score']);

            // Generate risk factors summary
            foreach ($analysis['raw_scores'] as $factor => $score) {
                if ($score > $this->config['low_risk_threshold']) {
                    $analysis['risk_factors'][] = [
                        'type' => $factor,
                        'score' => round($score, 3),
                        'severity' => $this->getRiskLevel($score),
                    ];
                }
            }

            // Generate recommendations
            $analysis['recommendations'] = $this->generateRecommendations($analysis);

            // Determine if camera targeting should be activated
            $analysis['should_target_cameras'] = $analysis['risk_score'] >= $this->config['min_confidence_for_targeting'];
            $analysis['camera_targeting_duration'] = $this->config['tracking_duration_minutes'];

            return $analysis;
        } catch (Exception $e) {
            $this->logger->error("Failed to analyze staff member $staffId: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Analyze discount patterns for suspicious activity
     */
    private function analyzeDiscountPatterns(int $staffId, DateTime $startDate): float
    {
        try {
            $sql = "
                SELECT
                    COUNT(*) as discount_count,
                    AVG(discount_percentage) as avg_discount,
                    MAX(discount_percentage) as max_discount,
                    SUM(discount_amount) as total_discount_value
                FROM sales_transactions
                WHERE staff_id = ?
                AND transaction_date >= ?
                AND discount_percentage > 0
                AND transaction_type = 'SALE'
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$staffId, $startDate->format('Y-m-d H:i:s')]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $discountCount = (int)$result['discount_count'];
            $avgDiscount = (float)$result['avg_discount'] ?? 0;
            $maxDiscount = (float)$result['max_discount'] ?? 0;

            // Get peer average for comparison
            $peerSql = "
                SELECT AVG(discount_percentage) as peer_avg_discount
                FROM sales_transactions
                WHERE transaction_date >= ?
                AND discount_percentage > 0
                AND transaction_type = 'SALE'
            ";
            $peerStmt = $this->pdo->prepare($peerSql);
            $peerStmt->execute([$startDate->format('Y-m-d H:i:s')]);
            $peerResult = $peerStmt->fetch(PDO::FETCH_ASSOC);
            $peerAvgDiscount = (float)$peerResult['peer_avg_discount'] ?? 0;

            // Calculate risk score
            $risk = 0;

            // High frequency of discounts above peer average
            if ($avgDiscount > ($peerAvgDiscount * 1.5)) {
                $risk += 0.3;
            }

            // Consistently high discount amounts
            if ($maxDiscount > $this->config['discount_threshold_percentage']) {
                $risk += 0.25;
            }

            // Excessive total discount value
            if ($discountCount > 20 && ($result['total_discount_value'] ?? 0) > 500) {
                $risk += 0.25;
            }

            // Frequent discounting pattern
            if ($discountCount > 30) {
                $risk += 0.2;
            }

            return min($risk, 1.0);
        } catch (Exception $e) {
            $this->logger->error("Discount analysis failed for staff $staffId: " . $e->getMessage());
            return 0.1; // Return low score on error
        }
    }

    /**
     * Analyze void transaction patterns
     */
    private function analyzeVoidTransactions(int $staffId, DateTime $startDate): float
    {
        try {
            $sql = "
                SELECT COUNT(*) as void_count, SUM(transaction_value) as void_value
                FROM sales_transactions
                WHERE staff_id = ?
                AND transaction_date >= ?
                AND status = 'VOID'
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$staffId, $startDate->format('Y-m-d H:i:s')]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $voidCount = (int)$result['void_count'];
            $voidValue = (float)$result['void_value'] ?? 0;

            // Get store average
            $storeAvgSql = "
                SELECT AVG(void_count) as avg_voids
                FROM (
                    SELECT COUNT(*) as void_count
                    FROM sales_transactions
                    WHERE transaction_date >= ?
                    AND status = 'VOID'
                    GROUP BY staff_id
                ) sub
            ";
            $storeAvgStmt = $this->pdo->prepare($storeAvgSql);
            $storeAvgStmt->execute([$startDate->format('Y-m-d H:i:s')]);
            $storeAvgResult = $storeAvgStmt->fetch(PDO::FETCH_ASSOC);
            $storeAvgVoids = (float)$storeAvgResult['avg_voids'] ?? 2;

            $risk = 0;

            // Significantly higher than average void rate
            if ($voidCount > ($storeAvgVoids * 2)) {
                $risk += 0.4;
            }

            // Excessive void count
            if ($voidCount >= $this->config['void_transaction_threshold']) {
                $risk += 0.3;
            }

            // High value voids (potential cash theft concealment)
            if ($voidValue > 300) {
                $risk += 0.3;
            }

            return min($risk, 1.0);
        } catch (Exception $e) {
            $this->logger->error("Void transaction analysis failed: " . $e->getMessage());
            return 0.05;
        }
    }

    /**
     * Analyze refund and return patterns
     */
    private function analyzeRefundPatterns(int $staffId, DateTime $startDate): float
    {
        try {
            $sql = "
                SELECT
                    COUNT(*) as refund_count,
                    SUM(refund_amount) as total_refunds,
                    AVG(refund_amount) as avg_refund
                FROM refunds
                WHERE staff_id = ?
                AND refund_date >= ?
                AND status = 'APPROVED'
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$staffId, $startDate->format('Y-m-d H:i:s')]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $refundCount = (int)$result['refund_count'];
            $totalRefunds = (float)$result['total_refunds'] ?? 0;
            $avgRefund = (float)$result['avg_refund'] ?? 0;

            // Get store average for comparison
            $storeAvgSql = "
                SELECT AVG(refund_count) as avg_count, AVG(total_refunds) as avg_value
                FROM (
                    SELECT COUNT(*) as refund_count, SUM(refund_amount) as total_refunds
                    FROM refunds
                    WHERE refund_date >= ?
                    AND status = 'APPROVED'
                    GROUP BY staff_id
                ) sub
            ";
            $storeAvgStmt = $this->pdo->prepare($storeAvgSql);
            $storeAvgStmt->execute([$startDate->format('Y-m-d H:i:s')]);
            $storeAvgResult = $storeAvgStmt->fetch(PDO::FETCH_ASSOC);

            $storeAvgCount = (float)$storeAvgResult['avg_count'] ?? 2;
            $storeAvgValue = (float)$storeAvgResult['avg_value'] ?? 50;

            $risk = 0;

            // Significantly higher than store average
            if ($refundCount > ($storeAvgCount * 2)) {
                $risk += 0.35;
            }

            // Excessive refund frequency
            if ($refundCount >= $this->config['refund_anomaly_threshold']) {
                $risk += 0.3;
            }

            // High total refund value
            if ($totalRefunds > ($storeAvgValue * 3)) {
                $risk += 0.25;
            }

            // Pattern of refunds without clear reason
            $sqlReasons = "
                SELECT COUNT(*) as no_reason_count
                FROM refunds
                WHERE staff_id = ?
                AND refund_date >= ?
                AND (reason IS NULL OR reason = '' OR reason = 'OTHER')
                AND status = 'APPROVED'
            ";
            $reasonStmt = $this->pdo->prepare($sqlReasons);
            $reasonStmt->execute([$staffId, $startDate->format('Y-m-d H:i:s')]);
            $reasonResult = $reasonStmt->fetch(PDO::FETCH_ASSOC);

            if ((int)$reasonResult['no_reason_count'] > ($refundCount * 0.5)) {
                $risk += 0.15;
            }

            return min($risk, 1.0);
        } catch (Exception $e) {
            $this->logger->error("Refund analysis failed: " . $e->getMessage());
            return 0.05;
        }
    }

    /**
     * Analyze inventory anomalies correlated with staff
     */
    private function analyzeInventoryAnomalies(int $staffId, DateTime $startDate): float
    {
        try {
            // Check for unusual inventory movements during staff shifts
            $sql = "
                SELECT
                    COUNT(*) as movement_count,
                    SUM(quantity) as total_movement,
                    AVG(quantity) as avg_movement
                FROM inventory_movements
                WHERE staff_id = ?
                AND created_at >= ?
                AND movement_type IN ('TRANSFER_OUT', 'ADJUSTMENT_DOWN', 'DAMAGE', 'LOSS')
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$staffId, $startDate->format('Y-m-d H:i:s')]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $movementCount = (int)$result['movement_count'];
            $totalMovement = (int)$result['total_movement'] ?? 0;

            // Get store shrinkage rate
            $shrinkageSql = "
                SELECT SUM(quantity) as shrink_qty, COUNT(*) as shrink_count
                FROM inventory_movements
                WHERE created_at >= ?
                AND movement_type IN ('ADJUSTMENT_DOWN', 'DAMAGE', 'LOSS')
            ";
            $shrinkageStmt = $this->pdo->prepare($shrinkageSql);
            $shrinkageStmt->execute([$startDate->format('Y-m-d H:i:s')]);
            $shrinkageResult = $shrinkageStmt->fetch(PDO::FETCH_ASSOC);

            $storeShrinkage = (int)$shrinkageResult['shrink_qty'] ?? 0;
            $storeMovementCount = (int)$shrinkageResult['shrink_count'] ?? 1;

            $risk = 0;

            // Staff involved in disproportionate shrinkage
            if ($storeMovementCount > 0) {
                $staffShrinkageRatio = $movementCount / $storeMovementCount;
                if ($staffShrinkageRatio > 0.3) { // Staff involved in >30% of shrinkage
                    $risk += 0.35;
                }
            }

            // Large quantity movements
            if ($totalMovement > $this->config['shrinkage_alert_threshold']) {
                $risk += 0.3;
            }

            // Pattern of movement without documentation
            $undocSql = "
                SELECT COUNT(*) as undoc_count
                FROM inventory_movements
                WHERE staff_id = ?
                AND created_at >= ?
                AND (reason IS NULL OR reason = '')
                AND movement_type IN ('ADJUSTMENT_DOWN', 'LOSS')
            ";
            $undocStmt = $this->pdo->prepare($undocSql);
            $undocStmt->execute([$staffId, $startDate->format('Y-m-d H:i:s')]);
            $undocResult = $undocStmt->fetch(PDO::FETCH_ASSOC);

            if ((int)$undocResult['undoc_count'] > 5) {
                $risk += 0.25;
            }

            return min($risk, 1.0);
        } catch (Exception $e) {
            $this->logger->error("Inventory analysis failed: " . $e->getMessage());
            return 0.05;
        }
    }

    /**
     * Analyze after-hours access and unusual activity patterns
     */
    private function analyzeAfterHoursActivity(int $staffId, DateTime $startDate): float
    {
        if (!$this->config['after_hours_access_alert']) {
            return 0;
        }

        try {
            // Check for transactions outside normal business hours
            $sql = "
                SELECT COUNT(*) as after_hours_count
                FROM sales_transactions
                WHERE staff_id = ?
                AND transaction_date >= ?
                AND (HOUR(transaction_date) < 7 OR HOUR(transaction_date) > 20)
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$staffId, $startDate->format('Y-m-d H:i:s')]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $afterHoursCount = (int)$result['after_hours_count'];

            // Check access logs
            $accessSql = "
                SELECT COUNT(*) as access_count
                FROM building_access_log
                WHERE staff_id = ?
                AND access_time >= ?
                AND (HOUR(access_time) < 7 OR HOUR(access_time) > 20)
                AND access_type IN ('ENTRY', 'EXIT')
            ";

            $accessStmt = $this->pdo->prepare($accessSql);
            $accessStmt->execute([$staffId, $startDate->format('Y-m-d H:i:s')]);
            $accessResult = $accessStmt->fetch(PDO::FETCH_ASSOC);

            $afterHoursAccess = (int)$accessResult['access_count'];

            $risk = 0;

            // After-hours transactions
            if ($afterHoursCount > 3) {
                $risk += 0.3;
            }

            // Unauthorized after-hours access
            if ($afterHoursAccess > 5) {
                $risk += 0.35;
            }

            // Combination of both
            if ($afterHoursCount > 2 && $afterHoursAccess > 2) {
                $risk += 0.2;
            }

            return min($risk, 1.0);
        } catch (Exception $e) {
            $this->logger->error("After-hours activity analysis failed: " . $e->getMessage());
            return 0.02;
        }
    }

    /**
     * Analyze time theft patterns (Deputy integration)
     */
    private function analyzeTimeFraud(int $staffId, DateTime $startDate): float
    {
        if (!$this->config['time_theft_check_enabled']) {
            return 0;
        }

        try {
            // Get Deputy time clock data
            $sql = "
                SELECT
                    COUNT(*) as clock_count,
                    SUM(hours_worked) as total_hours,
                    AVG(hours_worked) as avg_daily_hours,
                    SUM(CASE WHEN punch_correction = 1 THEN 1 ELSE 0 END) as corrections
                FROM deputy_timesheets
                WHERE staff_id = ?
                AND timesheet_date >= ?
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$staffId, $startDate->format('Y-m-d')]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $clockCount = (int)$result['clock_count'];
            $totalHours = (float)$result['total_hours'] ?? 0;
            $avgDailyHours = (float)$result['avg_daily_hours'] ?? 0;
            $corrections = (int)$result['corrections'] ?? 0;

            // Get store average
            $storeAvgSql = "
                SELECT AVG(hours_worked) as store_avg_hours, AVG(corrections) as avg_corrections
                FROM deputy_timesheets
                WHERE timesheet_date >= ?
            ";
            $storeAvgStmt = $this->pdo->prepare($storeAvgSql);
            $storeAvgStmt->execute([$startDate->format('Y-m-d')]);
            $storeAvgResult = $storeAvgStmt->fetch(PDO::FETCH_ASSOC);

            $storeAvgHours = (float)$storeAvgResult['store_avg_hours'] ?? 8;
            $storeAvgCorrections = (float)$storeAvgResult['avg_corrections'] ?? 0.5;

            $risk = 0;

            // Excessive corrections/punch modifications
            if ($corrections > ($storeAvgCorrections * 3)) {
                $risk += 0.3;
            }

            // Inconsistent hours pattern
            if ($clockCount > 0) {
                $variance = abs($avgDailyHours - $storeAvgHours);
                if ($variance > 2) {
                    $risk += 0.2;
                }
            }

            // Hours don't match transaction activity
            $txnSql = "
                SELECT COUNT(*) as tx_count
                FROM sales_transactions
                WHERE staff_id = ?
                AND transaction_date >= ?
            ";
            $txnStmt = $this->pdo->prepare($txnSql);
            $txnStmt->execute([$staffId, $startDate->format('Y-m-d H:i:s')]);
            $txnResult = $txnStmt->fetch(PDO::FETCH_ASSOC);
            $txnCount = (int)$txnResult['tx_count'];

            if ($totalHours > 0 && $txnCount < 5) { // High hours, low transaction activity
                $risk += 0.25;
            }

            return min($risk, 1.0);
        } catch (Exception $e) {
            $this->logger->error("Time fraud analysis failed: " . $e->getMessage());
            return 0.02;
        }
    }

    /**
     * Compare staff member to peer group for anomalies
     */
    private function compareToPeerGroup(int $staffId, DateTime $startDate): float
    {
        if (!$this->config['peer_comparison_enabled']) {
            return 0;
        }

        try {
            $staff = $this->getStaffMember($staffId);
            $storeId = $staff['store_id'];

            // Get peer transaction metrics
            $peerSql = "
                SELECT
                    AVG(t.transaction_value) as peer_avg_transaction,
                    AVG(CAST(d.amount as DECIMAL)) as peer_avg_discount,
                    COUNT(DISTINCT t.staff_id) as peer_count
                FROM sales_transactions t
                LEFT JOIN discounts d ON t.id = d.transaction_id
                WHERE t.store_id = ?
                AND t.transaction_date >= ?
                AND t.staff_id != ?
            ";

            $peerStmt = $this->pdo->prepare($peerSql);
            $peerStmt->execute([$storeId, $startDate->format('Y-m-d H:i:s'), $staffId]);
            $peerResult = $peerStmt->fetch(PDO::FETCH_ASSOC);

            // Get this staff's metrics
            $staffSql = "
                SELECT
                    AVG(transaction_value) as staff_avg_transaction,
                    AVG(CAST(d.amount as DECIMAL)) as staff_avg_discount,
                    COUNT(*) as transaction_count
                FROM sales_transactions t
                LEFT JOIN discounts d ON t.id = d.transaction_id
                WHERE t.staff_id = ?
                AND t.transaction_date >= ?
            ";

            $staffStmt = $this->pdo->prepare($staffSql);
            $staffStmt->execute([$staffId, $startDate->format('Y-m-d H:i:s')]);
            $staffResult = $staffStmt->fetch(PDO::FETCH_ASSOC);

            $peerAvgTx = (float)$peerResult['peer_avg_transaction'] ?? 50;
            $peerAvgDiscount = (float)$peerResult['peer_avg_discount'] ?? 5;
            $staffAvgTx = (float)$staffResult['staff_avg_transaction'] ?? 50;
            $staffAvgDiscount = (float)$staffResult['staff_avg_discount'] ?? 5;

            $risk = 0;

            // Transaction value significantly lower (might indicate underringing)
            if ($staffAvgTx < ($peerAvgTx * 0.7)) {
                $risk += 0.2;
            }

            // Discount amount significantly higher
            if ($staffAvgDiscount > ($peerAvgDiscount * 1.5)) {
                $risk += 0.25;
            }

            // Transaction count much lower (might be on fewer shifts)
            if ((int)$staffResult['transaction_count'] < 10) {
                // Not enough data for comparison
                $risk += 0.05;
            }

            return min($risk, 1.0);
        } catch (Exception $e) {
            $this->logger->error("Peer comparison analysis failed: " . $e->getMessage());
            return 0.05;
        }
    }

    /**
     * Check for repeat offender history
     */
    private function checkRepeatOffenderHistory(int $staffId): float
    {
        try {
            $sql = "
                SELECT
                    COUNT(*) as incident_count,
                    COUNT(DISTINCT DATE(incident_date)) as days_with_incidents
                FROM fraud_incidents
                WHERE staff_id = ?
                AND incident_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                AND status != 'DISMISSED'
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$staffId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $incidentCount = (int)$result['incident_count'];
            $daysWithIncidents = (int)$result['days_with_incidents'];

            $risk = 0;

            // Repeat incidents
            if ($incidentCount > 0) {
                $risk += 0.3 * min(($incidentCount / 5), 1.0); // Up to 0.3 for 5+ incidents
            }

            // Pattern across multiple days
            if ($daysWithIncidents > 3) {
                $risk += min(($daysWithIncidents / 10) * 0.3, 0.3);
            }

            // Apply repeat offender weight multiplier
            if ($incidentCount > 0) {
                $risk *= $this->config['repeat_offender_weight'];
            }

            return min($risk, 1.0);
        } catch (Exception $e) {
            $this->logger->error("Repeat offender check failed: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Calculate composite risk score from individual factors
     */
    private function calculateCompositeRisk(array $scores): float
    {
        $weights = [
            'discount_anomalies' => 0.15,
            'void_transactions' => 0.18,
            'refund_patterns' => 0.15,
            'inventory_anomalies' => 0.20,
            'after_hours_access' => 0.12,
            'time_theft' => 0.10,
            'peer_comparison' => 0.05,
            'repeat_offender' => 0.05,
        ];

        $compositeScore = 0;
        $totalWeight = 0;

        foreach ($scores as $factor => $score) {
            $weight = $weights[$factor] ?? 0;
            $compositeScore += ($score * $weight);
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $compositeScore / $totalWeight : 0;
    }

    /**
     * Generate recommendations based on analysis
     */
    private function generateRecommendations(array $analysis): array
    {
        $recommendations = [];
        $riskFactors = $analysis['risk_factors'];

        // Sort by severity
        usort($riskFactors, fn($a, $b) => $b['score'] <=> $a['score']);

        foreach ($riskFactors as $factor) {
            switch ($factor['type']) {
                case 'discount_anomalies':
                    $recommendations[] = [
                        'priority' => 'HIGH',
                        'action' => 'Monitor Discount Usage',
                        'description' => 'Staff member applying discounts at significantly higher rate than peers. Recommend manager review discount approval procedures.',
                        'severity_score' => $factor['score'],
                    ];
                    break;

                case 'void_transactions':
                    $recommendations[] = [
                        'priority' => 'CRITICAL',
                        'action' => 'Investigate Void Transactions',
                        'description' => 'Excessive void transaction pattern detected. Recommend immediate review of voided sales and camera footage.',
                        'severity_score' => $factor['score'],
                    ];
                    break;

                case 'refund_patterns':
                    $recommendations[] = [
                        'priority' => 'HIGH',
                        'action' => 'Review Refund Approvals',
                        'description' => 'Unusual refund pattern with potential unauthorized approvals. Recommend audit of refund receipts and inventory reconciliation.',
                        'severity_score' => $factor['score'],
                    ];
                    break;

                case 'inventory_anomalies':
                    $recommendations[] = [
                        'priority' => 'CRITICAL',
                        'action' => 'Conduct Inventory Investigation',
                        'description' => 'Staff member correlated with significant inventory shrinkage. Recommend physical inventory count and review of movement documentation.',
                        'severity_score' => $factor['score'],
                    ];
                    break;

                case 'after_hours_access':
                    $recommendations[] = [
                        'priority' => 'HIGH',
                        'action' => 'Review After-Hours Access',
                        'description' => 'Unusual after-hours access or transactions detected. Recommend review of building access logs and camera footage.',
                        'severity_score' => $factor['score'],
                    ];
                    break;

                case 'time_theft':
                    $recommendations[] = [
                        'priority' => 'MEDIUM',
                        'action' => 'Verify Time Records',
                        'description' => 'Possible time fraud detected through Deputy records. Recommend review of clock-in/out patterns and correlation with sales activity.',
                        'severity_score' => $factor['score'],
                    ];
                    break;

                case 'peer_comparison':
                    $recommendations[] = [
                        'priority' => 'MEDIUM',
                        'action' => 'Compare Performance Metrics',
                        'description' => 'Performance metrics deviate significantly from peer group. Recommend coaching or additional training.',
                        'severity_score' => $factor['score'],
                    ];
                    break;

                case 'repeat_offender':
                    $recommendations[] = [
                        'priority' => 'CRITICAL',
                        'action' => 'Escalate to Management',
                        'description' => 'Pattern of repeated incidents. Recommend immediate management review and potential disciplinary action.',
                        'severity_score' => $factor['score'],
                    ];
                    break;
            }
        }

        // Sort by priority
        $priorityOrder = ['CRITICAL' => 0, 'HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3];
        usort($recommendations, fn($a, $b) =>
            ($priorityOrder[$a['priority']] ?? 99) <=> ($priorityOrder[$b['priority']] ?? 99)
        );

        return $recommendations;
    }

    /**
     * Get risk level label
     */
    private function getRiskLevel(float $score): string
    {
        if ($score >= $this->config['high_risk_threshold']) {
            return 'CRITICAL';
        } elseif ($score >= $this->config['medium_risk_threshold']) {
            return 'HIGH';
        } elseif ($score >= $this->config['low_risk_threshold']) {
            return 'MEDIUM';
        }
        return 'LOW';
    }

    /**
     * Helper method: get staff member
     */
    private function getStaffMember(int $staffId): ?array
    {
        if (isset($this->staffCache[$staffId])) {
            return $this->staffCache[$staffId];
        }

        $sql = "
            SELECT s.id, s.name, s.email, s.store_id, st.name as store_name
            FROM staff s
            JOIN stores st ON s.store_id = st.id
            WHERE s.id = ? AND s.status = 'ACTIVE'
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$staffId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $this->staffCache[$staffId] = $result;
        }

        return $result;
    }

    /**
     * Get all active staff members
     */
    private function getAllActiveStaff(): array
    {
        $sql = "
            SELECT s.id, s.name, s.email, s.store_id, st.name as store_name
            FROM staff s
            JOIN stores st ON s.store_id = st.id
            WHERE s.status = 'ACTIVE'
            ORDER BY s.store_id, s.name
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get window days from time window string
     */
    private function getWindowDays(string $timeWindow): int
    {
        return match($timeWindow) {
            'daily' => 1,
            'weekly' => 7,
            'monthly' => 30,
            'quarterly' => 90,
            default => 7,
        };
    }

    /**
     * Save analysis results for auditing and trending
     */
    public function saveAnalysisResults(array $analysis): bool
    {
        try {
            $sql = "
                INSERT INTO behavioral_analysis_results
                (staff_id, analysis_period, risk_score, risk_level, risk_factors,
                 recommendations, camera_targeting, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    risk_score = VALUES(risk_score),
                    risk_level = VALUES(risk_level),
                    risk_factors = VALUES(risk_factors),
                    recommendations = VALUES(recommendations),
                    camera_targeting = VALUES(camera_targeting),
                    created_at = NOW()
            ";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                $analysis['staff_id'],
                $analysis['analysis_period'],
                $analysis['risk_score'],
                $analysis['risk_level'],
                json_encode($analysis['risk_factors']),
                json_encode($analysis['recommendations']),
                $analysis['should_target_cameras'] ? 1 : 0,
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to save analysis results: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get historical analysis for trending
     */
    public function getHistoricalAnalysis(int $staffId, int $days = 30): array
    {
        try {
            $sql = "
                SELECT created_at, risk_score, risk_level
                FROM behavioral_analysis_results
                WHERE staff_id = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY created_at ASC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$staffId, $days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Failed to get historical analysis: " . $e->getMessage());
            return [];
        }
    }
}
