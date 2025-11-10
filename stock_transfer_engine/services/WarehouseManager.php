<?php
/**
 * Warehouse Manager
 *
 * Handles warehouse operations during transition from single to dual warehouse:
 * - Current: Frankton (warehouse + retail + juice manufacturing)
 * - Future: Dedicated Warehouse + Frankton (retail + juice manufacturing only)
 *
 * Key Responsibilities:
 * - Config-driven warehouse mode (single/dual/future)
 * - Intelligent stock source selection
 * - Juice manufacturing always from Frankton
 * - Transition support without downtime
 *
 * @package CIS\Services\StockTransfers
 * @version 1.0.0
 */

namespace CIS\Services\StockTransfers;

use PDO;
use Exception;

class WarehouseManager
{
    private $db;
    private $logger;
    private $config;

    // Warehouse modes
    const MODE_SINGLE = 'single';      // Current: Frankton only
    const MODE_DUAL = 'dual';          // Transition: Both warehouses active
    const MODE_DEDICATED = 'dedicated'; // Future: Dedicated warehouse only

    // Outlet types
    const TYPE_WAREHOUSE = 'warehouse';
    const TYPE_RETAIL = 'retail';
    const TYPE_JUICE_MFG = 'juice_manufacturing';
    const TYPE_HYBRID = 'hybrid';

    public function __construct(PDO $db, array $config, $logger = null)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->config = array_merge($this->getDefaultConfig(), $config);

