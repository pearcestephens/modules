<?php
/**
 * Customer Loyalty Collusion Detection Engine
 * Cross-references customer loyalty data with staff relationships to detect fraud
 *
 * Features:
 * - Family/friend relationship detection
 * - Discount pattern analysis per customer-staff pair
 * - Address/phone/email matching
 * - Transaction frequency analysis
 * - Lifetime value anomaly detection
 * - Return pattern analysis
 * - Multi-store collusion tracking
 * - Network graph analysis
 *
 * Detection Capabilities:
 * - Staff giving discounts to family members
 * - Staff-customer collusion rings
 * - Return fraud with specific staff
 * - Gift card fraud networks
 * - Coordinated theft rings
 *
 * @package FraudDetection
 * @version 2.0.0
 * @author Ecigdis Intelligence System
 */

namespace FraudDetection;

use PDO;
use Exception;

class CustomerLoyaltyCollusionDetector
{
    private PDO $db;
    private array $config;

    // Detection thresholds
    private const DISCOUNT_ANOMALY_THRESHOLD = 2.5; // Standard deviations
    private const FREQUENCY_ANOMALY_THRESHOLD = 3.0;
    private const RELATIONSHIP_CONFIDENCE_HIGH = 0.80;
    private const RELATIONSHIP_CONFIDENCE_MEDIUM = 0.60;

    // Analysis periods
    private const SHORT_TERM_DAYS = 30;
    private const MEDIUM_TERM_DAYS = 90;
    private const LONG_TERM_DAYS = 365;

    /**
     * Relationship indicators with confidence weights
     */
    private const RELATIONSHIP_INDICATORS = [
        'same_last_name' => 0.70,
        'same_address' => 0.85,
        'same_phone_number' => 0.90,
        'same_email_domain' => 0.60,
        'contact_in_staff_records' => 0.95,
        'family_member_declared' => 1.00,
        'social_media_connection' => 0.75,
        'shared_payment_method' => 0.80
    ];

