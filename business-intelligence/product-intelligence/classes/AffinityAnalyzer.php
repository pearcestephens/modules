<?php
/**
 * Affinity Analyzer
 *
 * Product relationship & cross-sell intelligence:
 * - Purchase basket analysis
 * - Product correlation detection
 * - Bundle recommendation engine
 * - Frequently bought together discovery
 * - Customer segment affinity
 * - Upsell/cross-sell opportunity detection
 *
 * @package IntelligenceHub\Modules\Intelligence
 * @version 1.0.0
 * @author Intelligence Hub Team
 */

namespace IntelligenceHub\Intelligence;

class AffinityAnalyzer {

    private $db;
    private $logger;
    private $min_support = 0.02;     // Minimum 2% of transactions
    private $min_confidence = 0.3;   // Minimum 30% confidence
    private $min_lift = 1.2;         // Minimum 1.2x boost

    /**
     * Constructor
     *
     * @param PDO $db - Database connection
     * @param object $logger - Logging service
     */
    public function __construct($db, $logger = null) {
        $this->db = $db;
        $this->logger = $logger;
    }

    // ============================================================================
    // BASKET ANALYSIS & ASSOCIATION RULES
    // ============================================================================

    /**
     * Analyze product purchase baskets
     *
     * Finds frequently bought together combinations
     * Uses Apriori-like algorithm for association rules
     *
     * @param int $days_back - Historical window in days
     * @param int $min_items - Minimum items in basket
     * @return array - Association rules (A -> B)
     */
    public function analyzeBasketAssociations($days_back = 180, $min_items = 2) {
        try {
            // Get transactions (baskets)
            $stmt = $this->db->prepare("
                SELECT
                    vs.order_id,
                    JSON_ARRAYAGG(DISTINCT vs.product_id) as products
                FROM vend_sales vs
                WHERE vs.sale_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND vs.quantity > 0
                GROUP BY vs.order_id
                HAVING COUNT(DISTINCT vs.product_id) >= ?
                ORDER BY vs.sale_date DESC
                LIMIT 5000
            ");
            $stmt->execute([$days_back, $min_items]);
            $transactions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($transactions) < 100) {
                return ['insufficient_data' => true];
            }

            // Build association matrix
            $rules = $this->discoverAssociationRules($transactions);

            // Filter by confidence, support, and lift thresholds
            $filtered_rules = array_filter($rules, function($rule) {
                return $rule['support'] >= $this->min_support &&
                       $rule['confidence'] >= $this->min_confidence &&
                       $rule['lift'] >= $this->min_lift;
            });

            // Sort by lift (most interesting first)
            usort($filtered_rules, function($a, $b) {
                return $b['lift'] <=> $a['lift'];
            });

            return [
                'analysis_date' => date('Y-m-d H:i:s'),
                'total_transactions' => count($transactions),
                'total_rules' => count($rules),
                'filtered_rules' => count($filtered_rules),
                'rules' => array_slice($filtered_rules, 0, 100), // Top 100 rules
                'thresholds' => [
                    'min_support' => $this->min_support,
                    'min_confidence' => $this->min_confidence,
                    'min_lift' => $this->min_lift
                ]
            ];

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Basket analysis failed: " . $e->getMessage());
            }
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Discover association rules from transactions
     *
     * Calculates support, confidence, lift for all pairs
     *
     * Support(A,B) = P(A ∩ B) = (transactions with both A and B) / total
     * Confidence(A->B) = P(B|A) = P(A,B) / P(A)
     * Lift(A->B) = Confidence / P(B)
     *
     * @param array $transactions - Array of transactions (each with products array)
     * @return array - Association rules
     */
    private function discoverAssociationRules($transactions) {
        $rules = [];
        $total_transactions = count($transactions);

        // Count single items
        $item_counts = [];
        $pairs_counts = [];

        foreach ($transactions as $trans) {
            $products = json_decode($trans['products'], true);
            if (!is_array($products)) continue;

            // Count individual products
            foreach ($products as $product) {
                $item_counts[$product] = ($item_counts[$product] ?? 0) + 1;
            }

            // Count product pairs
            for ($i = 0; $i < count($products); $i++) {
                for ($j = $i + 1; $j < count($products); $j++) {
                    $a = min($products[$i], $products[$j]);
                    $b = max($products[$i], $products[$j]);
                    $pair_key = "$a-$b";
                    $pairs_counts[$pair_key] = ($pairs_counts[$pair_key] ?? 0) + 1;
                }
            }
        }

        // Calculate metrics
        foreach ($pairs_counts as $pair_key => $pair_count) {
            list($product_a, $product_b) = explode('-', $pair_key);

            $support = $pair_count / $total_transactions;
            $confidence_ab = $pair_count / ($item_counts[$product_a] ?? 1);
            $confidence_ba = $pair_count / ($item_counts[$product_b] ?? 1);
            $prob_b = ($item_counts[$product_b] ?? 0) / $total_transactions;
            $prob_a = ($item_counts[$product_a] ?? 0) / $total_transactions;

            $lift_ab = $prob_b > 0 ? $confidence_ab / $prob_b : 0;
            $lift_ba = $prob_a > 0 ? $confidence_ba / $prob_a : 0;

            // Store both directions
            if ($support >= $this->min_support) {
                $rules[] = [
                    'antecedent' => $product_a,
                    'consequent' => $product_b,
                    'support' => round($support, 4),
                    'confidence' => round($confidence_ab, 4),
                    'lift' => round($lift_ab, 4),
                    'count' => $pair_count
                ];

                $rules[] = [
                    'antecedent' => $product_b,
                    'consequent' => $product_a,
                    'support' => round($support, 4),
                    'confidence' => round($confidence_ba, 4),
                    'lift' => round($lift_ba, 4),
                    'count' => $pair_count
                ];
            }
        }

        return $rules;
    }

    // ============================================================================
    // PRODUCT CORRELATION ANALYSIS
    // ============================================================================

    /**
     * Calculate correlation matrix for products
     *
     * Measures how often products are bought together
     * (Pearson correlation of purchase patterns)
     *
     * @param int $limit - Max products to analyze
     * @return array - Correlation matrix
     */
    public function calculateProductCorrelation($limit = 50) {
        try {
            // Get top products
            $stmt = $this->db->prepare("
                SELECT product_id
                FROM vend_sales
                WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                GROUP BY product_id
                ORDER BY SUM(quantity) DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $products = array_map(fn($p) => $p['product_id'],
                                 $stmt->fetchAll(\PDO::FETCH_ASSOC));

            // Build co-occurrence matrix
            $correlation = [];

            foreach ($products as $p1) {
                $correlation[$p1] = [];

                foreach ($products as $p2) {
                    if ($p1 === $p2) {
                        $correlation[$p1][$p2] = 1.0;
                        continue;
                    }

                    // Count orders with both products
                    $stmt = $this->db->prepare("
                        SELECT COUNT(DISTINCT order_id) as co_purchases
                        FROM vend_sales vs1
                        WHERE vs1.product_id = ?
                        AND EXISTS (
                            SELECT 1 FROM vend_sales vs2
                            WHERE vs2.order_id = vs1.order_id
                            AND vs2.product_id = ?
                        )
                        AND vs1.sale_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                    ");
                    $stmt->execute([$p1, $p2]);
                    $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                    // Count orders with p1
                    $stmt = $this->db->prepare("
                        SELECT COUNT(DISTINCT order_id) as total
                        FROM vend_sales
                        WHERE product_id = ?
                        AND sale_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                    ");
                    $stmt->execute([$p1]);
                    $p1_count = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];

                    $correlation_coeff = $p1_count > 0 ?
                        round($result['co_purchases'] / $p1_count, 3) : 0;

                    $correlation[$p1][$p2] = $correlation_coeff;
                }
            }

            return [
                'generated_at' => date('Y-m-d H:i:s'),
                'products_analyzed' => count($products),
                'correlation_matrix' => $correlation
            ];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // ============================================================================
    // BUNDLE RECOMMENDATIONS
    // ============================================================================

    /**
     * Generate bundle recommendations
     *
     * Groups products that should be sold together
     *
     * @param int $product_id - Anchor product for bundle
     * @param int $bundle_size - How many items in bundle (2-5)
     * @return array - Recommended bundles
     */
    public function generateBundleRecommendations($product_id, $bundle_size = 3) {
        try {
            // Get strong correlations for this product
            $stmt = $this->db->prepare("
                SELECT DISTINCT vs1.product_id
                FROM vend_sales vs1
                INNER JOIN vend_sales vs2 ON vs1.order_id = vs2.order_id
                WHERE vs2.product_id = ?
                AND vs1.product_id != ?
                AND vs1.sale_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                GROUP BY vs1.product_id
                ORDER BY COUNT(*) DESC
                LIMIT ?
            ");
            $stmt->execute([$product_id, $product_id, $bundle_size + 5]);
            $correlated = array_map(fn($p) => $p['product_id'],
                                   $stmt->fetchAll(\PDO::FETCH_ASSOC));

            if (count($correlated) < $bundle_size - 1) {
                return ['insufficient_correlations' => true];
            }

            // Create bundle
            $bundle = [$product_id];
            $bundle = array_merge($bundle, array_slice($correlated, 0, $bundle_size - 1));

            // Calculate bundle metrics
            $bundle_stats = $this->calculateBundleMetrics($bundle);

            return [
                'anchor_product' => $product_id,
                'bundle_size' => count($bundle),
                'bundle_products' => $bundle,
                'metrics' => $bundle_stats,
                'suggested_bundle_price' => round($bundle_stats['total_avg_price'] * 0.95, 2), // 5% discount
                'expected_boost' => $bundle_stats['co_purchase_rate'] * 100 . '%'
            ];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Calculate metrics for a bundle
     *
     * @param array $products - Product IDs in bundle
     * @return array - Bundle metrics
     */
    private function calculateBundleMetrics($products) {
        $placeholders = implode(',', array_fill(0, count($products), '?'));

        // Get average prices
        $stmt = $this->db->prepare("
            SELECT AVG(price) as avg_price
            FROM price_history_daily
            WHERE product_id IN ($placeholders)
            AND created_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            AND competitor_name = 'Our Store'
        ");
        $stmt->execute($products);
        $price_data = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Calculate co-purchase rate
        $co_purchase_count = 0;
        for ($i = 0; $i < count($products); $i++) {
            for ($j = $i + 1; $j < count($products); $j++) {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as count
                    FROM vend_sales vs1
                    WHERE vs1.product_id = ?
                    AND EXISTS (
                        SELECT 1 FROM vend_sales vs2
                        WHERE vs2.order_id = vs1.order_id
                        AND vs2.product_id = ?
                    )
                ");
                $stmt->execute([$products[$i], $products[$j]]);
                $co_purchase_count += $stmt->fetch(\PDO::FETCH_ASSOC)['count'];
            }
        }

        // Get total sales of first product
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM vend_sales
            WHERE product_id = ?
            AND sale_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        $stmt->execute([$products[0]]);
        $first_product_sales = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];

        return [
            'total_avg_price' => round($price_data['avg_price'] * count($products), 2),
            'average_unit_price' => round($price_data['avg_price'], 2),
            'co_purchase_rate' => $first_product_sales > 0 ?
                                  round($co_purchase_count / $first_product_sales, 3) : 0,
            'product_count' => count($products)
        ];
    }

    // ============================================================================
    // CROSS-SELL & UPSELL DETECTION
    // ============================================================================

    /**
     * Identify cross-sell opportunities
     *
     * Products NOT currently bought together but should be
     *
     * @param int $product_id - Product to cross-sell
     * @param int $limit - Top N opportunities
     * @return array - Cross-sell recommendations
     */
    public function identifyCrossSellOpportunities($product_id, $limit = 10) {
        try {
            // Get customers who bought this product
            $stmt = $this->db->prepare("
                SELECT DISTINCT customer_id
                FROM vend_sales
                WHERE product_id = ?
                AND sale_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                LIMIT 100
            ");
            $stmt->execute([$product_id]);
            $customers = array_map(fn($c) => $c['customer_id'],
                                  $stmt->fetchAll(\PDO::FETCH_ASSOC));

            if (empty($customers)) {
                return ['no_customers' => true];
            }

            // Find products these customers bought (excluding the product itself)
            $placeholders = implode(',', array_fill(0, count($customers), '?'));
            $stmt = $this->db->prepare("
                SELECT
                    product_id,
                    COUNT(*) as purchase_count,
                    AVG(quantity) as avg_quantity
                FROM vend_sales
                WHERE customer_id IN ($placeholders)
                AND product_id != ?
                AND sale_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                GROUP BY product_id
                ORDER BY COUNT(*) DESC
                LIMIT ?
            ");
            $stmt->execute(array_merge($customers, [$product_id, $limit]));
            $opportunities = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return [
                'primary_product' => $product_id,
                'customer_base' => count($customers),
                'cross_sell_opportunities' => $opportunities,
                'generated_at' => date('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Identify upsell opportunities
     *
     * Higher-value alternatives to current product
     *
     * @param int $product_id - Product to upsell from
     * @param float $margin - Minimum price margin (1.2 = 20% higher)
     * @return array - Upsell recommendations
     */
    public function identifyUpsellOpportunities($product_id, $margin = 1.2) {
        try {
            // Get current product price and category
            $stmt = $this->db->prepare("
                SELECT price, product_name, category
                FROM vend_products
                WHERE product_id = ?
            ");
            $stmt->execute([$product_id]);
            $current = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$current) {
                return ['product_not_found' => true];
            }

            $target_price = $current['price'] * $margin;

            // Find similar products at higher price points
            $stmt = $this->db->prepare("
                SELECT
                    product_id,
                    product_name,
                    price,
                    ROUND(100 * (price - ?) / ?, 1) as price_uplift
                FROM vend_products
                WHERE category = ?
                AND product_id != ?
                AND price >= ?
                AND price <= ?
                AND active = 1
                ORDER BY price ASC
                LIMIT 10
            ");
            $stmt->execute([
                $current['price'],
                $current['price'],
                $current['category'],
                $product_id,
                $target_price,
                $target_price * 1.5
            ]);
            $upsells = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return [
                'current_product' => [
                    'id' => $product_id,
                    'name' => $current['product_name'],
                    'price' => $current['price']
                ],
                'upsell_opportunities' => $upsells,
                'target_price_range' => [
                    'min' => round($target_price, 2),
                    'max' => round($target_price * 1.5, 2)
                ]
            ];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // ============================================================================
    // CUSTOMER SEGMENT AFFINITY
    // ============================================================================

    /**
     * Analyze affinity by customer segment
     *
     * Different segments have different buying patterns
     *
     * @param string $segment - Customer segment code
     * @return array - Segment-specific affinities
     */
    public function analyzeSegmentAffinity($segment = 'high_value') {
        try {
            // Map segments to customer criteria
            $segment_criteria = $this->getSegmentCriteria($segment);

            // Get qualifying customers
            $stmt = $this->db->prepare($segment_criteria['query']);
            $stmt->execute($segment_criteria['params']);
            $customers = array_map(fn($c) => $c['customer_id'],
                                  $stmt->fetchAll(\PDO::FETCH_ASSOC));

            if (empty($customers)) {
                return ['no_customers_in_segment' => true];
            }

            // Get product preferences for segment
            $placeholders = implode(',', array_fill(0, count($customers), '?'));
            $stmt = $this->db->prepare("
                SELECT
                    product_id,
                    COUNT(*) as purchase_count,
                    AVG(quantity) as avg_qty,
                    SUM(total_price) as total_revenue
                FROM vend_sales
                WHERE customer_id IN ($placeholders)
                GROUP BY product_id
                ORDER BY COUNT(*) DESC
                LIMIT 20
            ");
            $stmt->execute($customers);
            $preferences = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return [
                'segment' => $segment,
                'customer_count' => count($customers),
                'total_transactions' => array_sum(array_column($preferences, 'purchase_count')),
                'total_revenue' => array_sum(array_column($preferences, 'total_revenue')),
                'product_preferences' => $preferences
            ];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get segment criteria
     *
     * @param string $segment - Segment name
     * @return array - Query and params
     */
    private function getSegmentCriteria($segment) {
        $criteria = [
            'high_value' => [
                'query' => "
                    SELECT DISTINCT customer_id
                    FROM vend_sales
                    WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                    GROUP BY customer_id
                    HAVING SUM(total_price) >= 500
                    ORDER BY SUM(total_price) DESC
                ",
                'params' => []
            ],
            'frequent_buyer' => [
                'query' => "
                    SELECT DISTINCT customer_id
                    FROM vend_sales
                    WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                    GROUP BY customer_id
                    HAVING COUNT(*) >= 10
                ",
                'params' => []
            ],
            'new_customer' => [
                'query' => "
                    SELECT DISTINCT customer_id
                    FROM vend_sales
                    WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    AND customer_id NOT IN (
                        SELECT customer_id FROM vend_sales
                        WHERE sale_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
                    )
                ",
                'params' => []
            ],
            'at_risk' => [
                'query' => "
                    SELECT DISTINCT customer_id
                    FROM vend_sales
                    WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 180 DAY)
                    AND sale_date < DATE_SUB(NOW(), INTERVAL 90 DAY)
                    AND customer_id NOT IN (
                        SELECT customer_id FROM vend_sales
                        WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                    )
                ",
                'params' => []
            ]
        ];

        return $criteria[$segment] ?? $criteria['high_value'];
    }

    // ============================================================================
    // COMPREHENSIVE AFFINITY REPORTS
    // ============================================================================

    /**
     * Generate complete affinity report for product
     *
     * @param int $product_id - Product to analyze
     * @return array - Comprehensive affinity report
     */
    public function generateProductAffinityReport($product_id) {
        return [
            'product_id' => $product_id,
            'generated_at' => date('Y-m-d H:i:s'),
            'basket_associations' => $this->analyzeBasketAssociations(),
            'correlations' => $this->calculateProductCorrelation(),
            'bundles' => $this->generateBundleRecommendations($product_id),
            'cross_sell' => $this->identifyCrossSellOpportunities($product_id),
            'upsell' => $this->identifyUpsellOpportunities($product_id),
            'segment_affinities' => [
                'high_value' => $this->analyzeSegmentAffinity('high_value'),
                'frequent_buyer' => $this->analyzeSegmentAffinity('frequent_buyer'),
                'new_customer' => $this->analyzeSegmentAffinity('new_customer')
            ]
        ];
    }
}

// ============================================================================
// USAGE EXAMPLES (For Reference)
// ============================================================================

/*

// Initialize affinity analyzer
$db = new PDO('mysql:host=localhost;dbname=your_db', 'user', 'pass');
$analyzer = new AffinityAnalyzer($db);

// 1. Analyze basket associations
$baskets = $analyzer->analyzeBasketAssociations(90);
foreach ($baskets['rules'] as $rule) {
    echo "Product " . $rule['antecedent'] . " leads to " . $rule['consequent'];
    echo " (Confidence: " . ($rule['confidence']*100) . "%, Lift: " . $rule['lift'] . ")";
}

// 2. Calculate product correlation
$corr = $analyzer->calculateProductCorrelation();
echo "Products analyzed: " . $corr['products_analyzed'];

// 3. Generate bundle recommendations
$bundle = $analyzer->generateBundleRecommendations(123);
echo "Bundle: " . implode(', ', $bundle['bundle_products']);
echo "Suggested price: $" . $bundle['suggested_bundle_price'];

// 4. Find cross-sell opportunities
$cross = $analyzer->identifyCrossSellOpportunities(123);
foreach ($cross['cross_sell_opportunities'] as $opp) {
    echo "Also sell: Product " . $opp['product_id'];
}

// 5. Find upsell opportunities
$upsell = $analyzer->identifyUpsellOpportunities(123, 1.3); // 30% higher
foreach ($upsell['upsell_opportunities'] as $u) {
    echo $u['product_name'] . " (+" . $u['price_uplift'] . "%)";
}

// 6. Segment-specific analysis
$segment = $analyzer->analyzeSegmentAffinity('high_value');
echo "High-value customers spent: $" . $segment['total_revenue'];

// 7. Complete report
$report = $analyzer->generateProductAffinityReport(123);

*/
?>
