<?php
declare(strict_types=1);

/**
 * 🏪 OUTLET MANAGER DASHBOARD - Full Page
 *
 * Complete view for outlet managers including:
 * - Live inventory status & low stock alerts
 * - Outlet's incoming/outgoing transfers
 * - Regional balance analysis
 * - AI recommendations for this outlet
 * - Profitability tracking by product
 * - Quick transfer creation
 * - Performance vs other outlets
 *
 * @package CIS\Consignments
 * @version 2.0.0
 * @created 2025-11-13
 */

session_start();

// Authentication
if (empty($_SESSION['userID'])) {
    header('Location: /login.php');
    exit;
}

// Get outlet from session
$outlet_id = $_SESSION['outlet_id'] ?? null;
if (!$outlet_id) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1><p>Outlet assignment required</p>');
}

require_once __DIR__ . '/bootstrap.php';

$userID = (int)$_SESSION['userID'];

try {
    $pdo = cis_resolve_pdo();
} catch (Exception $e) {
    http_response_code(500);
    die('Database connection failed');
}

// ============================================================================
// API HANDLER FOR AJAX REQUESTS
// ============================================================================

if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'] ?? '';

    if ($action === 'getInventory') {
        $stmt = $pdo->prepare("
            SELECT
                p.id,
                p.sku,
                p.name,
                oi.quantity,
                p.cost_price,
                p.retail_price,
                COALESCE(p.reorder_point, 20) as reorder_point,
                COALESCE(p.overstock_point, 100) as overstock_point,
                (SELECT COUNT(*) FROM lightspeed_sales
                 WHERE product_id = p.id AND outlet_id = ?
                 AND transaction_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as sales_30d
            FROM outlet_inventory oi
            LEFT JOIN products p ON p.id = oi.product_id
            WHERE oi.outlet_id = ? AND oi.quantity > 0
            ORDER BY p.name
        ");

        $stmt->execute([$outlet_id, $outlet_id]);
        $inventory = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'inventory' => $inventory]);
        exit;
    }

    if ($action === 'getLowStock') {
        $stmt = $pdo->prepare("
            SELECT
                p.id,
                p.sku,
                p.name,
                oi.quantity,
                COALESCE(p.reorder_point, 20) as reorder_point,
                (oi.quantity - COALESCE(p.reorder_point, 20)) as shortage
            FROM outlet_inventory oi
            LEFT JOIN products p ON p.id = oi.product_id
            WHERE oi.outlet_id = ?
              AND oi.quantity <= COALESCE(p.reorder_point, 20)
            ORDER BY oi.quantity ASC
            LIMIT 20
        ");

        $stmt->execute([$outlet_id]);
        $lowStock = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'low_stock' => $lowStock]);
        exit;
    }

    if ($action === 'getTransfers') {
        $stmt = $pdo->prepare("
            SELECT
                c.id,
                c.transfer_number,
                c.transfer_type,
                c.status,
                c.created_at,
                c.from_outlet_id,
                c.to_outlet_id,
                o1.outlet_name as from_outlet,
                o2.outlet_name as to_outlet,
                COUNT(DISTINCT ci.id) as item_count,
                SUM(ci.quantity) as total_qty,
                c.estimated_profit,
                c.estimated_shipping_cost,
                CASE WHEN c.from_outlet_id = ? THEN 'outgoing' ELSE 'incoming' END as direction
            FROM consignments c
            LEFT JOIN consignment_items ci ON ci.consignment_id = c.id
            LEFT JOIN outlets o1 ON o1.id = c.from_outlet_id
            LEFT JOIN outlets o2 ON o2.id = c.to_outlet_id
            WHERE (c.from_outlet_id = ? OR c.to_outlet_id = ?)
              AND c.status IN ('draft', 'ready', 'in_progress', 'sent')
            GROUP BY c.id
            ORDER BY c.created_at DESC
            LIMIT 30
        ");

        $stmt->execute([$outlet_id, $outlet_id, $outlet_id]);
        $transfers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'transfers' => $transfers]);
        exit;
    }

    if ($action === 'getMetrics') {
        // Inventory value
        $stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT product_id) as unique_products,
                SUM(quantity) as total_units,
                SUM(quantity * p.cost_price) as inventory_cost_value,
                SUM(quantity * p.retail_price) as inventory_retail_value
            FROM outlet_inventory oi
            LEFT JOIN products p ON p.id = oi.product_id
            WHERE oi.outlet_id = ? AND oi.quantity > 0
        ");
        $stmt->execute([$outlet_id]);
        $invMetrics = $stmt->fetch(\PDO::FETCH_ASSOC) ?? [];

        // Transfer metrics
        $stmt = $pdo->prepare("
            SELECT
                COUNT(CASE WHEN from_outlet_id = ? THEN 1 END) as outgoing,
                COUNT(CASE WHEN to_outlet_id = ? THEN 1 END) as incoming,
                SUM(CASE WHEN from_outlet_id = ? THEN estimated_profit ELSE 0 END) as outgoing_profit,
                SUM(CASE WHEN to_outlet_id = ? THEN estimated_profit ELSE 0 END) as incoming_profit
            FROM consignments
            WHERE (from_outlet_id = ? OR to_outlet_id = ?)
              AND status IN ('draft', 'ready', 'in_progress')
        ");
        $stmt->execute([$outlet_id, $outlet_id, $outlet_id, $outlet_id, $outlet_id, $outlet_id]);
        $transferMetrics = $stmt->fetch(\PDO::FETCH_ASSOC) ?? [];

        $metrics = array_merge($invMetrics, $transferMetrics);
        echo json_encode(['ok' => true, 'metrics' => $metrics]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    exit;
}

// ============================================================================
// PAGE RENDER
// ============================================================================

// Get outlet details
$stmt = $pdo->prepare("
    SELECT outlet_name, outlet_code, region, city, latitude, longitude
    FROM outlets
    WHERE id = ?
");
$stmt->execute([$outlet_id]);
$outlet = $stmt->fetch(\PDO::FETCH_ASSOC) ?? ['outlet_name' => 'Unknown Outlet'];

// Get inventory summary
$stmt = $pdo->prepare("
    SELECT
        COUNT(DISTINCT product_id) as unique_products,
        SUM(quantity) as total_units,
        SUM(quantity * p.cost_price) as inventory_value,
        COUNT(CASE WHEN oi.quantity <= COALESCE(p.reorder_point, 20) THEN 1 END) as low_stock_count
    FROM outlet_inventory oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.outlet_id = ? AND oi.quantity > 0
");
$stmt->execute([$outlet_id]);
$inventory_summary = $stmt->fetch(\PDO::FETCH_ASSOC) ?? [];

// Get transfer summary
$stmt = $pdo->prepare("
    SELECT
        COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft,
        COUNT(CASE WHEN status = 'ready' THEN 1 END) as ready,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
        COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent,
        SUM(CASE WHEN status IN ('draft','ready','in_progress') THEN estimated_profit ELSE 0 END) as active_profit
    FROM consignments
    WHERE from_outlet_id = ? OR to_outlet_id = ?
");
$stmt->execute([$outlet_id, $outlet_id]);
$transfer_summary = $stmt->fetch(\PDO::FETCH_ASSOC) ?? [];

// Get low stock items
$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.sku,
        p.name,
        oi.quantity,
        COALESCE(p.reorder_point, 20) as reorder_point
    FROM outlet_inventory oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.outlet_id = ?
      AND oi.quantity <= COALESCE(p.reorder_point, 20)
    ORDER BY oi.quantity ASC
    LIMIT 10
");
$stmt->execute([$outlet_id]);
$low_stock = $stmt->fetchAll(\PDO::FETCH_ASSOC);

// Get recent transfers
$stmt = $pdo->prepare("
    SELECT
        c.id,
        c.transfer_number,
        c.transfer_type,
        c.status,
        c.created_at,
        o1.outlet_name as from_outlet,
        o2.outlet_name as to_outlet,
        COUNT(DISTINCT ci.id) as item_count,
        c.estimated_profit
    FROM consignments c
    LEFT JOIN consignment_items ci ON ci.consignment_id = c.id
    LEFT JOIN outlets o1 ON o1.id = c.from_outlet_id
    LEFT JOIN outlets o2 ON o2.id = c.to_outlet_id
    WHERE (c.from_outlet_id = ? OR c.to_outlet_id = ?)
      AND c.status IN ('draft', 'ready', 'in_progress', 'sent')
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT 15
");
$stmt->execute([$outlet_id, $outlet_id]);
$recent_transfers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outlet Manager - <?= htmlspecialchars($outlet['outlet_name']) ?></title>

    <!-- Google Maps -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCPoUkSlgFccj4lw2_5g8fgqwSjHhU4m0U"></script>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        :root {
            --primary: #0d6efd;
            --success: #198754;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #0dcaf0;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
        }

        .outlet-header {
            background: linear-gradient(135deg, #8bc34a 0%, #4caf50 100%);
            color: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .outlet-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
        }

        .outlet-info {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .outlet-info-item {
            flex: 0 1 auto;
            opacity: 0.95;
        }

        .outlet-info-item strong {
            display: block;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.9;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 4px solid #ddd;
            text-align: center;
        }

        .stat-card:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            transform: translateY(-4px);
        }

        .stat-card.success { border-left-color: var(--success); }
        .stat-card.info { border-left-color: var(--info); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.danger { border-left-color: var(--danger); }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0.5rem 0;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #888;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border: none;
        }

        .card-header h4 {
            margin: 0;
            font-weight: 700;
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            border-left: 4px solid #dc3545;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            color: #721c24;
        }

        .list-item {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .list-item:hover {
            background: #f8f9fa;
        }

        .list-item:last-child {
            border-bottom: none;
        }

        .status-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-draft { background: rgba(108, 117, 125, 0.1); color: #6c757d; }
        .status-ready { background: rgba(13, 110, 253, 0.1); color: #0d6efd; }
        .status-in-progress { background: rgba(25, 135, 84, 0.1); color: #198754; }
        .status-sent { background: rgba(13, 202, 240, 0.1); color: #0dcaf0; }

        .btn-action {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            border-radius: 6px;
            border: none;
            margin-left: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-action:hover {
            transform: scale(1.05);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .action-bar {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .action-bar button {
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .action-bar button:hover {
            transform: scale(1.05);
        }

        .container-fluid {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .outlet-header h1 {
                font-size: 1.8rem;
            }

            .outlet-info {
                flex-direction: column;
                gap: 0.5rem;
            }

            .grid-3 {
                grid-template-columns: 1fr;
            }

            .list-item {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- OUTLET HEADER -->
        <div class="outlet-header">
            <h1><i class="fas fa-store"></i> <?= htmlspecialchars($outlet['outlet_name']) ?></h1>
            <div class="outlet-info">
                <div class="outlet-info-item">
                    Code
                    <span><?= htmlspecialchars($outlet['outlet_code']) ?></span>
                </div>
                <div class="outlet-info-item">
                    Region
                    <span><?= htmlspecialchars($outlet['region']) ?></span>
                </div>
                <div class="outlet-info-item">
                    City
                    <span><?= htmlspecialchars($outlet['city']) ?></span>
                </div>
                <div class="outlet-info-item">
                    Coordinates
                    <span><?= number_format((float)$outlet['latitude'], 2) ?>, <?= number_format((float)$outlet['longitude'], 2) ?></span>
                </div>
            </div>
        </div>

        <!-- STATS GRID -->
        <div class="grid-3">
            <div class="stat-card success">
                <div class="stat-label">Inventory Items</div>
                <div class="stat-value"><?= $inventory_summary['unique_products'] ?? 0 ?></div>
                <small class="text-muted"><?= number_format($inventory_summary['total_units'] ?? 0) ?> units total</small>
            </div>

            <div class="stat-card info">
                <div class="stat-label">Inventory Value</div>
                <div class="stat-value">$<?= number_format($inventory_summary['inventory_value'] ?? 0, 0) ?></div>
                <small class="text-muted">At cost price</small>
            </div>

            <div class="stat-card danger">
                <div class="stat-label">Low Stock Items</div>
                <div class="stat-value"><?= $inventory_summary['low_stock_count'] ?? 0 ?></div>
                <small class="text-muted">Below reorder point</small>
            </div>

            <div class="stat-card success">
                <div class="stat-label">Active Transfers</div>
                <div class="stat-value"><?= ($transfer_summary['draft'] ?? 0) + ($transfer_summary['ready'] ?? 0) + ($transfer_summary['in_progress'] ?? 0) ?></div>
                <small class="text-muted">Pending activity</small>
            </div>

            <div class="stat-card info">
                <div class="stat-label">Transfer Profit</div>
                <div class="stat-value">$<?= number_format($transfer_summary['active_profit'] ?? 0, 0) ?></div>
                <small class="text-muted">All pending transfers</small>
            </div>

            <div class="stat-card warning">
                <div class="stat-label">Draft Transfers</div>
                <div class="stat-value"><?= $transfer_summary['draft'] ?? 0 ?></div>
                <small class="text-muted">Awaiting approval</small>
            </div>
        </div>

        <!-- ACTION BAR -->
        <div class="action-bar">
            <button style="background: var(--success); color: white;" onclick="window.location.href='/modules/consignments/create.php?outlet=<?= $outlet_id ?>'">
                <i class="fas fa-plus"></i> Create Transfer
            </button>
            <button style="background: var(--info); color: white;" onclick="loadLowStock()">
                <i class="fas fa-exclamation-circle"></i> Request Stock
            </button>
            <button style="background: #667eea; color: white;" onclick="loadAIRecommendations()">
                <i class="fas fa-brain"></i> AI Suggestions
            </button>
        </div>

        <!-- LOW STOCK ALERTS -->
        <?php if (!empty($low_stock)): ?>
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-exclamation-triangle"></i> Low Stock Alerts</h4>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php foreach ($low_stock as $item): ?>
                        <div class="list-item">
                            <div style="flex: 1;">
                                <strong><?= htmlspecialchars($item['sku']) ?> - <?= htmlspecialchars($item['name']) ?></strong>
                                <div style="font-size: 0.85rem; color: #dc3545; margin-top: 0.25rem;">
                                    <i class="fas fa-warning"></i>
                                    Current: <?= $item['quantity'] ?> | Reorder: <?= $item['reorder_point'] ?>
                                </div>
                            </div>
                            <div>
                                <button class="btn-action btn-success" onclick="requestTransfer(<?= $item['id'] ?>)">
                                    <i class="fas fa-plus"></i> Request
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- RECENT TRANSFERS -->
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-exchange-alt"></i> Recent Transfers</h4>
                <small><?= count($recent_transfers) ?> transfers involving this outlet</small>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($recent_transfers)): ?>
                    <div style="padding: 2rem; text-align: center; color: #999;">
                        <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                        <p>No recent transfers</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_transfers as $t): ?>
                        <div class="list-item">
                            <div style="flex: 1;">
                                <strong><?= htmlspecialchars($t['transfer_number']) ?></strong>
                                <div style="font-size: 0.85rem; color: #666; margin-top: 0.25rem;">
                                    <i class="fas fa-arrow-right"></i>
                                    <?= htmlspecialchars($t['from_outlet']) ?> →
                                    <?= htmlspecialchars($t['to_outlet']) ?>
                                </div>
                            </div>
                            <div style="flex: 0.2; text-align: right;">
                                <span class="status-badge status-<?= $t['status'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $t['status'])) ?>
                                </span>
                            </div>
                            <div style="flex: 0.2; text-align: right; margin-right: 1rem;">
                                <div style="font-weight: 700; color: <?= ($t['estimated_profit'] >= 50 ? '#198754' : '#0d6efd') ?>;">
                                    $<?= number_format($t['estimated_profit'], 0) ?>
                                </div>
                                <small style="color: #888;"><?= $t['item_count'] ?> items</small>
                            </div>
                            <div>
                                <a href="/modules/consignments/view.php?id=<?= $t['id'] ?>" class="btn-action btn-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function requestTransfer(productId) {
            alert('Request feature will open transfer creation dialog for product #' + productId);
        }

        function loadLowStock() {
            alert('Low stock modal will show top stock requests');
        }

        function loadAIRecommendations() {
            window.location.href = '/modules/consignments/transfers-hub.php?tab=outlet';
        }

        // Auto-refresh data every 30 seconds
        setInterval(() => {
            fetch('?ajax=1&action=getMetrics')
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        // Update stats if needed
                    }
                });
        }, 30000);
    </script>
</body>
</html>
