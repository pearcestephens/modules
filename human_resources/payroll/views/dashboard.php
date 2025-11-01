<?php
/**
 * Payroll Dashboard - Comprehensive Management Interface
 *
 * Features:
 * - Timesheet Amendments
 * - Wage Discrepancies (AI-powered)
 * - Leave Requests
 * - Bonuses (Vape Drops, Google Reviews, Monthly)
 * - Vend Account Payments
 *
 * @package HumanResources\Payroll\Views
 */

$pageTitle = 'Payroll Dashboard';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/human_resources/payroll/views/layouts/header.php';
?>

<style>
/* Dashboard Specific Styles */
.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    margin: -1rem -1rem 2rem -1rem;
    border-radius: 0.5rem 0.5rem 0 0;
}

.dashboard-header h1 {
    margin: 0;
    font-size: 2rem;
    font-weight: 600;
}

.dashboard-header .subtitle {
    opacity: 0.9;
    font-size: 0.95rem;
    margin-top: 0.5rem;
}

.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 1.25rem;
    transition: all 0.2s;
}

.stat-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.stat-card .stat-label {
    font-size: 0.875rem;
    color: #64748b;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.stat-card .stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
}

.stat-card .stat-subtext {
    font-size: 0.75rem;
    color: #94a3b8;
    margin-top: 0.5rem;
}

.stat-card.urgent {
    border-left: 4px solid #ef4444;
}

.stat-card.pending {
    border-left: 4px solid #f59e0b;
}

.stat-card.success {
    border-left: 4px solid #10b981;
}

.nav-tabs .nav-link {
    color: #64748b;
    font-weight: 500;
    border: none;
    border-bottom: 3px solid transparent;
    padding: 0.75rem 1.5rem;
}

.nav-tabs .nav-link:hover {
    border-bottom-color: #cbd5e1;
}

