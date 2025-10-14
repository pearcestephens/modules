<?php
declare(strict_types=1);

namespace Modules\Consignments\controllers;

use Modules\Base\Controller\PageController;
use Transfers\Lib\Db;

/**
 * Base Transfer Controller
 * 
 * Provides common functionality for all transfer-related controllers
 * (Pack, Receive, Hub, etc.). This ensures consistency and reduces
 * code duplication across transfer operations.
 * 
 * @package Modules\Consignments\Controllers
 */
abstract class BaseTransferController extends PageController
{
    public function __construct()
    {
        parent::__construct();
        // Use the new master layout with partials
        $this->layout = dirname(__DIR__, 2) . '/base/views/layouts/master.php';
        
        // CRITICAL: Ensure database connection is active
        $this->ensureDatabaseConnection();
    }
    
    /**
     * Ensure database connection is active
     * Fallback if app.php didn't load properly
     */
    private function ensureDatabaseConnection(): void
    {
        try {
            // Test if connection works
            $mysqli = Db::mysqli();
            if (!$mysqli->ping()) {
                throw new \Exception('Database ping failed');
            }
        } catch (\Throwable $e) {
            // Connection failed - try to establish it
            global $con;
            if (!$con instanceof \mysqli) {
                // Load mysql.php if available
                $mysqlPath = $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/mysql.php';
                if (file_exists($mysqlPath)) {
                    require_once $mysqlPath;
                    if (function_exists('connectToSQL')) {
                        connectToSQL();
                    }
                }
            }
        }
    }

    /**
     * Get standard transfer data used by all transfer pages
     * 
     * @param int $transferId Transfer ID (0 for new transfer)
     * @return array Standard data structure
     */
    protected function getStandardTransferData(int $transferId): array
    {
        $transfer = null;
        $items = [];
        $count = 0;

        if ($transferId > 0) {
            $transfer = $this->loadTransfer($transferId);
            $items = $this->loadTransferItems($transferId);
        }
        
        $count = $this->countTransfers();

        return [
            'transferId' => $transferId,
            'transfer' => $transfer,
            'items' => $items,
            'transferCount' => $count,
        ];
    }

