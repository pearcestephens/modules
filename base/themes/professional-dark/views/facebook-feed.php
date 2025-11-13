<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>News Feed - Professional Dark Theme</title>
        <?php
            // Ensure required data exists with safe defaults
            $metrics = isset($metrics) && is_array($metrics) ? $metrics : [];
            $metrics_defaults = [
                'orders_today' => 0,
                'total_sales' => 0,
                'active_customers' => 0,
                'average_order' => 0,
            ];
            $metrics = array_merge($metrics_defaults, $metrics);

            // Prepare flattened sales data for mini chart
            $salesChart = isset($salesChart) && is_array($salesChart) ? $salesChart : [];
            $sales_values = array_column($salesChart, 'sales');
            $max_sales = max($sales_values ?: [1]);
        ?>
        <?php echo $theme->styles(); ?>
</head>
<body>
    <header class="cis-header">
        <a href="#" class="cis-logo">🛍️ CIS Dashboard</a>
        <nav class="cis-nav">
            <a href="?layout=facebook-feed" class="cis-nav-link active">Feed</a>
            <a href="?layout=card-grid" class="cis-nav-link">Products</a>
            <a href="?layout=store-outlet" class="cis-nav-link">Stores</a>
        </nav>
        <div class="flex flex-center gap-2">
            <span class="text-small text-muted"><?php echo date('l, F j Y'); ?></span>
        </div>
    </header>

    <div class="cis-container" style="padding-top: 40px;">
        <!-- Quick Stats Bar -->
        <div class="cis-grid cis-grid-4 mb-3">
            <div class="cis-card">
                <div class="cis-stat">
                    <div class="cis-stat-value"><?php echo (int)$metrics['orders_today']; ?></div>
                    <div class="cis-stat-label">Orders Today</div>
                    <div class="cis-stat-change positive">▲ —</div>
                </div>
            </div>
            <div class="cis-card">
                <div class="cis-stat">
                    <div class="cis-stat-value">$<?php echo number_format((float)$metrics['total_sales'], 2); ?></div>
                    <div class="cis-stat-label">Total Sales</div>
                    <div class="cis-stat-change positive">▲ —</div>
                </div>
            </div>
            <div class="cis-card">
                <div class="cis-stat">
                    <div class="cis-stat-value"><?php echo number_format((int)$metrics['active_customers']); ?></div>
                    <div class="cis-stat-label">Customers</div>
                    <div class="cis-stat-change positive">▲ —</div>
                </div>
            </div>
            <div class="cis-card">
                <div class="cis-stat">
                    <div class="cis-stat-value">$<?php echo number_format((float)$metrics['average_order'], 2); ?></div>
                    <div class="cis-stat-label">Avg Order</div>
                    <div class="cis-stat-change negative">▼ —</div>
                </div>
            </div>
        </div>

        <!-- News Feed -->
        <div class="cis-feed">
            <?php foreach ($newsFeed as $post): ?>
            <article class="cis-feed-post">
                <div class="cis-feed-post-header">
                    <div class="cis-feed-avatar">
                        <?php echo substr($post['author'], 0, 2); ?>
                    </div>
                    <div class="cis-feed-author">
                        <div class="cis-feed-author-name"><?php echo $post['author']; ?></div>
                        <div class="cis-feed-post-time"><?php echo $post['time']; ?></div>
                    </div>
                    <span class="cis-badge cis-badge-<?php echo $post['type']; ?>">
                        <?php echo ucfirst($post['type']); ?>
                    </span>
                </div>

                <div class="cis-feed-content">
                    <?php echo $post['content']; ?>
                </div>

                <?php if (!empty($post['image'])): ?>
                <img src="<?php echo $post['image']; ?>" alt="Post image" class="cis-feed-image">
                <?php endif; ?>

                <div class="cis-feed-actions">
                    <div class="cis-feed-action">
                        👍 Like (<?php echo $post['likes']; ?>)
                    </div>
                    <div class="cis-feed-action">
                        💬 Comment (<?php echo $post['comments']; ?>)
                    </div>
                    <div class="cis-feed-action">
                        🔗 Share
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <!-- Sidebar with Recent Activity -->
        <div class="cis-grid cis-grid-2 mt-3" style="max-width: 700px; margin-left: auto; margin-right: auto;">
            <div class="cis-card">
                <div class="cis-card-header">
                    <h3 class="cis-card-title">📊 Weekly Sales</h3>
                </div>
                <div class="cis-chart">
                    <div class="cis-chart-simple">
                    <?php foreach ($salesChart as $point): $day = $point['date'] ?? ''; $amount = (float)($point['sales'] ?? 0); ?>
                        <div class="cis-chart-bar"
                        style="height: <?php echo $max_sales > 0 ? round(($amount / $max_sales) * 100) : 0; ?>%;"
                        data-value="<?php echo htmlspecialchars((string)$amount, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="cis-chart-label"><?php echo $day; ?></div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="cis-card">
                <div class="cis-card-header">
                    <h3 class="cis-card-title">⚡ Recent Activity</h3>
                </div>
                <div class="cis-card-body">
                    <?php foreach ($activities as $activity): ?>
                    <div class="cis-activity">
                        <div class="cis-activity-icon"><?php echo $activity['icon']; ?></div>
                        <div class="cis-activity-content">
                            <div class="cis-activity-text"><?php echo $activity['text']; ?></div>
                            <div class="cis-activity-time"><?php echo $activity['time']; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <?php echo $theme->scripts(); ?>
</body>
</html>
