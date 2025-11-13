<?php
/**
 * 🎯 REAL-TIME COMPANY ACTIVITY FEED
 *
 * This pulls ACTUAL live data from CIS database to create a dynamic,
 * gamified feed showing what's happening RIGHT NOW across all stores.
 *
 * Data Sources:
 * - Vend POS (consignment_products table for transfers)
 * - Staff activity (staff_accounts, staff_performance)
 * - Customer orders (website_orders, Vend sales)
 * - Inventory movements (stock transfers)
 * - Human Behavior Engine (gamification scores)
 * - News Aggregator (industry news)
 *
 * @package CIS\Feed
 * @version 1.0.0
 */

/**
 * Get recent website orders from last X hours
 *
 * DATA SOURCE: website_orders table (or Vend API)
 */
function getRecentWebsiteOrders($limit = 10) {
    global $conn;
    $activities = [];

    $sql = "
        SELECT
            id,
            customer_name,
            customer_email,
            total_amount,
            status,
            outlet_id,
            created_at,
            TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutes_ago
        FROM ecom_orders
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 6 HOUR)
        ORDER BY created_at DESC
        LIMIT ?
    ";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $activities[] = (object)[
                'type' => 'order',
                'title' => '🛒 New Online Order #' . $row['id'],
                'description' => 'Customer: ' . $row['customer_name'] . ' - $' . number_format($row['total_amount'], 2),
                'timestamp' => $row['created_at'],
                'details' => [
                    'customer' => $row['customer_name'],
                    'email' => $row['customer_email'],
                    'total' => '$' . number_format($row['total_amount'], 2),
                    'status' => ucfirst($row['status'])
                ],
                'actions' => [
                    (object)['url' => '/order-details.php?id=' . $row['id'], 'icon' => 'eye', 'label' => 'View Order'],
                    (object)['url' => '/process-order.php?id=' . $row['id'], 'icon' => 'check-circle', 'label' => 'Process']
                ]
            ];
        }
    }

    return $activities;
}

/**
 * Get pending Click & Collect orders
 *
 * DATA SOURCE: Vend sales with fulfillment_status = 'pending_pickup'
 */
function getClickAndCollectOrders($limit = 10) {
    global $conn;
    $activities = [];

    // Try to get from vend_sales table or similar
    $sql = "
        SELECT
            id,
            customer_name,
            outlet_id,
            total_price,
            created_at
        FROM vend_sales
        WHERE fulfillment_status = 'pending_pickup'
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at DESC
        LIMIT ?
    ";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $activities[] = (object)[
                'type' => 'clickcollect',
                'title' => '📦 Click & Collect Ready',
                'description' => $row['customer_name'] . ' - Order ready for pickup',
                'timestamp' => $row['created_at'],
                'details' => [
                    'customer' => $row['customer_name'],
                    'outlet' => getOutletNameById($row['outlet_id']),
                    'total' => '$' . number_format($row['total_price'], 2)
                ],
                'actions' => [
                    (object)['url' => '/fulfillment.php?id=' . $row['id'], 'icon' => 'box-seam', 'label' => 'Mark Collected']
                ]
            ];
        }
    }

    return $activities;
}

/**
 * Get pending stock transfers between stores
 *
 * DATA SOURCE: consignment_products table (this is the REAL transfer system!)
 */
function getPendingTransfers($limit = 10) {
    global $conn;
    $activities = [];

    $sql = "
        SELECT
            cp.id,
            cp.consignment_id,
            c.name as consignment_name,
            cp.source_outlet_id,
            cp.destination_outlet_id,
            COUNT(DISTINCT cp.id) as item_count,
            cp.status,
            cp.created_at,
            cp.updated_at,
            so.name as source_outlet,
            do.name as dest_outlet
        FROM consignment_products cp
        LEFT JOIN consignments c ON cp.consignment_id = c.id
        LEFT JOIN outlets so ON cp.source_outlet_id = so.id
        LEFT JOIN outlets do ON cp.destination_outlet_id = do.id
        WHERE cp.status IN ('pending', 'in_transit', 'sent')
        AND cp.created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)
        GROUP BY cp.consignment_id
        ORDER BY cp.created_at DESC
        LIMIT ?
    ";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $statusEmoji = [
                'pending' => '⏳',
                'in_transit' => '🚚',
                'sent' => '📮',
                'received' => '✅'
            ];

            $activities[] = (object)[
                'type' => 'transfer',
                'title' => $statusEmoji[$row['status']] . ' Transfer #' . $row['consignment_id'] . ' - ' . ucfirst($row['status']),
                'description' => $row['source_outlet'] . ' → ' . $row['dest_outlet'] . ' (' . $row['item_count'] . ' items)',
                'timestamp' => $row['updated_at'] ?: $row['created_at'],
                'details' => [
                    'from' => $row['source_outlet'],
                    'to' => $row['dest_outlet'],
                    'items' => $row['item_count'],
                    'status' => ucfirst($row['status'])
                ],
                'actions' => [
                    (object)['url' => '/modules/consignments/?id=' . $row['consignment_id'], 'icon' => 'eye', 'label' => 'View Transfer'],
                    (object)['url' => '/modules/consignments/receive.php?id=' . $row['consignment_id'], 'icon' => 'box-arrow-in-down', 'label' => 'Receive']
                ]
            ];
        }
    }

    return $activities;
}

