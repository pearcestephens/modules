<?php
/**
 * Staff Accounts - My Account Page
 * Self-service portal for staff to view and manage their account
 * 
 * Features:
 * - Current balance display
 * - Purchase & payment history
 * - Make payments
 * - Setup payment plans
 * - Manage saved cards
 * - Download statements
 */

// Bootstrap the module
require_once __DIR__ . '/bootstrap.php';

// Require authentication (CIS standard)
cis_require_login();

// Get current user ID
$user_id = $_SESSION['userID'];

// Fetch user account details - USING ACTUAL COLUMN NAMES (NO ALIASES)
$stmt = $pdo->prepare("
    SELECT 
        sar.user_id,
        sar.employee_name,
        sar.vend_balance,
        sar.total_allocated,
        sar.total_payments_ytd,
        sar.last_reconciled_at,
        sar.last_payment_date,
        sar.vend_balance_updated_at,
        (SELECT COUNT(*) FROM staff_payment_plans WHERE user_id = sar.user_id AND status = 'active') as active_plans
    FROM staff_account_reconciliation sar
    WHERE sar.user_id = ?
    LIMIT 1
");
$stmt->execute([$user_id]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

// If no account found, create placeholder
if (!$account) {
    $account = [
        'employee_name' => $_SESSION['name'] ?? 'User',
        'current_balance' => 0.00,
        'total_purchases' => 0.00,
        'total_payments' => 0.00,
        'active_plans' => 0
    ];
}

// Format balance for display - USING ACTUAL COLUMN NAME: vend_balance
$balance = floatval($account['vend_balance']);
$balance_class = $balance < 0 ? 'negative' : 'positive';
$balance_text = $balance < 0 ? 'Amount Owed' : 'Credit Balance';

// Fetch recent transactions (last 10)
$stmt = $pdo->prepare("
    SELECT 
        t.id as transaction_id,
        t.amount,
        t.transaction_type,
        t.request_id as description,
        t.created_at,
        'online' as payment_method
    FROM staff_payment_transactions t
    WHERE t.user_id = ?
    ORDER BY t.created_at DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active payment plans
$stmt = $pdo->prepare("
    SELECT 
        pp.id as plan_id,
        pp.total_amount,
        pp.installment_amount,
        pp.frequency,
        pp.created_at as start_date,
        pp.completed_at as end_date,
        pp.status,
        (SELECT COUNT(*) FROM staff_payment_plan_installments WHERE plan_id = pp.id AND status = 'paid') as paid_count,
        (SELECT COUNT(*) FROM staff_payment_plan_installments WHERE plan_id = pp.id) as total_count
    FROM staff_payment_plans pp
    WHERE pp.user_id = ? AND pp.status = 'active'
    ORDER BY pp.created_at DESC
");
$stmt->execute([$user_id]);
$payment_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch saved cards
$stmt = $pdo->prepare("
    SELECT id as card_id, card_type as card_brand, last_four_digits as last_four, expiry_month, expiry_year, 'Cardholder' as cardholder_name, is_default
    FROM staff_saved_cards
    WHERE user_id = ? AND is_active = 1
    ORDER BY is_default DESC, created_at DESC
");
$stmt->execute([$user_id]);
$saved_cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set page variables for template
$page_title = 'My Account';
$page_head_extra = '<link rel="stylesheet" href="/modules/staff-accounts/css/staff-accounts.css">';
$body_class = 'staff-accounts my-account';

// Start output buffer for page content
ob_start();
?>

<div class="container-fluid staff-accounts"
    <!-- Balance Card -->
    <div class="balance-card <?= $balance_class ?>">
        <div class="balance-label"><?= htmlspecialchars($balance_text) ?></div>
        <div class="balance-amount">$<?= number_format(abs($balance), 2) ?></div>
        <div class="mt-3">
            <small>Last updated: <?= $account['balance_updated_at'] ? date('M j, Y g:i A', strtotime($account['balance_updated_at'])) : 'Never' ?></small>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-label">Total Purchases</div>
                <div class="stat-value">$<?= number_format($account['total_allocated'], 2) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card success">
                <div class="stat-label">Total Payments</div>
                <div class="stat-value">$<?= number_format($account['total_payments_ytd'], 2) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card <?= $account['active_plans'] > 0 ? 'warning' : '' ?>">
                <div class="stat-label">Active Payment Plans</div>
                <div class="stat-value"><?= $account['active_plans'] ?></div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="content-card mb-4">
        <h5 class="mb-3">Quick Actions</h5>
        <div class="btn-group" role="group">
            <a href="/modules/staff-accounts/make-payment.php" class="btn btn-primary btn-custom">Make a Payment</a>
            <a href="/modules/staff-accounts/setup-plan.php" class="btn btn-outline-primary btn-custom">Setup Payment Plan</a>
            <a href="/modules/staff-accounts/download-statement.php" class="btn btn-outline-secondary btn-custom">Download Statement</a>
        </div>
    </div>

    <!-- Active Payment Plans -->
    <?php if (count($payment_plans) > 0): ?>
    <div class="content-card mb-4">
        <h5 class="mb-3">Active Payment Plans</h5>
        <?php foreach ($payment_plans as $plan): 
            $progress = $plan['total_count'] > 0 ? ($plan['paid_count'] / $plan['total_count']) * 100 : 0;
        ?>
        <div class="mb-3 pb-3 border-bottom">
            <div class="d-flex justify-content-between mb-2">
                <div>
                    <strong>$<?= number_format($plan['total_amount'], 2) ?></strong> 
                    <span class="text-muted">- <?= ucfirst($plan['frequency']) ?> payments of $<?= number_format($plan['installment_amount'], 2) ?></span>
                </div>
                <div class="text-muted">
                    <?= $plan['paid_count'] ?> / <?= $plan['total_count'] ?> paid
                </div>
            </div>
            <div class="progress" style="height: 8px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress ?>%" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <small class="text-muted">Started <?= date('M j, Y', strtotime($plan['start_date'])) ?> • Ends <?= date('M j, Y', strtotime($plan['end_date'])) ?></small>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Saved Cards -->
    <?php if (count($saved_cards) > 0): ?>
    <div class="content-card mb-4">
        <h5 class="mb-3">Saved Payment Methods</h5>
        <div class="row">
            <?php foreach ($saved_cards as $card): ?>
            <div class="col-md-6 mb-3">
                <div class="border rounded p-3 <?= $card['is_default'] ? 'border-primary' : '' ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong><?= strtoupper(htmlspecialchars($card['card_brand'])) ?></strong> •••• <?= htmlspecialchars($card['last_four']) ?>
                            <br><small class="text-muted">Expires <?= str_pad($card['expiry_month'], 2, '0', STR_PAD_LEFT) ?>/<?= $card['expiry_year'] ?></small>
                            <?php if ($card['is_default']): ?>
                            <br><span class="badge badge-primary">Default</span>
                            <?php endif; ?>
                        </div>
                        <a href="/modules/staff-accounts/manage-cards.php?remove=<?= $card['card_id'] ?>" class="btn btn-sm btn-outline-danger">Remove</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Transactions -->
    <div class="content-card">
        <h5 class="mb-3">Recent Activity</h5>
        <?php if (count($recent_transactions) > 0): ?>
        <div class="timeline">
            <?php foreach ($recent_transactions as $txn): 
                $is_payment = $txn['transaction_type'] === 'payment';
                $timeline_class = $is_payment ? 'success' : 'danger';
                $icon = $is_payment ? '✓' : '→';
            ?>
            <div class="timeline-item <?= $timeline_class ?>">
                <div class="timeline-content">
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong class="<?= $is_payment ? 'text-success' : 'text-danger' ?>">
                                <?= $icon ?> <?= ucfirst($txn['transaction_type']) ?>
                            </strong>
                            <p class="mb-0 text-muted small"><?= htmlspecialchars($txn['description']) ?></p>
                            <?php if ($txn['payment_method']): ?>
                            <small class="text-muted">via <?= htmlspecialchars($txn['payment_method']) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="text-right">
                            <strong class="<?= $is_payment ? 'text-success' : 'text-danger' ?>">
                                <?= $is_payment ? '+' : '-' ?>$<?= number_format($txn['amount'], 2) ?>
                            </strong>
                            <br><small class="text-muted"><?= date('M j, Y', strtotime($txn['created_at'])) ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-3">
            <a href="/modules/staff-accounts/transaction-history.php" class="btn btn-outline-secondary btn-sm">View All Transactions</a>
        </div>
        <?php else: ?>
        <p class="text-muted text-center py-4">No transactions yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php
// Capture content
$page_content = ob_get_clean();

// Render using CIS template
require_once ROOT_PATH . '/assets/template/base-layout.php';
