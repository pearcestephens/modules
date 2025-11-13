<?php
/**
 * Store Reports - Admin Dashboard
 * Desktop interface for operations managers and compliance officers
 */
require_once __DIR__ . '/../../../../private_html/check-login.php';
require_once __DIR__ . '/../../config.php';

// Check admin access
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    die('Access denied: Admin privileges required');
}

// Get user info
$userId = $_SESSION['user_id'] ?? 0;
$userName = $_SESSION['user_name'] ?? 'Admin';

// Get database connection
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Get stats for dashboard
try {
    // Total reports
    $stmt = $db->query("SELECT COUNT(*) as total FROM store_reports");
    $totalReports = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Reports by status
    $stmt = $db->query("SELECT status, COUNT(*) as count FROM store_reports GROUP BY status");
    $reportsByStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Average scores
    $stmt = $db->query("SELECT
        AVG(overall_score) as avg_score,
        AVG(staff_score) as avg_staff_score,
        AVG(ai_score) as avg_ai_score
        FROM store_reports
        WHERE status = 'completed'
    ");
    $avgScores = $stmt->fetch(PDO::FETCH_ASSOC);

    // Critical issues count
    $stmt = $db->query("SELECT SUM(critical_issues_count) as total FROM store_reports WHERE status = 'completed'");
    $criticalIssues = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Recent reports
    $stmt = $db->query("SELECT
        sr.id,
        sr.outlet_id,
        vo.name as outlet_name,
        sr.report_date,
        sr.overall_score,
        sr.status,
        sr.critical_issues_count,
        sr.ai_analysis_status
        FROM store_reports sr
        LEFT JOIN vend_outlets vo ON sr.outlet_id = vo.id
        ORDER BY sr.report_date DESC
        LIMIT 10
    ");
    $recentReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Store Reports Dashboard - Stats error: " . $e->getMessage());
    $totalReports = 0;
    $reportsByStatus = [];
    $avgScores = ['avg_score' => 0, 'avg_staff_score' => 0, 'avg_ai_score' => 0];
    $criticalIssues = 0;
    $recentReports = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Reports Dashboard - Admin</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        :root {
            --sr-primary: #4a90e2;
            --sr-success: #4caf50;
            --sr-warning: #ff9800;
            --sr-danger: #f44336;
            --sr-dark: #1a1a2e;
            --sr-light: #f5f6fa;
        }

        body {
            background: var(--sr-light);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        /* Header */
        .sr-admin-header {
            background: linear-gradient(135deg, var(--sr-dark), #2c2c54);
            color: white;
            padding: 24px 0;
            margin-bottom: 32px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .sr-admin-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }

        .sr-admin-header .subtitle {
            opacity: 0.8;
            font-size: 14px;
        }

        /* Stats Cards */
        .sr-stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
        }

        .sr-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .sr-stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
        }

        .sr-stat-icon.primary { background: #e3f2fd; color: var(--sr-primary); }
        .sr-stat-icon.success { background: #e8f5e9; color: var(--sr-success); }
        .sr-stat-icon.warning { background: #fff3e0; color: var(--sr-warning); }
        .sr-stat-icon.danger { background: #ffebee; color: var(--sr-danger); }

        .sr-stat-value {
            font-size: 36px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 8px;
        }

        .sr-stat-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }

        /* Charts Section */
        .sr-chart-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }

        .sr-chart-card h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--sr-dark);
        }

        /* Table */
        .sr-table-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .sr-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .sr-table thead th {
            background: #f8f9fa;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e0e0e0;
        }

        .sr-table thead th:first-child {
            border-top-left-radius: 8px;
        }

        .sr-table thead th:last-child {
            border-top-right-radius: 8px;
        }

        .sr-table tbody tr {
            transition: background 0.2s;
        }

        .sr-table tbody tr:hover {
            background: #f8f9fa;
        }

        .sr-table tbody td {
            padding: 16px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .sr-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Status Badges */
        .sr-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .sr-badge.draft { background: #e0e0e0; color: #666; }
        .sr-badge.in-progress { background: #fff3e0; color: #f57c00; }
        .sr-badge.completed { background: #e8f5e9; color: #2e7d32; }
        .sr-badge.pending-review { background: #e3f2fd; color: #1976d2; }

        /* Score Badge */
        .sr-score {
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 8px;
            display: inline-block;
        }

        .sr-score.high { background: #e8f5e9; color: #2e7d32; }
        .sr-score.medium { background: #fff3e0; color: #f57c00; }
        .sr-score.low { background: #ffebee; color: #c62828; }

        /* Filters */
        .sr-filters {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }

        .sr-filter-row {
            display: flex;
            gap: 12px;
            align-items: end;
        }

        /* Action Buttons */
        .sr-action-btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .sr-action-btn-primary {
            background: var(--sr-primary);
            color: white;
        }

        .sr-action-btn-primary:hover {
            background: #357abd;
        }

        .sr-action-btn-secondary {
            background: #f5f5f5;
            color: #333;
        }

        .sr-action-btn-secondary:hover {
            background: #e0e0e0;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="sr-admin-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-chart-line me-3"></i>Store Reports Dashboard</h1>
                    <div class="subtitle">Real-time compliance monitoring across all locations</div>
                </div>
                <div>
                    <span class="text-white-50">Welcome, <?= htmlspecialchars($userName) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Stats Row -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="sr-stat-card">
                    <div class="sr-stat-icon primary">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="sr-stat-value"><?= number_format($totalReports) ?></div>
                    <div class="sr-stat-label">Total Reports</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="sr-stat-card">
                    <div class="sr-stat-icon success">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="sr-stat-value"><?= number_format($avgScores['avg_score'] ?? 0, 1) ?>%</div>
                    <div class="sr-stat-label">Average Score</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="sr-stat-card">
                    <div class="sr-stat-icon danger">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="sr-stat-value"><?= number_format($criticalIssues) ?></div>
                    <div class="sr-stat-label">Critical Issues</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="sr-stat-card">
                    <div class="sr-stat-icon warning">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="sr-stat-value"><?= number_format($avgScores['avg_ai_score'] ?? 0, 1) ?>%</div>
                    <div class="sr-stat-label">AI Avg Score</div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="sr-chart-card">
                    <h3>Reports by Status</h3>
                    <canvas id="statusChart" height="250"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="sr-chart-card">
                    <h3>Compliance Trend (Last 30 Days)</h3>
                    <canvas id="trendChart" height="250"></canvas>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="sr-filters">
            <div class="sr-filter-row">
                <div class="flex-fill">
                    <label class="form-label small mb-1">Outlet</label>
                    <select class="form-select" id="filter-outlet">
                        <option value="">All Outlets</option>
                        <!-- Populated via AJAX -->
                    </select>
                </div>
                <div class="flex-fill">
                    <label class="form-label small mb-1">Status</label>
                    <select class="form-select" id="filter-status">
                        <option value="">All Statuses</option>
                        <option value="draft">Draft</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="pending_review">Pending Review</option>
                    </select>
                </div>
                <div class="flex-fill">
                    <label class="form-label small mb-1">Date From</label>
                    <input type="date" class="form-control" id="filter-date-from">
                </div>
                <div class="flex-fill">
                    <label class="form-label small mb-1">Date To</label>
                    <input type="date" class="form-control" id="filter-date-to">
                </div>
                <div>
                    <label class="form-label small mb-1">&nbsp;</label>
                    <button class="sr-action-btn sr-action-btn-primary" onclick="applyFilters()">
                        <i class="fas fa-filter me-2"></i>Apply
                    </button>
                </div>
            </div>
        </div>

        <!-- Recent Reports Table -->
        <div class="sr-table-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="mb-0">Recent Reports</h3>
                <div>
                    <button class="sr-action-btn sr-action-btn-secondary me-2" onclick="exportReports()">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                    <button class="sr-action-btn sr-action-btn-secondary" onclick="refreshReports()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="sr-table" id="reports-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Outlet</th>
                            <th>Date</th>
                            <th>Score</th>
                            <th>Status</th>
                            <th>AI Status</th>
                            <th>Critical Issues</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentReports as $report):
                            $scoreClass = $report['overall_score'] >= 80 ? 'high' :
                                         ($report['overall_score'] >= 60 ? 'medium' : 'low');
                        ?>
                        <tr>
                            <td>#<?= $report['id'] ?></td>
                            <td><?= htmlspecialchars($report['outlet_name'] ?? 'Unknown') ?></td>
                            <td><?= date('M j, Y', strtotime($report['report_date'])) ?></td>
                            <td>
                                <span class="sr-score <?= $scoreClass ?>">
                                    <?= number_format($report['overall_score'], 1) ?>%
                                </span>
                            </td>
                            <td>
                                <span class="sr-badge <?= str_replace('_', '-', $report['status']) ?>">
                                    <?= ucwords(str_replace('_', ' ', $report['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $report['ai_analysis_status'] == 'completed' ? 'success' : 'secondary' ?>">
                                    <?= ucfirst($report['ai_analysis_status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($report['critical_issues_count'] > 0): ?>
                                    <span class="text-danger fw-bold">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <?= $report['critical_issues_count'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="viewReport(<?= $report['id'] ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Status Chart
        const statusData = <?= json_encode($reportsByStatus) ?>;
        const statusLabels = statusData.map(s => s.status.replace('_', ' ').toUpperCase());
        const statusCounts = statusData.map(s => s.count);

        const statusChart = new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusCounts,
                    backgroundColor: [
                        '#e0e0e0', // draft
                        '#fff3e0', // in_progress
                        '#e8f5e9', // completed
                        '#e3f2fd', // pending_review
                    ],
                    borderWidth: 0
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

        // Load trend data and render
        loadTrendData();

        async function loadTrendData() {
            try {
                const response = await fetch('/modules/store-reports/api/admin-trend-data.php');
                const data = await response.json();

                const trendChart = new Chart(document.getElementById('trendChart'), {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Average Score',
                            data: data.scores,
                            borderColor: '#4a90e2',
                            backgroundColor: 'rgba(74, 144, 226, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Failed to load trend data:', error);
            }
        }

        function viewReport(reportId) {
            window.location.href = `/modules/store-reports/views/admin/report-view.php?id=${reportId}`;
        }

        function applyFilters() {
            // Implement AJAX filtering
            console.log('Applying filters...');
        }

        function exportReports() {
            window.location.href = '/modules/store-reports/api/export-reports.php';
        }

        function refreshReports() {
            location.reload();
        }
    </script>
</body>
</html>
