<?php
/**
 * Dashboard Overview Page
 * Main dashboard with CIS statistics, inventory, sales, and operations metrics
 *
 * @package CIS/AdminUI
 * @category Dashboard Page
 */

declare(strict_types=1);

// Get CIS database connection
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

// ============================================================================
// SYSTEM STATISTICS
// ============================================================================

$systemStats = [
    'total_outlets' => 0,
    'total_products' => 0,
    'total_inventory_value' => 0,
    'low_stock_items' => 0
];

try {
    // Count outlets
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM vend_outlets WHERE active = 1");
    $systemStats['total_outlets'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Count products
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM vend_products WHERE active = 1");
    $systemStats['total_products'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Calculate inventory value
    $stmt = $pdo->query("SELECT SUM(quantity * cost) as total_value FROM vend_inventory WHERE quantity > 0");
    $systemStats['total_inventory_value'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0);

    // Count low stock items
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM vend_inventory WHERE quantity < 10 AND quantity > 0");
    $systemStats['low_stock_items'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {
    error_log("System stats error: " . $e->getMessage());
}

// ============================================================================
// SALES METRICS (Last 30 days)
// ============================================================================

$salesMetrics = [
    'total_sales' => 0,
    'sales_count' => 0,
    'avg_transaction' => 0
];

try {
    // Total sales
    $stmt = $pdo->query("
        SELECT
            SUM(total) as sales_total,
            COUNT(*) as transaction_count,
            AVG(total) as avg_sale
        FROM vend_sales
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $salesMetrics['total_sales'] = (float)($data['sales_total'] ?? 0);
    $salesMetrics['sales_count'] = (int)($data['transaction_count'] ?? 0);
    $salesMetrics['avg_transaction'] = (float)($data['avg_sale'] ?? 0);
} catch (Exception $e) {
    error_log("Sales metrics error: " . $e->getMessage());
}

// ============================================================================
// INVENTORY STATUS
// ============================================================================

$inventoryStatus = [
    'total_items' => 0,
    'in_stock' => 0,
    'out_of_stock' => 0,
    'low_stock' => 0
];

try {
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN quantity > 0 THEN 1 ELSE 0 END) as in_stock,
            SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
            SUM(CASE WHEN quantity > 0 AND quantity < 10 THEN 1 ELSE 0 END) as low_stock
        FROM vend_inventory
    ");
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $inventoryStatus['total_items'] = (int)($data['total'] ?? 0);
    $inventoryStatus['in_stock'] = (int)($data['in_stock'] ?? 0);
    $inventoryStatus['out_of_stock'] = (int)($data['out_of_stock'] ?? 0);
    $inventoryStatus['low_stock'] = (int)($data['low_stock'] ?? 0);
} catch (Exception $e) {
    error_log("Inventory status error: " . $e->getMessage());
}

// ============================================================================
// TRANSFER & ORDER STATS
// ============================================================================

$operationStats = [
    'pending_transfers' => 0,
    'completed_transfers' => 0,
    'pending_orders' => 0,
    'completed_orders' => 0
];

try {
    // Stock transfers
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM stock_transfers WHERE status = 'Pending'");
    $operationStats['pending_transfers'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM stock_transfers WHERE status = 'Completed'");
    $operationStats['completed_transfers'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Purchase orders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM purchase_orders WHERE status = 'Pending'");
    $operationStats['pending_orders'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM purchase_orders WHERE status = 'Received'");
    $operationStats['completed_orders'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {
    error_log("Operation stats error: " . $e->getMessage());
}

// ============================================================================
// RECENT ACTIVITY
// ============================================================================

$recentActivity = [];
try {
    $stmt = $pdo->query("
        SELECT 'Transfer' as type, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as timestamp, status
        FROM stock_transfers
        ORDER BY created_at DESC LIMIT 3
        UNION ALL
        SELECT 'Order' as type, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as timestamp, status
        FROM purchase_orders
        ORDER BY created_at DESC LIMIT 2
    ");
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Recent activity error: " . $e->getMessage());
}
?>

<!-- Dashboard Header -->
<div class="overview-header" style="margin-bottom: 30px;">
    <h1 style="font-size: 28px; font-weight: 600; color: #2c3e50;">CIS Dashboard Overview</h1>
    <p style="color: #7f8c8d;">Real-time inventory, sales, and operations metrics</p>
</div>

<!-- System Statistics -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <div style="color: #7f8c8d; font-size: 14px; margin-bottom: 10px;">Active Outlets</div>
        <div style="font-size: 28px; font-weight: bold; color: #2980b9;"><?php echo $systemStats['total_outlets']; ?></div>
        <div style="color: #95a5a6; font-size: 12px;">Retail locations</div>
    </div>
    <div class="stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <div style="color: #7f8c8d; font-size: 14px; margin-bottom: 10px;">Total Products</div>
        <div style="font-size: 28px; font-weight: bold; color: #27ae60;"><?php echo number_format($systemStats['total_products']); ?></div>
        <div style="color: #95a5a6; font-size: 12px;">In catalog</div>
    </div>
    <div class="stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <div style="color: #7f8c8d; font-size: 14px; margin-bottom: 10px;">Inventory Value</div>
        <div style="font-size: 28px; font-weight: bold; color: #8e44ad;">$<?php echo number_format($systemStats['total_inventory_value'], 2); ?></div>
        <div style="color: #95a5a6; font-size: 12px;">Current stock</div>
    </div>
    <div class="stat-card" style="background: #fff5f5; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <div style="color: #7f8c8d; font-size: 14px; margin-bottom: 10px;">Low Stock ⚠️</div>
        <div style="font-size: 28px; font-weight: bold; color: #e74c3c;"><?php echo $systemStats['low_stock_items']; ?></div>
        <div style="color: #95a5a6; font-size: 12px;">Items below 10 units</div>
    </div>
</div>

<!-- Sales Metrics -->
<div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 30px;">
    <h2 style="font-size: 18px; font-weight: 600; color: #2c3e50; margin-bottom: 20px;">Sales Metrics (Last 30 Days)</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
        <div style="border-left: 4px solid #3498db; padding: 15px;">
            <div style="color: #7f8c8d; font-size: 13px; margin-bottom: 5px;">Total Sales</div>
            <div style="font-size: 24px; font-weight: bold; color: #3498db;">$<?php echo number_format($salesMetrics['total_sales'], 2); ?></div>
        </div>
        <div style="border-left: 4px solid #2ecc71; padding: 15px;">
            <div style="color: #7f8c8d; font-size: 13px; margin-bottom: 5px;">Transactions</div>
            <div style="font-size: 24px; font-weight: bold; color: #2ecc71;"><?php echo number_format($salesMetrics['sales_count']); ?></div>
        </div>
        <div style="border-left: 4px solid #9b59b6; padding: 15px;">
            <div style="color: #7f8c8d; font-size: 13px; margin-bottom: 5px;">Avg Transaction</div>
            <div style="font-size: 24px; font-weight: bold; color: #9b59b6;">$<?php echo number_format($salesMetrics['avg_transaction'], 2); ?></div>
        </div>
    </div>
</div>

<!-- Inventory Status -->
<div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 30px;">
    <h2 style="font-size: 18px; font-weight: 600; color: #2c3e50; margin-bottom: 20px;">Inventory Status</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px;">
        <div style="text-align: center; padding: 20px; border-radius: 8px; background: #e8f8f5;">
            <div style="font-size: 24px; font-weight: bold; color: #27ae60;"><?php echo number_format($inventoryStatus['in_stock']); ?></div>
            <div style="color: #27ae60; font-size: 13px; margin-top: 5px;">In Stock</div>
        </div>
        <div style="text-align: center; padding: 20px; border-radius: 8px; background: #fff9e6;">
            <div style="font-size: 24px; font-weight: bold; color: #f39c12;"><?php echo number_format($inventoryStatus['low_stock']); ?></div>
            <div style="color: #f39c12; font-size: 13px; margin-top: 5px;">Low Stock</div>
        </div>
        <div style="text-align: center; padding: 20px; border-radius: 8px; background: #fadbd8;">
            <div style="font-size: 24px; font-weight: bold; color: #e74c3c;"><?php echo number_format($inventoryStatus['out_of_stock']); ?></div>
            <div style="color: #e74c3c; font-size: 13px; margin-top: 5px;">Out of Stock</div>
        </div>
        <div style="text-align: center; padding: 20px; border-radius: 8px; background: #ebf5fb;">
            <div style="font-size: 24px; font-weight: bold; color: #3498db;"><?php echo number_format($inventoryStatus['total_items']); ?></div>
            <div style="color: #3498db; font-size: 13px; margin-top: 5px;">Total Items</div>
        </div>
    </div>
</div>

<!-- Operations Status -->
<div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 30px;">
    <h2 style="font-size: 18px; font-weight: 600; color: #2c3e50; margin-bottom: 20px;">Operations Status</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        <div style="border: 1px solid #ecf0f1; padding: 20px; border-radius: 8px;">
            <div style="font-weight: 600; margin-bottom: 15px; color: #2c3e50;">Stock Transfers</div>
            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #ecf0f1;">
                <span style="color: #7f8c8d;">Pending</span>
                <strong style="color: #f39c12;"><?php echo $operationStats['pending_transfers']; ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px 0;">
                <span style="color: #7f8c8d;">Completed</span>
                <strong style="color: #27ae60;"><?php echo $operationStats['completed_transfers']; ?></strong>
            </div>
        </div>
        <div style="border: 1px solid #ecf0f1; padding: 20px; border-radius: 8px;">
            <div style="font-weight: 600; margin-bottom: 15px; color: #2c3e50;">Purchase Orders</div>
            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #ecf0f1;">
                <span style="color: #7f8c8d;">Pending</span>
                <strong style="color: #f39c12;"><?php echo $operationStats['pending_orders']; ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px 0;">
                <span style="color: #7f8c8d;">Received</span>
                <strong style="color: #27ae60;"><?php echo $operationStats['completed_orders']; ?></strong>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
    <h2 style="font-size: 18px; font-weight: 600; color: #2c3e50; margin-bottom: 20px;">Recent Activity</h2>
    <div>
        <?php if (empty($recentActivity)): ?>
            <p style="color: #95a5a6; text-align: center; padding: 20px;">No recent activity</p>
        <?php else: ?>
            <?php foreach ($recentActivity as $activity): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #ecf0f1;">
                    <div>
                        <span style="font-weight: 600; color: #2c3e50;"><?php echo htmlspecialchars($activity['type']); ?></span>
                        <span style="color: #95a5a6; margin-left: 10px; font-size: 13px;"><?php echo $activity['timestamp']; ?></span>
                    </div>
                    <span style="padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; background: <?php echo (strpos($activity['status'], 'Pending') !== false) ? '#fef5e7; color: #f39c12;' : '#e8f8f5; color: #27ae60;'; ?>">
                        <?php echo htmlspecialchars($activity['status']); ?>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
