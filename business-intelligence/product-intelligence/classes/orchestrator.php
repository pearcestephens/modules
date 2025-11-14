<?php
/**
 * Intelligence System Integration & Orchestrator
 *
 * Wires together all BI components into a unified system:
 * - ScientificAnalyzer
 * - ProductIntelligenceGrabber
 * - ForecastingEngine
 * - AffinityAnalyzer
 * - Enhanced Dashboard
 *
 * Provides:
 * - Automated daily workflows
 * - API endpoints for external access
 * - Cron job integration
 * - End-to-end pipeline orchestration
 * - Health checks and monitoring
 *
 * @package IntelligenceHub\Modules\Intelligence
 * @version 1.0.0
 * @author Intelligence Hub Team
 */

namespace IntelligenceHub\Intelligence;

// Include all components
require_once __DIR__ . '/ScientificAnalyzer.php';
require_once __DIR__ . '/ProductIntelligenceGrabber.php';
require_once __DIR__ . '/ForecastingEngine.php';
require_once __DIR__ . '/AffinityAnalyzer.php';
require_once __DIR__ . '/dashboard.php';

class IntelligenceOrchestrator {
    
    private $db;
    private $logger;
    
    // Component instances
    private $analyzer;
    private $grabber;
    private $forecaster;
    private $affinity;
    private $dashboard;
    
    /**
     * Constructor
     *
     * @param PDO $db - Database connection
     * @param object $logger - Logging service (optional)
     */
    public function __construct($db, $logger = null) {
        $this->db = $db;
        $this->logger = $logger;
        
        // Initialize all components
        $this->initializeComponents();
    }
    
