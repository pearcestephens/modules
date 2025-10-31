<?php
/**
 * Bank Transactions - Payment Processor Library
 *
 * Handles payment creation, voiding, and status updates
 *
 * @package CIS\BankTransactions\Lib
 * @author Pearce Stephens
 */

declare(strict_types=1);

namespace CIS\BankTransactions\Lib;

class PaymentProcessor {

    private $con;
    private $paymentModel;

    public function __construct($connection = null) {
        global $con;
        $this->con = $connection ?? $con;

        require_once __DIR__ . '/../models/PaymentModel.php';
        $this->paymentModel = new \CIS\BankTransactions\Models\PaymentModel($this->con);
    }

    /**
     * Create a new payment
     */
    public function createPayment(array $data): int {
        // Validate required fields
        if (!isset($data['order_id'], $data['amount'], $data['payment_date'])) {
            throw new \Exception('Missing required payment fields');
        }

        // Sanitize amount
        $data['amount'] = (float)$data['amount'];
        if ($data['amount'] <= 0) {
            throw new \Exception('Payment amount must be greater than 0');
        }

        // Create payment record
        return $this->paymentModel->create($data);
    }

    /**
     * Void an existing payment
     */
    public function voidPayment(int $paymentId, int $userId, string $reason = ''): bool {
        $payment = $this->paymentModel->findById($paymentId);

        if (!$payment) {
            throw new \Exception('Payment not found');
        }

        if ($payment['status'] === 'void') {
            throw new \Exception('Payment is already voided');
        }

        // Update payment status
        $updateData = [
            'status' => 'void',
            'void_reason' => $reason,
            'voided_by' => $userId,
            'voided_at' => date('Y-m-d H:i:s'),
        ];

        return $this->paymentModel->update($paymentId, $updateData);
    }

    /**
     * Process refund for a payment
     */
    public function processRefund(int $paymentId, float $amount, int $userId, string $reason = ''): int {
        $payment = $this->paymentModel->findById($paymentId);

        if (!$payment) {
            throw new \Exception('Payment not found');
        }

        // Create refund as negative payment
        $refundData = [
            'order_id' => $payment['order_id'],
            'amount' => -abs($amount),
            'payment_date' => date('Y-m-d H:i:s'),
            'payment_type' => 'refund',
            'reference' => 'REFUND-' . $paymentId,
            'notes' => $reason,
            'created_by' => $userId,
            'status' => 'completed',
        ];

        return $this->paymentModel->create($refundData);
    }

    /**
     * Get payment summary for order
     */
    public function getOrderPaymentSummary(int $orderId): array {
        $payments = $this->paymentModel->getByOrderId($orderId);

        $total = 0;
        $count = 0;
        $voids = 0;
        $refunds = 0;

        foreach ($payments as $payment) {
            if ($payment['status'] === 'void') {
                $voids++;
            } elseif ($payment['payment_type'] === 'refund') {
                $refunds++;
                $total -= abs($payment['amount']);
            } else {
                $count++;
                $total += $payment['amount'];
            }
        }

        return [
            'order_id' => $orderId,
            'total_amount' => $total,
            'payment_count' => $count,
            'void_count' => $voids,
            'refund_count' => $refunds,
            'payments' => $payments,
        ];
    }

    /**
     * Reconcile payment against transaction
     */
    public function reconcilePayment(int $paymentId, int $transactionId): bool {
        $stmt = $this->con->prepare(
            "UPDATE bank_transactions SET payment_id = ?, reconciled_at = NOW() WHERE id = ?"
        );
        $stmt->bind_param('ii', $paymentId, $transactionId);
        return $stmt->execute();
    }

    /**
     * Batch update payment statuses
     */
    public function batchUpdateStatus(array $paymentIds, string $status): int {
        if (empty($paymentIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($paymentIds), '?'));
        $types = str_repeat('i', count($paymentIds)) . 's';

        $query = "UPDATE payments SET status = ? WHERE id IN ($placeholders)";
        $stmt = $this->con->prepare($query);

        $params = array_merge([$status], $paymentIds);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            return $stmt->affected_rows;
        }

        return 0;
    }
}
?>
