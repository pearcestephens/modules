<?php
/**
 * Leaderboard Dashboard - ANALYTICS STYLE
 * Matches pack-advanced-layout-a.php design scheme
 * Daily/Weekly/Monthly/All-Time rankings, Store vs Store competition
 */

require_once __DIR__ . '/../../base/_templates/themes/cis-classic/theme.php';

$theme = new CISClassicTheme();
$theme->setTitle('Leaderboard');
$theme->setPageSubtitle('See how you rank against your colleagues');
$theme->showTimestamps = true;

// Breadcrumbs
$theme->addBreadcrumb('Consignments', '/modules/consignments/');
$theme->addBreadcrumb('Analytics', null);
$theme->addBreadcrumb('Leaderboard', null);

// Header buttons
$theme->addHeaderButton('My Performance', 'btn-outline-primary', '/modules/consignments/analytics/performance-dashboard.php', 'fa-chart-line');
$theme->addHeaderButton('Store Comparison', 'btn-outline-info', 'javascript:showStoreComparison()', 'fa-building');
$theme->addHeaderButton('Export Rankings', 'btn-outline-secondary', 'javascript:exportRankings()', 'fa-download');
?>

<?php $theme->render('html-head'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<?php $theme->render('header'); ?>
<?php $theme->render('sidebar'); ?>
<?php $theme->render('main-start'); ?>

<style>
/* Match pack-advanced-layout-a.php styling */
.leaderboard-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    background: #f8f9fa;
    min-height: calc(100vh - 200px);
}

/* Tab Selector (matches period-selector) */
.leaderboard-tabs {
    background: #fff;
    border: 1px solid #dee2e6;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
}

.tab-btn {
    padding: 10px 24px;
    border: 2px solid #dee2e6;
    background: #fff;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
    font-weight: 600;
    color: #495057;
}

.tab-btn.active {
    border-color: #007bff;
    background: #007bff;
    color: #fff;
    box-shadow: 0 2px 8px rgba(0,123,255,0.3);
}

.tab-btn:hover:not(.active) {
    border-color: #007bff;
    background: #e7f1ff;
}

.tab-btn i {
    margin-right: 6px;
}

/* Metric Selector */
.metric-selector {
    background: #fff;
    border: 1px solid #dee2e6;
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 12px;
}

.metric-btn {
    padding: 6px 16px;
    border: 1px solid #dee2e6;
    background: #f8f9fa;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 13px;
    color: #495057;
}

.metric-btn.active {
    border-color: #667eea;
    background: #667eea;
    color: #fff;
}

/* Podium Section */
.podium-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 8px;
    padding: 40px 20px;
    margin-bottom: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.podium-container {
    display: flex;
    justify-content: center;
    align-items: flex-end;
    gap: 20px;
    max-width: 800px;
    margin: 0 auto;
}

.podium-place {
    text-align: center;
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    transition: transform 0.3s;
}

.podium-place:hover {
    transform: translateY(-10px);
}

.podium-place.first {
    order: 2;
    width: 200px;
    padding: 30px 20px;
}

.podium-place.second {
    order: 1;
    width: 180px;
}

.podium-place.third {
    order: 3;
    width: 180px;
}

.podium-rank {
    font-size: 48px;
    font-weight: bold;
    margin-bottom: 10px;
}

.podium-rank.first { color: #ffd700; text-shadow: 2px 2px 4px rgba(0,0,0,0.2); }
.podium-rank.second { color: #c0c0c0; }
.podium-rank.third { color: #cd7f32; }

.podium-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin: 0 auto 15px;
    border: 4px solid #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.podium-place.first .podium-avatar {
    width: 100px;
    height: 100px;
    border-color: #ffd700;
}

.podium-name {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}

.podium-store {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 10px;
}

.podium-score {
    font-size: 24px;
    font-weight: bold;
    color: #667eea;
}

.podium-score-label {
    font-size: 11px;
    color: #999;
    text-transform: uppercase;
}

/* Rankings Table */
.rankings-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    margin-bottom: 20px;
    overflow: hidden;
}

.rankings-header {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    padding: 15px 20px;
}

.rankings-title {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin: 0;
}

.rankings-table {
    width: 100%;
    margin-bottom: 0;
}

.rankings-table thead {
    background: #f8f9fa;
}

.rankings-table th {
    padding: 12px 15px;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
    color: #495057;
}

.rankings-table td {
    padding: 15px;
    vertical-align: middle;
    font-size: 14px;
    border-bottom: 1px solid #f0f0f0;
}

.rankings-table tbody tr:hover {
    background: #f8f9fa;
}

.rankings-table tbody tr.current-user {
    background: #e7f1ff;
    border-left: 4px solid #007bff;
}

.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    font-weight: bold;
    font-size: 16px;
}

