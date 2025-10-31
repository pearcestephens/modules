<?php
/**
 * Bank Transactions Dashboard
 *
 * Main dashboard showing metrics, charts, and recent activity
 */

// Set page variables
$pageTitle = $pageTitle ?? 'Bank Transactions Dashboard';
$pageCSS = ['/modules/bank-transactions/assets/css/dashboard.css'];
$pageJS = ['/modules/bank-transactions/assets/js/dashboard.js'];

// Provide default data if not passed from controller
if (!isset($metrics) || !is_array($metrics)) {
    $metrics = [
        'total' => 0,
        'unmatched' => 0,
        'unmatched_amount' => 0,
        'matched' => 0,
        'auto_matched' => 0,
        'manual_matched' => 0,
    ];
}
if (!isset($typeBreakdown)) $typeBreakdown = [];
if (!isset($recentMatches)) $recentMatches = [];
if (!isset($autoMatchRate)) $autoMatchRate = 0;
if (!isset($avgReconciliationTime)) $avgReconciliationTime = 0;
if (!isset($date)) $date = date('Y-m-d');
?>

<!-- Dashboard Header -->
<div class="dashboard-header">
    <div>
        <h1 class="page-title">
            <i class="fas fa-university me-2"></i>
            Bank Transactions
        </h1>
        <p class="page-subtitle mb-0">Automated transaction matching and reconciliation</p>
    </div>
    <div class="header-actions">
        <input type="date"
               class="form-control form-control-sm"
               id="date-picker"
               value="<?= htmlspecialchars($date) ?>"
               style="width: auto;">
        <button class="btn btn-primary btn-sm ms-2" id="run-auto-match">
            <i class="fas fa-magic me-1"></i>
            Run Auto-Match
        </button>
    </div>
</div>

