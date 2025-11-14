<?php
/**
 * Customer Management Service
 *
 * Handles all customer account operations for both retail and wholesale
 *
 * @package    WebsiteOperations
 * @version    1.0.0
 */

namespace Modules\WebsiteOperations\Services;

use PDO;
use PDOException;
use Exception;

class CustomerManagementService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get customer statistics
     */
    public function getCustomerStats(int $days = 30): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as total,
                    COUNT(CASE WHEN last_order_at >= DATE_SUB(NOW(), INTERVAL :days DAY) THEN 1 END) as active,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL :days DAY) THEN 1 END) as new,
                    COUNT(CASE WHEN is_wholesale = 1 THEN 1 END) as wholesale
                FROM web_customers
                WHERE status = 'active'
            ");

            $stmt->execute([':days' => $days]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get customer stats error: " . $e->getMessage());
            return ['total' => 0, 'active' => 0, 'new' => 0, 'wholesale' => 0];
        }
    }

    /**
     * Get customers with filters
     */
    public function getCustomers(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        try {
            $offset = ($page - 1) * $perPage;
            $where = ['c.status = "active"'];
            $params = [];

            if (!empty($filters['search'])) {
                $where[] = '(c.name LIKE :search OR c.email LIKE :search OR c.company LIKE :search)';
                $params[':search'] = '%' . $filters['search'] . '%';
            }

            if (isset($filters['is_wholesale'])) {
                $where[] = 'c.is_wholesale = :is_wholesale';
                $params[':is_wholesale'] = $filters['is_wholesale'];
            }

            $whereClause = implode(' AND ', $where);

            $stmt = $this->db->prepare("
                SELECT
                    c.*,
                    COUNT(DISTINCT o.id) as total_orders,
                    SUM(o.total_amount) as lifetime_value,
                    MAX(o.created_at) as last_order_date
                FROM web_customers c
                LEFT JOIN web_orders o ON c.id = o.customer_id
                WHERE $whereClause
                GROUP BY c.id
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset
            ");

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get customers error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get customer by ID
     */
    public function getCustomerById(int $customerId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    c.*,
                    COUNT(DISTINCT o.id) as total_orders,
                    SUM(o.total_amount) as lifetime_value
                FROM web_customers c
                LEFT JOIN web_orders o ON c.id = o.customer_id
                WHERE c.id = :id
                GROUP BY c.id
            ");

            $stmt->execute([':id' => $customerId]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                return null;
            }

            // Get recent orders
            $customer['recent_orders'] = $this->getCustomerOrders($customerId, 10);

            return $customer;
        } catch (PDOException $e) {
            error_log("Get customer by ID error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get customer orders
     */
    private function getCustomerOrders(int $customerId, int $limit = 10): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM web_orders
                WHERE customer_id = :customer_id
                ORDER BY created_at DESC
                LIMIT :limit
            ");

            $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Create customer
     */
    public function createCustomer(array $customerData): array
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO web_customers (
                    name, email, phone, company, is_wholesale, status, created_at
                ) VALUES (
                    :name, :email, :phone, :company, :is_wholesale, 'active', NOW()
                )
            ");

            $stmt->execute([
                ':name' => $customerData['name'],
                ':email' => $customerData['email'],
                ':phone' => $customerData['phone'] ?? null,
                ':company' => $customerData['company'] ?? null,
                ':is_wholesale' => $customerData['is_wholesale'] ?? 0
            ]);

            return [
                'success' => true,
                'customer_id' => $this->db->lastInsertId()
            ];
        } catch (PDOException $e) {
            error_log("Create customer error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