.nav-tabs .nav-link.active {
    color: #667eea;
    border-bottom-color: #667eea;
    background: transparent;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e2e8f0;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.badge-counter {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.5rem;
    height: 1.5rem;
    padding: 0 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 1rem;
    margin-left: 0.5rem;
}

.data-table {
    width: 100%;
    margin-bottom: 1rem;
}

.data-table thead th {
    background: #f8fafc;
    color: #475569;
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.75rem;
    border-bottom: 2px solid #e2e8f0;
}

.data-table tbody td {
    padding: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
}

.data-table tbody tr:hover {
    background: #f8fafc;
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.pending {
    background: #fef3c7;
    color: #92400e;
}

.status-badge.approved {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.declined {
    background: #fee2e2;
    color: #991b1b;
}

.status-badge.ai-review {
    background: #dbeafe;
    color: #1e40af;
}

.status-badge.urgent {
    background: #fecaca;
    color: #7f1d1d;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

.loading-spinner {
    display: inline-block;
    width: 1.5rem;
    height: 1.5rem;
    border: 3px solid #f3f4f6;
    border-top-color: #667eea;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: #94a3b8;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.toast-container {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 9999;
}

.toast {
    background: white;
    border-radius: 0.5rem;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    padding: 1rem;
    margin-bottom: 0.5rem;
    min-width: 300px;
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.toast.success { border-left: 4px solid #10b981; }
.toast.error { border-left: 4px solid #ef4444; }
.toast.warning { border-left: 4px solid #f59e0b; }
.toast.info { border-left: 4px solid #3b82f6; }

.ai-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    color: #6366f1;
}

.ai-indicator i {
    font-size: 0.875rem;
}

.confidence-bar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.confidence-bar-fill {
    height: 0.5rem;
    background: #e2e8f0;
    border-radius: 9999px;
    overflow: hidden;
    flex: 1;
}

.confidence-bar-value {
    height: 100%;
    background: linear-gradient(90deg, #10b981 0%, #059669 100%);
    transition: width 0.3s;
}

.confidence-bar-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #475569;
    min-width: 3rem;
    text-align: right;
}
</style>

<div class="container-fluid">
    <div class="dashboard-header">
        <h1><i class="fas fa-tachometer-alt me-2"></i> Payroll Dashboard</h1>
        <div class="subtitle">Comprehensive payroll management with AI-powered automation</div>
    </div>

    <!-- Statistics Overview -->
    <div class="stats-overview" id="statsOverview">
        <div class="stat-card pending">
            <div class="stat-label">Pending Actions</div>
            <div class="stat-value" id="statPendingTotal">
                <span class="loading-spinner"></span>
            </div>
            <div class="stat-subtext">Requires attention</div>
        </div>

        <div class="stat-card urgent">
            <div class="stat-label">Urgent Items</div>
            <div class="stat-value" id="statUrgentTotal">
                <span class="loading-spinner"></span>
            </div>
            <div class="stat-subtext">High priority</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">AI Reviews</div>
            <div class="stat-value" id="statAiReviewTotal">
                <span class="loading-spinner"></span>
            </div>
            <div class="stat-subtext">Awaiting AI analysis</div>
        </div>

        <div class="stat-card success">
            <div class="stat-label">Auto Approved</div>
            <div class="stat-value" id="statAutoApproved">
                <span class="loading-spinner"></span>
            </div>
            <div class="stat-subtext">Last 7 days</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Total Bonuses</div>
            <div class="stat-value" id="statBonusesTotal">
                <span class="loading-spinner"></span>
            </div>
            <div class="stat-subtext">Pending payment</div>
        </div>
    </div>

    <!-- RATE LIMIT MONITORING WIDGET -->
    <?php require_once __DIR__ . '/widgets/rate_limits.php'; ?>

    <!-- RECONCILIATION QUICK LINK -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-info" role="alert">
                <i class="fas fa-balance-scale me-2"></i>
                <strong>Reconciliation Dashboard:</strong> View variances between CIS, Xero, and Deputy
                <a href="?view=reconciliation" class="btn btn-sm btn-info float-end">
                    <i class="fas fa-chart-line me-1"></i> View Reconciliation
                </a>
            </div>
        </div>
    </div>

    <!-- Main Tabs -->
    <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="amendments-tab" data-bs-toggle="tab" data-bs-target="#amendments" type="button" role="tab">
                <i class="fas fa-clock me-1"></i> Timesheet Amendments
                <span class="badge-counter bg-warning text-dark" id="badgeAmendments">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="discrepancies-tab" data-bs-toggle="tab" data-bs-target="#discrepancies" type="button" role="tab">
                <i class="fas fa-exclamation-triangle me-1"></i> Wage Discrepancies
                <span class="badge-counter bg-danger text-white" id="badgeDiscrepancies">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="bonuses-tab" data-bs-toggle="tab" data-bs-target="#bonuses" type="button" role="tab">
                <i class="fas fa-gift me-1"></i> Bonuses
                <span class="badge-counter bg-success text-white" id="badgeBonuses">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="vend-payments-tab" data-bs-toggle="tab" data-bs-target="#vend-payments" type="button" role="tab">
                <i class="fas fa-credit-card me-1"></i> Vend Payments
                <span class="badge-counter bg-info text-white" id="badgeVendPayments">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="leave-tab" data-bs-toggle="tab" data-bs-target="#leave" type="button" role="tab">
                <i class="fas fa-umbrella-beach me-1"></i> Leave Requests
                <span class="badge-counter bg-primary text-white" id="badgeLeave">0</span>
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content mt-4" id="dashboardTabContent">
        <!-- AMENDMENTS SECTION -->
        <div class="tab-pane fade show active" id="amendments" role="tabpanel">
            <div class="section-header">
                <h2 class="section-title">Timesheet Amendments</h2>
                <button class="btn btn-primary" onclick="Dashboard.showCreateAmendmentModal()">
                    <i class="fas fa-plus me-1"></i> Create Amendment
                </button>
            </div>

            <div id="amendmentsContent">
                <div class="text-center p-5">
                    <div class="loading-spinner" style="width: 3rem; height: 3rem;"></div>
                    <p class="mt-3">Loading amendments...</p>
                </div>
            </div>
        </div>

        <!-- DISCREPANCIES SECTION -->
        <div class="tab-pane fade" id="discrepancies" role="tabpanel">
            <div class="section-header">
                <h2 class="section-title">Wage Discrepancies</h2>
                <button class="btn btn-primary" onclick="Dashboard.showSubmitDiscrepancyModal()">
                    <i class="fas fa-plus me-1"></i> Submit Discrepancy
                </button>
            </div>

            <div id="discrepanciesContent">
                <div class="text-center p-5">
                    <div class="loading-spinner" style="width: 3rem; height: 3rem;"></div>
                    <p class="mt-3">Loading discrepancies...</p>
                </div>
            </div>
        </div>

        <!-- BONUSES SECTION -->
        <div class="tab-pane fade" id="bonuses" role="tabpanel">
            <div class="section-header">
                <h2 class="section-title">Bonuses</h2>
                <button class="btn btn-primary" onclick="Dashboard.showCreateBonusModal()">
                    <i class="fas fa-plus me-1"></i> Create Bonus
                </button>
            </div>

            <!-- Bonus Type Tabs -->
            <ul class="nav nav-pills mb-3" id="bonusTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="monthly-bonuses-tab" data-bs-toggle="pill" data-bs-target="#monthly-bonuses" type="button" role="tab">
                        Monthly Bonuses
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="vape-drops-tab" data-bs-toggle="pill" data-bs-target="#vape-drops" type="button" role="tab">
                        Vape Drops ($6.00/drop)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="google-reviews-tab" data-bs-toggle="pill" data-bs-target="#google-reviews" type="button" role="tab">
                        Google Reviews
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="bonusTabContent">
                <div class="tab-pane fade show active" id="monthly-bonuses" role="tabpanel">
                    <div id="monthlyBonusesContent">
                        <div class="text-center p-5">
                            <div class="loading-spinner" style="width: 3rem; height: 3rem;"></div>
                            <p class="mt-3">Loading monthly bonuses...</p>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="vape-drops" role="tabpanel">
                    <div id="vapeDropsContent">
                        <div class="text-center p-5">
                            <div class="loading-spinner" style="width: 3rem; height: 3rem;"></div>
                            <p class="mt-3">Loading vape drops...</p>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="google-reviews" role="tabpanel">
                    <div id="googleReviewsContent">
                        <div class="text-center p-5">
                            <div class="loading-spinner" style="width: 3rem; height: 3rem;"></div>
                            <p class="mt-3">Loading Google reviews...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- VEND PAYMENTS SECTION -->
        <div class="tab-pane fade" id="vend-payments" role="tabpanel">
            <div class="section-header">
                <h2 class="section-title">Vend Account Payments</h2>
                <?php if ($isAdmin): ?>
                <button class="btn btn-secondary" onclick="Dashboard.showVendPaymentStats()">
                    <i class="fas fa-chart-bar me-1"></i> Statistics
                </button>
                <?php endif; ?>
            </div>

            <div id="vendPaymentsContent">
                <div class="text-center p-5">
                    <div class="loading-spinner" style="width: 3rem; height: 3rem;"></div>
                    <p class="mt-3">Loading Vend payments...</p>
                </div>
            </div>
        </div>

        <!-- LEAVE SECTION -->
        <div class="tab-pane fade" id="leave" role="tabpanel">
            <div class="section-header">
                <h2 class="section-title">Leave Requests</h2>
                <button class="btn btn-primary" onclick="Dashboard.showCreateLeaveModal()">
                    <i class="fas fa-plus me-1"></i> Request Leave
                </button>
            </div>

            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">Leave Balances</h6>
                            <div id="leaveBalancesContent">
                                <div class="loading-spinner"></div> Loading balances...
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="leaveRequestsContent">
                <div class="text-center p-5">
                    <div class="loading-spinner" style="width: 3rem; height: 3rem;"></div>
                    <p class="mt-3">Loading leave requests...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Include section-specific JS -->
<script src="/modules/human_resources/payroll/assets/js/dashboard.js"></script>

<script>
// Initialize dashboard on page load
document.addEventListener('DOMContentLoaded', function() {
    Dashboard.init();
});
</script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/human_resources/payroll/views/layouts/footer.php'; ?>
