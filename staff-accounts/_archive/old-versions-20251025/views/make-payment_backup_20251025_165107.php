<?php
/**
 * Staff Accounts - Make Payment View
 * 
 * Purpose: Payment processing interface for staff to pay down account balances
 * 
 * Features:
 * - Amount input with balance validation
 * - Payment method selection (credit card, saved cards, bank transfer)
 * - Nuvei payment gateway integration
 * - Real-time validation
 * - Confirmation flow with receipt
 * - High-end professional CSS
 * 
 * Database Tables:
 * - staff_payment_transactions (records payments)
 * - staff_saved_cards (stored payment methods)
 * - staff_account_reconciliation (updates balance)
 * 
 * @package CIS\Modules\StaffAccounts
 * @version 2.0.0
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

// Page title
$page_title = "Make a Payment";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - CIS Staff Accounts</title>
    
    <!-- Bootstrap 4.2 + Font Awesome -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.3/css/all.css">
    
    <style>
        /* ===================================================================
           HIGH-END PROFESSIONAL CSS - PAYMENT UI
           =================================================================== */
        
        :root {
            --primary-color: #2C3E50;
            --secondary-color: #3498DB;
            --success-color: #27AE60;
            --danger-color: #E74C3C;
            --warning-color: #F39C12;
            --light-bg: #F8F9FA;
            --border-color: #DEE2E6;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.12);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.16);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        
        /* Payment Container */
        .payment-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        /* Payment Card */
        .payment-card {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            margin-bottom: 24px;
            transition: var(--transition);
        }
        
        .payment-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 48px rgba(0,0,0,0.2);
        }
        
        /* Card Header */
        .payment-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #34495E 100%);
            color: white;
            padding: 32px;
            position: relative;
            overflow: hidden;
        }
        
        .payment-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        .payment-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 8px 0;
            position: relative;
            z-index: 1;
        }
        
        .payment-header p {
            margin: 0;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        /* Balance Display */
        .balance-display {
            background: linear-gradient(135deg, #FF6B6B 0%, #EE5A6F 100%);
            padding: 24px;
            border-radius: 12px;
            margin: -16px 0 24px 0;
            text-align: center;
            box-shadow: var(--shadow-md);
        }
        
        .balance-display.positive {
            background: linear-gradient(135deg, var(--success-color) 0%, #229954 100%);
        }
        
        .balance-label {
            color: rgba(255,255,255,0.9);
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        
        .balance-amount {
            color: white;
            font-size: 48px;
            font-weight: 800;
            line-height: 1;
            text-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        /* Payment Form */
        .payment-body {
            padding: 32px;
        }
        
        .form-section {
            margin-bottom: 32px;
        }
        
        .form-section-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }
        
        /* Amount Input */
        .amount-input-group {
            position: relative;
            margin-bottom: 24px;
        }
        
        .amount-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .currency-symbol {
            position: absolute;
            left: 20px;
            font-size: 32px;
            font-weight: 700;
            color: var(--secondary-color);
            pointer-events: none;
            z-index: 2;
        }
        
        .amount-input {
            width: 100%;
            font-size: 32px;
            font-weight: 700;
            padding: 16px 24px 16px 56px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            transition: var(--transition);
            text-align: left;
        }
        
        .amount-input:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
            outline: none;
        }
        
        .amount-input.error {
            border-color: var(--danger-color);
        }
        
        .amount-suggestions {
            display: flex;
            gap: 12px;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        
        .amount-suggestion {
            flex: 1;
            min-width: 100px;
            padding: 12px;
            background: var(--light-bg);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
        }
        
        .amount-suggestion:hover {
            background: var(--secondary-color);
            border-color: var(--secondary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        /* Payment Method Selection */
        .payment-methods {
            display: grid;
            gap: 16px;
        }
        
        .payment-method-card {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }
        
        .payment-method-card:hover {
            border-color: var(--secondary-color);
            box-shadow: var(--shadow-sm);
            transform: translateX(4px);
        }
        
        .payment-method-card.selected {
            border-color: var(--secondary-color);
            background: rgba(52, 152, 219, 0.05);
            box-shadow: var(--shadow-md);
        }
        
        .payment-method-radio {
            position: absolute;
            right: 20px;
            top: 20px;
        }
        
        .payment-method-content {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .payment-method-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, #2980B9 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            flex-shrink: 0;
        }
        
        .payment-method-details {
            flex: 1;
        }
        
        .payment-method-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 4px;
        }
        
        .payment-method-subtitle {
            font-size: 14px;
            color: #6C757D;
        }
        
        /* Saved Cards */
        .saved-card {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .card-icon {
            width: 48px;
            height: 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 700;
        }
        
        .card-number {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .default-badge {
            background: var(--success-color);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        /* Action Buttons */
        .payment-actions {
            display: flex;
            gap: 16px;
            margin-top: 32px;
        }
        
        .btn-payment {
            flex: 1;
            padding: 16px 32px;
            font-size: 16px;
            font-weight: 700;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-payment-primary {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #2980B9 100%);
            color: white;
            box-shadow: var(--shadow-md);
        }
        
        .btn-payment-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(52, 152, 219, 0.4);
        }
        
        .btn-payment-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-payment-secondary {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--border-color);
        }
        
        .btn-payment-secondary:hover {
            background: var(--light-bg);
            border-color: var(--primary-color);
        }
        
        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Alert Messages */
        .alert-custom {
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            border: none;
            box-shadow: var(--shadow-sm);
        }
        
        .alert-info {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: white;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, var(--danger-color) 0%, #C0392B 100%);
            color: white;
        }
        
        .alert-warning {
            background: linear-gradient(135deg, var(--warning-color) 0%, #D68910 100%);
            color: white;
        }
        
        /* Validation Messages */
        .validation-message {
            font-size: 14px;
            margin-top: 8px;
            padding: 8px 12px;
            border-radius: 6px;
        }
        
        .validation-error {
            color: var(--danger-color);
            background: rgba(231, 76, 60, 0.1);
        }
        
        .validation-success {
            color: var(--success-color);
            background: rgba(39, 174, 96, 0.1);
        }
        
        /* Recent Payments */
        .recent-payments {
            background: var(--light-bg);
            border-radius: 12px;
            padding: 24px;
            margin-top: 24px;
        }
        
        .recent-payment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .recent-payment-item:last-child {
            border-bottom: none;
        }
        
        .recent-payment-date {
            font-size: 13px;
            color: #6C757D;
        }
        
        .recent-payment-amount {
            font-weight: 700;
            color: var(--success-color);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .payment-container {
                padding: 0 12px;
                margin: 20px auto;
            }
            
            .payment-header {
                padding: 24px;
            }
            
            .payment-body {
                padding: 24px;
            }
            
            .balance-amount {
                font-size: 36px;
            }
            
            .amount-input {
                font-size: 24px;
                padding: 12px 16px 12px 48px;
            }
            
            .currency-symbol {
                font-size: 24px;
                left: 16px;
            }
            
            .payment-actions {
                flex-direction: column;
            }
            
            .amount-suggestion {
                min-width: calc(50% - 6px);
            }
        }
    </style>
</head>
<body>
    <div class="payment-container">
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
                    <form id="paymentForm" method="POST" action="api/process-payment.php">
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
    
    <!-- jQuery + Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/js/bootstrap.bundle.min.js"></script>
    
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
                    .html('<i class="fas fa-check-circle"></i> Payment amount is valid')
                    .show();
                $submitBtn.prop('disabled', false);
            });
            
            // Quick Amount Suggestions
            $('.amount-suggestion').on('click', function() {
                const amount = $(this).data('amount');
                $('#paymentAmount').val(amount).trigger('input');
                
                // Visual feedback
                $('.amount-suggestion').removeClass('active');
                $(this).addClass('active');
            });
            
            // Payment Method Selection
            $('.payment-method-card').on('click', function() {
                $('.payment-method-card').removeClass('selected');
                $(this).addClass('selected');
                $(this).find('input[type="radio"]').prop('checked', true);
            });
            
            // Form Submission
            $('#paymentForm').on('submit', function(e) {
                e.preventDefault();
                
                const $submitBtn = $('#submitBtn');
                const $form = $(this);
                
                // Disable submit button and show loading
                $submitBtn.prop('disabled', true).html('<span class="spinner"></span> Processing...');
                
                // Submit form via AJAX
                $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    data: $form.serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Redirect to success page
                            window.location.href = 'payment-success.php?transaction_id=' + response.transaction_id;
                        } else {
                            // Show error
                            alert('Payment failed: ' + (response.error || 'Unknown error'));
                            $submitBtn.prop('disabled', false).html('<i class="fas fa-lock"></i> Process Payment');
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Payment processing error. Please try again.');
                        $submitBtn.prop('disabled', false).html('<i class="fas fa-lock"></i> Process Payment');
                    }
                });
            });
        });
    </script>
</body>
</html>
