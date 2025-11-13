<?php
/**
 * =============================================================================
 * VAPEULTRA COMPLETE DEMO - WITH FEED & CHAT
 * =============================================================================
 *
 * This demo shows ALL VapeUltra features:
 * - Facebook-style news feed
 * - Bottom chat bar
 * - Store cards view
 * - Toggle between views
 *
 * =============================================================================
 */

require_once __DIR__ . '/../bootstrap.php';

// Get view mode from session
$viewMode = $_SESSION['demo_view'] ?? 'dashboard';

// Handle view toggle
if (isset($_GET['toggle_view'])) {
    $_SESSION['demo_view'] = ($viewMode === 'dashboard') ? 'feed' : 'dashboard';
    header('Location: vapeultra-demo-full.php');
    exit;
}

// Start output buffering
ob_start();
?>

<div class="container-fluid">

    <!-- Premium Header -->
    <div class="premium-dashboard-header">
        <div class="premium-dashboard-header-content">
            <div>
                <h1 class="premium-dashboard-title">VapeUltra Complete Demo</h1>
                <p class="premium-dashboard-welcome">Explore all features: Feed, Chat, Cards</p>
            </div>
            <div class="premium-quick-actions">
                <button class="premium-action-btn" onclick="window.location.reload()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <a href="?toggle_view=1" class="premium-action-btn">
                    <i class="bi bi-<?= $viewMode === 'dashboard' ? 'fire' : 'grid-3x3-gap' ?>"></i>
                    <?= $viewMode === 'dashboard' ? 'Feed View' : 'Dashboard' ?>
                </a>
            </div>
        </div>
    </div>

    <?php if ($viewMode === 'feed'): ?>
        <!-- ============================================ -->
        <!-- FACEBOOK-STYLE FEED VIEW                     -->
        <!-- ============================================ -->
        <?php
        // Include feed functions
        require_once __DIR__ . '/../../base/templates/vape-ultra/includes/feed-functions.php';

        // Get feed data
        $recentActivity = getRecentSystemActivity(20);
        $storeAccuracy = getStoreAccuracyStats();
        $lowStock = getLowStockAlerts(5);
        ?>

        <div class="premium-dashboard-feed">
            <!-- LEFT SIDEBAR: Quick Actions -->
            <aside class="feed-sidebar feed-sidebar-left">
                <div class="widget-card">
                    <div class="widget-header">
                        <i class="bi bi-lightning-charge-fill"></i>
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="quick-actions">
                        <a href="#" class="quick-action-btn">
                            <i class="bi bi-arrow-left-right"></i>
                            <span>New Transfer</span>
                        </a>
                        <a href="#" class="quick-action-btn">
                            <i class="bi bi-clipboard-plus"></i>
                            <span>New PO</span>
                        </a>
                        <a href="#" class="quick-action-btn">
                            <i class="bi bi-search"></i>
                            <span>Search</span>
                        </a>
                    </div>
                </div>

                <!-- Store Accuracy Widget -->
                <div class="widget-card mt-3">
                    <div class="widget-header">
                        <i class="bi bi-bullseye"></i>
                        <h3>Store Accuracy</h3>
                    </div>
                    <?php foreach ($storeAccuracy as $store): ?>
                    <div class="store-accuracy-item">
                        <div class="store-name"><?= htmlspecialchars($store->name) ?></div>
                        <div class="accuracy-bar">
                            <div class="accuracy-fill" style="width: <?= $store->accuracy ?>%"></div>
                        </div>
                        <div class="accuracy-value"><?= $store->accuracy ?>%</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </aside>

            <!-- CENTER: Activity Feed -->
            <main class="feed-main">
                <div class="feed-header">
                    <h2><i class="bi bi-rss-fill"></i> Live Activity Feed</h2>
                    <p>Real-time updates from across all stores</p>
                </div>

                <?php foreach ($recentActivity as $activity): ?>
                <div class="activity-card">
                    <div class="activity-icon">
                        <?php
                        $icon = 'circle-fill';
                        switch($activity->type) {
                            case 'order': $icon = 'cart-check-fill'; break;
                            case 'transfer': $icon = 'arrow-left-right'; break;
                            case 'stock': $icon = 'box-seam-fill'; break;
                            case 'staff': $icon = 'person-badge-fill'; break;
                        }
                        ?>
                        <i class="bi bi-<?= $icon ?>"></i>
                    </div>
                    <div class="activity-content">
                        <h4><?= htmlspecialchars($activity->title) ?></h4>
                        <p><?= htmlspecialchars($activity->description) ?></p>
                        <div class="activity-meta">
                            <span class="activity-time">
                                <i class="bi bi-clock"></i>
                                <?= time_ago($activity->timestamp) ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </main>

            <!-- RIGHT SIDEBAR: Low Stock Alerts -->
            <aside class="feed-sidebar feed-sidebar-right">
                <div class="widget-card">
                    <div class="widget-header">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <h3>Low Stock Alerts</h3>
                    </div>
                    <?php foreach ($lowStock as $item): ?>
                    <div class="low-stock-item">
                        <div class="product-name"><?= htmlspecialchars($item->product_name) ?></div>
                        <div class="stock-info">
                            <span class="stock-level"><?= $item->qty ?> left</span>
                            <span class="store-name"><?= htmlspecialchars($item->store_name) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </aside>
        </div>

    <?php else: ?>
        <!-- ============================================ -->
        <!-- STANDARD DASHBOARD VIEW (STORE CARDS)        -->
        <!-- ============================================ -->

        <div class="row g-4">
            <!-- Stats Cards -->
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                                    <i class="bi bi-graph-up text-primary fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="text-muted mb-1">Total Sales</h6>
                                <h3 class="mb-0">$24,567</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-success bg-opacity-10 p-3">
                                    <i class="bi bi-box-seam text-success fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="text-muted mb-1">Products</h6>
                                <h3 class="mb-0">1,234</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                                    <i class="bi bi-people text-warning fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="text-muted mb-1">Customers</h6>
                                <h3 class="mb-0">5,678</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-info bg-opacity-10 p-3">
                                    <i class="bi bi-clock-history text-info fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="text-muted mb-1">Pending</h6>
                                <h3 class="mb-0">42</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Store Cards Section -->
        <div class="row mt-4">
            <div class="col-12">
                <h3 class="mb-3"><i class="bi bi-shop"></i> Store Overview</h3>
            </div>

            <?php
            // Demo store data
            $stores = [
                ['name' => 'Auckland CBD', 'accuracy' => 94.2, 'sales' => 8500, 'transfers' => 3],
                ['name' => 'Wellington', 'accuracy' => 91.5, 'sales' => 7200, 'transfers' => 5],
                ['name' => 'Christchurch', 'accuracy' => 88.3, 'sales' => 5800, 'transfers' => 2],
                ['name' => 'Hamilton', 'accuracy' => 85.1, 'sales' => 4200, 'transfers' => 1]
            ];

            foreach ($stores as $store): ?>
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="store-card">
                    <div class="store-card-header">
                        <h4><?= htmlspecialchars($store['name']) ?></h4>
                        <div class="store-badge">
                            <?php if ($store['accuracy'] > 90): ?>
                                <i class="bi bi-trophy-fill text-warning"></i>
                            <?php else: ?>
                                <i class="bi bi-shop"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="store-card-body">
                        <div class="store-stat">
                            <span class="store-stat-label">Stock Accuracy</span>
                            <div class="progress mt-2" style="height: 8px;">
                                <div class="progress-bar <?= $store['accuracy'] > 90 ? 'bg-success' : 'bg-warning' ?>"
                                     style="width: <?= $store['accuracy'] ?>%"></div>
                            </div>
                            <span class="store-stat-value"><?= $store['accuracy'] ?>%</span>
                        </div>
                        <div class="store-stat mt-3">
                            <span class="store-stat-label">Today's Sales</span>
                            <span class="store-stat-value">$<?= number_format($store['sales']) ?></span>
                        </div>
                        <div class="store-stat mt-2">
                            <span class="store-stat-label">Pending Transfers</span>
                            <span class="store-stat-value"><?= $store['transfers'] ?></span>
                        </div>
                    </div>
                    <div class="store-card-footer">
                        <button class="btn btn-sm btn-outline-primary w-100">
                            <i class="bi bi-eye"></i> View Details
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>

