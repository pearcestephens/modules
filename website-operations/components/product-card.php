<?php
/**
 * Website Operations Module - Product Card Component
 *
 * Reusable product display card with inventory and actions
 *
 * @version 1.0.0
 * @author Ecigdis Development Team
 * @date 2025-11-14
 */

/**
 * Render a product card
 *
 * @param array $product Product data
 * @param array $options Display options
 */
function renderProductCard($product, $options = []) {
    // Default options
    $defaults = [
        'show_inventory' => true,
        'show_price' => true,
        'show_category' => true,
        'show_actions' => true,
        'compact' => false
    ];

    $options = array_merge($defaults, $options);

    // Stock status
    $stock = (int)($product['stock'] ?? 0);
    $stockStatus = getStockStatus($stock, $product['reorder_point'] ?? 10);
    $stockClass = getStockClass($stockStatus);

    // Price formatting
    $price = formatCurrency($product['price'] ?? 0);
    $wholesalePrice = !empty($product['wholesale_price']) ? formatCurrency($product['wholesale_price']) : null;

    // Image
    $imageUrl = $product['image'] ?? '';
    $productName = htmlspecialchars($product['name'] ?? 'Unnamed Product');

    ?>
    <div class="webops-card product-card" data-product-id="<?php echo $product['id'] ?? ''; ?>">
        <?php if ($imageUrl): ?>
            <div class="product-image">
                <img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="<?php echo $productName; ?>">
                <?php if ($stockStatus === 'out_of_stock'): ?>
                    <div class="product-overlay out-of-stock">Out of Stock</div>
                <?php elseif ($stockStatus === 'low_stock'): ?>
                    <div class="product-overlay low-stock">Low Stock</div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="product-image product-placeholder">
                🏷️
            </div>
        <?php endif; ?>

        <div class="webops-card-body">
            <h3 class="product-name"><?php echo $productName; ?></h3>

            <?php if (!empty($product['sku'])): ?>
                <div class="product-sku">
                    <small class="text-muted">SKU: <?php echo htmlspecialchars($product['sku']); ?></small>
                </div>
            <?php endif; ?>

            <?php if ($options['show_category'] && !empty($product['category'])): ?>
                <div class="product-category">
                    <span class="webops-badge webops-badge-gray">
                        <?php echo htmlspecialchars($product['category']); ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($options['show_price']): ?>
                <div class="product-pricing">
                    <div class="product-price">
                        <strong><?php echo $price; ?></strong>
                        <?php if ($wholesalePrice): ?>
                            <small class="text-muted">Wholesale: <?php echo $wholesalePrice; ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($options['show_inventory']): ?>
                <div class="product-inventory">
                    <div class="inventory-row">
                        <span>Stock:</span>
                        <span class="webops-badge webops-badge-<?php echo $stockClass; ?>">
                            <?php echo $stock; ?> units
                        </span>
                    </div>

                    <?php if (!empty($product['reorder_point'])): ?>
                        <div class="inventory-row">
                            <small class="text-muted">Reorder at: <?php echo (int)$product['reorder_point']; ?></small>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($product['description']) && !$options['compact']): ?>
                <div class="product-description">
                    <small><?php echo htmlspecialchars(substr($product['description'], 0, 100)); ?>...</small>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($options['show_actions']): ?>
            <div class="webops-card-footer">
                <div class="webops-flex webops-gap-sm">
                    <button
                        class="webops-btn webops-btn-sm webops-btn-primary"
                        onclick="editProduct(<?php echo $product['id']; ?>)">
                        Edit
                    </button>

                    <?php if ($stockStatus === 'low_stock' || $stockStatus === 'out_of_stock'): ?>
                        <button
                            class="webops-btn webops-btn-sm webops-btn-success"
                            onclick="reorderProduct(<?php echo $product['id']; ?>)">
                            Reorder
                        </button>
                    <?php endif; ?>

                    <button
                        class="webops-btn webops-btn-sm webops-btn-secondary"
                        onclick="viewProductAnalytics(<?php echo $product['id']; ?>)">
                        Analytics
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render compact product list item
 *
 * @param array $product Product data
 */
function renderProductListItem($product) {
    $stock = (int)($product['stock'] ?? 0);
    $stockStatus = getStockStatus($stock, $product['reorder_point'] ?? 10);
    $stockClass = getStockClass($stockStatus);
    $price = formatCurrency($product['price'] ?? 0);
    ?>
    <div class="product-list-item" data-product-id="<?php echo $product['id']; ?>" onclick="editProduct(<?php echo $product['id']; ?>)">
        <div class="product-list-image">
            <?php if (!empty($product['image'])): ?>
                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
            <?php else: ?>
                <div class="product-list-placeholder">🏷️</div>
            <?php endif; ?>
        </div>
        <div class="product-list-info">
            <strong><?php echo htmlspecialchars($product['name'] ?? 'Unnamed Product'); ?></strong>
            <?php if (!empty($product['sku'])): ?>
                <br><small class="text-muted">SKU: <?php echo htmlspecialchars($product['sku']); ?></small>
            <?php endif; ?>
        </div>
        <div class="product-list-category">
            <?php echo htmlspecialchars($product['category'] ?? '-'); ?>
        </div>
        <div class="product-list-stock">
            <span class="webops-badge webops-badge-<?php echo $stockClass; ?>">
                <?php echo $stock; ?>
            </span>
        </div>
        <div class="product-list-price">
            <?php echo $price; ?>
        </div>
    </div>
    <?php
}

