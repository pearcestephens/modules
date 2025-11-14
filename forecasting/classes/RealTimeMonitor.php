<?php
/**
 * ============================================================================
 * REAL-TIME MONITOR & ANOMALY DETECTION
 * Detects sales spikes, drops, and unusual patterns in real-time
 * ============================================================================
 *
 * Features:
 *   - Real-time spike detection (>20% change)
 *   - Drop detection (<20% decline)
 *   - Unusual pattern identification
 *   - Anomaly scoring (0-100)
 *   - Automatic demand signal creation
 *   - Alert triggering
 *   - Seasonal adjustment for fair comparisons
 *   - By-product and by-outlet monitoring
 */

namespace CIS\Forecasting;

use PDO;
use Exception;

class RealTimeMonitor {
    protected $pdo;
    protected $spike_threshold = 0.20; // 20% threshold for spike
    protected $drop_threshold = -0.20; // -20% threshold for drop
    protected $anomaly_threshold = 70; // 70% anomaly score triggers alert

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Scan all products for real-time anomalies
     * Should be run every hour or on demand
     */
    public function scanAllProducts($outlet_id = null) {
        $sql = "
            SELECT DISTINCT p.product_id, p.product_name
            FROM vend_products p
            WHERE p.active = 1
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("RealTimeMonitor: Failed to get products: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }

        $anomalies = [];
        $alerts_created = 0;

        foreach ($products as $product) {
            $check = $this->checkProductAnomaly($product['product_id'], $outlet_id);

            if ($check['has_anomaly']) {
                $anomalies[] = $check;

                // Auto-create demand signal if anomaly is significant
                if ($check['anomaly_score'] >= $this->anomaly_threshold) {
                    $this->createDemandSignal($product['product_id'], $check);
                    $alerts_created++;
                }
            }
        }

        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'products_scanned' => count($products),
            'anomalies_detected' => count($anomalies),
            'alerts_created' => $alerts_created,
            'anomalies' => $anomalies,
        ];
    }

