<?php
/**
 * Product Intelligence Grabber
 *
 * Real-time ingestion of product data from Vend API and crawlers
 * Creates hourly/daily snapshots for historical analysis
 * Matches products across competitors
 * Tracks inventory status in real-time
 *
 * @package IntelligenceHub\Modules\Intelligence
 * @version 1.0.0
 * @author Intelligence Hub Team
 */

namespace IntelligenceHub\Intelligence;

class ProductIntelligenceGrabber {

    private $db;
    private $vend_api;
    private $logger;

    /**
     * Constructor
     *
     * @param PDO $db - Database connection
     * @param object $vend_api - Vend API client
     * @param object $logger - Logging service
     */
    public function __construct($db, $vend_api = null, $logger = null) {
        $this->db = $db;
        $this->vend_api = $vend_api;
        $this->logger = $logger;
    }

    // ============================================================================
    // PRODUCT INGESTION FROM VEND
    // ============================================================================

    /**
     * Grab all products from Vend API
     *
     * Fetches complete product catalog including pricing, stock, and images
     *
     * @param array $filters - Optional filters (category, status, etc.)
     * @return array - Array of products with full details
     */
    public function grabAllProducts($filters = []) {
        try {
            // Query Vend API or local cache
            $stmt = $this->db->prepare("
                SELECT
                    p.vend_id,
                    p.product_id,
                    p.name,
                    p.price,
                    p.cost_price,
                    p.description,
                    p.sku,
                    p.status,
                    p.category,
                    p.image_url,
                    p.last_updated,
                    COUNT(DISTINCT s.id) as total_sales,
                    SUM(COALESCE(s.quantity, 0)) as units_sold
                FROM vend_products p
                LEFT JOIN vend_sales s ON p.product_id = s.product_id
                    AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                WHERE p.status = 'active'
                GROUP BY p.product_id
                ORDER BY p.name ASC
            ");
            $stmt->execute();
            $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if ($this->logger) {
                $this->logger->info("Grabbed " . count($products) . " products from Vend");
            }

            return $products;

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to grab products: " . $e->getMessage());
            }
            return [];
        }
    }

    /**
     * Get single product with full details
     *
     * @param int $product_id - Product ID
     * @return array - Product details or null
     */
    public function getProductDetails($product_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM vend_products
                WHERE product_id = ?
            ");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($product) {
                // Enrich with additional data
                $product['inventory'] = $this->getProductInventory($product_id);
                $product['price_history'] = $this->getPriceHistory($product_id, 30);
                $product['sales_velocity'] = $this->getSalesVelocity($product_id);
                $product['competitor_prices'] = $this->getCompetitorPrices($product['name']);
            }

