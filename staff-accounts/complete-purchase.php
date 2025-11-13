<?php
/**
 * Complete Purchase API Endpoint
 *
 * Processes staff purchases with discount calculations
 * Creates transaction records and updates account balances
 * Syncs with Lightspeed for inventory management
 *
 * @package StaffAccounts
 * @version 1.0.0
 */

declare(strict_types=1);

// Bootstrap
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/lightspeed-api.php';

// Authentication required
requireAuthentication();

// Get authenticated staff
$staff = getCurrentStaff();

// CORS headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get request body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new InvalidArgumentException('Invalid JSON payload');
    }

    // Validate required fields
    $required = ['items', 'discount_type', 'payment_method'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            throw new InvalidArgumentException("Missing required field: {$field}");
        }
    }

    // Validate items
    if (!is_array($data['items']) || empty($data['items'])) {
        throw new InvalidArgumentException('Items must be a non-empty array');
    }

    // Validate discount type
    $discount_type = $data['discount_type'];
    if (!in_array($discount_type, ['staff', 'friends', 'family'])) {
        throw new InvalidArgumentException('Invalid discount type');
    }

    // Validate payment method
    $payment_method = $data['payment_method'];
    if (!in_array($payment_method, ['payroll', 'direct', 'mixed'])) {
        throw new InvalidArgumentException('Invalid payment method');
    }

    // Get discount rate
    $discount_rate = getStaffDiscountRate($staff['id'], $discount_type);

    // Get database connection
    $db = getDatabaseConnection();

    // Start transaction
    $db->beginTransaction();

    try {
        // Generate purchase number
        $purchase_number = generatePurchaseNumber($staff['id']);

        // Calculate totals
        $subtotal = 0;
        $processed_items = [];

        foreach ($data['items'] as $item) {
            if (!isset($item['product_id'], $item['quantity'])) {
                throw new InvalidArgumentException('Each item must have product_id and quantity');
            }

            $retail_price = (float)($item['retail_price'] ?? 0);
            $quantity = (int)$item['quantity'];

            if ($retail_price <= 0 || $quantity <= 0) {
                throw new InvalidArgumentException('Invalid price or quantity');
            }

            $staff_price = $retail_price * (1 - ($discount_rate / 100));
            $line_total = $staff_price * $quantity;
            $subtotal += $retail_price * $quantity;

            $processed_items[] = [
                'product_id' => $item['product_id'],
                'product_name' => $item['name'] ?? '',
                'product_sku' => $item['sku'] ?? '',
                'quantity' => $quantity,
                'retail_price' => $retail_price,
                'staff_price' => round($staff_price, 2),
                'discount_rate' => $discount_rate,
                'line_total' => round($line_total, 2)
            ];
        }

        // Calculate discount and total
        $discount_amount = $subtotal * ($discount_rate / 100);
        $total = $subtotal - $discount_amount;
        $gst_amount = $total * 0.15; // NZ GST 15%

        // Check credit limit
        $stmt = $db->prepare("
            SELECT balance, credit_limit, status
            FROM staff_accounts
            WHERE staff_id = ?
        ");
        $stmt->execute([$staff['id']]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            throw new RuntimeException('Staff account not found');
        }

        if ($account['status'] !== 'active') {
            throw new RuntimeException('Staff account is not active');
        }

        // Check if purchase would exceed credit limit
        $new_balance = $account['balance'] - $total;
        $available_credit = $account['credit_limit'] + $account['balance'];

        if ($payment_method === 'payroll' && $total > $available_credit) {
            throw new RuntimeException(sprintf(
                'Purchase amount ($%.2f) exceeds available credit ($%.2f)',
                $total,
                $available_credit
            ));
        }

        // Call stored procedure to record purchase
        $stmt = $db->prepare("
            CALL sp_record_staff_purchase(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @transaction_id, @purchase_id)
        ");

        $stmt->execute([
            $staff['id'],
            $purchase_number,
            $discount_type,
            $discount_rate,
            $subtotal,
            $discount_amount,
            $total,
            $gst_amount,
            $payment_method,
            $data['notes'] ?? null
        ]);

        // Get output parameters
        $result = $db->query("SELECT @transaction_id AS transaction_id, @purchase_id AS purchase_id")->fetch(PDO::FETCH_ASSOC);
        $transaction_id = $result['transaction_id'];
        $purchase_id = $result['purchase_id'];

        // Insert purchase items
        $stmt = $db->prepare("
            INSERT INTO staff_purchase_items (
                purchase_id, product_id, product_name, product_sku,
                quantity, retail_price, staff_price, discount_rate, line_total
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($processed_items as $item) {
            $stmt->execute([
                $purchase_id,
                $item['product_id'],
                $item['product_name'],
                $item['product_sku'],
                $item['quantity'],
                $item['retail_price'],
                $item['staff_price'],
                $item['discount_rate'],
                $item['line_total']
            ]);
        }

        // Generate receipt
        $receipt_number = generateReceiptNumber($purchase_id);
        $receipt_html = generateReceiptHTML($purchase_id, $staff, $processed_items, [
            'purchase_number' => $purchase_number,
            'subtotal' => $subtotal,
            'discount_rate' => $discount_rate,
            'discount_amount' => $discount_amount,
            'total' => $total,
            'gst_amount' => $gst_amount,
            'payment_method' => $payment_method
        ]);

        // Store receipt
        $stmt = $db->prepare("
            INSERT INTO staff_receipts (
                purchase_id, receipt_number, staff_id, receipt_html
            ) VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$purchase_id, $receipt_number, $staff['id'], $receipt_html]);
        $receipt_id = $db->lastInsertId();

        // Update purchase with receipt info
        $stmt = $db->prepare("
            UPDATE staff_purchases
            SET receipt_generated = TRUE, receipt_url = ?
            WHERE id = ?
        ");
        $receipt_url = '/staff-accounts/receipt.php?id=' . $receipt_id;
        $stmt->execute([$receipt_url, $purchase_id]);

        // Sync with Lightspeed (optional - can be done async)
        if (isset($data['sync_lightspeed']) && $data['sync_lightspeed']) {
            try {
                $lightspeed = new LightspeedAPI();
                $sale_data = [
                    'register_id' => $data['register_id'] ?? null,
                    'customer_id' => $staff['vend_customer_id'] ?? null,
                    'sale_date' => date('Y-m-d H:i:s'),
                    'status' => 'CLOSED',
                    'line_items' => array_map(function($item) {
                        return [
                            'product_id' => $item['product_id'],
                            'quantity' => $item['quantity'],
                            'price' => $item['staff_price'],
                            'discount' => $item['retail_price'] - $item['staff_price']
                        ];
                    }, $processed_items)
                ];

                $sale_response = $lightspeed->post('sales', $sale_data);

                if ($sale_response['success']) {
                    // Update purchase with Vend sale ID
                    $vend_sale_id = $sale_response['data']['sale']['id'];
                    $stmt = $db->prepare("UPDATE staff_purchases SET vend_sale_id = ? WHERE id = ?");
                    $stmt->execute([$vend_sale_id, $purchase_id]);
                }
            } catch (Exception $e) {
                // Log but don't fail transaction
                error_log('[STAFF_ACCOUNTS] Lightspeed sync warning: ' . $e->getMessage());
            }
        }

        // Commit transaction
        $db->commit();

        // Build success response
        $response = [
            'success' => true,
            'data' => [
                'transaction_id' => $transaction_id,
                'purchase_id' => $purchase_id,
                'purchase_number' => $purchase_number,
                'receipt_id' => $receipt_id,
                'receipt_number' => $receipt_number,
                'receipt_url' => $receipt_url,
                'summary' => [
                    'subtotal' => round($subtotal, 2),
                    'discount_type' => $discount_type,
                    'discount_rate' => $discount_rate,
                    'discount_amount' => round($discount_amount, 2),
                    'total' => round($total, 2),
                    'gst_amount' => round($gst_amount, 2),
                    'items_count' => count($processed_items)
                ],
                'account' => [
                    'balance_before' => (float)$account['balance'],
                    'balance_after' => round($new_balance, 2),
                    'available_credit' => round($available_credit - $total, 2)
                ],
                'payment' => [
                    'method' => $payment_method,
                    'deduction_scheduled' => $payment_method === 'payroll'
                ]
            ],
            'message' => 'Purchase completed successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        echo json_encode($response, JSON_PRETTY_PRINT);

        // Log successful purchase
        error_log(sprintf(
            '[STAFF_ACCOUNTS] Purchase completed: %s | Staff: %s | Total: $%.2f',
            $purchase_number,
            $staff['first_name'] . ' ' . $staff['last_name'],
            $total
        ));

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => 'INVALID_REQUEST'
    ]);

} catch (RuntimeException $e) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => 'TRANSACTION_ERROR'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An unexpected error occurred',
        'error_code' => 'INTERNAL_ERROR'
    ]);

    // Log error
    error_log(sprintf(
        '[STAFF_ACCOUNTS] Purchase Error: %s | File: %s | Line: %d | Staff: %d',
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $staff['id'] ?? 0
    ));
}
