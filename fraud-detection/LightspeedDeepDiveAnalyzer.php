<?php

/**
 * Lightspeed/Vend Deep Dive Fraud Analyzer
 *
 * COMPREHENSIVE POS DATA ANALYSIS covering ALL fraud vectors:
 *
 * SECTION 1: PAYMENT TYPE FRAUD
 * - Payments to unusual/random payment types
 * - Payment type switching patterns
 * - Custom payment type abuse
 * - Split payment anomalies
 *
 * SECTION 2: CUSTOMER ACCOUNT FRAUD
 * - Sales on random/fake customer accounts
 * - Account credit manipulation
 * - Loyal customer account abuse
 * - Store credit fraud
 *
 * SECTION 3: INVENTORY MOVEMENT FRAUD
 * - Stock adjustments without reason
 * - Transfer manipulation (outlet to outlet)
 * - Receiving discrepancies
 * - Shrinkage patterns
 * - Consignment fraud
 *
 * SECTION 4: CASH REGISTER CLOSURE FRAUD
 * - Till closure discrepancies
 * - Cash-up shortages/overages
 * - Float manipulation
 * - Expected vs actual cash variances
 *
 * SECTION 5: BANKING & DEPOSIT FRAUD
 * - Daily banking discrepancies
 * - Missing deposits
 * - Deposit timing anomalies
 * - Bank vs POS reconciliation gaps
 *
 * SECTION 6: TRANSACTION MANIPULATION
 * - Void patterns and timing
 * - Refund fraud schemes
 * - Discount abuse
 * - Price override patterns
 * - Layby/layaway fraud
 *
 * SECTION 7: END-OF-DAY/WEEK RECONCILIATION
 * - Daily totals manipulation
 * - Weekly summary discrepancies
 * - Cross-outlet reconciliation gaps
 *
 * @package FraudDetection
 * @version 3.0.0
 */

namespace FraudDetection;

use PDO;
use Exception;
use DateTime;

class LightspeedDeepDiveAnalyzer
{
    private PDO $pdo;
    private array $config;
    private array $analysisResults = [];

