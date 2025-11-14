<?php
declare(strict_types=1);

/**
 * Supplier Service
 *
 * Handles supplier management and communication.
 *
 * Key Responsibilities:
 * - Supplier CRUD operations
 * - Email notifications to suppliers
 * - Supplier portal access
 * - Order history tracking
 * - Performance metrics
 *
 * @package CIS\Consignments\Services
 * @version 1.0.0
 */

namespace CIS\Consignments\Services;

use PDO;
use PDOException;
use RuntimeException;
use InvalidArgumentException;

class SupplierService
{
    private PDO $pdo;

    /**
     * Constructor
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get supplier by ID
     *
     * @param string $supplierId Supplier UUID
     * @return object|null Supplier object or null
     */
    public function get(string $supplierId): ?object
    {
        $stmt = $this->pdo->prepare("
            SELECT
                vs.*,
                COUNT(DISTINCT vc.id) AS total_orders,
                COALESCE(SUM(vc.total_cost), 0) AS total_value,
                MAX(vc.created_at) AS last_order_date
            FROM vend_suppliers vs
            LEFT JOIN vend_consignments vc ON vs.id = vc.supplier_id
                AND vc.transfer_category = 'PURCHASE_ORDER'
                AND vc.deleted_at IS NULL
            WHERE vs.id = :supplier_id
            GROUP BY vs.id
        ");

        $stmt->execute([':supplier_id' => $supplierId]);
        $result = $stmt->fetch(PDO::FETCH_OBJ);

        return $result ?: null;
    }

    /**
     * Get supplier by vendor code
     *
     * @param string $vendorCode Vendor code
     * @return object|null Supplier object or null
     */
    public function getByVendorCode(string $vendorCode): ?object
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM vend_suppliers
            WHERE source_variant_parent_id = ?
        ");

        $stmt->execute([$vendorCode]);
        $result = $stmt->fetch(PDO::FETCH_OBJ);

        return $result ?: null;
    }

