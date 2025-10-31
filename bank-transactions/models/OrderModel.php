<?php
/**
 * OrderModel - Orders model (VapeShed database)
 *
 * Manages orders table in VapeShed database
 *
 * @package CIS\BankTransactions\Models
 */

declare(strict_types=1);

namespace CIS\BankTransactions\Models;

require_once __DIR__ . '/BaseModel.php';

use CIS\Base\Database;
use PDO;

class OrderModel extends BaseModel
{
    protected $table = 'orders';
    protected $primaryKey = 'order_id';

    /**
     * Constructor - uses VapeShed database
     */
    public function __construct()
    {
        // Get VapeShed database connection
        $config = require $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';

        $this->db = new PDO(
            "mysql:host={$config['vapeshed']['host']};dbname={$config['vapeshed']['database']};charset=utf8mb4",
            $config['vapeshed']['username'],
            $config['vapeshed']['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    }

    /**
     * Find orders by customer name (fuzzy search)
     *
     * @param string $customerName Customer name to search
     * @param int $limit Number of results
     * @return array Orders
     */
    public function findByCustomerName(string $customerName, int $limit = 10): array
    {
        $sql = "SELECT
                    o.order_id,
                    o.customer_name,
                    o.customer_email,
                    o.outlet_id,
                    o.total_amount,
                    o.order_date,
                    o.status,
                    ou.outlet_name
                FROM {$this->table} o
                LEFT JOIN outlets ou ON o.outlet_id = ou.outlet_id
                WHERE o.customer_name LIKE ?
                ORDER BY o.order_date DESC
                LIMIT ?";

        $searchTerm = '%' . $customerName . '%';
        return $this->query($sql, [$searchTerm, $limit]);
    }

    /**
     * Find order by order number
     *
     * @param string $orderNumber Order number
     * @return array|null Order data
     */
    public function findByOrderNumber(string $orderNumber): ?array
    {
        $sql = "SELECT
                    o.*,
                    ou.outlet_name
                FROM {$this->table} o
                LEFT JOIN outlets ou ON o.outlet_id = ou.outlet_id
                WHERE o.order_number = ?
                LIMIT 1";

        $result = $this->query($sql, [$orderNumber]);
        return $result[0] ?? null;
    }

    /**
     * Find potential matches for transaction
     *
     * @param array $transaction Transaction data
     * @param int $limit Number of results
     * @return array Potential matching orders
     */
    public function findPotentialMatches(array $transaction, int $limit = 10): array
    {
        $amount = (float)$transaction['amount'];
        $date = $transaction['date'];
        $customerName = $transaction['customer_name'] ?? '';

        // Calculate amount tolerance (±5%)
        $amountMin = $amount * 0.95;
        $amountMax = $amount * 1.05;

        // Calculate date range (±2 days)
        $dateMin = date('Y-m-d', strtotime($date . ' -2 days'));
        $dateMax = date('Y-m-d', strtotime($date . ' +2 days'));

        $sql = "SELECT
                    o.order_id,
                    o.order_number,
                    o.customer_name,
                    o.customer_email,
                    o.total_amount,
                    o.order_date,
                    o.outlet_id,
                    ou.outlet_name,
                    o.status,
                    -- Relevance scoring
                    (
                        (CASE
                            WHEN o.total_amount BETWEEN ? AND ? THEN 100
                            ELSE 0
                        END) +
                        (CASE
                            WHEN o.order_date BETWEEN ? AND ? THEN 50
                            ELSE 0
                        END) +
                        (CASE
                            WHEN o.customer_name LIKE ? THEN 50
                            ELSE 0
                        END)
                    ) as relevance_score
                FROM {$this->table} o
                LEFT JOIN outlets ou ON o.outlet_id = ou.outlet_id
                WHERE o.total_amount BETWEEN ? AND ?
                AND o.order_date BETWEEN ? AND ?
                HAVING relevance_score > 0
                ORDER BY relevance_score DESC, o.order_date DESC
                LIMIT ?";

        $customerSearchTerm = '%' . $customerName . '%';

        return $this->query($sql, [
            $amountMin, $amountMax,  // Amount scoring
            $dateMin, $dateMax,       // Date scoring
            $customerSearchTerm,      // Name scoring
            $amountMin, $amountMax,   // Amount WHERE
            $dateMin, $dateMax,       // Date WHERE
            $limit
        ]);
    }

    /**
     * Check if order already has payment
     *
     * @param int $orderId Order ID
     * @return bool True if payment exists
     */
    public function hasPayment(int $orderId): bool
    {
        $sql = "SELECT COUNT(*) FROM orders_invoices
                WHERE order_id = ?
                AND payment_status = 'paid'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$orderId]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Get order with payments
     *
     * @param int $orderId Order ID
     * @return array|null Order with payment history
     */
    public function getOrderWithPayments(int $orderId): ?array
    {
        $sql = "SELECT
                    o.*,
                    ou.outlet_name,
                    GROUP_CONCAT(
                        CONCAT_WS('|', oi.invoice_id, oi.amount, oi.payment_date, oi.payment_method)
                        SEPARATOR ';'
                    ) as payments
                FROM {$this->table} o
                LEFT JOIN outlets ou ON o.outlet_id = ou.outlet_id
                LEFT JOIN orders_invoices oi ON o.order_id = oi.order_id
                WHERE o.order_id = ?
                GROUP BY o.order_id
                LIMIT 1";

        $result = $this->query($sql, [$orderId]);

        if (empty($result)) {
            return null;
        }

        $order = $result[0];

        // Parse payments
        if (!empty($order['payments'])) {
            $paymentsList = explode(';', $order['payments']);
            $order['payment_history'] = [];

            foreach ($paymentsList as $payment) {
                $parts = explode('|', $payment);
                if (count($parts) === 4) {
                    $order['payment_history'][] = [
                        'invoice_id' => $parts[0],
                        'amount' => $parts[1],
                        'payment_date' => $parts[2],
                        'payment_method' => $parts[3]
                    ];
                }
            }
        }

        unset($order['payments']);

        return $order;
    }
}
