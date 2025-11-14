<?php
/**
 * Website Operations Module - Order Card Component
 *
 * Reusable order display card with actions
 *
 * @version 1.0.0
 * @author Ecigdis Development Team
 * @date 2025-11-14
 */

/**
 * Render an order card
 *
 * @param array $order Order data
 * @param array $options Display options
 */
function renderOrderCard($order, $options = []) {
    // Default options
    $defaults = [
        'show_customer' => true,
        'show_items' => true,
        'show_shipping' => true,
        'show_actions' => true,
        'compact' => false
    ];

    $options = array_merge($defaults, $options);

    // Get status badge class
    $statusClass = getOrderStatusClass($order['status'] ?? 'pending');

    // Format currency
    $total = formatCurrency($order['total'] ?? 0);
    $shippingCost = formatCurrency($order['shipping_cost'] ?? 0);

    // Format date
    $orderDate = date('M j, Y g:i A', strtotime($order['created_at'] ?? 'now'));
    $relativeTime = getRelativeTime($order['created_at'] ?? 'now');

    ?>
    <div class="webops-card order-card" data-order-id="<?php echo $order['id'] ?? ''; ?>">
        <div class="webops-card-header">
            <div class="webops-flex webops-items-center webops-gap-md">
                <strong class="order-number">Order #<?php echo htmlspecialchars($order['order_number'] ?? $order['id']); ?></strong>
                <span class="webops-badge webops-badge-<?php echo $statusClass; ?>">
                    <?php echo htmlspecialchars(ucfirst($order['status'] ?? 'Pending')); ?>
                </span>
            </div>
            <div class="order-total webops-stat-value" data-format="currency">
                <?php echo $total; ?>
            </div>
        </div>

        <div class="webops-card-body">
            <?php if ($options['show_customer'] && !empty($order['customer_name'])): ?>
                <div class="order-info-row">
                    <span class="order-info-label">👤 Customer:</span>
                    <span class="order-info-value">
                        <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong>
                        <?php if (!empty($order['customer_email'])): ?>
                            <br><small><?php echo htmlspecialchars($order['customer_email']); ?></small>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($options['show_items']): ?>
                <div class="order-info-row">
                    <span class="order-info-label">📦 Items:</span>
                    <span class="order-info-value"><?php echo (int)($order['item_count'] ?? 0); ?> items</span>
                </div>
            <?php endif; ?>

            <?php if ($options['show_shipping']): ?>
                <div class="order-info-row">
                    <span class="order-info-label">🚚 Shipping:</span>
                    <span class="order-info-value">
                        <?php echo htmlspecialchars($order['shipping_method'] ?? 'Standard'); ?>
                        <?php if (!empty($order['shipping_cost'])): ?>
                            (<?php echo $shippingCost; ?>)
                        <?php endif; ?>
                    </span>
                </div>

                <?php if (!empty($order['shipping_savings'])): ?>
                    <div class="order-info-row">
                        <span class="order-info-label">💰 Saved:</span>
                        <span class="order-info-value webops-stat-change positive">
                            <?php echo formatCurrency($order['shipping_savings']); ?>
                        </span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="order-info-row">
                <span class="order-info-label">📅 Date:</span>
                <span class="order-info-value">
                    <?php echo $orderDate; ?>
                    <br><small class="text-muted"><?php echo $relativeTime; ?></small>
                </span>
            </div>

            <?php if (!empty($order['notes'])): ?>
                <div class="order-notes">
                    <small><strong>Notes:</strong> <?php echo htmlspecialchars($order['notes']); ?></small>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($options['show_actions']): ?>
            <div class="webops-card-footer">
                <div class="webops-flex webops-gap-sm">
                    <button
                        class="webops-btn webops-btn-sm webops-btn-primary"
                        onclick="viewOrder(<?php echo $order['id']; ?>)">
                        View Details
                    </button>

                    <?php if (in_array($order['status'] ?? '', ['pending', 'processing'])): ?>
                        <button
                            class="webops-btn webops-btn-sm webops-btn-success"
                            onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'completed')">
                            Complete
                        </button>
                    <?php endif; ?>

                    <?php if ($order['status'] === 'pending'): ?>
                        <button
                            class="webops-btn webops-btn-sm webops-btn-secondary"
                            onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'processing')">
                            Process
                        </button>
                    <?php endif; ?>
                </div>

                <div class="order-actions-menu">
                    <button class="webops-btn webops-btn-sm webops-btn-secondary" onclick="showOrderMenu(<?php echo $order['id']; ?>)">
                        ⋮
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render compact order list item
 *
 * @param array $order Order data
 */