    /**
     * Check a specific product for anomalies
     * Compares current period to historical baseline with seasonal adjustment
     */
    public function checkProductAnomaly($product_id, $outlet_id = null) {
        // Get current period sales (last 24 hours)
        $current_period = $this->getSalesByPeriod($product_id, $outlet_id, -1);

        // Get recent history (last 7 days, excluding current)
        $recent_history = $this->getSalesByPeriod($product_id, $outlet_id, -7);

        // Get historical baseline (same day last week, last month, last quarter)
        $last_week_same_day = $this->getSalesByPeriod($product_id, $outlet_id, -8);
        $last_month_same_period = $this->getSalesByPeriod($product_id, $outlet_id, -37);

        // Calculate baseline (average of recent history, adjusted for day-of-week)
        $baseline = $this->calculateBaseline($recent_history, $last_week_same_day);

        if (!$baseline) {
            return [
                'product_id' => $product_id,
                'has_anomaly' => false,
                'reason' => 'Insufficient historical data',
            ];
        }

        // Calculate deviation from baseline
        $deviation = (($current_period['units'] - $baseline['units']) / max($baseline['units'], 1)) * 100;
        $deviation_transactions = (($current_period['transactions'] - $baseline['transactions']) / max($baseline['transactions'], 1)) * 100;

        // Detect spike
        $is_spike = $deviation > ($this->spike_threshold * 100);
        $is_drop = $deviation < ($this->drop_threshold * 100);
        $is_anomaly = $is_spike || $is_drop;

        // Calculate anomaly score (0-100)
        $anomaly_score = min(100, abs($deviation) / 2); // 40% deviation = 80 anomaly score

        // Detect unusual pattern (high transactions but low units, or vice versa)
        $pattern_anomaly = $this->detectPatternAnomaly($current_period, $recent_history);

        if ($pattern_anomaly) {
            $anomaly_score = min(100, $anomaly_score + 15);
            $is_anomaly = true;
        }

        // Get product info for context
        $product_info = $this->getProductInfo($product_id);

        // Assess severity
        $severity = $this->calculateSeverity($deviation, $current_period['units'], $product_info);

        return [
            'product_id' => $product_id,
            'product_name' => $product_info['name'] ?? 'Unknown',
            'outlet_id' => $outlet_id,
            'has_anomaly' => $is_anomaly,
            'anomaly_score' => round($anomaly_score, 1),
            'severity' => $severity,
            'current_period' => $current_period,
            'baseline' => $baseline,
            'deviation_pct' => round($deviation, 2),
            'deviation_transactions_pct' => round($deviation_transactions, 2),
            'is_spike' => $is_spike,
            'is_drop' => $is_drop,
            'pattern_anomaly' => $pattern_anomaly,
            'recommendation' => $this->getAnomialyRecommendation($is_spike, $is_drop, $severity),
            'detected_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Monitor product performance against forecast
     * Real-time comparison to see if we're on track
     */
    public function monitorForecastPerformance($product_id, $outlet_id = null) {
        // Get active forecast
        $forecast = $this->getActiveForecast($product_id, $outlet_id);
        if (!$forecast) {
            return ['error' => 'No active forecast'];
        }

        // Get period-to-date sales
        $period_start = $forecast['forecast_period_start'];
        $today = date('Y-m-d');
        $ptd_sales = $this->getSalesRange($product_id, $outlet_id, $period_start, $today);

        // Calculate pace
        $days_elapsed = (strtotime($today) - strtotime($period_start)) / 86400 + 1;
        $days_total = (strtotime($forecast['forecast_period_end']) - strtotime($period_start)) / 86400 + 1;
        $expected_pct_complete = ($days_elapsed / $days_total) * 100;
        $actual_pct_complete = ($ptd_sales['units'] / max($forecast['predicted_demand_units'], 1)) * 100;

        // On track?
        $variance = $actual_pct_complete - $expected_pct_complete;
        $on_track = abs($variance) <= 5; // Within 5% is on track

        // Project year-end
        $daily_rate = $ptd_sales['units'] / $days_elapsed;
        $projected_total = round($daily_rate * $days_total);
        $projected_vs_forecast = (($projected_total - $forecast['predicted_demand_units']) / max($forecast['predicted_demand_units'], 1)) * 100;

        return [
            'product_id' => $product_id,
            'outlet_id' => $outlet_id,
            'forecast_period' => $forecast['forecast_period_start'] . ' to ' . $forecast['forecast_period_end'],
            'forecasted_units' => $forecast['predicted_demand_units'],
            'sales_to_date' => $ptd_sales['units'],
            'sales_to_date_revenue' => round($ptd_sales['revenue'], 2),
            'days_elapsed' => $days_elapsed,
            'days_total' => $days_total,
            'expected_pct_complete' => round($expected_pct_complete, 1),
            'actual_pct_complete' => round($actual_pct_complete, 1),
            'variance_pct' => round($variance, 1),
            'on_track' => $on_track,
            'daily_rate' => round($daily_rate, 2),
            'projected_total' => $projected_total,
            'projected_vs_forecast_pct' => round($projected_vs_forecast, 2),
            'projection' => $this->getProjection($projected_vs_forecast),
            'assessed_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get demand signal strength for a product
     * High = increasing demand, Low = decreasing demand
     */
    public function getDemandSignalStrength($product_id, $outlet_id = null) {
        // Get recent demand signals
        $sql = "
            SELECT
                signal_type,
                impact_on_forecast,
                confidence,
                recorded_date
            FROM demand_signals
            WHERE product_id = ?
                AND recorded_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY recorded_date DESC
        ";

        if ($outlet_id) {
            // Could add outlet filtering if needed in future
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$product_id]);
            $signals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $signals = [];
        }

        if (empty($signals)) {
            return [
                'product_id' => $product_id,
                'signal_strength' => 0,
                'signal_direction' => 'neutral',
                'active_signals' => 0,
            ];
        }

        // Calculate weighted signal strength
        $total_impact = 0;
        $total_confidence = 0;
        $signal_types = [];

        foreach ($signals as $signal) {
            $weight = $signal['impact_on_forecast'] * $signal['confidence'] / 100;
            $total_impact += $signal['signal_value'] * $weight;
            $total_confidence += $weight;
            $signal_types[] = $signal['signal_type'];
        }

        $signal_strength = $total_confidence > 0 ? $total_impact / $total_confidence : 0;
        $signal_direction = $signal_strength > 0.05 ? 'up' : ($signal_strength < -0.05 ? 'down' : 'neutral');

        return [
            'product_id' => $product_id,
            'active_signals' => count($signals),
            'signal_types' => array_unique($signal_types),
            'signal_strength' => round($signal_strength, 3),
            'signal_direction' => $signal_direction,
            'days_of_data' => 30,
            'confidence_avg' => round(array_sum(array_column($signals, 'confidence')) / count($signals), 1),
        ];
    }

    /**
     * ========== PRIVATE HELPER METHODS ==========
     */

    private function getSalesByPeriod($product_id, $outlet_id, $days_offset) {
        $date_start = date('Y-m-d', strtotime("{$days_offset} days"));
        $date_end = date('Y-m-d', strtotime("{$days_offset + 1} days"));

        return $this->getSalesRange($product_id, $outlet_id, $date_start, $date_end);
    }

    private function getSalesRange($product_id, $outlet_id, $date_from, $date_to) {
        $sql = "
            SELECT
                COALESCE(SUM(vsl.quantity), 0) as units,
                COALESCE(SUM(vsl.price_paid), 0) as revenue,
                COUNT(DISTINCT vs.id) as transactions,
                COUNT(DISTINCT vs.customer_id) as unique_customers,
                AVG(vsl.quantity) as avg_units_per_line,
                MIN(vs.sale_date) as first_sale,
                MAX(vs.sale_date) as last_sale
            FROM vend_sales vs
            JOIN vend_sale_lines vsl ON vs.id = vsl.sale_id
            WHERE vsl.product_id = ?
                AND vs.sale_date >= ?
                AND vs.sale_date <= ?
                AND vs.status = 'CLOSED'
        ";

        $params = [$product_id, $date_from . ' 00:00:00', $date_to . ' 23:59:59'];

        if ($outlet_id) {
            $sql .= " AND vs.outlet_id = ?";
            $params[] = $outlet_id;
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("getSalesRange failed: " . $e->getMessage());
            return [
                'units' => 0,
                'revenue' => 0,
                'transactions' => 0,
                'unique_customers' => 0,
            ];
        }
    }

    private function calculateBaseline($recent_history, $last_week_same_day) {
        // Average recent history, with some weighting to more recent days
        // Adjust for day-of-week differences

        $baseline_units = 0;
        $baseline_transactions = 0;

        if ($recent_history && $recent_history['units'] > 0) {
            $baseline_units = $recent_history['units'] / 7;
            $baseline_transactions = $recent_history['transactions'] / 7;
        }

        // Blend with last week same day if available
        if ($last_week_same_day && $last_week_same_day['units'] > 0) {
            $baseline_units = ($baseline_units * 0.6) + ($last_week_same_day['units'] * 0.4);
            $baseline_transactions = ($baseline_transactions * 0.6) + ($last_week_same_day['transactions'] * 0.4);
        }

        return $baseline_units > 0 ? [
            'units' => round($baseline_units, 1),
            'transactions' => round($baseline_transactions, 1),
        ] : null;
    }

    private function detectPatternAnomaly($current, $history) {
        if (!$current || !$history) {
            return false;
        }

        $current_avg_per_transaction = $current['transactions'] > 0 ? $current['units'] / $current['transactions'] : 0;
        $history_avg_per_transaction = $history['transactions'] > 0 ? $history['units'] / $history['transactions'] : 0;

        if ($history_avg_per_transaction == 0) {
            return false;
        }

        $ratio_change = abs($current_avg_per_transaction - $history_avg_per_transaction) / $history_avg_per_transaction;

        // Anomalous if units per transaction changes by >30%
        return $ratio_change > 0.30;
    }

    private function getProductInfo($product_id) {
        $sql = "SELECT product_id, name, sku FROM vend_products WHERE product_id = ? LIMIT 1";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$product_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return ['name' => 'Unknown'];
        }
    }

    private function calculateSeverity($deviation, $current_units, $product_info) {
        // Severity based on magnitude of deviation and volume
        $magnitude_score = min(100, abs($deviation) / 2);
        $volume_score = $current_units > 50 ? 20 : ($current_units > 10 ? 10 : 0);

        $severity_score = ($magnitude_score * 0.7) + ($volume_score * 0.3);

        if ($severity_score >= 80) {
            return 'Critical';
        } elseif ($severity_score >= 60) {
            return 'High';
        } elseif ($severity_score >= 40) {
            return 'Medium';
        } else {
            return 'Low';
        }
    }

    private function getAnomialyRecommendation($is_spike, $is_drop, $severity) {
        if (!$is_spike && !$is_drop) {
            return 'No action needed';
        }

        if ($is_spike) {
            if ($severity === 'Critical') {
                return 'URGENT: Demand spike detected. Increase stock immediately and reorder from supplier.';
            } else {
                return 'Demand spike detected. Monitor and consider restocking.';
            }
        }

        if ($is_drop) {
            if ($severity === 'Critical') {
                return 'URGENT: Sharp demand drop. Hold new orders and investigate root cause.';
            } else {
                return 'Demand drop detected. Review for promotions, competition, or seasonal factors.';
            }
        }

        return 'Investigate anomaly';
    }

    private function getActiveForecast($product_id, $outlet_id) {
        $sql = "
            SELECT *
            FROM forecast_predictions
            WHERE product_id = ?
                AND forecast_period_end >= CURDATE()
                AND forecast_date = (
                    SELECT MAX(forecast_date)
                    FROM forecast_predictions fp2
                    WHERE fp2.product_id = forecast_predictions.product_id
                )
        ";

        $params = [$product_id];
        if ($outlet_id) {
            $sql .= " AND outlet_id = ?";
            $params[] = $outlet_id;
        }

        $sql .= " LIMIT 1";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }

    private function getProjection($variance_pct) {
        if ($variance_pct > 15) {
            return 'EXCEED forecast - More stock may be needed';
        } elseif ($variance_pct > 5) {
            return 'Above forecast - Track closely';
        } elseif ($variance_pct >= -5) {
            return 'On track - Forecast accurate';
        } elseif ($variance_pct >= -15) {
            return 'Below forecast - Less stock needed';
        } else {
            return 'FALL SHORT of forecast - Reduce future orders';
        }
    }

    private function createDemandSignal($product_id, $anomaly_data) {
        $sql = "
            INSERT INTO demand_signals
            (product_id, signal_type, signal_value, confidence, data_source, impact_on_forecast, recorded_date, notes)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
        ";

        $signal_type = $anomaly_data['is_spike'] ? 'sales_velocity' : 'inventory_depletion';
        $signal_value = $anomaly_data['deviation_pct'] / 100;
        $confidence = min(95, $anomaly_data['anomaly_score'] + 20);

        $note = sprintf(
            'Auto-detected %s: %s%% deviation, severity: %s',
            $signal_type,
            $anomaly_data['deviation_pct'],
            $anomaly_data['severity']
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $product_id,
                $signal_type,
                $signal_value,
                $confidence,
                'anomaly_detector',
                $anomaly_data['anomaly_score'],
                $note,
            ]);
        } catch (Exception $e) {
            error_log("Failed to create demand signal: " . $e->getMessage());
        }
    }
}