    /**
     * Initialize all intelligence components
     */
    private function initializeComponents() {
        try {
            $this->analyzer = new ScientificAnalyzer($this->db, $this->logger);
            $this->grabber = new ProductIntelligenceGrabber($this->db, null, $this->logger);
            $this->forecaster = new ForecastingEngine($this->db, $this->analyzer, $this->logger);
            $this->affinity = new AffinityAnalyzer($this->db, $this->logger);
            $this->dashboard = new IntelligenceDashboard(
                $this->db,
                $this->analyzer,
                $this->grabber,
                $this->forecaster,
                $this->affinity,
                $this->logger
            );
            
            $this->log("All intelligence components initialized successfully");
        } catch (\Exception $e) {
            $this->log("Component initialization failed: " . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    // ============================================================================
    // DAILY AUTOMATED WORKFLOWS
    // ============================================================================
    
    /**
     * Run complete daily intelligence pipeline
     *
     * This is the main cron job entry point
     * Executes all intelligence tasks in optimal sequence
     *
     * @return array - Summary of all operations
     */
    public function runDailyPipeline() {
        $start_time = microtime(true);
        $results = [
            'started_at' => date('Y-m-d H:i:s'),
            'tasks' => []
        ];
        
        try {
            $this->log("=== DAILY INTELLIGENCE PIPELINE STARTED ===");
            
            // Task 1: Snapshot all product prices
            $this->log("Task 1: Snapshotting product prices...");
            $snapshot_result = $this->grabber->snapshotAllProductPrices();
            $results['tasks']['price_snapshot'] = $snapshot_result;
            
            // Task 2: Update inventory status
            $this->log("Task 2: Updating inventory status...");
            $inventory_result = $this->grabber->updateAllInventoryStatus();
            $results['tasks']['inventory_update'] = $inventory_result;
            
            // Task 3: Record sales velocity
            $this->log("Task 3: Recording sales velocity...");
            $velocity_result = $this->recordAllSalesVelocity();
            $results['tasks']['velocity_recording'] = $velocity_result;
            
            // Task 4: Calculate price statistics
            $this->log("Task 4: Calculating price statistics...");
            $stats_result = $this->calculateAllPriceStatistics();
            $results['tasks']['price_statistics'] = $stats_result;
            
            // Task 5: Detect anomalies
            $this->log("Task 5: Detecting price anomalies...");
            $anomaly_result = $this->detectAllAnomalies();
            $results['tasks']['anomaly_detection'] = $anomaly_result;
            
            // Task 6: Generate forecasts
            $this->log("Task 6: Generating forecasts...");
            $forecast_result = $this->generateTopProductForecasts(20);
            $results['tasks']['forecasting'] = $forecast_result;
            
            // Task 7: Analyze product affinity
            $this->log("Task 7: Analyzing product affinity...");
            $affinity_result = $this->affinity->analyzeBasketAssociations(90);
            $results['tasks']['affinity_analysis'] = $affinity_result;
            
            // Task 8: Update competitive analysis
            $this->log("Task 8: Updating competitive analysis...");
            $competitive_result = $this->updateCompetitiveAnalysis();
            $results['tasks']['competitive_analysis'] = $competitive_result;
            
            // Task 9: Generate price recommendations
            $this->log("Task 9: Generating price recommendations...");
            $recommendations = $this->generatePriceRecommendations(50);
            $results['tasks']['price_recommendations'] = $recommendations;
            
            $end_time = microtime(true);
            $results['completed_at'] = date('Y-m-d H:i:s');
            $results['duration_seconds'] = round($end_time - $start_time, 2);
            $results['status'] = 'success';
            
            $this->log("=== DAILY PIPELINE COMPLETED in {$results['duration_seconds']}s ===");
            
        } catch (\Exception $e) {
            $results['status'] = 'failed';
            $results['error'] = $e->getMessage();
            $this->log("Pipeline failed: " . $e->getMessage(), 'error');
        }
        
        return $results;
    }
    
    /**
     * Record sales velocity for all products
     *
     * @return array - Result summary
     */
    private function recordAllSalesVelocity() {
        try {
            // Get active products
            $stmt = $this->db->prepare("
                SELECT DISTINCT product_id 
                FROM vend_sales 
                WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            ");
            $stmt->execute();
            $products = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            $recorded = 0;
            $failed = 0;
            
            foreach ($products as $product_id) {
                try {
                    $velocity = $this->grabber->getSalesVelocity($product_id);
                    $this->grabber->recordVelocitySnapshot($product_id, $velocity);
                    $recorded++;
                } catch (\Exception $e) {
                    $failed++;
                    $this->log("Velocity recording failed for product {$product_id}: " . $e->getMessage(), 'warning');
                }
            }
            
            return [
                'total_products' => count($products),
                'recorded' => $recorded,
                'failed' => $failed
            ];
            
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Calculate price statistics for all products
     *
     * @return array - Result summary
     */
    private function calculateAllPriceStatistics() {
        try {
            // Get products with price history
            $stmt = $this->db->prepare("
                SELECT DISTINCT product_id 
                FROM price_history_daily 
                WHERE competitor_name = 'Our Store'
                AND created_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            $products = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            $calculated = 0;
            $failed = 0;
            
            foreach ($products as $product_id) {
                try {
                    $stats = $this->analyzer->getProductPriceStatsSummary($product_id, 30);
                    
                    if (!isset($stats['error'])) {
                        // Store in database
                        $this->storePriceStatistics($product_id, $stats);
                        $calculated++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $this->log("Stats calculation failed for product {$product_id}: " . $e->getMessage(), 'warning');
                }
            }
            
            return [
                'total_products' => count($products),
                'calculated' => $calculated,
                'failed' => $failed
            ];
            
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Store price statistics in database
     *
     * @param string $product_id
     * @param array $stats
     */
    private function storePriceStatistics($product_id, $stats) {
        $stmt = $this->db->prepare("
            INSERT INTO price_statistics (
                product_id, calculation_date, period_days,
                current_price, min_price, max_price, avg_price,
                std_dev, variance, coefficient_variation,
                trend_slope, trend_direction, trend_strength, r_squared,
                anomaly_count, is_seasonal,
                confidence_score, data_points
            ) VALUES (
                ?, CURDATE(), 30,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?,
                ?, ?
            )
            ON DUPLICATE KEY UPDATE
                current_price = VALUES(current_price),
                min_price = VALUES(min_price),
                max_price = VALUES(max_price),
                avg_price = VALUES(avg_price),
                std_dev = VALUES(std_dev),
                variance = VALUES(variance),
                coefficient_variation = VALUES(coefficient_variation),
                trend_slope = VALUES(trend_slope),
                trend_direction = VALUES(trend_direction),
                trend_strength = VALUES(trend_strength),
                r_squared = VALUES(r_squared),
                anomaly_count = VALUES(anomaly_count),
                is_seasonal = VALUES(is_seasonal),
                confidence_score = VALUES(confidence_score),
                data_points = VALUES(data_points),
                updated_at = NOW()
        ");
        
        $stmt->execute([
            $product_id,
            $stats['current_price'] ?? 0,
            $stats['volatility']['min'] ?? 0,
            $stats['volatility']['max'] ?? 0,
            $stats['volatility']['mean'] ?? 0,
            $stats['volatility']['std_dev'] ?? 0,
            $stats['volatility']['variance'] ?? 0,
            $stats['volatility']['cv'] ?? 0,
            $stats['trend']['slope'] ?? 0,
            $stats['trend']['direction'] ?? 'stable',
            $stats['trend']['strength'] ?? 0,
            $stats['trend']['r_squared'] ?? 0,
            $stats['anomalies']['count'] ?? 0,
            0, // is_seasonal placeholder
            $stats['confidence_score'] ?? 75,
            count($stats['prices'] ?? [])
        ]);
    }
    
    /**
     * Detect anomalies for all products
     *
     * @return array - Result summary
     */
    private function detectAllAnomalies() {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT product_id 
                FROM price_history_daily 
                WHERE competitor_name = 'Our Store'
                AND created_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            $products = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            $anomalies_found = 0;
            
            foreach ($products as $product_id) {
                // Get prices
                $price_stmt = $this->db->prepare("
                    SELECT price, id 
                    FROM price_history_daily 
                    WHERE product_id = ? 
                    AND competitor_name = 'Our Store'
                    AND created_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    ORDER BY created_date ASC
                ");
                $price_stmt->execute([$product_id]);
                $price_data = $price_stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                if (count($price_data) < 7) continue;
                
                $prices = array_map(fn($p) => (float)$p['price'], $price_data);
                $anomalies = $this->analyzer->detectAnomalies($prices, 2.0);
                
                // Mark anomalies in database
                if (!empty($anomalies['anomalies'])) {
                    foreach ($anomalies['anomalies'] as $anomaly) {
                        $idx = $anomaly['index'];
                        if (isset($price_data[$idx])) {
                            $update_stmt = $this->db->prepare("
                                UPDATE price_history_daily 
                                SET is_anomaly = 1, anomaly_score = ? 
                                WHERE id = ?
                            ");
                            $update_stmt->execute([
                                $anomaly['z_score'],
                                $price_data[$idx]['id']
                            ]);
                            $anomalies_found++;
                        }
                    }
                }
            }
            
            return [
                'products_analyzed' => count($products),
                'anomalies_found' => $anomalies_found
            ];
            
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate forecasts for top products
     *
     * @param int $limit - Number of products to forecast
     * @return array - Result summary
     */
    private function generateTopProductForecasts($limit = 20) {
        try {
            // Get top selling products
            $stmt = $this->db->prepare("
                SELECT product_id 
                FROM vend_sales 
                WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY product_id 
                ORDER BY SUM(quantity) DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $products = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            $forecasted = 0;
            $failed = 0;
            
            foreach ($products as $product_id) {
                try {
                    $price_forecast = $this->forecaster->forecastPrices($product_id, 14, 90);
                    $demand_forecast = $this->forecaster->forecastDemand($product_id, 14);
                    
                    if (!isset($price_forecast['error']) && !isset($demand_forecast['error'])) {
                        // Store forecast results (would need forecast_results table)
                        $forecasted++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $this->log("Forecast failed for product {$product_id}: " . $e->getMessage(), 'warning');
                }
            }
            
            return [
                'products_forecasted' => $forecasted,
                'failed' => $failed
            ];
            
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Update competitive analysis for all products
     *
     * @return array - Result summary
     */
    private function updateCompetitiveAnalysis() {
        try {
            // Get products with competitor data
            $stmt = $this->db->prepare("
                SELECT DISTINCT 
                    phd1.product_id,
                    vp.product_name,
                    phd1.price as our_price
                FROM price_history_daily phd1
                INNER JOIN vend_products vp ON phd1.product_id = vp.product_id
                WHERE phd1.competitor_name = 'Our Store'
                AND phd1.created_date = CURDATE()
                AND EXISTS (
                    SELECT 1 FROM competitive_prices cp
                    WHERE cp.product_name LIKE CONCAT('%', vp.product_name, '%')
                    AND cp.scraped_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                )
                LIMIT 100
            ");
            $stmt->execute();
            $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $analyzed = 0;
            
            foreach ($products as $product) {
                try {
                    // Get competitor prices
                    $comp_stmt = $this->db->prepare("
                        SELECT price, competitor_name
                        FROM competitive_prices
                        WHERE product_name LIKE ?
                        AND scraped_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                    ");
                    $comp_stmt->execute(['%' . $product['product_name'] . '%']);
                    $competitors = $comp_stmt->fetchAll(\PDO::FETCH_ASSOC);
                    
                    if (empty($competitors)) continue;
                    
                    $competitor_prices = array_map(fn($c) => (float)$c['price'], $competitors);
                    $our_price = (float)$product['our_price'];
                    
                    // Calculate position
                    $position = $this->analyzer->calculateCompetitivePosition($our_price, $competitor_prices);
                    
                    // Store analysis
                    $insert_stmt = $this->db->prepare("
                        INSERT INTO competitor_analysis (
                            product_id, analysis_date, our_product_name, our_price,
                            competitors_tracking, competitors_with_product,
                            min_competitor_price, max_competitor_price, avg_competitor_price,
                            our_percentile, price_position,
                            gap_to_lowest, gap_pct_to_lowest,
                            has_price_advantage, competitive_score,
                            recommended_action, competitor_details
                        ) VALUES (
                            ?, CURDATE(), ?, ?,
                            ?, ?,
                            ?, ?, ?,
                            ?, ?,
                            ?, ?,
                            ?, ?,
                            ?, ?
                        )
                        ON DUPLICATE KEY UPDATE
                            our_price = VALUES(our_price),
                            competitors_with_product = VALUES(competitors_with_product),
                            min_competitor_price = VALUES(min_competitor_price),
                            max_competitor_price = VALUES(max_competitor_price),
                            avg_competitor_price = VALUES(avg_competitor_price),
                            our_percentile = VALUES(our_percentile),
                            price_position = VALUES(price_position),
                            gap_to_lowest = VALUES(gap_to_lowest),
                            gap_pct_to_lowest = VALUES(gap_pct_to_lowest),
                            has_price_advantage = VALUES(has_price_advantage),
                            competitive_score = VALUES(competitive_score),
                            recommended_action = VALUES(recommended_action),
                            competitor_details = VALUES(competitor_details),
                            updated_at = NOW()
                    ");
                    
                    $insert_stmt->execute([
                        $product['product_id'],
                        $product['product_name'],
                        $our_price,
                        count($competitors),
                        count($competitors),
                        min($competitor_prices),
                        max($competitor_prices),
                        array_sum($competitor_prices) / count($competitor_prices),
                        $position['percentile'],
                        $position['position'],
                        $our_price - min($competitor_prices),
                        (($our_price - min($competitor_prices)) / min($competitor_prices)) * 100,
                        $our_price <= min($competitor_prices) ? 1 : 0,
                        $position['percentile'] > 50 ? 40 : 70,
                        $position['strategy'],
                        json_encode($competitors)
                    ]);
                    
                    $analyzed++;
                    
                } catch (\Exception $e) {
                    $this->log("Competitive analysis failed for product {$product['product_id']}: " . $e->getMessage(), 'warning');
                }
            }
            
            return [
                'products_analyzed' => $analyzed
            ];
            
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate price recommendations based on all intelligence
     *
     * @param int $limit - Number of recommendations to generate
     * @return array - Result summary
     */
    private function generatePriceRecommendations($limit = 50) {
        try {
            // Get products needing recommendations
            $stmt = $this->db->prepare("
                SELECT 
                    vp.product_id,
                    vp.product_name,
                    vp.price as current_price,
                    vp.cost as current_cost,
                    ca.avg_competitor_price,
                    ca.min_competitor_price,
                    ca.price_position,
                    ps.trend_direction,
                    ps.trend_strength
                FROM vend_products vp
                LEFT JOIN competitor_analysis ca ON vp.product_id = ca.product_id AND ca.analysis_date = CURDATE()
                LEFT JOIN price_statistics ps ON vp.product_id = ps.product_id AND ps.calculation_date = CURDATE()
                WHERE vp.active = 1
                AND vp.price > 0
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $generated = 0;
            
            foreach ($products as $product) {
                try {
                    $recommended_price = $product['current_price'];
                    $strategy = 'hold';
                    $reasoning = '';
                    $priority = 'low';
                    
                    // Decision logic
                    if ($product['avg_competitor_price'] && $product['current_price'] > $product['avg_competitor_price'] * 1.1) {
                        $recommended_price = $product['avg_competitor_price'] * 0.98; // Undercut by 2%
                        $strategy = 'undercut';
                        $reasoning = 'Price 10%+ above market average - recommend competitive pricing';
                        $priority = 'medium';
                    } elseif ($product['trend_direction'] === 'decreasing' && $product['trend_strength'] > 30) {
                        $recommended_price = $product['current_price'] * 0.95; // 5% decrease
                        $strategy = 'elastic';
                        $reasoning = 'Strong downward price trend detected - proactive adjustment';
                        $priority = 'medium';
                    }
                    
                    if ($recommended_price != $product['current_price']) {
                        // Store recommendation
                        $insert_stmt = $this->db->prepare("
                            INSERT INTO price_recommendations_v2 (
                                product_id, current_price, current_cost,
                                recommended_price, price_change_pct,
                                strategy, reasoning, priority,
                                avg_competitor_price, min_competitor_price,
                                our_position, confidence_score,
                                expires_at, status
                            ) VALUES (
                                ?, ?, ?,
                                ?, ?,
                                ?, ?, ?,
                                ?, ?,
                                ?, ?,
                                DATE_ADD(NOW(), INTERVAL 7 DAY), 'pending'
                            )
                        ");
                        
                        $insert_stmt->execute([
                            $product['product_id'],
                            $product['current_price'],
                            $product['current_cost'],
                            $recommended_price,
                            (($recommended_price - $product['current_price']) / $product['current_price']) * 100,
                            $strategy,
                            $reasoning,
                            $priority,
                            $product['avg_competitor_price'],
                            $product['min_competitor_price'],
                            $product['price_position'],
                            75
                        ]);
                        
                        $generated++;
                    }
                } catch (\Exception $e) {
                    $this->log("Recommendation generation failed for product {$product['product_id']}: " . $e->getMessage(), 'warning');
                }
            }
            
            return [
                'products_evaluated' => count($products),
                'recommendations_generated' => $generated
            ];
            
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    // ============================================================================
    // API ENDPOINTS
    // ============================================================================
    
    /**
     * Get dashboard data (API endpoint)
     *
     * @param array $filters
     * @return array
     */
    public function apiGetDashboard($filters = []) {
        return $this->dashboard->getDashboardData($filters);
    }
    
    /**
     * Get product intelligence report (API endpoint)
     *
     * @param int $product_id
     * @return array
     */
    public function apiGetProductIntelligence($product_id) {
        return [
            'product_id' => $product_id,
            'details' => $this->grabber->getProductDetails($product_id),
            'forecast' => $this->forecaster->generateCompleteForecastReport($product_id),
            'affinity' => $this->affinity->generateProductAffinityReport($product_id),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    // ============================================================================
    // HEALTH & MONITORING
    // ============================================================================
    
    /**
     * System health check
     *
     * @return array - Health status
     */
    public function healthCheck() {
        $health = [
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'healthy',
            'components' => []
        ];
        
        // Check database
        try {
            $this->db->query("SELECT 1");
            $health['components']['database'] = 'ok';
        } catch (\Exception $e) {
            $health['components']['database'] = 'failed';
            $health['status'] = 'degraded';
        }
        
        // Check tables exist
        $required_tables = [
            'price_history_daily',
            'sales_velocity_history',
            'price_statistics',
            'price_recommendations_v2',
            'product_affinity',
            'competitor_analysis'
        ];
        
        foreach ($required_tables as $table) {
            try {
                $stmt = $this->db->query("SELECT COUNT(*) FROM `{$table}` LIMIT 1");
                $health['components']["table_{$table}"] = 'ok';
            } catch (\Exception $e) {
                $health['components']["table_{$table}"] = 'missing';
                $health['status'] = 'unhealthy';
            }
        }
        
        return $health;
    }
    
    /**
     * Logging helper
     *
     * @param string $message
     * @param string $level
     */
    private function log($message, $level = 'info') {
        if ($this->logger) {
            $this->logger->log($level, $message);
        } else {
            echo "[" . date('Y-m-d H:i:s') . "] [{$level}] {$message}\n";
        }
    }
}

// ============================================================================
// CLI / CRON ENTRY POINT
// ============================================================================

// Only execute if called directly (not via include/require)
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    
    echo "\n";
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║     ULTIMATE BI SYSTEM - Intelligence Orchestrator          ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    
    // Load environment and database config
    $env_file = __DIR__ . '/../../.env';
    if (file_exists($env_file)) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value);
            }
        }
    }
    
    // Connect to database
    try {
        $db = new \PDO(
            "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
            $_ENV['DB_USER'],
            $_ENV['DB_PASS'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        
        echo "✓ Database connected\n";
        
        // Initialize orchestrator
        $orchestrator = new IntelligenceOrchestrator($db);
        echo "✓ Orchestrator initialized\n\n";
        
        // Check command line argument
        $action = $argv[1] ?? 'pipeline';
        
        switch ($action) {
            case 'pipeline':
            case 'daily':
                echo "Running daily intelligence pipeline...\n\n";
                $result = $orchestrator->runDailyPipeline();
                echo "\n" . json_encode($result, JSON_PRETTY_PRINT) . "\n";
                break;
                
            case 'health':
                echo "Running health check...\n\n";
                $result = $orchestrator->healthCheck();
                echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
                break;
                
            default:
                echo "Unknown action: {$action}\n";
                echo "Available actions: pipeline, daily, health\n";
                exit(1);
        }
        
        echo "\n✓ Completed successfully\n\n";
        
    } catch (\Exception $e) {
        echo "\n✗ Error: " . $e->getMessage() . "\n\n";
        exit(1);
    }
}

?>
