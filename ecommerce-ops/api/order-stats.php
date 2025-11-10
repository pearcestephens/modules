<?php
/**
 * ORDER STATS API
 * Returns current order statistics
 */

require_once __DIR__ . '/../../app.php';

header('Content-Type: application/json');

$user_id = $_SESSION['userID'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $stats = $conn->query("
        SELECT
            COUNT(CASE WHEN o.sale_status IN ('OPEN', 'LAYBY') AND o.on_hold = 0 THEN 1 END) as processing,
            COUNT(CASE WHEN oss.urgency_level IN ('high', 'critical') AND oss.optimal_outlet_id IS NOT NULL THEN 1 END) as ready,
            COUNT(CASE WHEN oss.urgency_level = 'critical' THEN 1 END) as urgent,
            COALESCE(SUM(CASE WHEN DATE(o.created_at) = CURDATE() THEN o.total_price ELSE 0 END), 0) as revenue,
            COUNT(CASE WHEN o.on_hold = 1 THEN 1 END) as on_hold,
            COUNT(CASE WHEN oss.fraud_risk_score > 60 THEN 1 END) as fraud_flags
        FROM vend_sales o
        LEFT JOIN order_sort_states oss ON o.id = oss.order_id
        WHERE o.sale_status IN ('OPEN', 'LAYBY', 'ONACCOUNT')
        AND o.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ")->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'processing' => intval($stats['processing']),
        'ready' => intval($stats['ready']),
        'urgent' => intval($stats['urgent']),
        'revenue' => number_format($stats['revenue'], 2),
        'on_hold' => intval($stats['on_hold']),
        'fraud_flags' => intval($stats['fraud_flags'])
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