<?php
$moduleContent = ob_get_clean();

// Render with VapeUltra template
$renderer->render($moduleContent, [
    'title' => 'VapeUltra Complete Demo - CIS 2.0',
    'class' => 'page-demo-full',
    'layout' => 'main',
    'scripts' => [
        '/assets/js/dashboard.js',
    ],
    'styles' => [
        '/assets/css/dashboard-custom.css',
        '/assets/css/silver-chrome-theme.css',
        '/assets/css/store-cards-award-winning.css',
        '/assets/css/award-winning-refinements.css',
        '/assets/css/premium-dashboard-header.css',
    ]
]);
?>

<!-- Include Chat Bar Component -->
<?php
$CHAT_ENABLED = true;
include $_SERVER['DOCUMENT_ROOT'] . '/modules/base/templates/components/chat-bar.php';
?>

<style>
/* Feed View Styles */
.premium-dashboard-feed {
    display: grid;
    grid-template-columns: 280px 1fr 280px;
    gap: 24px;
    margin-top: 24px;
}

.feed-sidebar {
    position: sticky;
    top: 24px;
    height: fit-content;
}

.widget-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.widget-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}

.widget-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.quick-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: #f8f9fa;
    border: none;
    border-radius: 8px;
    text-decoration: none;
    color: #333;
    transition: all 0.2s;
}

