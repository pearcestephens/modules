<?php
/**
 * Performance Dashboard - ANALYTICS STYLE
 * Matches pack-advanced-layout-a.php design scheme
 * User performance stats, achievements, rank, personal bests
 */

require_once __DIR__ . '/../../base/_templates/themes/cis-classic/theme.php';

$theme = new CISClassicTheme();
$theme->setTitle('Performance Dashboard');
$theme->setPageSubtitle('Track your scanning performance and achievements');
$theme->showTimestamps = true;

// Breadcrumbs
$theme->addBreadcrumb('Consignments', '/modules/consignments/');
$theme->addBreadcrumb('Analytics', null);
$theme->addBreadcrumb('Performance', null);

// Header buttons
$theme->addHeaderButton('View Leaderboard', 'btn-outline-primary', '/modules/consignments/analytics/leaderboard.php', 'fa-trophy');
$theme->addHeaderButton('View Achievements', 'btn-outline-success', 'javascript:showAchievements()', 'fa-star');
$theme->addHeaderButton('Export Report', 'btn-outline-secondary', 'javascript:exportReport()', 'fa-download');
?>

<?php $theme->render('html-head'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<?php $theme->render('header'); ?>
<?php $theme->render('sidebar'); ?>
<?php $theme->render('main-start'); ?>

<style>
/* Match pack-advanced-layout-a.php styling */
.performance-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    background: #f8f9fa;
    min-height: calc(100vh - 200px);
}

