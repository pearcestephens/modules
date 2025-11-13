<?php
/**
 * 🎯 REAL-TIME COMPANY ACTIVITY FEED WITH GAMIFICATION
 *
 * Shows LIVE data from:
 * - Vend POS (transfers, sales, inventory)
 * - Website orders & Click & Collect
 * - Staff performance & achievements
 * - Customer feedback (5-star reviews)
 * - Industry news (RSS aggregator)
 * - Low stock alerts & top sellers
 *
 * @package CIS Ultra
 * @version 3.00 - GAMIFIED EDITION
 */

// Load helper functions (REAL database queries!)
require_once __DIR__ . '/../includes/feed-functions.php';

// Load News Aggregator
require_once __DIR__ . '/../../../news-aggregator/FeedProvider.php';
use CIS\NewsAggregator\FeedProvider;

// Initialize Feed Provider
$newsProvider = new FeedProvider($GLOBALS['conn']);

// 🔥 GET REAL-TIME DATA FROM LIVE TABLES
$recentOrders = getRecentWebsiteOrders(10);
$clickCollect = getClickAndCollectOrders(5);
$pendingTransfers = getPendingTransfers(8);
$pendingPOs = getPendingPurchaseOrders(5);
$storeAccuracy = getStoreAccuracyStats();
$topProducts = getTopSellingProducts(5);
$lowStock = getLowStockAlerts(5);

// Get NEWS FEED from aggregator (REAL external content!)
$externalNews = $newsProvider->getUnifiedFeed([
    'limit' => 15,
    'include_pinned' => true
]);

$trendingNews = $newsProvider->getTrending(5, 7);
$newsCategories = $newsProvider->getCategories();

// Mix internal activity with external news
$recentActivity = [];

// Add internal CIS activity
foreach(getRecentSystemActivity(10) as $activity) {
    $activity->feed_type = 'internal';
    $recentActivity[] = $activity;
}

// Add external news articles
foreach($externalNews as $article) {
    $newsItem = (object)[
        'feed_type' => 'external',
        'type' => 'news',
        'title' => $article['title'],
        'description' => $article['content'],
        'timestamp' => $article['published_at'],
        'details' => [
            'source' => $article['source_name'],
            'category' => ucfirst(str_replace('-', ' ', $article['category']))
        ],
        'image' => $article['image_url'],
        'url' => $article['external_url'],
        'engagement' => $article['view_count'] + $article['click_count'],
        'is_pinned' => $article['is_pinned']
    ];
    $recentActivity[] = $newsItem;
}

// Sort by pinned first, then timestamp
usort($recentActivity, function($a, $b) {
    if (isset($a->is_pinned) && $a->is_pinned) return -1;
    if (isset($b->is_pinned) && $b->is_pinned) return 1;
    return strtotime($b->timestamp) - strtotime($a->timestamp);
});

$staffOnline = getStaffOnlineNow();
?>

