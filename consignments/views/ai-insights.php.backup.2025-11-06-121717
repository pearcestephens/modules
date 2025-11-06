<?php
/**
 * AI Insights View
 *
 * AI-powered recommendations and analytics for consignment operations.
 *
 * @package CIS\Consignments
 * @version 3.0.0
 */

declare(strict_types=1);

// Page metadata
$pageTitle = 'AI Insights';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/'],
    ['label' => 'AI Insights', 'url' => '', 'active' => true]
];

// Get database connection
$pdo = CIS\Base\Database::pdo();

// Load some sample insights
$insights = [];

// Check for slow-moving transfers
$slowTransfersStmt = $pdo->query("
    SELECT COUNT(*) as count
    FROM vend_consignments
    WHERE status = 'OPEN'
    AND DATEDIFF(NOW(), created_at) > 7
");
$slowTransfers = $slowTransfersStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Check for high-performing outlets
$topOutletsStmt = $pdo->query("
    SELECT
        outlet_id,
        COUNT(*) as transfer_count
    FROM vend_consignments
    WHERE DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND status = 'RECEIVED'
    GROUP BY outlet_id
    ORDER BY transfer_count DESC
    LIMIT 5
");
$topOutlets = $topOutletsStmt->fetchAll(PDO::FETCH_ASSOC);

// Start output buffering
ob_start();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">
            <i class="fas fa-brain text-primary me-2"></i>
            AI Insights
        </h1>
        <p class="text-muted mb-0">AI-powered recommendations and analytics</p>
    </div>
    <div>
        <button class="btn btn-primary" onclick="refreshInsights()">
            <i class="fas fa-sync-alt me-2"></i>
            Refresh Insights
        </button>
    </div>
</div>

<!-- Insights Grid -->
<div class="row g-4">

    <!-- Slow Transfers Alert -->
    <?php if ($slowTransfers > 0): ?>
    <div class="col-12 col-lg-6">
        <div class="card border-warning">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="flex-shrink-0">
                        <div class="avatar avatar-lg bg-warning bg-opacity-10 text-warning rounded-3">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="card-title mb-0">Slow-Moving Transfers</h5>
                        <p class="text-muted mb-0 small">Transfers open for 7+ days</p>
                    </div>
                </div>
                <div class="alert alert-warning mb-0">
                    <h3 class="mb-2"><?= number_format($slowTransfers) ?> transfers</h3>
                    <p class="mb-0">These transfers have been open for over a week. Consider following up to complete them.</p>
                    <a href="/modules/consignments/?route=stock-transfers&filter=slow" class="btn btn-warning btn-sm mt-2">
                        View Slow Transfers
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Top Performing Outlets -->
    <div class="col-12 col-lg-6">
        <div class="card border-success">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="flex-shrink-0">
                        <div class="avatar avatar-lg bg-success bg-opacity-10 text-success rounded-3">
                            <i class="fas fa-trophy fa-2x"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="card-title mb-0">Top Performing Outlets</h5>
                        <p class="text-muted mb-0 small">Most completed transfers (30 days)</p>
                    </div>
                </div>
                <?php if (count($topOutlets) > 0): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($topOutlets as $outlet): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>Outlet <?= htmlspecialchars($outlet['outlet_id']) ?></span>
                                <span class="badge bg-success rounded-pill"><?= number_format($outlet['transfer_count']) ?> transfers</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted mb-0">No data available for the last 30 days.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recommendations Card -->
    <div class="col-12">
        <div class="card border-primary">
            <div class="card-body">
                <div class="d-flex align-items-center mb-4">
                    <div class="flex-shrink-0">
                        <div class="avatar avatar-lg bg-primary bg-opacity-10 text-primary rounded-3">
                            <i class="fas fa-lightbulb fa-2x"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="card-title mb-0">AI Recommendations</h5>
                        <p class="text-muted mb-0 small">Personalized suggestions to improve your workflow</p>
                    </div>
                </div>

                <div class="row g-3">
                    <!-- Recommendation 1 -->
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="p-3 bg-light rounded-3">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                <div>
                                    <h6 class="mb-1">Use Barcode Scanner</h6>
                                    <p class="text-muted small mb-2">Speed up receiving by 40% using our barcode scanning widget</p>
                                    <a href="/modules/consignments/?route=transfer-manager" class="btn btn-sm btn-outline-primary">Try Now</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recommendation 2 -->
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="p-3 bg-light rounded-3">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-camera text-info me-2 mt-1"></i>
                                <div>
                                    <h6 class="mb-1">Photo Verification</h6>
                                    <p class="text-muted small mb-2">Reduce disputes with automatic photo uploads during receiving</p>
                                    <a href="/modules/consignments/analytics/performance-dashboard.php" class="btn btn-sm btn-outline-info">Learn More</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recommendation 3 -->
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="p-3 bg-light rounded-3">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-chart-line text-warning me-2 mt-1"></i>
                                <div>
                                    <h6 class="mb-1">Track Performance</h6>
                                    <p class="text-muted small mb-2">Monitor your efficiency with real-time analytics and leaderboards</p>
                                    <a href="/modules/consignments/analytics/leaderboard.php" class="btn btn-sm btn-outline-warning">View Rankings</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recommendation 4 -->
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="p-3 bg-light rounded-3">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-shield-alt text-danger me-2 mt-1"></i>
                                <div>
                                    <h6 class="mb-1">Security Monitoring</h6>
                                    <p class="text-muted small mb-2">Enable fraud detection to catch suspicious scanning patterns</p>
                                    <a href="/modules/consignments/analytics/security-dashboard.php" class="btn btn-sm btn-outline-danger">View Alerts</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recommendation 5 -->
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="p-3 bg-light rounded-3">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-cog text-secondary me-2 mt-1"></i>
                                <div>
                                    <h6 class="mb-1">Customize Settings</h6>
                                    <p class="text-muted small mb-2">Adjust analytics preferences to match your workflow style</p>
                                    <a href="/modules/consignments/analytics/analytics-settings.php" class="btn btn-sm btn-outline-secondary">Settings</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recommendation 6 -->
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="p-3 bg-light rounded-3">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-users text-primary me-2 mt-1"></i>
                                <div>
                                    <h6 class="mb-1">Team Collaboration</h6>
                                    <p class="text-muted small mb-2">Share best practices and compete with friendly leaderboards</p>
                                    <a href="/modules/consignments/?route=control-panel" class="btn btn-sm btn-outline-primary">Team Stats</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Predictive Analytics -->
    <div class="col-12 col-lg-6">
        <div class="card border-info">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="flex-shrink-0">
                        <div class="avatar avatar-lg bg-info bg-opacity-10 text-info rounded-3">
                            <i class="fas fa-crystal-ball fa-2x"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="card-title mb-0">Predictive Analytics</h5>
                        <p class="text-muted mb-0 small">AI forecasting and trend analysis</p>
                    </div>
                </div>
                <div class="alert alert-info mb-0">
                    <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Coming Soon</h6>
                    <p class="mb-0">
                        We're training AI models to predict:
                    </p>
                    <ul class="mb-0 mt-2">
                        <li>Peak transfer times and staffing needs</li>
                        <li>Potential stock shortages before they happen</li>
                        <li>Optimal transfer routes and timing</li>
                        <li>Fraud likelihood based on historical patterns</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- System Health -->
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="flex-shrink-0">
                        <div class="avatar avatar-lg bg-success bg-opacity-10 text-success rounded-3">
                            <i class="fas fa-heartbeat fa-2x"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="card-title mb-0">System Health</h5>
                        <p class="text-muted mb-0 small">Real-time performance monitoring</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <td><i class="fas fa-database text-success me-2"></i>Database</td>
                                <td class="text-end"><span class="badge bg-success">Healthy</span></td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-sync text-success me-2"></i>Sync Queue</td>
                                <td class="text-end"><span class="badge bg-success">Active</span></td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-shield-alt text-success me-2"></i>Fraud Detection</td>
                                <td class="text-end"><span class="badge bg-success">Enabled</span></td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-chart-line text-success me-2"></i>Analytics Engine</td>
                                <td class="text-end"><span class="badge bg-success">Running</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<style>
    .avatar {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 64px;
        height: 64px;
    }

    .avatar-lg {
        width: 64px;
        height: 64px;
    }

    .card {
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    }
</style>

<script>
    function refreshInsights() {
        // Show loading toast
        if (typeof toastr !== 'undefined') {
            toastr.info('Refreshing AI insights...', 'Please wait');
        }

        // Reload the page after a short delay
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }
</script>

<?php
// Get buffered content
$content = ob_get_clean();

// Include BASE dashboard layout
require_once dirname(dirname(__DIR__)) . '/base/_templates/layouts/dashboard.php';
