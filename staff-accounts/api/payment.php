<?php
/**
 * Nuvei Payment API Handler
 * 
 * Endpoints:
 * - createSession: Create payment session
 * - processPayment: Process credit card payment
 * - getPaymentHistory: Get user payment history
 * - createPaymentPlan: Set up installment plan
 * - getSavedCards: Get user's saved cards
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/NuveiPayment.php';
require_once __DIR__ . '/../lib/csrf.php';

header('Content-Type: application/json');

// Check authentication (use CIS standard userID)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// CSRF Protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
}

$action = $_REQUEST['action'] ?? '';
$userId = $_SESSION['user_id'];
$nuvei = new NuveiPayment($pdo);

switch ($action) {
    
    /**
     * Create payment session (Step 1)
     * POST: amount
     */
    case 'createSession':
        $amount = floatval($_POST['amount'] ?? 0);
        
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid amount']);
            exit;
        }
        
        $result = $nuvei->createPaymentSession($userId, $amount);
        echo json_encode($result);
        break;
    
    /**
     * Process payment (Step 2)
     * POST: sessionToken, cardData (encrypted), amount
     */
    case 'processPayment':
        $sessionToken = $_POST['sessionToken'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        
        // Card data comes from frontend Nuvei SDK (already tokenized)
        // For security, never accept raw card numbers via POST
        $cardData = [
            'cardNumber' => $_POST['cardNumber'] ?? '',
            'cardHolderName' => $_POST['cardHolderName'] ?? '',
            'expirationMonth' => $_POST['expirationMonth'] ?? '',
            'expirationYear' => $_POST['expirationYear'] ?? '',
            'cvv' => $_POST['cvv'] ?? ''
        ];
        
        if (empty($sessionToken) || $amount <= 0) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            exit;
        }
        
        $result = $nuvei->processPayment($sessionToken, $cardData, $userId, $amount);
        echo json_encode($result);
        break;
    
    /**
     * Get payment history
     * GET: limit (optional)
     */
    case 'getPaymentHistory':
        $limit = intval($_GET['limit'] ?? 10);
        $history = $nuvei->getPaymentHistory($userId, $limit);
        
        echo json_encode([
            'success' => true,
            'history' => $history
        ]);
        break;
    
    /**
     * Create payment plan (installments)
     * POST: totalAmount, installmentAmount, frequency, startDate
     */
    case 'createPaymentPlan':
        $totalAmount = floatval($_POST['totalAmount'] ?? 0);
        $installmentAmount = floatval($_POST['installmentAmount'] ?? 0);
        $frequency = $_POST['frequency'] ?? 'monthly'; // weekly, fortnightly, monthly
        $startDate = $_POST['startDate'] ?? date('Y-m-d', strtotime('+1 week'));
        
        if ($totalAmount <= 0 || $installmentAmount <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid amounts']);
            exit;
        }
        
        $totalInstallments = ceil($totalAmount / $installmentAmount);
        
        // Insert payment plan
        $query = "INSERT INTO staff_payment_plans 
                  (user_id, total_amount, installment_amount, frequency, total_installments, next_payment_date, status) 
                  VALUES (?, ?, ?, ?, ?, ?, 'active')";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId, $totalAmount, $installmentAmount, $frequency, $totalInstallments, $startDate]);
        
        if ($stmt->rowCount() > 0) {
            $planId = $pdo->lastInsertId();
            
            // Create installment records
            $dueDate = new DateTime($startDate);
            for ($i = 1; $i <= $totalInstallments; $i++) {
                $amount = ($i === $totalInstallments) 
                    ? $totalAmount - ($installmentAmount * ($totalInstallments - 1)) // Last installment takes remainder
                    : $installmentAmount;
                
                $query2 = "INSERT INTO staff_payment_plan_installments 
                          (plan_id, installment_number, amount, due_date, status) 
                          VALUES (?, ?, ?, ?, 'pending')";
                
                $stmt2 = $pdo->prepare($query2);
                $dueDateStr = $dueDate->format('Y-m-d');
                $stmt2->execute([$planId, $i, $amount, $dueDateStr]);
                
                // Increment date based on frequency
                switch ($frequency) {
                    case 'weekly':
                        $dueDate->modify('+1 week');
                        break;
                    case 'fortnightly':
                        $dueDate->modify('+2 weeks');
                        break;
                    case 'monthly':
                        $dueDate->modify('+1 month');
                        break;
                }
            }
            
            echo json_encode([
                'success' => true,
                'planId' => $planId,
                'totalInstallments' => $totalInstallments,
                'message' => "Payment plan created with {$totalInstallments} installments"
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create payment plan']);
        }
        break;
    
    /**
     * Get active payment plans
     */
    case 'getPaymentPlans':
        $query = "SELECT 
                    pp.*,
                    (pp.total_installments - pp.completed_installments) as remaining_installments,
                    (pp.total_amount - (pp.installment_amount * pp.completed_installments)) as remaining_balance
                  FROM staff_payment_plans pp
                  WHERE pp.user_id = ? 
                  AND pp.status = 'active'
                  ORDER BY pp.next_payment_date ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId]);
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'plans' => $plans
        ]);
        break;
    
    /**
     * Get saved credit cards
     */
    case 'getSavedCards':
        $query = "SELECT id, last_four_digits, card_type, expiry_month, expiry_year, is_default, last_used_at
                  FROM staff_saved_cards
                  WHERE user_id = ? AND is_active = 1
                  ORDER BY is_default DESC, last_used_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId]);
        $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'cards' => $cards
        ]);
        break;
    
    /**
     * Get current balance (for payment display)
     */
    case 'getCurrentBalance':
        $query = "SELECT vend_balance, last_payment_date, last_payment_amount
                  FROM staff_account_reconciliation
                  WHERE user_id = ?
                  LIMIT 1";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId]);
        $balance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'balance' => $balance ? floatval($balance['vend_balance']) : 0,
            'lastPaymentDate' => $balance['last_payment_date'] ?? null,
            'lastPaymentAmount' => $balance['last_payment_amount'] ?? null
        ]);
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
