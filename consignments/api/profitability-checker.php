<?php
declare(strict_types=1);

/**
 * 💰 PROFITABILITY CHECKER API
 *
 * Real-time profitability validation for transfers
 * Prevents staff from creating unprofitable internal transfers
 * Suggests consolidations to improve margins
 *
 * @package CIS\Transfers\Profitability
 * @version 1.0.0
 * @created 2025-11-13
 */

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';
$pdo = cis_resolve_pdo();

$checker = new ProfitabilityChecker($pdo);

try {
    switch ($action) {

        /**
         * Check if a proposed transfer is profitable
         * POST: product_id, from_outlet, to_outlet, quantity
         */
        case 'checkTransfer':
            $result = $checker->checkTransferProfitability(
                (int)$_POST['product_id'],
                (int)$_POST['from_outlet'],
                (int)$_POST['to_outlet'],
                (int)$_POST['quantity']
            );
            echo json_encode($result);
            break;

        /**
         * Get consolidation suggestions for unprofitable transfer
         * POST: product_id, transfer_qty, from_outlet, to_outlet
         */
        case 'suggestConsolidations':
            $result = $checker->suggestConsolidations(
                (int)$_POST['product_id'],
                (int)$_POST['transfer_qty'],
                (int)$_POST['from_outlet'],
                (int)$_POST['to_outlet']
            );
            echo json_encode($result);
            break;

        /**
         * Get profit margin analysis for a product
         * GET: product_id
         */
        case 'getMarginAnalysis':
            $result = $checker->getMarginAnalysis((int)$_GET['product_id']);
            echo json_encode($result);
            break;

        /**
         * Suggest complementary items to add to transfer
         * POST: route (from_outlet:to_outlet), exclude_products
         */
        case 'suggestAdditionalItems':
            $result = $checker->suggestAdditionalItems(
                $_POST['route'],
                explode(',', $_POST['exclude_products'] ?? '')
            );
            echo json_encode($result);
            break;

        /**
         * Get threshold values for your system
         * GET
         */
        case 'getThresholds':
            $result = $checker->getThresholds();
            echo json_encode($result);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action: ' . $action]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// ============================================================================

class ProfitabilityChecker
{
    protected $pdo;
    protected $config = [
        'min_margin' => 0.15,                    // 15% minimum margin
        'shipping_overhead' => 1.2,              // 20% overhead
        'min_profit_per_transfer' => 10,         // Must make at least $10
        'consolidation_item_limit' => 5,         // Max 5 complementary items
    ];

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Check if a transfer is profitable
     */
    public function checkTransferProfitability($productId, $fromOutlet, $toOutlet, $qty)
    {
        try {
            // Get product details
            $product = $this->getProduct($productId);
            if (!$product) {
                return ['profitable' => false, 'reason' => 'Product not found'];
            }

            // Calculate shipping cost
            $shippingCost = $this->estimateShippingCost($fromOutlet, $toOutlet, $qty);

            // Calculate profit
            $costOfGoods = $product['cost_price'] * $qty;
            $expectedRevenue = $product['retail_price'] * $qty;
            $grossProfit = $expectedRevenue - $costOfGoods;
            $totalShippingWithOverhead = $shippingCost * $this->config['shipping_overhead'];
            $netProfit = $grossProfit - $totalShippingWithOverhead;

            // Calculate margin
            $profitMargin = $grossProfit > 0 ? $netProfit / $grossProfit : 0;

            // Decision
            $isProfitable = $netProfit >= $this->config['min_profit_per_transfer'] &&
                           $profitMargin >= $this->config['min_margin'];

            $reason = '';
            if ($netProfit < $this->config['min_profit_per_transfer']) {
                $reason = "Profit too low ($" . round($netProfit, 2) .
                         " < $" . $this->config['min_profit_per_transfer'] . ")";
            } elseif ($profitMargin < $this->config['min_margin']) {
                $reason = "Margin too low (" . round($profitMargin * 100, 1) .
                         "% < " . round($this->config['min_margin'] * 100, 1) . "%)";
            }

            return [
                'profitable' => $isProfitable,
                'product_id' => $productId,
                'product_name' => $product['name'],
                'quantity' => $qty,
                'cost_price' => (float)$product['cost_price'],
                'retail_price' => (float)$product['retail_price'],
                'shipping_cost' => (float)$shippingCost,
                'cost_of_goods' => (float)$costOfGoods,
                'expected_revenue' => (float)$expectedRevenue,
                'gross_profit' => (float)$grossProfit,
                'net_profit' => (float)$netProfit,
                'profit_margin' => (float)$profitMargin,
                'reason' => $reason
            ];

        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Suggest items to add to make transfer profitable
     */
    public function suggestConsolidations($productId, $transferQty, $fromOutlet, $toOutlet)
    {
        $suggestions = [];

        try {
            // First, check the original transfer
            $original = $this->checkTransferProfitability($productId, $fromOutlet, $toOutlet, $transferQty);

            if ($original['profitable']) {
                return [
                    'message' => 'Transfer is already profitable',
                    'original' => $original,
                    'suggestions' => []
                ];
            }

            // Find complementary items going same route
            $stmt = $this->pdo->prepare("
                SELECT
                    p.id,
                    p.sku,
                    p.name,
                    p.cost_price,
                    p.retail_price,
                    oi.quantity as from_qty,
                    (SELECT quantity FROM outlet_inventory
                     WHERE product_id = p.id AND outlet_id = ?) as to_qty
                FROM products p
                JOIN outlet_inventory oi ON oi.product_id = p.id
                WHERE oi.outlet_id = ?
                  AND p.id != ?
                  AND p.status = 'active'
                  AND oi.quantity > 0
                ORDER BY (p.retail_price - p.cost_price) DESC
                LIMIT ?
            ");

            $stmt->execute([$toOutlet, $fromOutlet, $productId, $this->config['consolidation_item_limit']]);
            $candidates = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Test each candidate
            foreach ($candidates as $candidate) {
                // Try adding 1-5 units
                for ($qty = 1; $qty <= 5; $qty++) {
                    if ($qty > $candidate['from_qty']) break;

                    $combined = [
                        'original' => $original,
                        'additional_items' => [
                            [
                                'product_id' => $candidate['id'],
                                'quantity' => $qty
                            ]
                        ]
                    ];

                    // Calculate combined profitability
                    $shippingOriginal = $this->estimateShippingCost($fromOutlet, $toOutlet, $transferQty);
                    $shippingWithExtra = $this->estimateShippingCost(
                        $fromOutlet,
                        $toOutlet,
                        $transferQty + $qty
                    );

                    // Extra shipping is just the incremental cost
                    $extraShipping = $shippingWithExtra - $shippingOriginal;

                    $additionalProfit = ($candidate['retail_price'] - $candidate['cost_price']) * $qty -
                                       ($extraShipping * $this->config['shipping_overhead']);

                    $newTotalProfit = $original['net_profit'] + $additionalProfit;

                    if ($newTotalProfit >= $this->config['min_profit_per_transfer']) {
                        $suggestions[] = [
                            'product_id' => $candidate['id'],
                            'product_name' => $candidate['name'],
                            'sku' => $candidate['sku'],
                            'quantity' => $qty,
                            'additional_profit' => (float)$additionalProfit,
                            'new_total_profit' => (float)$newTotalProfit,
                            'makes_profitable' => true
                        ];

                        // Stop after finding one solution
                        break 2;
                    }
                }
            }

            return [
                'original_transfer' => $original,
                'status' => empty($suggestions) ? 'cannot_consolidate' : 'consolidation_found',
                'suggestions' => $suggestions
            ];

        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get margin analysis for a product across all outlets
     */
    public function getMarginAnalysis($productId)
    {
        try {
            $product = $this->getProduct($productId);
            if (!$product) {
                return ['error' => 'Product not found'];
            }

            // Get inventory and sales velocity
            $stmt = $this->pdo->prepare("
                SELECT
                    o.id,
                    o.outlet_name,
                    oi.quantity,
                    (SELECT COUNT(*) FROM lightspeed_sales
                     WHERE product_id = ? AND outlet_id = o.id
                     AND transaction_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as sales_30d
                FROM outlets o
                LEFT JOIN outlet_inventory oi ON oi.product_id = ? AND oi.outlet_id = o.id
                WHERE o.status = 'active'
                ORDER BY oi.quantity DESC
            ");

            $stmt->execute([$productId, $productId]);
            $outlets = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return [
                'product_id' => $productId,
                'sku' => $product['sku'],
                'name' => $product['name'],
                'cost_price' => (float)$product['cost_price'],
                'retail_price' => (float)$product['retail_price'],
                'gross_margin' => (float)(($product['retail_price'] - $product['cost_price']) / $product['retail_price']),
                'by_outlet' => $outlets
            ];

        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Suggest additional items to add to a route
     */
    public function suggestAdditionalItems($route, $excludeProducts = [])
    {
        try {
            list($fromId, $toId) = explode(':', $route);

            $stmt = $this->pdo->prepare("
                SELECT
                    p.id,
                    p.sku,
                    p.name,
                    p.cost_price,
                    p.retail_price,
                    oi.quantity,
                    (p.retail_price - p.cost_price) as unit_profit
                FROM products p
                JOIN outlet_inventory oi ON oi.product_id = p.id
                WHERE oi.outlet_id = ?
                  AND p.status = 'active'
                  AND oi.quantity > 0
                " . (count($excludeProducts) > 0 ? "AND p.id NOT IN (" .
                    implode(',', array_fill(0, count($excludeProducts), '?')) . ")" : "") . "
                ORDER BY unit_profit DESC
                LIMIT 10
            ");

            $params = array_merge([$fromId], $excludeProducts);
            $stmt->execute($params);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return [
                'route' => $route,
                'suggestions' => $items
            ];

        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get current threshold configuration
     */
    public function getThresholds()
    {
        return $this->config;
    }

    /**
     * ============================================================================
     * HELPERS
     * ============================================================================
     */

    protected function getProduct($productId)
    {
        $stmt = $this->pdo->prepare("
            SELECT id, sku, name, cost_price, retail_price, weight_g
            FROM products WHERE id = ?
        ");
        $stmt->execute([$productId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    protected function estimateShippingCost($fromOutlet, $toOutlet, $qty)
    {
        // Get outlet regions
        $stmt = $this->pdo->prepare("
            SELECT
                (SELECT region FROM outlets WHERE id = ?) as from_region,
                (SELECT region FROM outlets WHERE id = ?) as to_region
        ");
        $stmt->execute([$fromOutlet, $toOutlet]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Cost matrix (can be made more sophisticated)
        $matrix = [
            'same_region' => 25,
            'cross_region' => 45,
            'cross_country' => 65
        ];

        $costType = ($result['from_region'] === $result['to_region'])
            ? 'same_region'
            : 'cross_country';

        $base = $matrix[$costType];
        $weightSurcharge = ($qty * 0.2 / 1000) * 5;  // $5 per kg over 2kg baseline

        return $base + max(0, $weightSurcharge);
    }
}

?>
