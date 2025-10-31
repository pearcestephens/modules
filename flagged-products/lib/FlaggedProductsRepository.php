<?php
/**
 * Flagged Products Repository
 * 
 * Database access layer using prepared statements
 * All SQL injection vulnerabilities eliminated
 * 
 * @package CIS\FlaggedProducts
 * @version 2.0.0
 */

declare(strict_types=1);

class FlaggedProductsRepository {
    private mysqli $con;
    
    public function __construct(mysqli $con) {
        $this->con = $con;
    }
    
    /**
     * Get pending flagged products for an outlet
     */
    public function getPendingForOutlet(string $outletID): array {
        $sql = "SELECT fp.id, fp.product_id, fp.outlet, fp.reason, fp.date_flagged, 
                       fp.qty_before, fp.dummy_product, vp.name as product_name, 
                       vp.handle, vp.image_url, vi.inventory_level
                FROM flagged_products fp
                INNER JOIN vend_products vp ON vp.id = fp.product_id
                INNER JOIN vend_inventory vi ON vi.product_id = vp.id AND vi.outlet_id = fp.outlet
                WHERE fp.outlet = ? 
                  AND fp.date_completed_stocktake IS NULL
                  AND fp.completed_by_staff IS NULL
                ORDER BY fp.date_flagged DESC, vp.name ASC";
        
        return sql_query_collection_safe($sql, [$outletID]);
    }
    
    /**
     * Get completed flagged products (last 30 days)
     */
    public function getCompletedLast30Days(string $outletID): array {
        $sql = "SELECT fp.id, fp.product_id, fp.date_completed_stocktake, 
                       fp.completed_by_staff, fp.qty_before, fp.qty_after,
                       u.first_name, u.last_name, vp.name as product_name
                FROM flagged_products fp
                INNER JOIN users u ON u.id = fp.completed_by_staff
                INNER JOIN vend_products vp ON vp.id = fp.product_id
                WHERE fp.outlet = ? 
                  AND fp.reason = 'Daily Product Stocktake'
                  AND fp.date_completed_stocktake IS NOT NULL
                  AND fp.date_completed_stocktake BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND NOW()
                ORDER BY fp.date_completed_stocktake DESC";
        
        return sql_query_collection_safe($sql, [$outletID]);
    }
    
    /**
     * Get inaccurate products (qty_before != qty_after)
     */
    public function getInaccurateProducts(string $outletID, int $limit = 100): array {
        $sql = "SELECT vp.name as product_name, fp.product_id, fp.date_completed_stocktake,
                       fp.qty_before, fp.qty_after
                FROM flagged_products fp
                INNER JOIN vend_products vp ON fp.product_id = vp.id
                WHERE fp.reason = 'Daily Product Stocktake'
                  AND fp.qty_before != fp.qty_after
                  AND fp.outlet = ?
                ORDER BY fp.date_completed_stocktake DESC
                LIMIT ?";
        
        return sql_query_collection_safe($sql, [$outletID, $limit]);
    }
    
    /**
     * Get most commonly inaccurate products (last 6 months)
     */
    public function getCommonlyInaccurateProducts(string $outletID, int $limit = 100): array {
        $sql = "SELECT vp.name as product_name, fp.product_id, 
                       COUNT(fp.id) as times_wrong,
                       AVG(ABS(fp.qty_before - fp.qty_after)) as avg_difference
                FROM flagged_products fp
                INNER JOIN vend_products vp ON fp.product_id = vp.id
                WHERE fp.reason = 'Daily Product Stocktake'
                  AND fp.date_flagged > DATE_SUB(NOW(), INTERVAL 6 MONTH)
                  AND fp.qty_before != fp.qty_after
                  AND fp.outlet = ?
                GROUP BY fp.product_id, vp.name
                ORDER BY times_wrong DESC, avg_difference DESC
                LIMIT ?";
        
        return sql_query_collection_safe($sql, [$outletID, $limit]);
    }
    
    /**
     * Create a flagged product
     */
    public function create(string $productID, string $outletID, string $reason, int $qtyBefore, int $isDummy = 0): ?int {
        // Check if already exists
        if ($this->exists($productID, $outletID, $reason)) {
            return null; // Already flagged
        }
        
        $sql = "INSERT INTO flagged_products 
                (product_id, outlet, reason, qty_before, dummy_product, date_flagged)
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        return sql_query_update_or_insert_safe($sql, [$productID, $outletID, $reason, $qtyBefore, $isDummy]);
    }
    
