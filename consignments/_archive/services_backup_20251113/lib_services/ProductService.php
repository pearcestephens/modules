<?php
/**
 * ProductService - Product search and inventory operations
 *
 * Handles product-related operations for transfer management:
 * - Product search and lookup
 * - Inventory queries
 * - Product details with stock levels
 *
 * Extracted from TransferManagerAPI to follow proper MVC pattern.
 *
 * @package CIS\Consignments\Services
 * @version 1.0.0
 * @author CIS Development Team
 * @created 2025-11-05
 */

declare(strict_types=1);

namespace CIS\Consignments\Services;

use PDO;
use InvalidArgumentException;

class ProductService
{
    /**
     * Read-only database connection
     * @var PDO
     */
    private PDO $ro;

    /**
     * Constructor
     *
     * @param PDO $ro Read-only connection
     */
    public function __construct(PDO $ro)
    {
        $this->ro = $ro;
    }

    /**
     * Factory method using global database helpers
     *
     * @return self
     */
    public static function make(): self
    {
        return new self(db_ro());
    }

    // ========================================================================
    // SEARCH & LOOKUP
    // ========================================================================

    /**
     * Search products by name or SKU
     *
     * @param string $query Search term
     * @param int $limit Maximum results (1-100)
     * @param int|null $outletId Filter by outlet inventory
     * @return array Array of product records with stock info
     * @throws InvalidArgumentException If query is too short
     */
    public function search(string $query, int $limit = 30, ?int $outletId = null): array
    {
        $query = trim($query);

        if (strlen($query) < 2) {
            throw new InvalidArgumentException('Search query must be at least 2 characters');
        }

        $limit = max(1, min(100, $limit));
        $searchTerm = "%{$query}%";

        // Base query
        $sql = "SELECT
                    p.id as product_id,
                    p.name,
                    p.sku,
                    p.price_including_tax as retail_price,
                    p.supply_price,
                    p.active,
                    COALESCE(SUM(i.current_amount), 0) as total_stock";

        // Join outlet inventory if filtering by outlet
        if ($outletId !== null) {
            $sql .= ",
                    COALESCE(oi.current_amount, 0) as outlet_stock";
        }

        $sql .= "
                FROM vend_products p
                LEFT JOIN vend_inventory i ON p.id = i.product_id";

        if ($outletId !== null) {
            $sql .= "
                LEFT JOIN vend_inventory oi ON p.id = oi.product_id AND oi.outlet_id = ?";
        }

        $sql .= "
                WHERE (p.name LIKE ? OR p.sku LIKE ?)
                AND p.active = 1
                GROUP BY p.id";

        if ($outletId !== null) {
            $sql .= ", oi.current_amount";
        }

        $sql .= "
                ORDER BY p.name
                LIMIT ?";

        $stmt = $this->ro->prepare($sql);

        // Bind parameters in order they appear in SQL
        $paramIndex = 1;
        if ($outletId !== null) {
            $stmt->bindValue($paramIndex++, $outletId, PDO::PARAM_INT);
        }
        $stmt->bindValue($paramIndex++, $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue($paramIndex++, $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }    /**
     * Get product by ID with full details
     *
     * @param int $id Product ID
     * @param bool $includeInventory Include inventory levels
     * @return array|null Product record or null if not found
     */
    public function getById(int $id, bool $includeInventory = true): ?array
    {
        $sql = "SELECT
                    p.*,
                    s.name as supplier_name,
                    b.name as brand_name";

        if ($includeInventory) {
            $sql .= ",
                    COALESCE(SUM(i.current_amount), 0) as total_stock";
        }

        $sql .= "
                FROM vend_products p
                LEFT JOIN vend_suppliers s ON p.supplier_id = s.id
                LEFT JOIN vend_brands b ON p.brand_id = b.id";

        if ($includeInventory) {
            $sql .= "
                LEFT JOIN vend_inventory i ON p.id = i.product_id";
        }

        $sql .= "
                WHERE p.id = :id";

        if ($includeInventory) {
            $sql .= " GROUP BY p.id";
        }

        $sql .= " LIMIT 1";

        $stmt = $this->ro->prepare($sql);
        $stmt->execute([':id' => $id]);

        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        return $product ?: null;
    }

    /**
     * Get multiple products by IDs
     *
     * @param array<int> $ids Array of product IDs
     * @param bool $includeInventory Include inventory levels
     * @return array Array of product records indexed by product_id
     */
    public function getByIds(array $ids, bool $includeInventory = true): array
    {
        if (empty($ids)) {
            return [];
        }

        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "SELECT
                    p.id as product_id,
                    p.name,
                    p.sku,
                    p.price_including_tax as retail_price,
                    p.supply_price,
                    p.active";

        if ($includeInventory) {
            $sql .= ",
                    COALESCE(SUM(i.current_amount), 0) as total_stock";
        }

        $sql .= "
                FROM vend_products p";

        if ($includeInventory) {
            $sql .= "
                LEFT JOIN vend_inventory i ON p.id = i.product_id";
        }

        $sql .= "
                WHERE p.id IN ({$placeholders})";

        if ($includeInventory) {
            $sql .= " GROUP BY p.id";
        }

        $stmt = $this->ro->prepare($sql);
        $stmt->execute($ids);

        $products = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[$row['product_id']] = $row;
        }

        return $products;
    }

    // ========================================================================
    // INVENTORY QUERIES
    // ========================================================================

    /**
     * Get inventory levels for a product across all outlets
     *
     * @param int $productId Product ID
     * @return array Array of inventory records by outlet
     */
    public function getInventoryByOutlets(int $productId): array
    {
        $sql = "SELECT
                    i.outlet_id,
                    o.name as outlet_name,
                    i.current_amount as stock,
                    i.reorder_point,
                    i.reorder_amount as restock_level
                FROM vend_inventory i
                JOIN vend_outlets o ON i.outlet_id = o.id
                WHERE i.product_id = :product_id
                ORDER BY o.name";

        $stmt = $this->ro->prepare($sql);
        $stmt->execute([':product_id' => $productId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get stock level for a product at a specific outlet
     *
     * @param int $productId Product ID
     * @param int $outletId Outlet ID
     * @return int Stock level
     */
    public function getOutletStock(int $productId, int $outletId): int
    {
        $sql = "SELECT COALESCE(current_amount, 0) as stock
                FROM vend_inventory
                WHERE product_id = :product_id
                AND outlet_id = :outlet_id
                LIMIT 1";

        $stmt = $this->ro->prepare($sql);
        $stmt->execute([
            ':product_id' => $productId,
            ':outlet_id' => $outletId
        ]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Get low stock products at outlet
     *
     * @param int $outletId Outlet ID
     * @param int $limit Maximum results
     * @return array Array of products below reorder point
     */
    public function getLowStockAtOutlet(int $outletId, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));

        $sql = "SELECT
                    p.id as product_id,
                    p.name,
                    p.sku,
                    i.current_amount as current_stock,
                    i.reorder_point,
                    i.reorder_amount as restock_level,
                    (i.reorder_amount - i.current_amount) as qty_needed
                FROM vend_inventory i
                JOIN vend_products p ON i.product_id = p.id
                WHERE i.outlet_id = :outlet_id
                AND i.current_amount < i.reorder_point
                AND p.active = 1
                ORDER BY (i.reorder_point - i.current_amount) DESC
                LIMIT :limit";

        $stmt = $this->ro->prepare($sql);
        $stmt->bindValue(':outlet_id', $outletId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========================================================================
    // STATISTICS & REPORTING
    // ========================================================================

    /**
     * Get product movement statistics
     *
     * @param int $productId Product ID
     * @param int $days Number of days to analyze
     * @return array Statistics data
     */
    public function getMovementStats(int $productId, int $days = 30): array
    {
        $sql = "SELECT
                    COUNT(DISTINCT cp.consignment_id) as transfer_count,
                    SUM(cp.count_ordered) as total_qty_requested,
                    SUM(cp.count_received) as total_qty_received,
                    AVG(cp.count_ordered) as avg_qty_per_transfer
                FROM queue_consignment_products cp
                JOIN queue_consignments t ON cp.consignment_id = t.id
                WHERE cp.vend_product_id = :product_id
                AND t.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";

        $stmt = $this->ro->prepare($sql);
        $stmt->execute([
            ':product_id' => $productId,
            ':days' => $days
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get top transferred products
     *
     * @param int $limit Maximum results
     * @param int $days Number of days to analyze
     * @return array Array of products with transfer stats
     */
    public function getTopTransferred(int $limit = 20, int $days = 30): array
    {
        $limit = max(1, min(100, $limit));

        $sql = "SELECT
                    p.id as product_id,
                    p.name,
                    p.sku,
                    COUNT(DISTINCT cp.consignment_id) as transfer_count,
                    SUM(cp.count_ordered) as total_qty
                FROM queue_consignment_products cp
                JOIN vend_products p ON cp.vend_product_id = p.id
                JOIN queue_consignments t ON cp.consignment_id = t.id
                WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND t.status != 'CANCELLED'
                GROUP BY p.id
                ORDER BY total_qty DESC
                LIMIT :limit";

        $stmt = $this->ro->prepare($sql);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
