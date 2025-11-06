<?php
/**
 * Apply Failed Payments - Bulk Allocation Tool
 *
 * Shows all unallocated staff payments from the past 2 weeks
 * and allows one-click application to Vend customer accounts
 *
 * @package CIS\Modules\StaffAccounts
 * @version 1.0.0
 */

require_once __DIR__ . '/../bootstrap.php';
require_once ROOT_PATH . '/assets/functions/config.php';
cis_require_login();

use CIS\Modules\StaffAccounts\PaymentService;

$pdo = cis_resolve_pdo();

// Get unallocated payments from past 2 weeks with FULL transaction details for statement
// OPTIMIZED: Removed vend_sales join (huge table, not needed for display)
// PERFORMANCE: Ensure indexes exist on:
//   - sales_payments(payment_date, sale_status)
//   - sales_payments(vend_customer_id)
//   - users(vend_customer_account, staff_active)
$stmt = $pdo->query("
    SELECT
        sp.id,
        sp.vend_customer_id,
        sp.amount,
        sp.payment_date,
        sp.name as payment_method,
        sp.sale_status,
        sp.outlet_name,
        CONCAT(u.first_name, ' ', u.last_name) as staff_name,
        u.id as user_id,
        u.email as staff_email,
        vc.customer_code,
        vc.balance as current_vend_balance,
        DATEDIFF(NOW(), sp.payment_date) as days_ago,
        vo.name as outlet_full_name,
        vo.physical_address1,
        vo.physical_city,
        vo.physical_postcode
    FROM sales_payments sp
    INNER JOIN vend_customers vc ON sp.vend_customer_id = vc.id
    INNER JOIN users u ON u.vend_customer_account = vc.id
    LEFT JOIN vend_outlets vo ON sp.outlet_name = vo.name
    WHERE sp.payment_date >= DATE_SUB(NOW(), INTERVAL 14 DAY)
        AND u.staff_active = 1
        AND (sp.sale_status IS NULL OR sp.sale_status != 'ALLOCATED')
    ORDER BY sp.payment_date DESC
    LIMIT 500
");
$unallocated_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent payroll deductions for each staff member (last 4 weeks)
// This shows what was deducted from their paychecks
$payroll_deductions = [];
if (!empty($unallocated_payments)) {
    $user_ids = array_unique(array_column($unallocated_payments, 'user_id'));
    $user_ids_str = implode(',', array_map('intval', $user_ids));

    $stmt = $pdo->query("
        SELECT
            u.id as user_id,
            pxl.earnings_line_name,
            pxl.earnings_line_amount,
            pxl.deduction_type_name,
            pxl.deduction_type_amount,
            ps.week_ending,
            ps.pay_period_start,
            ps.pay_period_end,
            DATE_FORMAT(ps.week_ending, '%d %b') as week_label
        FROM payroll_xero_payslip_lines pxl
        INNER JOIN payroll_snapshots ps ON pxl.snapshot_id = ps.id
        INNER JOIN users u ON pxl.user_id = u.id
        WHERE pxl.user_id IN ($user_ids_str)
            AND ps.week_ending >= DATE_SUB(NOW(), INTERVAL 4 WEEK)
            AND (
                pxl.deduction_type_name LIKE '%staff%account%'
                OR pxl.deduction_type_name LIKE '%vend%'
                OR pxl.deduction_type_name LIKE '%account%repay%'
                OR pxl.deduction_type_name LIKE '%debt%'
            )
        ORDER BY ps.week_ending DESC, u.id
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $payroll_deductions[$row['user_id']][] = $row;
    }
}

// Get last allocation activity
$stmt = $pdo->query("
    SELECT
        MAX(created_at) as last_allocation,
        COUNT(*) as total_allocated_today
    FROM vend_payment_allocations
    WHERE DATE(created_at) = CURDATE()
");
$allocation_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate totals
$total_unallocated = count($unallocated_payments);
$total_amount_pending = array_sum(array_column($unallocated_payments, 'amount'));

// Calculate total payroll deductions
$total_payroll_deductions = 0;
foreach ($payroll_deductions as $user_deductions) {
    foreach ($user_deductions as $deduction) {
        $total_payroll_deductions += floatval($deduction['deduction_type_amount']);
    }
}

// Group payments by staff member for "Payments to be Applied" summary
$payments_by_staff = [];
foreach ($unallocated_payments as $payment) {
    $user_id = $payment['user_id'];
    if (!isset($payments_by_staff[$user_id])) {
        $payments_by_staff[$user_id] = [
            'staff_name' => $payment['staff_name'],
            'customer_code' => $payment['customer_code'],
            'current_balance' => $payment['current_vend_balance'],
            'payments' => [],
            'payroll_deductions' => $payroll_deductions[$user_id] ?? [],
            'total_payments' => 0,
            'total_deductions' => 0
        ];
    }
    $payments_by_staff[$user_id]['payments'][] = $payment;
    $payments_by_staff[$user_id]['total_payments'] += floatval($payment['amount']);
}

// Calculate total deductions per staff member
foreach ($payments_by_staff as $user_id => &$staff_data) {
    foreach ($staff_data['payroll_deductions'] as $deduction) {
        $staff_data['total_deductions'] += floatval($deduction['deduction_type_amount']);
    }
}
unset($staff_data);

// Start output
ob_start();
?>

<style>
body { background: #f8f9fa !important; color: #212529; }
.apply-payments-container { max-width: 1600px; margin: 0 auto; padding: 20px; color: #212529; }
.stats-row { display: flex; gap: 20px; margin-bottom: 30px; }
.stat-box { flex: 1; background: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.stat-box.pending { border-left: 4px solid #ffc107; }
.stat-box.amount { border-left: 4px solid #dc3545; }
.stat-box.last-run { border-left: 4px solid #28a745; }
.stat-label { font-size: 12px; color: #6c757d; text-transform: uppercase; font-weight: 600; margin-bottom: 8px; }
.stat-value { font-size: 32px; font-weight: 700; color: #212529; }
.stat-subtext { font-size: 13px; color: #6c757d; margin-top: 5px; }
.payment-table { background: #fff; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.payment-table table { margin-bottom: 0; }
.payment-table thead { background: #f8f9fa; border-bottom: 2px solid #dee2e6; }
.payment-table th { color: #495057; font-weight: 600; padding: 12px 15px; font-size: 13px; }
.payment-table td { color: #212529 !important; padding: 12px 15px; vertical-align: middle; }
.payment-table tbody tr:hover { background: #f8f9fa; }
.payment-table small { color: #6c757d !important; }
.payment-table strong { color: #212529 !important; }
.text-muted { color: #6c757d !important; }
.text-success { color: #28a745 !important; }
.text-danger { color: #dc3545 !important; }
.text-primary { color: #007bff !important; }
.btn-apply { background: #28a745; color: white !important; border: none; padding: 8px 12px; border-radius: 4px; font-size: 13px; cursor: pointer; font-weight: 500; transition: all 0.2s; }
.btn-apply:hover { background: #218838; transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
.btn-apply:disabled { background: #6c757d; cursor: not-allowed; }
.btn-apply, .btn-success, .btn-secondary, .btn-outline-secondary, .btn-primary, .btn { color: white !important; }
.btn-outline-secondary { background: white; color: #6c757d !important; border: 1px solid #6c757d; }
.btn-outline-secondary:hover { background: #6c757d; color: white !important; }
.btn-group { display: flex; gap: 4px; }
.btn-group .btn-apply { flex: 1; }
.view-statement-btn { background: #17a2b8; }
.view-statement-btn:hover { background: #138496; }
.email-statement-btn { background: #6c757d; }
.email-statement-btn:hover { background: #5a6268; }
.badge-pending { background: #ffc107; color: #000; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
.badge-days { background: #dc3545; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
.badge { padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
.badge-danger { background: #dc3545; color: white !important; }
.badge-warning { background: #ffc107; color: #000 !important; }
.badge-success { background: #28a745; color: white !important; }
.alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724 !important; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
.alert-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460 !important; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
.btn { padding: 10px 20px; border-radius: 6px; font-weight: 500; font-size: 14px; }
.btn-success { background: #28a745; color: white !important; border: none; }
.btn-success:hover { background: #218838; }
.btn-outline-secondary { background: white; color: #6c757d !important; border: 1px solid #6c757d; }
.btn-outline-secondary:hover { background: #6c757d; color: white !important; }
h1, h2, h3, h4, h5, h6 { color: #212529; }
p { color: #212529; }
small { color: #6c757d; }
code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; color: #e83e8c; font-size: 12px; }
.fas, .fa { color: inherit; }
/* Summary section specific styles */
.summary-section { background: #fff; border: 2px solid #17a2b8; border-radius: 8px; padding: 20px; margin-bottom: 25px; }
.summary-section h4 { color: #17a2b8 !important; }
.summary-section h5 { color: #212529 !important; }
.summary-section small { color: #6c757d !important; }
.summary-section strong { color: inherit; }
.summary-section .debt-amount { color: #dc3545 !important; }
.summary-section .credit-amount { color: #28a745 !important; }
</style>

<div class="apply-payments-container">
    <div class="mb-4">
        <h1 class="h3 mb-2"><i class="fas fa-credit-card me-2"></i>Apply Failed Payments</h1>
        <p class="text-muted">Allocate unprocessed staff payments to Vend customer accounts</p>
    </div>

    <!-- Stats Row -->
    <div class="stats-row">
        <div class="stat-box pending">
            <div class="stat-label"><i class="fas fa-clock me-1"></i>Pending Payments</div>
            <div class="stat-value"><?php echo number_format($total_unallocated); ?></div>
            <div class="stat-subtext">From past 14 days</div>
        </div>
        <div class="stat-box amount">
            <div class="stat-label"><i class="fas fa-dollar-sign me-1"></i>Total Pending Amount</div>
            <div class="stat-value">$<?php echo number_format($total_amount_pending, 2); ?></div>
            <div class="stat-subtext">Waiting to be allocated</div>
        </div>
        <div class="stat-box" style="border-left: 4px solid #17a2b8;">
            <div class="stat-label"><i class="fas fa-receipt me-1"></i>Payroll Deductions (4wk)</div>
            <div class="stat-value">$<?php echo number_format($total_payroll_deductions, 2); ?></div>
            <div class="stat-subtext">Deducted from paychecks</div>
        </div>
        <div class="stat-box last-run">
            <div class="stat-label"><i class="fas fa-check-circle me-1"></i>Last Allocation</div>
            <div class="stat-value">
                <?php if ($allocation_stats['last_allocation']): ?>
                    <?php echo date('g:i A', strtotime($allocation_stats['last_allocation'])); ?>
                <?php else: ?>
                    Never
                <?php endif; ?>
            </div>
            <div class="stat-subtext">
                <?php echo number_format($allocation_stats['total_allocated_today']); ?> allocated today
            </div>
        </div>
    </div>

    <?php if ($total_unallocated === 0): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <strong>All caught up!</strong> No pending payments to allocate. Great job! 🎉
        </div>
    <?php else: ?>
        <!-- PAYMENTS TO BE APPLIED SUMMARY -->
        <div class="summary-section" style="box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
            <h4 style="margin: 0 0 20px 0; border-bottom: 2px solid #17a2b8; padding-bottom: 10px; font-size: 20px; font-weight: bold;">
                <i class="fas fa-clipboard-list me-2"></i>PAYMENTS TO BE APPLIED
            </h4>

            <?php foreach ($payments_by_staff as $user_id => $staff): ?>
            <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 15px; margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="flex: 1;">
                        <h5 style="margin: 0; color: #212529 !important; font-size: 18px; font-weight: bold;">
                            <i class="fas fa-user me-2" style="color: #212529 !important;"></i><?php echo htmlspecialchars($staff['staff_name']); ?>
                        </h5>
                        <small style="color: #6c757d !important; font-size: 13px;">Customer Code: <?php echo htmlspecialchars($staff['customer_code']); ?></small>
                    </div>
                    <div style="text-align: right; flex: 1;">
                        <div style="font-size: 24px; font-weight: bold; color: #dc3545 !important;">
                            Current Debt: -$<?php echo number_format(abs($staff['current_balance']), 2); ?>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                    <div>
                        <div style="font-size: 12px; color: #212529 !important; text-transform: uppercase; font-weight: 700; margin-bottom: 8px; background: #e9ecef; padding: 8px; border-radius: 4px;">
                            <i class="fas fa-receipt me-1" style="color: #212529 !important;"></i>PAYROLL DEDUCTIONS (Last 4 Weeks)
                        </div>
                        <?php if (!empty($staff['payroll_deductions'])): ?>
                            <?php foreach ($staff['payroll_deductions'] as $deduction): ?>
                            <div style="padding: 8px 0; border-bottom: 1px solid #ddd;">
                                <strong style="color: #dc3545 !important; font-size: 14px;">-$<?php echo number_format($deduction['deduction_type_amount'], 2); ?></strong>
                                <small style="color: #212529 !important; display: block; font-size: 12px; font-weight: 600;">
                                    <?php echo htmlspecialchars($deduction['deduction_type_name']); ?> (Week <?php echo $deduction['week_label']; ?>)
                                </small>
                            </div>
                            <?php endforeach; ?>
                            <div style="margin-top: 10px; font-weight: bold; color: #212529 !important; font-size: 15px; background: #fff3cd; padding: 10px; border-radius: 4px;">
                                Total Deducted: <span style="color: #dc3545 !important;">-$<?php echo number_format($staff['total_deductions'], 2); ?></span>
                            </div>
                        <?php else: ?>
                            <small style="color: #212529 !important;">No payroll deductions found</small>
                        <?php endif; ?>
                    </div>

                    <div>
                        <div style="font-size: 12px; color: #212529 !important; text-transform: uppercase; font-weight: 700; margin-bottom: 8px; background: #e9ecef; padding: 8px; border-radius: 4px;">
                            <i class="fas fa-money-bill-wave me-1" style="color: #212529 !important;"></i>PENDING PAYMENTS
                        </div>
                        <?php foreach ($staff['payments'] as $pmt): ?>
                        <div style="padding: 8px 0; border-bottom: 1px solid #ddd;">
                            <strong style="color: #28a745 !important; font-size: 14px;">+$<?php echo number_format($pmt['amount'], 2); ?></strong>
                            <small style="color: #212529 !important; display: block; font-size: 12px; font-weight: 600;">
                                <?php echo date('M j', strtotime($pmt['payment_date'])); ?> - <?php echo htmlspecialchars($pmt['outlet_name']); ?>
                            </small>
                        </div>
                        <?php endforeach; ?>
                        <div style="margin-top: 10px; font-weight: bold; color: #212529 !important; font-size: 15px; background: #d4edda; padding: 10px; border-radius: 4px;">
                            Total to Apply: <span style="color: #28a745 !important;">+$<?php echo number_format($staff['total_payments'], 2); ?></span>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #000; display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; padding: 15px; border-radius: 6px;">
                    <div style="font-size: 20px; font-weight: bold; color: #212529 !important;">
                        New Balance After:
                        <?php
                        $new_balance = $staff['current_balance'] + $staff['total_payments'];
                        $color = $new_balance < 0 ? '#dc3545' : '#28a745';
                        ?>
                        <span style="color: <?php echo $color; ?> !important; font-size: 24px;">
                            <?php echo $new_balance < 0 ? '-' : ''; ?>$<?php echo number_format(abs($new_balance), 2); ?>
                        </span>
                        <?php if ($new_balance >= 0): ?>
                            <i class="fas fa-check-circle ms-2" style="color: #28a745 !important;"></i>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-success apply-staff-btn" data-user-id="<?php echo $user_id; ?>" style="background: #28a745 !important; color: white !important; font-size: 16px; font-weight: bold; padding: 12px 24px; border: none; cursor: pointer;">
                        <i class="fas fa-check-circle me-2" style="color: white !important;"></i>Apply All (<?php echo count($staff['payments']); ?> payments)
                    </button>
                </div>

                <?php
                $difference = $staff['total_deductions'] - $staff['total_payments'];
                if (abs($difference) > 0.01):
                ?>
                <div style="margin-top: 10px; padding: 10px; background: <?php echo $difference > 0 ? '#fff3cd' : '#d1ecf1'; ?>; border-radius: 4px;">
                    <small style="color: #856404;">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Note:</strong>
                        <?php if ($difference > 0): ?>
                            Deducted $<?php echo number_format($difference, 2); ?> MORE than pending payments
                        <?php else: ?>
                            Pending payments are $<?php echo number_format(abs($difference), 2); ?> MORE than deductions
                        <?php endif; ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Bulk Actions -->
        <div class="mb-3">
            <button id="applyAllBtn" class="btn btn-success">
                <i class="fas fa-bolt me-2"></i>Apply All Payments (<?php echo $total_unallocated; ?>)
            </button>
            <button id="refreshBtn" class="btn btn-outline-secondary ms-2">
                <i class="fas fa-sync me-2"></i>Refresh
            </button>
        </div>

        <!-- Payments Table -->
        <div class="payment-table">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Staff Member</th>
                        <th>Customer Code</th>
                        <th>Payment Amount</th>
                        <th>Recent Payroll Deductions</th>
                        <th>Current Debt</th>
                        <th>New Balance After</th>
                        <th>Outlet</th>
                        <th>Receipt</th>
                        <th>Age</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($unallocated_payments as $payment):
                        $paymentData = [
                            'id' => $payment['id'],
                            'user_id' => $payment['user_id'],
                            'vend_customer_id' => $payment['vend_customer_id'],
                            'amount' => $payment['amount'],
                            'staff_name' => $payment['staff_name'],
                            'staff_email' => $payment['staff_email'],
                            'customer_code' => $payment['customer_code'],
                            'payment_date' => $payment['payment_date'],
                            'payment_method' => $payment['payment_method'],
                            'outlet_name' => $payment['outlet_name'],
                            'outlet_full_name' => $payment['outlet_full_name'],
                            'outlet_address' => trim(($payment['physical_address1'] ?? '') . ', ' . ($payment['physical_city'] ?? '') . ' ' . ($payment['physical_postcode'] ?? '')),
                            'sale_id' => $payment['sale_id'],
                            'register_id' => $payment['register_id'],
                            'current_balance' => $payment['current_vend_balance'],
                            'payroll_deductions' => $payroll_deductions[$payment['user_id']] ?? []
                        ];
                    ?>
                    <tr data-payment-id="<?php echo $payment['id']; ?>"
                        data-user-id="<?php echo $payment['user_id']; ?>"
                        data-vend-customer-id="<?php echo $payment['vend_customer_id']; ?>"
                        data-amount="<?php echo $payment['amount']; ?>"
                        data-payment-info='<?php echo htmlspecialchars(json_encode($paymentData), ENT_QUOTES); ?>'>
                        <td>
                            <small><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></small><br>
                            <small class="text-muted"><?php echo date('g:i A', strtotime($payment['payment_date'])); ?></small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($payment['staff_name']); ?></strong>
                        </td>
                        <td>
                            <code><?php echo htmlspecialchars($payment['customer_code']); ?></code>
                        </td>
                        <td>
                            <strong class="text-success">+$<?php echo number_format($payment['amount'], 2); ?></strong>
                            <br><small class="text-muted" style="color: #6c757d !important;">Repayment</small>
                        </td>
                        <td style="font-size: 12px;">
                            <?php if (isset($payroll_deductions[$payment['user_id']]) && !empty($payroll_deductions[$payment['user_id']])): ?>
                                <?php
                                $total_deducted = 0;
                                $payment_timestamp = strtotime($payment['payment_date']);
                                $relevant_deductions = [];

                                // Find deductions within 7 days of this payment
                                foreach ($payroll_deductions[$payment['user_id']] as $deduction) {
                                    $deduction_timestamp = strtotime($deduction['week_ending']);
                                    $days_diff = abs(($payment_timestamp - $deduction_timestamp) / 86400);

                                    if ($days_diff <= 7) {
                                        $relevant_deductions[] = $deduction;
                                        $total_deducted += floatval($deduction['deduction_type_amount']);
                                    }
                                }

                                if (!empty($relevant_deductions)):
                                    foreach ($relevant_deductions as $deduction):
                                ?>
                                    <div style="padding: 3px 0; border-bottom: 1px solid #eee; color: #212529;">
                                        <strong style="color: #dc3545;">-$<?php echo number_format($deduction['deduction_type_amount'], 2); ?></strong>
                                        <small style="color: #6c757d; display: block;">
                                            <?php echo htmlspecialchars($deduction['deduction_type_name']); ?>
                                        </small>
                                        <small style="color: #6c757d; font-size: 10px;">
                                            Week ending <?php echo $deduction['week_label']; ?>
                                        </small>
                                    </div>
                                <?php
                                    endforeach;
                                ?>
                                <div style="margin-top: 5px; padding-top: 5px; border-top: 2px solid #000; color: #212529;">
                                    <strong>Deducted: -$<?php echo number_format($total_deducted, 2); ?></strong>
                                </div>
                                <?php else: ?>
                                    <small style="color: #6c757d;">No deductions match this date</small>
                                <?php endif; ?>
                            <?php else: ?>
                                <small style="color: #6c757d;">No recent deductions</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $balance = floatval($payment['current_vend_balance']);
                            $debt = abs($balance);
                            $badge_class = $debt > 500 ? 'danger' : ($debt > 0 ? 'warning' : 'success');
                            ?>
                            <span class="badge badge-<?php echo $badge_class; ?>">
                                <?php if ($balance < 0): ?>
                                    -$<?php echo number_format($debt, 2); ?>
                                <?php else: ?>
                                    $<?php echo number_format($balance, 2); ?>
                                <?php endif; ?>
                            </span>
                            <br><small class="text-muted" style="color: #6c757d !important;">Currently Owes</small>
                        </td>
                        <td>
                            <?php
                            $new_balance = $balance + $payment['amount'];
                            $new_debt = abs($new_balance);
                            $new_badge_class = $new_balance < -500 ? 'danger' : ($new_balance < 0 ? 'warning' : 'success');
                            ?>
                            <span class="badge badge-<?php echo $new_badge_class; ?>">
                                <?php if ($new_balance < 0): ?>
                                    -$<?php echo number_format($new_debt, 2); ?>
                                    <i class="fas fa-arrow-down text-success ms-1" title="Debt Reduced"></i>
                                <?php elseif ($new_balance > 0): ?>
                                    +$<?php echo number_format($new_balance, 2); ?>
                                    <i class="fas fa-check-circle text-success ms-1" title="Paid Off!"></i>
                                <?php else: ?>
                                    $0.00
                                    <i class="fas fa-check-circle text-success ms-1" title="Fully Paid"></i>
                                <?php endif; ?>
                            </span>
                            <br>
                            <?php
                            $reduction = $payment['amount'];
                            $reduction_percent = $debt > 0 ? ($reduction / $debt) * 100 : 0;
                            ?>
                            <small class="text-success">
                                <i class="fas fa-minus-circle me-1"></i><?php echo number_format($reduction_percent, 1); ?>% debt reduction
                            </small>
                        </td>
                        <td>
                            <small style="color: #212529;"><?php echo htmlspecialchars($payment['outlet_name']); ?></small>
                        </td>
                        <td>
                            <?php if (!empty($payment['sale_id'])): ?>
                                <code class="text-primary">Sale #<?php echo htmlspecialchars($payment['sale_id']); ?></code>
                            <?php else: ?>
                                <small class="text-muted">N/A</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($payment['days_ago'] > 7): ?>
                                <span class="badge-days"><?php echo $payment['days_ago']; ?> days</span>
                            <?php else: ?>
                                <span class="badge-pending"><?php echo $payment['days_ago']; ?> days</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <button class="btn-apply view-statement-btn" data-payment-id="<?php echo $payment['id']; ?>" title="View Statement">
                                    <i class="fas fa-receipt"></i>
                                </button>
                                <button class="btn-apply email-statement-btn" data-payment-id="<?php echo $payment['id']; ?>" title="Email Statement">
                                    <i class="fas fa-envelope"></i>
                                </button>
                                <button class="btn-apply apply-single-btn" data-payment-id="<?php echo $payment['id']; ?>" title="Apply to Vend">
                                    <i class="fas fa-check"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Payment Statement Modal -->
<div class="modal fade" id="statementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-receipt me-2"></i>Payment Statement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="statementContent">
                <!-- Statement will be injected here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print
                </button>
                <button type="button" id="emailStatementBtn" class="btn btn-primary">
                    <i class="fas fa-envelope me-2"></i>Email to Staff Member
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    let currentStatementData = null;
    const statementModal = new bootstrap.Modal(document.getElementById('statementModal'));

    // View Statement
    $('.view-statement-btn').on('click', function() {
        const btn = $(this);
        const row = btn.closest('tr');
        const paymentInfo = JSON.parse(row.attr('data-payment-info'));

        currentStatementData = paymentInfo;

        const currentDebt = Math.abs(parseFloat(paymentInfo.current_balance));
        const paymentAmount = parseFloat(paymentInfo.amount);
        const newBalance = parseFloat(paymentInfo.current_balance) + paymentAmount;
        const newDebt = Math.abs(newBalance);
        const debtReduction = paymentAmount;
        const debtReductionPercent = currentDebt > 0 ? (debtReduction / currentDebt * 100) : 0;

        const statementHTML = `
            <div class="statement-document" style="font-family: 'Courier New', monospace; background: #fff; padding: 30px; border: 2px solid #000;">
                <div style="text-align: center; border-bottom: 3px double #000; padding-bottom: 20px; margin-bottom: 20px;">
                    <h2 style="margin: 0; font-size: 24px; font-weight: bold;">THE VAPE SHED</h2>
                    <p style="margin: 5px 0;">STAFF ACCOUNT PAYMENT STATEMENT</p>
                    <p style="margin: 0; font-size: 12px;">Ecigdis Limited</p>
                </div>

                <div style="margin-bottom: 25px;">
                    <table style="width: 100%; font-size: 13px;">
                        <tr>
                            <td style="padding: 5px;"><strong>Staff Member:</strong></td>
                            <td style="padding: 5px;">${paymentInfo.staff_name}</td>
                            <td style="padding: 5px;"><strong>Customer Code:</strong></td>
                            <td style="padding: 5px;">${paymentInfo.customer_code}</td>
                        </tr>
                        <tr>
                            <td style="padding: 5px;"><strong>Payment Date:</strong></td>
                            <td style="padding: 5px;">${new Date(paymentInfo.payment_date).toLocaleString('en-NZ')}</td>
                            <td style="padding: 5px;"><strong>Payment ID:</strong></td>
                            <td style="padding: 5px;">#${paymentInfo.id}</td>
                        </tr>
                        <tr>
                            <td style="padding: 5px;"><strong>Outlet:</strong></td>
                            <td style="padding: 5px;">${paymentInfo.outlet_full_name || paymentInfo.outlet_name}</td>
                            <td style="padding: 5px;"><strong>Sale ID:</strong></td>
                            <td style="padding: 5px;">${paymentInfo.sale_id || 'N/A'}</td>
                        </tr>
                    </table>
                </div>

                <div style="border: 2px solid #000; padding: 20px; margin-bottom: 25px; background: #f8f9fa;">
                    <h3 style="text-align: center; margin-top: 0; font-size: 18px; text-decoration: underline;">ACCOUNT SUMMARY</h3>

                    <table style="width: 100%; font-size: 14px; margin-top: 15px;">
                        <tr style="border-bottom: 1px solid #ccc;">
                            <td style="padding: 10px;"><strong>Previous Balance (Amount Owed):</strong></td>
                            <td style="padding: 10px; text-align: right; color: ${currentDebt > 0 ? '#dc3545' : '#28a745'};">
                                ${currentDebt > 0 ? '-' : ''}$${currentDebt.toFixed(2)}
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid #ccc;">
                            <td style="padding: 10px;"><strong>Payment Received:</strong></td>
                            <td style="padding: 10px; text-align: right; color: #28a745; font-weight: bold;">
                                +$${paymentAmount.toFixed(2)}
                            </td>
                        </tr>
                        <tr style="border-bottom: 2px solid #000; background: #e9ecef;">
                            <td style="padding: 10px;"><strong>NEW BALANCE:</strong></td>
                            <td style="padding: 10px; text-align: right; font-weight: bold; font-size: 16px; color: ${newBalance < 0 ? '#dc3545' : '#28a745'};">
                                ${newBalance < 0 ? '-' : ''}$${newDebt.toFixed(2)}
                            </td>
                        </tr>
                    </table>

                    ${currentDebt > 0 ? `
                    <div style="margin-top: 15px; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px;">
                        <p style="margin: 0; text-align: center; color: #155724;">
                            <strong>✓ DEBT REDUCED BY $${debtReduction.toFixed(2)} (${debtReductionPercent.toFixed(1)}%)</strong>
                        </p>
                        ${newBalance >= 0 ? `
                        <p style="margin: 10px 0 0 0; text-align: center; color: #155724; font-weight: bold;">
                            🎉 ACCOUNT FULLY PAID! 🎉
                        </p>
                        ` : ''}
                    </div>
                    ` : ''}
                </div>

                <div style="margin-bottom: 20px;">
                    <h4 style="font-size: 14px; border-bottom: 1px solid #000; padding-bottom: 5px;">TRANSACTION DETAILS</h4>
                    <table style="width: 100%; font-size: 12px;">
                        <tr>
                            <td style="padding: 5px;"><strong>Payment Method:</strong></td>
                            <td style="padding: 5px;">${paymentInfo.payment_method || 'Staff Account Repayment'}</td>
                        </tr>
                        <tr>
                            <td style="padding: 5px;"><strong>Register ID:</strong></td>
                            <td style="padding: 5px;">${paymentInfo.register_id || 'N/A'}</td>
                        </tr>
                        ${paymentInfo.outlet_address && paymentInfo.outlet_address !== ',  ' ? `
                        <tr>
                            <td style="padding: 5px;"><strong>Store Address:</strong></td>
                            <td style="padding: 5px;">${paymentInfo.outlet_address}</td>
                        </tr>
                        ` : ''}
                    </table>
                </div>

                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #000; font-size: 11px; text-align: center; color: #6c757d;">
                    <p style="margin: 5px 0;">This is a computer-generated statement from CIS Staff Portal</p>
                    <p style="margin: 5px 0;">Generated: ${new Date().toLocaleString('en-NZ')}</p>
                    <p style="margin: 5px 0;">For queries contact: accounts@vapeshed.co.nz</p>
                </div>
            </div>
        `;

        $('#statementContent').html(statementHTML);
        statementModal.show();
    });

    // Email Statement
    $('.email-statement-btn').on('click', function() {
        const btn = $(this);
        const row = btn.closest('tr');
        const paymentInfo = JSON.parse(row.attr('data-payment-info'));

        if (!paymentInfo.staff_email) {
            alert('No email address found for this staff member.');
            return;
        }

        if (!confirm(`Email statement to ${paymentInfo.staff_name} at ${paymentInfo.staff_email}?`)) {
            return;
        }

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: '/modules/staff-accounts/api/email-statement.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(paymentInfo),
            success: function(response) {
                if (response.success) {
                    alert('✓ Statement emailed successfully!');
                } else {
                    alert('Failed to email statement: ' + (response.error || 'Unknown error'));
                }
                btn.prop('disabled', false).html('<i class="fas fa-envelope"></i>');
            },
            error: function() {
                alert('Network error - please try again');
                btn.prop('disabled', false).html('<i class="fas fa-envelope"></i>');
            }
        });
    });

    $('#emailStatementBtn').on('click', function() {
        if (!currentStatementData) return;

        if (!currentStatementData.staff_email) {
            alert('No email address found for this staff member.');
            return;
        }

        if (!confirm(`Email statement to ${currentStatementData.staff_name} at ${currentStatementData.staff_email}?`)) {
            return;
        }

        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Sending...');

        $.ajax({
            url: '/modules/staff-accounts/api/email-statement.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(currentStatementData),
            success: function(response) {
                if (response.success) {
                    alert('✓ Statement emailed successfully!');
                    statementModal.hide();
                } else {
                    alert('Failed to email statement: ' + (response.error || 'Unknown error'));
                }
                $('#emailStatementBtn').prop('disabled', false).html('<i class="fas fa-envelope me-2"></i>Email to Staff Member');
            },
            error: function() {
                alert('Network error - please try again');
                $('#emailStatementBtn').prop('disabled', false).html('<i class="fas fa-envelope me-2"></i>Email to Staff Member');
            }
        });
    });

    // Apply single payment
    $('.apply-single-btn').on('click', function() {
        const btn = $(this);
        const row = btn.closest('tr');
        const paymentId = btn.data('payment-id');
        const userId = row.data('user-id');
        const vendCustomerId = row.data('vend-customer-id');
        const amount = row.data('amount');

        if (!confirm(`Apply $${amount} to ${row.find('strong').first().text()}?`)) {
            return;
        }

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying...');

        $.ajax({
            url: '/modules/staff-accounts/api/apply-payment.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                user_id: userId,
                vend_customer_id: vendCustomerId,
                amount: amount,
                payment_id: paymentId
            }),
            success: function(response) {
                if (response.success) {
                    row.fadeOut(300, function() { row.remove(); });
                    toastr.success('Payment applied successfully!');
                    updateStats();
                } else {
                    toastr.error(response.error || 'Failed to apply payment');
                    btn.prop('disabled', false).html('<i class="fas fa-check me-1"></i>Apply to Vend');
                }
            },
            error: function() {
                toastr.error('Network error - please try again');
                btn.prop('disabled', false).html('<i class="fas fa-check me-1"></i>Apply to Vend');
            }
        });
    });

    // Apply all payments
    $('#applyAllBtn').on('click', function() {
        const totalPayments = $('.apply-single-btn').length;

        if (!confirm(`Apply all ${totalPayments} payments? This may take a few minutes.`)) {
            return;
        }

        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');

        let processed = 0;
        let failed = 0;

        $('.apply-single-btn').each(function(index) {
            const btn = $(this);
            setTimeout(function() {
                btn.click();
            }, index * 500); // Stagger by 500ms each
        });
    });

    // Refresh button
    $('#refreshBtn').on('click', function() {
        location.reload();
    });

    // Apply all payments for a specific staff member
    $('.apply-staff-btn').on('click', function() {
        const btn = $(this);
        const userId = btn.data('user-id');
        const staffName = btn.closest('div[style*="background: #f8f9fa"]').find('h5').text().trim();
        const paymentCount = btn.text().match(/\d+/)[0];

        if (!confirm(`Apply all ${paymentCount} payments for ${staffName}?`)) {
            return;
        }

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');

        // Find all payment rows for this user and click their apply buttons
        let applied = 0;
        let failed = 0;

        $('tr[data-user-id="' + userId + '"] .apply-single-btn').each(function(index) {
            const applyBtn = $(this);
            setTimeout(function() {
                applyBtn.click();
            }, index * 600); // Stagger by 600ms each
        });

        // Re-enable button after all are processed
        setTimeout(function() {
            btn.html('<i class="fas fa-check-circle me-2"></i>Done!').removeClass('btn-success').addClass('btn-secondary');
        }, paymentCount * 600 + 1000);
    });

    function updateStats() {
        const remaining = $('.apply-single-btn').length;
        if (remaining === 0) {
            location.reload();
        }
    }
});
</script>

<?php
$content = ob_get_clean();
$pageTitle = 'Apply Failed Payments';
require_once dirname(dirname(__DIR__)) . '/base/_templates/layouts/dashboard-modern.php';
