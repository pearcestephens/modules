<?php
/**
 * Staff Accounts - Payment Success Page
 *
 * Purpose: Display payment confirmation receipt after successful payment
 *
 * Features:
 * - Transaction summary with receipt
 * - Downloadable PDF receipt (future)
 * - Return to account link
 * - Print receipt button
 * - High-end professional design
 *
 * @package CIS\Modules\StaffAccounts
 * @version 2.0.0
 */

// Bootstrap the module
require_once __DIR__ . '/../bootstrap.php';

// Require authentication
cis_require_login();

$user_id = $_SESSION['userID'];

// Get transaction ID from query string
$transaction_id = $_GET['transaction_id'] ?? '';

if (empty($transaction_id)) {
    header('Location: my-account.php');
    exit;
}

// CHECK TABLE: staff_payment_transactions - Fetch transaction details
// VERIFIED: staff_payment_transactions columns = id, user_id, transaction_type, amount, request_id, response_data, created_at
$stmt = $pdo->prepare("
    SELECT
        spt.id,
        spt.user_id,
        spt.amount,
        spt.transaction_type,
        spt.request_id,
        spt.created_at,
        u.first_name,
        u.last_name,
        u.email,
        sar.employee_name,
        sar.vend_balance as current_balance
    FROM staff_payment_transactions spt
    INNER JOIN users u ON spt.user_id = u.id
    LEFT JOIN staff_account_reconciliation sar ON sar.user_id = spt.user_id
    WHERE spt.request_id = ? AND spt.user_id = ?
    LIMIT 1
");
$stmt->execute([$transaction_id, $user_id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

// If transaction not found or doesn't belong to user, redirect
if (!$transaction) {
    header('Location: my-account.php?error=transaction_not_found');
    exit;
}

// Page configuration for CIS template
$page_title = 'Payment Confirmation - Staff Accounts';
$page_head_extra = '<link rel="stylesheet" href="/assets/css/staff-accounts.css">';
$body_class = 'staff-accounts payment-success';

// Start output buffering
ob_start();
?>

<div class="container-fluid staff-accounts">
    <div class="receipt-wrapper">
        <div class="receipt-container">
        <!-- Receipt Card -->
        <div class="receipt-card">
            <!-- Success Header -->
            <div class="receipt-header">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h1>Payment Successful!</h1>
                <p>Your payment has been processed securely</p>
            </div>

            <!-- Receipt Body -->
            <div class="receipt-body">
                <!-- Amount Section -->
                <div class="amount-display">
                    <div class="amount-label">Amount Paid</div>
                    <div class="amount-value">$<?= number_format($transaction['amount'], 2) ?></div>
                </div>

                <!-- Transaction Details -->
                <div class="receipt-section">
                    <h3 class="receipt-section-title">Transaction Details</h3>

                    <table class="transaction-table">
                        <tr>
                            <td>Request ID</td>
                            <td><code><?= htmlspecialchars($transaction['request_id']) ?></code></td>
                        </tr>
                        <tr>
                            <td>Date & Time</td>
                            <td><?= date('F j, Y \a\t g:i A', strtotime($transaction['created_at'])) ?></td>
                        </tr>
                        <tr>
                            <td>Transaction Type</td>
                            <td>
                                <span class="status-badge <?= $transaction['transaction_type'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $transaction['transaction_type'])) ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Account Information -->
                <div class="receipt-section">
                    <h3 class="receipt-section-title">Account Information</h3>

                    <div class="receipt-info-grid">
                        <div class="receipt-info-item">
                            <div class="receipt-info-label">Account Holder</div>
                            <div class="receipt-info-value">
                                <?= htmlspecialchars($transaction['employee_name'] ?? ($transaction['first_name'] . ' ' . $transaction['last_name'])) ?>
                            </div>
                        </div>

                        <div class="receipt-info-item">
                            <div class="receipt-info-label">Email</div>
                            <div class="receipt-info-value">
                                <?= htmlspecialchars($transaction['email']) ?>
                            </div>
                        </div>

                        <div class="receipt-info-item">
                            <div class="receipt-info-label">Current Balance</div>
                            <div class="receipt-info-value large">
                                $<?= number_format(abs($transaction['current_balance'] ?? 0), 2) ?>
                            </div>
                        </div>

                        <div class="receipt-info-item">
                            <div class="receipt-info-label">Vend Account</div>
                            <div class="receipt-info-value">
                                <?= htmlspecialchars($transaction['vend_customer_account'] ?? 'N/A') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Success Message -->
                <div style="background: rgba(39, 174, 96, 0.1); border-left: 4px solid var(--success-color); padding: 16px; border-radius: 8px; margin: 24px 0;">
                    <p style="margin: 0; color: var(--success-color); font-weight: 600;">
                        <i class="fas fa-info-circle"></i>
                        Your payment has been applied to your Vend account. Your updated balance will be reflected in your next statement.
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="receipt-actions">
                    <a href="my-account.php" class="btn-receipt btn-receipt-primary">
                        <i class="fas fa-arrow-left"></i> Back to My Account
                    </a>
                    <button onclick="window.print()" class="btn-receipt btn-receipt-secondary no-print">
                        <i class="fas fa-print"></i> Print Receipt
                    </button>
                </div>
            </div>
        </div>

        <!-- Support Info -->
        <div style="text-align: center; color: white; margin-top: 24px; opacity: 0.9;">
            <p style="margin: 0 0 8px 0;">
                <i class="fas fa-lock"></i> This transaction is secure and encrypted
            </p>
            <p style="margin: 0; font-size: 14px;">
                Questions? Contact your manager or IT support
            </p>
        </div>
        </div> <!-- /receipt-container -->
    </div> <!-- /receipt-wrapper -->
</div> <!-- /container-fluid.staff-accounts -->

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../shared/templates/base-layout.php';
