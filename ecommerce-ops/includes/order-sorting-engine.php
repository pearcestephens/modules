<?php
/**
 * SMART ORDER SORTING ENGINE
 * Calculates optimal priority scores for orders
 * Runs on page load, handles concurrent users intelligently
 */

function calculate_order_priorities($conn, $user_id = null) {
    // Get all unprocessed orders that need scoring
    $sql = "
        SELECT
            o.id,
            o.created_at,
            o.total_price,
            o.customer_id,
            o.sale_status,
            c.email as customer_email,
            c.phone as customer_phone,
            COUNT(DISTINCT prev.id) as customer_order_count,
            MAX(prev.created_at) as last_order_date
        FROM vend_sales o
        LEFT JOIN vend_customers c ON o.customer_id = c.id
        LEFT JOIN vend_sales prev ON prev.customer_id = o.customer_id AND prev.id != o.id
        WHERE o.sale_status IN ('OPEN', 'LAYBY', 'ONACCOUNT')
        AND o.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY o.id
    ";

    $stmt = $conn->query($sql);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($orders as $order) {
        $score = calculate_priority_score($conn, $order);
        $urgency = calculate_urgency_level($order, $score);
        $fraud_score = calculate_fraud_risk($conn, $order);
        $optimal_outlet = suggest_optimal_outlet($conn, $order);

        // Upsert into order_sort_states
        $upsert = $conn->prepare("
            INSERT INTO order_sort_states (
                order_id, user_id, priority_score, urgency_level,
                fraud_risk_score, optimal_outlet_id, last_calculated
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                priority_score = VALUES(priority_score),
                urgency_level = VALUES(urgency_level),
                fraud_risk_score = VALUES(fraud_risk_score),
                optimal_outlet_id = VALUES(optimal_outlet_id),
                last_calculated = NOW()
        ");

        $upsert->execute([
            $order['id'],
            $user_id,
            $score,
            $urgency,
            $fraud_score,
            $optimal_outlet
        ]);
    }

    return true;
}

function calculate_priority_score($conn, $order) {
    $score = 50; // Base score

    // AGE FACTOR: Older orders get higher priority
    $age_hours = (time() - strtotime($order['created_at'])) / 3600;
    if ($age_hours > 24) $score += 30;
    elseif ($age_hours > 12) $score += 20;
    elseif ($age_hours > 6) $score += 10;
    elseif ($age_hours > 2) $score += 5;

    // VALUE FACTOR: High-value orders prioritized
    $value = floatval($order['total_price']);
    if ($value > 500) $score += 25;
    elseif ($value > 300) $score += 15;
    elseif ($value > 150) $score += 8;
    elseif ($value > 75) $score += 3;

    // CUSTOMER LOYALTY FACTOR: Repeat customers get priority
    $order_count = intval($order['customer_order_count']);
    if ($order_count > 10) $score += 15;
    elseif ($order_count > 5) $score += 10;
    elseif ($order_count > 2) $score += 5;

    // RECENT CUSTOMER FACTOR: Recent orders get slight boost
    if ($order['last_order_date']) {
        $days_since = (time() - strtotime($order['last_order_date'])) / 86400;
        if ($days_since < 30) $score += 8;
        elseif ($days_since < 90) $score += 4;
    }

    // TIME OF DAY FACTOR: Orders placed early morning get priority (customer waiting)
    $hour = intval(date('H', strtotime($order['created_at'])));
    if ($hour >= 6 && $hour <= 10) $score += 10; // Morning orders
    if ($hour >= 16 && $hour <= 18) $score += 5; // Late afternoon

    // Cap score at 100
    return min($score, 100);
}

function calculate_urgency_level($order, $priority_score) {
    $age_hours = (time() - strtotime($order['created_at'])) / 3600;

    // CRITICAL: Very old or very high priority
    if ($age_hours > 48 || $priority_score > 90) {
        return 'critical';
    }

    // HIGH: Aging or high priority
    if ($age_hours > 24 || $priority_score > 75) {
        return 'high';
    }

    // MEDIUM: Normal priority
    if ($priority_score > 50) {
        return 'medium';
    }

    // LOW: Recent, low value
    return 'low';
}

function calculate_fraud_risk($conn, $order) {
    $risk = 0;

    // Check blacklist
    $blacklist_check = $conn->prepare("
        SELECT COUNT(*) as count
        FROM ecommerce_fraud_blacklist
        WHERE (email = ? OR phone = ?)
        AND is_active = 1
    ");
    $blacklist_check->execute([$order['customer_email'], $order['customer_phone']]);
    $blacklisted = $blacklist_check->fetchColumn();

    if ($blacklisted > 0) {
        $risk += 80; // Major red flag
    }

    // First-time customer with high value
    if ($order['customer_order_count'] == 0 && $order['total_price'] > 300) {
        $risk += 30;
    }

    // Recent similar orders (potential duplicate/test)
    $recent_similar = $conn->prepare("
        SELECT COUNT(*) as count
        FROM vend_sales
        WHERE customer_id = ?
        AND id != ?
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $recent_similar->execute([$order['customer_id'], $order['id']]);
    $similar_count = $recent_similar->fetchColumn();

    if ($similar_count > 0) {
        $risk += ($similar_count * 20); // Multiple orders in short time
    }

    // Cap at 100
    return min($risk, 100);
}

function suggest_optimal_outlet($conn, $order) {
    // Get order line items
    $items = $conn->prepare("
        SELECT product_id, quantity
        FROM vend_sale_products
        WHERE sale_id = ?
    ");
    $items->execute([$order['id']]);
    $products = $items->fetchAll(PDO::FETCH_ASSOC);

    if (empty($products)) {
        return 1; // Default to warehouse
    }

    // Find outlets with ALL items in stock
    $product_ids = array_column($products, 'product_id');
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';

    $stock_check = $conn->prepare("
        SELECT
            outlet_id,
            COUNT(DISTINCT product_id) as products_available,
            SUM(inventory_level) as total_stock
        FROM vend_inventory
        WHERE product_id IN ($placeholders)
        AND inventory_level > 0
        GROUP BY outlet_id
        HAVING products_available = ?
        ORDER BY total_stock DESC
        LIMIT 1
    ");

    $params = array_merge($product_ids, [count($product_ids)]);
    $stock_check->execute($params);
    $result = $stock_check->fetch(PDO::FETCH_ASSOC);

    return $result ? $result['outlet_id'] : 1; // Default to warehouse if no match
}

// Track order locks (prevent concurrent processing)
function lock_order_for_processing($conn, $order_id, $user_id) {
    $stmt = $conn->prepare("
        UPDATE order_sort_states
        SET locked_by = ?, locked_at = NOW()
        WHERE order_id = ?
        AND (locked_by IS NULL OR locked_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE))
    ");

    $stmt->execute([$user_id, $order_id]);
    return $stmt->rowCount() > 0; // Returns true if lock acquired
}

function unlock_order($conn, $order_id, $user_id) {
    $stmt = $conn->prepare("
        UPDATE order_sort_states
        SET locked_by = NULL, locked_at = NULL
        WHERE order_id = ? AND locked_by = ?
    ");

    $stmt->execute([$order_id, $user_id]);
    return true;
}
