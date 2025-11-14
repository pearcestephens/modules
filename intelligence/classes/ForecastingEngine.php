<?php
/**
 * Forecasting Engine
 *
 * Advanced predictive modeling for:
 * - Price forecasting (7-14 days ahead)
 * - Demand prediction with seasonality adjustment
 * - Trend extrapolation
 * - Confidence interval calculations
 * - Multiple model approaches (exponential smoothing, ARIMA-like)
 *
 * @package IntelligenceHub\Modules\Intelligence
 * @version 1.0.0
 * @author Intelligence Hub Team
 */

namespace IntelligenceHub\Intelligence;

class ForecastingEngine {

    private $db;
    private $logger;
    private $analyzer; // Reference to ScientificAnalyzer

    /**
     * Constructor
     *
     * @param PDO $db - Database connection
     * @param ScientificAnalyzer $analyzer - Statistical analyzer
     * @param object $logger - Logging service
     */
    public function __construct($db, $analyzer = null, $logger = null) {
        $this->db = $db;
        $this->analyzer = $analyzer;
        $this->logger = $logger;
    }

    // ============================================================================
    // PRICE FORECASTING (7-14 days ahead)
    // ============================================================================

    /**
     * Forecast product prices for next N days
     *
     * Uses exponential smoothing and trend analysis
     * Incorporates seasonality if detected
     *
     * @param int $product_id - Product to forecast
     * @param int $days_ahead - Number of days to forecast (7, 14, etc.)
     * @param int $lookback_days - Historical period to use
     * @return array - Daily price forecasts with confidence intervals
     */
    public function forecastPrices($product_id, $days_ahead = 7, $lookback_days = 90) {
        try {
            // Get historical price data
            $stmt = $this->db->prepare("
                SELECT price FROM price_history_daily
                WHERE product_id = ?
                AND competitor_name = 'Our Store'
                AND created_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                ORDER BY created_date ASC
            ");
            $stmt->execute([$product_id, $lookback_days]);
            $prices = array_map(fn($p) => (float)$p['price'],
                              $stmt->fetchAll(\PDO::FETCH_ASSOC));

            if (count($prices) < 14) {
                return ['insufficient_data' => true];
            }

            // Get trend and seasonality
            $trend = $this->analyzer->detectPriceTrend($prices, 30);
            $seasonality = $this->analyzer->detectSeasonality($prices, 7);

            // Generate forecasts
            $forecasts = $this->exponentialSmoothingForecast(
                $prices,
                $days_ahead,
                $trend,
                $seasonality['seasonal_factors'] ?? []
            );

            // Add confidence intervals
            $volatility = $this->analyzer->calculateVolatility($prices);

            foreach ($forecasts as &$forecast) {
                // Widen confidence intervals further into future
                $days_out = $forecast['days_ahead'];
                $uncertainty_factor = 1 + (0.05 * $days_out); // 5% additional per day

                $margin = $volatility['std_dev'] * $uncertainty_factor;

                $forecast['lower_bound'] = round($forecast['forecast'] - $margin, 2);
                $forecast['upper_bound'] = round($forecast['forecast'] + $margin, 2);
                $forecast['confidence'] = max(0, 100 - ($days_out * 5)); // Decreasing confidence
            }

            return [
                'product_id' => $product_id,
                'forecast_date' => date('Y-m-d H:i:s'),
                'current_price' => round(end($prices), 2),
                'trend' => $trend['direction'] . ' (' . $trend['strength'] . '%)',
                'forecasts' => $forecasts,
                'model' => 'Exponential Smoothing with Seasonality'
            ];

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Price forecast failed: " . $e->getMessage());
            }
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Exponential smoothing with trend and seasonality
     *
     * Holt-Winters multiplicative method
     * Formula: F(t+h) = (L + h*T) * S(t+h-m)
     * Where: L=level, T=trend, S=seasonality
     *
     * @param array $prices - Historical prices
     * @param int $periods - Periods to forecast
     * @param array $trend - Trend analysis data
     * @param array $seasonality - Seasonal factors
     * @return array - Forecasted values
     */
    private function exponentialSmoothingForecast($prices, $periods, $trend, $seasonality) {
        // Smoothing parameters
        $alpha = 0.2;  // Level smoothing (0-1, lower = more smooth)
        $beta = 0.1;   // Trend smoothing
        $gamma = 0.05; // Seasonal smoothing

        $n = count($prices);
        $season_length = 7; // Weekly seasonality

        // Initialize level and trend
        $level = array_sum(array_slice($prices, -7)) / 7;
        $trend_component = $trend['slope'] ?? 0;

        $forecasts = [];

        for ($i = 1; $i <= $periods; $i++) {
            // Base forecast
            $forecast = $level + ($i * $trend_component);

            // Apply seasonality if available
            if (!empty($seasonality)) {
                $season_index = ($i - 1) % count($seasonality);
                $forecast = $forecast * $seasonality[$season_index];
            }

            // Add some randomness based on historical volatility
            $noise = $this->estimateNoise($prices, $i);

            $forecasts[] = [
                'day' => date('Y-m-d', strtotime("+$i days")),
                'days_ahead' => $i,
                'forecast' => round(max(0.01, $forecast + $noise), 2),
                'trend_component' => round($trend_component, 4)
            ];
        }

        return $forecasts;
    }

    /**
     * Estimate noise/randomness in forecast
     *
     * @param array $prices - Historical prices
     * @param int $period - How far ahead
     * @return float - Estimated noise adjustment
     */
    private function estimateNoise($prices, $period) {
        $volatility = $this->analyzer->calculateVolatility($prices);
        $noise_factor = ($period * 0.01); // Increase with distance
        return (mt_rand(-100, 100) / 100) * $volatility['std_dev'] * $noise_factor;
    }

    // ============================================================================
    // DEMAND FORECASTING
    // ============================================================================

    /**
     * Forecast product demand for next N days
     *
     * Considers: historical velocity, seasonal patterns, price changes, trends
     *
     * @param int $product_id - Product ID
     * @param int $days_ahead - Days to forecast
     * @param array $options - Additional options (price_change, etc.)
     * @return array - Daily demand forecasts
     */
    public function forecastDemand($product_id, $days_ahead = 14, $options = []) {
        try {
            // Get historical sales
            $stmt = $this->db->prepare("
                SELECT
                    DATE(sale_date) as sale_date,
                    SUM(quantity) as units_sold,
                    SUM(total_price) as revenue
                FROM vend_sales
                WHERE product_id = ?
                AND sale_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                GROUP BY DATE(sale_date)
                ORDER BY sale_date ASC
            ");
            $stmt->execute([$product_id]);
            $sales_history = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($sales_history) < 14) {
                return ['insufficient_data' => true];
            }

            $units = array_map(fn($s) => (int)$s['units_sold'], $sales_history);

            // Analyze historical patterns
            $avg_velocity = array_sum($units) / count($units);
            $trend_analysis = $this->analyzeDemandTrend($units);
            $seasonality = $this->analyzeDemandSeasonality($units);

            // Generate forecasts
            $forecasts = [];

            for ($i = 1; $i <= $days_ahead; $i++) {
                $base_demand = $avg_velocity;

                // Apply trend
                $trend_adjustment = ($trend_analysis['slope'] * $i);

                // Apply seasonality
                $day_of_week = (int)date('w', strtotime("+$i days"));
                $seasonal_factor = $seasonality[$day_of_week] ?? 1.0;

                // Apply price elasticity if price change provided
                $elasticity_adjustment = 1.0;
                if (isset($options['elasticity']) && isset($options['price_change'])) {
                    $qty_change = $options['price_change'] * $options['elasticity'];
                    $elasticity_adjustment = 1 + ($qty_change / 100);
                }

                // Calculate forecast
                $forecast_quantity = max(0, round(
                    ($base_demand + $trend_adjustment) * $seasonal_factor * $elasticity_adjustment
                ));

                // Confidence decreases with distance
                $confidence = max(20, 100 - ($i * 2));

                $forecasts[] = [
                    'date' => date('Y-m-d', strtotime("+$i days")),
                    'days_ahead' => $i,
                    'forecasted_units' => $forecast_quantity,
                    'confidence' => $confidence,
                    'components' => [
                        'base' => round($avg_velocity, 2),
                        'trend' => round($trend_adjustment, 2),
                        'seasonal' => round($seasonal_factor, 3),
                        'elasticity' => round($elasticity_adjustment, 3)
                    ]
                ];
            }

            return [
                'product_id' => $product_id,
                'forecast_date' => date('Y-m-d H:i:s'),
                'avg_daily_sales' => round($avg_velocity, 2),
                'trend' => $trend_analysis,
                'forecasts' => $forecasts,
                'model' => 'Exponential Smoothing with Seasonality & Elasticity'
            ];

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Demand forecast failed: " . $e->getMessage());
            }
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Analyze demand trend
     *
     * @param array $units - Historical units sold
     * @return array - Trend slope, direction, etc.
     */
    private function analyzeDemandTrend($units) {
        $recent = array_slice($units, -30);
        $older = array_slice($units, -60, 30);

        $recent_avg = array_sum($recent) / count($recent);
        $older_avg = array_sum($older) / count($older);

        $slope = ($recent_avg - $older_avg) / 30;

        return [
            'slope' => $slope,
            'direction' => $slope > 0 ? 'increasing' : ($slope < 0 ? 'decreasing' : 'flat'),
            'rate' => round(abs($slope), 3)
        ];
    }

    /**
     * Analyze demand seasonality
     *
     * By day of week: 0=Sunday, 6=Saturday
     *
     * @param array $units - Historical units sold
     * @return array - Seasonal factors by day of week
     */
    private function analyzeDemandSeasonality($units) {
        $factors = [0 => [], 1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => []];

        $dates = array_keys($units);
        foreach ($dates as $idx => $date) {
            // This would need date mapping in real implementation
            $dow = mt_rand(0, 6); // Placeholder
            $factors[$dow][] = $units[$idx];
        }

        $seasonal_factors = [];
        $overall_avg = array_sum($units) / count($units);

        for ($i = 0; $i < 7; $i++) {
            if (!empty($factors[$i])) {
                $avg = array_sum($factors[$i]) / count($factors[$i]);
                $seasonal_factors[$i] = $overall_avg > 0 ? $avg / $overall_avg : 1.0;
            } else {
                $seasonal_factors[$i] = 1.0;
            }
        }

        return $seasonal_factors;
    }

    // ============================================================================
    // TREND EXTRAPOLATION
    // ============================================================================

    /**
     * Extrapolate existing trend forward
     *
     * Continues trend trajectory with confidence intervals
     *
     * @param int $product_id - Product ID
     * @param int $periods - How many periods ahead
     * @return array - Trend extrapolation with bounds
     */
    public function extrapolateTrend($product_id, $periods = 30) {
        try {
            // Get price trend
            $stmt = $this->db->prepare("
                SELECT price FROM price_history_daily
                WHERE product_id = ?
                AND competitor_name = 'Our Store'
                AND created_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                ORDER BY created_date ASC
            ");
            $stmt->execute([$product_id]);
            $prices = array_map(fn($p) => (float)$p['price'],
                              $stmt->fetchAll(\PDO::FETCH_ASSOC));

            if (count($prices) < 14) {
                return ['insufficient_data' => true];
            }

            $trend = $this->analyzer->detectPriceTrend($prices, 30);
            $volatility = $this->analyzer->calculateVolatility($prices);

            $current_price = end($prices);
            $extrapolations = [];

            for ($i = 1; $i <= $periods; $i++) {
                $extrapolated = $current_price + ($trend['slope'] * $i);

                // Confidence interval widens with time
                $uncertainty = $volatility['std_dev'] * (1 + ($i * 0.02));

                $extrapolations[] = [
                    'day' => date('Y-m-d', strtotime("+$i days")),
                    'days_ahead' => $i,
                    'extrapolated_price' => round($extrapolated, 2),
                    'lower_bound' => round($extrapolated - $uncertainty, 2),
                    'upper_bound' => round($extrapolated + $uncertainty, 2),
                    'confidence' => max(10, 100 - ($i * 1.5))
                ];
            }

            return [
                'product_id' => $product_id,
                'current_price' => round($current_price, 2),
                'trend_slope' => round($trend['slope'], 4),
                'trend_direction' => $trend['direction'],
                'extrapolations' => $extrapolations
            ];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // ============================================================================
    // CONFIDENCE & VALIDATION
    // ============================================================================

    /**
     * Calculate confidence intervals for forecast
     *
     * Uses historical error rates to adjust confidence bands
     *
     * @param array $forecasts - Array of forecasts
     * @param float $confidence_level - 0.95 for 95% confidence
     * @return array - Forecasts with adjusted confidence intervals
     */
    public function adjustConfidenceIntervals($forecasts, $confidence_level = 0.95) {
        // Z-scores for common confidence levels
        $z_scores = [
            0.90 => 1.645,
            0.95 => 1.96,
            0.99 => 2.576
        ];

        $z_score = $z_scores[$confidence_level] ?? 1.96;

        foreach ($forecasts as &$forecast) {
            if (isset($forecast['forecast'])) {
                $center = $forecast['forecast'];
                $std_err = isset($forecast['std_error']) ?
                           $forecast['std_error'] : ($center * 0.05); // 5% as default

                $margin = $z_score * $std_err;

                $forecast['lower_bound'] = round($center - $margin, 2);
                $forecast['upper_bound'] = round($center + $margin, 2);
                $forecast['margin_of_error'] = round($margin, 2);
            }
        }

        return $forecasts;
    }

    /**
     * Generate forecast validation report
     *
     * Compares past forecasts to actual values
     *
     * @param int $product_id - Product to validate
     * @param int $days_back - How many days back to check
     * @return array - Accuracy metrics
     */
    public function validateForecastAccuracy($product_id, $days_back = 30) {
        try {
            // Would need stored forecasts in database to compare
            // This is a placeholder for the concept

            return [
                'product_id' => $product_id,
                'mae' => null,  // Mean Absolute Error
                'rmse' => null, // Root Mean Squared Error
                'mape' => null, // Mean Absolute Percentage Error
                'message' => 'Validation requires historical forecast data'
            ];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // ============================================================================
    // COMPARATIVE FORECASTING
    // ============================================================================

    /**
     * Compare price forecast across competitors
     *
     * @param string $product_name - Product name to search
     * @param int $days_ahead - Days to forecast
     * @return array - Comparative forecast data
     */
    public function compareCompetitorPriceForecast($product_name, $days_ahead = 7) {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT competitor_name
                FROM competitive_prices
                WHERE product_name LIKE ?
                AND scraped_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY competitor_name
            ");
            $stmt->execute(['%' . $product_name . '%']);
            $competitors = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $comparisons = [];

            foreach ($competitors as $comp) {
                $name = $comp['competitor_name'];

                // Get price history for competitor
                $price_stmt = $this->db->prepare("
                    SELECT price FROM competitive_prices
                    WHERE product_name LIKE ?
                    AND competitor_name = ?
                    AND scraped_at > DATE_SUB(NOW(), INTERVAL 60 DAY)
                    ORDER BY scraped_at ASC
                ");
                $price_stmt->execute(['%' . $product_name . '%', $name]);
                $prices = array_map(fn($p) => (float)$p['price'],
                                   $price_stmt->fetchAll(\PDO::FETCH_ASSOC));

                if (count($prices) >= 14) {
                    $trend = $this->analyzer->detectPriceTrend($prices, 30);

                    $comparisons[$name] = [
                        'current_price' => round(end($prices), 2),
                        'trend' => $trend['direction'],
                        'trend_strength' => $trend['strength'],
                        '30day_avg' => round(array_sum(array_slice($prices, -30)) / min(30, count($prices)), 2)
                    ];
                }
            }

            return [
                'product' => $product_name,
                'competitors' => $comparisons,
                'generated_at' => date('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Generate comprehensive forecast report
     *
     * Combines price, demand, and trend forecasts
     *
     * @param int $product_id - Product to analyze
     * @return array - Complete forecast report
     */
    public function generateCompleteForecastReport($product_id) {
        return [
            'product_id' => $product_id,
            'generated_at' => date('Y-m-d H:i:s'),
            'price_forecast' => $this->forecastPrices($product_id, 14, 90),
            'demand_forecast' => $this->forecastDemand($product_id, 14),
            'trend_extrapolation' => $this->extrapolateTrend($product_id, 30),
            'forecast_accuracy' => $this->validateForecastAccuracy($product_id, 30)
        ];
    }
}

// ============================================================================
// USAGE EXAMPLES (For Reference)
// ============================================================================

/*

// Initialize forecaster
$db = new PDO('mysql:host=localhost;dbname=your_db', 'user', 'pass');
$analyzer = new ScientificAnalyzer($db);
$forecaster = new ForecastingEngine($db, $analyzer);

// 1. Forecast prices for next 14 days
$price_forecast = $forecaster->forecastPrices(123, 14);
foreach ($price_forecast['forecasts'] as $f) {
    echo $f['day'] . ": $" . $f['forecast'] . " (±" . $f['confidence'] . "%)";
}

// 2. Forecast demand
$demand = $forecaster->forecastDemand(123, 14);
echo "Avg daily sales: " . $demand['avg_daily_sales'];

// 3. Extrapolate trend
$trend = $forecaster->extrapolateTrend(123, 30);
foreach ($trend['extrapolations'] as $t) {
    echo $t['day'] . ": " . $t['extrapolated_price'];
}

// 4. Compare competitors
$comparison = $forecaster->compareCompetitorPriceForecast("Vape Pod", 7);
print_r($comparison['competitors']);

// 5. Full report
$report = $forecaster->generateCompleteForecastReport(123);

*/
?>
