<?php
/**
 * Flagged Products Service
 * 
 * Business logic layer for flagged products management
 * 
 * @package CIS\FlaggedProducts
 * @version 2.0.0
 */

declare(strict_types=1);

class FlaggedProductsService {
    private FlaggedProductsRepository $repo;
    
    public function __construct(FlaggedProductsRepository $repo) {
        $this->repo = $repo;
    }
    
    /**
     * Get dashboard data for an outlet
     */
    public function getDashboardData(string $outletID): array {
        return [
            'pending' => $this->repo->getPendingForOutlet($outletID),
            'pending_count' => $this->repo->getPendingCount($outletID),
            'recent_completed' => $this->repo->getCompletedLast30Days($outletID),
            'inaccurate' => $this->repo->getInaccurateProducts($outletID, 50),
            'commonly_inaccurate' => $this->repo->getCommonlyInaccurateProducts($outletID, 20),
            'accuracy_30d' => $this->repo->calculateAccuracy($outletID, 30),
            'accuracy_7d' => $this->repo->calculateAccuracy($outletID, 7),
            'accuracy_stored' => $this->repo->getStoredAccuracy($outletID, false),
        ];
    }
    
    /**
     * Flag a product for checking
     */
    public function flagProduct(string $productID, string $outletID, string $reason, int $qtyBefore, int $isDummy = 0): array {
        // Validate inputs
        if (empty($productID) || empty($outletID) || empty($reason)) {
            return ['success' => false, 'error' => 'Missing required fields'];
        }
        
        // Check if already exists
        if ($this->repo->exists($productID, $outletID, $reason)) {
            return ['success' => false, 'error' => 'Product already flagged'];
        }
        
        // Create the flag
        $id = $this->repo->create($productID, $outletID, $reason, $qtyBefore, $isDummy);
        
        if ($id) {
            logFlaggedProductsAction('flag_created', [
                'product_id' => $productID,
                'outlet' => $outletID,
                'reason' => $reason
            ]);
            
            return [
                'success' => true,
                'id' => $id,
                'message' => 'Product flagged successfully'
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to create flag'];
    }
    
    /**
     * Complete a flagged product check
     */
    public function completeFlag(string $productID, string $outletID, int $staffID, int $qtyAfter, ?int $qtyBefore = null): array {
        // Validate inputs
        if (empty($productID) || empty($outletID) || $staffID <= 0) {
            return ['success' => false, 'error' => 'Invalid parameters'];
        }
        
        $success = $this->repo->markComplete($productID, $outletID, $staffID, $qtyAfter, $qtyBefore);
        
        if ($success) {
            $wasAccurate = ($qtyBefore === null || $qtyBefore === $qtyAfter);
            
            logFlaggedProductsAction('flag_completed', [
                'product_id' => $productID,
                'outlet' => $outletID,
                'accurate' => $wasAccurate,
                'qty_after' => $qtyAfter
            ]);
            
            return [
                'success' => true,
                'message' => 'Flag completed successfully',
                'was_accurate' => $wasAccurate
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to complete flag'];
    }
    
    /**
     * Bulk complete multiple flags
     */
    public function bulkComplete(array $flags, int $staffID): array {
        $completed = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($flags as $flag) {
            $result = $this->completeFlag(
                $flag['product_id'],
                $flag['outlet'],
                $staffID,
                $flag['qty_after'],
                $flag['qty_before'] ?? null
            );
            
            if ($result['success']) {
                $completed++;
            } else {
                $failed++;
                $errors[] = $result['error'];
            }
        }
        
        return [
            'success' => $failed === 0,
            'completed' => $completed,
            'failed' => $failed,
            'errors' => $errors
        ];
    }
    
    /**
     * Clear all pending flags for an outlet
     */
    public function clearPending(string $outletID): array {
        $count = $this->repo->deletePending($outletID);
        
        logFlaggedProductsAction('flags_cleared', [
            'outlet' => $outletID,
            'count' => $count
        ]);
        
        return [
            'success' => true,
            'deleted_count' => $count,
            'message' => "{$count} pending flags cleared"
        ];
    }
    
    /**
     * Generate dummy test product for testing
     */
    public function generateDummyTest(string $outletID): array {
        // Get a product that was previously wrong and is now at 0 stock
        $sql = "SELECT product_id, qty_after 
                FROM flagged_products 
                WHERE outlet = ? 
                  AND dummy_product = 0 
                  AND date_completed_stocktake < NOW() - INTERVAL 1 WEEK
                  AND qty_after = 0 
                  AND date_completed_stocktake >= NOW() - INTERVAL 3 MONTH
                ORDER BY RAND() 
                LIMIT 1";
        
        $result = sql_query_single_row_safe($sql, [$outletID]);
        
        if (!$result) {
            return ['success' => false, 'error' => 'No suitable products found for dummy test'];
        }
        
        $productID = $result->product_id;
        
        // Verify product is still at 0 stock
        $currentQty = $this->getProductInventory($productID, $outletID);
        
        if ($currentQty !== 0) {
            return ['success' => false, 'error' => 'Product stock changed, not suitable for test'];
        }
        
        // Create dummy flag with random quantity
        $fakeQty = mt_rand(3, 8);
        $id = $this->repo->create($productID, $outletID, 'Daily Product Stocktake', $fakeQty, 1);
        
        if ($id) {
            logFlaggedProductsAction('dummy_test_created', [
                'product_id' => $productID,
                'outlet' => $outletID,
                'fake_qty' => $fakeQty
            ]);
            
            return [
                'success' => true,
                'product_id' => $productID,
                'fake_qty' => $fakeQty,
                'actual_qty' => 0,
                'message' => 'Dummy test product created'
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to create dummy test'];
    }
    
    /**
     * Get product inventory level
     */
    private function getProductInventory(string $productID, string $outletID): int {
        $sql = "SELECT inventory_level 
                FROM vend_inventory 
                WHERE product_id = ? AND outlet_id = ?";
        
        $result = sql_query_single_row_safe($sql, [$productID, $outletID]);
        return $result ? (int)$result->inventory_level : 0;
    }
    
    /**
     * Get system-wide statistics
     */
    public function getSystemStats(): array {
        $outlets = $this->repo->getOutletsWithPendingFlags();
        
        $totalPending = array_sum(array_column($outlets, 'pending_count'));
        
        return [
            'total_pending' => $totalPending,
            'outlets_with_pending' => count($outlets),
            'outlets_breakdown' => $outlets
        ];
    }
}

?>