.quick-action-btn:hover {
    background: #e9ecef;
    transform: translateY(-1px);
}

.feed-main {
    background: white;
    border-radius: 12px;
    padding: 24px;
}

.feed-header {
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid #f0f0f0;
}

.feed-header h2 {
    margin: 0 0 8px 0;
    font-size: 24px;
    font-weight: 700;
}

.feed-header p {
    margin: 0;
    color: #6b7280;
}

.activity-card {
    display: flex;
    gap: 16px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 12px;
    margin-bottom: 16px;
    transition: all 0.2s;
}

.activity-card:hover {
    background: #f0f1f3;
    transform: translateX(4px);
}

.activity-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    flex-shrink: 0;
}

.activity-content h4 {
    margin: 0 0 8px 0;
    font-size: 16px;
    font-weight: 600;
}

.activity-content p {
    margin: 0 0 12px 0;
    color: #6b7280;
    font-size: 14px;
}

.activity-meta {
    display: flex;
    gap: 16px;
    font-size: 13px;
    color: #9ca3af;
}

/* Store Cards */
.store-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: all 0.3s;
}

.store-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
}

.store-card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.store-card-header h4 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.store-badge {
    width: 36px;
    height: 36px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.store-card-body {
    padding: 20px;
}

.store-stat {
    margin-bottom: 12px;
}

.store-stat-label {
    display: block;
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 4px;
}

.store-stat-value {
    display: block;
    font-size: 20px;
    font-weight: 700;
    color: #1f2937;
}

.store-card-footer {
    padding: 16px 20px;
    border-top: 1px solid #e5e7eb;
}

/* Responsive */
@media (max-width: 1200px) {
    .premium-dashboard-feed {
        grid-template-columns: 1fr;
    }

    .feed-sidebar {
        position: relative;
    }
}
</style>

<script>
// Helper function for time ago
function time_ago(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);

    if (seconds < 60) return seconds + ' seconds ago';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ago';
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
    const days = Math.floor(hours / 24);
    return days + ' day' + (days > 1 ? 's' : '') + ' ago';
}
</script>

<?php
// Helper function for PHP time ago
function time_ago($timestamp) {
    $diff = time() - strtotime($timestamp);
    if ($diff < 60) return $diff . ' seconds ago';
    $diff = floor($diff / 60);
    if ($diff < 60) return $diff . ' minute' . ($diff > 1 ? 's' : '') . ' ago';
    $diff = floor($diff / 60);
    if ($diff < 24) return $diff . ' hour' . ($diff > 1 ? 's' : '') . ' ago';
    $diff = floor($diff / 24);
    return $diff . ' day' . ($diff > 1 ? 's' : '') . ' ago';
}
?>