    /**
     * Mark flagged product as complete
     */
    public function markComplete(string $productID, string $outletID, int $staffID, int $qtyAfter, ?int $qtyBefore = null): bool {
        if ($qtyBefore !== null) {
            $sql = "UPDATE flagged_products 
                    SET date_completed_stocktake = NOW(),
                        qty_before = ?,
                        qty_after = ?,
                        completed_by_staff = ?
                    WHERE product_id = ? 
                      AND outlet = ?
                      AND date_completed_stocktake IS NULL
                    LIMIT 1";
            $params = [$qtyBefore, $qtyAfter, $staffID, $productID, $outletID];
        } else {
            $sql = "UPDATE flagged_products 
                    SET date_completed_stocktake = NOW(),
                        qty_after = ?,
                        completed_by_staff = ?
                    WHERE product_id = ? 
                      AND outlet = ?
                      AND date_completed_stocktake IS NULL
                    LIMIT 1";
            $params = [$qtyAfter, $staffID, $productID, $outletID];
        }
        
        $result = sql_query_update_or_insert_safe($sql, $params);
        return $result > 0;
    }
    
    /**
     * Delete pending flagged products for outlet
     */
    public function deletePending(?string $outletID = null): int {
        if ($outletID === null) {
            $sql = "DELETE FROM flagged_products WHERE date_completed_stocktake IS NULL";
            return sql_query_update_or_insert_safe($sql);
        } else {
            $sql = "DELETE FROM flagged_products WHERE date_completed_stocktake IS NULL AND outlet = ?";
            return sql_query_update_or_insert_safe($sql, [$outletID]);
        }
    }
    
    /**
     * Check if product is already flagged
     */
    public function exists(string $productID, string $outletID, ?string $reason = null): bool {
        if ($reason !== null) {
            $sql = "SELECT id FROM flagged_products 
                    WHERE product_id = ? AND outlet = ? AND reason = ? 
                      AND completed_by_staff IS NULL";
            $params = [$productID, $outletID, $reason];
        } else {
            $sql = "SELECT id FROM flagged_products 
                    WHERE product_id = ? AND outlet = ? 
                      AND completed_by_staff IS NULL";
            $params = [$productID, $outletID];
        }
        
        $result = sql_query_collection_safe($sql, $params);
        return count($result) > 0;
    }
    
    /**
     * Get count of pending flagged products
     */
    public function getPendingCount(string $outletID): int {
        $sql = "SELECT COUNT(*) as count 
                FROM flagged_products fp
                INNER JOIN vend_products vp ON vp.id = fp.product_id
                INNER JOIN vend_inventory vi ON vi.product_id = vp.id AND vi.outlet_id = fp.outlet
                WHERE fp.date_completed_stocktake IS NULL 
                  AND fp.outlet = ?";
        
        $result = sql_query_single_row_safe($sql, [$outletID]);
        return $result ? (int)$result->count : 0;
    }
    
    /**
     * Calculate stock accuracy for outlet (last 30 days)
     */
    public function calculateAccuracy(string $outletID, int $days = 30): ?float {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN qty_before = qty_after THEN 1 ELSE 0 END) as accurate
                FROM flagged_products
                WHERE outlet = ?
                  AND reason = 'Daily Product Stocktake'
                  AND qty_after IS NOT NULL
                  AND date_flagged > '2021-03-16'
                  AND date_flagged >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
        
        $result = sql_query_single_row_safe($sql, [$outletID, $days]);
        
        if ($result && $result->total > 0) {
            return round(($result->accurate / $result->total) * 100, 2);
        }
        
        return null;
    }
    
    /**
     * Get accuracy history from stock_accuracy_history table
     */
    public function getStoredAccuracy(string $outletID, bool $previousMonth = false): ?float {
        if ($previousMonth) {
            $sql = "SELECT percentage 
                    FROM stock_accuracy_history 
                    WHERE DATE_FORMAT(date_created, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m')
                      AND outlet_id = ?
                    ORDER BY id DESC 
                    LIMIT 1";
        } else {
            $sql = "SELECT percentage 
                    FROM stock_accuracy_history 
                    WHERE outlet_id = ?
                    ORDER BY id DESC 
                    LIMIT 1";
        }
        
        $result = sql_query_single_row_safe($sql, [$outletID]);
        return $result ? (float)$result->percentage : null;
    }
    
    /**
     * Check if product is marked as dummy
     */
    public function isDummyProduct(string $productID, string $outletID): bool {
        $sql = "SELECT id FROM flagged_products 
                WHERE product_id = ? AND outlet = ? AND dummy_product = 1";
        
        $result = sql_query_collection_safe($sql, [$productID, $outletID]);
        return count($result) > 0;
    }
    
    /**
     * Get all outlets with pending flags
     */
    public function getOutletsWithPendingFlags(): array {
        $sql = "SELECT DISTINCT outlet, COUNT(*) as pending_count
                FROM flagged_products
                WHERE date_completed_stocktake IS NULL
                GROUP BY outlet
                ORDER BY pending_count DESC";
        
        return sql_query_collection_safe($sql);
    }
}

?>
