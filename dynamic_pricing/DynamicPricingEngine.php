<?php
/**
 * Dynamic Pricing Engine
 *
 * AI-powered price optimization based on competitive intelligence
 * Analyzes market data and recommends optimal pricing
 * Integrates with Vend API for automated price updates
 *
 * @package CIS\DynamicPricing
 * @version 1.0.1
 */

namespace CIS\DynamicPricing;

require_once __DIR__ . '/CentralLogger.php';

class DynamicPricingEngine {

    private $db;
    private $logger;
    private $config;

    // Pricing strategies
    const STRATEGY_MATCH = 'match';           // Match lowest competitor
    const STRATEGY_UNDERCUT = 'undercut';     // Beat lowest by X%
    const STRATEGY_PREMIUM = 'premium';       // Price above market
    const STRATEGY_MARGIN = 'margin_optimize'; // Optimize for margin

    public function __construct($db, $config = []) {
        $this->db = $db;

        $this->config = array_merge([
            'default_strategy' => self::STRATEGY_MARGIN,
            'undercut_percent' => 5,
            'premium_percent' => 10,
            'min_margin_percent' => 15,
            'target_margin_percent' => 35,
            'max_margin_percent' => 60,
            'price_change_threshold' => 2, // Only recommend if change > 2%
            'auto_approve_under' => 5, // Auto-approve changes under 5%
            'vend_api_enabled' => false,
            'vend_api_url' => 'https://vapeshed.vendhq.com/api/2.0',
            'vend_api_token' => getenv('VEND_API_TOKEN'),
        ], $config);

        $this->logger = new CentralLogger($db, 'dynamic_pricing', [
            'enable_db_logging' => true,
            'enable_file_logging' => true,
        ]);

        $this->logger->info("DynamicPricingEngine initialized", [
            'strategy' => $this->config['default_strategy'],
            'vend_enabled' => $this->config['vend_api_enabled'],
        ]);
    }