        $this->validateConfig();
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'mode' => self::MODE_SINGLE,
            'primary_warehouse_id' => 'frankton_001',
            'dedicated_warehouse_id' => null,
            'juice_manufacturing_outlet_id' => 'frankton_001',
            'fallback_enabled' => true,
            'stock_source_priority' => [
                'warehouse',  // Try warehouse first
                'hub_store',  // Then hub stores
                'flagship',   // Then flagship stores
                'any'         // Last resort: any outlet
            ]
        ];
    }

    /**
     * Validate configuration
     */
    private function validateConfig(): void
    {
        $requiredKeys = ['mode', 'primary_warehouse_id', 'juice_manufacturing_outlet_id'];

        foreach ($requiredKeys as $key) {
            if (!isset($this->config[$key])) {
                throw new Exception("Missing required config key: {$key}");
            }
        }

        if (!in_array($this->config['mode'], [self::MODE_SINGLE, self::MODE_DUAL, self::MODE_DEDICATED])) {
            throw new Exception("Invalid warehouse mode: {$this->config['mode']}");
        }

        if ($this->config['mode'] === self::MODE_DUAL && empty($this->config['dedicated_warehouse_id'])) {
            throw new Exception("Dual mode requires dedicated_warehouse_id");
        }
    }

    /**
     * Get the optimal stock source for a transfer
     *
     * @param string $productId Product ID
     * @param int $quantity Required quantity
     * @param string|null $destinationOutletId Destination (for routing decisions)
     * @param array $options Additional options (is_juice, prefer_peer, etc.)
     * @return array Stock source details: outlet_id, available_quantity, is_warehouse, etc.
     */
    public function getStockSource(string $productId, int $quantity, ?string $destinationOutletId = null, array $options = []): array
    {
        $this->log('info', 'Getting stock source', [
            'product_id' => $productId,
            'quantity' => $quantity,
            'destination' => $destinationOutletId,
            'is_juice' => $options['is_juice'] ?? false
        ]);

        // Special handling for juice products - ALWAYS from Frankton
        if (!empty($options['is_juice'])) {
            return $this->getJuiceManufacturingSource($productId, $quantity);
        }

        // Get stock source based on current mode
        switch ($this->config['mode']) {
            case self::MODE_SINGLE:
                return $this->getSingleWarehouseSource($productId, $quantity);

            case self::MODE_DUAL:
                return $this->getDualWarehouseSource($productId, $quantity, $destinationOutletId, $options);

            case self::MODE_DEDICATED:
                return $this->getDedicatedWarehouseSource($productId, $quantity);

            default:
                throw new Exception("Unknown warehouse mode: {$this->config['mode']}");
        }
    }

    /**
     * Get juice manufacturing source (always Frankton)
     */
    private function getJuiceManufacturingSource(string $productId, int $quantity): array
    {
        $juiceOutletId = $this->config['juice_manufacturing_outlet_id'];

        try {
            $stmt = $this->db->prepare("
                SELECT
                    pi.outlet_id,
                    pi.count as available_quantity,
                    o.name as outlet_name,
                    ofz.outlet_type,
                    ofz.can_manufacture_juice
                FROM vend_product_inventory pi
                JOIN vend_outlets o ON pi.outlet_id = o.id
                LEFT JOIN outlet_freight_zones ofz ON o.id = ofz.outlet_id
                WHERE pi.product_id = ?
                AND pi.outlet_id = ?
            ");

            $stmt->execute([$productId, $juiceOutletId]);
            $source = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$source) {
                throw new Exception("Juice product not found at manufacturing outlet");
            }

            if ($source['available_quantity'] < $quantity) {
                $this->log('warning', 'Insufficient juice stock at manufacturing outlet', [
                    'product_id' => $productId,
                    'required' => $quantity,
                    'available' => $source['available_quantity']
                ]);
            }

            return [
                'outlet_id' => $source['outlet_id'],
                'outlet_name' => $source['outlet_name'],
                'available_quantity' => $source['available_quantity'],
                'is_warehouse' => false,
                'is_juice_manufacturing' => true,
                'can_fulfill' => $source['available_quantity'] >= $quantity,
                'source_type' => 'juice_manufacturing'
            ];

        } catch (Exception $e) {
            $this->log('error', 'Failed to get juice manufacturing source', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get stock source in SINGLE warehouse mode (current setup)
     */
    private function getSingleWarehouseSource(string $productId, int $quantity): array
    {
        $warehouseId = $this->config['primary_warehouse_id'];

        try {
            $stmt = $this->db->prepare("
                SELECT
                    pi.outlet_id,
                    pi.count as available_quantity,
                    o.name as outlet_name,
                    ofz.outlet_type
                FROM vend_product_inventory pi
                JOIN vend_outlets o ON pi.outlet_id = o.id
                LEFT JOIN outlet_freight_zones ofz ON o.id = ofz.outlet_id
                WHERE pi.product_id = ?
                AND pi.outlet_id = ?
            ");

            $stmt->execute([$productId, $warehouseId]);
            $source = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$source) {
                // Fallback: Try to find stock at any outlet
                if ($this->config['fallback_enabled']) {
                    $this->log('warning', 'Product not at primary warehouse, checking fallback outlets', [
                        'product_id' => $productId
                    ]);
                    return $this->getFallbackSource($productId, $quantity);
                }

                throw new Exception("Product not found at warehouse");
            }

            return [
                'outlet_id' => $source['outlet_id'],
                'outlet_name' => $source['outlet_name'],
                'available_quantity' => $source['available_quantity'],
                'is_warehouse' => true,
                'can_fulfill' => $source['available_quantity'] >= $quantity,
                'source_type' => 'primary_warehouse'
            ];

        } catch (Exception $e) {
            $this->log('error', 'Failed to get single warehouse source', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get stock source in DUAL warehouse mode (transition period)
     */
    private function getDualWarehouseSource(string $productId, int $quantity, ?string $destinationOutletId, array $options): array
    {
        try {
            // Get stock at both warehouses
            $stmt = $this->db->prepare("
                SELECT
                    pi.outlet_id,
                    pi.count as available_quantity,
                    o.name as outlet_name,
                    ofz.outlet_type,
                    ofz.is_flagship,
                    ofz.is_hub_store,
                    ofz.distance_from_warehouse_km
                FROM vend_product_inventory pi
                JOIN vend_outlets o ON pi.outlet_id = o.id
                LEFT JOIN outlet_freight_zones ofz ON o.id = ofz.outlet_id
                WHERE pi.product_id = ?
                AND pi.outlet_id IN (?, ?)
                ORDER BY pi.count DESC
            ");

            $stmt->execute([
                $productId,
                $this->config['primary_warehouse_id'],
                $this->config['dedicated_warehouse_id']
            ]);

            $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($warehouses)) {
                if ($this->config['fallback_enabled']) {
                    return $this->getFallbackSource($productId, $quantity, $destinationOutletId);
                }
                throw new Exception("Product not found at any warehouse");
            }

            // Select optimal warehouse based on:
            // 1. Has sufficient stock
            // 2. Closer to destination (if known)
            // 3. Higher stock level (avoid stockouts)

            $optimalWarehouse = $this->selectOptimalWarehouse($warehouses, $quantity, $destinationOutletId);

            return [
                'outlet_id' => $optimalWarehouse['outlet_id'],
                'outlet_name' => $optimalWarehouse['outlet_name'],
                'available_quantity' => $optimalWarehouse['available_quantity'],
                'is_warehouse' => true,
                'can_fulfill' => $optimalWarehouse['available_quantity'] >= $quantity,
                'source_type' => 'dual_warehouse',
                'alternate_warehouse' => $warehouses[1] ?? null // Fallback warehouse
            ];

        } catch (Exception $e) {
            $this->log('error', 'Failed to get dual warehouse source', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get stock source in DEDICATED warehouse mode (future state)
     */
    private function getDedicatedWarehouseSource(string $productId, int $quantity): array
    {
        $warehouseId = $this->config['dedicated_warehouse_id'];

        try {
            $stmt = $this->db->prepare("
                SELECT
                    pi.outlet_id,
                    pi.count as available_quantity,
                    o.name as outlet_name,
                    ofz.outlet_type
                FROM vend_product_inventory pi
                JOIN vend_outlets o ON pi.outlet_id = o.id
                LEFT JOIN outlet_freight_zones ofz ON o.id = ofz.outlet_id
                WHERE pi.product_id = ?
                AND pi.outlet_id = ?
            ");

            $stmt->execute([$productId, $warehouseId]);
            $source = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$source) {
                if ($this->config['fallback_enabled']) {
                    return $this->getFallbackSource($productId, $quantity);
                }
                throw new Exception("Product not found at dedicated warehouse");
            }

            return [
                'outlet_id' => $source['outlet_id'],
                'outlet_name' => $source['outlet_name'],
                'available_quantity' => $source['available_quantity'],
                'is_warehouse' => true,
                'can_fulfill' => $source['available_quantity'] >= $quantity,
                'source_type' => 'dedicated_warehouse'
            ];

        } catch (Exception $e) {
            $this->log('error', 'Failed to get dedicated warehouse source', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get fallback source when warehouse doesn't have stock
     * Checks hub stores, flagship stores, then any outlet with stock
     */
    private function getFallbackSource(string $productId, int $quantity, ?string $destinationOutletId = null): array
    {
        $this->log('info', 'Looking for fallback source', [
            'product_id' => $productId,
            'quantity' => $quantity
        ]);

        try {
            // Build priority-based query
            $sql = "
                SELECT
                    pi.outlet_id,
                    pi.count as available_quantity,
                    o.name as outlet_name,
                    ofz.outlet_type,
                    ofz.is_flagship,
                    ofz.is_hub_store,
                    CASE
                        WHEN ofz.outlet_type = 'warehouse' THEN 1
                        WHEN ofz.is_hub_store = 1 THEN 2
                        WHEN ofz.is_flagship = 1 THEN 3
                        ELSE 4
                    END as priority
                FROM vend_product_inventory pi
                JOIN vend_outlets o ON pi.outlet_id = o.id
                LEFT JOIN outlet_freight_zones ofz ON o.id = ofz.outlet_id
                WHERE pi.product_id = ?
                AND pi.count >= ?
                ORDER BY priority ASC, pi.count DESC
                LIMIT 1
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$productId, $quantity]);

            $source = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$source) {
                // Try with lower quantity requirement
                $stmt = $this->db->prepare(str_replace('pi.count >= ?', 'pi.count > 0', $sql));
                $stmt->execute([$productId]);
                $source = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$source) {
                throw new Exception("No stock available anywhere for product: {$productId}");
            }

            $this->log('info', 'Fallback source found', [
                'outlet' => $source['outlet_name'],
                'available' => $source['available_quantity']
            ]);

            return [
                'outlet_id' => $source['outlet_id'],
                'outlet_name' => $source['outlet_name'],
                'available_quantity' => $source['available_quantity'],
                'is_warehouse' => $source['outlet_type'] === self::TYPE_WAREHOUSE,
                'is_hub_store' => (bool)$source['is_hub_store'],
                'is_flagship' => (bool)$source['is_flagship'],
                'can_fulfill' => $source['available_quantity'] >= $quantity,
                'source_type' => 'fallback',
                'priority' => $source['priority']
            ];

        } catch (Exception $e) {
            $this->log('error', 'Failed to get fallback source', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Select optimal warehouse from multiple options
     */
    public function selectOptimalWarehouse(array $warehouses, int $quantity, ?string $destinationOutletId = null): array
    {
        // Filter to warehouses that can fulfill the order
        $canFulfill = array_filter($warehouses, function($w) use ($quantity) {
            return $w['available_quantity'] >= $quantity;
        });

        // If we have warehouses that can fulfill, prefer those
        $candidates = !empty($canFulfill) ? $canFulfill : $warehouses;

        // If we know destination, prefer closer warehouse
        if ($destinationOutletId && count($candidates) > 1) {
            // TODO: Calculate distance to destination and prefer closer
            // For now, just use the first one with sufficient stock
        }

        // Return warehouse with most stock (reduces stockout risk)
        usort($candidates, function($a, $b) {
            return $b['available_quantity'] <=> $a['available_quantity'];
        });

        return $candidates[0];
    }

    /**
     * Check if we're running multiple warehouses
     */
    public function checkMultipleWarehouses(): bool
    {
        return $this->config['mode'] === self::MODE_DUAL;
    }

    /**
     * Get all active warehouse outlets
     */
    public function getActiveWarehouses(): array
    {
        try {
            $warehouseIds = [$this->config['primary_warehouse_id']];

            if ($this->config['mode'] === self::MODE_DUAL && $this->config['dedicated_warehouse_id']) {
                $warehouseIds[] = $this->config['dedicated_warehouse_id'];
            } elseif ($this->config['mode'] === self::MODE_DEDICATED && $this->config['dedicated_warehouse_id']) {
                $warehouseIds = [$this->config['dedicated_warehouse_id']];
            }

            $placeholders = implode(',', array_fill(0, count($warehouseIds), '?'));

            $stmt = $this->db->prepare("
                SELECT
                    o.id,
                    o.name,
                    o.outlet_code,
                    ofz.outlet_type,
                    ofz.street_address,
                    ofz.city,
                    ofz.can_manufacture_juice
                FROM vend_outlets o
                LEFT JOIN outlet_freight_zones ofz ON o.id = ofz.outlet_id
                WHERE o.id IN ($placeholders)
                AND o.deleted_at IS NULL
            ");

            $stmt->execute($warehouseIds);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $this->log('error', 'Failed to get active warehouses', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get warehouse mode
     */
    public function getMode(): string
    {
        return $this->config['mode'];
    }

    /**
     * Get configuration value
     */
    public function getConfig(string $key)
    {
        return $this->config[$key] ?? null;
    }

    /**
     * Logger helper
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger && method_exists($this->logger, $level)) {
            $this->logger->$level("[WarehouseManager] {$message}", $context);
        }
    }
}
