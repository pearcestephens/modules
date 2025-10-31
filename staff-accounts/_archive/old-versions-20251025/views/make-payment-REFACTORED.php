<?php
/**
 * Staff Accounts - Make Payment View (CIS Template Version)
 * 
 * Purpose: Payment processing interface for staff to pay down account balances
 * 
 * Features:
 * - Amount input with balance validation
 * - Payment method selection (credit card, saved cards, bank transfer)
 * - Nuvei payment gateway integration
 * - Real-time validation
 * - Confirmation flow with receipt
 * - High-end professional CSS targeting .container-fluid
 * 
 * Database Tables:
 * - staff_payment_transactions (records payments)
 * - staff_saved_cards (stored payment methods)
 * - staff_account_reconciliation (updates balance)
 * 
 * @package CIS\Modules\StaffAccounts
 * @version 2.0.1 (Refactored for CIS Template System)
 */

// Bootstrap the module
require_once __DIR__ . '/../bootstrap.php';

// Require authentication
cis_require_login();

$user_id = $_SESSION['userID'];

// STEP 1: CHECK TABLE - Fetch account details from staff_account_reconciliation
$stmt = $pdo->prepare("
    SELECT 
        sar.id,
        sar.user_id,
        sar.vend_customer_id,
        sar.employee_name,
        sar.vend_balance,
        sar.outstanding_amount,
        sar.total_allocated,
        sar.credit_limit,
        sar.last_payment_date,
        sar.last_payment_amount
    FROM staff_account_reconciliation sar
    WHERE sar.user_id = ?
    LIMIT 1
");
$stmt->execute([$user_id]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

// If no account found, create error state
if (!$account) {
    $error_message = "No staff account found. Please contact your manager.";
    $account = [
        'employee_name' => $_SESSION['name'] ?? 'User',
        'vend_balance' => 0.00,
        'outstanding_amount' => 0.00
    ];
}

// Calculate amount owed (negative balance)
$vend_balance = floatval($account['vend_balance'] ?? 0);
$amount_owed = abs($vend_balance); // Convert negative balance to positive amount

// Check if payment is needed
$can_make_payment = $vend_balance < 0 && $amount_owed >= 10;

// STEP 2: CHECK TABLE - Fetch saved payment methods from staff_saved_cards
$stmt = $pdo->prepare("
    SELECT 
        id,
        card_type,
        last_four_digits,
        expiry_month,
        expiry_year,
        is_default,
        cardholder_name
    FROM staff_saved_cards
    WHERE user_id = ? AND is_active = 1
    ORDER BY is_default DESC, created_at DESC
");
$stmt->execute([$user_id]);
$saved_cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent payment history for context
$stmt = $pdo->prepare("
    SELECT 
        amount,
        transaction_type,
        created_at,
        status
    FROM staff_payment_transactions
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 3
");
$stmt->execute([$user_id]);
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Configure page for CIS template
$page_title = "Make a Payment - Staff Accounts";
$page_head_extra = '<link rel="stylesheet" href="/modules/staff-accounts/css/payment.css">';
$body_class = 'staff-accounts make-payment';

// Start output buffering for page content
ob_start();
?>

<div class="container-fluid mt-4 payment-page">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <!-- Payment Card -->
            <div class="payment-card">
                <!-- Header -->
                <div class="payment-header">
                    <h1><i class="fas fa-credit-card"></i> Make a Payment</h1>
                    <p>Pay down your staff account balance securely</p>
                </div>
                
                <div class="payment-body">
                    <!-- Balance Display -->
                    <div class="balance-display <?= $vend_balance >= 0 ? 'positive' : '' ?>">
                        <div class="balance-label">
                            <?= $vend_balance < 0 ? 'Amount Owed' : 'Credit Balance' ?>
                        </div>
                        <div class="balance-amount">
                            $<?= number_format($amount_owed, 2) ?>
                        </div>
                    </div>
                    
                    <?php if (!$can_make_payment): ?>
                        <!-- No Payment Needed Alert -->
                        <div class="alert-custom alert-info">
                            <i class="fas fa-info-circle"></i>
                            <?php if ($vend_balance >= 0): ?>
                                <strong>No payment needed!</strong> Your account has a positive balance.
                            <?php else: ?>
                                <strong>Minimum payment not met.</strong> Payments must be at least $10.00
                            <?php endif; ?>
                        </div>
                        
                        <div class="payment-actions">
                            <a href="my-account.php" class="btn-payment btn-payment-secondary">
                                <i class="fas fa-arrow-left"></i> Back to My Account
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Payment Form -->
                        <form id="paymentForm" method="POST" action="../api/process-payment.php">
                            <!-- CSRF Token -->
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                            <input type="hidden" name="vend_customer_id" value="<?= htmlspecialchars($account['vend_customer_id'] ?? '') ?>">
                            
                            <!-- Amount Section -->
                            <div class="form-section">
                                <h3 class="form-section-title">
                                    <i class="fas fa-dollar-sign"></i> Payment Amount
                                </h3>
                                
                                <div class="amount-input-group">
                                    <div class="amount-input-wrapper">
                                        <span class="currency-symbol">$</span>
                                        <input 
                                            type="number" 
                                            id="paymentAmount" 
                                            name="amount" 
                                            class="amount-input" 
                                            placeholder="0.00" 
                                            min="10" 
                                            max="<?= number_format($amount_owed, 2, '.', '') ?>" 
                                            step="0.01"
                                            required
                                            autocomplete="off"
                                        >
                                    </div>
                                    <div id="amountValidation" class="validation-message" style="display: none;"></div>
                                </div>
                                
                                <!-- Quick Amount Suggestions -->
                                <div class="amount-suggestions">
                                    <div class="amount-suggestion" data-amount="50">$50</div>
                                    <div class="amount-suggestion" data-amount="100">$100</div>
                                    <div class="amount-suggestion" data-amount="<?= min(200, $amount_owed) ?>">
                                        $<?= min(200, $amount_owed) ?>
                                    </div>
                                    <div class="amount-suggestion" data-amount="<?= $amount_owed ?>">
                                        Pay Full Balance
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Method Section -->
                            <div class="form-section">
                                <h3 class="form-section-title">
                                    <i class="fas fa-wallet"></i> Payment Method
                                </h3>
                                
                                <div class="payment-methods">
                                    <!-- Saved Cards -->
                                    <?php if (!empty($saved_cards)): ?>
                                        <?php foreach ($saved_cards as $index => $card): ?>
                                            <label class="payment-method-card <?= $index === 0 ? 'selected' : '' ?>" for="card_<?= $card['id'] ?>">
                                                <input 
                                                    type="radio" 
                                                    name="payment_method" 
                                                    id="card_<?= $card['id'] ?>" 
                                                    value="saved_card:<?= $card['id'] ?>" 
                                                    class="payment-method-radio"
                                                    <?= $index === 0 ? 'checked' : '' ?>
                                                >
                                                <div class="payment-method-content">
                                                    <div class="card-icon">
                                                        <?= strtoupper(substr($card['card_type'] ?? 'CARD', 0, 4)) ?>
                                                    </div>
                                                    <div class="payment-method-details">
                                                        <div class="payment-method-title">
                                                            <span class="card-number">•••• <?= htmlspecialchars($card['last_four_digits']) ?></span>
                                                            <?php if ($card['is_default']): ?>
                                                                <span class="default-badge">Default</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="payment-method-subtitle">
                                                            Expires <?= str_pad($card['expiry_month'], 2, '0', STR_PAD_LEFT) ?>/<?= $card['expiry_year'] ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    
                                    <!-- New Credit Card -->
                                    <label class="payment-method-card" for="new_card">
                                        <input 
                                            type="radio" 
                                            name="payment_method" 
                                            id="new_card" 
                                            value="new_card" 
                                            class="payment-method-radio"
                                            <?= empty($saved_cards) ? 'checked' : '' ?>
                                        >
                                        <div class="payment-method-content">
                                            <div class="payment-method-icon">
                                                <i class="fas fa-credit-card"></i>
                                            </div>
                                            <div class="payment-method-details">
                                                <div class="payment-method-title">New Credit Card</div>
                                                <div class="payment-method-subtitle">Pay with a different card</div>
                                            </div>
                                        </div>
                                    </label>
                                    
                                    <!-- Bank Transfer (Future) -->
                                    <label class="payment-method-card" for="bank_transfer" style="opacity: 0.5; cursor: not-allowed;">
                                        <input 
                                            type="radio" 
                                            name="payment_method" 
                                            id="bank_transfer" 
                                            value="bank_transfer" 
                                            class="payment-method-radio"
                                            disabled
                                        >
                                        <div class="payment-method-content">
                                            <div class="payment-method-icon" style="background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);">
                                                <i class="fas fa-university"></i>
                                            </div>
                                            <div class="payment-method-details">
                                                <div class="payment-method-title">Bank Transfer</div>
                                                <div class="payment-method-subtitle">Coming soon</div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="payment-actions">
                                <button type="submit" id="submitBtn" class="btn-payment btn-payment-primary" disabled>
                                    <i class="fas fa-lock"></i> Process Payment
                                </button>
                                <a href="my-account.php" class="btn-payment btn-payment-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <!-- Recent Payments -->
                    <?php if (!empty($recent_payments)): ?>
                        <div class="recent-payments">
                            <h4 style="margin-bottom: 16px; color: var(--primary-color);">
                                <i class="fas fa-history"></i> Recent Payments
                            </h4>
                            <?php foreach ($recent_payments as $payment): ?>
                                <div class="recent-payment-item">
                                    <div>
                                        <div class="recent-payment-date">
                                            <?= date('M j, Y g:i A', strtotime($payment['created_at'])) ?>
                                        </div>
                                        <div style="font-size: 13px; color: #6C757D;">
                                            <?= ucfirst($payment['transaction_type']) ?>
                                        </div>
                                    </div>
                                    <div class="recent-payment-amount">
                                        $<?= number_format($payment['amount'], 2) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Form JavaScript -->
<script>
// ===================================================================
// PAYMENT FORM JAVASCRIPT - REAL-TIME VALIDATION & INTERACTIONS
// ===================================================================

$(document).ready(function() {
    const maxAmount = <?= $amount_owed ?>;
    const minAmount = 10;
    
    // Amount Input Validation
    $('#paymentAmount').on('input', function() {
        const amount = parseFloat($(this).val());
        const $validation = $('#amountValidation');
        const $submitBtn = $('#submitBtn');
        
        $(this).removeClass('error');
        $validation.hide();
        
        if (!amount || isNaN(amount)) {
            $submitBtn.prop('disabled', true);
            return;
        }
        
        if (amount < minAmount) {
            $(this).addClass('error');
            $validation
                .removeClass('validation-success')
                .addClass('validation-error')
                .html('<i class="fas fa-exclamation-circle"></i> Minimum payment is $' + minAmount.toFixed(2))
                .show();
            $submitBtn.prop('disabled', true);
            return;
        }
        
        if (amount > maxAmount) {
            $(this).addClass('error');
            $validation
                .removeClass('validation-success')
                .addClass('validation-error')
                .html('<i class="fas fa-exclamation-circle"></i> Maximum payment is $' + maxAmount.toFixed(2))
                .show();
            $submitBtn.prop('disabled', true);
            return;
        }
        
        // Valid amount
        $validation
            .removeClass('validation-error')
            .addClass('validation-success')
            .html('<i class="fas fa-check-circle"></i> Payment amount valid')
            .show();
        $submitBtn.prop('disabled', false);
    });
    
    // Quick Amount Suggestions
    $('.amount-suggestion').on('click', function() {
        const amount = parseFloat($(this).data('amount'));
        $('#paymentAmount').val(amount.toFixed(2)).trigger('input');
    });
    
    // Payment Method Selection
    $('.payment-method-card').on('click', function() {
        $('.payment-method-card').removeClass('selected');
        $(this).addClass('selected');
        $(this).find('input[type="radio"]').prop('checked', true);
    });
    
    // Form Submission
    $('#paymentForm').on('submit', function(e) {
        const amount = parseFloat($('#paymentAmount').val());
        
        if (!amount || amount < minAmount || amount > maxAmount) {
            e.preventDefault();
            alert('Please enter a valid payment amount between $' + minAmount + ' and $' + maxAmount.toFixed(2));
            return false;
        }
        
        // Show loading state
        const $submitBtn = $('#submitBtn');
        $submitBtn.prop('disabled', true).html('<span class="spinner"></span> Processing...');
        
        // Allow form to submit
        return true;
    });
});
</script>

<?php
// Capture output and render with CIS template
$page_content = ob_get_clean();
require_once __DIR__ . '/../../shared/templates/base-layout.php';
