<?php
/**
 * Scientific Analyzer Engine
 *
 * Provides comprehensive statistical and scientific analysis for:
 * - Price trend analysis (moving averages, linear regression)
 * - Volatility measurement (standard deviation, confidence intervals)
 * - Seasonality detection (recurring patterns)
 * - Price elasticity estimation (demand response)
 * - Anomaly detection (unusual prices)
 * - Market analysis (competitive positioning)
 *
 * @package IntelligenceHub\Modules\Intelligence
 * @version 1.0.0
 * @author Intelligence Hub Team
 */

namespace IntelligenceHub\Intelligence;

class ScientificAnalyzer {

    private $db;
    private $logger;

    /**
     * Constructor
     */
    public function __construct($db, $logger = null) {
        $this->db = $db;
        $this->logger = $logger;
    }

    // ============================================================================
    // STATISTICAL ANALYSIS METHODS
    // ============================================================================

    /**
     * Calculate moving average over N periods
     *
     * Smooths out daily price fluctuations to reveal underlying trends
     * Formula: MA(n) = SUM(prices last n days) / n
     *
     * @param array $prices - Array of prices in chronological order
     * @param int $period - Window size (7 for 7-day, 30 for 30-day)
     * @return array - Array of moving averages with dates
     */
    public function calculateMovingAverage($prices, $period = 7) {
        if (empty($prices) || count($prices) < $period) {
            return [];
        }

        $movingAverages = [];

        for ($i = $period - 1; $i < count($prices); $i++) {
            $window = array_slice($prices, $i - $period + 1, $period);
            $sum = array_sum($window);
            $average = $sum / $period;

            $movingAverages[] = [
                'index' => $i,
                'value' => round($average, 2),
                'price' => $prices[$i]
            ];
        }

        return $movingAverages;
    }

    /**
     * Calculate linear regression to detect price trends
     *
     * Fits a line to the data: y = mx + b
     * Returns slope (trend direction), intercept, and R-squared (fit quality)
     *
     * @param array $prices - Historical prices
     * @param int $lookbackDays - How many days to analyze
     * @return array - Contains direction, slope, strength (0-1), R-squared
     */
    public function detectPriceTrend($prices, $lookbackDays = 30) {
        $prices = array_slice($prices, -$lookbackDays);

        if (count($prices) < 2) {
            return [
                'direction' => 'insufficient_data',
                'slope' => 0,
                'strength' => 0,
                'r_squared' => 0
            ];
        }

        $n = count($prices);
        $x_values = range(1, $n);
        $x_mean = array_sum($x_values) / $n;
        $y_mean = array_sum($prices) / $n;

        // Calculate slope and intercept
        $numerator = 0;
        $denominator = 0;

        for ($i = 0; $i < $n; $i++) {
            $numerator += ($x_values[$i] - $x_mean) * ($prices[$i] - $y_mean);
            $denominator += pow($x_values[$i] - $x_mean, 2);
        }

        $slope = $denominator != 0 ? $numerator / $denominator : 0;
        $intercept = $y_mean - $slope * $x_mean;

        // Calculate R-squared (coefficient of determination)
        $ss_res = 0;
        $ss_tot = 0;

        for ($i = 0; $i < $n; $i++) {
            $predicted = $slope * $x_values[$i] + $intercept;
            $ss_res += pow($prices[$i] - $predicted, 2);
            $ss_tot += pow($prices[$i] - $y_mean, 2);
        }

        $r_squared = $ss_tot != 0 ? 1 - ($ss_res / $ss_tot) : 0;

        // Determine trend direction and strength
        $direction = ($slope > 0) ? 'up' : (($slope < 0) ? 'down' : 'flat');
        $strength = min(abs($slope) / max(abs($y_mean), 0.01) * 100, 100); // 0-100%

        return [
            'direction' => $direction,
            'slope' => round($slope, 4),
            'strength' => round($strength, 2),
            'r_squared' => round($r_squared, 4),
            'intercept' => round($intercept, 2),
            'confidence' => round($r_squared * 100, 0) // How well line fits
        ];
    }

