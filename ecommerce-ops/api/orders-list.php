<?php
/**
 * ORDER LIST API
 * Returns sorted, filtered orders with smart priority
 */

require_once __DIR__ . '/../../app.php';
require_once __DIR__ . '/../includes/order-sorting-engine.php';

header('Content-Type: application/json');

$user_id = $_SESSION['userID'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get filters
$status = $_GET['status'] ?? 'processing';
$outlet_id = $_GET['outlet'] ?? null;
$sort_by = $_GET['sort'] ?? 'priority';

// Build query
$where_conditions = ["o.sale_status IN ('OPEN', 'LAYBY', 'ONACCOUNT')"];
$params = [];

// Status filter
if ($status === 'ready') {
    $where_conditions[] = "oss.urgency_level IN ('high', 'critical')";
    $where_conditions[] = "oss.optimal_outlet_id IS NOT NULL";
} elseif ($status === 'hold') {
    $where_conditions[] = "o.on_hold = 1";
} elseif ($status === 'processing') {
    $where_conditions[] = "o.on_hold = 0";
}

// Outlet filter
if ($outlet_id) {
    $where_conditions[] = "oss.optimal_outlet_id = ?";
    $params[] = $outlet_id;
}

// Sort clause
$sort_clause = match($sort_by) {
    'age' => 'o.created_at ASC',
    'value' => 'o.total_price DESC',
    'customer' => 'c.first_name ASC',
    default => 'oss.priority_score DESC, oss.urgency_level DESC'
};

$where_sql = implode(' AND ', $where_conditions);

$sql = "
    SELECT
        o.id,
        o.created_at,
        o.total_price as total,
        o.sale_status,
        o.on_hold,
        CONCAT(c.first_name, ' ', c.last_name) as customer_name,
        c.email as customer_email,
        oss.priority_score,
        oss.urgency_level,
        oss.fraud_risk_score,
        oss.optimal_outlet_id,
        oss.locked_by,
        vo.name as optimal_outlet_name,
        COUNT(sp.id) as items_count,
        CASE
            WHEN vi.total_available >= vi.total_needed THEN 'available'
            WHEN vi.total_available > 0 THEN 'partial'
            ELSE 'none'
        END as stock_status
    FROM vend_sales o
    LEFT JOIN vend_customers c ON o.customer_id = c.id
    LEFT JOIN order_sort_states oss ON o.id = oss.order_id AND (oss.user_id = ? OR oss.user_id IS NULL)
    LEFT JOIN vend_outlets vo ON oss.optimal_outlet_id = vo.id
    LEFT JOIN vend_sale_products sp ON o.id = sp.sale_id
    LEFT JOIN (
        SELECT
            sale_id,
            SUM(quantity) as total_needed,
            SUM(COALESCE(vi.inventory_level, 0)) as total_available
        FROM vend_sale_products sp
        LEFT JOIN vend_inventory vi ON sp.product_id = vi.product_id AND vi.outlet_id = oss.optimal_outlet_id
        GROUP BY sale_id
    ) vi ON o.id = vi.sale_id
    WHERE $where_sql
    GROUP BY o.id
    ORDER BY $sort_clause
    LIMIT 50
";

array_unshift($params, $user_id); // Add user_id as first param

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format data
    foreach ($orders as &$order) {
        $order['total'] = number_format($order['total'], 2);
        $order['priority_score'] = number_format($order['priority_score'], 0);
        $order['is_locked'] = !empty($order['locked_by']) && $order['locked_by'] != $user_id;
    }

    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'count' => count($orders)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
