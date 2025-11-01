<?php
declare(strict_types=1);

/**
 * Detailed Rate Limit Analytics View
 * 
 * Comprehensive analysis of API rate limit consumption
 * - Historical trends
 * - Service breakdown
 * - Response time analysis
 * - 429 error patterns
 * 
 * @package HumanResources\Payroll\Views
 */

$pageTitle = 'Rate Limit Analytics';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/human_resources/payroll/views/layouts/header.php';

// Get connection
$pdo = new PDO(
    "mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4",
    "jcepnzzkmj",
    "wprKh9Jq63",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Get filter parameters
$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
$service = isset($_GET['service']) ? $_GET['service'] : 'all';
?>

<style>
.analytics-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    margin: -1.5rem -1.5rem 2rem -1.5rem;
    border-radius: 0.5rem 0.5rem 0 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-box {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stat-box h3 {
    font-size: 0.875rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.5rem;
}

.stat-box .value {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
}

.stat-box .trend {
    font-size: 0.875rem;
    margin-top: 0.5rem;
}

.trend.up { color: #10b981; }
.trend.down { color: #ef4444; }

.chart-container {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.service-breakdown {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 1.5rem;
}

.table-responsive {
    max-height: 600px;
    overflow-y: auto;
}

.endpoint-row {
    cursor: pointer;
    transition: background 0.2s;
}

.endpoint-row:hover {
    background: #f8fafc;
}

.response-time-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.875rem;
    font-weight: 600;
}

.response-time-fast { background: #d1fae5; color: #065f46; }
.response-time-normal { background: #dbeafe; color: #1e40af; }
.response-time-slow { background: #fef3c7; color: #92400e; }
.response-time-critical { background: #fee2e2; color: #991b1b; }
</style>

<div class="analytics-header">
    <h1><i class="bi bi-graph-up"></i> Rate Limit Analytics</h1>
    <p class="mb-0">Comprehensive API consumption analysis and monitoring</p>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-md-3">
        <label class="form-label">Time Period</label>
        <select class="form-select" id="daysFilter" onchange="applyFilters()">
            <option value="7" <?php echo $days === 7 ? 'selected' : ''; ?>>Last 7 Days</option>
            <option value="30" <?php echo $days === 30 ? 'selected' : ''; ?>>Last 30 Days</option>
            <option value="90" <?php echo $days === 90 ? 'selected' : ''; ?>>Last 90 Days</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Service</label>
        <select class="form-select" id="serviceFilter" onchange="applyFilters()">
            <option value="all" <?php echo $service === 'all' ? 'selected' : ''; ?>>All Services</option>
            <option value="xero" <?php echo $service === 'xero' ? 'selected' : ''; ?>>Xero</option>
            <option value="deputy" <?php echo $service === 'deputy' ? 'selected' : ''; ?>>Deputy</option>
        </select>
    </div>
</div>

<?php
// Build WHERE clause
$whereClause = "WHERE logged_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
$params = [$days];

if ($service !== 'all') {
    $whereClause .= " AND service = ?";
    $params[] = $service;
}

// Get summary stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_calls,
        AVG(response_time_ms) as avg_response_time,
        MAX(response_time_ms) as max_response_time,
        SUM(CASE WHEN status_code = 429 THEN 1 ELSE 0 END) as rate_limit_hits,
        SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) as server_errors,
        AVG(rate_limit_remaining) as avg_remaining
    FROM payroll_rate_limits
    $whereClause
");
$stmt->execute($params);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get daily breakdown
$stmt = $pdo->prepare("
    SELECT 
        DATE(logged_at) as date,
        COUNT(*) as calls,
        AVG(response_time_ms) as avg_response,
        SUM(CASE WHEN status_code = 429 THEN 1 ELSE 0 END) as rate_limits
    FROM payroll_rate_limits
    $whereClause
    GROUP BY DATE(logged_at)
    ORDER BY date DESC
");
$stmt->execute($params);
$dailyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get endpoint breakdown
$stmt = $pdo->prepare("
    SELECT 
        endpoint,
        COUNT(*) as total_calls,
        AVG(response_time_ms) as avg_response_time,
        MAX(response_time_ms) as max_response_time,
        SUM(CASE WHEN status_code = 429 THEN 1 ELSE 0 END) as rate_limit_hits,
        MAX(logged_at) as last_called
    FROM payroll_rate_limits
    $whereClause
    GROUP BY endpoint
    ORDER BY total_calls DESC
    LIMIT 50
");
$stmt->execute($params);
$endpoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Summary Stats -->
<div class="stats-grid">
    <div class="stat-box">
        <h3>Total API Calls</h3>
        <div class="value"><?php echo number_format($stats['total_calls']); ?></div>
        <div class="trend up">
            <i class="bi bi-arrow-up"></i> Active monitoring
        </div>
    </div>
    
    <div class="stat-box">
        <h3>Avg Response Time</h3>
        <div class="value"><?php echo round($stats['avg_response_time']); ?>ms</div>
        <div class="trend <?php echo $stats['avg_response_time'] < 300 ? 'up' : 'down'; ?>">
            <?php if ($stats['avg_response_time'] < 300): ?>
                <i class="bi bi-arrow-up"></i> Excellent
            <?php else: ?>
                <i class="bi bi-arrow-down"></i> Needs attention
            <?php endif; ?>
        </div>
    </div>
    
    <div class="stat-box">
        <h3>Rate Limit Hits</h3>
        <div class="value"><?php echo $stats['rate_limit_hits']; ?></div>
        <div class="trend <?php echo $stats['rate_limit_hits'] === 0 ? 'up' : 'down'; ?>">
            <?php if ($stats['rate_limit_hits'] === 0): ?>
                <i class="bi bi-check-circle"></i> No issues
            <?php else: ?>
                <i class="bi bi-exclamation-triangle"></i> Action needed
            <?php endif; ?>
        </div>
    </div>
    
    <div class="stat-box">
        <h3>Avg Remaining Quota</h3>
        <div class="value"><?php echo round($stats['avg_remaining']); ?></div>
        <div class="trend <?php echo $stats['avg_remaining'] > 50 ? 'up' : 'down'; ?>">
            <?php if ($stats['avg_remaining'] > 50): ?>
                <i class="bi bi-arrow-up"></i> Healthy
            <?php else: ?>
                <i class="bi bi-arrow-down"></i> Monitor closely
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Daily Trend Chart -->
<div class="chart-container">
    <h2 class="h4 mb-3"><i class="bi bi-bar-chart"></i> Daily API Call Trend</h2>
    <canvas id="dailyChart" height="80"></canvas>
</div>

<!-- Endpoint Breakdown -->
<div class="service-breakdown">
    <h2 class="h4 mb-3"><i class="bi bi-list-ul"></i> Endpoint Breakdown</h2>
    
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Endpoint</th>
                    <th class="text-center">Total Calls</th>
                    <th class="text-center">Avg Response</th>
                    <th class="text-center">Max Response</th>
                    <th class="text-center">429 Hits</th>
                    <th>Last Called</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($endpoints as $endpoint): ?>
                    <tr class="endpoint-row">
                        <td>
                            <code><?php echo htmlspecialchars($endpoint['endpoint']); ?></code>
                        </td>
                        <td class="text-center">
                            <strong><?php echo number_format($endpoint['total_calls']); ?></strong>
                        </td>
                        <td class="text-center">
                            <?php
                            $avgTime = round($endpoint['avg_response_time']);
                            $badgeClass = 'response-time-fast';
                            if ($avgTime > 1000) $badgeClass = 'response-time-critical';
                            elseif ($avgTime > 500) $badgeClass = 'response-time-slow';
                            elseif ($avgTime > 300) $badgeClass = 'response-time-normal';
                            ?>
                            <span class="response-time-badge <?php echo $badgeClass; ?>">
                                <?php echo $avgTime; ?>ms
                            </span>
                        </td>
                        <td class="text-center text-muted">
                            <?php echo round($endpoint['max_response_time']); ?>ms
                        </td>
                        <td class="text-center">
                            <?php if ($endpoint['rate_limit_hits'] > 0): ?>
                                <span class="badge bg-danger"><?php echo $endpoint['rate_limit_hits']; ?></span>
                            <?php else: ?>
                                <span class="text-success">✓</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted">
                            <?php echo date('Y-m-d H:i', strtotime($endpoint['last_called'])); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<script>
// Apply filters
function applyFilters() {
    const days = document.getElementById('daysFilter').value;
    const service = document.getElementById('serviceFilter').value;
    window.location.href = `?view=rate_limit_analytics&days=${days}&service=${service}`;
}

// Daily trend chart
const dailyData = <?php echo json_encode($dailyData); ?>;
const ctx = document.getElementById('dailyChart').getContext('2d');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: dailyData.map(d => d.date).reverse(),
        datasets: [
            {
                label: 'API Calls',
                data: dailyData.map(d => d.calls).reverse(),
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                yAxisID: 'y'
            },
            {
                label: 'Rate Limit Hits',
                data: dailyData.map(d => d.rate_limits).reverse(),
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                tension: 0.4,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
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
                    afterLabel: function(context) {
                        if (context.dataset.label === 'API Calls') {
                            const idx = context.dataIndex;
                            const avgResponse = Math.round(dailyData.reverse()[idx].avg_response);
                            return `Avg Response: ${avgResponse}ms`;
                        }
                        return '';
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
                    text: 'API Calls'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Rate Limit Hits'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>