    /**
     * Calculate standard deviation (volatility)
     *
     * Measures how much prices vary from the average
     * Higher stddev = more volatile pricing
     *
     * @param array $prices - Historical prices
     * @return array - Contains std dev, variance, coefficient of variation
     */
    public function calculateVolatility($prices) {
        if (count($prices) < 2) {
            return ['std_dev' => 0, 'variance' => 0, 'cv' => 0];
        }

        $mean = array_sum($prices) / count($prices);
        $sum_squared_diff = 0;

        foreach ($prices as $price) {
            $sum_squared_diff += pow($price - $mean, 2);
        }

        $variance = $sum_squared_diff / count($prices);
        $std_dev = sqrt($variance);
        $cv = ($mean > 0) ? ($std_dev / $mean) * 100 : 0; // Coefficient of variation %

        return [
            'std_dev' => round($std_dev, 2),
            'variance' => round($variance, 2),
            'cv' => round($cv, 2), // % - normalized volatility
            'mean' => round($mean, 2),
            'min' => round(min($prices), 2),
            'max' => round(max($prices), 2),
            'range' => round(max($prices) - min($prices), 2)
        ];
    }

    /**
     * Calculate confidence intervals around a mean price
     *
     * Returns range where price is likely to fall with 95% confidence
     *
     * @param array $prices - Historical prices
     * @param float $confidence_level - 0.95 for 95% confidence
     * @return array - Contains lower and upper bounds
     */
    public function calculateConfidenceIntervals($prices, $confidence_level = 0.95) {
        $n = count($prices);
        $mean = array_sum($prices) / $n;
        $volatility = $this->calculateVolatility($prices);
        $std_dev = $volatility['std_dev'];

        // Z-score for 95% confidence = 1.96
        $z_score = 1.96; // Standard for 95% confidence

        $margin_of_error = $z_score * ($std_dev / sqrt($n));

        return [
            'mean' => round($mean, 2),
            'lower_bound' => round($mean - $margin_of_error, 2),
            'upper_bound' => round($mean + $margin_of_error, 2),
            'margin_of_error' => round($margin_of_error, 2),
            'confidence_level' => $confidence_level * 100
        ];
    }

    /**
     * Detect seasonality patterns in price data
     *
     * Finds recurring seasonal factors (e.g., prices higher in summer)
     * Uses average method for simplicity
     *
     * @param array $prices - Historical prices (minimum 60 days recommended)
     * @param int $period - Season length (7 for weekly, 30 for monthly, 365 for yearly)
     * @return array - Seasonal factors (1.0 = no seasonality)
     */
    public function detectSeasonality($prices, $period = 7) {
        if (count($prices) < $period * 2) {
            return ['insufficient_data' => true];
        }

        // Calculate average for each seasonal period
        $seasonal_factors = [];
        $overall_mean = array_sum($prices) / count($prices);

        for ($i = 0; $i < $period; $i++) {
            $seasonal_prices = [];

            for ($j = $i; $j < count($prices); $j += $period) {
                $seasonal_prices[] = $prices[$j];
            }

            if (!empty($seasonal_prices)) {
                $seasonal_mean = array_sum($seasonal_prices) / count($seasonal_prices);
                $factor = $overall_mean > 0 ? $seasonal_mean / $overall_mean : 1;
                $seasonal_factors[$i] = round($factor, 4);
            }
        }

        return [
            'seasonal_factors' => $seasonal_factors,
            'period' => $period,
            'overall_mean' => round($overall_mean, 2),
            'is_seasonal' => $this->isSignificantlySeisonal($seasonal_factors)
        ];
    }

    /**
     * Detect anomalies (unusual prices)
     *
     * Uses standard deviation method: prices > 2*std_dev from mean are anomalies
     *
     * @param array $prices - Historical prices with dates
     * @param float $threshold - Standard deviations from mean (2.0 = 95% of data)
     * @return array - List of anomalies with indices and percentiles
     */
    public function detectAnomalies($prices, $threshold = 2.0) {
        $volatility = $this->calculateVolatility($prices);
        $mean = $volatility['mean'];
        $std_dev = $volatility['std_dev'];

        $anomalies = [];

        foreach ($prices as $index => $price) {
            $z_score = ($price - $mean) / ($std_dev > 0 ? $std_dev : 1);

            if (abs($z_score) > $threshold) {
                $percentile = ($price - $volatility['min']) / ($volatility['max'] - $volatility['min']) * 100;

                $anomalies[] = [
                    'index' => $index,
                    'price' => $price,
                    'z_score' => round($z_score, 2),
                    'distance_from_mean' => round(abs($price - $mean), 2),
                    'percentile' => round($percentile, 1),
                    'severity' => abs($z_score) > 3 ? 'critical' : 'warning'
                ];
            }
        }

        return [
            'anomalies' => $anomalies,
            'count' => count($anomalies),
            'percentage' => round((count($anomalies) / count($prices)) * 100, 2)
        ];
    }