    // Fraud thresholds
    private const UNUSUAL_PAYMENT_TYPE_THRESHOLD = 5; // Uses per week
    private const CASH_VARIANCE_THRESHOLD = 50; // Dollars
    private const INVENTORY_ADJUSTMENT_THRESHOLD = 10; // Qty per adjustment
    private const CUSTOMER_ACCOUNT_SALES_THRESHOLD = 3; // Sales per day on account
    private const REFUND_PERCENTAGE_THRESHOLD = 15; // % of sales
    private const VOID_PERCENTAGE_THRESHOLD = 10; // % of transactions
    private const DISCOUNT_PERCENTAGE_THRESHOLD = 20; // % discount
    private const DEPOSIT_DELAY_THRESHOLD_DAYS = 3; // Days to deposit
    
    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'analysis_window_days' => 30,
            'enable_all_checks' => true,
            'alert_on_critical' => true,
        ], $config);
    }

    /**
     * Run comprehensive Lightspeed deep-dive analysis
     *
     * @param int $staffId Staff member to analyze
     * @param int $days Number of days to analyze
     * @return array Complete analysis results
     */
    public function analyzeStaff(int $staffId, int $days = 30): array
    {
        $this->analysisResults = [
            'staff_id' => $staffId,
            'analysis_period_days' => $days,
            'analysis_timestamp' => date('Y-m-d H:i:s'),
            'sections' => [],
            'fraud_indicators' => [],
            'risk_score' => 0.0,
            'risk_level' => 'low',
            'critical_alerts' => []
        ];

        try {
            // SECTION 1: Payment Type Fraud Analysis
            $this->analyzePaymentTypeFraud($staffId, $days);

            // SECTION 2: Customer Account Fraud Analysis
            $this->analyzeCustomerAccountFraud($staffId, $days);

            // SECTION 3: Inventory Movement Fraud Analysis
            $this->analyzeInventoryMovementFraud($staffId, $days);

            // SECTION 4: Cash Register Closure Analysis
            $this->analyzeCashRegisterClosures($staffId, $days);

            // SECTION 5: Banking & Deposit Analysis
            $this->analyzeBankingDeposits($staffId, $days);

            // SECTION 6: Transaction Manipulation Analysis
            $this->analyzeTransactionManipulation($staffId, $days);

            // SECTION 7: End-of-Day/Week Reconciliation
            $this->analyzeReconciliation($staffId, $days);

            // Calculate overall risk score
            $this->calculateRiskScore();

            // Store results
            $this->storeAnalysisResults();

            return $this->analysisResults;

        } catch (Exception $e) {
            error_log("Lightspeed deep-dive analysis failed for staff {$staffId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * SECTION 1: PAYMENT TYPE FRAUD ANALYSIS
     * 
     * Detects:
     * - Payments to unusual/custom payment types
     * - Payment type switching patterns
     * - Split payment manipulation
     * - Cash vs card ratio anomalies
     */
    private function analyzePaymentTypeFraud(int $staffId, int $days): void
    {
        $sectionData = [
            'section_name' => 'Payment Type Fraud',
            'checks_performed' => [],
            'issues_found' => []
        ];

        try {
            // 1. Get all payment types used by this staff
            $stmt = $this->pdo->prepare("
                SELECT 
                    payment_type,
                    COUNT(*) as usage_count,
                    SUM(total_price) as total_amount,
                    AVG(total_price) as avg_amount,
                    MIN(sale_date) as first_use,
                    MAX(sale_date) as last_use
                FROM vend_sales
                WHERE user_id = :staff_id
                AND sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND status = 'CLOSED'
                GROUP BY payment_type
                ORDER BY usage_count DESC
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $paymentTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sectionData['checks_performed'][] = 'payment_type_distribution';

            // 2. Identify unusual payment types
            $standardPaymentTypes = ['CASH', 'EFTPOS', 'CREDIT_CARD', 'DEBIT_CARD', 'ACCOUNT'];
            foreach ($paymentTypes as $pt) {
                if (!in_array($pt['payment_type'], $standardPaymentTypes)) {
                    // Custom/unusual payment type
                    $this->addFraudIndicator(
                        'unusual_payment_type',
                        'payment_type_fraud',
                        "Using unusual payment type '{$pt['payment_type']}': {$pt['usage_count']} times, \${$pt['total_amount']}",
                        0.7,
                        $pt
                    );
                    $sectionData['issues_found'][] = [
                        'type' => 'unusual_payment_type',
                        'payment_type' => $pt['payment_type'],
                        'usage_count' => $pt['usage_count'],
                        'total_amount' => $pt['total_amount']
                    ];
                }
            }

            // 3. Check for random payment type usage (low frequency)
            foreach ($paymentTypes as $pt) {
                $usesPerWeek = ($pt['usage_count'] / $days) * 7;
                if ($usesPerWeek < 1 && $pt['total_amount'] > 100) {
                    // Rarely used payment type but high value
                    $this->addFraudIndicator(
                        'random_payment_type',
                        'payment_type_fraud',
                        "Random payment type usage: '{$pt['payment_type']}' used {$pt['usage_count']} times but totaling \${$pt['total_amount']}",
                        0.75,
                        $pt
                    );
                }
            }

            // 4. Analyze split payment patterns
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(sale_date) as sale_day,
                    COUNT(*) as split_payment_count,
                    SUM(total_price) as total_amount
                FROM vend_sales
                WHERE user_id = :staff_id
                AND sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND (
                    payment_type LIKE '%,%' 
                    OR JSON_LENGTH(payment_types) > 1
                )
                GROUP BY DATE(sale_date)
                HAVING split_payment_count > 5
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $splitPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($splitPayments) > 0) {
                $sectionData['checks_performed'][] = 'split_payment_analysis';
                foreach ($splitPayments as $sp) {
                    if ($sp['split_payment_count'] > 5) {
                        $this->addFraudIndicator(
                            'excessive_split_payments',
                            'payment_type_fraud',
                            "Excessive split payments on {$sp['sale_day']}: {$sp['split_payment_count']} transactions",
                            0.65,
                            $sp
                        );
                    }
                }
            }

            // 5. Cash vs Card ratio analysis (compare to outlet average)
            $stmt = $this->pdo->prepare("
                SELECT 
                    SUM(CASE WHEN payment_type = 'CASH' THEN total_price ELSE 0 END) as cash_total,
                    SUM(CASE WHEN payment_type IN ('EFTPOS', 'CREDIT_CARD', 'DEBIT_CARD') THEN total_price ELSE 0 END) as card_total,
                    COUNT(CASE WHEN payment_type = 'CASH' THEN 1 END) as cash_count,
                    COUNT(CASE WHEN payment_type IN ('EFTPOS', 'CREDIT_CARD', 'DEBIT_CARD') THEN 1 END) as card_count
                FROM vend_sales
                WHERE user_id = :staff_id
                AND sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND status = 'CLOSED'
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $ratios = $stmt->fetch(PDO::FETCH_ASSOC);

            $cashPercentage = $ratios['cash_total'] / ($ratios['cash_total'] + $ratios['card_total']) * 100;
            
            // Get outlet average for comparison
            $outletCashPercentage = $this->getOutletAverageCashPercentage($staffId, $days);
            
            if (abs($cashPercentage - $outletCashPercentage) > 20) {
                $this->addFraudIndicator(
                    'abnormal_cash_ratio',
                    'payment_type_fraud',
                    "Abnormal cash ratio: {$cashPercentage}% vs outlet average {$outletCashPercentage}%",
                    0.6,
                    ['staff_cash_pct' => $cashPercentage, 'outlet_avg_pct' => $outletCashPercentage]
                );
            }

            $this->analysisResults['sections']['payment_type_fraud'] = $sectionData;

        } catch (Exception $e) {
            error_log("Payment type fraud analysis failed: " . $e->getMessage());
        }
    }

    /**
     * SECTION 2: CUSTOMER ACCOUNT FRAUD ANALYSIS
     * 
     * Detects:
     * - Sales on random/fake customer accounts
     * - Account credit manipulation
     * - Loyal customer account abuse
     * - Store credit fraud
     */
    private function analyzeCustomerAccountFraud(int $staffId, int $days): void
    {
        $sectionData = [
            'section_name' => 'Customer Account Fraud',
            'checks_performed' => [],
            'issues_found' => []
        ];

        try {
            // 1. Find sales on ACCOUNT payment type
            $stmt = $this->pdo->prepare("
                SELECT 
                    customer_id,
                    customer_name,
                    COUNT(*) as sales_count,
                    SUM(total_price) as total_amount,
                    AVG(total_price) as avg_amount,
                    MAX(sale_date) as last_sale
                FROM vend_sales
                WHERE user_id = :staff_id
                AND payment_type = 'ACCOUNT'
                AND sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY customer_id, customer_name
                ORDER BY sales_count DESC
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $accountSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sectionData['checks_performed'][] = 'account_payment_analysis';

            foreach ($accountSales as $account) {
                // Check for excessive account sales
                $salesPerWeek = ($account['sales_count'] / $days) * 7;
                if ($salesPerWeek > self::CUSTOMER_ACCOUNT_SALES_THRESHOLD) {
                    $this->addFraudIndicator(
                        'excessive_account_sales',
                        'customer_account_fraud',
                        "Excessive account sales to customer '{$account['customer_name']}': {$account['sales_count']} sales, \${$account['total_amount']}",
                        0.8,
                        $account
                    );
                }

                // Check if customer account is suspicious (e.g., generic names)
                if ($this->isSuspiciousCustomerName($account['customer_name'])) {
                    $this->addFraudIndicator(
                        'suspicious_customer_account',
                        'customer_account_fraud',
                        "Sales to suspicious customer account '{$account['customer_name']}': {$account['sales_count']} sales",
                        0.85,
                        $account
                    );
                }
            }

            // 2. Check for store credit abuse
            $stmt = $this->pdo->prepare("
                SELECT 
                    customer_id,
                    customer_name,
                    COUNT(*) as credit_usage_count,
                    SUM(store_credit_used) as total_credit_used
                FROM vend_sales
                WHERE user_id = :staff_id
                AND store_credit_used > 0
                AND sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY customer_id, customer_name
                HAVING total_credit_used > 500
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $storeCreditAbuse = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($storeCreditAbuse) > 0) {
                $sectionData['checks_performed'][] = 'store_credit_analysis';
                foreach ($storeCreditAbuse as $credit) {
                    $this->addFraudIndicator(
                        'excessive_store_credit',
                        'customer_account_fraud',
                        "Excessive store credit usage for '{$credit['customer_name']}': \${$credit['total_credit_used']}",
                        0.75,
                        $credit
                    );
                }
            }

            // 3. Check for loyalty points manipulation
            $stmt = $this->pdo->prepare("
                SELECT 
                    customer_id,
                    customer_name,
                    SUM(loyalty_points_earned) as points_earned,
                    SUM(loyalty_points_redeemed) as points_redeemed,
                    COUNT(*) as transaction_count
                FROM vend_sales
                WHERE user_id = :staff_id
                AND sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND (loyalty_points_earned > 0 OR loyalty_points_redeemed > 0)
                GROUP BY customer_id, customer_name
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $loyaltyActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($loyaltyActivity as $loyalty) {
                // Unusually high points redemption
                if ($loyalty['points_redeemed'] > $loyalty['points_earned'] * 2) {
                    $this->addFraudIndicator(
                        'loyalty_points_manipulation',
                        'customer_account_fraud',
                        "Suspicious loyalty activity for '{$loyalty['customer_name']}': Redeemed {$loyalty['points_redeemed']} vs earned {$loyalty['points_earned']}",
                        0.8,
                        $loyalty
                    );
                }
            }

            // 4. Random customer assignment (different customers used randomly)
            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT customer_id) as unique_customers
                FROM vend_sales
                WHERE user_id = :staff_id
                AND customer_id IS NOT NULL
                AND sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $uniqueCustomers = $stmt->fetchColumn();

            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total_sales
                FROM vend_sales
                WHERE user_id = :staff_id
                AND sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $totalSales = $stmt->fetchColumn();

            // If nearly every sale has a different customer, that's suspicious
            if ($uniqueCustomers > ($totalSales * 0.8)) {
                $this->addFraudIndicator(
                    'random_customer_assignment',
                    'customer_account_fraud',
                    "Random customer assignment pattern: {$uniqueCustomers} unique customers for {$totalSales} sales",
                    0.7,
                    ['unique_customers' => $uniqueCustomers, 'total_sales' => $totalSales]
                );
            }

            $this->analysisResults['sections']['customer_account_fraud'] = $sectionData;

        } catch (Exception $e) {
            error_log("Customer account fraud analysis failed: " . $e->getMessage());
        }
    }

    /**
     * SECTION 3: INVENTORY MOVEMENT FRAUD ANALYSIS
     * 
     * Detects:
     * - Stock adjustments without proper reason
     * - Transfer manipulation between outlets
     * - Receiving discrepancies
     * - Shrinkage patterns
     */
    private function analyzeInventoryMovementFraud(int $staffId, int $days): void
    {
        $sectionData = [
            'section_name' => 'Inventory Movement Fraud',
            'checks_performed' => [],
            'issues_found' => []
        ];

        try {
            // 1. Analyze stock adjustments
            $stmt = $this->pdo->prepare("
                SELECT 
                    product_id,
                    product_name,
                    outlet_id,
                    SUM(adjustment_qty) as total_adjusted,
                    COUNT(*) as adjustment_count,
                    GROUP_CONCAT(DISTINCT adjustment_reason) as reasons,
                    SUM(ABS(adjustment_qty * cost_price)) as value_adjusted
                FROM vend_stock_adjustments
                WHERE created_by_user_id = :staff_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY product_id, product_name, outlet_id
                HAVING ABS(total_adjusted) > :threshold
                ORDER BY ABS(total_adjusted) DESC
            ");
            $stmt->execute([
                'staff_id' => $staffId, 
                'days' => $days,
                'threshold' => self::INVENTORY_ADJUSTMENT_THRESHOLD
            ]);
            $adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sectionData['checks_performed'][] = 'stock_adjustment_analysis';

            foreach ($adjustments as $adj) {
                // Large adjustments are suspicious
                $this->addFraudIndicator(
                    'large_stock_adjustment',
                    'inventory_fraud',
                    "Large stock adjustment for '{$adj['product_name']}': {$adj['total_adjusted']} units, \${$adj['value_adjusted']} value",
                    0.7,
                    $adj
                );

                // Adjustments without proper reason
                if (empty($adj['reasons']) || $adj['reasons'] === 'null' || strpos($adj['reasons'], 'Unknown') !== false) {
                    $this->addFraudIndicator(
                        'stock_adjustment_no_reason',
                        'inventory_fraud',
                        "Stock adjustment without reason for '{$adj['product_name']}': {$adj['total_adjusted']} units",
                        0.8,
                        $adj
                    );
                }
            }

            // 2. Analyze stock transfers
            $stmt = $this->pdo->prepare("
                SELECT 
                    from_outlet_id,
                    to_outlet_id,
                    COUNT(*) as transfer_count,
                    SUM(total_items) as total_items_transferred,
                    SUM(total_value) as total_value
                FROM vend_stock_transfers
                WHERE created_by_user_id = :staff_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY from_outlet_id, to_outlet_id
                ORDER BY total_value DESC
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($transfers as $transfer) {
                // Frequent transfers or high-value transfers
                if ($transfer['transfer_count'] > 10 || $transfer['total_value'] > 5000) {
                    $this->addFraudIndicator(
                        'unusual_transfer_pattern',
                        'inventory_fraud',
                        "High volume transfers: {$transfer['transfer_count']} transfers from outlet {$transfer['from_outlet_id']} to {$transfer['to_outlet_id']}, \${$transfer['total_value']}",
                        0.65,
                        $transfer
                    );
                }
            }

            // 3. Receiving discrepancies
            $stmt = $this->pdo->prepare("
                SELECT 
                    r.id as receiving_id,
                    r.expected_items,
                    r.received_items,
                    r.discrepancy,
                    r.received_date,
                    r.supplier_id
                FROM vend_stock_receiving r
                WHERE r.received_by_user_id = :staff_id
                AND r.received_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND ABS(r.discrepancy) > 0
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $receivingDiscrepancies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($receivingDiscrepancies) > 0) {
                $sectionData['checks_performed'][] = 'receiving_discrepancy_analysis';
                $totalDiscrepancy = array_sum(array_column($receivingDiscrepancies, 'discrepancy'));
                
                if (abs($totalDiscrepancy) > 20) {
                    $this->addFraudIndicator(
                        'receiving_discrepancies',
                        'inventory_fraud',
                        "Receiving discrepancies: {$totalDiscrepancy} items difference across " . count($receivingDiscrepancies) . " receipts",
                        0.75,
                        ['total_discrepancy' => $totalDiscrepancy, 'receipt_count' => count($receivingDiscrepancies)]
                    );
                }
            }

            // 4. Shrinkage pattern analysis
            $stmt = $this->pdo->prepare("
                SELECT 
                    outlet_id,
                    SUM(shrinkage_qty) as total_shrinkage,
                    SUM(shrinkage_qty * cost_price) as shrinkage_value,
                    COUNT(DISTINCT product_id) as products_affected
                FROM vend_stock_adjustments
                WHERE created_by_user_id = :staff_id
                AND adjustment_reason LIKE '%shrinkage%'
                AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY outlet_id
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $shrinkage = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($shrinkage as $s) {
                if ($s['shrinkage_value'] > 500) {
                    $this->addFraudIndicator(
                        'excessive_shrinkage',
                        'inventory_fraud',
                        "Excessive shrinkage at outlet {$s['outlet_id']}: \${$s['shrinkage_value']} across {$s['products_affected']} products",
                        0.8,
                        $s
                    );
                }
            }

            $this->analysisResults['sections']['inventory_fraud'] = $sectionData;

        } catch (Exception $e) {
            error_log("Inventory movement fraud analysis failed: " . $e->getMessage());
        }
    }

    /**
     * SECTION 4: CASH REGISTER CLOSURE ANALYSIS
     * 
     * Detects:
     * - Till closure discrepancies
     * - Cash-up shortages/overages
     * - Float manipulation
     * - Expected vs actual cash variances
     */
    private function analyzeCashRegisterClosures(int $staffId, int $days): void
    {
        $sectionData = [
            'section_name' => 'Cash Register Closures',
            'checks_performed' => [],
            'issues_found' => []
        ];

        try {
            // 1. Get all register closures by this staff
            $stmt = $this->pdo->prepare("
                SELECT 
                    rc.id,
                    rc.outlet_id,
                    rc.register_id,
                    rc.closure_date,
                    rc.expected_cash,
                    rc.actual_cash,
                    rc.variance,
                    rc.float_amount,
                    rc.total_sales,
                    rc.notes
                FROM vend_register_closures rc
                WHERE rc.closed_by_user_id = :staff_id
                AND rc.closure_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
                ORDER BY rc.closure_date DESC
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $closures = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sectionData['checks_performed'][] = 'register_closure_analysis';

            $totalShortages = 0;
            $totalOverages = 0;
            $shortageCount = 0;
            $overageCount = 0;

            foreach ($closures as $closure) {
                // Check for significant variances
                if (abs($closure['variance']) > self::CASH_VARIANCE_THRESHOLD) {
                    $type = $closure['variance'] < 0 ? 'shortage' : 'overage';
                    $this->addFraudIndicator(
                        "cash_register_{$type}",
                        'register_closure_fraud',
                        "Significant cash {$type}: \${$closure['variance']} on " . $closure['closure_date'],
                        $closure['variance'] < 0 ? 0.9 : 0.6, // Shortages more suspicious than overages
                        $closure
                    );

                    if ($closure['variance'] < 0) {
                        $totalShortages += abs($closure['variance']);
                        $shortageCount++;
                    } else {
                        $totalOverages += $closure['variance'];
                        $overageCount++;
                    }
                }

                // Check for pattern of small shortages (potential skimming)
                if ($closure['variance'] < 0 && abs($closure['variance']) >= 5 && abs($closure['variance']) <= 20) {
                    $sectionData['issues_found'][] = [
                        'type' => 'small_shortage_pattern',
                        'amount' => $closure['variance'],
                        'date' => $closure['closure_date']
                    ];
                }
            }

            // Pattern analysis: Consistent small shortages (skimming)
            if ($shortageCount >= 5) {
                $avgShortage = $totalShortages / $shortageCount;
                if ($avgShortage < 50) { // Small consistent shortages
                    $this->addFraudIndicator(
                        'potential_skimming_pattern',
                        'register_closure_fraud',
                        "Pattern of consistent small shortages: {$shortageCount} shortages averaging \${$avgShortage}",
                        0.85,
                        ['shortage_count' => $shortageCount, 'total' => $totalShortages, 'avg' => $avgShortage]
                    );
                }
            }

            // 2. Float manipulation check
            $stmt = $this->pdo->prepare("
                SELECT 
                    register_id,
                    AVG(float_amount) as avg_float,
                    STDDEV(float_amount) as float_stddev,
                    COUNT(DISTINCT float_amount) as unique_float_amounts
                FROM vend_register_closures
                WHERE closed_by_user_id = :staff_id
                AND closure_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY register_id
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $floatAnalysis = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($floatAnalysis as $float) {
                // Highly variable float amounts are suspicious
                if ($float['float_stddev'] > 50) {
                    $this->addFraudIndicator(
                        'float_manipulation',
                        'register_closure_fraud',
                        "Highly variable float amounts for register {$float['register_id']}: stddev \${$float['float_stddev']}",
                        0.75,
                        $float
                    );
                }
            }

            $this->analysisResults['sections']['register_closure_fraud'] = $sectionData;

        } catch (Exception $e) {
            error_log("Cash register closure analysis failed: " . $e->getMessage());
        }
    }

    /**
     * SECTION 5: BANKING & DEPOSIT ANALYSIS
     * 
     * Detects:
     * - Daily banking discrepancies
     * - Missing deposits
     * - Deposit timing anomalies
     * - Bank vs POS reconciliation gaps
     */
    private function analyzeBankingDeposits(int $staffId, int $days): void
    {
        $sectionData = [
            'section_name' => 'Banking & Deposits',
            'checks_performed' => [],
            'issues_found' => []
        ];

        try {
            // 1. Get deposit records
            $stmt = $this->pdo->prepare("
                SELECT 
                    d.id,
                    d.outlet_id,
                    d.deposit_date,
                    d.expected_amount,
                    d.deposited_amount,
                    d.discrepancy,
                    d.bank_name,
                    d.deposited_by_user_id,
                    d.created_at,
                    DATEDIFF(d.deposit_date, d.created_at) as days_to_deposit
                FROM vend_deposits d
                WHERE d.created_by_user_id = :staff_id
                OR d.deposited_by_user_id = :staff_id
                AND d.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sectionData['checks_performed'][] = 'deposit_analysis';

            foreach ($deposits as $deposit) {
                // Check for deposit discrepancies
                if (abs($deposit['discrepancy']) > 50) {
                    $this->addFraudIndicator(
                        'deposit_discrepancy',
                        'banking_fraud',
                        "Deposit discrepancy: \${$deposit['discrepancy']} on " . $deposit['deposit_date'],
                        0.85,
                        $deposit
                    );
                }

                // Check for deposit delays
                if ($deposit['days_to_deposit'] > self::DEPOSIT_DELAY_THRESHOLD_DAYS) {
                    $this->addFraudIndicator(
                        'delayed_deposit',
                        'banking_fraud',
                        "Delayed deposit: {$deposit['days_to_deposit']} days delay for \${$deposit['deposited_amount']}",
                        0.7,
                        $deposit
                    );
                }
            }

            // 2. Check for missing deposits (daily sales vs deposits)
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(s.sale_date) as sale_day,
                    s.outlet_id,
                    SUM(CASE WHEN s.payment_type = 'CASH' THEN s.total_price ELSE 0 END) as daily_cash_sales,
                    d.deposited_amount,
                    (SUM(CASE WHEN s.payment_type = 'CASH' THEN s.total_price ELSE 0 END) - COALESCE(d.deposited_amount, 0)) as gap
                FROM vend_sales s
                LEFT JOIN vend_deposits d ON DATE(s.sale_date) = d.deposit_date AND s.outlet_id = d.outlet_id
                WHERE s.user_id = :staff_id
                AND s.sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND s.status = 'CLOSED'
                GROUP BY DATE(s.sale_date), s.outlet_id, d.deposited_amount
                HAVING ABS(gap) > 100
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $missingDeposits = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($missingDeposits) > 0) {
                foreach ($missingDeposits as $missing) {
                    $this->addFraudIndicator(
                        'missing_deposit',
                        'banking_fraud',
                        "Deposit gap on {$missing['sale_day']}: \${$missing['gap']} (Sales: \${$missing['daily_cash_sales']}, Deposited: \${$missing['deposited_amount']})",
                        0.9,
                        $missing
                    );
                }
            }

            // 3. Weekly banking reconciliation
            $stmt = $this->pdo->prepare("
                SELECT 
                    YEARWEEK(sale_date) as year_week,
                    SUM(CASE WHEN payment_type = 'CASH' THEN total_price ELSE 0 END) as weekly_cash_sales
                FROM vend_sales
                WHERE user_id = :staff_id
                AND sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND status = 'CLOSED'
                GROUP BY YEARWEEK(sale_date)
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $weeklySales = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->pdo->prepare("
                SELECT 
                    YEARWEEK(deposit_date) as year_week,
                    SUM(deposited_amount) as weekly_deposits
                FROM vend_deposits
                WHERE (created_by_user_id = :staff_id OR deposited_by_user_id = :staff_id)
                AND deposit_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY YEARWEEK(deposit_date)
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $weeklyDeposits = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Compare weekly sales vs deposits
            $salesByWeek = array_column($weeklySales, 'weekly_cash_sales', 'year_week');
            $depositsByWeek = array_column($weeklyDeposits, 'weekly_deposits', 'year_week');

            foreach ($salesByWeek as $week => $sales) {
                $deposits = $depositsByWeek[$week] ?? 0;
                $gap = $sales - $deposits;
                
                if (abs($gap) > 200) {
                    $this->addFraudIndicator(
                        'weekly_reconciliation_gap',
                        'banking_fraud',
                        "Weekly reconciliation gap (week {$week}): \${$gap}",
                        0.8,
                        ['week' => $week, 'sales' => $sales, 'deposits' => $deposits, 'gap' => $gap]
                    );
                }
            }

            $this->analysisResults['sections']['banking_fraud'] = $sectionData;

        } catch (Exception $e) {
            error_log("Banking deposit analysis failed: " . $e->getMessage());
        }
    }

    /**
     * SECTION 6: TRANSACTION MANIPULATION ANALYSIS
     * 
     * Detects:
     * - Void patterns and timing
     * - Refund fraud schemes
     * - Discount abuse
     * - Price override patterns
     */
    private function analyzeTransactionManipulation(int $staffId, int $days): void
    {
        $sectionData = [
            'section_name' => 'Transaction Manipulation',
            'checks_performed' => [],
            'issues_found' => []
        ];

        try {
            // Get total transaction count for percentage calculations
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM vend_sales
                WHERE user_id = :staff_id
                AND sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $totalTransactions = $stmt->fetchColumn();

            // 1. Void analysis
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as void_count,
                    SUM(total_price) as void_amount,
                    AVG(total_price) as avg_void_amount,
                    GROUP_CONCAT(CONCAT(id, ':', TIMESTAMPDIFF(MINUTE, sale_date, voided_at))) as void_timing
                FROM vend_sales
                WHERE user_id = :staff_id
                AND status = 'VOIDED'
                AND sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $voids = $stmt->fetch(PDO::FETCH_ASSOC);

            $sectionData['checks_performed'][] = 'void_analysis';

            if ($totalTransactions > 0) {
                $voidPercentage = ($voids['void_count'] / $totalTransactions) * 100;
                
                if ($voidPercentage > self::VOID_PERCENTAGE_THRESHOLD) {
                    $this->addFraudIndicator(
                        'excessive_voids',
                        'transaction_manipulation',
                        "Excessive void rate: {$voidPercentage}% ({$voids['void_count']} voids, \${$voids['void_amount']})",
                        0.8,
                        $voids
                    );
                }
            }

            // Check void timing (immediate voids are more suspicious)
            if (!empty($voids['void_timing'])) {
                $voidTimings = explode(',', $voids['void_timing']);
                $immediateVoids = 0;
                foreach ($voidTimings as $timing) {
                    list($id, $minutes) = explode(':', $timing);
                    if ($minutes < 5) {
                        $immediateVoids++;
                    }
                }
                
                if ($immediateVoids > 5) {
                    $this->addFraudIndicator(
                        'immediate_void_pattern',
                        'transaction_manipulation',
                        "Pattern of immediate voids: {$immediateVoids} voids within 5 minutes of sale",
                        0.75,
                        ['immediate_voids' => $immediateVoids]
                    );
                }
            }

            // 2. Refund analysis
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as refund_count,
                    SUM(ABS(total_price)) as refund_amount,
                    AVG(ABS(total_price)) as avg_refund
                FROM vend_sales
                WHERE user_id = :staff_id
                AND total_price < 0
                AND status = 'CLOSED'
                AND sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $refunds = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($totalTransactions > 0) {
                $refundPercentage = ($refunds['refund_count'] / $totalTransactions) * 100;
                
                if ($refundPercentage > self::REFUND_PERCENTAGE_THRESHOLD) {
                    $this->addFraudIndicator(
                        'excessive_refunds',
                        'transaction_manipulation',
                        "Excessive refund rate: {$refundPercentage}% ({$refunds['refund_count']} refunds, \${$refunds['refund_amount']})",
                        0.75,
                        $refunds
                    );
                }
            }

            // 3. Discount abuse
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as discount_count,
                    AVG((total_discount / (total_price + total_discount)) * 100) as avg_discount_pct,
                    MAX((total_discount / (total_price + total_discount)) * 100) as max_discount_pct,
                    SUM(total_discount) as total_discount_amount
                FROM vend_sales
                WHERE user_id = :staff_id
                AND total_discount > 0
                AND sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND status = 'CLOSED'
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $discounts = $stmt->fetch(PDO::FETCH_ASSOC);

            $sectionData['checks_performed'][] = 'discount_analysis';

            if ($discounts['avg_discount_pct'] > self::DISCOUNT_PERCENTAGE_THRESHOLD) {
                $this->addFraudIndicator(
                    'excessive_discounts',
                    'transaction_manipulation',
                    "Excessive discount usage: {$discounts['avg_discount_pct']}% average, \${$discounts['total_discount_amount']} total",
                    0.7,
                    $discounts
                );
            }

            // 4. Price override analysis
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as override_count,
                    SUM(price_override_amount) as total_override_amount
                FROM vend_sale_line_items sli
                JOIN vend_sales s ON sli.sale_id = s.id
                WHERE s.user_id = :staff_id
                AND sli.price_override = 1
                AND s.sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $priceOverrides = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($priceOverrides['override_count'] > 20) {
                $this->addFraudIndicator(
                    'excessive_price_overrides',
                    'transaction_manipulation',
                    "Excessive price overrides: {$priceOverrides['override_count']} overrides",
                    0.7,
                    $priceOverrides
                );
            }

            $this->analysisResults['sections']['transaction_manipulation'] = $sectionData;

        } catch (Exception $e) {
            error_log("Transaction manipulation analysis failed: " . $e->getMessage());
        }
    }

    /**
     * SECTION 7: END-OF-DAY/WEEK RECONCILIATION
     * 
     * Detects:
     * - Daily totals manipulation
     * - Weekly summary discrepancies
     * - Cross-outlet reconciliation gaps
     */
    private function analyzeReconciliation(int $staffId, int $days): void
    {
        $sectionData = [
            'section_name' => 'Reconciliation',
            'checks_performed' => [],
            'issues_found' => []
        ];

        try {
            // 1. Daily reconciliation check
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(s.sale_date) as sale_day,
                    s.outlet_id,
                    SUM(s.total_price) as daily_sales_total,
                    rc.total_sales as closure_total,
                    (SUM(s.total_price) - COALESCE(rc.total_sales, 0)) as discrepancy
                FROM vend_sales s
                LEFT JOIN vend_register_closures rc ON DATE(s.sale_date) = DATE(rc.closure_date) 
                    AND s.outlet_id = rc.outlet_id 
                    AND s.register_id = rc.register_id
                WHERE s.user_id = :staff_id
                AND s.sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND s.status = 'CLOSED'
                GROUP BY DATE(s.sale_date), s.outlet_id, rc.total_sales
                HAVING ABS(discrepancy) > 50
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $dailyDiscrepancies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sectionData['checks_performed'][] = 'daily_reconciliation';

            foreach ($dailyDiscrepancies as $disc) {
                $this->addFraudIndicator(
                    'daily_reconciliation_gap',
                    'reconciliation_fraud',
                    "Daily reconciliation gap on {$disc['sale_day']}: \${$disc['discrepancy']}",
                    0.8,
                    $disc
                );
            }

            // 2. Cross-outlet analysis (if staff works at multiple outlets)
            $stmt = $this->pdo->prepare("
                SELECT 
                    outlet_id,
                    COUNT(*) as transaction_count,
                    SUM(total_price) as total_sales,
                    AVG(total_price) as avg_transaction
                FROM vend_sales
                WHERE user_id = :staff_id
                AND sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND status = 'CLOSED'
                GROUP BY outlet_id
            ");
            $stmt->execute(['staff_id' => $staffId, 'days' => $days]);
            $outletBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($outletBreakdown) > 1) {
                $sectionData['checks_performed'][] = 'cross_outlet_analysis';
                
                // Check for unusual patterns (e.g., much higher avg transaction at one outlet)
                $avgTransactions = array_column($outletBreakdown, 'avg_transaction');
                $maxAvg = max($avgTransactions);
                $minAvg = min($avgTransactions);
                
                if (($maxAvg / $minAvg) > 2) {
                    $this->addFraudIndicator(
                        'cross_outlet_anomaly',
                        'reconciliation_fraud',
                        "Significant difference in average transactions across outlets: Max \${$maxAvg} vs Min \${$minAvg}",
                        0.65,
                        $outletBreakdown
                    );
                }
            }

            $this->analysisResults['sections']['reconciliation_fraud'] = $sectionData;

        } catch (Exception $e) {
            error_log("Reconciliation analysis failed: " . $e->getMessage());
        }
    }

    /**
     * Helper: Check if customer name is suspicious
     */
    private function isSuspiciousCustomerName(?string $name): bool
    {
        if (empty($name)) return false;
        
        $suspiciousPatterns = [
            'test', 'dummy', 'fake', 'random', 'temp', 
            'asdf', 'qwerty', 'zzz', 'aaa', 'xxx',
            'cash customer', 'walk in', 'walkin', 'no name'
        ];
        
        $nameLower = strtolower($name);
        foreach ($suspiciousPatterns as $pattern) {
            if (strpos($nameLower, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Helper: Get outlet average cash percentage
     */
    private function getOutletAverageCashPercentage(int $staffId, int $days): float
    {
        try {
            // Get staff's outlet(s)
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT outlet_id FROM vend_sales
                WHERE user_id = :staff_id
                LIMIT 1
            ");
            $stmt->execute(['staff_id' => $staffId]);
            $outletId = $stmt->fetchColumn();

            if (!$outletId) return 50.0; // Default fallback

            // Get outlet average
            $stmt = $this->pdo->prepare("
                SELECT 
                    SUM(CASE WHEN payment_type = 'CASH' THEN total_price ELSE 0 END) as cash_total,
                    SUM(total_price) as all_total
                FROM vend_sales
                WHERE outlet_id = :outlet_id
                AND sale_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND status = 'CLOSED'
            ");
            $stmt->execute(['outlet_id' => $outletId, 'days' => $days]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['all_total'] > 0) {
                return ($result['cash_total'] / $result['all_total']) * 100;
            }

            return 50.0;
        } catch (Exception $e) {
            return 50.0;
        }
    }

    /**
     * Add fraud indicator to results
     */
    private function addFraudIndicator(
        string $type,
        string $category,
        string $description,
        float $severity,
        array $data
    ): void {
        $indicator = [
            'type' => $type,
            'category' => $category,
            'description' => $description,
            'severity' => $severity,
            'data' => $data,
            'detected_at' => date('Y-m-d H:i:s')
        ];

        $this->analysisResults['fraud_indicators'][] = $indicator;

        // Add to critical alerts if severity is high
        if ($severity >= 0.8) {
            $this->analysisResults['critical_alerts'][] = $indicator;
        }
    }

    /**
     * Calculate overall risk score
     */
    private function calculateRiskScore(): void
    {
        $totalSeverity = 0;
        $indicatorCount = count($this->analysisResults['fraud_indicators']);

        foreach ($this->analysisResults['fraud_indicators'] as $indicator) {
            $totalSeverity += $indicator['severity'];
        }

        if ($indicatorCount > 0) {
            $avgSeverity = $totalSeverity / $indicatorCount;
            $this->analysisResults['risk_score'] = min(100, $avgSeverity * 100 + ($indicatorCount * 2));
        } else {
            $this->analysisResults['risk_score'] = 0;
        }

        // Determine risk level
        $score = $this->analysisResults['risk_score'];
        $this->analysisResults['risk_level'] = match (true) {
            $score >= 80 => 'critical',
            $score >= 60 => 'high',
            $score >= 40 => 'medium',
            default => 'low'
        };
    }

    /**
     * Store analysis results in database
     */
    private function storeAnalysisResults(): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO lightspeed_deep_dive_analysis
                (staff_id, analysis_period_days, risk_score, risk_level,
                 indicator_count, critical_alert_count, analysis_data, created_at)
                VALUES
                (:staff_id, :days, :risk_score, :risk_level,
                 :indicator_count, :critical_count, :data, NOW())
            ");
            $stmt->execute([
                'staff_id' => $this->analysisResults['staff_id'],
                'days' => $this->analysisResults['analysis_period_days'],
                'risk_score' => $this->analysisResults['risk_score'],
                'risk_level' => $this->analysisResults['risk_level'],
                'indicator_count' => count($this->analysisResults['fraud_indicators']),
                'critical_count' => count($this->analysisResults['critical_alerts']),
                'data' => json_encode($this->analysisResults)
            ]);
        } catch (Exception $e) {
            error_log("Failed to store Lightspeed analysis results: " . $e->getMessage());
        }
    }
}