<div class="premium-dashboard-feed">

    <!-- LEFT SIDEBAR: Quick Actions & Widgets -->
    <aside class="feed-sidebar feed-sidebar-left">

        <!-- Quick Actions Card -->
        <div class="widget-card pulse-on-hover">
            <div class="widget-header">
                <i class="bi bi-lightning-charge-fill"></i>
                <h3>Quick Actions</h3>
            </div>
            <div class="quick-actions">
                <a href="/stock-transfers.php" class="quick-action-btn">
                    <i class="bi bi-arrow-left-right"></i>
                    <span>New Transfer</span>
                </a>
                <a href="/create-purchase-order.php" class="quick-action-btn">
                    <i class="bi bi-clipboard-plus"></i>
                    <span>New PO</span>
                </a>
                <a href="/product-browser.php" class="quick-action-btn">
                    <i class="bi bi-search"></i>
                    <span>Find Product</span>
                </a>
                <a href="/orders-overview.php" class="quick-action-btn">
                    <i class="bi bi-cart-check"></i>
                    <span>View Orders</span>
                </a>
            </div>
        </div>

        <!-- Store Accuracy Widget -->
        <div class="widget-card">
            <div class="widget-header">
                <i class="bi bi-bullseye"></i>
                <h3>Store Accuracy</h3>
            </div>
            <div class="accuracy-grid">
                <?php foreach($storeAccuracy as $store): ?>
                <div class="accuracy-item">
                    <div class="store-name"><?= htmlspecialchars($store->name) ?></div>
                    <div class="accuracy-bar">
                        <div class="accuracy-fill <?= $store->accuracy >= 95 ? 'high' : ($store->accuracy >= 85 ? 'medium' : 'low') ?>"
                             style="width: <?= $store->accuracy ?>%"></div>
                    </div>
                    <div class="accuracy-percent"><?= number_format($store->accuracy, 1) ?>%</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Staff Online Widget -->
        <div class="widget-card">
            <div class="widget-header">
                <i class="bi bi-people-fill"></i>
                <h3>Staff Online</h3>
                <span class="badge-count"><?= count($staffOnline) ?></span>
            </div>
            <div class="staff-list">
                <?php foreach($staffOnline as $staff): ?>
                <div class="staff-item">
                    <div class="staff-avatar">
                        <span><?= strtoupper(substr($staff->first_name, 0, 1)) ?></span>
                    </div>
                    <div class="staff-info">
                        <div class="staff-name"><?= htmlspecialchars($staff->first_name . ' ' . $staff->last_name) ?></div>
                        <div class="staff-role"><?= htmlspecialchars($staff->role) ?></div>
                    </div>
                    <div class="online-dot"></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </aside>

    <!-- CENTER: Activity Feed -->
    <main class="feed-main">

        <!-- Feed Header -->
        <div class="feed-header">
            <h2>
                <i class="bi bi-rss-fill"></i>
                Live Activity Feed
            </h2>
            <div class="feed-filters">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="orders">Orders</button>
                <button class="filter-btn" data-filter="transfers">Transfers</button>
                <button class="filter-btn" data-filter="pos">POs</button>
            </div>
        </div>

    <!-- Activity Stream -->
    <div id="activity-stream" class="activity-stream">

            <?php foreach($recentActivity as $activity): ?>

            <?php if ($activity->feed_type === 'external'): ?>
                <!-- EXTERNAL NEWS ARTICLE -->
                <div class="activity-card news-card" data-type="news">
                    <?php if (!empty($activity->image)): ?>
                    <div class="news-image">
                        <img src="<?= htmlspecialchars($activity->image) ?>" alt="<?= htmlspecialchars($activity->title) ?>">
                        <?php if ($activity->is_pinned): ?>
                        <div class="pinned-badge">
                            <i class="bi bi-pin-fill"></i> Pinned
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="activity-content">
                        <div class="activity-header">
                            <div class="activity-title">
                                <i class="bi bi-newspaper"></i>
                                <?= htmlspecialchars($activity->title) ?>
                            </div>
                            <div class="activity-time"><?= timeAgo($activity->timestamp) ?></div>
                        </div>

                        <div class="activity-body">
                            <?= nl2br(htmlspecialchars(mb_strimwidth($activity->description, 0, 200, "..."))) ?>
                        </div>

                        <div class="news-meta">
                            <span class="news-source">
                                <i class="bi bi-globe"></i>
                                <?= htmlspecialchars($activity->details['source']) ?>
                            </span>
                            <span class="news-category">
                                <i class="bi bi-tag"></i>
                                <?= htmlspecialchars($activity->details['category']) ?>
                            </span>
                            <?php if ($activity->engagement > 0): ?>
                            <span class="news-engagement">
                                <i class="bi bi-eye"></i>
                                <?= $activity->engagement ?> views
                            </span>
                            <?php endif; ?>
                        </div>

                        <div class="activity-actions">
                            <a href="<?= htmlspecialchars($activity->url) ?>" target="_blank" class="action-btn action-primary">
                                <i class="bi bi-box-arrow-up-right"></i>
                                Read Full Article
                            </a>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- INTERNAL CIS ACTIVITY -->
                <div class="activity-card" data-type="<?= $activity->type ?>">
                    <div class="activity-icon <?= $activity->type ?>">
                        <?php
                        $icons = [
                            'order' => 'cart-check-fill',
                            'transfer' => 'arrow-left-right',
                            'po' => 'clipboard-check-fill',
                            'clickcollect' => 'bag-check-fill',
                            'lowstock' => 'exclamation-triangle-fill',
                            'feedback' => 'chat-square-quote-fill'
                        ];
                        ?>
                        <i class="bi bi-<?= $icons[$activity->type] ?? 'circle-fill' ?>"></i>
                    </div>

                    <div class="activity-content">
                        <div class="activity-header">
                            <div class="activity-title"><?= htmlspecialchars($activity->title) ?></div>
                            <div class="activity-time"><?= timeAgo($activity->timestamp) ?></div>
                        </div>

                        <div class="activity-body">
                            <?= htmlspecialchars($activity->description) ?>
                        </div>

                        <?php if (!empty($activity->details)): ?>
                        <div class="activity-details">
                            <?php foreach($activity->details as $key => $value): ?>
                            <span class="detail-item">
                                <strong><?= ucfirst($key) ?>:</strong> <?= htmlspecialchars($value) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($activity->actions)): ?>
                        <div class="activity-actions">
                            <?php foreach($activity->actions as $action): ?>
                            <a href="<?= htmlspecialchars($action->url) ?>" class="action-btn">
                                <i class="bi bi-<?= $action->icon ?>"></i>
                                <?= htmlspecialchars($action->label) ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php endforeach; ?>

        </div>

        <!-- Load More -->
        <div class="load-more">
            <button class="btn-load-more">
                <i class="bi bi-arrow-clockwise"></i>
                Load More Activity
            </button>
        </div>

    </main>

    <!-- RIGHT SIDEBAR: Stats & Alerts -->
    <aside class="feed-sidebar feed-sidebar-right">

        <!-- Live Stats Card -->
        <div class="widget-card stats-card">
            <div class="widget-header">
                <i class="bi bi-graph-up-arrow"></i>
                <h3>Today's Stats</h3>
            </div>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-icon orders">
                        <i class="bi bi-cart-fill"></i>
                    </div>
                    <div class="stat-value"><?= count($recentOrders) ?></div>
                    <div class="stat-label">Website Orders</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon clickcollect">
                        <i class="bi bi-bag-fill"></i>
                    </div>
                    <div class="stat-value"><?= count($clickCollect) ?></div>
                    <div class="stat-label">Click & Collect</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon transfers">
                        <i class="bi bi-arrow-repeat"></i>
                    </div>
                    <div class="stat-value"><?= count($pendingTransfers) ?></div>
                    <div class="stat-label">Pending Transfers</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon pos">
                        <i class="bi bi-clipboard-data"></i>
                    </div>
                    <div class="stat-value"><?= count($pendingPOs) ?></div>
                    <div class="stat-label">Purchase Orders</div>
                </div>
            </div>
        </div>

        <!-- Low Stock Alerts -->
        <div class="widget-card alert-card">
            <div class="widget-header">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <h3>Low Stock Alerts</h3>
                <span class="badge-danger"><?= count($lowStock) ?></span>
            </div>
            <div class="alert-list">
                <?php foreach($lowStock as $item): ?>
                <div class="alert-item">
                    <div class="alert-icon">⚠️</div>
                    <div class="alert-info">
                        <div class="alert-product"><?= htmlspecialchars($item->name) ?></div>
                        <div class="alert-stock">Only <?= $item->stock ?> left</div>
                    </div>
                    <a href="/product-view.php?id=<?= $item->id ?>" class="alert-action">
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Top Products -->
        <div class="widget-card">
            <div class="widget-header">
                <i class="bi bi-fire"></i>
                <h3>Top Sellers Today</h3>
            </div>
            <div class="product-list">
                <?php foreach($topProducts as $index => $product): ?>
                <div class="product-item">
                    <div class="product-rank">#<?= $index + 1 ?></div>
                    <div class="product-info">
                        <div class="product-name"><?= htmlspecialchars($product->name) ?></div>
                        <div class="product-sales"><?= $product->sales ?> sold</div>
                    </div>
                    <div class="product-trend">
                        <i class="bi bi-arrow-up-right text-success"></i>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </aside>

