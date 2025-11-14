<?php
/**
 * OUTLET MANAGER DASHBOARD WIDGET
 * Shows outlet's transfers + inventory + AI recommendations
 */

try {
    // Get outlet's active transfers
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.transfer_number,
            c.transfer_type,
            c.status,
            c.created_at,
            COUNT(DISTINCT ci.id) as item_count,
            SUM(ci.quantity) as total_qty,
            o1.outlet_name as from_outlet,
            o2.outlet_name as to_outlet,
            SUM(ci.quantity * p.retail_price) as potential_value
        FROM consignments c
        LEFT JOIN consignment_items ci ON ci.consignment_id = c.id
        LEFT JOIN products p ON p.id = ci.product_id
        LEFT JOIN outlets o1 ON o1.id = c.from_outlet_id
        LEFT JOIN outlets o2 ON o2.id = c.to_outlet_id
        WHERE c.from_outlet_id = ? OR c.to_outlet_id = ?
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT 20
    ");

    $stmt->execute([$outlet_id, $outlet_id]);
    $outletTransfers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Get outlet inventory summary
    $stmt2 = $pdo->prepare("
        SELECT
            COUNT(DISTINCT product_id) as unique_products,
            SUM(quantity) as total_units,
            SUM(quantity * p.cost_price) as inventory_value
        FROM outlet_inventory oi
        LEFT JOIN products p ON p.id = oi.product_id
        WHERE oi.outlet_id = ? AND oi.quantity > 0
    ");
    $stmt2->execute([$outlet_id]);
    $inventory = $stmt2->fetch(\PDO::FETCH_ASSOC) ?? [
        'unique_products' => 0,
        'total_units' => 0,
        'inventory_value' => 0
    ];

    // Get low stock alerts
    $stmt3 = $pdo->prepare("
        SELECT
            p.id,
            p.sku,
            p.name,
            oi.quantity,
            COALESCE(p.reorder_point, 20) as reorder_point
        FROM outlet_inventory oi
        LEFT JOIN products p ON p.id = oi.product_id
        WHERE oi.outlet_id = ?
          AND oi.quantity <= COALESCE(p.reorder_point, 20)
        ORDER BY oi.quantity ASC
        LIMIT 10
    ");
    $stmt3->execute([$outlet_id]);
    $lowStock = $stmt3->fetchAll(\PDO::FETCH_ASSOC);

    // Get outlet name
    $stmt4 = $pdo->prepare("SELECT outlet_name, region FROM outlets WHERE id = ?");
    $stmt4->execute([$outlet_id]);
    $outlet = $stmt4->fetch(\PDO::FETCH_ASSOC) ?? ['outlet_name' => 'Unknown', 'region' => '?'];

} catch (Exception $e) {
    $outletTransfers = [];
    $inventory = ['unique_products' => 0, 'total_units' => 0, 'inventory_value' => 0];
    $lowStock = [];
    $outlet = ['outlet_name' => 'Unknown', 'region' => '?'];
}
?>

<div class="alert alert-info" style="margin-bottom: 2rem;">
    <i class="fas fa-store"></i>
    <strong><?= htmlspecialchars($outlet['outlet_name']) ?></strong>
    (<span><?= htmlspecialchars($outlet['region']) ?></span>)
</div>

<!-- Inventory Summary -->
<div class="widget-grid">
    <div class="widget-card info">
        <div class="metric-box">
            <div class="metric-value"><?= $inventory['unique_products'] ?? 0 ?></div>
            <div class="metric-label">UNIQUE PRODUCTS</div>
        </div>
        <small class="text-muted">In stock at this outlet</small>
    </div>

    <div class="widget-card info">
        <div class="metric-box">
            <div class="metric-value"><?= number_format($inventory['total_units'] ?? 0) ?></div>
            <div class="metric-label">TOTAL UNITS</div>
        </div>
        <small class="text-muted">All products combined</small>
    </div>

    <div class="widget-card info">
        <div class="metric-box">
            <div class="metric-value">$<?= number_format($inventory['inventory_value'] ?? 0, 0) ?></div>
            <div class="metric-label">INVENTORY VALUE</div>
        </div>
        <small class="text-muted">At cost price</small>
    </div>
</div>

<!-- LOW STOCK ALERTS -->
<?php if (!empty($lowStock)): ?>
    <div class="widget-card urgent" style="margin-bottom: 1.5rem;">
        <div class="widget-title" style="color: #dc3545;">
            <i class="fas fa-exclamation-circle"></i> Low Stock Alerts
        </div>
        <div class="widget-content">
            <?php foreach ($lowStock as $item): ?>
                <div style="padding: 0.75rem; background: #fff5f5; border-radius: 6px; margin-bottom: 0.5rem;">
                    <div style="font-weight: 600; margin-bottom: 0.25rem;">
                        <?= htmlspecialchars($item['sku']) ?> - <?= htmlspecialchars($item['name']) ?>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #dc3545; font-weight: 700;">
                            <?= $item['quantity'] ?> units
                        </span>
                        <span style="color: #999; font-size: 0.85rem;">
                            Reorder: <?= $item['reorder_point'] ?>
                        </span>
                    </div>
                    <button class="quick-action-btn" style="margin-top: 0.5rem; width: 100%; text-align: center;">
                        <i class="fas fa-plus"></i> Request Transfer
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- AI RECOMMENDATIONS -->
<div class="widget-card" style="margin-bottom: 1.5rem;">
    <div class="widget-title">
        <i class="fas fa-brain"></i> AI Recommendations
    </div>
    <div class="alert-box info">
        <strong>🤖 Smart Transfer Engine Analysis</strong><br>
        <small>Run daily at 2 AM - Analyzes stock imbalances across all outlets</small>
    </div>
    <div style="margin-top: 1rem;">
        <button class="quick-action-btn" style="width: 100%; margin-bottom: 0.5rem;">
            <i class="fas fa-sync"></i> View Latest Recommendations
        </button>
        <button class="quick-action-btn" style="width: 100%; background: #6c757d;">
            <i class="fas fa-cog"></i> Configure AI Rules
        </button>
    </div>
</div>

<!-- RECENT TRANSFERS -->
<div class="widget-card">
    <div class="widget-title">
        <i class="fas fa-history"></i> Recent Transfers
    </div>

    <?php if (empty($outletTransfers)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No recent transfers</p>
        </div>
    <?php else: ?>
        <div class="widget-content">
            <?php foreach ($outletTransfers as $t): ?>
                <div style="padding: 1rem; border-bottom: 1px solid #eee; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                        <strong><?= htmlspecialchars($t['transfer_number']) ?></strong>
                        <span class="status-badge status-<?= $t['status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $t['status'])) ?>
                        </span>
                    </div>

                    <div style="font-size: 0.9rem; color: #666; margin-bottom: 0.5rem;">
                        <i class="fas fa-arrow-right"></i>
                        <?= htmlspecialchars($t['from_outlet'] ?? 'Unknown') ?> →
                        <?= htmlspecialchars($t['to_outlet'] ?? 'Unknown') ?>
                    </div>

                    <div class="stat-row" style="border: none; padding: 0; margin-bottom: 0.5rem;">
                        <span><?= $t['item_count'] ?> items • <?= $t['total_qty'] ?? 0 ?> units • $<?= number_format($t['potential_value'] ?? 0, 0) ?></span>
                    </div>

                    <a href="/modules/consignments/view.php?id=<?= $t['id'] ?>"
                       class="quick-action-btn" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">
                        <i class="fas fa-eye"></i> Details
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
