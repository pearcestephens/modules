<?php
/**
 * Staff Accounts - Management Dashboard
 *
 * Comprehensive overview of all staff accounts, statistics, and system health
 * Management view with account summaries, payment tracking, and quick actions
 *
 * @package CIS\Modules\StaffAccounts
 * @version 3.0.0
 */

// Load module bootstrap
require_once __DIR__ . '/bootstrap.php';

// Authentication
require_once ROOT_PATH . '/assets/functions/config.php';
cis_require_login();

// Import required classes
use CIS\Modules\StaffAccounts\StaffAccountService;
use CIS\Modules\StaffAccounts\PaymentService;

// Resolve database connection
$pdo = cis_resolve_pdo();

// ============================================================================
// FETCH DASHBOARD DATA
// ============================================================================

// Get all staff accounts with balances
$stmt = $pdo->query("
    SELECT
        u.id,
        vu.username,
        u.email,
        CONCAT(u.first_name, ' ', u.last_name) as full_name,
        vc.id as vend_customer_id,
        vc.customer_code,
        vc.balance as vend_balance,
        vc.customer_group_id,
        (SELECT COUNT(*) FROM sales_payments WHERE vend_customer_id = vc.id) as payment_count,
        (SELECT MAX(payment_date) FROM sales_payments WHERE vend_customer_id = vc.id) as last_payment_date
    FROM users u
    LEFT JOIN vend_users vu ON u.vend_id = vu.id
    LEFT JOIN vend_customers vc ON u.vend_customer_account = vc.id
    WHERE u.staff_active = 1
    ORDER BY vc.balance DESC
");
$staff_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_accounts = count($staff_accounts);
$total_outstanding = array_sum(array_column($staff_accounts, 'vend_balance'));
$accounts_with_balance = count(array_filter($staff_accounts, function($a) { return $a['vend_balance'] > 0; }));
$average_balance = $accounts_with_balance > 0 ? $total_outstanding / $accounts_with_balance : 0;

// Get recent payments (last 30 days)
$stmt = $pdo->query("
    SELECT
        sp.id,
        sp.amount,
        sp.payment_date,
        sp.name as payment_method,
        sp.sale_status as status,
        sp.outlet_name,
        CONCAT(u.first_name, ' ', u.last_name) as staff_name
    FROM sales_payments sp
    JOIN vend_customers vc ON sp.vend_customer_id = vc.id
    JOIN users u ON vc.id = u.vend_customer_account
    WHERE sp.payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND u.staff_active = 1
    ORDER BY sp.payment_date DESC
    LIMIT 10
");
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Get payment statistics (last 30 days)
$stmt = $pdo->query("
    SELECT
        COUNT(*) as payment_count,
        SUM(sp.amount) as total_amount,
        AVG(sp.amount) as average_amount,
        sp.name as payment_method,
        sp.sale_status as status
    FROM sales_payments sp
    JOIN vend_customers vc ON sp.vend_customer_id = vc.id
    JOIN users u ON vc.id = u.vend_customer_account
    WHERE sp.payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND u.staff_active = 1
    GROUP BY sp.name, sp.sale_status
");
$payment_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_payments_30d = array_sum(array_column($payment_stats, 'total_amount'));
$payment_count_30d = array_sum(array_column($payment_stats, 'payment_count'));

// Get accounts needing attention (balance > $500)
$high_balance_accounts = array_filter($staff_accounts, function($a) {
    return $a['vend_balance'] > 500;
});

// Get unmapped employees (no vend_customer_account set)
$stmt = $pdo->query("
    SELECT COUNT(*) as unmapped_count
    FROM users u
    WHERE u.staff_active = 1
    AND (u.vend_customer_account IS NULL OR u.vend_customer_account = '')
");
$unmapped_count = $stmt->fetchColumn();

// ============================================================================
// RENDER VIEW WITH CIS TEMPLATE
// ============================================================================

// Set page variables for CIS template
$page_title = 'Staff Accounts Management Dashboard';
$page_head_extra = '<link rel="stylesheet" href="/assets/css/staff-accounts.css">';
$body_class = 'staff-accounts dashboard';

// Start output buffering
ob_start();
?>

<div class="container-fluid staff-accounts">
    <!-- Page Header -->
    <div class="page-header mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="h3 mb-0">
                    <i class="fas fa-users-cog"></i> Staff Accounts Management
                </h1>
                <p class="text-muted mb-0">Comprehensive overview and account management</p>
            </div>
            <div class="col-md-6 text-right">
                <a href="views/staff-list.php" class="btn btn-primary">
                    <i class="fas fa-list"></i> View All Accounts
                </a>
                <a href="views/make-payment.php" class="btn btn-success">
                    <i class="fas fa-credit-card"></i> Make Payment
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Accounts</h6>
                            <h2 class="mb-0"><?php echo number_format($total_accounts); ?></h2>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-users fa-3x opacity-50"></i>
                        </div>
                    </div>
                    <?php if ($unmapped_count > 0): ?>
                    <small class="mt-2 d-block">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $unmapped_count; ?> unmapped
                    </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Outstanding</h6>
                            <h2 class="mb-0">$<?php echo number_format($total_outstanding, 2); ?></h2>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-circle fa-3x opacity-50"></i>
                        </div>
                    </div>
                    <small class="mt-2 d-block">
                        <?php echo $accounts_with_balance; ?> accounts with balance
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Payments (30d)</h6>
                            <h2 class="mb-0">$<?php echo number_format($total_payments_30d, 2); ?></h2>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign fa-3x opacity-50"></i>
                        </div>
                    </div>
                    <small class="mt-2 d-block">
                        <?php echo number_format($payment_count_30d); ?> transactions
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Average Balance</h6>
                            <h2 class="mb-0">$<?php echo number_format($average_balance, 2); ?></h2>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-balance-scale fa-3x opacity-50"></i>
                        </div>
                    </div>
                    <small class="mt-2 d-block">
                        For accounts with balance
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- PROMINENT ACTION BUTTON -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-info" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1" style="color: white; font-weight: bold;">
                            <i class="fas fa-clipboard-list me-2"></i>PAYMENTS TO BE APPLIED
                        </h4>
                        <p class="mb-0" style="color: rgba(255,255,255,0.9);">
                            View payroll deductions and apply pending payments to staff accounts
                        </p>
                    </div>
                    <div>
                        <a href="views/apply-payments.php" class="btn btn-light btn-lg" style="font-size: 18px; font-weight: bold; padding: 15px 30px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
                            <i class="fas fa-arrow-right me-2"></i>GO TO PAYMENTS
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- High Balance Accounts -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle"></i> Accounts Needing Attention
                    </h5>
                    <small>Balances over $500</small>
                </div>
                <div class="card-body p-0">
                    <?php if (count($high_balance_accounts) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Staff Member</th>
                                    <th>Customer Code</th>
                                    <th class="text-right">Balance</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($high_balance_accounts, 0, 10) as $account): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($account['full_name'] ?: $account['username']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($account['email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($account['customer_code']); ?></td>
                                    <td class="text-right">
                                        <span class="badge badge-danger">
                                            $<?php echo number_format($account['vend_balance'], 2); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <a href="views/my-account.php?user_id=<?php echo $account['id']; ?>"
                                           class="btn btn-sm btn-outline-primary" title="View Account">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="p-4 text-center text-muted">
                        <i class="fas fa-check-circle fa-3x mb-3"></i>
                        <p class="mb-0">No accounts with balance over $500</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-history"></i> Recent Payments
                    </h5>
                    <small>Last 10 payments (30 days)</small>
                </div>
                <div class="card-body p-0">
                    <?php if (count($recent_payments) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Staff Member</th>
                                    <th class="text-right">Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td><?php echo date('M d', strtotime($payment['payment_date'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($payment['staff_name']); ?></strong>
                                    </td>
                                    <td class="text-right">
                                        $<?php echo number_format($payment['amount'], 2); ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary">
                                            <?php echo htmlspecialchars($payment['payment_method']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = $payment['status'] === 'completed' ? 'success' : 'warning';
                                        ?>
                                        <span class="badge badge-<?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($payment['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="p-4 text-center text-muted">
                        <i class="fas fa-info-circle fa-3x mb-3"></i>
                        <p class="mb-0">No payments in the last 30 days</p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center">
                    <a href="views/payment-history.php" class="btn btn-sm btn-outline-success">
                        <i class="fas fa-list"></i> View All Payment History
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- All Staff Accounts (Top 20 by Balance) -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">
                                <i class="fas fa-users"></i> All Staff Accounts
                            </h5>
                            <small>Top 20 accounts by outstanding balance</small>
                        </div>
                        <a href="views/staff-list.php" class="btn btn-light btn-sm">
                            <i class="fas fa-expand"></i> View Full List
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Staff Member</th>
                                    <th>Customer Code</th>
                                    <th>Group</th>
                                    <th class="text-right">Balance</th>
                                    <th class="text-center">Payments</th>
                                    <th>Last Payment</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($staff_accounts, 0, 20) as $account): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($account['full_name'] ?: $account['username']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($account['email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($account['customer_code'] ?: '-'); ?></td>
                                    <td>
                                        <?php if ($account['customer_group_id']): ?>
                                        <span class="badge badge-secondary">
                                            <?php echo htmlspecialchars($account['customer_group_id']); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right">
                                        <?php
                                        $balance = floatval($account['vend_balance']);
                                        if ($balance > 500):
                                            $badge_class = 'danger';
                                        elseif ($balance > 100):
                                            $badge_class = 'warning';
                                        elseif ($balance > 0):
                                            $badge_class = 'info';
                                        else:
                                            $badge_class = 'success';
                                        endif;
                                        ?>
                                        <span class="badge badge-<?php echo $badge_class; ?>">
                                            $<?php echo number_format($balance, 2); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-light">
                                            <?php echo number_format($account['payment_count']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($account['last_payment_date']): ?>
                                        <small><?php echo date('M d, Y', strtotime($account['last_payment_date'])); ?></small>
                                        <?php else: ?>
                                        <span class="text-muted">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="views/my-account.php?user_id=<?php echo $account['id']; ?>"
                                               class="btn btn-outline-primary" title="View Account">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($balance > 0): ?>
                                            <a href="views/make-payment.php?user_id=<?php echo $account['id']; ?>"
                                               class="btn btn-outline-success" title="Make Payment">
                                                <i class="fas fa-credit-card"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions & Info -->
    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="views/apply-payments.php" class="list-group-item list-group-item-action" style="background: #d4edda; border-left: 4px solid #28a745; font-weight: bold;">
                            <i class="fas fa-clipboard-list text-success"></i> 🎯 PAYMENTS TO BE APPLIED
                        </a>
                        <a href="views/make-payment.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-credit-card text-success"></i> Process Payment
                        </a>
                        <a href="views/staff-list.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-users text-primary"></i> Manage All Accounts
                        </a>
                        <a href="views/payment-history.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-history text-info"></i> Payment History
                        </a>
                        <a href="views/payment-plans.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar-alt text-warning"></i> Payment Plans
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-pie"></i> Payment Method Breakdown (30 Days)</h6>
                </div>
                <div class="card-body">
                    <?php if (count($payment_stats) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Payment Method</th>
                                    <th>Status</th>
                                    <th class="text-center">Count</th>
                                    <th class="text-right">Total Amount</th>
                                    <th class="text-right">Average</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payment_stats as $stat): ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-secondary">
                                            <?php echo htmlspecialchars($stat['payment_method']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = $stat['status'] === 'completed' ? 'success' : 'warning';
                                        ?>
                                        <span class="badge badge-<?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($stat['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><?php echo number_format($stat['payment_count']); ?></td>
                                    <td class="text-right">$<?php echo number_format($stat['total_amount'], 2); ?></td>
                                    <td class="text-right">$<?php echo number_format($stat['average_amount'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted mb-0">No payment data for the last 30 days</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Capture output
$content = ob_get_clean();

// Set page title
$pageTitle = 'Staff Accounts Management Dashboard';

// Load modern CIS dashboard template (same as consignments)
// Prefer new templates path; keep a fallback to modern theme layout
$__baseDir = dirname(__DIR__) . '/base';
$__tplPrimary = $__baseDir . '/templates/layouts/dashboard-modern.php';
$__tplModern  = $__baseDir . '/templates/themes/modern/layouts/dashboard.php';

if (file_exists($__tplPrimary)) {
    require_once $__tplPrimary;
} elseif (file_exists($__tplModern)) {
    require_once $__tplModern;
} else {
    // Last-resort fallback: render content without chrome to avoid hard fatal
    echo $content;
}
?>