</div>

<style>
.premium-dashboard-feed {
    display: grid;
    grid-template-columns: 280px 1fr 320px;
    gap: 20px;
    padding: 20px;
    min-height: calc(100vh - 120px);
}

/* Sidebar Styles */
.feed-sidebar {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.widget-card {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    overflow: hidden;
    transition: all 0.3s ease;
}

.widget-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}

.pulse-on-hover:hover {
    animation: pulse 1s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.02); }
}

.widget-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 16px;
    border-bottom: 1px solid #f0f0f0;
    font-weight: 600;
    font-size: 14px;
}

.widget-header i {
    font-size: 18px;
    color: #6c757d;
}

.widget-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    flex: 1;
}

.badge-count, .badge-danger {
    background: #dc3545;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    padding: 12px;
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 16px 8px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.2s ease;
    gap: 8px;
}

.quick-action-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.quick-action-btn i {
    font-size: 24px;
}

.quick-action-btn span {
    font-size: 12px;
    font-weight: 600;
}

/* Store Accuracy */
.accuracy-grid {
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.accuracy-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.store-name {
    font-size: 12px;
    font-weight: 600;
    color: #2c3e50;
}

.accuracy-bar {
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
}

.accuracy-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s ease;
}

.accuracy-fill.high {
    background: linear-gradient(90deg, #28a745, #20c997);
}

.accuracy-fill.medium {
    background: linear-gradient(90deg, #ffc107, #fd7e14);
}

.accuracy-fill.low {
    background: linear-gradient(90deg, #dc3545, #c82333);
}

.accuracy-percent {
    font-size: 11px;
    font-weight: 600;
    color: #6c757d;
    text-align: right;
}

/* Staff List */
.staff-list {
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.staff-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.staff-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
}

.staff-info {
    flex: 1;
}

.staff-name {
    font-size: 13px;
    font-weight: 600;
    color: #2c3e50;
}

.staff-role {
    font-size: 11px;
    color: #6c757d;
}

.online-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #28a745;
    box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.2);
}

/* Feed Main */
.feed-main {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.feed-header {
    background: #ffffff;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.feed-header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}

.feed-filters {
    display: flex;
    gap: 8px;
}

.filter-btn {
    padding: 8px 16px;
    border: 1px solid #dee2e6;
    background: white;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.filter-btn:hover {
    background: #f8f9fa;
}

.filter-btn.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-color: #667eea;
}

/* Activity Stream */
.activity-stream {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.activity-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 16px;
    display: flex;
    gap: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    border-left: 4px solid #dee2e6;
}

.activity-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    transform: translateX(4px);
}

.activity-card[data-type="order"] {
    border-left-color: #007bff;
}

.activity-card[data-type="transfer"] {
    border-left-color: #6f42c1;
}

.activity-card[data-type="po"] {
    border-left-color: #28a745;
}

.activity-card[data-type="clickcollect"] {
    border-left-color: #fd7e14;
}

.activity-card[data-type="lowstock"] {
    border-left-color: #dc3545;
}

.activity-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}

.activity-icon.order {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
}

.activity-icon.transfer {
    background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);
    color: white;
}