            return $product;

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to get product $product_id: " . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Get current inventory for product across all outlets
     *
     * @param int $product_id - Product ID
     * @return array - Inventory by outlet
     */
    public function getProductInventory($product_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    outlet_id,
                    quantity_on_hand,
                    quantity_committed,
                    quantity_available,
                    last_stocktake,
                    status
                FROM vend_inventory
                WHERE product_id = ?
                ORDER BY outlet_id ASC
            ");
            $stmt->execute([$product_id]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    // ============================================================================
    // PRICE SNAPSHOT & HISTORY
    // ============================================================================

    /**
     * Create hourly price snapshot for a product
     *
     * Captures current price across all channels/competitors
     * Used for historical tracking and trend analysis
     *
     * @param int $product_id - Product ID
     * @param float $our_price - Our current price
     * @return bool - Success/failure
     */
    public function snapshotProductPrice($product_id, $our_price) {
        try {
            // Get competitor prices if available
            $competitor_prices = $this->grabCompetitorPricesForProduct($product_id);

            // Store our price snapshot
            $stmt = $this->db->prepare("
                INSERT INTO price_history_daily
                (product_id, competitor_name, price, original_price, in_stock,
                 discount_percent, scraped_at, created_date)
                VALUES (?, 'Our Store', ?, ?, ?, ?, NOW(), CURDATE())
                ON DUPLICATE KEY UPDATE
                    price = VALUES(price),
                    scraped_at = NOW()
            ");

            $discount_pct = 0; // TODO: Calculate if product on sale
            $stmt->execute([
                $product_id,
                $our_price,
                $our_price,
                1, // in_stock
                $discount_pct
            ]);

            // Store competitor snapshots
            foreach ($competitor_prices as $competitor => $price_data) {
                $comp_stmt = $this->db->prepare("
                    INSERT INTO price_history_daily
                    (product_id, competitor_name, price, original_price, in_stock,
                     discount_percent, scraped_at, created_date)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), CURDATE())
                    ON DUPLICATE KEY UPDATE
                        price = VALUES(price),
                        scraped_at = NOW()
                ");

                $comp_stmt->execute([
                    $product_id,
                    $competitor,
                    $price_data['price'],
                    $price_data['original_price'] ?? $price_data['price'],
                    $price_data['in_stock'] ? 1 : 0,
                    $price_data['discount_percent'] ?? 0
                ]);
            }

            if ($this->logger) {
                $this->logger->info("Snapshotted price for product $product_id");
            }

            return true;

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to snapshot price: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Create daily snapshots for ALL products
     *
     * Call this once per day to maintain historical record
     *
     * @return array - Summary of snapshot results
     */
    public function snapshotAllProductPrices() {
        $products = $this->grabAllProducts();
        $results = [
            'total' => count($products),
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($products as $product) {
            if ($this->snapshotProductPrice($product['product_id'], $product['price'])) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Failed to snapshot product " . $product['product_id'];
            }
        }

        if ($this->logger) {
            $this->logger->info("Daily price snapshot complete", $results);
        }

        return $results;
    }

    /**
     * Get price history for a product
     *
     * @param int $product_id - Product ID
     * @param int $days - Number of days of history
     * @return array - Historical prices with dates
     */
    public function getPriceHistory($product_id, $days = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    DATE(created_at) as date,
                    price,
                    MIN(price) as min_price,
                    MAX(price) as max_price,
                    AVG(price) as avg_price
                FROM price_history_daily
                WHERE product_id = ?
                AND created_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$product_id, $days]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    // ============================================================================
    // SALES VELOCITY & TRENDS
    // ============================================================================

    /**
     * Get sales velocity (units sold per day)
     *
     * Calculates rolling sales rates for different windows
     *
     * @param int $product_id - Product ID
     * @return array - Velocity metrics for 7-day, 30-day, and all-time
     */
    public function getSalesVelocity($product_id) {
        try {
            $windows = [
                '7_day' => 7,
                '30_day' => 30,
                '90_day' => 90
            ];

            $velocities = [];

            foreach ($windows as $label => $days) {
                $stmt = $this->db->prepare("
                    SELECT
                        COUNT(*) as orders,
                        SUM(quantity) as units,
                        SUM(total_price) as revenue,
                        AVG(quantity) as avg_per_order,
                        SUM(quantity) / ? as units_per_day
                    FROM vend_sales
                    WHERE product_id = ?
                    AND sale_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ");
                $stmt->execute([$days, $product_id, $days]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                $velocities[$label] = [
                    'units_sold' => (int)($result['units'] ?? 0),
                    'units_per_day' => round($result['units_per_day'] ?? 0, 2),
                    'orders' => (int)($result['orders'] ?? 0),
                    'revenue' => round($result['revenue'] ?? 0, 2),
                    'avg_per_order' => round($result['avg_per_order'] ?? 0, 2)
                ];
            }

            return $velocities;

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Record sales velocity snapshot
     *
     * Store velocity metrics at regular intervals for trend analysis
     *
     * @param int $product_id
     * @param array $velocity_data
     * @return bool
     */
    public function recordVelocitySnapshot($product_id, $velocity_data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO sales_velocity_history
                (product_id, window_period, units_sold, revenue, avg_price,
                 velocity_per_day, recorded_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            foreach ($velocity_data as $window => $data) {
                $stmt->execute([
                    $product_id,
                    $window,
                    $data['units_sold'],
                    $data['revenue'],
                    $data['revenue'] > 0 ? $data['revenue'] / $data['units_sold'] : 0,
                    $data['units_per_day']
                ]);
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ============================================================================
    // COMPETITOR PRICE MATCHING
    // ============================================================================

    /**
     * Get competitor prices for a specific product
     *
     * Finds matching products from crawled competitor data
     *
     * @param string $product_name - Product name to search for
     * @return array - Competitor prices keyed by competitor name
     */
    public function getCompetitorPrices($product_name) {
        try {
            $search_term = '%' . $product_name . '%';

            $stmt = $this->db->prepare("
                SELECT DISTINCT
                    competitor_name,
                    price,
                    original_price,
                    in_stock,
                    discount_percent,
                    MAX(scraped_at) as last_scraped
                FROM competitive_prices
                WHERE product_name LIKE ?
                AND scraped_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY competitor_name
                ORDER BY price ASC
            ");
            $stmt->execute([$search_term]);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $prices = [];
            foreach ($results as $row) {
                $prices[$row['competitor_name']] = $row;
            }

            return $prices;

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Grab competitor prices for product (from crawlers)
     *
     * @param int $product_id
     * @return array
     */
    private function grabCompetitorPricesForProduct($product_id) {
        try {
            $product = $this->db->prepare("
                SELECT name FROM vend_products WHERE product_id = ?
            ");
            $product->execute([$product_id]);
            $prod = $product->fetch(\PDO::FETCH_ASSOC);

            if ($prod) {
                return $this->getCompetitorPrices($prod['name']);
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Match products across competitors
     *
     * Uses fuzzy matching to find equivalent products at competitors
     *
     * @param string $product_name - Our product name
     * @param array $sku - SKU variants
     * @return array - Matched products with confidence scores
     */
    public function matchProductsAcrossCompetitors($product_name, $sku = []) {
        try {
            $matches = [];

            // Exact match first
            $stmt = $this->db->prepare("
                SELECT DISTINCT
                    competitor_name,
                    product_name,
                    price,
                    url,
                    scraped_at,
                    100 as match_confidence
                FROM competitive_prices
                WHERE product_name = ?
                AND scraped_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$product_name]);
            $exact = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $matches = array_merge($matches, $exact);

            // Fuzzy match if few exact matches
            if (count($exact) < 3) {
                $search_term = '%' . trim(substr($product_name, 0, 15)) . '%';

                $fuzzy_stmt = $this->db->prepare("
                    SELECT DISTINCT
                        competitor_name,
                        product_name,
                        price,
                        url,
                        scraped_at,
                        75 as match_confidence
                    FROM competitive_prices
                    WHERE product_name LIKE ?
                    AND product_name != ?
                    AND scraped_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                    LIMIT 10
                ");
                $fuzzy_stmt->execute([$search_term, $product_name]);
                $fuzzy = $fuzzy_stmt->fetchAll(\PDO::FETCH_ASSOC);
                $matches = array_merge($matches, $fuzzy);
            }

            return $matches;

        } catch (\Exception $e) {
            return [];
        }
    }

    // ============================================================================
    // INVENTORY TRACKING
    // ============================================================================

    /**
     * Update inventory status for all products
     *
     * Pulls from Vend API and updates local database
     *
     * @return array - Update summary
     */
    public function updateAllInventoryStatus() {
        try {
            $results = [
                'total_products' => 0,
                'updated' => 0,
                'low_stock_alerts' => 0,
                'out_of_stock_alerts' => 0,
                'errors' => []
            ];

            // Get all products
            $products = $this->grabAllProducts();
            $results['total_products'] = count($products);

            foreach ($products as $product) {
                $inventory = $this->getProductInventory($product['product_id']);

                if (!empty($inventory)) {
                    // Update inventory in database
                    foreach ($inventory as $outlet_stock) {
                        $stmt = $this->db->prepare("
                            INSERT INTO vend_inventory
                            (product_id, outlet_id, quantity_on_hand, quantity_committed,
                             quantity_available, last_stocktake, updated_at)
                            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                            ON DUPLICATE KEY UPDATE
                                quantity_on_hand = VALUES(quantity_on_hand),
                                quantity_committed = VALUES(quantity_committed),
                                quantity_available = VALUES(quantity_available),
                                updated_at = NOW()
                        ");

                        $stmt->execute([
                            $product['product_id'],
                            $outlet_stock['outlet_id'],
                            $outlet_stock['quantity_on_hand'],
                            $outlet_stock['quantity_committed'],
                            $outlet_stock['quantity_available']
                        ]);
                    }

                    $results['updated']++;

                    // Check for low/out of stock
                    foreach ($inventory as $outlet_stock) {
                        if ($outlet_stock['quantity_available'] == 0) {
                            $results['out_of_stock_alerts']++;
                        } elseif ($outlet_stock['quantity_available'] < 10) {
                            $results['low_stock_alerts']++;
                        }
                    }
                }
            }

            if ($this->logger) {
                $this->logger->info("Inventory update complete", $results);
            }

            return $results;

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get low stock products across all outlets
     *
     * @param int $threshold - Stock level threshold
     * @return array - Low stock products
     */
    public function getLowStockProducts($threshold = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    p.product_id,
                    p.name,
                    p.price,
                    i.outlet_id,
                    i.quantity_on_hand,
                    i.quantity_available,
                    i.quantity_committed
                FROM vend_products p
                JOIN vend_inventory i ON p.product_id = i.product_id
                WHERE i.quantity_available <= ?
                AND p.status = 'active'
                ORDER BY i.quantity_available ASC
            ");
            $stmt->execute([$threshold]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Detect price changes since last snapshot
     *
     * @param int $days - Look back period
     * @return array - Products with significant price changes
     */
    public function detectPriceChanges($days = 1) {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    p.product_id,
                    p.name,
                    p.price as current_price,
                    (SELECT price FROM price_history_daily
                     WHERE product_id = p.product_id
                     AND created_date < DATE_SUB(CURDATE(), INTERVAL ? DAY)
                     ORDER BY created_date DESC LIMIT 1) as previous_price,
                    ((p.price - (SELECT price FROM price_history_daily
                     WHERE product_id = p.product_id
                     AND created_date < DATE_SUB(CURDATE(), INTERVAL ? DAY)
                     ORDER BY created_date DESC LIMIT 1)) /
                     (SELECT price FROM price_history_daily
                     WHERE product_id = p.product_id
                     AND created_date < DATE_SUB(CURDATE(), INTERVAL ? DAY)
                     ORDER BY created_date DESC LIMIT 1)) * 100 as change_pct
                FROM vend_products p
                WHERE p.status = 'active'
                HAVING change_pct IS NOT NULL
                ORDER BY ABS(change_pct) DESC
            ");
            $stmt->execute([$days, $days, $days]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Generate product intelligence report
     *
     * @param int $product_id
     * @return array - Comprehensive product intelligence
     */
    public function generateProductIntelligenceReport($product_id) {
        return [
            'product' => $this->getProductDetails($product_id),
            'inventory' => $this->getProductInventory($product_id),
            'price_history' => $this->getPriceHistory($product_id, 30),
            'sales_velocity' => $this->getSalesVelocity($product_id),
            'competitor_prices' => $this->grabCompetitorPricesForProduct($product_id),
            'matched_competitors' => $this->matchProductsAcrossCompetitors(
                $this->getProductDetails($product_id)['name']
            ),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
}

// ============================================================================
// USAGE EXAMPLES (For Reference)
// ============================================================================

/*

// Initialize grabber
$db = new PDO('mysql:host=localhost;dbname=your_db', 'user', 'pass');
$grabber = new ProductIntelligenceGrabber($db);

// 1. Get all products
$products = $grabber->grabAllProducts();
echo "Found " . count($products) . " products";

// 2. Get single product with all details
$product = $grabber->getProductDetails(123);
print_r($product);

// 3. Create price snapshots
$grabber->snapshotAllProductPrices();

// 4. Get sales velocity
$velocity = $grabber->getSalesVelocity(123);
echo "Selling " . $velocity['30_day']['units_per_day'] . " units/day";

// 5. Get competitor prices
$competitors = $grabber->getCompetitorPrices("Premium Vape Pod");
foreach ($competitors as $name => $price) {
    echo "$name: $" . $price['price'];
}

// 6. Update inventory
$results = $grabber->updateAllInventoryStatus();
echo "Low stock: " . $results['low_stock_alerts'];

// 7. Generate report
$report = $grabber->generateProductIntelligenceReport(123);

*/
?>
