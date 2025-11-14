<?php
/**
 * ============================================================================
 * DATA ACCURACY VALIDATOR
 * Measures forecast accuracy against actual sales with multiple metrics
 * ============================================================================
 *
 * Features:
 *   - MAPE (Mean Absolute Percentage Error)
 *   - RMSE (Root Mean Square Error)
 *   - MAE (Mean Absolute Error)
 *   - Bias (systematic over/under forecasting)
 *   - Forecast direction accuracy
 *   - By-category and by-outlet accuracy tracking
 *   - Continuous learning and calibration
 *   - Alert on accuracy degradation
 */

namespace CIS\Forecasting;

use PDO;
use Exception;

class DataAccuracyValidator {
    protected $pdo;
    protected $sales_aggregator;
    protected $acceptable_mape = 20; // 20% MAPE threshold

    public function __construct(PDO $pdo, SalesDataAggregator $aggregator = null) {
        $this->pdo = $pdo;
        $this->sales_aggregator = $aggregator ?? new SalesDataAggregator($pdo);
    }

    /**
     * Compare forecast vs actual sales for a product
     * Returns detailed accuracy metrics
     */
    public function validateProductForecast($product_id, $forecast_period_start, $forecast_period_end) {
        // Get forecast from database
        $forecast = $this->getForecast($product_id, $forecast_period_start, $forecast_period_end);
        if (!$forecast) {
            return [
                'error' => 'No forecast found for this period',
                'product_id' => $product_id,
                'period_start' => $forecast_period_start,
                'period_end' => $forecast_period_end,
            ];
        }

        // Get actual sales during the forecast period
        $actual_sales = $this->getActualSales($product_id, $forecast_period_start, $forecast_period_end);

        // Calculate accuracy metrics
        $metrics = $this->calculateAccuracyMetrics(
            $forecast['predicted_demand_units'],
            $actual_sales['total_units'],
            $forecast['confidence_level']
        );

        // Calculate direction accuracy (did we forecast trend correctly?)
        $direction_accuracy = $this->validateTrendDirection($product_id, $forecast_period_start);

        // Store validation result for learning
        $this->storeValidationResult($product_id, $forecast, $actual_sales, $metrics);

        return [
            'product_id' => $product_id,
            'forecast_id' => $forecast['id'] ?? null,
            'period_start' => $forecast_period_start,
            'period_end' => $forecast_period_end,
            'forecasted_units' => $forecast['predicted_demand_units'],
            'actual_units' => $actual_sales['total_units'],
            'actual_revenue' => $actual_sales['total_revenue'],
            'actual_transactions' => $actual_sales['transaction_count'],
            'forecast_confidence' => $forecast['confidence_level'],
            'metrics' => $metrics,
            'direction_accuracy' => $direction_accuracy,
            'assessment' => $this->assessAccuracy($metrics),
            'recommendations' => $this->getCalibrationRecommendations($metrics, $forecast),
            'validated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Calculate MAPE, RMSE, MAE, Bias for a forecast
     */
    public function calculateAccuracyMetrics($forecast, $actual, $confidence = 75) {
        if ($actual == 0 && $forecast == 0) {
            return [
                'mape' => 0,
                'rmse' => 0,
                'mae' => 0,
                'bias' => 0,
                'accuracy_score' => 100,
                'note' => 'No demand (forecast and actual both zero)',
            ];
        }

        // Prevent division by zero
        $actual_safe = max($actual, 1);
        $forecast_safe = max($forecast, 1);

        // Mean Absolute Percentage Error (MAPE)
        // Measures accuracy as percentage error
        $mape = abs(($actual_safe - $forecast_safe) / $actual_safe) * 100;

        // Root Mean Square Error (RMSE)
        // Penalizes larger errors more heavily
        $rmse = sqrt(pow($actual - $forecast, 2));

        // Mean Absolute Error (MAE)
        // Simple average of absolute errors
        $mae = abs($actual - $forecast);

        // Bias
        // Positive = over-forecasting, Negative = under-forecasting
        $bias = (($forecast - $actual) / max($actual, 1)) * 100;

        // Accuracy score (0-100)
        // Starts at 100, decreases with MAPE
        $accuracy_score = max(0, min(100, 100 - $mape));

        // Adjust for confidence
        // If confidence was high but accuracy was low, penalize more
        if ($confidence >= 80 && $accuracy_score < 60) {
            $accuracy_score *= 0.9; // 10% penalty for misplaced confidence
        }

        return [
            'mape' => round($mape, 2),
            'rmse' => round($rmse, 2),
            'mae' => round($mae, 2),
            'bias' => round($bias, 2),
            'accuracy_score' => round($accuracy_score, 2),
            'error_units' => $actual - $forecast,
            'is_acceptable' => $mape <= $this->acceptable_mape,
            'overforecast' => $bias > 0,
            'underforecast' => $bias < 0,
        ];
    }

    /**
     * Validate if trend direction was correct (up/down/stable)
     */
    public function validateTrendDirection($product_id, $forecast_period_start) {
        $forecast_start = new \DateTime($forecast_period_start);
        $forecast_start->modify('-30 days');
        $historical_start = $forecast_start->format('Y-m-d');

        // Get historical trend before forecast
        $historical_trend = $this->calculateHistoricalTrend($product_id, $historical_start, $forecast_period_start);

        // Get actual trend during forecast period
        $actual_start = new \DateTime($forecast_period_start);
        $actual_start->modify('+30 days');
        $actual_trend = $this->calculateHistoricalTrend($product_id, $forecast_period_start, $actual_start->format('Y-m-d'));

        // Compare
        $forecasted_direction = $historical_trend['direction'];
        $actual_direction = $actual_trend['direction'];
        $direction_match = ($forecasted_direction === $actual_direction);

        return [
            'forecasted_direction' => $forecasted_direction,
            'actual_direction' => $actual_direction,
            'match' => $direction_match,
            'forecasted_momentum' => round($historical_trend['momentum'], 2),
            'actual_momentum' => round($actual_trend['momentum'], 2),
            'accuracy' => $direction_match ? 100 : 0,
        ];
    }

    /**
     * Calculate overall system accuracy across all products
     */
    public function getSystemAccuracy($days_back = 30) {
        $sql = "
            SELECT
                COUNT(*) as total_forecasts,
                AVG(CASE WHEN metrics_mape <= ? THEN 1 ELSE 0 END) * 100 as pct_acceptable,
                AVG(metrics_mape) as avg_mape,
                AVG(metrics_rmse) as avg_rmse,
                AVG(metrics_bias) as avg_bias,
                AVG(metrics_accuracy_score) as avg_accuracy_score,
                MIN(metrics_accuracy_score) as min_accuracy,
                MAX(metrics_accuracy_score) as max_accuracy,
                STDDEV(metrics_mape) as mape_stddev
            FROM forecast_validation_results
            WHERE validation_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->acceptable_mape, $days_back]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total_validated_forecasts' => (int)$result['total_forecasts'],
                'accuracy_summary' => [
                    'avg_mape' => round($result['avg_mape'] ?? 0, 2),
                    'avg_rmse' => round($result['avg_rmse'] ?? 0, 2),
                    'avg_bias' => round($result['avg_bias'] ?? 0, 2),
                    'avg_accuracy_score' => round($result['avg_accuracy_score'] ?? 0, 2),
                    'min_accuracy' => round($result['min_accuracy'] ?? 0, 2),
                    'max_accuracy' => round($result['max_accuracy'] ?? 0, 2),
                    'accuracy_stddev' => round($result['mape_stddev'] ?? 0, 2),
                ],
                'pct_acceptable' => round($result['pct_acceptable'] ?? 0, 2),
                'assessment' => $this->assessSystemAccuracy($result),
                'period_days' => $days_back,
                'calculated_at' => date('Y-m-d H:i:s'),
            ];
        } catch (Exception $e) {
            error_log("DataAccuracyValidator: System accuracy query failed: " . $e->getMessage());
            return [
                'error' => 'System accuracy calculation failed',
                'exception' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get accuracy by category (for trend analysis)
     */
    public function getAccuracyByCategory($days_back = 30) {
        $sql = "
            SELECT
                c.category_id,
                c.category_name,
                COUNT(*) as forecasts,
                AVG(metrics_mape) as avg_mape,
                AVG(metrics_accuracy_score) as avg_accuracy,
                SUM(CASE WHEN metrics_mape <= ? THEN 1 ELSE 0 END) as acceptable_count
            FROM forecast_validation_results fvr
            JOIN products p ON fvr.product_id = p.product_id
            JOIN categories c ON p.category_id = c.category_id
            WHERE fvr.validation_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY c.category_id, c.category_name
            ORDER BY avg_accuracy DESC
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->acceptable_mape, $days_back]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format results
            $formatted = [];
            foreach ($results as $row) {
                $formatted[] = [
                    'category_id' => $row['category_id'],
                    'category_name' => $row['category_name'],
                    'forecast_count' => (int)$row['forecasts'],
                    'avg_mape' => round($row['avg_mape'], 2),
                    'avg_accuracy_score' => round($row['avg_accuracy'], 2),
                    'acceptable_pct' => round(($row['acceptable_count'] / $row['forecasts']) * 100, 2),
                ];
            }

            return $formatted;
        } catch (Exception $e) {
            error_log("DataAccuracyValidator: Category accuracy query failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get accuracy by outlet
     */
    public function getAccuracyByOutlet($days_back = 30) {
        $sql = "
            SELECT
                fvr.outlet_id,
                o.outlet_name,
                COUNT(*) as forecasts,
                AVG(metrics_mape) as avg_mape,
                AVG(metrics_accuracy_score) as avg_accuracy,
                SUM(CASE WHEN metrics_mape <= ? THEN 1 ELSE 0 END) as acceptable_count
            FROM forecast_validation_results fvr
            JOIN outlets o ON fvr.outlet_id = o.outlet_id
            WHERE fvr.validation_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY fvr.outlet_id, o.outlet_name
            ORDER BY avg_accuracy DESC
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->acceptable_mape, $days_back]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $formatted = [];
            foreach ($results as $row) {
                $formatted[] = [
                    'outlet_id' => $row['outlet_id'],
                    'outlet_name' => $row['outlet_name'],
                    'forecast_count' => (int)$row['forecasts'],
                    'avg_mape' => round($row['avg_mape'], 2),
                    'avg_accuracy_score' => round($row['avg_accuracy'], 2),
                    'acceptable_pct' => round(($row['acceptable_count'] / $row['forecasts']) * 100, 2),
                ];
            }

            return $formatted;
        } catch (Exception $e) {
            error_log("DataAccuracyValidator: Outlet accuracy query failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Identify products with poor forecast accuracy for investigation
     */
    public function getProblematicProducts($days_back = 30, $limit = 20) {
        $sql = "
            SELECT
                fvr.product_id,
                p.product_name,
                COUNT(*) as forecast_count,
                AVG(metrics_mape) as avg_mape,
                AVG(metrics_accuracy_score) as avg_accuracy,
                AVG(metrics_bias) as avg_bias,
                SUM(CASE WHEN metrics_mape > ? THEN 1 ELSE 0 END) as poor_forecasts
            FROM forecast_validation_results fvr
            JOIN products p ON fvr.product_id = p.product_id
            WHERE fvr.validation_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY fvr.product_id, p.product_name
            HAVING avg_accuracy < 70
            ORDER BY avg_mape DESC
            LIMIT ?
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->acceptable_mape, $days_back, $limit]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $formatted = [];
            foreach ($results as $row) {
                $formatted[] = [
                    'product_id' => $row['product_id'],
                    'product_name' => $row['product_name'],
                    'forecast_count' => (int)$row['forecast_count'],
                    'avg_mape' => round($row['avg_mape'], 2),
                    'avg_accuracy_score' => round($row['avg_accuracy'], 2),
                    'avg_bias' => round($row['avg_bias'], 2),
                    'issue' => $this->diagnoseProblem($row),
                ];
            }

            return $formatted;
        } catch (Exception $e) {
            error_log("DataAccuracyValidator: Problematic products query failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ========== PRIVATE HELPER METHODS ==========
     */

    private function getForecast($product_id, $period_start, $period_end) {
        $sql = "
            SELECT *
            FROM forecast_predictions
            WHERE product_id = ?
                AND forecast_period_start <= ?
                AND forecast_period_end >= ?
            ORDER BY forecast_date DESC
            LIMIT 1
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$product_id, $period_end, $period_start]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch forecast: " . $e->getMessage());
            return null;
        }
    }

    private function getActualSales($product_id, $date_from, $date_to) {
        $sql = "
            SELECT
                COALESCE(SUM(vsl.quantity), 0) as total_units,
                COALESCE(SUM(vsl.price_paid), 0) as total_revenue,
                COUNT(DISTINCT vs.id) as transaction_count,
                COUNT(DISTINCT vs.customer_id) as unique_customers
            FROM vend_sales vs
            JOIN vend_sale_lines vsl ON vs.id = vsl.sale_id
            WHERE vsl.product_id = ?
                AND vs.sale_date >= ?
                AND vs.sale_date <= ?
                AND vs.status = 'CLOSED'
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$product_id, $date_from . ' 00:00:00', $date_to . ' 23:59:59']);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch actual sales: " . $e->getMessage());
            return ['total_units' => 0, 'total_revenue' => 0, 'transaction_count' => 0];
        }
    }