.rank-badge.rank-1 {
    background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
    color: #8b6914;
    box-shadow: 0 2px 8px rgba(255,215,0,0.4);
}

.rank-badge.rank-2 {
    background: linear-gradient(135deg, #c0c0c0 0%, #e8e8e8 100%);
    color: #666;
    box-shadow: 0 2px 8px rgba(192,192,192,0.4);
}

.rank-badge.rank-3 {
    background: linear-gradient(135deg, #cd7f32 0%, #e8a85d 100%);
    color: #5c3a1a;
    box-shadow: 0 2px 8px rgba(205,127,50,0.4);
}

.rank-badge.rank-other {
    background: #f8f9fa;
    color: #495057;
    border: 2px solid #dee2e6;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #fff;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 2px;
}

.user-store {
    font-size: 12px;
    color: #6c757d;
}

.score-value {
    font-size: 20px;
    font-weight: bold;
    color: #333;
}

.score-unit {
    font-size: 12px;
    color: #6c757d;
    margin-left: 4px;
}

.trend-indicator {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.trend-indicator.up {
    background: #e8f5e9;
    color: #28a745;
}

.trend-indicator.down {
    background: #ffebee;
    color: #dc3545;
}

.trend-indicator.same {
    background: #f8f9fa;
    color: #6c757d;
}

/* Store Rankings Grid */
.store-rankings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.store-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: transform 0.2s;
}

.store-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.store-rank {
    display: inline-block;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #667eea;
    color: #fff;
    text-align: center;
    line-height: 30px;
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 10px;
}

.store-name {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin-bottom: 15px;
}

.store-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.store-stat {
    text-align: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
}

.store-stat-value {
    font-size: 20px;
    font-weight: bold;
    color: #333;
}

.store-stat-label {
    font-size: 11px;
    color: #6c757d;
    text-transform: uppercase;
}

/* Loading State */
.loading-state {
    text-align: center;
    padding: 60px 20px;
}

.loading-spinner {
    width: 60px;
    height: 60px;
    border: 4px solid #f0f0f0;
    border-top-color: #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
    .podium-container {
        flex-direction: column;
        align-items: center;
    }

    .podium-place {
        width: 100% !important;
        max-width: 300px;
    }

    .leaderboard-tabs, .metric-selector {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<div class="leaderboard-container">

    <!-- Tab Selector -->
    <div class="leaderboard-tabs">
        <strong>Time Period:</strong>
        <button class="tab-btn active" data-period="daily" onclick="loadPeriod('daily')">
            <i class="bi bi-calendar-day"></i> Daily
        </button>
        <button class="tab-btn" data-period="weekly" onclick="loadPeriod('weekly')">
            <i class="bi bi-calendar-week"></i> Weekly
        </button>
        <button class="tab-btn" data-period="monthly" onclick="loadPeriod('monthly')">
            <i class="bi bi-calendar-month"></i> Monthly
        </button>
        <button class="tab-btn" data-period="alltime" onclick="loadPeriod('alltime')">
            <i class="bi bi-infinity"></i> All Time
        </button>
    </div>

    <!-- Metric Selector -->
    <div class="metric-selector">
        <strong>Rank By:</strong>
        <button class="metric-btn active" data-metric="overall" onclick="changeMetric('overall')">Overall Score</button>
        <button class="metric-btn" data-metric="speed" onclick="changeMetric('speed')">Speed</button>
        <button class="metric-btn" data-metric="accuracy" onclick="changeMetric('accuracy')">Accuracy</button>
        <button class="metric-btn" data-metric="volume" onclick="changeMetric('volume')">Volume</button>
    </div>

    <!-- Loading State -->
    <div id="loadingState" class="loading-state">
        <div class="loading-spinner"></div>
        <p class="text-muted">Loading leaderboard...</p>
    </div>

    <!-- Main Content -->
    <div id="mainContent" style="display: none;">

        <!-- Podium (Top 3) -->
        <div class="podium-section">
            <h2 class="text-center text-white mb-4">
                <i class="bi bi-trophy-fill"></i> Top Performers
            </h2>
            <div class="podium-container" id="podiumContainer">
                <!-- Top 3 loaded dynamically -->
            </div>
        </div>

        <!-- Full Rankings Table -->
        <div class="rankings-card">
            <div class="rankings-header">
                <h3 class="rankings-title">
                    <i class="bi bi-list-ol"></i> Full Rankings
                    <span class="badge bg-primary ms-2" id="totalUsers">0</span>
                </h3>
            </div>
            <table class="rankings-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">Rank</th>
                        <th>User</th>
                        <th>Store</th>
                        <th class="text-center">Transfers</th>
                        <th class="text-center">Avg Speed</th>
                        <th class="text-center">Accuracy</th>
                        <th class="text-center">Score</th>
                        <th class="text-center">Trend</th>
                    </tr>
                </thead>
                <tbody id="rankingsTable">
                    <!-- Rankings loaded dynamically -->
                </tbody>
            </table>
        </div>

        <!-- Store vs Store Section -->
        <div id="storeSection" style="display: none;">
            <div class="rankings-card">
                <div class="rankings-header">
                    <h3 class="rankings-title">
                        <i class="bi bi-building"></i> Store Rankings
                    </h3>
                </div>
                <div class="store-rankings-grid" id="storeRankings">
                    <!-- Store rankings loaded dynamically -->
                </div>
            </div>
        </div>

    </div>
</div>

<script>
let currentPeriod = 'daily';
let currentMetric = 'overall';
let currentUserId = <?php echo $_SESSION['user_id'] ?? 1; ?>;
let currentOutletId = '<?php echo $_SESSION['outlet_id'] ?? 'OUTLET001'; ?>';

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadLeaderboard();
});

// Load Period
function loadPeriod(period) {
    currentPeriod = period;

    // Update active tab
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-period="${period}"]`).classList.add('active');

    loadLeaderboard();
}

// Change Metric
function changeMetric(metric) {
    currentMetric = metric;

    // Update active button
    document.querySelectorAll('.metric-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-metric="${metric}"]`).classList.add('active');

    loadLeaderboard();
}