function renderOrderListItem($order) {
    $statusClass = getOrderStatusClass($order['status'] ?? 'pending');
    $total = formatCurrency($order['total'] ?? 0);
    ?>
    <div class="order-list-item" data-order-id="<?php echo $order['id']; ?>" onclick="viewOrder(<?php echo $order['id']; ?>)">
        <div class="order-list-info">
            <strong>Order #<?php echo htmlspecialchars($order['order_number'] ?? $order['id']); ?></strong>
            <span class="webops-badge webops-badge-<?php echo $statusClass; ?> webops-badge-sm">
                <?php echo htmlspecialchars($order['status'] ?? 'Pending'); ?>
            </span>
        </div>
        <div class="order-list-customer">
            <?php echo htmlspecialchars($order['customer_name'] ?? 'Unknown'); ?>
        </div>
        <div class="order-list-total">
            <?php echo $total; ?>
        </div>
        <div class="order-list-date">
            <?php echo getRelativeTime($order['created_at'] ?? 'now'); ?>
        </div>
    </div>
    <?php
}

/**
 * Get order status badge class
 *
 * @param string $status Order status
 * @return string CSS class
 */
function getOrderStatusClass($status) {
    $statusMap = [
        'pending' => 'warning',
        'processing' => 'info',
        'completed' => 'success',
        'cancelled' => 'danger',
        'refunded' => 'danger',
        'shipped' => 'info',
        'delivered' => 'success',
        'on_hold' => 'warning'
    ];

    return $statusMap[strtolower($status)] ?? 'gray';
}

/**
 * Render order items list
 *
 * @param array $items Order items
 */
function renderOrderItems($items) {
    if (empty($items)) {
        echo '<p class="text-muted">No items</p>';
        return;
    }
    ?>
    <div class="order-items-list">
        <?php foreach ($items as $item): ?>
            <div class="order-item">
                <div class="order-item-image">
                    <?php if (!empty($item['image'])): ?>
                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                    <?php else: ?>
                        <div class="order-item-placeholder">📦</div>
                    <?php endif; ?>
                </div>
                <div class="order-item-info">
                    <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                    <?php if (!empty($item['sku'])): ?>
                        <br><small class="text-muted">SKU: <?php echo htmlspecialchars($item['sku']); ?></small>
                    <?php endif; ?>
                </div>
                <div class="order-item-quantity">
                    Qty: <?php echo (int)$item['quantity']; ?>
                </div>
                <div class="order-item-price">
                    <?php echo formatCurrency($item['price'] ?? 0); ?>
                </div>
                <div class="order-item-total">
                    <?php echo formatCurrency(($item['price'] ?? 0) * ($item['quantity'] ?? 1)); ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Render order timeline
 *
 * @param array $events Order status change events
 */
function renderOrderTimeline($events) {
    if (empty($events)) {
        return;
    }
    ?>
    <div class="order-timeline">
        <?php foreach ($events as $event): ?>
            <div class="timeline-item">
                <div class="timeline-marker"></div>
                <div class="timeline-content">
                    <strong><?php echo htmlspecialchars($event['status']); ?></strong>
                    <p><?php echo htmlspecialchars($event['note'] ?? ''); ?></p>
                    <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($event['created_at'])); ?></small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Helper: Format currency
 *
 * @param float $amount Amount
 * @return string Formatted currency
 */
function formatCurrency($amount) {
    return '$' . number_format((float)$amount, 2);
}

/**
 * Helper: Get relative time
 *
 * @param string $datetime Datetime string
 * @return string Relative time
 */
function getRelativeTime($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}
