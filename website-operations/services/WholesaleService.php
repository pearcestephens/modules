<?php
/**
 * Wholesale Service
 *
 * Handles wholesale account management and bulk ordering for Ecigdis
 *
 * @package    WebsiteOperations
 * @version    1.0.0
 */

namespace Modules\WebsiteOperations\Services;

use PDO;
use PDOException;

class WholesaleService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get pending approval count
     */
    public function getPendingApprovalCount(): int
    {
        try {
            $stmt = $this->db->query("
                SELECT COUNT(*) as count
                FROM wholesale_accounts
                WHERE status = 'pending'
            ");

            return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Get wholesale accounts
     */
    public function getWholesaleAccounts(array $filters = []): array
    {
        try {
            $where = ['1=1'];
            $params = [];

            if (!empty($filters['status'])) {
                $where[] = 'status = :status';
                $params[':status'] = $filters['status'];
            }

            $whereClause = implode(' AND ', $where);

            $stmt = $this->db->prepare("
                SELECT
                    w.*,
                    c.name as customer_name,
                    c.email as customer_email
                FROM wholesale_accounts w
                LEFT JOIN web_customers c ON w.customer_id = c.id
                WHERE $whereClause
                ORDER BY w.created_at DESC
            ");

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get wholesale accounts error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Approve wholesale account
     */
    public function approveAccount(int $accountId): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE wholesale_accounts
                SET status = 'approved',
                    approved_at = NOW(),
                    approved_by = :user_id
                WHERE id = :id
            ");

            return $stmt->execute([
                ':id' => $accountId,
                ':user_id' => $_SESSION['user']['id'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Approve account error: " . $e->getMessage());
            return false;
        }
    }
}