.activity-icon.po {
    background: linear-gradient(135deg, #28a745 0%, #218838 100%);
    color: white;
}

.activity-icon.clickcollect {
    background: linear-gradient(135deg, #fd7e14 0%, #e8590c 100%);
    color: white;
}

.activity-icon.lowstock {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
}

.activity-content {
    flex: 1;
}

.activity-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.activity-title {
    font-size: 15px;
    font-weight: 700;
    color: #2c3e50;
}

.activity-time {
    font-size: 12px;
    color: #6c757d;
}

.activity-body {
    font-size: 14px;
    color: #495057;
    margin-bottom: 8px;
}

.activity-details {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #f0f0f0;
}

.detail-item {
    font-size: 12px;
    color: #6c757d;
}

.detail-item strong {
    color: #495057;
}

.activity-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
}

.action-btn {
    padding: 6px 12px;
    background: #f8f9fa;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #495057;
    text-decoration: none;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

.action-btn:hover {
    background: #e9ecef;
    color: #007bff;
}

/* Stats Card */
.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    padding: 12px;
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 16px 8px;
    background: #f8f9fa;
    border-radius: 10px;
    gap: 8px;
}

.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: white;
}

.stat-icon.orders {
    background: linear-gradient(135deg, #007bff, #0056b3);
}

.stat-icon.clickcollect {
    background: linear-gradient(135deg, #fd7e14, #e8590c);
}

.stat-icon.transfers {
    background: linear-gradient(135deg, #6f42c1, #5a32a3);
}

.stat-icon.pos {
    background: linear-gradient(135deg, #28a745, #218838);
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #2c3e50;
}

.stat-label {
    font-size: 11px;
    color: #6c757d;
    text-align: center;
}

/* Alert List */
.alert-list {
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.alert-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: #fff3cd;
    border-radius: 8px;
}

.alert-icon {
    font-size: 20px;
}

.alert-info {
    flex: 1;
}

.alert-product {
    font-size: 13px;
    font-weight: 600;
    color: #2c3e50;
}

.alert-stock {
    font-size: 11px;
    color: #856404;
}

.alert-action {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #856404;
    text-decoration: none;
}

/* Product List */
.product-list {
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.product-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.product-rank {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
}

.product-info {
    flex: 1;
}

.product-name {
    font-size: 13px;
    font-weight: 600;
    color: #2c3e50;
}

.product-sales {
    font-size: 11px;
    color: #6c757d;
}

.product-trend i {
    font-size: 18px;
}

/* Load More */
.load-more {
    text-align: center;
    padding: 20px;
}

.btn-load-more {
    padding: 12px 32px;
    background: white;
    border: 2px solid #dee2e6;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    color: #495057;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-load-more:hover {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-color: #667eea;
}

/* NEWS CARD STYLING */
.news-card {
    flex-direction: column;
    padding: 0;
    overflow: hidden;
}

.news-image {
    width: 100%;
    height: 240px;
    overflow: hidden;
    position: relative;
}

.news-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.news-card:hover .news-image img {
    transform: scale(1.05);
}

.pinned-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.news-card .activity-content {
    padding: 20px;
    flex: 1;
}

.news-card .activity-title {
    font-size: 18px;
    font-weight: 700;
    line-height: 1.4;
    color: #2c3e50;
}

.news-card .activity-title i {
    color: #667eea;
    margin-right: 6px;
}

.news-meta {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 12px 0;
    margin-top: 12px;
    border-top: 1px solid #e9ecef;
    flex-wrap: wrap;
}

.news-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: #6c757d;
}

.news-meta i {
    font-size: 14px;
    color: #999;
}

.news-source {
    font-weight: 600;
}

.news-category {
    background: #f8f9fa;
    padding: 4px 10px;
    border-radius: 12px;
}

.action-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white !important;
    border: none;
}

.action-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

/* Responsive */
@media (max-width: 1400px) {
    .premium-dashboard-feed {
        grid-template-columns: 260px 1fr 280px;
    }
}

@media (max-width: 1200px) {
    .premium-dashboard-feed {
        grid-template-columns: 1fr 280px;
    }
    .feed-sidebar-left {
        display: none;
    }
}

@media (max-width: 768px) {
    .premium-dashboard-feed {
        grid-template-columns: 1fr;
    }
    .feed-sidebar-right {
        display: none;
    }

    .news-image {
        height: 180px;
    }
}
</style>

<script>
// Feed filtering
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        // Update active state
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        // Filter cards
        const filter = this.dataset.filter;
        document.querySelectorAll('.activity-card').forEach(card => {
            if (filter === 'all' || card.dataset.type === filter) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    });
});

// Auto-refresh feed every 30 seconds (AJAX)
function refreshFeed() {
    const container = document.getElementById('activity-stream');
    if (!container) return;

    fetch('/modules/base/api/feed_refresh.php', { method: 'GET', credentials: 'same-origin' })
        .then(res => res.json())
        .then(data => {
            if (data.ok && data.html) {
                container.innerHTML = data.html;
                console.log('🔄 Feed refreshed at', new Date().toISOString());
            } else {
                console.warn('Feed refresh failed', data.error || 'no html');
            }
        })
        .catch(err => console.error('Feed refresh error', err));
}

// Start periodic refresh
setInterval(refreshFeed, 30000);

// Optional: initial refresh after 5s to let page settle
setTimeout(refreshFeed, 5000);
</script>
<?php

/**
 * Helper function to format time ago
 */
function timeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}
?>
