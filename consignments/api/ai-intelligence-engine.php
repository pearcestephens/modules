<?php
declare(strict_types=1);

/**
 * 🧠 AI TRANSFER INTELLIGENCE ENGINE v2.0
 *
 * Machine learning-based transfer scheduling system
 * Features:
 * - Demand prediction & stock velocity forecasting
 * - Optimal transfer timing (when, not just what)
 * - Regional route optimization (consolidate nearby locations)
 * - Day-of-week intelligence (best days to ship)
 * - Cost optimization vs delivery windows
 * - Driver route suggestions with distance/time calculations
 * - Learning from historical patterns
 * - Autonomous scheduling with staff approval workflow
 *
 * @package CIS\Transfers\AI
 * @version 2.0.0
 * @created 2025-11-13
 */

class AITransferIntelligenceEngine
{
    protected $pdo;
    protected $config;

    public function __construct($pdo, $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'learning_window_days' => 90,     // Analyze 90 days of history
            'min_consolidation_items' => 2,   // At least 2 items per route
            'max_route_distance_km' => 500,   // Max 500km for same-day delivery
            'preferred_ship_days' => [1, 2, 3],  // Mon-Wed (0=Sun, 6=Sat)
            'avoid_ship_days' => [5, 6],      // Avoid Fri-Sat (delivery weekend issues)
            'driver_hourly_cost' => 25,       // $25/hour for driver
            'vehicle_cost_per_km' => 0.85,    // $0.85/km fuel + wear
        ], $config);
    }

    /**
     * ============================================================================
     * MAIN: GENERATE INTELLIGENT TRANSFER RECOMMENDATIONS
     * ============================================================================
     */
    public function generateIntelligentRecommendations()
    {
        $startTime = microtime(true);

        // Step 1: Analyze current stock state
        $stockAnalysis = $this->analyzeCurrentStock();

        // Step 2: Predict what needs to move
        $transfers = $this->predictRequiredTransfers($stockAnalysis);

        // Step 3: Learn from history - when should we ship?
        $transfers = $this->findOptimalShippingDays($transfers);

        // Step 4: Consolidate into regional routes
        $routes = $this->consolidateIntoRegionalRoutes($transfers);

        // Step 5: Optimize each route
        $routes = $this->optimizeRoutes($routes);

        // Step 6: Score by urgency & opportunity
        $routes = $this->scoreOpportunities($routes);

        // Step 7: Generate approval-ready recommendations
        $recommendations = $this->generateRecommendationPackets($routes);

        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'recommendations' => $recommendations,
            'total_routes' => count($recommendations),
            'total_potential_profit' => array_sum(array_map(fn($r) => $r['net_profit'], $recommendations)),
            'processing_time' => round(microtime(true) - $startTime, 3) . 's'
        ];
    }

    /**
     * ============================================================================
     * STEP 1: ANALYZE CURRENT STOCK
     * ============================================================================
     */
    protected function analyzeCurrentStock()
    {
        $query = "
            SELECT
                p.id,
                p.sku,
                p.name,
                p.cost_price,
                p.retail_price,
                o.id as outlet_id,
                o.outlet_name,
                o.region,
                oi.quantity,
                COALESCE(p.reorder_point, 20) as reorder_point,
                COALESCE(p.overstock_point, 100) as overstock_point

            FROM products p
            LEFT JOIN outlet_inventory oi ON oi.product_id = p.id
            LEFT JOIN outlets o ON o.id = oi.outlet_id

            WHERE p.status = 'active'
              AND oi.quantity > 0
              AND o.status = 'active'
        ";

        $stmt = $this->pdo->query($query);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * ============================================================================
     * STEP 2: PREDICT REQUIRED TRANSFERS
     * ============================================================================
     */
    protected function predictRequiredTransfers($stock)
    {
        $transfers = [];
        $grouped = [];

        // Group by product
        foreach ($stock as $row) {
            $pId = $row['id'];
            if (!isset($grouped[$pId])) {
                $grouped[$pId] = [
                    'product' => $row,
                    'outlets' => []
                ];
            }
            $grouped[$pId]['outlets'][] = $row;
        }

        // Analyze each product
        foreach ($grouped as $productId => $data) {
            $product = $data['product'];
            $outlets = $data['outlets'];

            if (count($outlets) < 2) continue;

            // Get velocity
            $velocities = $this->getSalesVelocity($productId);

            foreach ($outlets as $outlet) {
                $velocity = $velocities[$outlet['outlet_id']] ?? 1;
                $daysUntilStockout = $outlet['quantity'] / max(1, $velocity);

                // Stock-out risk
                if ($daysUntilStockout < 7) {
                    $supplier = $this->findBestSupplier($outlet['outlet_id'], $outlets);
                    if ($supplier) {
                        $transfers[] = [
                            'product_id' => $productId,
                            'sku' => $product['sku'],
                            'name' => $product['name'],
                            'from_outlet_id' => $supplier['outlet_id'],
                            'from_outlet_name' => $supplier['outlet_name'],
                            'from_region' => $supplier['region'],
                            'to_outlet_id' => $outlet['outlet_id'],
                            'to_outlet_name' => $outlet['outlet_name'],
                            'to_region' => $outlet['region'],
                            'from_qty' => $supplier['quantity'],
                            'to_qty' => $outlet['quantity'],
                            'qty' => ceil($velocity * 7),
                            'urgency' => 'high',
                            'urgency_days' => $daysUntilStockout,
                            'cost_price' => (float)$product['cost_price'],
                            'retail_price' => (float)$product['retail_price'],
                        ];
                    }
                }
                // Dead stock
                else if ($daysUntilStockout > 90 && $outlet['quantity'] > $outlet['overstock_point']) {
                    $buyer = $this->findBestBuyer($productId, $outlet['outlet_id'], $outlets);
                    if ($buyer) {
                        $transfers[] = [
                            'product_id' => $productId,
                            'sku' => $product['sku'],
                            'name' => $product['name'],
                            'from_outlet_id' => $outlet['outlet_id'],
                            'from_outlet_name' => $outlet['outlet_name'],
                            'from_region' => $outlet['region'],
                            'to_outlet_id' => $buyer['outlet_id'],
                            'to_outlet_name' => $buyer['outlet_name'],
                            'to_region' => $buyer['region'],
                            'from_qty' => $outlet['quantity'],
                            'to_qty' => $buyer['quantity'],
                            'qty' => floor($outlet['quantity'] / 3),
                            'urgency' => 'medium',
                            'urgency_days' => 30,
                            'cost_price' => (float)$product['cost_price'],
                            'retail_price' => (float)$product['retail_price'],
                        ];
                    }
                }
            }
        }

        return $transfers;
    }

    /**
     * ============================================================================
     * STEP 3: FIND OPTIMAL SHIPPING DAYS
     * ============================================================================
     * Learning: Best days to ship based on historical delivery times
     */
    protected function findOptimalShippingDays($transfers)
    {
        // Query historical delivery performance
        $query = "
            SELECT
                DAYOFWEEK(sent_at) as day_of_week,
                DAYNAME(sent_at) as day_name,
                AVG(DATEDIFF(received_at, sent_at)) as avg_days,
                COUNT(*) as count
            FROM consignments
            WHERE sent_at IS NOT NULL
              AND received_at IS NOT NULL
              AND DATE(sent_at) >= DATE_SUB(NOW(), INTERVAL ?)
            GROUP BY DAYOFWEEK(sent_at)
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$this->config['learning_window_days']]);
        $history = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Build day scores
        $dayScores = [];
        foreach ($history as $h) {
            // Lower delivery days = better score
            $score = max(0, 7 - $h['avg_days']) * ($h['count'] / 10);
            $dayScores[$h['day_of_week']] = [
                'name' => $h['day_name'],
                'avg_days' => $h['avg_days'],
                'score' => $score,
                'count' => $h['count']
            ];
        }

        // Assign optimal ship date to each transfer
        foreach ($transfers as &$t) {
            if ($t['urgency'] === 'high') {
                $t['ship_date'] = date('Y-m-d');  // Today
            } else {
                // Find best day within next 7 days
                $bestDay = null;
                $bestScore = -1;

                for ($i = 0; $i < 7; $i++) {
                    $checkDay = date('w', strtotime("+$i days"));
                    if (isset($dayScores[$checkDay]) && $dayScores[$checkDay]['score'] > $bestScore) {
                        $bestScore = $dayScores[$checkDay]['score'];
                        $bestDay = date('Y-m-d', strtotime("+$i days"));
                    }
                }

                $t['ship_date'] = $bestDay ?? date('Y-m-d', strtotime('+2 days'));
            }
        }

        return $transfers;
    }

    /**
     * ============================================================================
     * STEP 4: CONSOLIDATE INTO REGIONAL ROUTES
     * ============================================================================
     * Group by: From Region → To Region + Ship Date
     */
    protected function consolidateIntoRegionalRoutes($transfers)
    {
        $grouped = [];

        foreach ($transfers as $t) {
            $key = $t['from_region'] . '->' . $t['to_region'] . '||' . $t['ship_date'];

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'from_region' => $t['from_region'],
                    'to_region' => $t['to_region'],
                    'ship_date' => $t['ship_date'],
                    'from_outlets' => [],
                    'to_outlets' => [],
                    'items' => [],
                    'total_units' => 0,
                    'potential_profit' => 0
                ];
            }

            if (!in_array($t['from_outlet_id'], $grouped[$key]['from_outlets'])) {
                $grouped[$key]['from_outlets'][] = $t['from_outlet_id'];
            }
            if (!in_array($t['to_outlet_id'], $grouped[$key]['to_outlets'])) {
                $grouped[$key]['to_outlets'][] = $t['to_outlet_id'];
            }

            $grouped[$key]['items'][] = $t;
            $grouped[$key]['total_units'] += $t['qty'];
            $grouped[$key]['potential_profit'] += ($t['retail_price'] - $t['cost_price']) * $t['qty'];
        }

        return array_values($grouped);
    }

    /**
     * ============================================================================
     * STEP 5: OPTIMIZE ROUTES
     * ============================================================================
     * Calculate: distance, time, cost, departure time
     */
    protected function optimizeRoutes($routes)
    {
        foreach ($routes as &$route) {
            // Get outlet coordinates
            $outlets = array_merge($route['from_outlets'], $route['to_outlets']);
            $coords = $this->getOutletCoords($outlets);

            // Calculate distance (simplified)
            $totalKm = 0;
            for ($i = 0; $i < count($coords) - 1; $i++) {
                $totalKm += $this->haversineDistance($coords[$i], $coords[$i + 1]);
            }

            // Calculate costs
            $fuelCost = $totalKm * $this->config['vehicle_cost_per_km'];
            $hours = $totalKm / 100;  // 100 km/hr average
            $driverCost = $hours * $this->config['driver_hourly_cost'];
            $totalCost = $fuelCost + $driverCost;

            // Optimal departure (arrive before 5 PM)
            $departureTime = $this->calculateDeparture($totalKm, $route['ship_date']);

            $route['distance_km'] = round($totalKm, 1);
            $route['hours'] = round($hours, 1);
            $route['fuel_cost'] = round($fuelCost, 2);
            $route['driver_cost'] = round($driverCost, 2);
            $route['shipping_cost'] = $totalCost;
            $route['net_profit'] = $route['potential_profit'] - $totalCost;
            $route['cost_per_unit'] = $totalCost / max(1, $route['total_units']);
            $route['departure_time'] = $departureTime;
            $route['outlets_list'] = count($route['from_outlets']) . ' pickup, ' . count($route['to_outlets']) . ' delivery';
        }

        return $routes;
    }

    /**
     * ============================================================================
     * STEP 6: SCORE OPPORTUNITIES
     * ============================================================================
     */
    protected function scoreOpportunities($routes)
    {
        foreach ($routes as &$route) {
            // Count urgent items
            $urgentCount = count(array_filter($route['items'], fn($i) => $i['urgency'] === 'high'));
            $urgencyScore = min(1, $urgentCount / max(1, count($route['items'])));

            // Profit efficiency
            $profitPerKm = $route['distance_km'] > 0 ? $route['net_profit'] / $route['distance_km'] : 0;
            $profitScore = min(1, max(0, $profitPerKm / 30));

            // Consolidation potential
            $consolidationScore = (count($route['items']) - 1) / 10;  // More items = better consolidation

            // Overall score
            $overall = ($urgencyScore + $profitScore + $consolidationScore) / 3;

            // Priority
            if ($urgencyScore > 0.7) $priority = '🚨 URGENT';
            else if ($route['net_profit'] < 0) $priority = '⚠️  UNPROFITABLE';
            else if ($profitScore > 0.8) $priority = '💰 HIGH_ROI';
            else if ($urgencyScore > 0.4) $priority = '📦 SOON';
            else $priority = '💡 CONSIDER';

            $route['score'] = [
                'urgency' => round($urgencyScore, 2),
                'profit_efficiency' => round($profitScore, 2),
                'consolidation' => round($consolidationScore, 2),
                'overall' => round($overall, 2),
                'priority' => $priority
            ];
        }

        // Sort by overall score
        usort($routes, fn($a, $b) => $b['score']['overall'] <=> $a['score']['overall']);

        return $routes;
    }

    /**
     * ============================================================================
     * STEP 7: GENERATE RECOMMENDATION PACKETS
     * ============================================================================
     */
    protected function generateRecommendationPackets($routes)
    {
        $packets = [];

        foreach ($routes as $i => $route) {
            $packet = [
                'id' => 'REC-' . date('YmdHis') . '-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'from_region' => $route['from_region'],
                'to_region' => $route['to_region'],
                'ship_date' => $route['ship_date'],
                'departure_time' => $route['departure_time'],
                'summary' => $this->generateSummary($route),
                'items_count' => count($route['items']),
                'total_units' => $route['total_units'],
                'distance_km' => $route['distance_km'],
                'drive_hours' => $route['hours'],
                'pickup_stops' => count($route['from_outlets']),
                'delivery_stops' => count($route['to_outlets']),
                'gross_profit' => round($route['potential_profit'], 2),
                'shipping_cost' => round($route['shipping_cost'], 2),
                'net_profit' => round($route['net_profit'], 2),
                'profit_margin' => $route['potential_profit'] > 0
                    ? round(($route['net_profit'] / $route['potential_profit']) * 100, 1)
                    : 0,
                'cost_per_unit' => round($route['cost_per_unit'], 2),
                'score' => $route['score'],
                'items' => $route['items'],
                'status' => 'pending_approval'
            ];

            $packets[] = $packet;
        }

        return $packets;
    }

    /**
     * ============================================================================
     * HELPER FUNCTIONS
     * ============================================================================
     */

    protected function getSalesVelocity($productId)
    {
        $query = "
            SELECT
                outlet_id,
                COUNT(*) / 30 as velocity
            FROM lightspeed_sales
            WHERE product_id = ?
              AND transaction_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY outlet_id
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$productId]);

        $velocities = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $velocities[$row['outlet_id']] = (float)$row['velocity'];
        }
        return $velocities;
    }

    protected function findBestSupplier($targetId, $outlets)
    {
        foreach ($outlets as $outlet) {
            if ($outlet['outlet_id'] !== $targetId && $outlet['quantity'] > 10) {
                return $outlet;
            }
        }
        return null;
    }

    protected function findBestBuyer($productId, $sourceId, $outlets)
    {
        $fastest = null;
        $fastestVel = 0;
        $velocities = $this->getSalesVelocity($productId);

        foreach ($outlets as $outlet) {
            if ($outlet['outlet_id'] === $sourceId) continue;
            $vel = $velocities[$outlet['outlet_id']] ?? 0;
            if ($vel > $fastestVel) {
                $fastestVel = $vel;
                $fastest = $outlet;
            }
        }
        return $fastest;
    }

    protected function getOutletCoords($outletIds)
    {
        $placeholders = implode(',', array_fill(0, count($outletIds), '?'));
        $stmt = $this->pdo->prepare("SELECT id, outlet_name, latitude, longitude FROM outlets WHERE id IN ($placeholders) LIMIT ?");
        $stmt->execute(array_merge($outletIds, [1000]));
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    protected function haversineDistance($from, $to)
    {
        if (!$from['latitude'] || !$to['latitude']) return 100;

        $lat1 = deg2rad($from['latitude']);
        $lon1 = deg2rad($from['longitude']);
        $lat2 = deg2rad($to['latitude']);
        $lon2 = deg2rad($to['longitude']);

        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;
        $a = sin($dlat/2)**2 + cos($lat1) * cos($lat2) * sin($dlon/2)**2;
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return 6371 * $c;  // Earth radius in km
    }

    protected function calculateDeparture($km, $date)
    {
        $hours = $km / 100;
        $targetArrival = new \DateTime($date . ' 17:00:00');
        $depart = clone $targetArrival;
        $depart->modify("-" . ceil($hours) . " hours");
        return $depart->format('H:i');
    }

    protected function generateSummary($route)
    {
        $profit = $route['net_profit'];
        $icon = $profit > 50 ? '💰' : ($profit > 0 ? '✅' : '⚠️');

        return sprintf(
            "%s %s → %s | %d units | %d km | $%.0f net profit | Ship %s at %s",
            $icon,
            $route['from_region'],
            $route['to_region'],
            $route['total_units'],
            $route['distance_km'],
            $profit,
            $route['ship_date'],
            $route['departure_time']
        );
    }
}

?>
