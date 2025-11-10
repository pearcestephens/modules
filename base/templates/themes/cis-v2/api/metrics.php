<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

try {
    $pdo = cisv2_pdo();
    // Aggregate metrics (simplified). Replace with proper analytics tables when available.
    $rev = tryQuery($pdo, "SELECT SUM(total_amount) as revenue_today FROM sales WHERE DATE(created_at)=CURRENT_DATE() LIMIT 1");
    $orders = tryQuery($pdo, "SELECT COUNT(*) as orders_today FROM orders WHERE DATE(created_at)=CURRENT_DATE() LIMIT 1");
    $transfers = tryQuery($pdo, "SELECT COUNT(*) as transfers_today FROM stock_transfers WHERE DATE(created_at)=CURRENT_DATE() LIMIT 1");
    $customers = tryQuery($pdo, "SELECT COUNT(*) as customers_total FROM customers LIMIT 1");
    $lowStock = tryQuery($pdo, "SELECT COUNT(*) as low_stock FROM products WHERE qty <= reorder_point");
    $inTransit = tryQuery($pdo, "SELECT COUNT(*) as in_transit FROM stock_transfers WHERE status='IN TRANSIT'");
    $storesOnline = tryQuery($pdo, "SELECT COUNT(*) as stores_online FROM outlets WHERE online_status='ONLINE'");

    respond([
        'ok' => true,
        'data' => [
            'revenue_today' => (float)($rev[0]['revenue_today'] ?? 0),
            'orders_today' => (int)($orders[0]['orders_today'] ?? 0),
            'transfers_today' => (int)($transfers[0]['transfers_today'] ?? 0),
            'customers_total' => (int)($customers[0]['customers_total'] ?? 0),
            'low_stock' => (int)($lowStock[0]['low_stock'] ?? 0),
            'in_transit' => (int)($inTransit[0]['in_transit'] ?? 0),
            'stores_online' => (int)($storesOnline[0]['stores_online'] ?? 0),
        ],
        'time' => gmdate('c')
    ]);
} catch (Throwable $e) {
    respond(['ok'=>false,'error'=>'metrics_unavailable'], 500);
}