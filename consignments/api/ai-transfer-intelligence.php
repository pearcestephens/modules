<?php
declare(strict_types=1);

/**
 * 🤖 AI TRANSFER INTELLIGENCE SYSTEM
 *
 * Machine learning-based transfer optimization:
 * - Predicts optimal transfer timing (not weekly, when needed)
 * - Learns from historical data
 * - Considers regional delivery times
 * - Optimizes for day-of-week patterns
 * - Predicts stock velocity
 * - Minimizes waste & dead stock
 *
 * @package CIS\AI\TransferIntelligence
 * @version 1.0.0
 * @created 2025-11-13
 */

class AITransferIntelligence
{
    protected $pdo;
    protected $config;

    // Historical data for learning
    protected $transfers_history = [];
    protected $velocity_history = [];
    protected $regional_delivery_times = [
        'Auckland' => 1,
        'Wellington' => 2,
        'Christchurch' => 2,
        'Dunedin' => 3,
        'Hamilton' => 1,
        'Tauranga' => 1,
    ];

    public function __construct($pdo, $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'min_data_points' => 20,        // Need 20+ data points to start learning
            'learning_window_days' => 90,   // Learn from last 90 days
            'confidence_threshold' => 0.7,  // 70% confidence needed
            're_train_frequency' => 'daily',
        ], $config);
    }

    /**
     * ============================================================================
     * MAIN INTELLIGENCE ENGINE
     * ============================================================================
     *
     * Determines WHEN a transfer should happen and HOW SOON
     * Returns: {should_transfer, optimal_send_date, confidence, reasoning}
     */
    public function analyzeTransferTiming($productId, $fromOutlet, $toOutlet)
    {
        $analysis = [];

        try {
            // Step 1: Get product & outlet data
            $productData = $this->getProductData($productId);
            $outletData = $this->getOutletData($fromOutlet, $toOutlet);
            $deliveryTime = $this->getDeliveryTime($fromOutlet, $toOutlet);

            // Step 2: Analyze current situation
            $currentState = $this->analyzeCurrentState($productId, $fromOutlet, $toOutlet);

            // Step 3: Predict future demand
            $futureDemand = $this->predictFutureDemand($productId, $toOutlet);

            // Step 4: Predict stock velocity
            $velocity = $this->predictStockVelocity($productId, $toOutlet);

            // Step 5: Find optimal send date
            $optimalDate = $this->calculateOptimalSendDate(
                $currentState,
                $futureDemand,
                $velocity,
                $deliveryTime
            );

            // Step 6: Assess confidence
            $confidence = $this->assessConfidence($productId, $toOutlet);

            // Step 7: Reasoning
            $reasoning = $this->generateReasoning(
                $currentState,
                $futureDemand,
                $velocity,
                $optimalDate
            );

            return [
                'product_id' => $productId,
                'from_outlet' => $fromOutlet,
                'to_outlet' => $toOutlet,
                'should_transfer' => $confidence >= $this->config['confidence_threshold'],
                'optimal_send_date' => $optimalDate['date'],
                'days_until_send' => $optimalDate['days_from_now'],
                'estimated_arrival' => $optimalDate['arrival_date'],
                'confidence' => (float)$confidence,
                'confidence_factors' => $optimalDate['confidence_factors'],
                'reasoning' => $reasoning,
                'predicted_velocity' => (float)$velocity['units_per_day'],
                'stock_projection' => $velocity['stock_projection'],
                'demand_forecast' => $futureDemand
            ];

        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * ============================================================================
     * STEP 1: ANALYZE CURRENT STATE
     * ============================================================================
     */
    protected function analyzeCurrentState($productId, $fromOutlet, $toOutlet)
    {
        $stmt = $this->pdo->prepare("
            SELECT
                -- From outlet
                (SELECT quantity FROM outlet_inventory WHERE product_id = ? AND outlet_id = ?) as from_qty,
                (SELECT MIN(quantity) FROM outlet_inventory oi
                 WHERE oi.product_id = ? AND oi.outlet_id != ?) as min_outlet_qty,
                (SELECT AVG(quantity) FROM outlet_inventory
                 WHERE product_id = ?) as avg_outlet_qty,

                -- To outlet
                (SELECT quantity FROM outlet_inventory WHERE product_id = ? AND outlet_id = ?) as to_qty,

                -- Days since last transfer of this product
                COALESCE(
                    DATEDIFF(NOW(), MAX(c.created_at)),
                    999
                ) as days_since_last_transfer
            FROM consignments c
            WHERE c.transfer_type = 'STOCK'
        ");

        $stmt->execute([$productId, $fromOutlet, $productId, $toOutlet, $productId, $productId, $toOutlet]);
        $state = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'from_qty' => (int)$state['from_qty'],
            'to_qty' => (int)$state['to_qty'],
            'min_outlet_qty' => (int)$state['min_outlet_qty'],
            'avg_outlet_qty' => (float)$state['avg_outlet_qty'],
            'imbalance_ratio' => $state['avg_outlet_qty'] > 0
                ? ((int)$state['from_qty'] / (float)$state['avg_outlet_qty'])
                : 0,
            'days_since_last_transfer' => (int)$state['days_since_last_transfer']
        ];
    }

    /**
     * ============================================================================
     * STEP 2: PREDICT FUTURE DEMAND (30 days out)
     * ============================================================================
     *
     * Uses historical sales data + seasonality + day-of-week patterns
     */
    protected function predictFutureDemand($productId, $outletId)
    {
        // Get historical sales
        $stmt = $this->pdo->prepare("
            SELECT
                DATE(transaction_date) as sale_date,
                DAYNAME(transaction_date) as day_name,
                COUNT(*) as units_sold,
                WEEK(transaction_date) as week_num,
                MONTH(transaction_date) as month_num
            FROM lightspeed_sales
            WHERE product_id = ? AND outlet_id = ?
              AND transaction_date >= DATE_SUB(NOW(), INTERVAL 180 DAY)
            GROUP BY DATE(transaction_date)
            ORDER BY transaction_date DESC
        ");

        $stmt->execute([$productId, $outletId]);
        $salesHistory = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($salesHistory) < $this->config['min_data_points']) {
            // Not enough data - return conservative estimate
            return $this->getConservativeForecast($salesHistory);
        }

        // Analyze patterns
        $patterns = $this->analyzeSalesPatterns($salesHistory);

        // Forecast next 30 days
        $forecast = [];
        for ($i = 0; $i < 30; $i++) {
            $date = date('Y-m-d', strtotime("+$i days"));
            $dayOfWeek = date('l', strtotime($date));
            $forecast[$date] = $patterns['by_day_of_week'][$dayOfWeek] ?? $patterns['average_daily'];
        }

        return [
            'next_7_days' => array_sum(array_slice($forecast, 0, 7)),
            'next_14_days' => array_sum(array_slice($forecast, 0, 14)),
            'next_30_days' => array_sum($forecast),
            'by_day' => $forecast,
            'confidence' => count($salesHistory) >= 60 ? 0.85 : 0.65
        ];
    }

    /**
     * ============================================================================
     * STEP 3: PREDICT STOCK VELOCITY
     * ============================================================================
     *
     * How fast is stock moving? When will they run out?
     */
    protected function predictStockVelocity($productId, $outletId)
    {
        // Get recent sales
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as total_units,
                DATEDIFF(MAX(transaction_date), MIN(transaction_date)) as days_span
            FROM lightspeed_sales
            WHERE product_id = ? AND outlet_id = ?
              AND transaction_date >= DATE_SUB(NOW(), INTERVAL 60 DAY)
        ");

        $stmt->execute([$productId, $outletId]);
        $velocity = $stmt->fetch(\PDO::FETCH_ASSOC);

        $unitsPerDay = $velocity['days_span'] > 0
            ? (int)$velocity['total_units'] / (int)$velocity['days_span']
            : 0;

        // Get current stock
        $currentStock = $this->getOutletStock($productId, $outletId);

        // Calculate stock projection
        $projection = [];
        $stock = $currentStock;
        for ($day = 1; $day <= 30; $day++) {
            $stock -= $unitsPerDay;
            $projection[$day] = max(0, $stock);

            if ($stock <= 0 && !isset($projection['stockout_day'])) {
                $projection['stockout_day'] = $day;
            }
        }

        return [
            'units_per_day' => (float)$unitsPerDay,
            'current_stock' => (int)$currentStock,
            'stock_projection' => $projection,
            'stockout_risk_days' => $projection['stockout_day'] ?? null,
            'trend' => $unitsPerDay > 0 ? 'positive' : 'flat'
        ];
    }

    /**
     * ============================================================================
     * STEP 4: CALCULATE OPTIMAL SEND DATE
     * ============================================================================
     *
     * Takes into account:
     * - Delivery times (don't send on Friday if 2-day delivery)
     * - Stock depletion risk
     * - Demand forecasts
     * - Day-of-week patterns
     */
    protected function calculateOptimalSendDate($currentState, $futureDemand, $velocity, $deliveryTime)
    {
        $today = new DateTime();
        $confidenceFactors = [];

        // Factor 1: Stock imbalance urgency
        $urgency = $currentState['imbalance_ratio'];
        if ($urgency > 1.5) {
            $urgencyDays = 0;  // Send ASAP
            $confidenceFactors['imbalance'] = 0.9;
        } elseif ($urgency > 1.2) {
            $urgencyDays = 1;  // Send soon
            $confidenceFactors['imbalance'] = 0.7;
        } else {
            $urgencyDays = 3;  // Can wait
            $confidenceFactors['imbalance'] = 0.5;
        }

        // Factor 2: Stock-out risk
        $stockoutRisk = $velocity['stockout_risk_days'];
        if ($stockoutRisk && $stockoutRisk <= 7) {
            // High risk of stockout - send ASAP
            $urgencyDays = 0;
            $confidenceFactors['stockout_risk'] = 0.95;
        } else {
            $confidenceFactors['stockout_risk'] = 0.3;
        }

        // Factor 3: Optimal day of week
        $optimalSendDay = $this->getOptimalDayOfWeek($deliveryTime);

        // Find next occurrence of optimal day
        $sendDate = clone $today;
        $daysUntilOptimal = $this->daysUntil($sendDate, $optimalSendDay);

        // But don't wait longer than urgency suggests
        if ($daysUntilOptimal > $urgencyDays) {
            $sendDate->modify("+{$urgencyDays} days");
            $confidenceFactors['timing'] = 0.6;
        } else {
            $sendDate->modify("+{$daysUntilOptimal} days");
            $confidenceFactors['timing'] = 0.9;
        }

        // Ensure we're in the future
        while ($sendDate <= $today) {
            $sendDate->modify('+1 day');
        }

        // Factor 4: Demand forecast alignment
        $demandConfidence = $futureDemand['confidence'];
        $confidenceFactors['demand_forecast'] = $demandConfidence;

        // Calculate arrival date
        $arrivalDate = clone $sendDate;
        $arrivalDate->modify("+{$deliveryTime} days");

        // Overall confidence
        $overallConfidence = array_sum($confidenceFactors) / count($confidenceFactors);

        return [
            'date' => $sendDate->format('Y-m-d'),
            'days_from_now' => (int)$sendDate->diff($today)->days,
            'arrival_date' => $arrivalDate->format('Y-m-d'),
            'day_of_week' => $sendDate->format('l'),
            'confidence_factors' => $confidenceFactors,
            'overall_confidence' => (float)$overallConfidence
        ];
    }

    /**
     * ============================================================================
     * STEP 5: ASSESS OVERALL CONFIDENCE
     * ============================================================================
     *
     * How confident are we in this recommendation?
     */
    protected function assessConfidence($productId, $outletId)
    {
        // Count data points
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM lightspeed_sales
            WHERE product_id = ? AND outlet_id = ?
              AND transaction_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");

        $stmt->execute([$productId, $outletId]);
        $count = (int)$stmt->fetch(\PDO::FETCH_COLUMN);

        // Base confidence on data availability
        if ($count < 10) return 0.3;      // Very low
        if ($count < 20) return 0.5;      // Low
        if ($count < 50) return 0.75;     // Medium
        return 0.95;                      // High
    }

    /**
     * ============================================================================
     * LEARNING: ANALYZE PATTERNS
     * ============================================================================
     *
     * Extract patterns from historical sales data
     */
    protected function analyzeSalesPatterns($salesHistory)
    {
        $byDayOfWeek = [];
        $byDayOfMonth = [];
        $allSales = [];

        foreach ($salesHistory as $sale) {
            $allSales[] = (int)$sale['units_sold'];

            $day = $sale['day_name'];
            if (!isset($byDayOfWeek[$day])) {
                $byDayOfWeek[$day] = [];
            }
            $byDayOfWeek[$day][] = (int)$sale['units_sold'];
        }

        // Calculate averages
        $patterns = [
            'average_daily' => array_sum($allSales) / count($allSales),
            'by_day_of_week' => [],
            'trend' => $this->calculateTrend($allSales)
        ];

        foreach ($byDayOfWeek as $day => $sales) {
            $patterns['by_day_of_week'][$day] = array_sum($sales) / count($sales);
        }

        return $patterns;
    }

    /**
     * Calculate trend: is demand going up or down?
     */
    protected function calculateTrend($allSales)
    {
        if (count($allSales) < 5) return 'insufficient_data';

        $recent = array_slice($allSales, 0, 30);
        $older = array_slice($allSales, 30, 30);

        $recentAvg = array_sum($recent) / count($recent);
        $olderAvg = array_sum($older) / count($older);

        if ($recentAvg > $olderAvg * 1.1) return 'increasing';
        if ($recentAvg < $olderAvg * 0.9) return 'decreasing';
        return 'stable';
    }

    /**
     * Get optimal day of week to send
     * (e.g., Monday-Wednesday for 2-day delivery to avoid Friday arrival)
     */
    protected function getOptimalDayOfWeek($deliveryTime)
    {
        // For 1-day delivery: send Mon-Fri
        // For 2-day delivery: send Mon-Thu (so arrives Mon-Thu)
        // For 3+ day delivery: send Mon-Wed

        if ($deliveryTime <= 1) return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        if ($deliveryTime <= 2) return ['Monday', 'Tuesday', 'Wednesday', 'Thursday'];
        return ['Monday', 'Tuesday', 'Wednesday'];
    }

    /**
     * Calculate days until a specific day of week
     */
    protected function daysUntil($date, $targetDays)
    {
        $min = 7;
        foreach ($targetDays as $day) {
            $temp = new DateTime($date->format('Y-m-d'));
            $daysToAdd = 0;
            while ($temp->format('l') !== $day) {
                $temp->modify('+1 day');
                $daysToAdd++;
            }
            $min = min($min, $daysToAdd);
        }
        return $min;
    }

    /**
     * ============================================================================
     * HELPERS
     * ============================================================================
     */

    protected function getProductData($productId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    protected function getOutletData($fromOutlet, $toOutlet)
    {
        $stmt = $this->pdo->prepare("
            SELECT
                (SELECT name FROM outlets WHERE id = ?) as from_name,
                (SELECT name FROM outlets WHERE id = ?) as to_name
        ");
        $stmt->execute([$fromOutlet, $toOutlet]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    protected function getDeliveryTime($fromOutlet, $toOutlet)
    {
        // Get outlet regions and look up delivery time
        $stmt = $this->pdo->prepare("
            SELECT
                (SELECT region FROM outlets WHERE id = ?) as from_region,
                (SELECT region FROM outlets WHERE id = ?) as to_region
        ");
        $stmt->execute([$fromOutlet, $toOutlet]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result['from_region'] === $result['to_region']) {
            return 1;  // Same region = 1 day
        }

        return $this->regional_delivery_times[$result['to_region']] ?? 2;
    }

    protected function getOutletStock($productId, $outletId)
    {
        $stmt = $this->pdo->prepare("
            SELECT quantity FROM outlet_inventory
            WHERE product_id = ? AND outlet_id = ?
        ");
        $stmt->execute([$productId, $outletId]);
        return (int)($stmt->fetch(\PDO::FETCH_COLUMN) ?? 0);
    }

    protected function getConservativeForecast($salesHistory)
    {
        $avg = count($salesHistory) > 0
            ? array_sum(array_column($salesHistory, 'units_sold')) / count($salesHistory)
            : 1;

        return [
            'next_7_days' => ceil($avg * 7),
            'next_14_days' => ceil($avg * 14),
            'next_30_days' => ceil($avg * 30),
            'by_day' => array_fill(0, 30, $avg),
            'confidence' => 0.3
        ];
    }

    protected function generateReasoning($currentState, $futureDemand, $velocity, $optimalDate)
    {
        $reasons = [];

        if ($currentState['imbalance_ratio'] > 1.5) {
            $reasons[] = "Stock imbalance: " . round($currentState['imbalance_ratio'], 1) . "x above average";
        }

        if ($velocity['stockout_risk_days'] && $velocity['stockout_risk_days'] <= 14) {
            $reasons[] = "Risk of stockout in " . $velocity['stockout_risk_days'] . " days";
        }

        $reasons[] = "Forecasted demand: " . $futureDemand['next_7_days'] . " units in next week";

        $reasons[] = "Optimal send day: " . $optimalDate['day_of_week'] .
                   " (arrives " . $optimalDate['arrival_date'] . ")";

        return implode(" | ", $reasons);
    }
}

?>