    /**
     * List all active suppliers
     *
     * @param array $filters Optional filters
     *   - search: string (search name, email, phone)
     *   - active_only: bool (default true)
     * @return array Array of supplier objects
     */
    public function list(array $filters = []): array
    {
        $where = [];
        $params = [];

        // Active only filter (default true)
        if (!isset($filters['active_only']) || $filters['active_only'] === true) {
            $where[] = "vs.deleted_at IS NULL";
        }

        // Search filter
        if (!empty($filters['search'])) {
            $where[] = "(vs.name LIKE :search OR vs.email LIKE :search OR vs.phone LIKE :search OR vs.source_variant_parent_id LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT
                vs.id,
                vs.name,
                vs.email,
                vs.phone,
                vs.source_variant_parent_id AS vendor_code,
                COUNT(DISTINCT vc.id) AS total_orders,
                COALESCE(SUM(vc.total_cost), 0) AS total_value,
                MAX(vc.created_at) AS last_order_date
            FROM vend_suppliers vs
            LEFT JOIN vend_consignments vc ON vs.id = vc.supplier_id
                AND vc.transfer_category = 'PURCHASE_ORDER'
                AND vc.deleted_at IS NULL
            $whereClause
            GROUP BY vs.id
            ORDER BY vs.name ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get purchase orders for supplier
     *
     * @param string $supplierId Supplier UUID
     * @param array $filters Optional filters
     *   - state: string|array (filter by state)
     *   - date_from: string (YYYY-MM-DD)
     *   - date_to: string (YYYY-MM-DD)
     * @param int $limit Results per page
     * @param int $offset Pagination offset
     * @return array Purchase orders
     */
    public function getPurchaseOrders(string $supplierId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where = [
            "vc.supplier_id = :supplier_id",
            "vc.transfer_category = 'PURCHASE_ORDER'",
            "vc.deleted_at IS NULL"
        ];
        $params = [':supplier_id' => $supplierId];

        // State filter
        if (!empty($filters['state'])) {
            if (is_array($filters['state'])) {
                $placeholders = implode(',', array_fill(0, count($filters['state']), '?'));
                $where[] = "vc.state IN ($placeholders)";
                $params = array_merge($params, $filters['state']);
            } else {
                $where[] = "vc.state = ?";
                $params[] = $filters['state'];
            }
        }

        // Date filters
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(vc.created_at) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(vc.created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT
                vc.id,
                vc.public_id,
                vc.state,
                vc.created_at,
                vc.expected_delivery_date,
                vc.sent_at,
                vc.received_at,
                vo.name AS outlet_name,
                COUNT(DISTINCT vcli.id) AS line_item_count,
                COALESCE(SUM(vcli.quantity), 0) AS total_quantity,
                COALESCE(SUM(vcli.total_cost), 0) AS total_amount
            FROM vend_consignments vc
            LEFT JOIN vend_outlets vo ON vc.outlet_to = vo.id
            LEFT JOIN vend_consignment_line_items vcli ON vc.id = vcli.transfer_id
            WHERE $whereClause
            GROUP BY vc.id
            ORDER BY vc.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);

        // Bind named parameters first, then positional
        $namedParams = [':supplier_id' => $supplierId];
        foreach ($namedParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        // Execute with remaining params
        $remainingParams = array_slice($params, 1);
        $stmt->execute($remainingParams);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Send email notification to supplier
     *
     * @param string $supplierId Supplier UUID
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param array $attachments Optional file attachments
     * @return bool Success
     */
    public function sendEmail(string $supplierId, string $subject, string $body, array $attachments = []): bool
    {
        // Get supplier
        $supplier = $this->get($supplierId);
        if (!$supplier) {
            throw new InvalidArgumentException("Supplier not found: $supplierId");
        }

        if (empty($supplier->email)) {
            throw new RuntimeException("Supplier has no email address");
        }

        // ✅ IMPLEMENTED: Email notification service integrated
        require_once __DIR__ . '/../../Services/EmailNotificationService.php';
        $mailer = new \ConsignmentsModule\Services\EmailNotificationService($this->pdo);

        // Send email using the notification service
        return $mailer->sendSupplierNotification(
            $supplierId,
            $subject,
            $body,
            $attachments ?? []
        );

        // Legacy code below kept for reference but no longer executed
        /*
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO supplier_email_log (
                    supplier_id,
                    to_email,
                    subject,
                    body,
                    has_attachments,
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, 'queued', NOW())
            ");

            $stmt->execute([
                $supplierId,
                $supplier->email,
                $subject,
                $body,
                !empty($attachments) ? 1 : 0
            ]);

            // In production, queue this for actual sending via mail service

            return true;

        } catch (PDOException $e) {
            throw new RuntimeException("Failed to queue supplier email: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Send PO notification to supplier
     *
     * @param int $poId Purchase order ID
     * @param string $type Notification type: 'created'|'approved'|'sent'|'cancelled'
     * @return bool Success
     */
    public function sendPONotification(int $poId, string $type): bool
    {
        // Get PO details
        $stmt = $this->pdo->prepare("
            SELECT
                vc.id,
                vc.public_id,
                vc.state,
                vc.supplier_id,
                vc.outlet_to,
                vc.expected_delivery_date,
                vc.supplier_reference,
                vc.total_cost,
                vs.name AS supplier_name,
                vs.email AS supplier_email,
                vo.name AS outlet_name,
                vo.address AS outlet_address
            FROM vend_consignments vc
            JOIN vend_suppliers vs ON vc.supplier_id = vs.id
            LEFT JOIN vend_outlets vo ON vc.outlet_to = vo.id
            WHERE vc.id = ? AND vc.transfer_category = 'PURCHASE_ORDER'
        ");
        $stmt->execute([$poId]);
        $po = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$po) {
            throw new InvalidArgumentException("Purchase order not found: $poId");
        }

        // Generate email content based on type
        $subject = $this->generateEmailSubject($po, $type);
        $body = $this->generateEmailBody($po, $type);

        return $this->sendEmail($po->supplier_id, $subject, $body);
    }

    /**
     * Get supplier performance metrics
     *
     * @param string $supplierId Supplier UUID
     * @param int $days Number of days to analyze (default 90)
     * @return array Performance metrics
     */
    public function getPerformanceMetrics(string $supplierId, int $days = 90): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(DISTINCT vc.id) AS total_orders,
                COALESCE(SUM(vc.total_cost), 0) AS total_value,
                AVG(vc.total_cost) AS avg_order_value,
                COUNT(DISTINCT CASE WHEN vc.state = 'RECEIVED' THEN vc.id END) AS completed_orders,
                COUNT(DISTINCT CASE WHEN vc.state = 'CANCELLED' THEN vc.id END) AS cancelled_orders,
                AVG(TIMESTAMPDIFF(DAY, vc.created_at, vc.received_at)) AS avg_fulfillment_days,
                AVG(CASE
                    WHEN vc.received_at IS NOT NULL AND vc.expected_delivery_date IS NOT NULL
                    THEN TIMESTAMPDIFF(DAY, vc.expected_delivery_date, vc.received_at)
                    ELSE NULL
                END) AS avg_delay_days,
                SUM(vcli.quantity) AS total_items_ordered,
                SUM(vcli.quantity_received) AS total_items_received,
                SUM(vcli.quantity_damaged) AS total_items_damaged
            FROM vend_consignments vc
            LEFT JOIN vend_consignment_line_items vcli ON vc.id = vcli.transfer_id
            WHERE vc.supplier_id = :supplier_id
              AND vc.transfer_category = 'PURCHASE_ORDER'
              AND vc.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
              AND vc.deleted_at IS NULL
            GROUP BY vc.supplier_id
        ");

        $stmt->execute([
            ':supplier_id' => $supplierId,
            ':days' => $days
        ]);

        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$metrics || $metrics['total_orders'] == 0) {
            return [
                'period_days' => $days,
                'total_orders' => 0,
                'no_data' => true
            ];
        }

        // Calculate derived metrics
        $metrics['completion_rate'] = $metrics['total_orders'] > 0
            ? round(($metrics['completed_orders'] / $metrics['total_orders']) * 100, 2)
            : 0;

        $metrics['cancellation_rate'] = $metrics['total_orders'] > 0
            ? round(($metrics['cancelled_orders'] / $metrics['total_orders']) * 100, 2)
            : 0;

        $metrics['receipt_accuracy'] = $metrics['total_items_ordered'] > 0
            ? round(($metrics['total_items_received'] / $metrics['total_items_ordered']) * 100, 2)
            : 0;

        $metrics['damage_rate'] = $metrics['total_items_received'] > 0
            ? round(($metrics['total_items_damaged'] / $metrics['total_items_received']) * 100, 2)
            : 0;

        $metrics['period_days'] = $days;

        return $metrics;
    }

    /**
     * Get top products ordered from supplier
     *
     * @param string $supplierId Supplier UUID
     * @param int $limit Number of products to return
     * @param int $days Period to analyze (default 90)
     * @return array Top products
     */
    public function getTopProducts(string $supplierId, int $limit = 10, int $days = 90): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                vp.id AS product_id,
                vp.sku,
                vp.name AS product_name,
                COUNT(DISTINCT vc.id) AS order_count,
                SUM(vcli.quantity) AS total_quantity,
                COALESCE(SUM(vcli.total_cost), 0) AS total_value,
                AVG(vcli.unit_cost) AS avg_unit_cost
            FROM vend_consignment_line_items vcli
            JOIN vend_consignments vc ON vcli.transfer_id = vc.id
            JOIN vend_products vp ON vcli.product_id = vp.id
            WHERE vc.supplier_id = :supplier_id
              AND vc.transfer_category = 'PURCHASE_ORDER'
              AND vc.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
              AND vc.deleted_at IS NULL
              AND vcli.deleted_at IS NULL
            GROUP BY vp.id
            ORDER BY total_quantity DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':supplier_id', $supplierId);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // PRIVATE HELPER METHODS
    // =========================================================================

    /**
     * Generate email subject for PO notification
     */
    private function generateEmailSubject(object $po, string $type): string
    {
        $subjects = [
            'created' => "New Purchase Order #{$po->public_id} - The Vape Shed",
            'approved' => "Purchase Order #{$po->public_id} Approved - The Vape Shed",
            'sent' => "Purchase Order #{$po->public_id} Sent - Awaiting Delivery",
            'cancelled' => "Purchase Order #{$po->public_id} Cancelled"
        ];

        return $subjects[$type] ?? "Purchase Order #{$po->public_id} Update";
    }

    /**
     * Generate email body for PO notification
     */
    private function generateEmailBody(object $po, string $type): string
    {
        // Get line items
        $stmt = $this->pdo->prepare("
            SELECT sku, name, quantity, unit_cost, total_cost
            FROM vend_consignment_line_items
            WHERE transfer_id = ? AND deleted_at IS NULL
            ORDER BY name ASC
        ");
        $stmt->execute([$po->id]);
        $lineItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Build line items HTML
        $itemsHtml = '';
        foreach ($lineItems as $item) {
            $itemsHtml .= sprintf(
                '<tr>
                    <td>%s</td>
                    <td>%s</td>
                    <td align="right">%d</td>
                    <td align="right">$%.2f</td>
                    <td align="right">$%.2f</td>
                </tr>',
                htmlspecialchars($item['sku']),
                htmlspecialchars($item['name']),
                $item['quantity'],
                $item['unit_cost'],
                $item['total_cost']
            );
        }

        // Email template (responsive HTML)
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; }
        .footer { background: #ecf0f1; padding: 15px; text-align: center; font-size: 12px; color: #7f8c8d; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; }
        th { background: #3498db; color: white; text-align: left; }
        .total { font-weight: bold; background: #ecf0f1; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>The Vape Shed</h1>
            <h2>Purchase Order #{$po->public_id}</h2>
        </div>

        <div class="content">
            <p>Dear {$po->supplier_name},</p>

            <p><strong>Order Details:</strong></p>
            <ul>
                <li><strong>PO Number:</strong> {$po->public_id}</li>
                <li><strong>Delivery Location:</strong> {$po->outlet_name}</li>
                <li><strong>Delivery Address:</strong> {$po->outlet_address}</li>
                <li><strong>Expected Delivery:</strong> {$po->expected_delivery_date}</li>
                <li><strong>Your Reference:</strong> {$po->supplier_reference}</li>
            </ul>

            <table>
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Product</th>
                        <th align="right">Qty</th>
                        <th align="right">Unit Price</th>
                        <th align="right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    $itemsHtml
                    <tr class="total">
                        <td colspan="4" align="right">Total:</td>
                        <td align="right">\${$po->total_cost}</td>
                    </tr>
                </tbody>
            </table>

            <p>Please confirm receipt of this order and provide an estimated delivery date.</p>

            <p>Thank you for your continued partnership.</p>
        </div>

        <div class="footer">
            <p>The Vape Shed | New Zealand's Premier Vape Retailer</p>
            <p>This is an automated notification. Please do not reply directly to this email.</p>
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }
}