    // ============================================================================
    // BUSINESS INTELLIGENCE METHODS
    // ============================================================================

    /**
     * Estimate price elasticity of demand
     *
     * Ed = (% change in quantity demanded) / (% change in price)
     * Negative values typical (inverse relationship)
     *
     * @param int $product_id - Product to analyze
     * @param int $lookback_days - Historical period to analyze
     * @return array - Elasticity coefficient and interpretation
     */
    public function estimatePriceElasticity($product_id, $lookback_days = 90) {
        try {
            // Get price history
            $price_stmt = $this->db->prepare("
                SELECT price, recorded_at
                FROM price_history_daily
                WHERE product_id = ?
                AND recorded_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY recorded_at ASC
            ");
            $price_stmt->execute([$product_id, $lookback_days]);
            $price_data = $price_stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Get sales volume history
            $sales_stmt = $this->db->prepare("
                SELECT SUM(quantity) as units_sold, DATE(created_at) as sale_date
                FROM vend_sales
                WHERE product_id = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY sale_date ASC
            ");
            $sales_stmt->execute([$product_id, $lookback_days]);
            $sales_data = $sales_stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($price_data) || empty($sales_data)) {
                return ['insufficient_data' => true];
            }

            // Extract values for regression
            $prices = array_map(fn($p) => (float)$p['price'], $price_data);
            $quantities = array_map(fn($s) => (float)$s['units_sold'], $sales_data);

            // Calculate elasticity using regression
            $elasticity = $this->calculateLinearElasticity($prices, $quantities);

            return [
                'elasticity' => round($elasticity, 3),
                'interpretation' => $this->interpretElasticity($elasticity),
                'data_points' => count($prices),
                'recommended_action' => $this->recommendPriceAction($elasticity)
            ];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Calculate linear regression for elasticity
     *
     * @param array $prices - Price values
     * @param array $quantities - Quantity sold values
     * @return float - Elasticity coefficient
     */
    private function calculateLinearElasticity($prices, $quantities) {
        $n = count($prices);

        // Ensure same length
        if ($n !== count($quantities)) {
            return 0;
        }

        $x_mean = array_sum($prices) / $n;
        $y_mean = array_sum($quantities) / $n;

        $numerator = 0;
        $denominator = 0;

        for ($i = 0; $i < $n; $i++) {
            $numerator += ($prices[$i] - $x_mean) * ($quantities[$i] - $y_mean);
            $denominator += pow($prices[$i] - $x_mean, 2);
        }

        return $denominator != 0 ? $numerator / $denominator : 0;
    }

    /**
     * Interpret elasticity coefficient
     *
     * @param float $elasticity - Elasticity value
     * @return string - Interpretation
     */
    private function interpretElasticity($elasticity) {
        $abs_e = abs($elasticity);

        if ($abs_e > 1) {
            return 'Elastic (demand sensitive to price)';
        } elseif ($abs_e == 1) {
            return 'Unit Elastic (proportional change)';
        } elseif ($abs_e > 0) {
            return 'Inelastic (demand resistant to price)';
        } else {
            return 'Insufficient Data';
        }
    }

    /**
     * Recommend pricing action based on elasticity
     *
     * @param float $elasticity - Elasticity coefficient
     * @return string - Recommendation
     */
    private function recommendPriceAction($elasticity) {
        $abs_e = abs($elasticity);

        if ($abs_e > 1.5) {
            return 'Price reduction may increase revenue (elastic demand)';
        } elseif ($abs_e > 1) {
            return 'Monitor price changes closely (elastic)';
        } elseif ($abs_e > 0.5) {
            return 'Price increases likely to improve margin (inelastic)';
        } else {
            return 'Demand is very inelastic - pricing power exists';
        }
    }

    /**
     * Forecast margin impact of price change
     *
     * Calculates how margin changes with different prices
     *
     * @param float $current_price - Current selling price
     * @param float $cost - Product cost
     * @param float $elasticity - Price elasticity estimate
     * @param float $current_volume - Current units sold
     * @param float $new_price - Proposed new price
     * @return array - Impact analysis
     */
    public function forecastMarginImpact($current_price, $cost, $elasticity, $current_volume, $new_price) {
        // Calculate current metrics
        $current_margin = $current_price - $cost;
        $current_margin_pct = ($current_margin / $current_price) * 100;
        $current_margin_dollars = $current_margin * $current_volume;

        // Estimate new volume based on elasticity
        $price_change_pct = (($new_price - $current_price) / $current_price) * 100;
        $quantity_change_pct = $price_change_pct * $elasticity;
        $new_volume = $current_volume * (1 + ($quantity_change_pct / 100));

        // Calculate new metrics
        $new_margin = $new_price - $cost;
        $new_margin_pct = ($new_margin / $new_price) * 100;
        $new_margin_dollars = $new_margin * $new_volume;

        // Calculate impact
        $margin_dollar_change = $new_margin_dollars - $current_margin_dollars;
        $margin_pct_change = (($new_margin_dollars - $current_margin_dollars) / $current_margin_dollars) * 100;

        return [
            'current' => [
                'price' => round($current_price, 2),
                'margin' => round($current_margin, 2),
                'margin_pct' => round($current_margin_pct, 1),
                'volume' => round($current_volume, 0),
                'total_margin' => round($current_margin_dollars, 2)
            ],
            'projected' => [
                'price' => round($new_price, 2),
                'margin' => round($new_margin, 2),
                'margin_pct' => round($new_margin_pct, 1),
                'volume' => round($new_volume, 0),
                'total_margin' => round($new_margin_dollars, 2)
            ],
            'impact' => [
                'price_change_pct' => round($price_change_pct, 2),
                'volume_change_pct' => round($quantity_change_pct, 2),
                'margin_dollar_change' => round($margin_dollar_change, 2),
                'margin_pct_change' => round($margin_pct_change, 2),
                'recommendation' => $margin_dollar_change > 0 ? 'RECOMMENDED' : 'NOT RECOMMENDED'
            ]
        ];
    }

    /**
     * Calculate competitive positioning
     *
     * Where are we vs competitors for a product?
     *
     * @param float $our_price - Our current price
     * @param array $competitor_prices - Array of competitor prices
     * @return array - Position metrics and percentiles
     */
    public function calculateCompetitivePosition($our_price, $competitor_prices) {
        if (empty($competitor_prices)) {
            return ['insufficient_data' => true];
        }

        $all_prices = array_merge([$our_price], $competitor_prices);
        sort($all_prices);

        $position = array_search($our_price, $all_prices) + 1;
        $total_competitors = count($all_prices);

        $avg_competitor = array_sum($competitor_prices) / count($competitor_prices);
        $lowest = min($competitor_prices);
        $highest = max($competitor_prices);

        return [
            'our_price' => round($our_price, 2),
            'position' => $position . ' of ' . $total_competitors,
            'percentile' => round(($position / $total_competitors) * 100, 0),
            'competitor_average' => round($avg_competitor, 2),
            'price_above_average' => round($our_price - $avg_competitor, 2),
            'lowest_competitor' => round($lowest, 2),
            'highest_competitor' => round($highest, 2),
            'pricing_strategy' => $this->determinePricingStrategy($our_price, $lowest, $highest, $avg_competitor)
        ];
    }

    /**
     * Determine pricing strategy classification
     *
     * @param float $our_price
     * @param float $lowest
     * @param float $highest
     * @param float $average
     * @return string
     */
    private function determinePricingStrategy($our_price, $lowest, $highest, $average) {
        $tolerance = ($highest - $lowest) * 0.05; // 5% tolerance

        if ($our_price <= ($lowest + $tolerance)) {
            return 'PRICE LEADER (lowest)';
        } elseif ($our_price >= ($highest - $tolerance)) {
            return 'PREMIUM PRICING (highest)';
        } elseif (abs($our_price - $average) < $tolerance) {
            return 'MARKET FOLLOWER (average)';
        } elseif ($our_price < $average) {
            return 'DISCOUNT STRATEGY';
        } else {
            return 'PREMIUM STRATEGY';
        }
    }

    // ============================================================================
    // HELPER METHODS
    // ============================================================================

    /**
     * Check if seasonality is statistically significant
     *
     * @param array $seasonal_factors
     * @return bool
     */
    private function isSignificantlySeisonal($seasonal_factors) {
        if (empty($seasonal_factors)) {
            return false;
        }

        $variance = $this->calculateVolatility($seasonal_factors)['variance'];
        return $variance > 0.01; // Threshold for significance
    }

    /**
     * Generate confidence score for a prediction
     *
     * Higher R-squared, lower volatility = higher confidence
     *
     * @param float $r_squared - R-squared from regression
     * @param float $std_dev - Standard deviation
     * @param float $mean - Mean value
     * @return int - Confidence 0-100
     */
    public function generateConfidenceScore($r_squared, $std_dev, $mean) {
        // Start with R-squared (0-100)
        $confidence = $r_squared * 100;

        // Adjust based on volatility
        $cv = $mean > 0 ? ($std_dev / $mean) : 1;

        if ($cv < 0.05) {
            $confidence += 10; // Very stable
        } elseif ($cv < 0.1) {
            $confidence += 5; // Stable
        } elseif ($cv > 0.5) {
            $confidence -= 20; // Very volatile
        }

        return min(100, max(0, (int)$confidence));
    }

    /**
     * Get price statistics summary for a product
     *
     * @param int $product_id
     * @param int $days - Historical period
     * @return array
     */
    public function getProductPriceStatsSummary($product_id, $days = 90) {
        try {
            $stmt = $this->db->prepare("
                SELECT price FROM price_history_daily
                WHERE product_id = ?
                AND created_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY created_date ASC
            ");
            $stmt->execute([$product_id, $days]);
            $prices = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'price');

            if (empty($prices)) {
                return ['insufficient_data' => true];
            }

            $prices = array_map('floatval', $prices);

            return [
                'period_days' => $days,
                'data_points' => count($prices),
                'current_price' => round(end($prices), 2),
                'average_price' => round(array_sum($prices) / count($prices), 2),
                'trend' => $this->detectPriceTrend($prices, $days),
                'volatility' => $this->calculateVolatility($prices),
                'anomalies' => $this->detectAnomalies($prices, 2.0)
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

// ============================================================================
// USAGE EXAMPLES (For Reference)
// ============================================================================

/*

// Initialize analyzer
$db = new PDO('mysql:host=localhost;dbname=your_db', 'user', 'pass');
$analyzer = new ScientificAnalyzer($db);

// 1. Calculate moving averages
$prices = [100, 102, 101, 103, 105, 104, 106];
$ma7 = $analyzer->calculateMovingAverage($prices, 7);

// 2. Detect trends
$trend = $analyzer->detectPriceTrend($prices, 7);
echo "Trend: " . $trend['direction'] . " (" . $trend['strength'] . "% strength)";

// 3. Calculate volatility
$volatility = $analyzer->calculateVolatility($prices);
echo "Volatility: " . $volatility['std_dev'];

// 4. Find anomalies
$anomalies = $analyzer->detectAnomalies($prices, 2.0);
echo "Anomalies found: " . $anomalies['count'];

// 5. Estimate elasticity
$elasticity = $analyzer->estimatePriceElasticity(123, 90);
echo "Product 123 elasticity: " . $elasticity['elasticity'];

// 6. Forecast margin impact
$impact = $analyzer->forecastMarginImpact(50, 30, -1.5, 100, 45);
echo "New margin: $" . $impact['projected']['total_margin'];

// 7. Check competitive position
$position = $analyzer->calculateCompetitivePosition(55, [48, 50, 52, 58, 60]);
echo "We are at: " . $position['position'] . " percentile";

*/
?>