/* Period Selector (matches hero-search-a) */
.period-selector {
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

.period-btn {
    padding: 8px 20px;
    border: 2px solid #dee2e6;
    background: #fff;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
    font-weight: 500;
    color: #495057;
}

.period-btn.active {
    border-color: #007bff;
    background: #007bff;
    color: #fff;
}

.period-btn:hover:not(.active) {
    border-color: #007bff;
    background: #e7f1ff;
}

/* Stats Grid (matches kpi-row-a) */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.stat-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    border-left: 4px solid #007bff;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.stat-card.success { border-left-color: #28a745; }
.stat-card.warning { border-left-color: #ffc107; }
.stat-card.danger { border-left-color: #dc3545; }
.stat-card.purple { border-left-color: #667eea; }
.stat-card.info { border-left-color: #17a2b8; }

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 12px;
}

.stat-card.success .stat-icon { background: #e8f5e9; color: #28a745; }
.stat-card.warning .stat-icon { background: #fff8e1; color: #ffc107; }
.stat-card.danger .stat-icon { background: #ffebee; color: #dc3545; }
.stat-card.purple .stat-icon { background: #ede7f6; color: #667eea; }
.stat-card.info .stat-icon { background: #e1f5fe; color: #17a2b8; }

.stat-value {
    font-size: 32px;
    font-weight: bold;
    color: #333;
    margin: 8px 0;
}

.stat-label {
    font-size: 13px;
    color: #6c757d;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.stat-change {
    font-size: 12px;
    margin-top: 8px;
    padding: 4px 8px;
    border-radius: 4px;
    display: inline-block;
}

.stat-change.up {
    background: #e8f5e9;
    color: #28a745;
}

.stat-change.down {
    background: #ffebee;
    color: #dc3545;
}

/* Charts Section */
.charts-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.chart-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}

.chart-title {
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.chart-body {
    min-height: 300px;
}

/* Achievements Section (matches freight-console-a) */
.achievements-section {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}

.section-header {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    padding: 15px 20px;
    border-radius: 6px 6px 0 0;
}

.section-title {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin: 0;
}

.achievements-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 15px;
    padding: 20px;
}

.achievement-badge {
    text-align: center;
    padding: 15px;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    background: #fff;
}

.achievement-badge.earned {
    border-color: #ffc107;
    background: linear-gradient(135deg, #fff9e6 0%, #fff 100%);
}

.achievement-badge:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.achievement-badge.locked {
    opacity: 0.5;
    filter: grayscale(100%);
}

.achievement-icon {
    font-size: 48px;
    margin-bottom: 10px;
}

.achievement-name {
    font-size: 13px;
    font-weight: 600;
    color: #333;
    margin-bottom: 4px;
}

.achievement-desc {
    font-size: 11px;
    color: #6c757d;
}

.achievement-date {
    font-size: 10px;
    color: #999;
    margin-top: 4px;
}

/* Recent Activity Table (matches product-table-a) */
.activity-table {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    overflow: hidden;
    margin-bottom: 20px;
}

.activity-table table {
    width: 100%;
    margin-bottom: 0;
}

.activity-table thead {
    background: #f8f9fa;
}

.activity-table th {
    padding: 12px 15px;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
    color: #495057;
}

.activity-table td {
    padding: 12px 15px;
    vertical-align: middle;
    font-size: 14px;
    border-bottom: 1px solid #f0f0f0;
}

.activity-table tbody tr:hover {
    background: #f8f9fa;
}

.score-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.score-perfect { background: #e8f5e9; color: #28a745; }
.score-great { background: #e3f2fd; color: #2196f3; }
.score-good { background: #fff8e1; color: #ffc107; }
.score-needs-improvement { background: #ffebee; color: #dc3545; }

/* Personal Best Section */
.personal-bests {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.best-record {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
}

.best-record:last-child {
    border-bottom: none;
}

.best-label {
    font-size: 14px;
    color: #495057;
    font-weight: 500;
}

.best-value {
    font-size: 18px;
    font-weight: bold;
    color: #333;
}

.best-date {
    font-size: 11px;
    color: #999;
    margin-left: 10px;
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
@media (max-width: 1200px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .period-selector {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<div class="performance-container">

    <!-- Period Selector -->
    <div class="period-selector">
        <strong>Time Period:</strong>
        <button class="period-btn active" data-period="today" onclick="loadPeriod('today')">Today</button>
        <button class="period-btn" data-period="week" onclick="loadPeriod('week')">This Week</button>
        <button class="period-btn" data-period="month" onclick="loadPeriod('month')">This Month</button>
        <button class="period-btn" data-period="all" onclick="loadPeriod('all')">All Time</button>
    </div>

    <!-- Loading State -->
    <div id="loadingState" class="loading-state">
        <div class="loading-spinner"></div>
        <p class="text-muted">Loading your performance data...</p>
    </div>

    <!-- Main Content (Hidden until loaded) -->
    <div id="mainContent" style="display: none;">

        <!-- Key Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="bi bi-speedometer2"></i>
                </div>
                <div class="stat-value" id="stat-speed">0</div>
                <div class="stat-label">Scans Per Minute</div>
                <div class="stat-change up" id="stat-speed-change">
                    <i class="bi bi-arrow-up"></i> +12% from last week
                </div>
            </div>

            <div class="stat-card purple">
                <div class="stat-icon">
                    <i class="bi bi-bullseye"></i>
                </div>
                <div class="stat-value" id="stat-accuracy">0%</div>
                <div class="stat-label">Accuracy Rate</div>
                <div class="stat-change up" id="stat-accuracy-change">
                    <i class="bi bi-arrow-up"></i> Above 95% target
                </div>
            </div>

            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div class="stat-value" id="stat-transfers">0</div>
                <div class="stat-label">Transfers Completed</div>
                <div class="stat-change" id="stat-transfers-change">
                    Today
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="bi bi-trophy"></i>
                </div>
                <div class="stat-value" id="stat-rank">#0</div>
                <div class="stat-label">Your Rank Today</div>
                <div class="stat-change" id="stat-rank-change">
                    Out of 25 staff
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-grid-3x3-gap"></i>
                </div>
                <div class="stat-value" id="stat-items">0</div>
                <div class="stat-label">Items Scanned</div>
                <div class="stat-change" id="stat-items-change">
                    This period
                </div>
            </div>

            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div class="stat-value" id="stat-errors">0</div>
                <div class="stat-label">Errors</div>
                <div class="stat-change" id="stat-errors-change">
                    Error rate: 0%
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="charts-grid">
            <!-- Performance Trend Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="bi bi-graph-up"></i> Performance Trend
                    </div>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary active" onclick="changeChartMetric('speed')">Speed</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="changeChartMetric('accuracy')">Accuracy</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="changeChartMetric('score')">Score</button>
                    </div>
                </div>
                <div class="chart-body">
                    <canvas id="performanceChart"></canvas>
                </div>
            </div>

            <!-- Distribution Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="bi bi-pie-chart"></i> Performance Distribution
                    </div>
                </div>
                <div class="chart-body">
                    <canvas id="distributionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Achievements Section -->
        <div class="achievements-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="bi bi-star-fill text-warning"></i> Achievements
                    <span class="badge bg-primary ms-2" id="achievements-count">0/15</span>
                </h2>
            </div>
            <div class="achievements-grid" id="achievementsGrid">
                <!-- Achievements loaded dynamically -->
            </div>
        </div>

        <!-- Personal Bests -->
        <div class="personal-bests">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="bi bi-award-fill text-success"></i> Personal Best Records
                </h2>
            </div>
            <div style="padding: 20px;">
                <div class="best-record">
                    <div class="best-label">
                        <i class="bi bi-speedometer2 text-success"></i> Fastest Scans Per Minute
                    </div>
                    <div>
                        <span class="best-value" id="best-speed">0</span>
                        <span class="best-date" id="best-speed-date">--</span>
                    </div>
                </div>
                <div class="best-record">
                    <div class="best-label">
                        <i class="bi bi-bullseye text-purple"></i> Highest Accuracy
                    </div>
                    <div>
                        <span class="best-value" id="best-accuracy">0%</span>
                        <span class="best-date" id="best-accuracy-date">--</span>
                    </div>
                </div>
                <div class="best-record">
                    <div class="best-label">
                        <i class="bi bi-trophy text-warning"></i> Best Performance Score
                    </div>
                    <div>
                        <span class="best-value" id="best-score">0</span>
                        <span class="best-date" id="best-score-date">--</span>
                    </div>
                </div>
                <div class="best-record">
                    <div class="best-label">
                        <i class="bi bi-box-seam text-info"></i> Most Transfers in One Day
                    </div>
                    <div>
                        <span class="best-value" id="best-transfers">0</span>
                        <span class="best-date" id="best-transfers-date">--</span>
                    </div>
                </div>
                <div class="best-record">
                    <div class="best-label">
                        <i class="bi bi-grid-3x3-gap text-primary"></i> Most Items in One Transfer
                    </div>
                    <div>
                        <span class="best-value" id="best-items">0</span>
                        <span class="best-date" id="best-items-date">--</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="activity-table">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="bi bi-clock-history"></i> Recent Activity
                </h2>
            </div>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Transfer ID</th>
                        <th>Items</th>
                        <th>Speed</th>
                        <th>Accuracy</th>
                        <th>Score</th>
                        <th>Duration</th>
                    </tr>
                </thead>
                <tbody id="recentActivity">
                    <!-- Loaded dynamically -->
                </tbody>
            </table>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
let currentPeriod = 'today';
let currentUserId = <?php echo $_SESSION['user_id'] ?? 1; ?>;
let performanceChart = null;
let distributionChart = null;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadPerformanceData();
});

// Load Period
function loadPeriod(period) {
    currentPeriod = period;

    // Update active button
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-period="${period}"]`).classList.add('active');

    loadPerformanceData();
}

// Load Performance Data
async function loadPerformanceData() {
    document.getElementById('loadingState').style.display = 'block';
    document.getElementById('mainContent').style.display = 'none';

    try {
        const response = await fetch(`../api/barcode_analytics.php?action=get_performance&user_id=${currentUserId}&period=${currentPeriod}`);
        const data = await response.json();

        if (data.success) {
            renderPerformanceData(data.performance);
            loadAchievements();
            loadRecentActivity();
        } else {
            alert('Error loading performance data: ' + data.error);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to load performance data');
    }

    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('mainContent').style.display = 'block';
}

// Render Performance Data
function renderPerformanceData(data) {
    // Update stats
    document.getElementById('stat-speed').textContent = data.avg_scans_per_minute?.toFixed(1) || '0';
    document.getElementById('stat-accuracy').textContent = (data.accuracy_percentage?.toFixed(1) || '0') + '%';
    document.getElementById('stat-transfers').textContent = data.transfers_completed || '0';
    document.getElementById('stat-rank').textContent = '#' + (data.outlet_rank || '--');
    document.getElementById('stat-items').textContent = data.total_items_scanned || '0';
    document.getElementById('stat-errors').textContent = data.error_count || '0';

    // Update change indicators
    if (data.accuracy_percentage >= 95) {
        document.getElementById('stat-accuracy-change').innerHTML = '<i class="bi bi-check-circle"></i> Above 95% target';
        document.getElementById('stat-accuracy-change').className = 'stat-change up';
    } else {
        document.getElementById('stat-accuracy-change').innerHTML = '<i class="bi bi-arrow-down"></i> Below 95% target';
        document.getElementById('stat-accuracy-change').className = 'stat-change down';
    }

    // Update personal bests
    document.getElementById('best-speed').textContent = data.personal_bests?.speed || '0';
    document.getElementById('best-speed-date').textContent = data.personal_bests?.speed_date || '--';
    document.getElementById('best-accuracy').textContent = (data.personal_bests?.accuracy || 0) + '%';
    document.getElementById('best-accuracy-date').textContent = data.personal_bests?.accuracy_date || '--';
    document.getElementById('best-score').textContent = data.personal_bests?.score || '0';
    document.getElementById('best-score-date').textContent = data.personal_bests?.score_date || '--';
    document.getElementById('best-transfers').textContent = data.personal_bests?.transfers || '0';
    document.getElementById('best-transfers-date').textContent = data.personal_bests?.transfers_date || '--';
    document.getElementById('best-items').textContent = data.personal_bests?.items || '0';
    document.getElementById('best-items-date').textContent = data.personal_bests?.items_date || '--';

    // Render charts
    renderCharts(data);
}

// Render Charts
function renderCharts(data) {
    // Performance Trend Chart
    const ctx1 = document.getElementById('performanceChart').getContext('2d');

    if (performanceChart) {
        performanceChart.destroy();
    }

    performanceChart = new Chart(ctx1, {
        type: 'line',
        data: {
            labels: data.trend_labels || [],
            datasets: [{
                label: 'Scans Per Minute',
                data: data.trend_speed || [],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Distribution Chart
    const ctx2 = document.getElementById('distributionChart').getContext('2d');

    if (distributionChart) {
        distributionChart.destroy();
    }

    distributionChart = new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: ['Perfect', 'Great', 'Good', 'Needs Improvement'],
            datasets: [{
                data: [
                    data.distribution?.perfect || 0,
                    data.distribution?.great || 0,
                    data.distribution?.good || 0,
                    data.distribution?.poor || 0
                ],
                backgroundColor: ['#28a745', '#2196f3', '#ffc107', '#dc3545']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Load Achievements
async function loadAchievements() {
    try {
        const response = await fetch(`../api/barcode_analytics.php?action=check_achievements&user_id=${currentUserId}`);
        const data = await response.json();

        if (data.success) {
            renderAchievements(data.achievements);
        }
    } catch (error) {
        console.error('Error loading achievements:', error);
    }
}

// Render Achievements
function renderAchievements(achievements) {
    const container = document.getElementById('achievementsGrid');
    container.innerHTML = '';

    const allAchievements = [
        { code: 'speed_demon', icon: '⚡', name: 'Speed Demon', desc: '50+ scans/min, 3 times' },
        { code: 'accuracy_ace', icon: '🎯', name: 'Accuracy Ace', desc: '95%+ accuracy, 10 times' },
        { code: 'perfect_score', icon: '💯', name: 'Perfect Score', desc: '100% accuracy, 20+ items' },
        { code: 'workhorse', icon: '🏋️', name: 'Workhorse', desc: '20 transfers in one day' },
        { code: 'week_warrior', icon: '🔥', name: 'Week Warrior', desc: '7-day streak' },
        { code: 'flawless', icon: '✨', name: 'Flawless', desc: '50 transfers, zero errors' }
    ];

    const earned = achievements?.earned || [];
    let earnedCount = 0;

    allAchievements.forEach(ach => {
        const isEarned = earned.some(e => e.achievement_code === ach.code);
        if (isEarned) earnedCount++;

        const earnedData = earned.find(e => e.achievement_code === ach.code);

        const div = document.createElement('div');
        div.className = `achievement-badge ${isEarned ? 'earned' : 'locked'}`;
        div.innerHTML = `
            <div class="achievement-icon">${ach.icon}</div>
            <div class="achievement-name">${ach.name}</div>
            <div class="achievement-desc">${ach.desc}</div>
            ${isEarned ? `<div class="achievement-date">Earned ${earnedData.earned_at}</div>` : ''}
        `;
        container.appendChild(div);
    });

    document.getElementById('achievements-count').textContent = `${earnedCount}/${allAchievements.length}`;
}

// Load Recent Activity
async function loadRecentActivity() {
    try {
        const response = await fetch(`../api/barcode_analytics.php?action=get_performance&user_id=${currentUserId}&period=week&include_sessions=true`);
        const data = await response.json();

        if (data.success && data.recent_sessions) {
            renderRecentActivity(data.recent_sessions);
        }
    } catch (error) {
        console.error('Error loading recent activity:', error);
    }
}

// Render Recent Activity
function renderRecentActivity(sessions) {
    const tbody = document.getElementById('recentActivity');
    tbody.innerHTML = '';

    sessions.slice(0, 10).forEach(session => {
        const score = session.performance_score || 0;
        const scoreClass = score >= 90 ? 'score-perfect' :
                          score >= 80 ? 'score-great' :
                          score >= 70 ? 'score-good' : 'score-needs-improvement';

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${session.completed_at}</td>
            <td>#${session.transfer_id}</td>
            <td>${session.items_scanned}</td>
            <td>${session.scans_per_minute?.toFixed(1)} /min</td>
            <td>${session.accuracy_percentage?.toFixed(1)}%</td>
            <td><span class="score-badge ${scoreClass}">${score}</span></td>
            <td>${session.duration_seconds}s</td>
        `;
        tbody.appendChild(tr);
    });
}

// Show Achievements Modal
function showAchievements() {
    alert('Full achievements page coming soon!');
}

// Export Report
function exportReport() {
    window.location.href = `../api/barcode_analytics.php?action=export_report&user_id=${currentUserId}&period=${currentPeriod}`;
}

// Change Chart Metric
function changeChartMetric(metric) {
    // Update active button
    document.querySelectorAll('.chart-header .btn-group button').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');

    // Update chart (would reload with different metric)
    console.log('Change chart to:', metric);
}
</script>

<?php $theme->render('main-end'); ?>
<?php $theme->render('footer'); ?>
<?php $theme->render('html-end'); ?>
