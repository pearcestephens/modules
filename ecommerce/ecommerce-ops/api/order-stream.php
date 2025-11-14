<?php
/**
 * SERVER-SENT EVENTS STREAM
 * Real-time order updates via SSE
 */

require_once __DIR__ . '/../../app.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // Disable nginx buffering

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo "data: {\"error\":\"unauthorized\"}\n\n";
    exit;
}

// Get last event ID from client
$last_event_id = intval($_GET['last_event_id'] ?? 0);

// Set user session variable for triggers
$conn->exec("SET @current_user_id = $user_id");

// Keep connection alive and send updates every 2 seconds
$start_time = time();
$max_duration = 300; // 5 minutes max connection

while (time() - $start_time < $max_duration) {
    // Check for new order changes
    $stmt = $conn->prepare("
        SELECT
            oc.id as event_id,
            oc.order_id,
            oc.change_type,
            oc.old_value,
            oc.new_value,
            oc.changed_by,
            oc.changed_at,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            o.total_price
        FROM order_changes oc
        LEFT JOIN vend_sales o ON oc.order_id = o.id
        LEFT JOIN vend_customers c ON o.customer_id = c.id
        WHERE oc.id > ?
        AND oc.notified = 0
        ORDER BY oc.id ASC
        LIMIT 10
    ");

    $stmt->execute([$last_event_id]);
    $changes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($changes as $change) {
        // Send SSE event
        echo "event: order_change\n";
        echo "data: " . json_encode([
            'event_id' => $change['event_id'],
            'order_id' => $change['order_id'],
            'change_type' => $change['change_type'],
            'old_value' => $change['old_value'],
            'new_value' => $change['new_value'],
            'customer_name' => $change['customer_name'],
            'total' => $change['total_price'],
            'timestamp' => $change['changed_at']
        ]) . "\n\n";

        $last_event_id = $change['event_id'];

        // Mark as notified
        $conn->exec("UPDATE order_changes SET notified = 1 WHERE id = {$change['event_id']}");
    }

    // Send stats update every 10 seconds
    if (time() % 10 === 0) {
        $stats = get_order_stats($conn);
        echo "event: stats_update\n";
        echo "data: " . json_encode($stats) . "\n\n";
    }

    // Send heartbeat to keep connection alive
    echo ": heartbeat\n\n";

    // Flush output
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();

    // Sleep for 2 seconds
    sleep(2);

    // Check if client disconnected
    if (connection_aborted()) {
        break;
    }
}

function get_order_stats($conn) {
    $stats = $conn->query("
        SELECT
            COUNT(CASE WHEN o.sale_status IN ('OPEN', 'LAYBY') AND o.on_hold = 0 THEN 1 END) as processing,
            COUNT(CASE WHEN oss.urgency_level IN ('high', 'critical') THEN 1 END) as ready,
            COUNT(CASE WHEN oss.urgency_level = 'critical' THEN 1 END) as urgent,
            COALESCE(SUM(CASE WHEN DATE(o.created_at) = CURDATE() THEN o.total_price ELSE 0 END), 0) as revenue
        FROM vend_sales o
        LEFT JOIN order_sort_states oss ON o.id = oss.order_id
        WHERE o.sale_status IN ('OPEN', 'LAYBY', 'ONACCOUNT')
        AND o.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ")->fetch(PDO::FETCH_ASSOC);

    return $stats;
}
