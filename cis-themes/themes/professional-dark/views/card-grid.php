<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Grid - Professional Dark Theme</title>
    <?php echo $theme->styles(); ?>
</head>
<body>
    <header class="cis-header">
        <a href="#" class="cis-logo">🛍️ CIS Dashboard</a>
        <nav class="cis-nav">
            <a href="?layout=facebook-feed" class="cis-nav-link">Feed</a>
            <a href="?layout=card-grid" class="cis-nav-link active">Products</a>
            <a href="?layout=store-outlet" class="cis-nav-link">Stores</a>
        </nav>
        <div class="flex flex-center gap-2">
            <span class="text-small text-muted">Inventory Management</span>
        </div>
    </header>

    <div class="cis-container" style="padding-top: 40px;">
        <!-- Page Header -->
        <div class="flex flex-between flex-center mb-3">
            <div>
                <h1 style="font-size: 28px; font-weight: 700; margin-bottom: 8px;">Product Catalog</h1>
                <p class="text-muted">Manage your inventory across all stores</p>
            </div>
            <button class="cis-btn cis-btn-primary">➕ Add Product</button>
        </div>

        <!-- Filter Bar -->
        <div class="cis-card mb-3">
            <div class="flex gap-2 flex-center">
                <input type="text" placeholder="🔍 Search products..."
                       style="flex: 1; padding: 10px 16px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: #f1f5f9;">
                <select style="padding: 10px 16px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: #f1f5f9;">
                    <option>All Categories</option>
                    <option>Pod Systems</option>
                    <option>Mods</option>
                    <option>Tanks</option>
                    <option>Accessories</option>
                </select>
                <select style="padding: 10px 16px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: #f1f5f9;">
                    <option>Sort by: Best Selling</option>
                    <option>Sort by: Price Low-High</option>
                    <option>Sort by: Price High-Low</option>
                    <option>Sort by: Stock Level</option>
                </select>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="cis-grid cis-grid-4 mb-3">
            <div class="cis-card">
                <div class="cis-stat">
                    <div class="cis-stat-value"><?php echo count($products); ?></div>
                    <div class="cis-stat-label">Total Products</div>
                </div>
            </div>
            <div class="cis-card">
                <div class="cis-stat">
                    <div class="cis-stat-value"><?php echo array_sum(array_column($products, 'stock')); ?></div>
                    <div class="cis-stat-label">Units in Stock</div>
                </div>
            </div>
            <div class="cis-card">
                <div class="cis-stat">
                    <div class="cis-stat-value">$<?php echo number_format(array_sum(array_column($products, 'price')) / count($products), 2); ?></div>
                    <div class="cis-stat-label">Avg Price</div>
                </div>
            </div>
            <div class="cis-card">
                <div class="cis-stat">
                    <div class="cis-stat-value"><?php echo array_sum(array_column($products, 'sales')); ?></div>
                    <div class="cis-stat-label">Units Sold (7d)</div>
                </div>
            </div>
        </div>

        <!-- Product Grid -->
        <div class="cis-grid cis-grid-4">
            <?php foreach ($products as $product): ?>
            <div class="cis-product-card">
                <div class="cis-product-image">
                    <?php echo $product['emoji']; ?>
                </div>
                <div class="cis-product-body">
                    <div class="cis-product-sku">SKU: <?php echo $product['sku']; ?></div>
                    <h3 class="cis-product-name"><?php echo $product['name']; ?></h3>
                    <div class="cis-product-price">$<?php echo number_format($product['price'], 2); ?></div>

                    <div class="cis-product-stock">
                        <div class="flex flex-between">
                            <span class="text-muted text-small">Stock:</span>
                            <span class="cis-product-stock-value <?php echo $product['stock'] < 20 ? 'low' : 'high'; ?>">
                                <?php echo $product['stock']; ?> units
                            </span>
                        </div>
                        <div class="flex flex-between mt-1">
                            <span class="text-muted text-small">Sold (7d):</span>
                            <span class="text-small"><?php echo $product['sales']; ?> units</span>
                        </div>
                    </div>

                    <div style="margin-top: 16px; display: flex; gap: 8px;">
                        <button class="cis-btn cis-btn-primary" style="flex: 1; padding: 8px;">Edit</button>
                        <button class="cis-btn cis-btn-secondary" style="padding: 8px 12px;">📊</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <div class="flex flex-center gap-2 mt-3">
            <button class="cis-btn cis-btn-secondary">← Previous</button>
            <div class="flex gap-1">
                <button class="cis-btn cis-btn-primary" style="padding: 10px 16px;">1</button>
                <button class="cis-btn cis-btn-secondary" style="padding: 10px 16px;">2</button>
                <button class="cis-btn cis-btn-secondary" style="padding: 10px 16px;">3</button>
            </div>
            <button class="cis-btn cis-btn-secondary">Next →</button>
        </div>

        <!-- Recent Orders Section -->
        <div class="cis-card mt-3">
            <div class="cis-card-header">
                <h3 class="cis-card-title">📦 Recent Orders</h3>
                <button class="cis-btn cis-btn-secondary" style="padding: 6px 12px; font-size: 12px;">View All</button>
            </div>
            <div class="cis-card-body">
                <?php foreach (array_slice($orders, 0, 5) as $order): ?>
                <div class="cis-order">
                    <div class="cis-order-info">
                        <div class="cis-order-id">Order #<?php echo $order['id']; ?></div>
                        <div class="cis-order-customer">
                            <?php echo $order['customer']; ?> • <?php echo $order['store']; ?>
                        </div>
                    </div>
                    <div class="cis-order-meta">
                        <span class="cis-badge cis-badge-<?php echo $order['status']; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                        <div class="cis-order-total">$<?php echo number_format($order['total'], 2); ?></div>
                        <div class="cis-order-time"><?php echo $order['time']; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php echo $theme->scripts(); ?>
</body>
</html>
