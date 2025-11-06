

<?php
/**
 * Staff Performance Dashboard - Main Leaderboard View
 *
 * Primary interface showing:
 * - Personal performance stats
 * - Overall leaderboard rankings
 * - Active weekly competition
 * - Performance trend chart
 *
 * @package CIS\Modules\StaffPerformance
 * @version 1.0.0
 */

// Initialize widgets
$widgets = new \CIS\StaffPerformance\PerformanceWidgets($db);

// Get current user's performance data
$userStats = $widgets->getStaffStats($current_user_id);
$topPerformers = $widgets->getTopPerformers(10);
$activeCompetition = $widgets->getActiveCompetition();
$monthlyPerformance = $widgets->getMonthlyPerformance($current_user_id, 6);

// Page metadata
$pageTitle = "Performance Dashboard";
$pageDescription = "Track your Google Reviews performance, compete with your team, and earn rewards!";
$pageBreadcrumbs = [
    ['title' => 'Home', 'url' => '/'],
    ['title' => 'Staff Performance', 'url' => STAFF_PERFORMANCE_MODULE_PATH],
    ['title' => 'Dashboard', 'url' => '']
];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - CIS Staff Portal</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6.7.1 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- Module CSS -->
    <link href="<?php echo STAFF_PERFORMANCE_CSS_PATH; ?>/style.css" rel="stylesheet">

    <style>
        :root {
            --gold: #FFD700;
            --silver: #C0C0C0;
            --bronze: #CD7F32;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
        }

        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
        }

        .stats-card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
        }

        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .leaderboard-position {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--gold) 0%, var(--bronze) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .achievement-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            margin: 0.25rem;
        }

        .badge-gold { background-color: var(--gold); color: #000; }
        .badge-silver { background-color: var(--silver); color: #000; }
        .badge-bronze { background-color: var(--bronze); color: #fff; }

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .competition-banner {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
        }

        .progress-bar-animated {
            animation: progress-animation 1s ease-in-out;
        }

        @keyframes progress-animation {
            from { width: 0%; }
        }
    </style>
</head>
<body>

<!-- CIS Header / Navigation -->
<?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/views/header.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">

        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2">
            <?php include __DIR__ . '/partials/sidebar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">

            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <h1 class="mb-2">
                    <i class="fas fa-chart-line me-2"></i>
                    Performance Dashboard
                </h1>
                <p class="mb-0 opacity-75">
                    <?php echo date('l, F j, Y'); ?> • Welcome back, <?php echo htmlspecialchars($_SESSION['userName'] ?? 'Team Member'); ?>!
                </p>
            </div>

            <!-- Active Competition Banner -->
            <?php if ($activeCompetition): ?>
            <div class="competition-banner">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-1">
                            <i class="fas fa-trophy me-2"></i>
                            <?php echo htmlspecialchars($activeCompetition['name']); ?>
                        </h3>
                        <p class="mb-0 opacity-75">
                            <?php echo htmlspecialchars($activeCompetition['description']); ?>
                        </p>
                        <p class="mb-0 mt-2">
                            <i class="fas fa-clock me-1"></i>
                            Ends <?php echo date('M j, Y g:ia', strtotime($activeCompetition['end_date'])); ?>
                        </p>
                    </div>
                    <div class="text-end">
                        <div class="h2 mb-0">🥇 $<?php echo number_format($activeCompetition['prize_amount_first'], 0); ?></div>
                        <small class="opacity-75">First Prize</small>
                        <div class="mt-2">
                            <a href="?page=competitions" class="btn btn-light btn-sm">
                                View Competition <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Personal Stats Cards -->
            <div class="quick-stats">

                <!-- Total Reviews Card -->
                <div class="stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted small mb-1">Total Reviews</div>
                                <h3 class="mb-0"><?php echo number_format($userStats['total_reviews'] ?? 0); ?></h3>
                                <small class="text-success">
                                    <i class="fas fa-arrow-up"></i>
                                    +<?php echo $userStats['reviews_this_month'] ?? 0; ?> this month
                                </small>
                            </div>
                            <div class="stat-icon" style="background-color: rgba(40, 167, 69, 0.1); color: var(--success);">
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vape Drops Card -->
                <div class="stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted small mb-1">Vape Drops</div>
                                <h3 class="mb-0"><?php echo number_format($userStats['total_drops'] ?? 0); ?></h3>
                                <small class="text-info">
                                    <i class="fas fa-arrow-up"></i>
                                    +<?php echo $userStats['drops_this_month'] ?? 0; ?> this month
                                </small>
                            </div>
                            <div class="stat-icon" style="background-color: rgba(23, 162, 184, 0.1); color: var(--info);">
                                <i class="fas fa-box"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Earnings Card -->
                <div class="stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted small mb-1">Total Earnings</div>
                                <h3 class="mb-0">$<?php echo number_format($userStats['total_earnings'] ?? 0, 2); ?></h3>
                                <small class="text-warning">
                                    <i class="fas fa-dollar-sign"></i>
                                    $<?php echo number_format($userStats['earnings_this_month'] ?? 0, 2); ?> this month
                                </small>
                            </div>
                            <div class="stat-icon" style="background-color: rgba(255, 193, 7, 0.1); color: var(--warning);">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Leaderboard Rank Card -->
                <div class="stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted small mb-1">Your Rank</div>
                                <h3 class="mb-0 leaderboard-position">#<?php echo $userStats['rank'] ?? '—'; ?></h3>
                                <small class="text-muted">
                                    <?php if (($userStats['rank'] ?? 999) <= 3): ?>
                                        🏆 Top Performer!
                                    <?php elseif (($userStats['rank'] ?? 999) <= 10): ?>
                                        🔥 Top 10!
                                    <?php else: ?>
                                        Keep climbing!
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="stat-icon" style="background-color: rgba(220, 53, 69, 0.1); color: var(--danger);">
                                <i class="fas fa-trophy"></i>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Main Content Row -->
            <div class="row">

                <!-- Leaderboard Column -->
                <div class="col-lg-6 mb-4">
                    <div class="card stats-card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fas fa-medal me-2 text-warning"></i>
                                Top Performers
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php echo $widgets->renderLeaderboard($topPerformers); ?>
                            <div class="text-center mt-3">
                                <a href="?page=leaderboard" class="btn btn-outline-primary btn-sm">
                                    View Full Leaderboard <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Chart Column -->
                <div class="col-lg-6 mb-4">
                    <div class="card stats-card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-area me-2 text-info"></i>
                                Your Performance Trend
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php echo $widgets->renderPerformanceChart($monthlyPerformance, 'userPerformanceChart'); ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Active Competition Details -->
            <?php if ($activeCompetition): ?>
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card stats-card">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-fire me-2 text-danger"></i>
                                    Current Competition: <?php echo htmlspecialchars($activeCompetition['name']); ?>
                                </h5>
                                <span class="badge bg-success">Active</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php
                            $competitionProgress = $widgets->getCompetitionProgress($activeCompetition['competition_id'], $current_user_id);
                            echo $widgets->renderActiveCompetition($activeCompetition, $competitionProgress);
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="card stats-card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-bolt me-2 text-warning"></i>
                                Quick Actions
                            </h5>
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <a href="?page=competitions" class="btn btn-primary">
                                    <i class="fas fa-trophy me-2"></i>View Competitions
                                </a>
                                <a href="?page=achievements" class="btn btn-success">
                                    <i class="fas fa-award me-2"></i>My Achievements
                                </a>
                                <a href="?page=history" class="btn btn-info">
                                    <i class="fas fa-history me-2"></i>View History
                                </a>
                                <a href="?page=leaderboard" class="btn btn-warning">
                                    <i class="fas fa-list-ol me-2"></i>Full Leaderboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<!-- CIS Footer -->
<?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/views/footer.php'; ?>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Module JS -->
<script src="<?php echo STAFF_PERFORMANCE_JS_PATH; ?>/dashboard.js"></script>

<script>
// Auto-refresh stats every 60 seconds
setInterval(function() {
    // Refresh stats via AJAX (implement API endpoint)
    console.log('Stats refresh interval - implement API call');
}, 60000);

// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>

</body>
</html>