    /**
     * Load transfer with complete details including outlet names
     * 
     * @param int $id Transfer ID
     * @return array|null Transfer data with outlet info or null if not found
     */
    protected function loadTransfer(int $id): ?array
    {
        try {
            $mysqli = Db::mysqli();
            // CRITICAL: Join on vend_outlets.id (varchar PK), use vend_outlets.name for outlet names
            // CRITICAL: vend_outlets.deleted_at is '0000-00-00 00:00:00' when active
            $stmt = $mysqli->prepare("
                SELECT transfers.*, 
                       vend_outlets_from.name as outlet_from_name,
                       vend_outlets_from.physical_city as outlet_from_city,
                       vend_outlets_from.is_warehouse as outlet_from_warehouse,
                       vend_outlets_to.name as outlet_to_name,
                       vend_outlets_to.physical_city as outlet_to_city,
                       vend_outlets_to.is_warehouse as outlet_to_warehouse,
                       CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as created_by_name
                FROM transfers
                LEFT JOIN vend_outlets vend_outlets_from ON transfers.outlet_from = vend_outlets_from.id 
                                                         AND vend_outlets_from.deleted_at = '0000-00-00 00:00:00'
                LEFT JOIN vend_outlets vend_outlets_to ON transfers.outlet_to = vend_outlets_to.id 
                                                       AND vend_outlets_to.deleted_at = '0000-00-00 00:00:00'
                LEFT JOIN users ON transfers.created_by = users.id
                WHERE transfers.id = ? AND transfers.deleted_at IS NULL
            ");
            
            if (!$stmt) {
                error_log("BaseTransferController: Prepare failed - " . $mysqli->error);
                return null;
            }
            
            $stmt->bind_param('i', $id);
            if (!$stmt->execute()) {
                error_log("BaseTransferController: Execute failed - " . $stmt->error);
                return null;
            }
            
            $result = $stmt->get_result();
            $transfer = $result->fetch_assoc();
            
            if (!$transfer) {
                error_log("BaseTransferController: Transfer #$id not found or filtered out");
            }
            
            return $transfer ?: null;
        } catch (\Throwable $e) {
            error_log("BaseTransferController: Exception in loadTransfer() - " . $e->getMessage());
            return null;
        }
    }

    /**
     * Load transfer items with full product and inventory details
     * 
     * @param int $transferId Transfer ID
     * @return array Array of transfer items with product/stock info
     */
    protected function loadTransferItems(int $transferId): array
    {
        try {
            // Get transfer outlet info first for inventory lookup
            $transfer = $this->loadTransfer($transferId);
            if (!$transfer) return [];

            $mysqli = Db::mysqli();
            // CRITICAL: vend_products uses deleted_at = '0000-00-00 00:00:00' for active (NOT IS NULL!)
            // CRITICAL: transfer_items uses deleted_by IS NULL for active (NOT deleted_at!)
            $stmt = $mysqli->prepare("
                SELECT transfer_items.*, 
                       vend_products.name as product_name,
                       vend_products.sku,
                       vend_products.brand,
                       vend_products.supplier_id,
                       vend_products.price_including_tax,
                       vend_products.price_excluding_tax,
                       vend_products.supply_price,
                       vend_products.avg_weight_grams,
                       vend_products.has_inventory,
                       vend_products.active as product_active,
                       vend_suppliers.name as supplier_name,
                       stock_from.inventory_level as stock_from,
                       stock_from.reorder_point as reorder_from,
                       stock_to.inventory_level as stock_to,
                       vend_categories.name as category_name
                FROM transfer_items
                LEFT JOIN vend_products ON transfer_items.product_id = vend_products.id 
                                       AND vend_products.deleted_at = '0000-00-00 00:00:00'
                                       AND vend_products.is_active = 1
                                       AND vend_products.is_deleted = 0
                LEFT JOIN vend_suppliers ON vend_products.supplier_id = vend_suppliers.id 
                                         AND vend_suppliers.deleted_at IS NULL
                LEFT JOIN vend_inventory stock_from ON vend_products.id = stock_from.product_id 
                                                    AND stock_from.outlet_id = ? 
                                                    AND stock_from.deleted_at IS NULL
                LEFT JOIN vend_inventory stock_to ON vend_products.id = stock_to.product_id 
                                                  AND stock_to.outlet_id = ?
                                                  AND stock_to.deleted_at IS NULL
                LEFT JOIN vend_categories ON vend_products.brand_id = vend_categories.categoryID 
                                          AND vend_categories.deleted_at IS NULL
                WHERE transfer_items.transfer_id = ? AND transfer_items.deleted_by IS NULL
                ORDER BY vend_products.name ASC
            ");
            $stmt->bind_param('ssi', $transfer['outlet_from'], $transfer['outlet_to'], $transferId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $items = [];
            while ($row = $result->fetch_assoc()) {
                // Add calculated fields
                $row['qty_remaining'] = max(0, $row['qty_requested'] - $row['qty_sent_total']);
                $row['qty_pending'] = max(0, $row['qty_sent_total'] - $row['qty_received_total']);
                $row['completion_pct'] = $row['qty_requested'] > 0 
                    ? round(($row['qty_received_total'] / $row['qty_requested']) * 100, 1)
                    : 0;
                $row['is_low_stock'] = $row['stock_from'] <= $row['reorder_from'];
                $row['weight_total_grams'] = ($row['avg_weight_grams'] ?? 500) * $row['qty_requested'];
                
                $items[] = $row;
            }
            return $items;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Count total transfers in system
     * 
     * @return int Total transfer count
     */
    protected function countTransfers(): int
    {
        try {
            $mysqli = Db::mysqli();
            $result = $mysqli->query('SELECT COUNT(*) as count FROM transfers WHERE deleted_at IS NULL');
            if ($result) {
                $row = $result->fetch_assoc();
                return (int)$row['count'];
            }
            return 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Search products with stock levels for advanced product picker
     * 
     * @param string $query Search term
     * @param string $outletId Outlet UUID for stock lookup
     * @param int $limit Maximum results to return
     * @return array Products with stock information
     */
    protected function searchProducts(string $query, string $outletId, int $limit = 50): array
    {
        try {
            $mysqli = Db::mysqli();
            $searchTerm = '%' . $query . '%';
            
            // CRITICAL: vend_products uses deleted_at = '0000-00-00 00:00:00' for active (NOT IS NULL!)
            $stmt = $mysqli->prepare("
                SELECT vend_products.id,
                       vend_products.name,
                       vend_products.sku,
                       vend_products.brand,
                       vend_products.supplier_id,
                       vend_products.price_including_tax,
                       vend_products.price_excluding_tax,
                       vend_products.supply_price,
                       vend_products.avg_weight_grams,
                       vend_products.has_inventory,
                       vend_products.active,
                       vend_suppliers.name as supplier_name,
                       vend_inventory.inventory_level,
                       vend_inventory.reorder_point,
                       vend_inventory.reorder_amount,
                       vend_categories.name as category_name,
                       CASE 
                           WHEN vend_products.name LIKE ? THEN 1
                           WHEN vend_products.sku LIKE ? THEN 2  
                           WHEN vend_products.brand LIKE ? THEN 3
                           ELSE 4
                       END as relevance_score
                FROM vend_products
                LEFT JOIN vend_suppliers ON vend_products.supplier_id = vend_suppliers.id 
                                         AND vend_suppliers.deleted_at IS NULL
                LEFT JOIN vend_inventory ON vend_products.id = vend_inventory.product_id 
                                        AND vend_inventory.outlet_id = ? 
                                        AND vend_inventory.deleted_at IS NULL
                LEFT JOIN vend_categories ON vend_products.brand_id = vend_categories.categoryID 
                                          AND vend_categories.deleted_at IS NULL
                WHERE vend_products.deleted_at = '0000-00-00 00:00:00'
                  AND vend_products.is_active = 1
                  AND vend_products.is_deleted = 0
                  AND vend_products.active = 1 
                  AND (vend_products.name LIKE ? OR vend_products.sku LIKE ? OR vend_products.brand LIKE ? OR vend_suppliers.name LIKE ?)
                ORDER BY relevance_score ASC, vend_products.name ASC
                LIMIT ?
            ");
            $stmt->bind_param('sssssssi', $searchTerm, $searchTerm, $searchTerm, $outletId, 
                             $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $products = [];
            while ($row = $result->fetch_assoc()) {
                $row['is_in_stock'] = ($row['inventory_level'] ?? 0) > 0;
                $row['is_low_stock'] = ($row['inventory_level'] ?? 0) <= ($row['reorder_point'] ?? 0);
                $row['formatted_price'] = '$' . number_format($row['price_including_tax'], 2);
                $row['weight_display'] = $row['avg_weight_grams'] ? $row['avg_weight_grams'] . 'g' : '500g';
                $products[] = $row;
            }
            return $products;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Get outlet list for dropdowns
     * 
     * @return array Outlets with display names
     */
    protected function getOutlets(): array
    {
        try {
            $mysqli = Db::mysqli();
            // CRITICAL: vend_outlets.deleted_at is '0000-00-00 00:00:00' for active outlets
            $result = $mysqli->query("
                SELECT id, name, physical_city, is_warehouse, physical_address_1
                FROM vend_outlets 
                WHERE deleted_at = '0000-00-00 00:00:00'
                ORDER BY is_warehouse DESC, name ASC
            ");
            
            $outlets = [];
            while ($row = $result->fetch_assoc()) {
                $row['display_name'] = $row['name'] . 
                    ($row['physical_city'] ? ' (' . $row['physical_city'] . ')' : '') .
                    ($row['is_warehouse'] ? ' [WAREHOUSE]' : '');
                $outlets[] = $row;
            }
            return $outlets;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Create new transfer with validation
     * 
     * @param array $data Transfer creation data
     * @return array Result with success/error status
     */
    protected function createTransfer(array $data): array
    {
        try {
            $mysqli = Db::mysqli();
            $mysqli->begin_transaction();
            
            // Generate public ID
            $publicId = 'TR-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            // Insert transfer record
            $stmt = $mysqli->prepare("
                INSERT INTO transfers (
                    public_id, outlet_from, outlet_to, created_by, transfer_category,
                    creation_method, state, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, 'MANUAL', 'DRAFT', NOW(), NOW())
            ");
            $stmt->bind_param('sssis', 
                $publicId,
                $data['outlet_from'],
                $data['outlet_to'], 
                $data['created_by'] ?? 1,
                $data['transfer_category'] ?? 'STOCK'
            );
            $stmt->execute();
            
            $transferId = $mysqli->insert_id;
            
            $mysqli->commit();
            
            return [
                'success' => true,
                'transfer_id' => $transferId,
                'public_id' => $publicId
            ];
            
        } catch (\Throwable $e) {
            $mysqli->rollback();
            return [
                'success' => false,
                'error' => 'Failed to create transfer: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get transfer ID from request parameters
     * Supports both ?transfer=ID and legacy ?id=ID
     * 
     * @return int Transfer ID or 0 if not specified
     */
    protected function getTransferIdFromRequest(): int
    {
        if (isset($_GET['transfer'])) {
            return (int)$_GET['transfer'];
        }
        if (isset($_GET['id'])) {
            return (int)$_GET['id'];
        }
        return 0;
    }

    /**
     * Generate standard breadcrumbs for transfer pages
     * 
     * @param string $currentPage Current page name (Pack, Receive, Hub)
     * @param int $transferId Optional transfer ID for specific transfer
     * @return array Breadcrumb array
     */
    protected function getTransferBreadcrumbs(string $currentPage, int $transferId = 0): array
    {
        $breadcrumbs = [
            ['label' => 'Home', 'href' => '/'],
            ['label' => 'Transfers', 'href' => '/modules/consignments/transfers'],
        ];

        if ($transferId > 0) {
            $breadcrumbs[] = ['label' => $currentPage . ' #' . $transferId, 'active' => true];
        } else {
            $breadcrumbs[] = ['label' => $currentPage, 'active' => true];
        }

        return $breadcrumbs;
    }

    /**
     * Generate page title with transfer ID if applicable
     * 
     * @param string $basePage Base page name (Pack, Receive, Hub)
     * @param int $transferId Transfer ID (0 for new)
     * @return string Generated page title
     */
    protected function getTransferPageTitle(string $basePage, int $transferId = 0): string
    {
        if ($transferId > 0) {
            return $basePage . ' Transfer #' . $transferId;
        }
        return $basePage . ' Transfer';
    }

    /**
     * Get common CSS classes for transfer pages
     * 
     * @param string $pageType Page type (pack, receive, hub)
     * @return string CSS classes
     */
    protected function getTransferBodyClass(string $pageType): string
    {
        return 'app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show vs-transfer vs-transfer--' . strtolower($pageType);
    }

    /**
     * Standard error handling for transfer operations
     * 
     * @param \Throwable $e Exception that occurred
     * @param string $operation Operation that failed
     * @return array Error data for view
     */
    protected function handleTransferError(\Throwable $e, string $operation = 'transfer operation'): array
    {
        // Log error (you might have a specific logging mechanism)
        error_log("Transfer Error in {$operation}: " . $e->getMessage());

        return [
            'alerts' => [
                [
                    'type' => 'danger',
                    'message' => 'An error occurred during ' . $operation . '. Please try again or contact support.'
                ]
            ]
        ];
    }

    /**
     * Check if user has permission for transfer operations
     * 
     * @param string $operation Operation type (view, edit, create, delete)
     * @return bool Whether user has permission
     */
    protected function hasTransferPermission(string $operation): bool
    {
        // This would integrate with your existing permission system
        // For now, return true - you can implement proper checks later
        return true;
    }

    /**
     * Get user preferences for transfer interface
     * 
     * @return array User preferences
     */
    protected function getUserTransferPreferences(): array
    {
        // This could load from database, session, or user settings
        // Default preferences for now
        return [
            'enableScanner' => true,
            'enablePrinting' => true,
            'autoSave' => true,
            'confirmActions' => true,
        ];
    }
}