/**
 * Render product grid
 *
 * @param array $products Array of products
 * @param int $columns Number of columns
 */
function renderProductGrid($products, $columns = 3) {
    if (empty($products)) {
        echo '<p class="text-muted">No products found</p>';
        return;
    }
    ?>
    <div class="webops-grid webops-grid-<?php echo $columns; ?>">
        <?php foreach ($products as $product): ?>
            <?php renderProductCard($product); ?>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Render product table
 *
 * @param array $products Array of products
 */
function renderProductTable($products) {
    if (empty($products)) {
        echo '<p class="text-muted">No products found</p>';
        return;
    }
    ?>
    <div class="webops-table-container">
        <table class="webops-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <?php
                    $stock = (int)($product['stock'] ?? 0);
                    $stockStatus = getStockStatus($stock, $product['reorder_point'] ?? 10);
                    $stockClass = getStockClass($stockStatus);
                    ?>
                    <tr data-product-id="<?php echo $product['id']; ?>">
                        <td>
                            <?php if (!empty($product['image'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image']); ?>"
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                            <?php else: ?>
                                <div style="width: 50px; height: 50px; background: #f3f4f6; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                    🏷️
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($product['name'] ?? 'Unnamed'); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($product['sku'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($product['category'] ?? '-'); ?></td>
                        <td><?php echo formatCurrency($product['price'] ?? 0); ?></td>
                        <td><?php echo $stock; ?></td>
                        <td>
                            <span class="webops-badge webops-badge-<?php echo $stockClass; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $stockStatus)); ?>
                            </span>
                        </td>
                        <td>
                            <div class="webops-flex webops-gap-sm">
                                <button class="webops-btn webops-btn-sm webops-btn-primary" onclick="editProduct(<?php echo $product['id']; ?>)">
                                    Edit
                                </button>
                                <button class="webops-btn webops-btn-sm webops-btn-secondary" onclick="viewProductAnalytics(<?php echo $product['id']; ?>)">
                                    View
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Get stock status
 *
 * @param int $stock Current stock
 * @param int $reorderPoint Reorder point
 * @return string Stock status
 */
function getStockStatus($stock, $reorderPoint = 10) {
    if ($stock <= 0) {
        return 'out_of_stock';
    } elseif ($stock <= $reorderPoint) {
        return 'low_stock';
    } else {
        return 'in_stock';
    }
}

/**
 * Get stock badge class
 *
 * @param string $status Stock status
 * @return string CSS class
 */
function getStockClass($status) {
    $statusMap = [
        'in_stock' => 'success',
        'low_stock' => 'warning',
        'out_of_stock' => 'danger'
    ];

    return $statusMap[$status] ?? 'gray';
}

/**
 * Render product variant selector
 *
 * @param array $variants Product variants
 */
function renderProductVariants($variants) {
    if (empty($variants)) {
        return;
    }
    ?>
    <div class="product-variants">
        <label class="webops-label">Select Variant:</label>
        <select class="webops-select" onchange="selectVariant(this.value)">
            <?php foreach ($variants as $variant): ?>
                <option value="<?php echo $variant['id']; ?>"
                        <?php echo ($variant['default'] ?? false) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($variant['name']); ?>
                    - <?php echo formatCurrency($variant['price']); ?>
                    (Stock: <?php echo (int)$variant['stock']; ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
}

/**
 * Render product bulk actions
 */
function renderProductBulkActions() {
    ?>
    <div class="product-bulk-actions" id="bulk-actions-bar" style="display: none;">
        <div class="webops-flex webops-items-center webops-gap-md">
            <span id="bulk-selected-count">0 selected</span>
            <button class="webops-btn webops-btn-sm webops-btn-primary" onclick="bulkUpdatePrice()">
                Update Price
            </button>
            <button class="webops-btn webops-btn-sm webops-btn-success" onclick="bulkUpdateStock()">
                Update Stock
            </button>
            <button class="webops-btn webops-btn-sm webops-btn-warning" onclick="bulkUpdateCategory()">
                Change Category
            </button>
            <button class="webops-btn webops-btn-sm webops-btn-danger" onclick="bulkDelete()">
                Delete
            </button>
            <button class="webops-btn webops-btn-sm webops-btn-secondary" onclick="clearSelection()">
                Clear
            </button>
        </div>
    </div>
    <?php
}
