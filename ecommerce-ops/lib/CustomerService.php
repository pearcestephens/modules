<?php
/**
 * Customer Service
 *
 * Handles customer operations including Vend sync, fraud detection, and age verification.
 * Replaces functionality from customers-overview.php and view-customer.php
 *
 * @package CIS\Modules\EcommerceOps\Services
 */

namespace CIS\Modules\EcommerceOps;

class CustomerService {

    private $db;
    private $vendAPI;

    /**
     * Constructor
     */
    public function __construct() {
        global $conn, $VendAPI;
        $this->db = $conn;
        $this->vendAPI = $VendAPI;
    }

    /**
     * Get customer by ID
     *
     * @param int $customerId
     * @return array|null
     */
    public function getCustomer(int $customerId): ?array {
        $stmt = $this->db->prepare("
            SELECT
                c.*,
                COUNT(DISTINCT o.id) as total_orders,
                SUM(o.total_price) as lifetime_value,
                MAX(o.order_date) as last_order_date,
                av.verified_at,
                av.verification_status,
                fb.id as is_blacklisted
            FROM vend_customers c
            LEFT JOIN vend_sales o ON c.id = o.customer_id
            LEFT JOIN ecommerce_age_verifications av ON c.id = av.customer_id AND av.verification_status = 'approved'
            LEFT JOIN ecommerce_fraud_blacklist fb ON (
                fb.email = c.email
                OR fb.phone = c.phone
                OR fb.mobile = c.mobile
            ) AND fb.is_active = 1
            WHERE c.id = ?
            GROUP BY c.id
        ");

        $stmt->execute([$customerId]);
        $customer = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $customer ?: null;
    }

    /**
     * Get customer by Vend ID
     *
     * @param string $vendCustomerId
     * @return array|null
     */
    public function getCustomerByVendId(string $vendCustomerId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM vend_customers WHERE vend_customer_id = ?");
        $stmt->execute([$vendCustomerId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get customer by email
     *
     * @param string $email
     * @return array|null
     */
    public function getCustomerByEmail(string $email): ?array {
        $stmt = $this->db->prepare("SELECT * FROM vend_customers WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * List customers with filters and pagination
     *
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function listCustomers(array $filters = [], int $page = 1, int $perPage = 50): array {
        $where = ['1=1'];
        $params = [];

        // Search filter
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
            $params = array_merge($params, [$search, $search, $search, $search]);
        }

        // Age verification status filter
        if (!empty($filters['age_verified'])) {
            if ($filters['age_verified'] === 'yes') {
                $where[] = "av.verification_status = 'approved'";
            } elseif ($filters['age_verified'] === 'no') {
                $where[] = "(av.verification_status IS NULL OR av.verification_status != 'approved')";
            }
        }

        // Blacklist filter
        if (!empty($filters['blacklisted'])) {
            if ($filters['blacklisted'] === 'yes') {
                $where[] = "fb.id IS NOT NULL";
            } elseif ($filters['blacklisted'] === 'no') {
                $where[] = "fb.id IS NULL";
            }
        }

        // Order count filter
        if (!empty($filters['min_orders'])) {
            $where[] = "order_count >= ?";
            $params[] = (int)$filters['min_orders'];
        }

        $whereSQL = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        // Get total count
        $countStmt = $this->db->prepare("
            SELECT COUNT(DISTINCT c.id)
            FROM vend_customers c
            LEFT JOIN vend_sales o ON c.id = o.customer_id
            LEFT JOIN ecommerce_age_verifications av ON c.id = av.customer_id AND av.verification_status = 'approved'
            LEFT JOIN ecommerce_fraud_blacklist fb ON (
                fb.email = c.email
                OR fb.phone = c.phone
                OR fb.mobile = c.mobile
            ) AND fb.is_active = 1
            WHERE $whereSQL
        ");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        // Get customers
        $stmt = $this->db->prepare("
            SELECT
                c.*,
                COUNT(DISTINCT o.id) as order_count,
                SUM(o.total_price) as lifetime_value,
                MAX(o.order_date) as last_order_date,
                av.verified_at,
                av.verification_status,
                CASE WHEN fb.id IS NOT NULL THEN 1 ELSE 0 END as is_blacklisted
            FROM vend_customers c
            LEFT JOIN vend_sales o ON c.id = o.customer_id
            LEFT JOIN ecommerce_age_verifications av ON c.id = av.customer_id AND av.verification_status = 'approved'
            LEFT JOIN ecommerce_fraud_blacklist fb ON (
                fb.email = c.email
                OR fb.phone = c.phone
                OR fb.mobile = c.mobile
            ) AND fb.is_active = 1
            WHERE $whereSQL
            GROUP BY c.id
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?
        ");

        $params[] = $perPage;
        $params[] = $offset;
        $stmt->execute($params);

        return [
            'customers' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Check if customer matches fraud patterns
     *
     * @param array $customer
     * @return array Matching fraud entries
     */
    public function checkFraudPatterns(array $customer): array {
        $stmt = $this->db->prepare("
            SELECT * FROM ecommerce_fraud_blacklist
            WHERE is_active = 1
            AND (
                email = ?
                OR phone = ?
                OR mobile = ?
                OR LOWER(address_line1) = LOWER(?)
            )
        ");

        $stmt->execute([
            $customer['email'] ?? '',
            $customer['phone'] ?? '',
            $customer['mobile'] ?? '',
            $customer['physical_address1'] ?? ''
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Add customer to fraud blacklist
     *
     * @param int $customerId
     * @param string $reason
     * @param int $staffId
     * @return bool
     */
    public function addToBlacklist(int $customerId, string $reason, int $staffId): bool {
        $customer = $this->getCustomer($customerId);

        if (!$customer) {
            return false;
        }

        // Insert blacklist entry
        $stmt = $this->db->prepare("
            INSERT INTO ecommerce_fraud_blacklist
            (customer_id, email, phone, mobile, address_line1, reason, added_by_staff_id, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");

        $result = $stmt->execute([
            $customerId,
            $customer['email'],
            $customer['phone'] ?? null,
            $customer['mobile'] ?? null,
            $customer['physical_address1'] ?? null,
            $reason,
            $staffId
        ]);

        // Update Vend customer notes
        if ($result && $this->vendAPI) {
            $this->addVendCustomerNote($customer['vend_customer_id'], "⚠️ FRAUD ALERT: $reason - Staff ID: $staffId", $staffId);
        }

        ecomm_log_error("Customer added to blacklist", [
            'customer_id' => $customerId,
            'reason' => $reason,
            'staff_id' => $staffId
        ]);

        return $result;
    }

    /**
     * Remove customer from fraud blacklist
     *
     * @param int $customerId
     * @param int $staffId
     * @return bool
     */
    public function removeFromBlacklist(int $customerId, int $staffId): bool {
        $customer = $this->getCustomer($customerId);

        if (!$customer) {
            return false;
        }

        // Deactivate blacklist entries
        $stmt = $this->db->prepare("
            UPDATE ecommerce_fraud_blacklist
            SET is_active = 0, removed_at = NOW(), removed_by_staff_id = ?
            WHERE customer_id = ? AND is_active = 1
        ");

        $result = $stmt->execute([$staffId, $customerId]);

        // Update Vend customer notes
        if ($result && $this->vendAPI) {
            $this->addVendCustomerNote($customer['vend_customer_id'], "✅ Fraud alert removed - Staff ID: $staffId", $staffId);
        }

        ecomm_log_error("Customer removed from blacklist", [
            'customer_id' => $customerId,
            'staff_id' => $staffId
        ]);

        return $result;
    }

    /**
     * Update customer age verification status
     *
     * @param int $customerId
     * @param string $status (approved, rejected, pending)
     * @return bool
     */
    public function updateAgeVerificationStatus(int $customerId, string $status): bool {
        $stmt = $this->db->prepare("
            UPDATE ecommerce_age_verifications
            SET verification_status = ?,
                verified_at = CASE WHEN ? = 'approved' THEN NOW() ELSE NULL END
            WHERE customer_id = ?
        ");

        return $stmt->execute([$status, $status, $customerId]);
    }

    /**
     * Sync customer from Vend
     *
     * @param string $vendCustomerId
     * @return bool
     */
    public function syncFromVend(string $vendCustomerId): bool {
        if (!$this->vendAPI) {
            return false;
        }

        try {
            $vendCustomer = $this->vendAPI->getCustomer($vendCustomerId);

            if (!$vendCustomer) {
                return false;
            }

            // Check if customer exists
            $existing = $this->getCustomerByVendId($vendCustomerId);

            if ($existing) {
                // Update existing customer
                $stmt = $this->db->prepare("
                    UPDATE vend_customers SET
                        first_name = ?,
                        last_name = ?,
                        email = ?,
                        phone = ?,
                        mobile = ?,
                        physical_address1 = ?,
                        physical_address2 = ?,
                        physical_suburb = ?,
                        physical_city = ?,
                        physical_postcode = ?,
                        customer_group_id = ?,
                        updated_at = NOW()
                    WHERE vend_customer_id = ?
                ");

                return $stmt->execute([
                    $vendCustomer['first_name'] ?? '',
                    $vendCustomer['last_name'] ?? '',
                    $vendCustomer['email'] ?? '',
                    $vendCustomer['phone'] ?? null,
                    $vendCustomer['mobile'] ?? null,
                    $vendCustomer['physical_address1'] ?? null,
                    $vendCustomer['physical_address2'] ?? null,
                    $vendCustomer['physical_suburb'] ?? null,
                    $vendCustomer['physical_city'] ?? null,
                    $vendCustomer['physical_postcode'] ?? null,
                    $vendCustomer['customer_group_id'] ?? null,
                    $vendCustomerId
                ]);
            } else {
                // Insert new customer
                $stmt = $this->db->prepare("
                    INSERT INTO vend_customers (
                        vend_customer_id, first_name, last_name, email, phone, mobile,
                        physical_address1, physical_address2, physical_suburb, physical_city,
                        physical_postcode, customer_group_id, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");

                return $stmt->execute([
                    $vendCustomerId,
                    $vendCustomer['first_name'] ?? '',
                    $vendCustomer['last_name'] ?? '',
                    $vendCustomer['email'] ?? '',
                    $vendCustomer['phone'] ?? null,
                    $vendCustomer['mobile'] ?? null,
                    $vendCustomer['physical_address1'] ?? null,
                    $vendCustomer['physical_address2'] ?? null,
                    $vendCustomer['physical_suburb'] ?? null,
                    $vendCustomer['physical_city'] ?? null,
                    $vendCustomer['physical_postcode'] ?? null,
                    $vendCustomer['customer_group_id'] ?? null
                ]);
            }
        } catch (\Exception $e) {
            ecomm_log_error("Failed to sync customer from Vend", [
                'vend_customer_id' => $vendCustomerId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Add note to Vend customer profile
     *
     * @param string $vendCustomerId
     * @param string $note
     * @param int $staffId
     * @return bool
     */
    private function addVendCustomerNote(string $vendCustomerId, string $note, int $staffId): bool {
        if (!$this->vendAPI) {
            return false;
        }

        try {
            return $this->vendAPI->addCustomerNote($vendCustomerId, $note);
        } catch (\Exception $e) {
            ecomm_log_error("Failed to add Vend customer note", [
                'vend_customer_id' => $vendCustomerId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get customer order history
     *
     * @param int $customerId
     * @param int $limit
     * @return array
     */
    public function getOrderHistory(int $customerId, int $limit = 20): array {
        $stmt = $this->db->prepare("
            SELECT
                o.*,
                vo.name as outlet_name
            FROM vend_sales o
            LEFT JOIN vend_outlets vo ON o.outlet_id = vo.id
            WHERE o.customer_id = ?
            ORDER BY o.order_date DESC
            LIMIT ?
        ");

        $stmt->execute([$customerId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get customer age verification attempts
     *
     * @param int $customerId
     * @return array
     */
    public function getAgeVerificationAttempts(int $customerId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM ecommerce_age_verifications
            WHERE customer_id = ?
            ORDER BY created_at DESC
        ");

        $stmt->execute([$customerId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
