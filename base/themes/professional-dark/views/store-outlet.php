<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Outlets - Professional Dark Theme</title>
    <?php echo $theme->styles(); ?>
</head>
<body>
    <header class="cis-header">
        <a href="#" class="cis-logo">🛍️ CIS Dashboard</a>
        <nav class="cis-nav">
            <a href="?layout=facebook-feed" class="cis-nav-link">Feed</a>
            <a href="?layout=card-grid" class="cis-nav-link">Products</a>
            <a href="?layout=store-outlet" class="cis-nav-link active">Stores</a>
        </nav>
        <div class="flex flex-center gap-2">
            <span class="text-small text-muted">Multi-Store Management</span>
        </div>
    </header>

    <div class="cis-container" style="padding-top: 40px;">
        <!-- Page Header -->
        <div class="flex flex-between flex-center mb-3">
            <div>
                <h1 style="font-size: 28px; font-weight: 700; margin-bottom: 8px;">Store Network</h1>
                <p class="text-muted"><?php echo count($stores); ?> active locations across New Zealand</p>
            </div>
            <button class="cis-btn cis-btn-primary">➕ Add Store</button>
        </div>

        <!-- Network Overview -->
        <div class="cis-grid cis-grid-4 mb-3">
            <div class="cis-card">
                <div class="cis-stat">
                    <div class="cis-stat-value">$<?php echo number_format(array_sum(array_column($stores, 'sales_today')), 2); ?></div>
                    <div class="cis-stat-label">Total Network Sales</div>
                    <div class="cis-stat-change positive">▲ 12.5%</div>
                </div>
            </div>
            <div class="cis-card">
                <div class="cis-stat">
                    <div class="cis-stat-value"><?php echo array_sum(array_column($stores, 'orders_pending')); ?></div>
                    <div class="cis-stat-label">Pending Orders</div>
                    <div class="cis-stat-change warning">→ 0%</div>
                </div>
            </div>
            <div class="cis-card">
                <div class="cis-stat">
                    <div class="cis-stat-value"><?php echo array_sum(array_column($stores, 'stock_alerts')); ?></div>
                    <div class="cis-stat-label">Stock Alerts</div>
                    <div class="cis-stat-change danger">▲ 3</div>
                </div>
            </div>
            <div class="cis-card">
                <div class="cis-stat">
                    <div class="cis-stat-value"><?php echo array_sum(array_column($stores, 'staff_online')); ?></div>
                    <div class="cis-stat-label">Staff Online</div>
                    <div class="cis-stat-change positive" data-live>● Live</div>
                </div>
            </div>
        </div>

        <!-- Store Cards -->
        <div class="cis-grid cis-grid-3 mb-3">
            <?php foreach ($stores as $store): ?>
            <div class="cis-store-card">
                <div class="cis-store-header">
                    <div>
                        <div class="cis-store-name"><?php echo $store['name']; ?></div>
                        <div class="cis-store-location">📍 <?php echo $store['location']; ?></div>
                        <div class="text-muted text-small mt-1">
                            Manager: <?php echo $store['manager']; ?>
                        </div>
                    </div>
                    <div class="cis-store-icon">🏪</div>
                </div>

                <div class="cis-store-stats">
                    <div class="cis-store-stat">
                        <div class="cis-store-stat-value">$<?php echo number_format($store['sales_today'], 0); ?></div>
                        <div class="cis-store-stat-label">Sales Today</div>
                    </div>
                    <div class="cis-store-stat">
                        <div class="cis-store-stat-value"><?php echo $store['orders_pending']; ?></div>
                        <div class="cis-store-stat-label">Pending Orders</div>
                    </div>
                    <div class="cis-store-stat">
                        <div class="cis-store-stat-value <?php echo $store['stock_alerts'] > 0 ? 'text-warning' : ''; ?>">
                            <?php echo $store['stock_alerts']; ?>
                        </div>
                        <div class="cis-store-stat-label">Stock Alerts</div>
                    </div>
                    <div class="cis-store-stat">
                        <div class="cis-store-stat-value"><?php echo $store['staff_online']; ?></div>
                        <div class="cis-store-stat-label">Staff Online</div>
                    </div>
                </div>

                <?php if ($store['stock_alerts'] > 0): ?>
                <div class="cis-alert cis-alert-warning mt-2" style="padding: 8px 12px; margin-bottom: 0;">
                    <span>⚠️</span>
                    <span style="font-size: 12px;"><?php echo $store['stock_alerts']; ?> items need restocking</span>
                </div>
                <?php endif; ?>

                <div style="margin-top: 16px; display: flex; gap: 8px;">
                    <button class="cis-btn cis-btn-primary" style="flex: 1; padding: 8px; font-size: 13px;">
                        📊 View Details
                    </button>
                    <button class="cis-btn cis-btn-secondary" style="padding: 8px 12px; font-size: 13px;">
                        📞 Call
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Two Column Layout: Stock Alerts + Today's Performance -->
        <div class="cis-grid cis-grid-2">
            <!-- Stock Alerts -->
            <div class="cis-card">
                <div class="cis-card-header">
                    <h3 class="cis-card-title">⚠️ Stock Alerts</h3>
                    <span class="cis-badge cis-badge-warning"><?php echo array_sum(array_column($stores, 'stock_alerts')); ?> Items</span>
                </div>
                <div class="cis-card-body">
                    <table class="cis-table">
                        <thead>
                            <tr>
                                <th>Store</th>
                                <th>Product</th>
                                <th>Stock</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stores as $store): ?>
                                <?php if ($store['stock_alerts'] > 0): ?>
                                <tr>
                                    <td><?php echo $store['name']; ?></td>
                                    <td>JUUL Starter Kit</td>
                                    <td><span class="cis-badge cis-badge-danger">Low (3)</span></td>
                                    <td><button class="cis-btn cis-btn-primary" style="padding: 4px 8px; font-size: 11px;">Restock</button></td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Today's Performance -->
            <div class="cis-card">
                <div class="cis-card-header">
                    <h3 class="cis-card-title">📈 Today's Performance</h3>
                    <span class="text-small text-muted"><?php echo date('l, F j'); ?></span>
                </div>
                <div class="cis-card-body">
                    <table class="cis-table">
                        <thead>
                            <tr>
                                <th>Store</th>
                                <th>Sales</th>
                                <th>Orders</th>
                                <th>Avg Order</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stores as $store): ?>
                            <tr>
                                <td>
                                    <div class="flex flex-center gap-1">
                                        <span>🏪</span>
                                        <span><?php echo $store['name']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <strong style="color: var(--color-primary);">
                                        $<?php echo number_format($store['sales_today'], 2); ?>
                                    </strong>
                                </td>
                                <td><?php echo $store['orders_pending']; ?></td>
                                <td>$<?php echo number_format($store['sales_today'] / max($store['orders_pending'], 1), 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr style="background: rgba(255, 255, 255, 0.05); font-weight: 600;">
                                <td>TOTAL</td>
                                <td style="color: var(--color-primary);">
                                    $<?php echo number_format(array_sum(array_column($stores, 'sales_today')), 2); ?>
                                </td>
                                <td><?php echo array_sum(array_column($stores, 'orders_pending')); ?></td>
                                <td>
                                    $<?php
                                        $totalSales = array_sum(array_column($stores, 'sales_today'));
                                        $totalOrders = array_sum(array_column($stores, 'orders_pending'));
                                        echo number_format($totalSales / max($totalOrders, 1), 2);
                                    ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Store Communication Panel -->
        <div class="cis-card mt-3">
            <div class="cis-card-header">
                <h3 class="cis-card-title">📞 Quick Contact</h3>
            </div>
            <div class="cis-card-body">
                <div class="cis-grid cis-grid-3">
                    <?php foreach ($stores as $store): ?>
                    <div style="padding: 12px; background: rgba(255,255,255,0.02); border-radius: 8px;">
                        <div style="font-weight: 600; margin-bottom: 4px;"><?php echo $store['name']; ?></div>
                        <div class="text-small text-muted mb-1">📞 <?php echo $store['phone']; ?></div>
                        <div class="text-small text-muted">👤 <?php echo $store['manager']; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <?php echo $theme->scripts(); ?>
</body>
</html>