// Load Leaderboard
async function loadLeaderboard() {
    document.getElementById('loadingState').style.display = 'block';
    document.getElementById('mainContent').style.display = 'none';

    try {
        const response = await fetch(`../api/barcode_analytics.php?action=get_leaderboard&period=${currentPeriod}&metric=${currentMetric}`);
        const data = await response.json();

        if (data.success) {
            renderLeaderboard(data.leaderboard);
        } else {
            alert('Error loading leaderboard: ' + data.error);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to load leaderboard');
    }

    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('mainContent').style.display = 'block';
}

// Render Leaderboard
function renderLeaderboard(data) {
    // Render podium (top 3)
    renderPodium(data.slice(0, 3));

    // Render full table
    renderRankingsTable(data);

    // Update total count
    document.getElementById('totalUsers').textContent = data.length + ' users';
}

// Render Podium
function renderPodium(top3) {
    const container = document.getElementById('podiumContainer');
    container.innerHTML = '';

    if (top3.length === 0) {
        container.innerHTML = '<p class="text-white text-center">No data available yet</p>';
        return;
    }

    const positions = ['second', 'first', 'third'];
    const ranks = [2, 1, 3];

    positions.forEach((position, index) => {
        const user = top3[ranks[index] - 1];
        if (!user) return;

        const div = document.createElement('div');
        div.className = `podium-place ${position}`;
        div.innerHTML = `
            <div class="podium-rank ${position}">${ranks[index] === 1 ? '🏆' : ranks[index] === 2 ? '🥈' : '🥉'}</div>
            <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(user.user_name)}&size=100&background=667eea&color=fff"
                 class="podium-avatar" alt="${user.user_name}">
            <div class="podium-name">${user.user_name}</div>
            <div class="podium-store">${user.outlet_name || user.outlet_id}</div>
            <div class="podium-score">${getMetricValue(user)}</div>
            <div class="podium-score-label">${getMetricLabel()}</div>
        `;
        container.appendChild(div);
    });
}

// Render Rankings Table
function renderRankingsTable(data) {
    const tbody = document.getElementById('rankingsTable');
    tbody.innerHTML = '';

    data.forEach((user, index) => {
        const rank = index + 1;
        const isCurrentUser = user.user_id == currentUserId;

        const rankClass = rank === 1 ? 'rank-1' : rank === 2 ? 'rank-2' : rank === 3 ? 'rank-3' : 'rank-other';

        const tr = document.createElement('tr');
        tr.className = isCurrentUser ? 'current-user' : '';
        tr.innerHTML = `
            <td>
                <div class="rank-badge ${rankClass}">${rank}</div>
            </td>
            <td>
                <div class="user-info">
                    <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(user.user_name)}&size=40&background=667eea&color=fff"
                         class="user-avatar" alt="${user.user_name}">
                    <div>
                        <div class="user-name">${user.user_name} ${isCurrentUser ? '(You)' : ''}</div>
                    </div>
                </div>
            </td>
            <td>
                <div class="user-store">${user.outlet_name || user.outlet_id}</div>
            </td>
            <td class="text-center">${user.transfers_completed || 0}</td>
            <td class="text-center">
                <span class="score-value">${(user.avg_scans_per_minute || 0).toFixed(1)}</span>
                <span class="score-unit">/min</span>
            </td>
            <td class="text-center">
                <span class="score-value">${(user.accuracy_percentage || 0).toFixed(1)}</span>
                <span class="score-unit">%</span>
            </td>
            <td class="text-center">
                <span class="score-value">${user.performance_score || 0}</span>
            </td>
            <td class="text-center">
                ${getTrendIndicator(user.rank_change)}
            </td>
        `;
        tbody.appendChild(tr);
    });
}

// Get Metric Value
function getMetricValue(user) {
    switch(currentMetric) {
        case 'speed': return (user.avg_scans_per_minute || 0).toFixed(1) + ' /min';
        case 'accuracy': return (user.accuracy_percentage || 0).toFixed(1) + '%';
        case 'volume': return (user.transfers_completed || 0);
        default: return user.performance_score || 0;
    }
}

// Get Metric Label
function getMetricLabel() {
    switch(currentMetric) {
        case 'speed': return 'scans/min';
        case 'accuracy': return 'accuracy';
        case 'volume': return 'transfers';
        default: return 'score';
    }
}

// Get Trend Indicator
function getTrendIndicator(change) {
    if (!change || change === 0) {
        return '<span class="trend-indicator same"><i class="bi bi-dash"></i> --</span>';
    } else if (change > 0) {
        return `<span class="trend-indicator up"><i class="bi bi-arrow-up"></i> +${change}</span>`;
    } else {
        return `<span class="trend-indicator down"><i class="bi bi-arrow-down"></i> ${change}</span>`;
    }
}

// Show Store Comparison
function showStoreComparison() {
    alert('Store comparison view coming soon!');
}

// Export Rankings
function exportRankings() {
    window.location.href = `../api/barcode_analytics.php?action=export_leaderboard&period=${currentPeriod}&metric=${currentMetric}`;
}
</script>

<?php $theme->render('main-end'); ?>
<?php $theme->render('footer'); ?>
<?php $theme->render('html-end'); ?>