    /**
     * Analyze competitive data and generate pricing recommendations
     */
    public function generateRecommendations() {
        $timer = $this->logger->startTimer('generate_pricing_recommendations');

        $this->logger->info("Generating pricing recommendations");

        $recommendations = [];

        // Get our products
        $ourProducts = $this->getOurProducts();

        foreach ($ourProducts as $product) {
            try {
                $recommendation = $this->analyzeProduct($product);

                if ($recommendation) {
                    $this->saveRecommendation($recommendation);
                    $recommendations[] = $recommendation;
                }

            } catch (\Exception $e) {
                $this->logger->error("Failed to analyze product: {$product['name']}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->endTimer($timer, true, [
            'products_analyzed' => count($ourProducts),
            'recommendations_generated' => count($recommendations),
        ]);

        return $recommendations;
    }

    /**
     * Analyze individual product pricing
     */
    private function analyzeProduct($product) {
        // Get competitive prices for similar products
        $competitivePrices = $this->getCompetitivePrices($product['name']);

        if (empty($competitivePrices)) {
            $this->logger->debug("No competitive data for: {$product['name']}");
            return null;
        }

        // Calculate market statistics
        $marketStats = $this->calculateMarketStats($competitivePrices);

        // Get our cost
        $cost = $product['cost'] ?? 0;
        $currentPrice = $product['price'];

        // Apply pricing strategy
        $recommendedPrice = $this->applyPricingStrategy(
            $currentPrice,
            $cost,
            $marketStats,
            $this->config['default_strategy']
        );

        // Calculate metrics
        $priceChange = $recommendedPrice - $currentPrice;
        $priceChangePercent = ($priceChange / $currentPrice) * 100;

        // Only recommend if change is significant
        if (abs($priceChangePercent) < $this->config['price_change_threshold']) {
            return null;
        }

        // Calculate margins
        $currentMargin = (($currentPrice - $cost) / $currentPrice) * 100;
        $recommendedMargin = (($recommendedPrice - $cost) / $recommendedPrice) * 100;

        // Validate margin constraints
        if ($recommendedMargin < $this->config['min_margin_percent']) {
            $this->logger->warning("Recommended price below min margin for: {$product['name']}", [
                'recommended_margin' => $recommendedMargin,
                'min_margin' => $this->config['min_margin_percent'],
            ]);

            // Adjust to meet min margin
            $recommendedPrice = $cost / (1 - $this->config['min_margin_percent'] / 100);
            $recommendedMargin = $this->config['min_margin_percent'];
        }

        // Build recommendation
        $recommendation = [
            'product_id' => $product['id'],
            'product_name' => $product['name'],
            'current_price' => $currentPrice,
            'recommended_price' => round($recommendedPrice, 2),
            'price_change_percent' => round($priceChangePercent, 2),
            'reason' => $this->buildRecommendationReason($marketStats, $priceChangePercent),
            'confidence_score' => $this->calculateConfidenceScore($competitivePrices, $marketStats),
            'competitor_data' => json_encode([
                'count' => count($competitivePrices),
                'min' => $marketStats['min'],
                'max' => $marketStats['max'],
                'avg' => $marketStats['avg'],
                'median' => $marketStats['median'],
            ]),
            'margin_analysis' => json_encode([
                'current_margin' => round($currentMargin, 2),
                'recommended_margin' => round($recommendedMargin, 2),
                'cost' => $cost,
            ]),
            'status' => 'pending',
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        // Auto-approve if small change
        if (abs($priceChangePercent) < $this->config['auto_approve_under']) {
            $recommendation['status'] = 'approved';
            $recommendation['auto_approved'] = true;
        }

        $this->logger->info("Generated recommendation for: {$product['name']}", [
            'current_price' => $currentPrice,
            'recommended_price' => $recommendedPrice,
            'change_percent' => round($priceChangePercent, 2),
        ]);

        return $recommendation;
    }

    /**
     * Apply pricing strategy
     */
    private function applyPricingStrategy($currentPrice, $cost, $marketStats, $strategy) {
        switch ($strategy) {
            case self::STRATEGY_MATCH:
                // Match lowest competitor
                return $marketStats['min'];

            case self::STRATEGY_UNDERCUT:
                // Beat lowest by X%
                $undercut = $marketStats['min'] * (1 - $this->config['undercut_percent'] / 100);
                return max($undercut, $cost * 1.15); // Ensure min margin

            case self::STRATEGY_PREMIUM:
                // Price above average
                return $marketStats['avg'] * (1 + $this->config['premium_percent'] / 100);

            case self::STRATEGY_MARGIN:
            default:
                // Optimize for target margin while staying competitive
                $targetPrice = $cost / (1 - $this->config['target_margin_percent'] / 100);

                // If target price is way above market, use average
                if ($targetPrice > $marketStats['avg'] * 1.2) {
                    return $marketStats['avg'];
                }

                // If target price is below market minimum, we can charge more
                if ($targetPrice < $marketStats['min']) {
                    return ($targetPrice + $marketStats['min']) / 2;
                }

                return $targetPrice;
        }
    }

    /**
     * Calculate market statistics
     */
    private function calculateMarketStats($prices) {
        $priceValues = array_column($prices, 'price');
        sort($priceValues);

        $count = count($priceValues);
        $median = $count % 2 === 0
            ? ($priceValues[$count/2 - 1] + $priceValues[$count/2]) / 2
            : $priceValues[floor($count/2)];

        return [
            'min' => min($priceValues),
            'max' => max($priceValues),
            'avg' => array_sum($priceValues) / $count,
            'median' => $median,
            'count' => $count,
        ];
    }

    /**
     * Build recommendation reason
     */
    private function buildRecommendationReason($marketStats, $priceChangePercent) {
        $direction = $priceChangePercent > 0 ? 'increase' : 'decrease';
        $absChange = abs($priceChangePercent);

        $reason = "Recommended {$direction} of {$absChange}% based on market analysis. ";
        $reason .= "Current market range: \${$marketStats['min']} - \${$marketStats['max']}, ";
        $reason .= "Average: \${$marketStats['avg']}. ";
        $reason .= "This pricing optimizes your margin while remaining competitive.";

        return $reason;
    }

    /**
     * Calculate confidence score
     */
    private function calculateConfidenceScore($prices, $marketStats) {
        $count = count($prices);

        // More data = higher confidence
        if ($count >= 5) {
            $confidenceBase = 90;
        } elseif ($count >= 3) {
            $confidenceBase = 75;
        } else {
            $confidenceBase = 60;
        }

        // Tight price range = higher confidence
        $priceRange = $marketStats['max'] - $marketStats['min'];
        $rangePercent = ($priceRange / $marketStats['avg']) * 100;

        if ($rangePercent < 10) {
            $confidenceBonus = 10;
        } elseif ($rangePercent < 20) {
            $confidenceBonus = 5;
        } else {
            $confidenceBonus = 0;
        }

        return min(100, $confidenceBase + $confidenceBonus);
    }

    /**
     * Get competitive prices for product
     */
    private function getCompetitivePrices($productName) {
        try {
            // Fuzzy match on product name
            $searchTerm = '%' . $productName . '%';

            $stmt = $this->db->prepare("
                SELECT competitor_name, product_name, price, scraped_at
                FROM competitive_prices
                WHERE product_name LIKE ?
                AND scraped_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND in_stock = TRUE
                ORDER BY scraped_at DESC
            ");

            $stmt->execute([$searchTerm]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\PDOException $e) {
            $this->logger->error("Failed to get competitive prices", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get our products (from Vend or local DB)
     */
    private function getOurProducts() {
        // TODO: Integrate with Vend API to get real products
        // For now, return dummy data

        try {
            $stmt = $this->db->query("
                SELECT id, name, price, cost
                FROM products
                WHERE active = TRUE
                LIMIT 100
            ");

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\PDOException $e) {
            $this->logger->error("Failed to get our products", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Save recommendation to database
     */
    private function saveRecommendation($recommendation) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO dynamic_pricing_recommendations (
                    product_id, product_name, current_price, recommended_price,
                    price_change_percent, reason, confidence_score,
                    competitor_data, margin_analysis, status, generated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $recommendation['product_id'],
                $recommendation['product_name'],
                $recommendation['current_price'],
                $recommendation['recommended_price'],
                $recommendation['price_change_percent'],
                $recommendation['reason'],
                $recommendation['confidence_score'],
                $recommendation['competitor_data'],
                $recommendation['margin_analysis'],
                $recommendation['status'],
                $recommendation['generated_at'],
            ]);

        } catch (\PDOException $e) {
            $this->logger->error("Failed to save recommendation", [
                'product' => $recommendation['product_name'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Approve recommendation
     */
    public function approveRecommendation($recommendationId, $userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE dynamic_pricing_recommendations
                SET status = 'approved',
                    reviewed_by = ?,
                    reviewed_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([$userId, $recommendationId]);

            $this->logger->info("Recommendation approved", [
                'recommendation_id' => $recommendationId,
                'user_id' => $userId,
            ]);

            return true;

        } catch (\PDOException $e) {
            $this->logger->error("Failed to approve recommendation", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Apply approved recommendations to Vend
     */
    public function applyApprovedRecommendations() {
        $timer = $this->logger->startTimer('apply_approved_recommendations');

        try {
            // Get approved recommendations
            $stmt = $this->db->query("
                SELECT * FROM dynamic_pricing_recommendations
                WHERE status = 'approved'
                AND applied_at IS NULL
            ");

            $recommendations = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $applied = 0;
            $failed = 0;

            foreach ($recommendations as $rec) {
                if ($this->applyPriceToVend($rec)) {
                    $this->markAsApplied($rec['id']);
                    $applied++;
                } else {
                    $failed++;
                }
            }

            $this->logger->endTimer($timer, true, [
                'applied' => $applied,
                'failed' => $failed,
            ]);

            return ['applied' => $applied, 'failed' => $failed];

        } catch (\Exception $e) {
            $this->logger->error("Failed to apply recommendations", ['error' => $e->getMessage()]);
            return ['applied' => 0, 'failed' => 0];
        }
    }

    /**
     * Apply price to Vend via API
     */
    private function applyPriceToVend($recommendation) {
        if (!$this->config['vend_api_enabled']) {
            $this->logger->warning("Vend API disabled, skipping price update");
            return false;
        }

        try {
            // TODO: Implement actual Vend API integration
            // This is placeholder code

            $url = $this->config['vend_api_url'] . '/products/' . $recommendation['product_id'];

            $data = [
                'price' => $recommendation['recommended_price'],
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->config['vend_api_token'],
                    'Content-Type: application/json',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $this->logger->info("Price updated in Vend", [
                    'product' => $recommendation['product_name'],
                    'new_price' => $recommendation['recommended_price'],
                ]);
                return true;
            } else {
                $this->logger->error("Vend API error", [
                    'http_code' => $httpCode,
                    'response' => $response,
                ]);
                return false;
            }

        } catch (\Exception $e) {
            $this->logger->error("Failed to update Vend price", [
                'product' => $recommendation['product_name'],
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Mark recommendation as applied
     */
    private function markAsApplied($recommendationId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE dynamic_pricing_recommendations
                SET status = 'applied',
                    applied_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([$recommendationId]);

        } catch (\PDOException $e) {
            $this->logger->error("Failed to mark as applied", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get pending recommendations
     */
    public function getPendingRecommendations($limit = 100) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM dynamic_pricing_recommendations
                WHERE status = 'pending'
                ORDER BY confidence_score DESC, generated_at DESC
                LIMIT ?
            ");

            $stmt->execute([$limit]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Get recommendation stats
     */
    public function getStats() {
        try {
            $stmt = $this->db->query("
                SELECT
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
                    COUNT(CASE WHEN status = 'applied' THEN 1 END) as applied,
                    AVG(confidence_score) as avg_confidence,
                    AVG(ABS(price_change_percent)) as avg_change_percent
                FROM dynamic_pricing_recommendations
                WHERE generated_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");

            return $stmt->fetch(\PDO::FETCH_ASSOC);

        } catch (\PDOException $e) {
            return [];
        }
    }
}
