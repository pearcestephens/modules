<?php
/**
 * PaymentModel - Payments model (VapeShed database)
 *
 * Manages orders_invoices table in VapeShed database
 *
 * @package CIS\BankTransactions\Models
 */

declare(strict_types=1);

namespace CIS\BankTransactions\Models;

require_once __DIR__ . '/BaseModel.php';

use PDO;

class PaymentModel extends BaseModel
{
    protected $table = 'orders_invoices';
    protected $primaryKey = 'invoice_id';

    /**
     * Constructor - uses VapeShed database
     *
     * Note: This model connects to the VapeShed orders database
     * where the orders_invoices table is stored
     */
    public function __construct()
    {
        // Use global VapeShed connection if available
        global $vapeShedCon;

        if (isset($vapeShedCon) && $vapeShedCon instanceof \PDO) {
            // Use existing VapeShed PDO connection
            $this->connection = $vapeShedCon;
        } else {
            // Fallback: Create new PDO connection if needed
            $config = require $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';

            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $config['vapeshed']['host'],
                $config['vapeshed']['database']
            );

            try {
                $this->connection = new \PDO(
                    $dsn,
                    $config['vapeshed']['username'],
                    $config['vapeshed']['password'],
                    [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
                        \PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (\PDOException $e) {
                throw new \Exception("VapeShed database connection failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Create payment record
     *
     * @param array $data Payment data
     * @return int Payment ID
     */
    public function create(array $data): int
    {
        // Generate invoice number if not provided
        if (!isset($data['invoice_number']) && isset($data['order_id'])) {
            $data['invoice_number'] = $this->generateInvoiceNumber((int)$data['order_id']);
        }

        // Set defaults
        $data['payment_status'] = $data['payment_status'] ?? 'paid';
        $data['payment_method'] = $data['payment_method'] ?? 'bank_transfer';
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');

        // Encode metadata if it's an array
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }

        return $this->insert($data);
    }

    /**
     * Legacy method - create payment (backwards compatibility)
     *
     * @param int $orderId Order ID
     * @param float $amount Payment amount
     * @param string $date Payment date (Y-m-d)
     * @param string $method Payment method
     * @param array $metadata Additional payment metadata
     * @return int Payment ID
     */
    public function createPayment(
        int $orderId,
        float $amount,
        string $date,
        string $method = 'bank_transfer',
        array $metadata = []
    ): int {
        return $this->create([
            'order_id' => $orderId,
            'amount' => $amount,
            'payment_date' => $date,
            'payment_method' => $method,
            'metadata' => $metadata
        ]);
    }

    /**
     * Generate invoice number
     *
     * Format: INV-YYYYMMDD-ORDERID
     *
     * @param int $orderId Order ID
     * @return string Invoice number
     */
    public function generateInvoiceNumber(int $orderId): string
    {
        return sprintf('INV-%s-%d', date('Ymd'), $orderId);
    }

    /**
     * Reassign payment to different order
     *
     * @param int $paymentId Payment ID
     * @param int $newOrderId New order ID
     * @param string $reason Reassignment reason
     * @param int $userId User performing reassignment
     * @return bool Success status
     */
    public function reassignPayment(
        int $paymentId,
        int $newOrderId,
        string $reason,
        int $userId
    ): bool {
        // Get current payment
        $payment = $this->find($paymentId);

        if (!$payment) {
            return false;
        }

        // Update payment with reassignment data
        $metadata = json_decode($payment['metadata'] ?? '{}', true);
        $metadata['reassignment'] = [
            'original_order_id' => $payment['order_id'],
            'new_order_id' => $newOrderId,
            'reason' => $reason,
            'reassigned_by' => $userId,
            'reassigned_at' => date('Y-m-d H:i:s')
        ];

        return $this->update($paymentId, [
            'order_id' => $newOrderId,
            'invoice_number' => $this->generateInvoiceNumber($newOrderId),
            'metadata' => json_encode($metadata),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Void payment
     *
     * @param int $paymentId Payment ID
     * @param int $userId User performing action
     * @param string $reason Reason for voiding
     * @return bool Success status
     */
    public function void(int $paymentId, int $userId, string $reason): bool
    {
        return $this->unlinkPayment($paymentId, $reason, $userId);
    }

    /**
     * Unlink payment from order (void) - legacy method
     *
     * @param int $paymentId Payment ID
     * @param string $reason Reason for unlinking
     * @param int $userId User performing action
     * @return bool Success status
     */
    public function unlinkPayment(int $paymentId, string $reason, int $userId): bool
    {
        $payment = $this->findById($paymentId);

        if (!$payment) {
            return false;
        }

        $metadata = json_decode($payment['metadata'] ?? '{}', true);
        $metadata['void'] = [
            'reason' => $reason,
            'voided_by' => $userId,
            'voided_at' => date('Y-m-d H:i:s')
        ];

        return $this->update($paymentId, [
            'payment_status' => 'void',
            'metadata' => json_encode($metadata),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get total paid for order
     *
     * @param int $orderId Order ID
     * @return float Total amount paid
     */
    public function getTotalPaidForOrder(int $orderId): float
    {
        $sql = "SELECT SUM(amount) as total
                FROM {$this->table}
                WHERE order_id = ?
                AND payment_status = 'paid'";

        $result = $this->query($sql, [$orderId]);
        return (float)($result[0]['total'] ?? 0.0);
    }

    /**
     * Get payments for order
     *
     * @param int $orderId Order ID
     * @return array Payments
     */
    public function getPaymentsForOrder(int $orderId): array
    {
        return $this->findAll(['order_id' => $orderId], ['order' => 'payment_date DESC']);
    }
}
