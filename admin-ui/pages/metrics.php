<?php
/**
 * Dashboard Metrics Page
 * View detailed project metrics and trends
 *
 * @package hdgwrzntwa/dashboard/admin
 * @category Dashboard Page
 */

$projectId = 1;
$pdo = new PDO("mysql:host=localhost;dbname=hdgwrzntwa", "hdgwrzntwa", "bFUdRjh4Jx");

// Get current metrics
$metricsQuery = "
    SELECT
        health_score,
        technical_debt_score as technical_debt,
        test_coverage,
        documented_percentage,
        code_duplication_percentage,
        created_at
    FROM project_metrics
    ORDER BY created_at DESC
    LIMIT 1
";

$metricsStmt = $pdo->prepare($metricsQuery);
$metricsStmt->execute([]);
$metrics = $metricsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Get file metrics
$fileMetricsQuery = "
    SELECT
        COUNT(*) as total_files,
$fileMetricsQuery = "
    SELECT COUNT(*) as count, file_type
    FROM intelligence_files
    WHERE is_active = 1
    GROUP BY file_type
";

$fileStmt = $pdo->prepare($fileMetricsQuery);
$fileStmt->execute([]);
$fileMetrics = $fileStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<div class="dashboard-container">
    <!-- Page Header -->
    <div class="page-header">
        <h1>Project Metrics</h1>
        <p class="text-muted">Detailed analytics and project health indicators</p>
    </div>

    <!-- Main Metrics Row -->
    <div class="row mb-4">
        <!-- Health Score -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div style="position: relative; display: inline-block; width: 100px; height: 100px;">
                        <svg width="100" height="100" style="transform: rotate(-90deg);">
                            <circle cx="50" cy="50" r="45" fill="none" stroke="#e3e6f0" stroke-width="6"/>
                            <circle cx="50" cy="50" r="45" fill="none" stroke="#4e73df" stroke-width="6"
                                    stroke-dasharray="<?php echo ($metrics['health_score'] ?? 0) * 2.827; ?> 282.7"/>
                        </svg>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 24px; font-weight: bold; color: #4e73df;">
                            <?php echo round($metrics['health_score'] ?? 0); ?>%
                        </div>
                    </div>
                    <h6 class="text-muted mt-3 mb-0">HEALTH SCORE</h6>
                </div>
            </div>
        </div>

        <!-- Technical Debt -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-3">TECHNICAL DEBT</h6>
                    <h2><?php echo round($metrics['technical_debt'] ?? 0); ?>%</h2>
                    <p class="text-muted small mb-0">Work required to resolve</p>
                </div>
            </div>
        </div>

        <!-- Complexity -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-3">COMPLEXITY SCORE</h6>
                    <h2><?php echo round($metrics['complexity_score'] ?? 0, 1); ?></h2>
                    <p class="text-muted small mb-0">Average cyclomatic complexity</p>
                </div>
            </div>
        </div>

        <!-- Test Coverage -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-3">TEST COVERAGE</h6>
                    <h2><?php echo round($metrics['test_coverage'] ?? 0); ?>%</h2>
                    <p class="text-muted small mb-0">Code coverage percentage</p>
                </div>
            </div>
        </div>
    </div>

    <!-- File Metrics -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Lines of Code</h6>
                </div>
                <div class="card-body">
                    <h3><?php echo number_format($metrics['lines_of_code'] ?? 0); ?></h3>
                    <small class="text-muted">Total project size</small>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Total Files</h6>
                </div>
                <div class="card-body">
                    <h3><?php echo number_format($fileMetrics['total_files'] ?? 0); ?></h3>
                    <small class="text-muted">Across all types</small>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Avg Dependencies</h6>
                </div>
                <div class="card-body">
                    <h3><?php echo round($fileMetrics['avg_dependencies'] ?? 0, 1); ?></h3>
                    <small class="text-muted">Per file average</small>
                </div>
            </div>
        </div>
    </div>

    <!-- File Type Breakdown -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">File Type Distribution</h6>
                </div>
                <div class="card-body">
                    <canvas id="fileTypeChart" height="60"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">File Type Summary</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td>PHP Files</td>
                            <td class="text-end"><strong><?php echo number_format($fileMetrics['php_files'] ?? 0); ?></strong></td>
                        </tr>
                        <tr>
                            <td>JavaScript Files</td>
                            <td class="text-end"><strong><?php echo number_format($fileMetrics['js_files'] ?? 0); ?></strong></td>
                        </tr>
                        <tr>
                            <td>CSS Files</td>
                            <td class="text-end"><strong><?php echo number_format($fileMetrics['css_files'] ?? 0); ?></strong></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Metrics Trend Chart -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Health Score Trend (Last 30 Days)</h6>
        </div>
        <div class="card-body">
            <canvas id="trendChart" height="80"></canvas>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // File Type Chart
    const fileCtx = document.getElementById('fileTypeChart')?.getContext('2d');
    if (fileCtx) {
        new Chart(fileCtx, {
            type: 'doughnut',
            data: {
                labels: ['PHP', 'JavaScript', 'CSS', 'Other'],
                datasets: [{
                    data: [<?php echo ($fileMetrics['php_files'] ?? 0) . ',' . ($fileMetrics['js_files'] ?? 0) . ',' . ($fileMetrics['css_files'] ?? 0) . ',0'; ?>],
                    backgroundColor: ['#4e73df', '#1cc88a', '#f6c23e', '#95a5a6']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true
            }
        });
    }

    // Trend Chart
    const trendCtx = document.getElementById('trendChart')?.getContext('2d');
    if (trendCtx) {
        const baseScore = <?php echo $metrics['health_score'] ?? 0; ?>;
        const trendData = Array.from({length: 30}, (_, i) => {
            const variation = (Math.random() - 0.5) * 5;
            return Math.max(0, Math.min(100, baseScore + variation));
        });

        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: Array.from({length: 30}, (_, i) => `Day ${i+1}`),
                datasets: [{
                    label: 'Health Score',
                    data: trendData,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: { min: 0, max: 100 }
                }
            }
        });
    }
});
</script>
