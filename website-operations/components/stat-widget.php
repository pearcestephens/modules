<?php
/**
 * Website Operations Module - Stat Widget Component
 *
 * Reusable statistics widget for displaying KPIs
 *
 * @version 1.0.0
 * @author Ecigdis Development Team
 * @date 2025-11-14
 */

/**
 * Render a statistics widget
 *
 * @param array $config Widget configuration
 *   - label: string - Widget label
 *   - value: mixed - Current value
 *   - change: float - Percentage change (optional)
 *   - icon: string - Icon identifier (optional)
 *   - color: string - Color theme (primary, success, warning, danger)
 *   - format: string - Value format (number, currency, percentage)
 *   - stat_key: string - Data attribute key for updates
 */
function renderStatWidget($config) {
    // Default values
    $defaults = [
        'label' => 'Statistic',
        'value' => 0,
        'change' => null,
        'icon' => null,
        'color' => 'primary',
        'format' => 'number',
        'stat_key' => ''
    ];

    $config = array_merge($defaults, $config);

    // Format the value
    $displayValue = formatStatValue($config['value'], $config['format']);

    // Determine change class
    $changeClass = '';
    $changePrefix = '';
    if ($config['change'] !== null) {
        $changeClass = $config['change'] >= 0 ? 'positive' : 'negative';
        $changePrefix = $config['change'] >= 0 ? '+' : '';
    }

    // Get icon HTML
    $iconHTML = '';
    if ($config['icon']) {
        $iconHTML = renderStatIcon($config['icon'], $config['color']);
    }

    // Data attribute for JavaScript updates
    $dataAttr = $config['stat_key'] ? ' data-stat="' . htmlspecialchars($config['stat_key']) . '"' : '';

    ?>
    <div class="webops-stat-widget"<?php echo $dataAttr; ?>>
        <?php if ($iconHTML): ?>
            <?php echo $iconHTML; ?>
        <?php endif; ?>

        <div class="webops-stat-label">
            <?php echo htmlspecialchars($config['label']); ?>
        </div>

        <div class="webops-stat-value" data-format="<?php echo htmlspecialchars($config['format']); ?>">
            <?php echo $displayValue; ?>
        </div>

        <?php if ($config['change'] !== null): ?>
            <div class="webops-stat-change <?php echo $changeClass; ?>">
                <span><?php echo $changePrefix . number_format(abs($config['change']), 1); ?>%</span>
                <span><?php echo $changeClass === 'positive' ? '↑' : '↓'; ?></span>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Format stat value based on type
 *
 * @param mixed $value Value to format
 * @param string $format Format type
 * @return string Formatted value
 */
function formatStatValue($value, $format) {
    switch ($format) {
        case 'currency':
            return '$' . number_format($value, 2);

        case 'percentage':
            return number_format($value, 1) . '%';

        case 'number':
            if ($value >= 1000000) {
                return number_format($value / 1000000, 1) . 'M';
            } elseif ($value >= 1000) {
                return number_format($value / 1000, 1) . 'K';
            }
            return number_format($value);

        case 'decimal':
            return number_format($value, 2);

        default:
            return (string)$value;
    }
}

/**
 * Render stat icon
 *
 * @param string $icon Icon identifier
 * @param string $color Color theme
 * @return string Icon HTML
 */
function renderStatIcon($icon, $color) {
    $iconMap = [
        'orders' => '📦',
        'revenue' => '💰',
        'customers' => '👥',
        'products' => '🏷️',
        'shipping' => '🚚',
        'savings' => '💵',
        'growth' => '📈',
        'decline' => '📉',
        'warning' => '⚠️',
        'success' => '✅',
        'info' => 'ℹ️',
        'star' => '⭐',
        'cart' => '🛒',
        'package' => '📦',
        'truck' => '🚛',
        'chart' => '📊',
        'clock' => '⏰',
        'calendar' => '📅',
        'check' => '✓',
        'alert' => '!',
    ];

    $iconContent = $iconMap[$icon] ?? $icon;

    return '<div class="webops-stat-icon ' . htmlspecialchars($color) . '">' .
           $iconContent .
           '</div>';
}

/**
 * Render a stat grid (multiple widgets in a row)
 *
 * @param array $widgets Array of widget configurations
 * @param int $columns Number of columns (2, 3, or 4)
 */
function renderStatGrid($widgets, $columns = 4) {
    echo '<div class="webops-grid webops-grid-' . $columns . '">';
    foreach ($widgets as $widget) {
        renderStatWidget($widget);
    }
    echo '</div>';
}

/**
 * Example stat widgets for dashboard
 */
function renderDashboardStats($stats) {
    $widgets = [
        [
            'label' => 'Total Orders',
            'value' => $stats['total_orders'] ?? 0,
            'change' => $stats['orders_change'] ?? null,
            'icon' => 'orders',
            'color' => 'primary',
            'format' => 'number',
            'stat_key' => 'total_orders'
        ],
        [
            'label' => 'Revenue',
            'value' => $stats['total_revenue'] ?? 0,
            'change' => $stats['revenue_change'] ?? null,
            'icon' => 'revenue',
            'color' => 'success',
            'format' => 'currency',
            'stat_key' => 'total_revenue'
        ],
        [
            'label' => 'Customers',
            'value' => $stats['total_customers'] ?? 0,
            'change' => $stats['customers_change'] ?? null,
            'icon' => 'customers',
            'color' => 'info',
            'format' => 'number',
            'stat_key' => 'total_customers'
        ],
        [
            'label' => 'Shipping Savings',
            'value' => $stats['shipping_savings'] ?? 0,
            'change' => $stats['savings_change'] ?? null,
            'icon' => 'savings',
            'color' => 'warning',
            'format' => 'currency',
            'stat_key' => 'shipping_savings'
        ]
    ];

    renderStatGrid($widgets, 4);
}

/**
 * Render order stats
 */
function renderOrderStats($stats) {
    $widgets = [
        [
            'label' => 'Pending Orders',
            'value' => $stats['pending'] ?? 0,
            'icon' => 'clock',
            'color' => 'warning',
            'format' => 'number',
            'stat_key' => 'pending_orders'
        ],
        [
            'label' => 'Processing',
            'value' => $stats['processing'] ?? 0,
            'icon' => 'package',
            'color' => 'info',
            'format' => 'number',
            'stat_key' => 'processing_orders'
        ],
        [
            'label' => 'Completed',
            'value' => $stats['completed'] ?? 0,
            'icon' => 'check',
            'color' => 'success',
            'format' => 'number',
            'stat_key' => 'completed_orders'
        ],
        [
            'label' => 'Today\'s Orders',
            'value' => $stats['today'] ?? 0,
            'icon' => 'calendar',
            'color' => 'primary',
            'format' => 'number',
            'stat_key' => 'today_orders'
        ]
    ];

    renderStatGrid($widgets, 4);
}

/**
 * Render product stats
 */
function renderProductStats($stats) {
    $widgets = [
        [
            'label' => 'Active Products',
            'value' => $stats['active'] ?? 0,
            'icon' => 'products',
            'color' => 'success',
            'format' => 'number',
            'stat_key' => 'active_products'
        ],
        [
            'label' => 'Low Stock',
            'value' => $stats['low_stock'] ?? 0,
            'icon' => 'warning',
            'color' => 'warning',
            'format' => 'number',
            'stat_key' => 'low_stock_products'
        ],
        [
            'label' => 'Out of Stock',
            'value' => $stats['out_of_stock'] ?? 0,
            'icon' => 'alert',
            'color' => 'danger',
            'format' => 'number',
            'stat_key' => 'out_of_stock_products'
        ],
        [
            'label' => 'Total Value',
            'value' => $stats['inventory_value'] ?? 0,
            'icon' => 'revenue',
            'color' => 'primary',
            'format' => 'currency',
            'stat_key' => 'inventory_value'
        ]
    ];

    renderStatGrid($widgets, 4);
}

/**
 * Render shipping stats
 */
function renderShippingStats($stats) {
    $widgets = [
        [
            'label' => 'Total Shipped',
            'value' => $stats['total_shipped'] ?? 0,
            'icon' => 'shipping',
            'color' => 'info',
            'format' => 'number',
            'stat_key' => 'total_shipped'
        ],
        [
            'label' => 'Average Cost',
            'value' => $stats['avg_cost'] ?? 0,
            'icon' => 'revenue',
            'color' => 'primary',
            'format' => 'currency',
            'stat_key' => 'avg_shipping_cost'
        ],
        [
            'label' => 'Money Saved',
            'value' => $stats['money_saved'] ?? 0,
            'change' => $stats['savings_change'] ?? null,
            'icon' => 'savings',
            'color' => 'success',
            'format' => 'currency',
            'stat_key' => 'money_saved'
        ],
        [
            'label' => 'Avg Savings Per Order',
            'value' => $stats['avg_savings'] ?? 0,
            'icon' => 'chart',
            'color' => 'warning',
            'format' => 'currency',
            'stat_key' => 'avg_savings'
        ]
    ];

    renderStatGrid($widgets, 4);
}
