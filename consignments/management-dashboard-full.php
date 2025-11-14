<?php
declare(strict_types=1);

/**
 * 🎯 MANAGEMENT CONTROL PANEL - Full Page
 *
 * Complete transfer management system with:
 * - Real-time transfer network visualization (Google Maps)
 * - Cost analysis & profit tracking
 * - Transfer creation & approval interface
 * - Performance reports & analytics
 * - AI recommendations dashboard
 * - Live profitability alerts
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

// Manager/Admin only
if (!in_array($_SESSION['role'] ?? 'staff', ['manager', 'admin'])) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1><p>Manager access required</p>');
}

require_once __DIR__ . '/bootstrap.php';

$userID = (int)$_SESSION['userID'];
$userRole = $_SESSION['role'] ?? 'staff';

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

    if ($action === 'getTransferNetwork') {
        // Get all outlets with transfers
        $stmt = $pdo->query("
            SELECT
                o.id,
                o.outlet_name,
                o.region,
                o.city,
                o.latitude,
                o.longitude,
                COUNT(CASE WHEN c.from_outlet_id = o.id THEN 1 END) as outgoing,
                COUNT(CASE WHEN c.to_outlet_id = o.id THEN 1 END) as incoming,
                SUM(CASE WHEN c.from_outlet_id = o.id THEN c.estimated_profit ELSE 0 END) as outgoing_profit,
                SUM(CASE WHEN c.to_outlet_id = o.id THEN c.estimated_profit ELSE 0 END) as incoming_profit
            FROM outlets o
            LEFT JOIN consignments c ON (c.from_outlet_id = o.id OR c.to_outlet_id = o.id)
                AND c.status IN ('draft', 'ready', 'in_progress')
            WHERE o.status = 'active'
            GROUP BY o.id
        ");

        $outlets = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'outlets' => $outlets]);
        exit;
    }

    if ($action === 'getTransferRoutes') {
        // Get all routes between outlets
        $stmt = $pdo->query("
            SELECT
                c.id,
                c.from_outlet_id,
                c.to_outlet_id,
                o1.outlet_name as from_outlet,
                o1.latitude as from_lat,
                o1.longitude as from_lng,
                o2.outlet_name as to_outlet,
                o2.latitude as to_lat,
                o2.longitude as to_lng,
                c.transfer_type,
                c.status,
                COUNT(DISTINCT ci.id) as item_count,
                SUM(ci.quantity) as total_qty,
                c.estimated_profit,
                c.estimated_shipping_cost,
                c.created_at
            FROM consignments c
            LEFT JOIN consignment_items ci ON ci.consignment_id = c.id
            LEFT JOIN outlets o1 ON o1.id = c.from_outlet_id
            LEFT JOIN outlets o2 ON o2.id = c.to_outlet_id
            WHERE c.status IN ('draft', 'ready', 'in_progress')
            GROUP BY c.id
            ORDER BY c.created_at DESC
        ");

        $routes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'routes' => $routes]);
        exit;
    }

    if ($action === 'getMetrics') {
        $stmt = $pdo->query("
            SELECT
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft,
                COUNT(CASE WHEN status = 'ready' THEN 1 END) as ready,
                COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
                SUM(estimated_profit) as total_profit,
                SUM(estimated_shipping_cost) as total_shipping,
                AVG(estimated_profit) as avg_profit,
                COUNT(CASE WHEN estimated_profit < 10 THEN 1 END) as unprofitable
            FROM consignments
            WHERE status IN ('draft', 'ready', 'in_progress')
        ");

        $metrics = $stmt->fetch(\PDO::FETCH_ASSOC) ?? [];
        echo json_encode(['ok' => true, 'metrics' => $metrics]);
        exit;
    }

    if ($action === 'saveTransfer') {
        $data = json_decode(file_get_contents('php://input'), true);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO consignments
                (from_outlet_id, to_outlet_id, transfer_type, status, created_by_user_id, estimated_profit, estimated_shipping_cost)
                VALUES (?, ?, ?, 'draft', ?, ?, ?)
            ");

            $stmt->execute([
                $data['from_outlet_id'],
                $data['to_outlet_id'],
                $data['transfer_type'] ?? 'stock',
                $userID,
                $data['profit'] ?? 0,
                $data['shipping_cost'] ?? 0
            ]);

            echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    exit;
}

// ============================================================================
// PAGE RENDER
// ============================================================================

// Get summary stats
$stmt = $pdo->query("
    SELECT
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_count,
        SUM(estimated_profit) as total_profit,
        SUM(CASE WHEN estimated_profit < 10 THEN 1 END) as unprofitable_count
    FROM consignments
    WHERE status IN ('draft', 'ready', 'in_progress')
");

$summary = $stmt->fetch(\PDO::FETCH_ASSOC) ?? [
    'total' => 0,
    'draft_count' => 0,
    'total_profit' => 0,
    'unprofitable_count' => 0
];

// Get recent transfers
$stmt = $pdo->query("
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
    WHERE c.status IN ('draft', 'ready', 'in_progress')
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT 20
");

$recentTransfers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

// Get outlets for map
$stmt = $pdo->query("
    SELECT id, outlet_name, latitude, longitude, region
    FROM outlets
    WHERE status = 'active' AND latitude IS NOT NULL AND longitude IS NOT NULL
    ORDER BY outlet_name
");

$outlets = $stmt->fetchAll(\PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management Control Panel - Transfers</title>

    <!-- Google Maps -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCPoUkSlgFccj4lw2_5g8fgqwSjHhU4m0U&libraries=drawing,geometry"></script>

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

        .main-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .main-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
        }

        .main-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.95;
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

        .stat-card.danger { border-left-color: var(--danger); }
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.info { border-left-color: var(--info); }

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

        #map-container {
            width: 100%;
            height: 500px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin: 2rem 0;
            overflow: hidden;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 1.5rem;
            border: none;
        }

        .card-header h4 {
            margin: 0;
            font-weight: 700;
        }

        .transfer-row {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .transfer-row:hover {
            background: #f8f9fa;
        }

        .transfer-row:last-child {
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

        .btn-action {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            margin-left: 0.5rem;
            border-radius: 6px;
        }

        .alert-box {
            background: rgba(220, 53, 69, 0.1);
            border-left: 4px solid #dc3545;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        .alert-box.warning {
            background: rgba(255, 193, 7, 0.1);
            border-left-color: #ffc107;
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

        .btn-create {
            background: var(--success);
            color: white;
        }

        .btn-create:hover {
            background: #157347;
        }

        .btn-reports {
            background: var(--info);
            color: white;
        }

        .btn-reports:hover {
            background: #0ba5c7;
        }

        .container-fluid {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .grid-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .grid-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .main-header h1 {
                font-size: 1.8rem;
            }

            #map-container {
                height: 300px;
            }

            .transfer-row {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- HEADER -->
        <div class="main-header">
            <h1><i class="fas fa-chart-line"></i> Management Control Panel</h1>
            <p>Real-time transfer management, network visualization, and profitability tracking</p>
        </div>

        <!-- STATS GRID -->
        <div class="grid-stats">
            <div class="stat-card danger">
                <div class="stat-label">Active Transfers</div>
                <div class="stat-value"><?= $summary['total'] ?></div>
                <small class="text-muted">Including drafts & pending</small>
            </div>

            <div class="stat-card warning">
                <div class="stat-label">Pending Approval</div>
                <div class="stat-value"><?= $summary['draft_count'] ?></div>
                <small class="text-muted">Awaiting manager review</small>
            </div>

            <div class="stat-card success">
                <div class="stat-label">Total Profit</div>
                <div class="stat-value">$<?= number_format($summary['total_profit'] ?? 0, 0) ?></div>
                <small class="text-muted">All active transfers</small>
            </div>

            <div class="stat-card danger">
                <div class="stat-label">Unprofitable</div>
                <div class="stat-value"><?= $summary['unprofitable_count'] ?></div>
                <small class="text-muted">Below $10 margin</small>
            </div>
        </div>

        <!-- ACTION BAR -->
        <div class="action-bar">
            <button class="btn-create" onclick="window.location.href='/modules/consignments/create.php'">
                <i class="fas fa-plus"></i> Create New Transfer
            </button>
            <button class="btn-reports" onclick="window.location.href='/modules/consignments/reports.php'">
                <i class="fas fa-chart-bar"></i> View Reports
            </button>
            <button class="btn-reports" onclick="loadAIRecommendations()">
                <i class="fas fa-brain"></i> AI Recommendations
            </button>
            <button class="btn-reports" onclick="window.location.href='/modules/consignments/settings.php'">
                <i class="fas fa-cog"></i> Settings
            </button>
        </div>

        <!-- TRANSFER NETWORK MAP -->
        <div class="card">
            <div class="card-header">
                <h4>🗺️ Transfer Network Visualization</h4>
                <small>Real-time view of all inter-outlet transfers across NZ</small>
            </div>
            <div class="card-body">
                <div id="map-container"></div>
                <div id="map-legend" style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                    <strong>Legend:</strong>
                    <ul style="margin: 0.5rem 0 0 1.5rem; font-size: 0.9rem;">
                        <li><span style="color: #198754;">●</span> High Profit (>$50)</li>
                        <li><span style="color: #0d6efd;">●</span> Moderate (>$10)</li>
                        <li><span style="color: #ffc107;">●</span> Low (<$10)</li>
                        <li><span style="color: #dc3545;">●</span> Unprofitable (<$0)</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ACTIVE TRANSFERS -->
        <div class="card">
            <div class="card-header">
                <h4>📋 Active Transfers</h4>
                <small><?= count($recentTransfers) ?> transfers currently in pipeline</small>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($recentTransfers)): ?>
                    <div style="padding: 2rem; text-align: center; color: #999;">
                        <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                        <p>No active transfers</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentTransfers as $t): ?>
                        <div class="transfer-row">
                            <div style="flex: 1;">
                                <strong><?= htmlspecialchars($t['transfer_number']) ?></strong>
                                <div style="font-size: 0.85rem; color: #666; margin-top: 0.25rem;">
                                    <i class="fas fa-arrow-right"></i>
                                    <?= htmlspecialchars($t['from_outlet']) ?> →
                                    <?= htmlspecialchars($t['to_outlet']) ?>
                                </div>
                            </div>
                            <div style="flex: 0.3;">
                                <span class="status-badge status-<?= $t['status'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $t['status'])) ?>
                                </span>
                            </div>
                            <div style="flex: 0.2; text-align: right;">
                                <div style="font-weight: 700; color: <?= ($t['estimated_profit'] >= 50 ? '#198754' : ($t['estimated_profit'] >= 10 ? '#0d6efd' : '#dc3545')) ?>;">
                                    $<?= number_format($t['estimated_profit'], 0) ?>
                                </div>
                                <small style="color: #888;"><?= $t['item_count'] ?> items</small>
                            </div>
                            <div style="flex: 0.3; text-align: right;">
                                <a href="/modules/consignments/view.php?id=<?= $t['id'] ?>" class="btn-action btn-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if ($t['status'] === 'draft'): ?>
                                    <a href="/modules/consignments/approve.php?id=<?= $t['id'] ?>" class="btn-action btn-success">
                                        <i class="fas fa-check"></i> Approve
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // =========================================================================
        // GOOGLE MAPS - TRANSFER NETWORK VISUALIZATION
        // =========================================================================

        let map, markers = {}, polylines = [];
        const outlets = <?= json_encode($outlets) ?>;

        function initMap() {
            // Center on NZ
            const nzCenter = { lat: -40.4, lng: 172.9 };

            map = new google.maps.Map(document.getElementById('map-container'), {
                zoom: 6,
                center: nzCenter,
                mapTypeId: 'roadmap',
                styles: [
                    {
                        featureType: 'water',
                        stylers: [{ color: '#e0f6ff' }]
                    },
                    {
                        featureType: 'land',
                        stylers: [{ color: '#f5f5f5' }]
                    }
                ]
            });

            // Add outlet markers
            outlets.forEach(outlet => {
                if (!outlet.latitude || !outlet.longitude) return;

                const marker = new google.maps.Marker({
                    position: { lat: parseFloat(outlet.latitude), lng: parseFloat(outlet.longitude) },
                    map: map,
                    title: outlet.outlet_name,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 8,
                        fillColor: '#667eea',
                        fillOpacity: 0.8,
                        strokeColor: '#fff',
                        strokeWeight: 2
                    }
                });

                const infoWindow = new google.maps.InfoWindow({
                    content: `<div style="padding: 0.5rem; font-size: 0.9rem;">
                        <strong>${outlet.outlet_name}</strong><br>
                        <small>${outlet.region || 'Region'}</small>
                    </div>`
                });

                marker.addListener('click', () => {
                    // Close all other info windows
                    Object.values(markers).forEach(m => m.infoWindow?.close());
                    infoWindow.open(map, marker);
                });

                markers[outlet.id] = { marker, infoWindow };
            });

            // Load transfer routes
            loadTransferRoutes();
        }

        function loadTransferRoutes() {
            fetch('?ajax=1&action=getTransferRoutes')
                .then(r => r.json())
                .then(data => {
                    if (!data.ok || !data.routes) return;

                    // Clear existing polylines
                    polylines.forEach(pl => pl.setMap(null));
                    polylines = [];

                    // Add routes
                    data.routes.forEach(route => {
                        if (!route.from_lat || !route.from_lng || !route.to_lat || !route.to_lng) return;

                        // Color based on profit
                        let color = '#dc3545'; // Unprofitable
                        if (route.estimated_profit >= 50) color = '#198754'; // High profit
                        else if (route.estimated_profit >= 10) color = '#0d6efd'; // Moderate

                        const polyline = new google.maps.Polyline({
                            path: [
                                { lat: parseFloat(route.from_lat), lng: parseFloat(route.from_lng) },
                                { lat: parseFloat(route.to_lat), lng: parseFloat(route.to_lng) }
                            ],
                            geodesic: true,
                            strokeColor: color,
                            strokeOpacity: 0.7,
                            strokeWeight: 2,
                            map: map
                        });

                        polyline.addListener('click', () => {
                            alert(`${route.from_outlet} → ${route.to_outlet}\n` +
                                `${route.transfer_type} | ${route.item_count} items\n` +
                                `Profit: $${route.estimated_profit.toFixed(2)}`);
                        });

                        polylines.push(polyline);
                    });
                });
        }

        function loadAIRecommendations() {
            window.location.href = '/modules/consignments/transfers-hub.php?tab=system';
        }

        // Initialize map on page load
        window.addEventListener('load', initMap);

        // Refresh data every 30 seconds
        setInterval(loadTransferRoutes, 30000);
    </script>
</body>
</html>