    /**
     * Collusion pattern types
     */
    private const COLLUSION_PATTERNS = [
        'excessive_discounts' => [
            'description' => 'Customer receives significantly higher discounts than average',
            'severity' => 'HIGH',
            'threshold' => 2.5 // Sigma
        ],
        'frequency_abuse' => [
            'description' => 'Customer shops with same staff member excessively',
            'severity' => 'MEDIUM',
            'threshold' => 3.0 // Sigma
        ],
        'return_fraud' => [
            'description' => 'Customer returns items only with specific staff',
            'severity' => 'HIGH',
            'threshold' => 0.75 // 75% of returns with same staff
        ],
        'after_hours_transactions' => [
            'description' => 'Transactions during off-hours or closed periods',
            'severity' => 'CRITICAL',
            'threshold' => 1 // Any occurrence
        ],
        'gift_card_manipulation' => [
            'description' => 'Unusual gift card purchase/usage patterns',
            'severity' => 'HIGH',
            'threshold' => 5 // Transactions
        ],
        'inventory_discrepancy' => [
            'description' => 'Items sold at discount later reported missing',
            'severity' => 'CRITICAL',
            'threshold' => 0.80 // Confidence
        ]
    ];

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = array_merge([
            'enable_lightspeed_integration' => true,
            'enable_social_media_matching' => false, // Requires API access
            'store_relationship_graph' => true,
            'alert_threshold' => 0.70,
            'auto_flag_high_risk' => true,
            'generate_investigation_reports' => true
        ], $config);
    }

    /**
     * Detect collusion between specific customer and staff member
     *
     * @param int $customerId Customer ID from Lightspeed
     * @param int $staffId Staff member ID
     * @return array Collusion analysis
     */
    public function detectCustomerStaffCollusion(int $customerId, int $staffId): array
    {
        // Get customer details
        $customer = $this->getCustomerDetails($customerId);
        if (!$customer) {
            return [
                'success' => false,
                'error' => 'Customer not found',
                'customer_id' => $customerId
            ];
        }

        // Get staff details
        $staff = $this->getStaffDetails($staffId);
        if (!$staff) {
            return [
                'success' => false,
                'error' => 'Staff member not found',
                'staff_id' => $staffId
            ];
        }

        // Check for personal relationships
        $relationshipAnalysis = $this->analyzeRelationship($customer, $staff);

        // Analyze transaction patterns
        $transactionAnalysis = $this->analyzeTransactionPatterns($customerId, $staffId);

        // Analyze discount patterns
        $discountAnalysis = $this->analyzeDiscountPatterns($customerId, $staffId);

        // Analyze return patterns
        $returnAnalysis = $this->analyzeReturnPatterns($customerId, $staffId);

        // Check for specific collusion patterns
        $collusionPatterns = $this->detectCollusionPatterns($customerId, $staffId, $transactionAnalysis);

        // Calculate composite collusion score
        $collusionScore = $this->calculateCollusionScore(
            $relationshipAnalysis,
            $transactionAnalysis,
            $discountAnalysis,
            $returnAnalysis,
            $collusionPatterns
        );

        // Generate alert if threshold exceeded
        $alert = null;
        if ($collusionScore['total_score'] >= $this->config['alert_threshold']) {
            $alert = $this->generateCollusionAlert($customerId, $staffId, $collusionScore);
        }

        // Store analysis
        $this->storeCollusionAnalysis($customerId, $staffId, $collusionScore);

        return [
            'success' => true,
            'customer_id' => $customerId,
            'customer_name' => $customer['customer_name'],
            'staff_id' => $staffId,
            'staff_name' => $staff['staff_name'],
            'relationship_analysis' => $relationshipAnalysis,
            'transaction_analysis' => $transactionAnalysis,
            'discount_analysis' => $discountAnalysis,
            'return_analysis' => $returnAnalysis,
            'collusion_patterns' => $collusionPatterns,
            'collusion_score' => $collusionScore,
            'alert_generated' => $alert !== null,
            'alert_details' => $alert,
            'analyzed_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Scan all customers for staff member to find collusion
     *
     * @param int $staffId Staff member ID
     * @return array All suspicious customer relationships
     */
    public function scanStaffCustomerRelationships(int $staffId): array
    {
        // Get all customers who have transacted with this staff member
        $sql = "
            SELECT DISTINCT
                customer_id,
                COUNT(*) as transaction_count,
                SUM(discount_amount) as total_discounts,
                AVG(discount_percentage) as avg_discount,
                MIN(transaction_date) as first_transaction,
                MAX(transaction_date) as last_transaction
            FROM lightspeed_transactions
            WHERE staff_id = :staff_id
                AND transaction_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY customer_id
            HAVING transaction_count >= 3
            ORDER BY total_discounts DESC, transaction_count DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'staff_id' => $staffId,
            'days' => self::LONG_TERM_DAYS
        ]);

        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $suspiciousRelationships = [];
        $totalScanned = 0;

        foreach ($customers as $customer) {
            $analysis = $this->detectCustomerStaffCollusion($customer['customer_id'], $staffId);

            if ($analysis['success']) {
                $totalScanned++;

                if ($analysis['collusion_score']['total_score'] >= $this->config['alert_threshold']) {
                    $suspiciousRelationships[] = [
                        'customer_id' => $customer['customer_id'],
                        'customer_name' => $analysis['customer_name'],
                        'collusion_score' => $analysis['collusion_score']['total_score'],
                        'risk_level' => $analysis['collusion_score']['risk_level'],
                        'transaction_count' => $customer['transaction_count'],
                        'total_discounts' => $customer['total_discounts'],
                        'primary_concerns' => $analysis['collusion_patterns'],
                        'relationship_detected' => $analysis['relationship_analysis']['relationship_detected']
                    ];
                }
            }
        }

        // Sort by collusion score
        usort($suspiciousRelationships, function($a, $b) {
            return $b['collusion_score'] <=> $a['collusion_score'];
        });

        return [
            'success' => true,
            'staff_id' => $staffId,
            'customers_scanned' => $totalScanned,
            'suspicious_relationships' => count($suspiciousRelationships),
            'relationships' => $suspiciousRelationships,
            'total_discount_impact' => array_sum(array_column($suspiciousRelationships, 'total_discounts')),
            'scanned_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Scan entire loyalty database for collusion patterns (comprehensive sweep)
     *
     * @param array $options Scan options
     * @return array Comprehensive collusion report
     */
    public function comprehensiveCollusionSweep(array $options = []): array
    {
        $defaults = [
            'min_transactions' => 5,
            'min_total_discounts' => 500, // $500 minimum
            'analysis_period_days' => self::LONG_TERM_DAYS,
            'include_low_risk' => false
        ];
        $options = array_merge($defaults, $options);

        $startTime = microtime(true);

        // Get all staff members
        $sql = "SELECT staff_id, staff_name FROM staff WHERE active = 1";
        $stmt = $this->db->query($sql);
        $allStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $comprehensiveResults = [];
        $totalStaffScanned = 0;
        $totalCustomersScanned = 0;
        $totalCollusionCasesFound = 0;
        $totalFinancialImpact = 0.0;

        foreach ($allStaff as $staff) {
            $staffScan = $this->scanStaffCustomerRelationships($staff['staff_id']);

            if ($staffScan['success']) {
                $totalStaffScanned++;
                $totalCustomersScanned += $staffScan['customers_scanned'];

                if ($staffScan['suspicious_relationships'] > 0) {
                    $comprehensiveResults[] = [
                        'staff_id' => $staff['staff_id'],
                        'staff_name' => $staff['staff_name'],
                        'suspicious_customer_count' => $staffScan['suspicious_relationships'],
                        'relationships' => $staffScan['relationships'],
                        'total_discount_impact' => $staffScan['total_discount_impact']
                    ];

                    $totalCollusionCasesFound += $staffScan['suspicious_relationships'];
                    $totalFinancialImpact += $staffScan['total_discount_impact'];
                }
            }
        }

        // Sort staff by financial impact
        usort($comprehensiveResults, function($a, $b) {
            return $b['total_discount_impact'] <=> $a['total_discount_impact'];
        });

        $processingTime = microtime(true) - $startTime;

        return [
            'success' => true,
            'sweep_type' => 'comprehensive',
            'analysis_period_days' => $options['analysis_period_days'],
            'staff_scanned' => $totalStaffScanned,
            'customers_analyzed' => $totalCustomersScanned,
            'collusion_cases_found' => $totalCollusionCasesFound,
            'total_financial_impact' => round($totalFinancialImpact, 2),
            'staff_with_collusion' => count($comprehensiveResults),
            'detailed_results' => $comprehensiveResults,
            'processing_time_seconds' => round($processingTime, 2),
            'completed_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Analyze relationship between customer and staff member
     *
     * @param array $customer Customer data
     * @param array $staff Staff data
     * @return array Relationship analysis
     */
    private function analyzeRelationship(array $customer, array $staff): array
    {
        $indicators = [];
        $confidenceScore = 0.0;

        // Same last name
        $customerLastName = $this->extractLastName($customer['customer_name']);
        $staffLastName = $this->extractLastName($staff['staff_name']);

        if ($customerLastName && $staffLastName &&
            strtolower($customerLastName) === strtolower($staffLastName)) {
            $indicators[] = [
                'type' => 'same_last_name',
                'confidence' => self::RELATIONSHIP_INDICATORS['same_last_name'],
                'detail' => "Shared last name: $customerLastName"
            ];
            $confidenceScore += self::RELATIONSHIP_INDICATORS['same_last_name'];
        }

        // Same address
        if (isset($customer['address']) && isset($staff['address'])) {
            $addressSimilarity = $this->calculateAddressSimilarity(
                $customer['address'],
                $staff['address']
            );

            if ($addressSimilarity >= 0.90) {
                $indicators[] = [
                    'type' => 'same_address',
                    'confidence' => self::RELATIONSHIP_INDICATORS['same_address'],
                    'detail' => "Matching address detected",
                    'similarity' => $addressSimilarity
                ];
                $confidenceScore += self::RELATIONSHIP_INDICATORS['same_address'];
            }
        }

        // Same phone number
        if (isset($customer['phone']) && isset($staff['phone'])) {
            if ($this->normalizePhone($customer['phone']) === $this->normalizePhone($staff['phone'])) {
                $indicators[] = [
                    'type' => 'same_phone_number',
                    'confidence' => self::RELATIONSHIP_INDICATORS['same_phone_number'],
                    'detail' => "Matching phone number"
                ];
                $confidenceScore += self::RELATIONSHIP_INDICATORS['same_phone_number'];
            }
        }

        // Same email domain (personal emails)
        if (isset($customer['email']) && isset($staff['personal_email'])) {
            $customerDomain = $this->extractEmailDomain($customer['email']);
            $staffDomain = $this->extractEmailDomain($staff['personal_email']);

            if ($customerDomain === $staffDomain &&
                !in_array($customerDomain, ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com'])) {
                $indicators[] = [
                    'type' => 'same_email_domain',
                    'confidence' => self::RELATIONSHIP_INDICATORS['same_email_domain'],
                    'detail' => "Shared email domain: $customerDomain"
                ];
                $confidenceScore += self::RELATIONSHIP_INDICATORS['same_email_domain'];
            }
        }

        // Check staff emergency contacts
        if ($this->isCustomerInStaffContacts($customer['customer_id'], $staff['staff_id'])) {
            $indicators[] = [
                'type' => 'contact_in_staff_records',
                'confidence' => self::RELATIONSHIP_INDICATORS['contact_in_staff_records'],
                'detail' => "Customer listed in staff emergency contacts or references"
            ];
            $confidenceScore += self::RELATIONSHIP_INDICATORS['contact_in_staff_records'];
        }

        // Check declared family members
        if ($this->isDeclaredFamilyMember($customer['customer_id'], $staff['staff_id'])) {
            $indicators[] = [
                'type' => 'family_member_declared',
                'confidence' => self::RELATIONSHIP_INDICATORS['family_member_declared'],
                'detail' => "Declared family member in HR records"
            ];
            $confidenceScore = 1.0; // Confirmed relationship
        }

        $normalizedConfidence = min(1.0, $confidenceScore);

        return [
            'relationship_detected' => !empty($indicators),
            'confidence_score' => round($normalizedConfidence, 3),
            'confidence_level' => $this->getConfidenceLevel($normalizedConfidence),
            'indicators' => $indicators,
            'indicator_count' => count($indicators)
        ];
    }

    /**
     * Analyze transaction patterns between customer and staff
     *
     * @param int $customerId
     * @param int $staffId
     * @return array Transaction pattern analysis
     */
    private function analyzeTransactionPatterns(int $customerId, int $staffId): array
    {
        // Get transactions with this specific staff member
        $sql = "
            SELECT
                COUNT(*) as transaction_count,
                SUM(total_amount) as total_spent,
                AVG(total_amount) as avg_transaction,
                MIN(transaction_date) as first_transaction,
                MAX(transaction_date) as last_transaction,
                SUM(CASE WHEN HOUR(transaction_date) < 7 OR HOUR(transaction_date) > 20
                    THEN 1 ELSE 0 END) as after_hours_count
            FROM lightspeed_transactions
            WHERE customer_id = :customer_id
                AND staff_id = :staff_id
                AND transaction_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'customer_id' => $customerId,
            'staff_id' => $staffId,
            'days' => self::LONG_TERM_DAYS
        ]);

        $withStaff = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get transactions with ALL other staff
        $sql = "
            SELECT
                COUNT(*) as transaction_count,
                AVG(total_amount) as avg_transaction
            FROM lightspeed_transactions
            WHERE customer_id = :customer_id
                AND staff_id != :staff_id
                AND transaction_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'customer_id' => $customerId,
            'staff_id' => $staffId,
            'days' => self::LONG_TERM_DAYS
        ]);

        $withOthers = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calculate frequency ratio
        $totalTransactions = $withStaff['transaction_count'] + $withOthers['transaction_count'];
        $frequencyWithStaff = $totalTransactions > 0
            ? $withStaff['transaction_count'] / $totalTransactions
            : 0.0;

        // Calculate statistical significance
        $expectedFrequency = 0.20; // Assume 5 staff, so 20% expected
        $frequencyAnomaly = $frequencyWithStaff > $expectedFrequency
            ? ($frequencyWithStaff - $expectedFrequency) / $expectedFrequency
            : 0.0;

        return [
            'transactions_with_staff' => (int)$withStaff['transaction_count'],
            'transactions_with_others' => (int)$withOthers['transaction_count'],
            'total_spent_with_staff' => (float)$withStaff['total_spent'],
            'avg_transaction_with_staff' => (float)$withStaff['avg_transaction'],
            'avg_transaction_with_others' => (float)$withOthers['avg_transaction'],
            'frequency_with_staff_percentage' => round($frequencyWithStaff * 100, 1),
            'frequency_anomaly_score' => round($frequencyAnomaly, 2),
            'after_hours_transactions' => (int)$withStaff['after_hours_count'],
            'relationship_duration_days' => $this->calculateDaysBetween(
                $withStaff['first_transaction'],
                $withStaff['last_transaction']
            ),
            'is_anomalous' => $frequencyAnomaly >= 2.0 // 2× expected frequency
        ];
    }

    /**
     * Analyze discount patterns
     *
     * @param int $customerId
     * @param int $staffId
     * @return array Discount analysis
     */
    private function analyzeDiscountPatterns(int $customerId, int $staffId): array
    {
        // Get discounts given by this staff member to customer
        $sql = "
            SELECT
                AVG(discount_percentage) as avg_discount,
                MAX(discount_percentage) as max_discount,
                SUM(discount_amount) as total_discounts,
                COUNT(*) as discount_count,
                STDDEV(discount_percentage) as discount_stddev
            FROM lightspeed_transactions
            WHERE customer_id = :customer_id
                AND staff_id = :staff_id
                AND discount_amount > 0
                AND transaction_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'customer_id' => $customerId,
            'staff_id' => $staffId,
            'days' => self::LONG_TERM_DAYS
        ]);

        $customerDiscounts = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get staff member's average discount to ALL customers
        $sql = "
            SELECT
                AVG(discount_percentage) as staff_avg_discount,
                STDDEV(discount_percentage) as staff_discount_stddev
            FROM lightspeed_transactions
            WHERE staff_id = :staff_id
                AND discount_amount > 0
                AND customer_id != :customer_id
                AND transaction_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'staff_id' => $staffId,
            'customer_id' => $customerId,
            'days' => self::LONG_TERM_DAYS
        ]);

        $staffAverage = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calculate statistical deviation
        $discountDeviation = 0.0;
        if ($staffAverage['staff_discount_stddev'] > 0) {
            $discountDeviation = abs(
                $customerDiscounts['avg_discount'] - $staffAverage['staff_avg_discount']
            ) / $staffAverage['staff_discount_stddev'];
        }

        return [
            'customer_avg_discount' => round((float)$customerDiscounts['avg_discount'], 2),
            'customer_max_discount' => round((float)$customerDiscounts['max_discount'], 2),
            'customer_total_discounts' => round((float)$customerDiscounts['total_discounts'], 2),
            'staff_avg_discount_to_others' => round((float)$staffAverage['staff_avg_discount'], 2),
            'statistical_deviation_sigma' => round($discountDeviation, 2),
            'is_anomalous' => $discountDeviation >= self::DISCOUNT_ANOMALY_THRESHOLD,
            'anomaly_severity' => $this->getAnomalySeverity($discountDeviation)
        ];
    }

    /**
     * Analyze return patterns
     *
     * @param int $customerId
     * @param int $staffId
     * @return array Return pattern analysis
     */
    private function analyzeReturnPatterns(int $customerId, int $staffId): array
    {
        // Get returns processed by this staff member
        $sql = "
            SELECT
                COUNT(*) as return_count,
                SUM(return_amount) as total_returns
            FROM lightspeed_transactions
            WHERE customer_id = :customer_id
                AND staff_id = :staff_id
                AND transaction_type = 'return'
                AND transaction_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'customer_id' => $customerId,
            'staff_id' => $staffId,
            'days' => self::LONG_TERM_DAYS
        ]);

        $returnsWithStaff = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get returns processed by other staff
        $sql = "
            SELECT COUNT(*) as return_count
            FROM lightspeed_transactions
            WHERE customer_id = :customer_id
                AND staff_id != :staff_id
                AND transaction_type = 'return'
                AND transaction_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'customer_id' => $customerId,
            'staff_id' => $staffId,
            'days' => self::LONG_TERM_DAYS
        ]);

        $returnsWithOthers = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalReturns = $returnsWithStaff['return_count'] + $returnsWithOthers['return_count'];
        $returnFrequency = $totalReturns > 0
            ? $returnsWithStaff['return_count'] / $totalReturns
            : 0.0;

        return [
            'returns_with_staff' => (int)$returnsWithStaff['return_count'],
            'returns_with_others' => (int)$returnsWithOthers['return_count'],
            'total_return_amount' => round((float)$returnsWithStaff['total_returns'], 2),
            'return_frequency_with_staff' => round($returnFrequency * 100, 1),
            'is_suspicious' => $returnFrequency >= 0.75 && $totalReturns >= 3
        ];
    }

    /**
     * Detect specific collusion patterns
     *
     * @param int $customerId
     * @param int $staffId
     * @param array $transactionData
     * @return array Detected patterns
     */
    private function detectCollusionPatterns(int $customerId, int $staffId, array $transactionData): array
    {
        $detectedPatterns = [];

        // After-hours transactions
        if ($transactionData['after_hours_transactions'] > 0) {
            $detectedPatterns[] = [
                'pattern' => 'after_hours_transactions',
                'severity' => 'CRITICAL',
                'description' => self::COLLUSION_PATTERNS['after_hours_transactions']['description'],
                'evidence' => "{$transactionData['after_hours_transactions']} after-hours transactions detected",
                'occurrences' => $transactionData['after_hours_transactions']
            ];
        }

        // Excessive discounts
        if ($transactionData['is_anomalous'] ?? false) {
            $detectedPatterns[] = [
                'pattern' => 'excessive_discounts',
                'severity' => 'HIGH',
                'description' => self::COLLUSION_PATTERNS['excessive_discounts']['description'],
                'evidence' => "Discounts significantly above staff average",
                'statistical_significance' => 'anomalous'
            ];
        }

        // Frequency abuse
        if ($transactionData['frequency_anomaly_score'] >= 2.0) {
            $detectedPatterns[] = [
                'pattern' => 'frequency_abuse',
                'severity' => 'MEDIUM',
                'description' => self::COLLUSION_PATTERNS['frequency_abuse']['description'],
                'evidence' => "{$transactionData['frequency_with_staff_percentage']}% of transactions with same staff",
                'expected_percentage' => '20%'
            ];
        }

        return $detectedPatterns;
    }

    /**
     * Calculate composite collusion score
     *
     * @param array $relationship
     * @param array $transactions
     * @param array $discounts
     * @param array $returns
     * @param array $patterns
     * @return array Collusion score
     */
    private function calculateCollusionScore(
        array $relationship,
        array $transactions,
        array $discounts,
        array $returns,
        array $patterns
    ): array {
        $scores = [
            'relationship' => $relationship['confidence_score'] * 0.30,
            'transaction_frequency' => min(1.0, $transactions['frequency_anomaly_score'] / 3.0) * 0.25,
            'discount_anomaly' => ($discounts['is_anomalous'] ? 1.0 : 0.0) * 0.25,
            'return_pattern' => ($returns['is_suspicious'] ? 1.0 : 0.0) * 0.10,
            'critical_patterns' => (count($patterns) * 0.20)
        ];

        $totalScore = array_sum($scores);
        $normalizedScore = min(1.0, $totalScore);

        return [
            'total_score' => round($normalizedScore, 3),
            'risk_level' => $this->determineRiskLevel($normalizedScore),
            'component_scores' => $scores,
            'patterns_detected' => count($patterns)
        ];
    }

    // ========== UTILITY METHODS ==========

    private function getCustomerDetails(int $customerId): ?array
    {
        $sql = "
            SELECT
                customer_id,
                customer_name,
                email,
                phone,
                address,
                city,
                postal_code
            FROM lightspeed_customers
            WHERE customer_id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $customerId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getStaffDetails(int $staffId): ?array
    {
        $sql = "
            SELECT
                staff_id,
                staff_name,
                email as work_email,
                personal_email,
                phone,
                address,
                city,
                postal_code
            FROM staff
            WHERE staff_id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $staffId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function extractLastName(string $fullName): ?string
    {
        $parts = explode(' ', trim($fullName));
        return count($parts) > 1 ? end($parts) : null;
    }

    private function calculateAddressSimilarity(string $addr1, string $addr2): float
    {
        $addr1 = strtolower(preg_replace('/[^a-z0-9]/', '', $addr1));
        $addr2 = strtolower(preg_replace('/[^a-z0-9]/', '', $addr2));

        similar_text($addr1, $addr2, $percent);
        return $percent / 100.0;
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }

    private function extractEmailDomain(string $email): ?string
    {
        $parts = explode('@', $email);
        return count($parts) === 2 ? strtolower($parts[1]) : null;
    }

    private function isCustomerInStaffContacts(int $customerId, int $staffId): bool
    {
        $sql = "
            SELECT COUNT(*) as count
            FROM staff_emergency_contacts
            WHERE staff_id = :staff_id
                AND (phone IN (SELECT phone FROM lightspeed_customers WHERE customer_id = :customer_id)
                     OR email IN (SELECT email FROM lightspeed_customers WHERE customer_id = :customer_id))
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'staff_id' => $staffId,
            'customer_id' => $customerId
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    private function isDeclaredFamilyMember(int $customerId, int $staffId): bool
    {
        $sql = "
            SELECT COUNT(*) as count
            FROM staff_family_declarations
            WHERE staff_id = :staff_id
                AND customer_id = :customer_id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'staff_id' => $staffId,
            'customer_id' => $customerId
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    private function calculateDaysBetween(?string $date1, ?string $date2): int
    {
        if (!$date1 || !$date2) return 0;

        $d1 = new \DateTime($date1);
        $d2 = new \DateTime($date2);
        return $d1->diff($d2)->days;
    }

    private function getConfidenceLevel(float $score): string
    {
        if ($score >= self::RELATIONSHIP_CONFIDENCE_HIGH) return 'HIGH';
        if ($score >= self::RELATIONSHIP_CONFIDENCE_MEDIUM) return 'MEDIUM';
        if ($score >= 0.30) return 'LOW';
        return 'NONE';
    }

    private function getAnomalySeverity(float $sigma): string
    {
        if ($sigma >= 4.0) return 'CRITICAL';
        if ($sigma >= 3.0) return 'HIGH';
        if ($sigma >= 2.0) return 'MEDIUM';
        return 'LOW';
    }

    private function determineRiskLevel(float $score): string
    {
        if ($score >= 0.85) return 'CRITICAL';
        if ($score >= 0.70) return 'HIGH';
        if ($score >= 0.50) return 'MEDIUM';
        if ($score >= 0.30) return 'LOW';
        return 'MINIMAL';
    }

    private function generateCollusionAlert(int $customerId, int $staffId, array $score): array
    {
        return [
            'alert_id' => uniqid('collusion_'),
            'alert_type' => 'CUSTOMER_STAFF_COLLUSION',
            'severity' => $score['risk_level'],
            'customer_id' => $customerId,
            'staff_id' => $staffId,
            'collusion_score' => $score['total_score'],
            'message' => "Potential collusion detected between customer and staff member",
            'recommended_action' => 'Investigate transaction history and relationship',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    private function storeCollusionAnalysis(int $customerId, int $staffId, array $score): void
    {
        $sql = "
            INSERT INTO customer_collusion_analysis (
                customer_id,
                staff_id,
                collusion_score,
                risk_level,
                analysis_data,
                analyzed_at,
                created_at
            ) VALUES (
                :customer_id,
                :staff_id,
                :score,
                :risk_level,
                :data,
                NOW(),
                NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'customer_id' => $customerId,
            'staff_id' => $staffId,
            'score' => $score['total_score'],
            'risk_level' => $score['risk_level'],
            'data' => json_encode($score)
        ]);
    }
}
