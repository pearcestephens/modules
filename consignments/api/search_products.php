<?php
/**
 * Google-Level Product Search API
 * 
 * Real-time product search with stock levels for transfer system
 * Optimized for high-performance UX with proper vend database handling
 * 
 * @version 2.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Support both GET and POST for flexibility
$query = trim($_REQUEST['query'] ?? $_REQUEST['q'] ?? '');
$outlet_id = (int)($_REQUEST['outlet_id'] ?? $_REQUEST['outlet_from'] ?? 0);
$limit = min((int)($_REQUEST['limit'] ?? 50), 100);

$response = ['success' => false, 'products' => [], 'error' => null];

try {
    // Validate outlet if provided
    if ($outlet_id > 0) {
        $outlet_check = $mysqli->prepare("
            SELECT outlet_id, outlet_name 
            FROM vend_outlets 
            WHERE outlet_id = ? AND deleted_at = ''
        ");
        $outlet_check->bind_param('i', $outlet_id);
        $outlet_check->execute();
        $outlet_result = $outlet_check->get_result();
        
        if ($outlet_result->num_rows === 0) {
            throw new Exception('Invalid outlet selected');
        }
        $outlet_check->close();
    }
    
    // Build search query with proper vend database patterns
    if (strlen($query) >= 2) {
        $searchTerm = "%{$query}%";
        
        // Google-level search with relevance scoring and proper deletion handling
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
                   COALESCE(vend_inventory.inventory_level, 0) as inventory_level,
                   vend_inventory.reorder_point,
                   vend_inventory.reorder_amount,
                   vend_categories.name as category_name,
                   CASE 
                       WHEN vend_products.name LIKE ? THEN 1
                       WHEN vend_products.sku LIKE ? THEN 2  
                       WHEN vend_products.brand LIKE ? THEN 3
                       WHEN vend_suppliers.name LIKE ? THEN 4
                       ELSE 5
                   END as relevance_score
            FROM vend_products
            LEFT JOIN vend_suppliers ON vend_products.supplier_id = vend_suppliers.id 
                                     AND vend_suppliers.deleted_at IS NULL
            LEFT JOIN vend_inventory ON vend_products.id = vend_inventory.product_id 
                                    " . ($outlet_id > 0 ? "AND vend_inventory.outlet_id = ?" : "") . "
                                    AND vend_inventory.deleted_at IS NULL
            LEFT JOIN vend_categories ON vend_products.brand_id = vend_categories.categoryID 
                                      AND vend_categories.deleted_at IS NULL
            WHERE vend_products.deleted_at IS NULL 
              AND vend_products.is_deleted = 0
              AND vend_products.active = 1 
              AND vend_products.is_active = 1
              AND (vend_products.name LIKE ? 
                   OR vend_products.sku LIKE ? 
                   OR vend_products.brand LIKE ? 
                   OR vend_suppliers.name LIKE ?)
            ORDER BY relevance_score ASC, vend_products.name ASC
            LIMIT ?
        ");
        
        if ($outlet_id > 0) {
            $stmt->bind_param('sssssisssi', 
                $searchTerm, $searchTerm, $searchTerm, $searchTerm,  // relevance scoring
                $outlet_id,                                          // outlet filter
                $searchTerm, $searchTerm, $searchTerm, $searchTerm,  // search terms
                $limit
            );
        } else {
            $stmt->bind_param('sssssssi', 
                $searchTerm, $searchTerm, $searchTerm, $searchTerm,  // relevance scoring
                $searchTerm, $searchTerm, $searchTerm, $searchTerm,  // search terms
                $limit
            );
        }
        
    } else {
        // No search query - return popular/recent products
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
                   COALESCE(vend_inventory.inventory_level, 0) as inventory_level,
                   vend_inventory.reorder_point,
                   vend_inventory.reorder_amount,
                   vend_categories.name as category_name
            FROM vend_products
            LEFT JOIN vend_suppliers ON vend_products.supplier_id = vend_suppliers.id 
                                     AND vend_suppliers.deleted_at IS NULL
            LEFT JOIN vend_inventory ON vend_products.id = vend_inventory.product_id 
                                    " . ($outlet_id > 0 ? "AND vend_inventory.outlet_id = ?" : "") . "
                                    AND vend_inventory.deleted_at IS NULL
            LEFT JOIN vend_categories ON vend_products.brand_id = vend_categories.categoryID 
                                      AND vend_categories.deleted_at IS NULL
            WHERE vend_products.deleted_at IS NULL 
              AND vend_products.is_deleted = 0
              AND vend_products.active = 1 
              AND vend_products.is_active = 1
            ORDER BY vend_products.name ASC
            LIMIT ?
        ");
        
        if ($outlet_id > 0) {
            $stmt->bind_param('ii', $outlet_id, $limit);
        } else {
            $stmt->bind_param('i', $limit);
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $products = [];
    
    while ($row = $result->fetch_assoc()) {
        // Format for Google-level UX
        $row['price_including_tax'] = $row['price_including_tax'] ? (float)$row['price_including_tax'] : null;
        $row['price_excluding_tax'] = $row['price_excluding_tax'] ? (float)$row['price_excluding_tax'] : null;
        $row['supply_price'] = $row['supply_price'] ? (float)$row['supply_price'] : null;
        $row['inventory_level'] = $row['inventory_level'] !== null ? (int)$row['inventory_level'] : null;
        $row['id'] = (int)$row['id'];
        $row['supplier_id'] = $row['supplier_id'] ? (int)$row['supplier_id'] : null;
        $row['has_inventory'] = (bool)$row['has_inventory'];
        $row['active'] = (bool)$row['active'];
        
        // Add UX enhancements
        $row['stock_status'] = getStockStatus($row['inventory_level']);
        $row['stock_class'] = getStockClass($row['inventory_level']);
        
        $products[] = $row;
    }
    
    $stmt->close();
    
    // Support both old and new response formats
    $response['success'] = true;
    $response['ok'] = true;
    $response['products'] = $products;
    $response['data'] = $products; // Legacy support
    $response['count'] = count($products);
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    $response['ok'] = false;
    error_log("Product search error: " . $e->getMessage());
}

echo json_encode($response);

function getStockStatus($level) {
    if ($level === null) return 'Unknown';
    if ($level <= 0) return 'Out of Stock';
    if ($level < 5) return 'Low Stock';
    if ($level < 20) return 'Medium Stock';
    return 'In Stock';
}

function getStockClass($level) {
    if ($level === null) return 'stock-unknown';
    if ($level <= 0) return 'stock-out';
    if ($level < 5) return 'stock-low';
    if ($level < 20) return 'stock-medium';
    return 'stock-high';
}
