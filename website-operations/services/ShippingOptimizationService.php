<?php
/**
 * Shipping Optimization Service
 *
 * *** THIS IS THE MONEY-SAVING ALGORITHM ***
 *
 * Intelligently routes orders to minimize shipping costs by:
 * 1. Analyzing inventory at all store locations
 * 2. Calculating shipping costs from each potential fulfillment point
 * 3. Comparing rates across NZ Post, CourierPost, Fastway
 * 4. Selecting the optimal combination of location + carrier
 * 5. Tracking savings vs. most expensive option
 *
 * BUSINESS IMPACT: Saves $XX,XXX per year in shipping costs
 *
 * @package    WebsiteOperations
 * @version    1.0.0
 * @author     Ecigdis Development Team
 * @copyright  2025 Ecigdis Limited
 */

namespace Modules\WebsiteOperations\Services;

use PDO;
use PDOException;
use Exception;

class ShippingOptimizationService
{
    private PDO $db;

    // Carrier configurations
    private array $carriers = [
        'nzpost' => [
            'name' => 'NZ Post',
            'enabled' => true,
            'api_endpoint' => 'https://api.nzpost.co.nz/shipping/v1',
            'weight_limits' => ['max' => 30000], // grams
        ],
        'courierpost' => [
            'name' => 'CourierPost',
            'enabled' => true,
            'api_endpoint' => 'https://api.courierpost.co.nz/v1',
            'weight_limits' => ['max' => 25000],
        ],
        'fastway' => [
            'name' => 'Fastway',
            'enabled' => true,
            'api_endpoint' => 'https://api.fastway.co.nz/v3',
            'weight_limits' => ['max' => 20000],
        ]
    ];