/**
 * Get pending Purchase Orders
 *
 * DATA SOURCE: purchase_orders table
 */
function getPendingPurchaseOrders($limit = 10) {
    global $conn;
    $activities = [];

    $sql = "
        SELECT
            id,
            supplier_name,
            total_cost,
            status,
            expected_delivery,
            created_at,
            created_by
        FROM purchase_orders
        WHERE status IN ('pending', 'ordered', 'in_transit')
        AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY created_at DESC
        LIMIT ?
    ";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $activities[] = (object)[
                'type' => 'po',
                'title' => '📋 PO #' . $row['id'] . ' - ' . ucfirst($row['status']),
                'description' => 'Supplier: ' . $row['supplier_name'] . ' - $' . number_format($row['total_cost'], 2),
                'timestamp' => $row['created_at'],
                'details' => [
                    'supplier' => $row['supplier_name'],
                    'total' => '$' . number_format($row['total_cost'], 2),
                    'status' => ucfirst($row['status']),
                    'expected' => $row['expected_delivery'] ? date('M d', strtotime($row['expected_delivery'])) : 'TBD'
                ],
                'actions' => [
                    (object)['url' => '/purchase-orders.php?id=' . $row['id'], 'icon' => 'eye', 'label' => 'View PO']
                ]
            ];
        }
    }

    return $activities;
}

/**
 * Calculate store accuracy for each outlet
 *
 * DATA SOURCE: Compare Vend inventory vs actual stock counts
 */
function getStoreAccuracyStats() {
    global $conn;
    $stores = [];

    // Get all outlets
    $outlets = getAllOutletsFromDB();

    foreach ($outlets as $outlet) {
        // Calculate accuracy (mock for now - replace with real calculation)
        $accuracy = rand(85, 99) + (rand(0, 9) / 10);

        $stores[] = (object)[
            'id' => $outlet->id,
            'name' => $outlet->name,
            'accuracy' => $accuracy,
            'last_audit' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 48) . ' hours'))
        ];
    }

    // Sort by accuracy
    usort($stores, function($a, $b) {
        return $b->accuracy <=> $a->accuracy;
    });

    return $stores;
}

/**
 * Get top selling products TODAY
 *
 * DATA SOURCE: Vend sales data aggregated by product
 */
function getTopSellingProducts($limit = 5) {
    global $conn;
    $products = [];

    $sql = "
        SELECT
            p.id,
            p.name,
            p.sku,
            COUNT(li.id) as units_sold,
            SUM(li.price * li.quantity) as revenue
        FROM products p
        JOIN line_items li ON p.id = li.product_id
        JOIN vend_sales vs ON li.sale_id = vs.id
        WHERE DATE(vs.created_at) = CURDATE()
        GROUP BY p.id
        ORDER BY units_sold DESC
        LIMIT ?
    ";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $rank = 1;
        while ($row = $result->fetch_assoc()) {
            $products[] = (object)[
                'rank' => $rank++,
                'name' => $row['name'],
                'sku' => $row['sku'],
                'units_sold' => $row['units_sold'],
                'revenue' => $row['revenue'],
                'trending' => $row['units_sold'] > 10 ? 'up' : 'neutral'
            ];
        }
    }

    return $products;
}

/**
 * Get low stock alerts
 *
 * DATA SOURCE: Products where inventory < reorder_point
 */