<!-- Dashboard Content -->
<div class="dashboard-content">

    <!-- Metrics Cards Row -->
    <div class="row g-3 mb-4">
        <!-- Total Transactions -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card card-metric">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="text-muted small">Total Transactions</div>
                            <h2 class="mb-0"><?= number_format($metrics['total']) ?></h2>
                        </div>
                        <div class="metric-icon bg-primary">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                    </div>
                    <div class="small text-muted">
                        <i class="fas fa-calendar"></i>
                        <?= date('M d, Y', strtotime($date)) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unmatched -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card card-metric">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="text-muted small">Unmatched</div>
                            <h2 class="mb-0 text-warning"><?= number_format($metrics['unmatched']) ?></h2>
                        </div>
                        <div class="metric-icon bg-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="small text-muted">
                        $<?= number_format($metrics['unmatched_amount'] ?? 0, 2) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Matched -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card card-metric">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="text-muted small">Matched</div>
                            <h2 class="mb-0 text-success"><?= number_format($metrics['matched']) ?></h2>
                        </div>
                        <div class="metric-icon bg-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="small text-muted">
                        $<?= number_format($metrics['matched_amount'], 2) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Needs Review -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card card-metric">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="text-muted small">Needs Review</div>
                            <h2 class="mb-0 text-info"><?= number_format($metrics['review']) ?></h2>
                        </div>
                        <div class="metric-icon bg-info">
                            <i class="fas fa-eye"></i>
                        </div>
                    </div>
                    <div class="small">
                        <a href="/modules/bank-transactions/review-queue.php" class="text-info">
                            Review Queue <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Bars Row -->
    <div class="row g-3 mb-4">
        <!-- Auto-Match Rate -->
        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-robot me-2"></i>
                            Auto-Match Rate
                        </h5>
                        <span class="badge bg-<?= $autoMatchRate >= $autoMatchTarget ? 'success' : 'warning' ?>">
                            <?= number_format($autoMatchRate, 1) ?>%
                        </span>
                    </div>
                    <div class="progress mb-2" style="height: 30px;">
                        <div class="progress-bar bg-<?= $autoMatchRate >= $autoMatchTarget ? 'success' : 'warning' ?>"
                             role="progressbar"
                             style="width: <?= $autoMatchRate ?>%"
                             aria-valuenow="<?= $autoMatchRate ?>"
                             aria-valuemin="0"
                             aria-valuemax="100">
                            <?= number_format($autoMatchRate, 1) ?>%
                        </div>
                    </div>
                    <div class="d-flex justify-content-between small text-muted">
                        <span>Target: <?= $autoMatchTarget ?>%</span>
                        <span><?= $autoMatchRate >= $autoMatchTarget ? 'Above' : 'Below' ?> Target</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reconciliation Time -->
        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-clock me-2"></i>
                            Avg Reconciliation Time
                        </h5>
                        <span class="badge bg-<?= $avgReconciliationTime <= $reconciliationTarget ? 'success' : 'warning' ?>">
                            <?= $avgReconciliationTime ?> min
                        </span>
                    </div>
                    <div class="progress mb-2" style="height: 30px;">
                        <div class="progress-bar bg-<?= $avgReconciliationTime <= $reconciliationTarget ? 'success' : 'warning' ?>"
                             role="progressbar"
                             style="width: <?= min(($avgReconciliationTime / $reconciliationTarget) * 100, 100) ?>%"
                             aria-valuenow="<?= $avgReconciliationTime ?>"
                             aria-valuemin="0"
                             aria-valuemax="<?= $reconciliationTarget ?>">
                            <?= $avgReconciliationTime ?> min
                        </div>
                    </div>
                    <div class="d-flex justify-content-between small text-muted">
                        <span>Target: ≤<?= $reconciliationTarget ?> min</span>
                        <span><?= $avgReconciliationTime <= $reconciliationTarget ? 'On' : 'Above' ?> Target</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Activity Row -->
    <div class="row g-3 mb-4">
        <!-- Transaction Type Breakdown -->
        <div class="col-12 col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        Transaction Type Breakdown
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($typeBreakdown)): ?>
                        <?php foreach ($typeBreakdown as $type): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-medium"><?= ucfirst(str_replace('_', ' ', $type['transaction_type'])) ?></span>
                                    <span class="text-muted small">
                                        <?= number_format($type['count']) ?> transactions
                                        ($<?= number_format($type['total_amount'], 2) ?>)
                                    </span>
                                </div>
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar bg-success"
                                         style="width: <?= ($type['matched_count'] / $type['count']) * 100 ?>%">
                                        <?= number_format($type['matched_count']) ?> matched
                                    </div>
                                    <div class="progress-bar bg-warning"
                                         style="width: <?= ($type['unmatched_count'] / $type['count']) * 100 ?>%">
                                        <?= number_format($type['unmatched_count']) ?> unmatched
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center mb-0">No transactions for selected date</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Matches -->
        <div class="col-12 col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history me-2"></i>
                        Recent Matches
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentMatches)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentMatches as $match): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="fw-medium"><?= htmlspecialchars($match['transaction_name']) ?></div>
                                            <div class="small text-muted">
                                                $<?= number_format($match['transaction_amount'], 2) ?>
                                                &bull;
                                                <?= date('M d, Y', strtotime($match['transaction_date'])) ?>
                                            </div>
                                        </div>
                                        <span class="badge bg-<?= $match['matched_by'] === 'AUTO' ? 'success' : 'primary' ?>">
                                            <?= $match['matched_by'] ?>
                                        </span>
                                    </div>
                                    <?php if ($match['confidence_score']): ?>
                                        <div class="mt-1">
                                            <small class="text-muted">Confidence: <?= $match['confidence_score'] ?>/300</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center mb-0">No recent matches</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="fas fa-bolt me-2"></i>
                        Quick Actions
                    </h5>
                    <div class="row g-2">
                        <div class="col-6 col-md-3">
                            <a href="/modules/bank-transactions/transactions.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-list me-1"></i>
                                View All Transactions
                            </a>
                        </div>
                        <div class="col-6 col-md-3">
                            <a href="/modules/bank-transactions/review-queue.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-eye me-1"></i>
                                Review Queue
                            </a>
                        </div>
                        <div class="col-6 col-md-3">
                            <a href="/modules/bank-transactions/reports.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-file-alt me-1"></i>
                                Reports
                            </a>
                        </div>
                        <div class="col-6 col-md-3">
                            <a href="/modules/bank-transactions/settings.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-cog me-1"></i>
                                Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- CSRF Token for AJAX -->
<input type="hidden" id="csrf-token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

<?php
// Include dashboard template footer
// Footer will include pageJS automatically
?>

<style>
.card-metric {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    transition: transform 0.2s, box-shadow 0.2s;
}

.card-metric:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}

.metric-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.card-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
}

.progress {
    border-radius: 8px;
    overflow: hidden;
}

.progress-bar {
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
}

.list-group-item {
    border-left: none;
    border-right: none;
    padding-top: 12px;
    padding-bottom: 12px;
}

.list-group-item:first-child {
    border-top: none;
}

.list-group-item:last-child {
    border-bottom: none;
}

.badge {
    font-size: 0.75rem;
    padding: 0.35em 0.65em;
}

.page-title {
    font-size: 1.75rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.page-subtitle {
    color: #6c757d;
    font-size: 0.9rem;
}

.header-actions {
    display: flex;
    align-items: center;
}

@media (max-width: 768px) {
    .header-actions {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }

    .header-actions input,
    .header-actions button {
        width: 100% !important;
    }
}
</style>

</body>
</html>
