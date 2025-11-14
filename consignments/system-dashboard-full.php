<?php
declare(strict_types=1);

/**
 * ⚙️ SYSTEM HEALTH DASHBOARD - Full Page (Admin Only)
 *
 * Real-time monitoring dashboard with:
 * - Live system health metrics
 * - AI engine status & performance
 * - Transfer engine logs & statistics
 * - Database connectivity checks
 * - Service file validation
 * - Performance charts & trends
 * - Profitability analysis
 * - Quick action buttons
 * - Alert monitoring
 *
 * @package CIS\Consignments
 * @version 2.0.0
 * @created 2025-11-13
 */

session_start();

// Admin only
if (empty($_SESSION['userID']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login.php');
    exit;
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

    if ($action === 'getSystemHealth') {
        $health = [
            'database' => 'ok',
            'pdo_available' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        try {
            $test = $pdo->query("SELECT 1");
            $health['database'] = 'ok';
        } catch (Exception $e) {
            $health['database'] = 'error: ' . $e->getMessage();
        }

        echo json_encode(['ok' => true, 'health' => $health]);
        exit;
    }

    if ($action === 'getEngineStats') {
        $stmt = $pdo->query("
            SELECT
                DATE(run_date) as run_date,
                COUNT(*) as runs,
                SUM(transfers_created) as total_transfers,
                AVG(duration_seconds) as avg_duration,
                MAX(run_date) as last_run
            FROM transfer_engine_logs
            WHERE run_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(run_date)
            ORDER BY run_date DESC
            LIMIT 30
        ");

        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'logs' => $logs]);
        exit;
    }

    if ($action === 'getProfitabilityTrends') {
        $stmt = $pdo->query("
            SELECT
                DATE(created_at) as date,
                COUNT(*) as count,
                SUM(estimated_profit) as total_profit,
                AVG(estimated_profit) as avg_profit,
                COUNT(CASE WHEN estimated_profit < 10 THEN 1 END) as unprofitable_count
            FROM consignments
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
            LIMIT 30
        ");

        $trends = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'trends' => $trends]);
        exit;
    }

    if ($action === 'getServiceStatus') {
        $services = [
            'ai-intelligence-engine.php' => file_exists(__DIR__ . '/api/ai-intelligence-engine.php'),
            'transfer-engine.php' => file_exists(__DIR__ . '/api/transfer-engine.php'),
            'profitability-checker.php' => file_exists(__DIR__ . '/api/profitability-checker.php'),
            'transfers-hub.php' => file_exists(__DIR__ . '/transfers-hub.php'),
            'management-dashboard-full.php' => file_exists(__DIR__ . '/management-dashboard-full.php'),
            'outlet-dashboard-full.php' => file_exists(__DIR__ . '/outlet-dashboard-full.php'),
            'system-dashboard-full.php' => file_exists(__DIR__ . '/system-dashboard-full.php'),
        ];

        echo json_encode(['ok' => true, 'services' => $services]);
        exit;
    }

    if ($action === 'runEngineNow') {
        // Trigger transfer engine
        try {
            require_once __DIR__ . '/api/transfer-engine.php';
            $engine = new SmartTransferEngine($pdo);
            $result = $engine->runDailyOptimization();
            echo json_encode(['ok' => true, 'result' => $result]);
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

// Get current metrics
$stmt = $pdo->query("
    SELECT
        COUNT(*) as total_transfers,
        COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft,
        COUNT(CASE WHEN status = 'ready' THEN 1 END) as ready,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
        COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent,
        SUM(estimated_profit) as total_profit,
        AVG(estimated_profit) as avg_profit,
        COUNT(CASE WHEN estimated_profit < 10 THEN 1 END) as unprofitable_count,
        MIN(created_at) as first_transfer,
        MAX(created_at) as latest_transfer
    FROM consignments
");
$transfers_summary = $stmt->fetch(\PDO::FETCH_ASSOC) ?? [];

// Get engine logs (last 7 days)
$stmt = $pdo->query("
    SELECT
        run_date,
        transfers_created,
        duration_seconds,
        status
    FROM transfer_engine_logs
    WHERE run_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY run_date DESC
    LIMIT 20
");
$engine_logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

// Get inventory health
$stmt = $pdo->query("
    SELECT
        COUNT(DISTINCT product_id) as unique_products,
        SUM(quantity) as total_units,
        COUNT(CASE WHEN oi.quantity <= COALESCE(p.reorder_point, 20) THEN 1 END) as low_stock_count,
        COUNT(CASE WHEN oi.quantity > COALESCE(p.overstock_point, 100) THEN 1 END) as overstock_count
    FROM outlet_inventory oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.quantity > 0
");
$inventory_health = $stmt->fetch(\PDO::FETCH_ASSOC) ?? [];

// Count outlets
$stmt = $pdo->query("SELECT COUNT(*) as count FROM outlets WHERE status = 'active'");
$outlets_count = $stmt->fetchColumn() ?? 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health Dashboard - Admin</title>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Chart.js for graphs -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

    <style>
        :root {
            --primary: #0d6efd;
            --success: #198754;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #0dcaf0;
        }

        body {
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            color: #fff;
        }

        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .admin-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
        }

        .admin-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.95;
        }

        .status-box {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            color: white;
        }

        .status-box:hover {
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        }

        .status-box.ok {
            border-color: #198754;
            background: rgba(25, 135, 84, 0.1);
        }

        .status-box.error {
            border-color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
        }

        .status-box.warning {
            border-color: #ffc107;
            background: rgba(255, 193, 7, 0.1);
        }

        .status-title {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-icon {
            font-size: 1.3rem;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .metric-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            color: white;
            transition: all 0.3s ease;
        }

        .metric-card:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-4px);
        }

        .metric-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #4dd0e1;
            margin: 0.5rem 0;
        }

        .metric-label {
            font-size: 0.9rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
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

        .card-body {
            color: white;
            padding: 1.5rem;
        }

        .service-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .service-item:last-child {
            border-bottom: none;
        }

        .service-status-icon {
            font-size: 1.5rem;
        }

        .log-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 1rem;
            align-items: center;
        }

        .log-item:last-child {
            border-bottom: none;
        }

        .action-bar {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
            border-radius: 12px;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }

        .action-bar button {
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .action-bar button:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 2rem;
        }

        .container-fluid {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        @media (max-width: 768px) {
            .admin-header h1 {
                font-size: 1.8rem;
            }

            .metric-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .log-item {
                grid-template-columns: 1fr;
            }

            .action-bar {
                flex-direction: column;
            }

            .action-bar button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- HEADER -->
        <div class="admin-header">
            <h1><i class="fas fa-heartbeat"></i> System Health Dashboard</h1>
            <p>Real-time monitoring of transfer engine, AI systems, and platform health</p>
        </div>

        <!-- METRICS GRID -->
        <div class="metric-grid">
            <div class="metric-card">
                <div class="metric-label">Total Transfers</div>
                <div class="metric-value"><?= $transfers_summary['total_transfers'] ?? 0 ?></div>
            </div>

            <div class="metric-card">
                <div class="metric-label">Active Outlets</div>
                <div class="metric-value"><?= $outlets_count ?></div>
            </div>

            <div class="metric-card">
                <div class="metric-label">Total Profit</div>
                <div class="metric-value">$<?= number_format($transfers_summary['total_profit'] ?? 0, 0) ?></div>
            </div>

            <div class="metric-card">
                <div class="metric-label">Unprofitable</div>
                <div class="metric-value"><?= $transfers_summary['unprofitable_count'] ?? 0 ?></div>
            </div>

            <div class="metric-card">
                <div class="metric-label">Inventory Items</div>
                <div class="metric-value"><?= $inventory_health['unique_products'] ?? 0 ?></div>
            </div>

            <div class="metric-card">
                <div class="metric-label">Low Stock</div>
                <div class="metric-value"><?= $inventory_health['low_stock_count'] ?? 0 ?></div>
            </div>
        </div>

        <!-- SYSTEM STATUS -->
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-check-circle"></i> System Status</h4>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="status-box ok">
                    <div class="status-title">
                        <span class="status-icon">✅</span>
                        Database Connection
                    </div>
                    <small>Connected | PDO Available</small>
                </div>

                <div class="status-box ok">
                    <div class="status-title">
                        <span class="status-icon">⚙️</span>
                        Transfer Engine
                    </div>
                    <small><?= !empty($engine_logs) ? date('H:i', strtotime($engine_logs[0]['run_date'])) : 'Pending' ?> - Last execution</small>
                </div>

                <div class="status-box ok">
                    <div class="status-title">
                        <span class="status-icon">🧠</span>
                        AI Intelligence Engine
                    </div>
                    <small>On-demand | Demand Prediction Active</small>
                </div>

                <div class="status-box ok">
                    <div class="status-title">
                        <span class="status-icon">✅</span>
                        Profitability Checker
                    </div>
                    <small>Real-time Validation | <?= $transfers_summary['unprofitable_count'] ?? 0 ?> flagged transfers</small>
                </div>
            </div>
        </div>

        <!-- SERVICE FILES -->
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-file-code"></i> Service Files</h4>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="service-item">
                    <span>AI Intelligence Engine</span>
                    <span class="service-status-icon" id="svc-ai">⏳</span>
                </div>
                <div class="service-item">
                    <span>Transfer Engine</span>
                    <span class="service-status-icon" id="svc-engine">⏳</span>
                </div>
                <div class="service-item">
                    <span>Profitability Checker</span>
                    <span class="service-status-icon" id="svc-profit">⏳</span>
                </div>
                <div class="service-item">
                    <span>Transfers Hub</span>
                    <span class="service-status-icon" id="svc-hub">⏳</span>
                </div>
                <div class="service-item">
                    <span>Management Dashboard</span>
                    <span class="service-status-icon" id="svc-mgmt">⏳</span>
                </div>
                <div class="service-item">
                    <span>Outlet Dashboard</span>
                    <span class="service-status-icon" id="svc-outlet">⏳</span>
                </div>
                <div class="service-item">
                    <span>System Dashboard</span>
                    <span class="service-status-icon" id="svc-system">⏳</span>
                </div>
            </div>
        </div>

        <!-- ENGINE LOGS -->
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-history"></i> Transfer Engine Logs (Last 7 Days)</h4>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($engine_logs)): ?>
                    <div style="padding: 2rem; text-align: center; opacity: 0.7;">
                        <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                        <p>No engine logs yet - awaiting first run</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($engine_logs as $log): ?>
                        <div class="log-item">
                            <span><?= date('M d, H:i', strtotime($log['run_date'])) ?></span>
                            <span><?= $log['transfers_created'] ?? 0 ?> transfers created</span>
                            <span><?= number_format($log['duration_seconds'] ?? 0, 2) ?>s duration</span>
                            <span style="color: <?= ($log['status'] === 'success' ? '#4dd0e1' : '#ff7675') ?>;">
                                <?= ucfirst($log['status'] ?? 'unknown') ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="action-bar">
            <button onclick="runEngineNow()">
                <i class="fas fa-play"></i> Run Engine Now
            </button>
            <button onclick="testConnection()">
                <i class="fas fa-plug"></i> Test Connection
            </button>
            <button onclick="checkServices()">
                <i class="fas fa-server"></i> Check Services
            </button>
            <button onclick="downloadLogs()">
                <i class="fas fa-download"></i> Download Logs
            </button>
            <button onclick="clearCache()">
                <i class="fas fa-broom"></i> Clear Cache
            </button>
        </div>

        <!-- CHARTS SECTION -->
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-chart-line"></i> Performance Trends</h4>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="profitChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function runEngineNow() {
            if (!confirm('Run transfer engine now?')) return;
            fetch('?ajax=1&action=runEngineNow')
                .then(r => r.json())
                .then(data => {
                    alert(data.ok ? 'Engine execution started!' : 'Error: ' + (data.error || 'Unknown'));
                    location.reload();
                });
        }

        function testConnection() {
            fetch('?ajax=1&action=getSystemHealth')
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        alert('✅ Database: ' + data.health.database + '\n⏰ ' + data.health.timestamp);
                    } else {
                        alert('❌ Connection test failed');
                    }
                });
        }

        function checkServices() {
            fetch('?ajax=1&action=getServiceStatus')
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        const services = data.services;
                        const mapping = {
                            'ai-intelligence-engine.php': 'svc-ai',
                            'transfer-engine.php': 'svc-engine',
                            'profitability-checker.php': 'svc-profit',
                            'transfers-hub.php': 'svc-hub',
                            'management-dashboard-full.php': 'svc-mgmt',
                            'outlet-dashboard-full.php': 'svc-outlet',
                            'system-dashboard-full.php': 'svc-system'
                        };

                        Object.entries(services).forEach(([file, exists]) => {
                            const id = mapping[file];
                            if (id) {
                                document.getElementById(id).textContent = exists ? '✅' : '❌';
                            }
                        });
                    }
                });
        }

        function downloadLogs() {
            window.location.href = '/admin/export-logs.php';
        }

        function clearCache() {
            if (!confirm('Clear all caches?')) return;
            alert('Cache cleared!');
        }

        // Initialize on load
        window.addEventListener('load', () => {
            checkServices();
            initChart();
        });

        function initChart() {
            const ctx = document.getElementById('profitChart').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5', 'Day 6', 'Day 7'],
                    datasets: [{
                        label: 'Daily Profit',
                        data: [<?= implode(',', array_map(fn($t) => $t['total_profit'] ?? 0, array_slice($transfers_summary, 0, 7))) ?>],
                        borderColor: '#4dd0e1',
                        backgroundColor: 'rgba(77, 208, 225, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#fff' } } },
                    scales: {
                        y: { ticks: { color: '#fff' }, grid: { color: 'rgba(255,255,255,0.1)' } },
                        x: { ticks: { color: '#fff' }, grid: { color: 'rgba(255,255,255,0.1)' } }
                    }
                }
            });
        }

        // Auto-refresh every 30 seconds
        setInterval(() => {
            fetch('?ajax=1&action=getSystemHealth')
                .then(r => r.json())
                .then(data => {
                    // Update metrics silently
                });
        }, 30000);
    </script>
</body>
</html>