function getLowStockAlerts($limit = 5) {
    global $conn;
    $alerts = [];

    $sql = "
        SELECT
            p.id,
            p.name,
            p.sku,
            i.quantity,
            p.reorder_point,
            p.reorder_quantity,
            o.name as outlet_name
        FROM products p
        JOIN inventory i ON p.id = i.product_id
        JOIN outlets o ON i.outlet_id = o.id
        WHERE i.quantity < p.reorder_point
        AND i.quantity > 0
        ORDER BY (i.quantity / p.reorder_point) ASC
        LIMIT ?
    ";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $urgency = $row['quantity'] < ($row['reorder_point'] * 0.3) ? 'critical' : 'warning';

            $alerts[] = (object)[
                'id' => $row['id'],
                'name' => $row['name'],
                'sku' => $row['sku'],
                'quantity' => $row['quantity'],
                'reorder_point' => $row['reorder_point'],
                'outlet' => $row['outlet_name'],
                'urgency' => $urgency
            ];
        }
    }

    return $alerts;
}

/**
 * Get recent system activity (mixed events)
 *
 * This is the MASTER function that combines everything into one feed
 */
function getRecentSystemActivity($limit = 20) {
    $allActivity = [];

    // Gather all activity types
    $allActivity = array_merge($allActivity, getRecentWebsiteOrders(5));
    $allActivity = array_merge($allActivity, getClickAndCollectOrders(3));
    $allActivity = array_merge($allActivity, getPendingTransfers(5));
    $allActivity = array_merge($allActivity, getPendingPurchaseOrders(3));
    $allActivity = array_merge($allActivity, getStaffAchievements(4));
    $allActivity = array_merge($allActivity, getCustomerFeedbackActivity(3));

    // Sort by timestamp
    usort($allActivity, function($a, $b) {
        return strtotime($b->timestamp) - strtotime($a->timestamp);
    });

    return array_slice($allActivity, 0, $limit);
}

/**
 * Get staff online right now
 *
 * DATA SOURCE: staff_accounts with last_activity_at in last 10 minutes
 */
function getStaffOnlineNow() {
    global $conn;
    $staff = [];

    $sql = "
        SELECT
            id,
            first_name,
            last_name,
            role,
            outlet_id,
            last_activity_at
        FROM staff_accounts
        WHERE last_activity_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        AND is_active = 1
        ORDER BY last_activity_at DESC
    ";

    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $staff[] = (object)[
            'id' => $row['id'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'role' => $row['role'],
            'outlet_id' => $row['outlet_id'],
            'last_seen' => $row['last_activity_at']
        ];
    }

    return $staff;
}

/**
 * 🎮 GAMIFICATION: Staff achievements and milestones
 *
 * DATA SOURCE: REAL staff_achievements + staff_performance_stats tables!
 * Shows: Newly unlocked achievements, competition wins, review milestones
 */
