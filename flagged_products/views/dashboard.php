<?php
/**
 * Flagged Products - Store Management Dashboard
 * 
 * Manager dashboard showing:
 * - Multi-store comparison
 * - Historical trends with Chart.js
 * - Team performance analytics
 * - Pattern detection
 * 
 * @version 2.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/CISLogger.php';
require_once __DIR__ . '/../models/FlaggedProductsRepository.php';

if (!isset($_SESSION['userID'])) {
    header('Location: /login.php');
    exit;
}

// Check if user has manager permissions
$isManager = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager', 'supervisor']);
if (!$isManager) {
    die("<div class='alert alert-danger m-5'><h3>Access Denied</h3><p>This dashboard is only available to managers and administrators.</p></div>");
}

// Get all outlets for multi-store comparison
$allOutlets = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM vend_outlets WHERE deleted_at IS NULL ORDER BY name");
    $allOutlets = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    CISLogger::error('flagged_products', 'Failed to get outlets: ' . $e->getMessage());
}

// Get date range from filters (default: last 30 days)
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$selectedOutlet = $_GET['outlet_id'] ?? 'all';

// Get comprehensive analytics
$multiStoreStats = [];
foreach ($allOutlets as $outlet) {
    $stats = FlaggedProductsRepository::getStoreStats($outlet->id, $startDate, $endDate);
    $multiStoreStats[] = [
        'outlet_id' => $outlet->id,
        'outlet_name' => $outlet->name,
        'stats' => $stats
    ];
}

// Get historical trend data for charts
$trendData = FlaggedProductsRepository::getHistoricalTrends($selectedOutlet, $startDate, $endDate);

// Get top performers
$topPerformers = FlaggedProductsRepository::getLeaderboard('all_time', 20);

// Get violation patterns
$violationStats = [];
try {
    $sql = "SELECT 
                violation_type,
                COUNT(*) as count,
                AVG(CASE WHEN severity = 'high' THEN 3 WHEN severity = 'medium' THEN 2 ELSE 1 END) as avg_severity
            FROM flagged_products_violations
            WHERE created_at >= ? AND created_at <= ?";
    
    if ($selectedOutlet !== 'all') {
        $sql .= " AND outlet_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59', $selectedOutlet]);
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    }
    
    $violationStats = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    CISLogger::error('flagged_products', 'Failed to get violation stats: ' . $e->getMessage());
}

// Include header
include $_SERVER['DOCUMENT_ROOT'] . '/modules/base/views/layouts/header.php';
?>

<link rel="stylesheet" href="/modules/flagged_products/assets/css/flagged-products.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.dashboard-header h1 {
    font-size: 28px;
    font-weight: 700;
    margin: 0 0 8px 0;
}

.dashboard-header p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
}

.filter-panel {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 25px;
}

.filter-panel .form-row {
    display: flex;
    gap: 15px;
    align-items: flex-end;
}

.filter-panel .form-group {
    flex: 1;
    margin: 0;
}

.filter-panel label {
    font-size: 12px;
    font-weight: 600;
    color: #4b5563;
    margin-bottom: 5px;
    display: block;
}

.filter-panel input,
.filter-panel select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 13px;
}

.filter-panel button {
    padding: 9px 20px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    font-size: 13px;
}

.filter-panel button:hover {
    background: #5568d3;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.stat-card h3 {
    font-size: 13px;
    font-weight: 600;
    color: #6b7280;
    margin: 0 0 10px 0;
    text-transform: uppercase;
}

.stat-card .value {
    font-size: 32px;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 5px 0;
}

.stat-card .change {
    font-size: 12px;
    font-weight: 600;
}

.stat-card .change.positive {
    color: #10b981;
}

.stat-card .change.negative {
    color: #ef4444;
}

.chart-container {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 25px;
}

.chart-container h2 {
    font-size: 18px;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 20px 0;
}

.chart-canvas {
    max-height: 350px;
}

.store-comparison {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 25px;
}

.store-comparison h2 {
    font-size: 18px;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 20px 0;
}

.store-row {
    display: flex;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;
}

.store-row:last-child {
    border-bottom: none;
}

.store-name {
    width: 180px;
    font-weight: 600;
    font-size: 13px;
    color: #374151;
}

.store-bar-container {
    flex: 1;
    height: 30px;
    background: #f3f4f6;
    border-radius: 6px;
    overflow: hidden;
    position: relative;
}

.store-bar {
    height: 100%;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    border-radius: 6px;
    transition: width 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 10px;
}

.store-bar span {
    color: white;
    font-weight: 700;
    font-size: 12px;
}

.store-metrics {
    display: flex;
    gap: 20px;
    margin-left: 20px;
    font-size: 12px;
}

.store-metrics .metric {
    text-align: center;
}

.store-metrics .metric-value {
    font-weight: 700;
    color: #1f2937;
}

.store-metrics .metric-label {
    color: #6b7280;
}

.violation-panel {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.violation-panel h2 {
    font-size: 18px;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 20px 0;
}

.violation-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
}

.violation-item:last-child {
    border-bottom: none;
}

.violation-type {
    font-weight: 600;
    font-size: 13px;
    color: #374151;
}

.violation-count {
    font-weight: 700;
    font-size: 14px;
    color: #ef4444;
}

.top-performers {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.top-performers h2 {
    font-size: 18px;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 20px 0;
}

.performer-item {
    display: flex;
    align-items: center;
    padding: 10px;
    border-bottom: 1px solid #f3f4f6;
}

.performer-item:last-child {
    border-bottom: none;
}

.performer-rank {
    width: 30px;
    font-weight: 700;
    font-size: 18px;
    color: #667eea;
}

.performer-rank.gold { color: #fbbf24; }
.performer-rank.silver { color: #9ca3af; }
.performer-rank.bronze { color: #f59e0b; }

.performer-info {
    flex: 1;
}

.performer-name {
    font-weight: 600;
    font-size: 13px;
    color: #374151;
}

.performer-outlet {
    font-size: 11px;
    color: #6b7280;
}

.performer-stats {
    display: flex;
    gap: 15px;
    font-size: 12px;
}

.performer-stats .stat {
    text-align: center;
}

.performer-stats .stat-value {
    font-weight: 700;
    color: #1f2937;
}

.performer-stats .stat-label {
    color: #6b7280;
}
</style>

<div class="container-fluid mt-4">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <h1>📊 Flagged Products Dashboard</h1>
        <p>Multi-store performance analytics and team insights</p>
    </div>

    <!-- Filter Panel -->
    <div class="filter-panel">
        <form method="GET" action="">
            <div class="form-row">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <div class="form-group">
                    <label>Store</label>
                    <select name="outlet_id">
                        <option value="all" <?= $selectedOutlet === 'all' ? 'selected' : '' ?>>All Stores</option>
                        <?php foreach ($allOutlets as $outlet): ?>
                            <option value="<?= htmlspecialchars($outlet->id) ?>" <?= $selectedOutlet === $outlet->id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($outlet->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit">Apply Filters</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Overall Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Products Verified</h3>
            <div class="value"><?= number_format(array_sum(array_column(array_column($multiStoreStats, 'stats'), 'products_completed'))) ?></div>
            <div class="change positive">↑ Across all stores</div>
        </div>
        <div class="stat-card">
            <h3>Average Accuracy</h3>
            <div class="value"><?= number_format(array_sum(array_column(array_column($multiStoreStats, 'stats'), 'accuracy')) / count($multiStoreStats), 1) ?>%</div>
            <div class="change">Company-wide</div>
        </div>
        <div class="stat-card">
            <h3>Active Users</h3>
            <div class="value"><?= count($topPerformers) ?></div>
            <div class="change">This period</div>
        </div>
        <div class="stat-card">
            <h3>Total Violations</h3>
            <div class="value"><?= number_format(array_sum(array_column($violationStats, 'count'))) ?></div>
            <div class="change <?= array_sum(array_column($violationStats, 'count')) > 0 ? 'negative' : 'positive' ?>">
                Security events
            </div>
        </div>
    </div>

    <!-- Historical Trends Chart -->
    <div class="chart-container">
        <h2>📈 Completion Trends</h2>
        <canvas id="trendsChart" class="chart-canvas"></canvas>
    </div>

    <!-- Store Comparison -->
    <div class="store-comparison">
        <h2>🏪 Store Performance Comparison</h2>
        <?php
        // Sort stores by products completed
        usort($multiStoreStats, function($a, $b) {
            return $b['stats']['products_completed'] - $a['stats']['products_completed'];
        });
        
        $maxCompleted = max(array_column(array_column($multiStoreStats, 'stats'), 'products_completed')) ?: 1;
        
        foreach ($multiStoreStats as $store):
            $stats = $store['stats'];
            $percentage = ($stats['products_completed'] / $maxCompleted) * 100;
        ?>
        <div class="store-row">
            <div class="store-name"><?= htmlspecialchars($store['outlet_name']) ?></div>
            <div class="store-bar-container">
                <div class="store-bar" style="width: <?= $percentage ?>%">
                    <span><?= number_format($stats['products_completed']) ?></span>
                </div>
            </div>
            <div class="store-metrics">
                <div class="metric">
                    <div class="metric-value"><?= number_format($stats['accuracy'], 1) ?>%</div>
                    <div class="metric-label">Accuracy</div>
                </div>
                <div class="metric">
                    <div class="metric-value"><?= number_format($stats['avg_time_per_product']) ?>s</div>
                    <div class="metric-label">Avg Time</div>
                </div>
                <div class="metric">
                    <div class="metric-value"><?= number_format($stats['total_points']) ?></div>
                    <div class="metric-label">Points</div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row">
        <div class="col-md-6">
            <!-- Violation Patterns -->
            <div class="violation-panel">
                <h2>⚠️ Security Violations</h2>
                <?php if (empty($violationStats)): ?>
                    <p style="color: #10b981; font-weight: 600;">✅ No violations detected</p>
                <?php else: ?>
                    <?php foreach ($violationStats as $violation): ?>
                    <div class="violation-item">
                        <div class="violation-type"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $violation->violation_type))) ?></div>
                        <div class="violation-count"><?= number_format($violation->count) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-6">
            <!-- Top Performers -->
            <div class="top-performers">
                <h2>🏆 Top Performers</h2>
                <?php foreach (array_slice($topPerformers, 0, 10) as $index => $performer): ?>
                <div class="performer-item">
                    <div class="performer-rank <?= $index === 0 ? 'gold' : ($index === 1 ? 'silver' : ($index === 2 ? 'bronze' : '')) ?>">
                        #<?= $index + 1 ?>
                    </div>
                    <div class="performer-info">
                        <div class="performer-name"><?= htmlspecialchars($performer->user_name ?? 'Unknown') ?></div>
                        <div class="performer-outlet"><?= htmlspecialchars($performer->outlet_name ?? 'Unknown Store') ?></div>
                    </div>
                    <div class="performer-stats">
                        <div class="stat">
                            <div class="stat-value"><?= number_format($performer->total_points) ?></div>
                            <div class="stat-label">Points</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value"><?= number_format($performer->products_completed) ?></div>
                            <div class="stat-label">Products</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value"><?= number_format($performer->accuracy, 1) ?>%</div>
                            <div class="stat-label">Accuracy</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Prepare trend data for Chart.js
const trendData = <?= json_encode($trendData) ?>;

// Create completion trends chart
const ctx = document.getElementById('trendsChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: trendData.map(d => d.date),
        datasets: [{
            label: 'Products Completed',
            data: trendData.map(d => d.products_completed),
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Accuracy %',
            data: trendData.map(d => d.accuracy),
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.4,
            fill: true,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false
        },
        plugins: {
            legend: {
                position: 'top'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) {
                            label += ': ';
                        }
                        if (context.datasetIndex === 1) {
                            label += context.parsed.y.toFixed(1) + '%';
                        } else {
                            label += context.parsed.y;
                        }
                        return label;
                    }
                }
            }
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Products Completed'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Accuracy %'
                },
                grid: {
                    drawOnChartArea: false
                },
                min: 0,
                max: 100
            }
        }
    }
});
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/modules/base/views/layouts/footer.php'; ?>
