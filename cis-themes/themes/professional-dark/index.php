<?php
/**
 * CIS Theme System - Demo & Test Page
 * Professional Dark Theme - All 3 Layouts
 */

// Load core files (paths relative to /modules/cis-themes/themes/professional-dark)
require_once __DIR__ . '/../../engine/ThemeEngine.php';
require_once __DIR__ . '/../../data/MockData.php';
require_once __DIR__ . '/../../data/NotificationData.php';

// Initialize theme engine
$theme = \CIS\Themes\ThemeEngine::getInstance();
$theme->switchTheme('professional-dark');

// Get mock data
$stores = \CIS\Themes\Data\MockData::getStores();
$products = \CIS\Themes\Data\MockData::getProducts();
$orders = \CIS\Themes\Data\MockData::getOrders();
$newsFeed = \CIS\Themes\Data\MockData::getNewsFeed();
$metrics = \CIS\Themes\Data\MockData::getMetrics();
$salesChart = \CIS\Themes\Data\MockData::getSalesChart();
$activities = \CIS\Themes\Data\MockData::getActivities();

// Get notification & messaging data
$notifications = \CIS\Themes\NotificationData::getNotifications();
$directMessages = \CIS\Themes\NotificationData::getMessages();
$chatRooms = \CIS\Themes\NotificationData::getChatRooms();
$onlineUsers = \CIS\Themes\NotificationData::getOnlineUsers();
$unreadNotifications = \CIS\Themes\NotificationData::getUnreadCount();
$unreadMessages = \CIS\Themes\NotificationData::getUnreadMessageCount();
$chatMessages = \CIS\Themes\NotificationData::getChatMessages(1); // Default to "All Staff" room

// Determine which layout to show
$layout = $_GET['layout'] ?? 'facebook-feed';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIS Theme System - Professional Dark Demo</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .demo-switcher {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #1e293b;
            padding: 16px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            z-index: 9999;
            border: 1px solid #334155;
        }
        .demo-switcher h4 {
            margin: 0 0 12px 0;
            color: #f1f5f9;
            font-size: 14px;
            font-weight: 600;
        }
        .demo-switcher a {
            display: block;
            padding: 8px 12px;
            margin: 4px 0;
            background: #0f172a;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.2s ease;
            border: 1px solid #334155;
        }
        .demo-switcher a:hover {
            background: #334155;
            color: #f1f5f9;
            transform: translateX(-4px);
        }
        .demo-switcher a.active {
            background: #0ea5e9;
            color: white;
            border-color: #0ea5e9;
        }
        .demo-info {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(30, 41, 59, 0.95);
            padding: 12px 16px;
            border-radius: 8px;
            color: #0ea5e9;
            font-size: 13px;
            font-weight: 600;
            z-index: 9998;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(14, 165, 233, 0.3);
        }
    </style>
</head>
<body>
    <!-- Demo Info Badge -->
    <div class="demo-info">
        🎨 DEMO MODE: Professional Dark Theme
    </div>

    <!-- Layout Switcher -->
    <div class="demo-switcher">
        <h4>📱 Switch Layout:</h4>
        <a href="?layout=facebook-feed" class="<?php echo $layout === 'facebook-feed' ? 'active' : ''; ?>">
            📰 Facebook Feed
        </a>
        <a href="?layout=card-grid" class="<?php echo $layout === 'card-grid' ? 'active' : ''; ?>">
            🎴 Card Grid (Products)
        </a>
        <a href="?layout=store-outlet" class="<?php echo $layout === 'store-outlet' ? 'active' : ''; ?>">
            🏪 Store Outlets
        </a>
        <a href="?layout=messaging" class="<?php echo $layout === 'messaging' ? 'active' : ''; ?>">
            💬 Messaging & Chat
        </a>
    </div>

    <?php
    // Render the selected layout
    switch ($layout) {
        case 'card-grid':
            include __DIR__ . '/views/card-grid.php';
            break;
        case 'store-outlet':
            include __DIR__ . '/views/store-outlet.php';
            break;
        case 'messaging':
            include __DIR__ . '/views/messaging.php';
            break;
            break;
        case 'facebook-feed':
        default:
            include __DIR__ . '/views/facebook-feed.php';
            break;
    }
    ?>
</body>
</html>