    /**
     * Initialize service
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Find optimal fulfillment location and carrier
     *
     * This is the CORE ALGORITHM that saves money on every order
     *
     * @param array $items Order items
     * @param array $destination Shipping destination address
     * @param string $strategy 'cost', 'speed', or 'balanced'
     * @return array Optimization results with cost savings
     */
    public function findOptimalFulfillment(array $items, array $destination, string $strategy = 'cost'): array
    {
        try {
            // 1. Get all stores that can fulfill this order (have inventory)
            $capableStores = $this->getCapableStores($items);

            if (empty($capableStores)) {
                throw new Exception("No stores have inventory for all items");
            }

            // 2. Calculate shipping options from each capable store
            $options = [];
            foreach ($capableStores as $store) {
                $storeOptions = $this->calculateStoreShippingOptions($store, $items, $destination);
                $options = array_merge($options, $storeOptions);
            }

            if (empty($options)) {
                throw new Exception("No shipping options available");
            }

            // 3. Sort and select best option based on strategy
            $bestOption = $this->selectBestOption($options, $strategy);

            // 4. Calculate how much money we saved
            $mostExpensive = max(array_column($options, 'total_cost'));
            $costSaved = $mostExpensive - $bestOption['total_cost'];

            // 5. Return optimization result
            return [
                'outlet_id' => $bestOption['store_id'],
                'location_name' => $bestOption['store_name'],
                'carrier' => $bestOption['carrier'],
                'service' => $bestOption['service'],
                'shipping_cost' => $bestOption['shipping_cost'],
                'total_cost' => $bestOption['total_cost'],
                'delivery_days' => $bestOption['delivery_days'],
                'cost_saved' => round($costSaved, 2),
                'alternatives' => array_slice($options, 0, 5), // Top 5 alternatives
                'strategy' => $strategy,
                'optimization_timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            error_log("Shipping optimization error: " . $e->getMessage());

            // Fallback to default store if optimization fails
            return $this->getDefaultFulfillment($items, $destination);
        }
    }

    /**
     * Get stores that have inventory for all items
     */
    private function getCapableStores(array $items): array
    {
        try {
            // Build query to find stores with ALL items in stock
            $productIds = array_column($items, 'product_id');
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));

            $stmt = $this->db->prepare("
                SELECT
                    s.id,
                    s.name,
                    s.address,
                    s.city,
                    s.postcode,
                    s.latitude,
                    s.longitude,
                    s.shipping_enabled,
                    COUNT(DISTINCT i.product_id) as available_products
                FROM store_configurations s
                INNER JOIN inventory i ON s.id = i.outlet_id
                WHERE i.product_id IN ($placeholders)
                AND i.quantity > 0
                AND s.shipping_enabled = 1
                GROUP BY s.id
                HAVING available_products = ?
            ");

            $params = array_merge($productIds, [count($productIds)]);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get capable stores error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate all shipping options from a specific store
     */
    private function calculateStoreShippingOptions(array $store, array $items, array $destination): array
    {
        $options = [];

        // Calculate package dimensions and weight
        $packageDetails = $this->calculatePackageDetails($items);

        // Get rates from each enabled carrier
        foreach ($this->carriers as $carrierCode => $carrier) {
            if (!$carrier['enabled']) continue;

            $rates = $this->getCarrierRates(
                $carrierCode,
                $store,
                $destination,
                $packageDetails
            );

            foreach ($rates as $rate) {
                $options[] = [
                    'store_id' => $store['id'],
                    'store_name' => $store['name'],
                    'store_location' => $store['city'],
                    'carrier' => $carrier['name'],
                    'carrier_code' => $carrierCode,
                    'service' => $rate['service'],
                    'shipping_cost' => $rate['cost'],
                    'total_cost' => $rate['cost'], // Can add handling fees here
                    'delivery_days' => $rate['delivery_days'],
                    'distance_km' => $this->calculateDistance($store, $destination)
                ];
            }
        }

        return $options;
    }

    /**
     * Get shipping rates from carrier API
     */
    private function getCarrierRates(string $carrierCode, array $origin, array $destination, array $package): array
    {
        // In production, this would call real carrier APIs
        // For now, using intelligent estimation based on distance + weight

        $distance = $this->calculateDistance($origin, $destination);
        $weight = $package['weight']; // grams

        // Base rates (NZD)
        $baseRates = [
            'nzpost' => [
                'standard' => ['base' => 5.50, 'per_kg' => 2.20, 'days' => 3],
                'express' => ['base' => 8.90, 'per_kg' => 3.50, 'days' => 1],
            ],
            'courierpost' => [
                'standard' => ['base' => 6.20, 'per_kg' => 2.50, 'days' => 2],
                'overnight' => ['base' => 12.50, 'per_kg' => 4.00, 'days' => 1],
            ],
            'fastway' => [
                'parcel' => ['base' => 5.90, 'per_kg' => 2.30, 'days' => 3],
                'satchel' => ['base' => 7.50, 'per_kg' => 0, 'days' => 2],
            ]
        ];

        $rates = [];
        $weightKg = $weight / 1000;

        if (isset($baseRates[$carrierCode])) {
            foreach ($baseRates[$carrierCode] as $service => $pricing) {
                $cost = $pricing['base'] + ($weightKg * $pricing['per_kg']);

                // Distance multiplier (further = more expensive)
                if ($distance > 100) {
                    $cost *= 1.2;
                } elseif ($distance > 300) {
                    $cost *= 1.5;
                } elseif ($distance > 500) {
                    $cost *= 1.8;
                }

                // Rural delivery surcharge
                if ($this->isRuralAddress($destination)) {
                    $cost += 3.50;
                }

                $rates[] = [
                    'service' => ucfirst($service),
                    'cost' => round($cost, 2),
                    'delivery_days' => $pricing['days']
                ];
            }
        }

        return $rates;
    }

    /**
     * Calculate package dimensions and weight from items
     */
    private function calculatePackageDetails(array $items): array
    {
        $totalWeight = 0;
        $totalVolume = 0;

        foreach ($items as $item) {
            // Get product dimensions from database
            $product = $this->getProductDimensions($item['product_id']);

            $itemWeight = ($product['weight'] ?? 200) * $item['quantity']; // default 200g
            $itemVolume = ($product['volume'] ?? 500) * $item['quantity']; // default 500cm³

            $totalWeight += $itemWeight;
            $totalVolume += $itemVolume;
        }

        // Estimate box dimensions (simplified)
        $dimensions = $this->estimateBoxSize($totalVolume);

        return [
            'weight' => $totalWeight, // grams
            'length' => $dimensions['length'], // cm
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'volume' => $totalVolume // cm³
        ];
    }

    /**
     * Get product dimensions from database
     */
    private function getProductDimensions(int $productId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT weight, length, width, height
                FROM web_products
                WHERE id = :id
            ");

            $stmt->execute([':id' => $productId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Estimate box size based on total volume
     */
    private function estimateBoxSize(float $volumeCm3): array
    {
        // Standard NZ Post box sizes
        $boxes = [
            ['length' => 15, 'width' => 10, 'height' => 5, 'volume' => 750],
            ['length' => 20, 'width' => 15, 'height' => 10, 'volume' => 3000],
            ['length' => 30, 'width' => 20, 'height' => 15, 'volume' => 9000],
            ['length' => 40, 'width' => 30, 'height' => 20, 'volume' => 24000],
        ];

        foreach ($boxes as $box) {
            if ($volumeCm3 <= $box['volume']) {
                return $box;
            }
        }

        // Default large box
        return $boxes[count($boxes) - 1];
    }

    /**
     * Calculate distance between two points (Haversine formula)
     */
    private function calculateDistance(array $origin, array $destination): float
    {
        // If we have lat/long coordinates, use them
        if (isset($origin['latitude']) && isset($destination['latitude'])) {
            return $this->haversineDistance(
                $origin['latitude'],
                $origin['longitude'],
                $destination['latitude'],
                $destination['longitude']
            );
        }

        // Otherwise estimate from postcodes
        return $this->estimateDistanceFromPostcode(
            $origin['postcode'] ?? '',
            $destination['postcode'] ?? ''
        );
    }

    /**
     * Haversine formula for distance calculation
     */
    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earthRadius * $c;

        return round($distance, 2);
    }

    /**
     * Estimate distance from postcodes (simplified)
     */
    private function estimateDistanceFromPostcode(string $originPostcode, string $destPostcode): float
    {
        // Major NZ city postcodes (simplified mapping)
        $cityMap = [
            '0' => ['name' => 'Auckland', 'lat' => -36.8485, 'lon' => 174.7633],
            '1' => ['name' => 'Auckland', 'lat' => -36.8485, 'lon' => 174.7633],
            '2' => ['name' => 'Auckland', 'lat' => -36.8485, 'lon' => 174.7633],
            '3' => ['name' => 'Hamilton', 'lat' => -37.7870, 'lon' => 175.2793],
            '4' => ['name' => 'Tauranga', 'lat' => -37.6878, 'lon' => 176.1651],
            '5' => ['name' => 'Wellington', 'lat' => -41.2865, 'lon' => 174.7762],
            '6' => ['name' => 'Wellington', 'lat' => -41.2865, 'lon' => 174.7762],
            '7' => ['name' => 'Nelson', 'lat' => -41.2706, 'lon' => 173.2840],
            '8' => ['name' => 'Christchurch', 'lat' => -43.5320, 'lon' => 172.6306],
            '9' => ['name' => 'Dunedin', 'lat' => -45.8788, 'lon' => 170.5028],
        ];

        $originRegion = substr($originPostcode, 0, 1);
        $destRegion = substr($destPostcode, 0, 1);

        if (isset($cityMap[$originRegion]) && isset($cityMap[$destRegion])) {
            return $this->haversineDistance(
                $cityMap[$originRegion]['lat'],
                $cityMap[$originRegion]['lon'],
                $cityMap[$destRegion]['lat'],
                $cityMap[$destRegion]['lon']
            );
        }

        return 100; // Default estimate
    }

    /**
     * Check if address is rural (simplified)
     */
    private function isRuralAddress(array $address): bool
    {
        $ruralKeywords = ['RD', 'Rural', 'Highway', 'SH'];
        $addressStr = $address['address'] ?? '';

        foreach ($ruralKeywords as $keyword) {
            if (stripos($addressStr, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Select best option based on strategy
     */
    private function selectBestOption(array $options, string $strategy): array
    {
        switch ($strategy) {
            case 'cost':
                // Cheapest option
                usort($options, fn($a, $b) => $a['total_cost'] <=> $b['total_cost']);
                break;

            case 'speed':
                // Fastest delivery
                usort($options, fn($a, $b) => $a['delivery_days'] <=> $b['delivery_days']);
                break;

            case 'balanced':
                // Balance cost and speed (weighted score)
                usort($options, function($a, $b) {
                    $scoreA = ($a['total_cost'] * 0.6) + ($a['delivery_days'] * 2 * 0.4);
                    $scoreB = ($b['total_cost'] * 0.6) + ($b['delivery_days'] * 2 * 0.4);
                    return $scoreA <=> $scoreB;
                });
                break;
        }

        return $options[0];
    }

    /**
     * Get default fulfillment if optimization fails
     */
    private function getDefaultFulfillment(array $items, array $destination): array
    {
        try {
            // Use primary warehouse/store
            $stmt = $this->db->query("
                SELECT id, name, city, postcode
                FROM store_configurations
                WHERE is_primary = 1
                LIMIT 1
            ");

            $store = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'outlet_id' => $store['id'],
                'location_name' => $store['name'],
                'carrier' => 'NZ Post',
                'service' => 'Standard',
                'shipping_cost' => 8.50, // Default rate
                'total_cost' => 8.50,
                'delivery_days' => 3,
                'cost_saved' => 0,
                'alternatives' => [],
                'strategy' => 'default_fallback',
                'optimization_timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            error_log("Default fulfillment error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get shipping savings report
     */
    public function getShippingSavingsReport(int $days = 30): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as total_orders,
                    SUM(shipping_cost_saved) as total_saved,
                    AVG(shipping_cost_saved) as avg_saved_per_order,
                    SUM(shipping_cost) as total_shipping_cost,
                    carrier,
                    COUNT(*) as orders_per_carrier
                FROM web_orders
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY carrier
            ");

            $stmt->execute([':days' => $days]);
            $carrierBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalSaved = array_sum(array_column($carrierBreakdown, 'total_saved'));
            $totalOrders = array_sum(array_column($carrierBreakdown, 'orders_per_carrier'));

            return [
                'period_days' => $days,
                'total_orders' => $totalOrders,
                'total_saved' => round($totalSaved, 2),
                'average_saved_per_order' => $totalOrders > 0 ? round($totalSaved / $totalOrders, 2) : 0,
                'projected_annual_savings' => round($totalSaved / $days * 365, 2),
                'carrier_breakdown' => $carrierBreakdown,
                'generated_at' => date('Y-m-d H:i:s')
            ];
        } catch (PDOException $e) {
            error_log("Shipping savings report error: " . $e->getMessage());
            return [];
        }
    }
}
