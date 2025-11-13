<?php
/**
 * Apply Payment API Endpoint
 *
 * Applies a failed/unallocated payment to a Vend customer account
 *
 * @package CIS\Modules\StaffAccounts\API
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use CIS\Modules\StaffAccounts\PaymentService;

header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    $userId = $input['user_id'] ?? '';
    $vendCustomerId = $input['vend_customer_id'] ?? '';
    $amount = floatval($input['amount'] ?? 0);
    $paymentId = $input['payment_id'] ?? null;

    // Validate inputs
    if (empty($userId)) {
        throw new Exception('User ID is required');
    }

    if (empty($vendCustomerId)) {
        throw new Exception('Vend customer ID is required');
    }

    if ($amount <= 0) {
        throw new Exception('Amount must be greater than 0');
    }

    // Apply the payment using PaymentService
    $result = PaymentService::applyFailedPayment(
        $userId,
        $vendCustomerId,
        $amount,
        ['attempt' => 1]
    );

    if ($result['success']) {
        // Mark payment as allocated in sales_payments table
        if ($paymentId) {
            $pdo = cis_resolve_pdo();
            $stmt = $pdo->prepare("
                UPDATE sales_payments
                SET sale_status = 'ALLOCATED',
                    allocated_at = NOW(),
                    allocated_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $paymentId]);

            // Get full payment details for statement
            $stmt = $pdo->prepare("
                SELECT
                    sp.*,
                    CONCAT(u.first_name, ' ', u.last_name) as staff_name,
                    u.email as staff_email,
                    vc.customer_code,
                    vc.balance as vend_balance,
                    vo.name as outlet_full_name
                FROM sales_payments sp
                LEFT JOIN vend_customers vc ON sp.vend_customer_id = vc.id
                LEFT JOIN users u ON u.vend_customer_account = vc.id
                LEFT JOIN vend_outlets vo ON sp.outlet_name = vo.name
                WHERE sp.id = ?
            ");
            $stmt->execute([$paymentId]);
            $paymentDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Log the allocation
        error_log(sprintf(
            "[PAYMENT APPLIED] User: %s, Customer: %s, Amount: $%s, Applied by: %s",
            $userId,
            $vendCustomerId,
            number_format($amount, 2),
            $_SESSION['user_name'] ?? 'Unknown'
        ));

        echo json_encode([
            'success' => true,
            'message' => 'Payment applied successfully',
            'allocated' => $result['allocated'] ?? $amount,
            'balance_before' => $result['balance_before'] ?? null,
            'balance_after' => $result['balance_after'] ?? null,
            'transaction_id' => $result['transaction_id'] ?? null,
            'payment_details' => $paymentDetails ?? null
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Failed to apply payment',
            'error_category' => $result['error_category'] ?? 'unknown'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