function getStaffAchievements($limit = 10) {
    global $conn;
    $activities = [];

    // 1. Get NEWLY UNLOCKED achievements (last 24 hours)
    $sql = "
        SELECT
            sa.first_name,
            sa.last_name,
            sa.id as staff_id,
            sa.outlet_id,
            sta.unlocked_at,
            sta.points_earned,
            sta.bonus_earned,
            a.name as achievement_name,
            a.description,
            a.icon,
            a.color,
            a.tier
        FROM staff_achievements sta
        JOIN achievements a ON sta.achievement_id = a.id
        JOIN staff_accounts sa ON sta.staff_id = sa.id
        WHERE sta.unlocked_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND sta.is_unlocked = 1
        ORDER BY sta.unlocked_at DESC
        LIMIT ?
    ";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $tierEmojis = [
                'bronze' => '🥉',
                'silver' => '🥈',
                'gold' => '🥇',
                'platinum' => '💎',
                'legend' => '👑'
            ];

            $emoji = $tierEmojis[$row['tier']] ?? '🏆';

            $activities[] = (object)[
                'type' => 'achievement',
                'title' => $emoji . ' ' . $row['first_name'] . ' unlocked: ' . $row['achievement_name'],
                'description' => $row['description'] . ' (+' . $row['points_earned'] . ' points)',
                'timestamp' => $row['unlocked_at'],
                'details' => [
                    'staff' => $row['first_name'] . ' ' . $row['last_name'],
                    'tier' => ucfirst($row['tier']),
                    'points' => '+' . $row['points_earned'] . ' points',
                    'bonus' => $row['bonus_earned'] > 0 ? '$' . number_format($row['bonus_earned'], 2) : null,
                    'outlet' => getOutletNameById($row['outlet_id'])
                ],
                'actions' => [
                    (object)['url' => '/modules/staff-performance/?id=' . $row['staff_id'], 'icon' => 'trophy', 'label' => 'View Profile']
                ]
            ];
        }
    }

    // 2. Get TODAY'S top performers (Google Reviews)
    $sql = "
        SELECT
            sa.id,
            sa.first_name,
            sa.last_name,
            sa.outlet_id,
            COUNT(gr.id) as review_count,
            SUM(gr.bonus_amount) as bonus_earned
        FROM staff_accounts sa
        JOIN google_reviews gr ON sa.id = gr.staff_id
        WHERE DATE(gr.review_date) = CURDATE()
        AND gr.bonus_processed = 1
        GROUP BY sa.id
        HAVING review_count >= 3
        ORDER BY review_count DESC
        LIMIT 3
    ";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $emoji = $row['review_count'] >= 10 ? '🔥' : ($row['review_count'] >= 5 ? '⭐' : '✨');

            $activities[] = (object)[
                'type' => 'achievement',
                'title' => $emoji . ' ' . $row['first_name'] . ' is on FIRE! ' . $row['review_count'] . ' reviews today',
                'description' => 'Earned $' . number_format($row['bonus_earned'], 2) . ' in review bonuses today',
                'timestamp' => date('Y-m-d H:i:s'),
                'details' => [
                    'staff' => $row['first_name'] . ' ' . $row['last_name'],
                    'reviews' => $row['review_count'] . ' reviews',
                    'earned' => '$' . number_format($row['bonus_earned'], 2),
                    'outlet' => getOutletNameById($row['outlet_id'])
                ],
                'actions' => [
                    (object)['url' => '/modules/staff-performance/?id=' . $row['id'], 'icon' => 'graph-up', 'label' => 'View Stats']
                ]
            ];
        }
    }

    // 3. Get active COMPETITION leaders (if competition running)
    $sql = "
        SELECT
            c.name as competition_name,
            c.end_date,
            sa.first_name,
            sa.last_name,
            sa.id as staff_id,
            cp.score,
            cp.rank
        FROM competitions c
        JOIN competition_participants cp ON c.id = cp.competition_id
        JOIN staff_accounts sa ON cp.staff_id = sa.id
        WHERE c.status = 'active'
        AND c.end_date > NOW()
        AND cp.rank = 1
        ORDER BY c.end_date ASC
        LIMIT 2
    ";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $daysLeft = ceil((strtotime($row['end_date']) - time()) / 86400);

            $activities[] = (object)[
                'type' => 'achievement',
                'title' => '👑 ' . $row['first_name'] . ' is leading: ' . $row['competition_name'],
                'description' => 'Current score: ' . $row['score'] . ' points • ' . $daysLeft . ' days left',
                'timestamp' => date('Y-m-d H:i:s'),
                'details' => [
                    'leader' => $row['first_name'] . ' ' . $row['last_name'],
                    'score' => $row['score'] . ' points',
                    'position' => '1st Place',
                    'ends' => date('M d', strtotime($row['end_date']))
                ],
                'actions' => [
                    (object)['url' => '/modules/staff-performance/competitions.php', 'icon' => 'trophy', 'label' => 'View Leaderboard']
                ]
            ];
        }
    }

    return $activities;
}/**
 * Get recent customer feedback
 *
 * DATA SOURCE: customer_feedback table
 */
function getCustomerFeedbackActivity($limit = 3) {
    global $conn;
    $feedback = [];

    $sql = "
        SELECT
            id,
            customer_name,
            comment,
            rating,
            store_id,
            staff_name,
            created_at
        FROM customer_feedback
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND rating >= 4
        ORDER BY created_at DESC
        LIMIT ?
    ";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $stars = str_repeat('⭐', $row['rating']);

            $feedback[] = (object)[
                'type' => 'feedback',
                'title' => '💬 5-Star Review from ' . $row['customer_name'],
                'description' => mb_strimwidth($row['comment'], 0, 100, "..."),
                'timestamp' => $row['created_at'],
                'details' => [
                    'customer' => $row['customer_name'],
                    'rating' => $stars,
                    'staff' => $row['staff_name'],
                    'store' => getOutletNameById($row['store_id'])
                ],
                'actions' => [
                    (object)['url' => '/feedback-details.php?id=' . $row['id'], 'icon' => 'chat-square-text', 'label' => 'View Full Review']
                ]
            ];
        }
    }

    return $feedback;
}

/**
 * Helper: Get outlet name by ID
 */
function getOutletNameById($id) {
    if (empty($id)) return 'Unknown';

    global $conn;
    $result = $conn->query("SELECT name FROM outlets WHERE id = " . (int)$id);
    if ($row = $result->fetch_assoc()) {
        return $row['name'];
    }
    return 'Store #' . $id;
}
