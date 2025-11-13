<?php
/**
 * Staff Accounts - Manager Dashboard
 * Executive overview of staff accounts across the organization
 */

require_once __DIR__ . '/bootstrap.php';
cis_require_login();

// Fetch dashboard data from API
$api_url = '/modules/staff-accounts/api/manager-dashboard.php';
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "Cookie: " . $_SERVER['HTTP_COOKIE'] . "\r\n"
    ]
]);
$api_response = @file_get_contents($_SERVER['DOCUMENT_ROOT'] . $api_url, false, $context);
$data = json_decode($api_response, true);

if (!$data || !isset($data['success']) || !$data['success']) {
    $error_message = $data['message'] ?? 'Failed to load dashboard data';
}

$summary = $data['data']['summary'] ?? [];
$staff_list = $data['data']['staff_list'] ?? [];
$payment_plans = $data['data']['payment_plans'] ?? [];
$alerts = $data['data']['alerts'] ?? [];

$page_title = 'Manager Dashboard - Staff Accounts';
$page_head_extra = '<link rel="stylesheet" href="/modules/staff-accounts/css/staff-accounts.css">';
$body_class = 'staff-accounts manager-dashboard';

ob_start();
?>

<div class="container-fluid mt-4">
    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger"><strong>Error:</strong> <?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-label">Total Staff</div>
                <div class="stat-value"><?= $summary['total_staff'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card <?= ($summary['total_owed'] ?? 0) > 0 ? 'danger' : '' ?>">
                <div class="stat-label">Total Owed</div>
                <div class="stat-value">$<?= number_format($summary['total_owed'] ?? 0, 2) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card success">
                <div class="stat-label">Payments (30d)</div>
                <div class="stat-value">$<?= number_format($summary['payments_30d'] ?? 0, 2) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card <?= ($summary['active_plans'] ?? 0) > 0 ? 'warning' : '' ?>">
                <div class="stat-label">Active Plans</div>
                <div class="stat-value"><?= $summary['active_plans'] ?? 0 ?></div>
            </div>
        </div>
    </div>

    <?php if (!empty($alerts)): ?>
    <div class="content-card mb-4">
        <h5 class="mb-3">⚠️ Alerts</h5>
        <?php foreach (array_slice($alerts, 0, 5) as $alert): ?>
        <div class="alert alert-<?= $alert['severity'] ?? 'warning' ?> mb-2">
            <strong><?= htmlspecialchars($alert['title'] ?? 'Alert') ?>:</strong> <?= htmlspecialchars($alert['message'] ?? '') ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="content-card mb-4">
        <h5 class="mb-3">Quick Actions</h5>
        <div class="btn-group">
            <a href="/modules/staff-accounts/staff-reconciliation.php" class="btn btn-primary btn-custom">View All Staff</a>
            <a href="/modules/staff-accounts/send-reminders.php" class="btn btn-outline-primary btn-custom">Send Reminders</a>
            <a href="/modules/staff-accounts/export-report.php" class="btn btn-outline-secondary btn-custom">Export</a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="content-card">
                <h5 class="mb-3">Top 10 Highest Balances</h5>
                <?php if (!empty($staff_list)): ?>
                <table class="table table-sm data-table">
                    <thead>
                        <tr><th>Employee</th><th class="text-right">Balance</th><th class="text-center">Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($staff_list, 0, 10) as $staff): 
                            $balance = floatval($staff['current_balance']);
                            $days = $staff['days_since_last_payment'] ?? 0;
                            $status = $days > 90 ? 'danger' : ($days > 30 ? 'warning' : 'success');
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($staff['employee_name']) ?></strong><br><small class="text-muted">ID: <?= $staff['employee_id'] ?></small></td>
                            <td class="text-right"><strong class="<?= $balance < 0 ? 'text-danger' : 'text-success' ?>">$<?= number_format(abs($balance), 2) ?></strong></td>
                            <td class="text-center"><span class="badge badge-<?= $status ?>"><?= $days > 0 ? $days . 'd' : 'Current' ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="text-center mt-3">
                    <a href="/modules/staff-accounts/staff-reconciliation.php" class="btn btn-outline-primary btn-sm">View All</a>
                </div>
                <?php else: ?>
                <p class="text-muted text-center py-4">No staff accounts found.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-6">
            <div class="content-card">
                <h5 class="mb-3">Active Payment Plans</h5>
                <?php if (!empty($payment_plans)): ?>
                <?php foreach (array_slice($payment_plans, 0, 5) as $plan): 
                    $progress = $plan['installments_total'] > 0 ? ($plan['installments_paid'] / $plan['installments_total']) * 100 : 0;
                ?>
                <div class="mb-3 pb-3 border-bottom">
                    <div class="d-flex justify-content-between mb-2">
                        <div><strong><?= htmlspecialchars($plan['employee_name']) ?></strong><br><small class="text-muted">$<?= number_format($plan['total_amount'], 2) ?> - <?= ucfirst($plan['frequency']) ?></small></div>
                        <div class="text-right"><strong><?= $plan['installments_paid'] ?> / <?= $plan['installments_total'] ?></strong><br><small class="text-muted"><?= round($progress) ?>%</small></div>
                    </div>
                    <div class="progress" style="height: 8px;"><div class="progress-bar bg-success" style="width: <?= $progress ?>%"></div></div>
                </div>
                <?php endforeach; ?>
                <div class="text-center mt-3"><a href="/modules/staff-accounts/payment-plans.php" class="btn btn-outline-primary btn-sm">View All</a></div>
                <?php else: ?>
                <p class="text-muted text-center py-4">No active payment plans.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../shared/templates/base-layout.php';