    private function calculateHistoricalTrend($product_id, $date_from, $date_to) {
        // Split period in half to compare early vs late
        $mid_date = date('Y-m-d', (strtotime($date_from) + strtotime($date_to)) / 2);

        $early_units = $this->getUnitsSoldBetween($product_id, $date_from, $mid_date);
        $late_units = $this->getUnitsSoldBetween($product_id, $mid_date, $date_to);

        $days_early = (strtotime($mid_date) - strtotime($date_from)) / 86400;
        $days_late = (strtotime($date_to) - strtotime($mid_date)) / 86400;

        $days_early = max($days_early, 1);
        $days_late = max($days_late, 1);

        $rate_early = $early_units / $days_early;
        $rate_late = $late_units / $days_late;

        $momentum = ($rate_late - $rate_early) / max($rate_early, 1);

        $direction = 'stable';
        if ($momentum > 0.15) {
            $direction = 'up';
        } elseif ($momentum < -0.15) {
            $direction = 'down';
        }

        return [
            'direction' => $direction,
            'momentum' => $momentum * 100,
            'early_rate' => $rate_early,
            'late_rate' => $rate_late,
        ];
    }

    private function getUnitsSoldBetween($product_id, $date_from, $date_to) {
        $sql = "
            SELECT COALESCE(SUM(vsl.quantity), 0) as units
            FROM vend_sales vs
            JOIN vend_sale_lines vsl ON vs.id = vsl.sale_id
            WHERE vsl.product_id = ?
                AND vs.sale_date >= ?
                AND vs.sale_date <= ?
                AND vs.status = 'CLOSED'
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$product_id, $date_from . ' 00:00:00', $date_to . ' 23:59:59']);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    private function assessAccuracy($metrics) {
        $score = $metrics['accuracy_score'];

        if ($score >= 90) {
            return 'Excellent - Forecast highly accurate';
        } elseif ($score >= 80) {
            return 'Good - Forecast reasonably accurate';
        } elseif ($score >= 70) {
            return 'Acceptable - Minor adjustments recommended';
        } elseif ($score >= 60) {
            return 'Fair - Notable variance from actual';
        } else {
            return 'Poor - Significant forecast error, needs investigation';
        }
    }

    private function assessSystemAccuracy($data) {
        $avg_accuracy = $data['avg_accuracy_score'] ?? 0;

        if ($avg_accuracy >= 85) {
            return '✅ System Performing Well';
        } elseif ($avg_accuracy >= 75) {
            return '⚠️ System Acceptable but Monitor';
        } else {
            return '❌ System Needs Tuning';
        }
    }

    private function getCalibrationRecommendations($metrics, $forecast) {
        $recommendations = [];

        // Check MAPE
        if ($metrics['mape'] > 30) {
            $recommendations[] = 'High MAPE - Consider including more demand signals or external factors';
        }

        // Check bias
        if ($metrics['bias'] > 15) {
            $recommendations[] = 'Consistent over-forecasting - Reduce confidence adjustments or increase supply lead time';
        } elseif ($metrics['bias'] < -15) {
            $recommendations[] = 'Consistent under-forecasting - Increase base demand estimates or decrease discount rates';
        }

        // Check confidence vs accuracy
        if ($forecast['confidence_level'] > 80 && $metrics['accuracy_score'] < 60) {
            $recommendations[] = 'Forecast confidence too high - Adjust weighting of confidence factors';
        }

        // Direction accuracy
        if (!($metrics['direction_accuracy']['match'] ?? true)) {
            $recommendations[] = 'Trend direction incorrect - Review seasonal adjustments and trend calculations';
        }

        return !empty($recommendations) ? $recommendations : ['Forecast within acceptable parameters'];
    }

    private function diagnoseProblem($row) {
        $mape = $row['avg_mape'];
        $bias = $row['avg_bias'];

        if ($bias > 20) {
            return 'Consistent over-forecasting (high bias)';
        } elseif ($bias < -20) {
            return 'Consistent under-forecasting (high negative bias)';
        } elseif ($mape > 40) {
            return 'High variance - inconsistent or volatile demand';
        } else {
            return 'Complex demand pattern - may need ML algorithm';
        }
    }

    private function storeValidationResult($product_id, $forecast, $actual_sales, $metrics) {
        $sql = "
            INSERT INTO forecast_validation_results
            (product_id, forecast_id, outlet_id, forecasted_units, actual_units, actual_revenue,
             metrics_mape, metrics_rmse, metrics_mae, metrics_bias, metrics_accuracy_score, validation_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                actual_units = VALUES(actual_units),
                actual_revenue = VALUES(actual_revenue),
                metrics_mape = VALUES(metrics_mape),
                metrics_accuracy_score = VALUES(metrics_accuracy_score)
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $product_id,
                $forecast['id'] ?? null,
                $forecast['outlet_id'] ?? null,
                $forecast['predicted_demand_units'],
                $actual_sales['total_units'],
                $actual_sales['total_revenue'],
                $metrics['mape'],
                $metrics['rmse'],
                $metrics['mae'],
                $metrics['bias'],
                $metrics['accuracy_score'],
            ]);
        } catch (Exception $e) {
            error_log("Failed to store validation result: " . $e->getMessage());
        }
    }
}
