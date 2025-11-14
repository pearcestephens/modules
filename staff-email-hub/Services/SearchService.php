<?php
declare(strict_types=1);

namespace StaffEmailHub\Services;

use PDO;
use Exception;

/**
 * SearchService - Advanced full-text and filtered search across customers and emails
 *
 * Features: Full-text search, filters, sorting, facets, pagination
 *
 * @version 1.0.0
 */
class SearchService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Global search across customers and emails
     */
    public function globalSearch(string $query, int $page = 1, int $perPage = 20): array
    {
        try {
            if (strlen($query) < 2) {
                return $this->error('Search query too short');
            }

            $results = [
                'customers' => $this->searchCustomers($query, 10)['customers'] ?? [],
                'emails' => $this->searchEmails($query, 10)['emails'] ?? [],
                'query' => $query
            ];

            return [
                'success' => true,
                'results' => $results,
                'total' => count($results['customers']) + count($results['emails'])
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Search customers by name, email, phone, vend ID, notes, tags
     */
    public function searchCustomers(string $query, int $limit = 50, array $filters = []): array
    {
        try {
            // Clean query
            $query = trim($query);

            $sql = "
                SELECT DISTINCT cp.id, cp.customer_id, cp.full_name, cp.email, cp.phone,
                       cp.vend_customer_id, cp.total_spent, cp.purchase_count, cp.is_vip,
                       cp.is_flagged, cp.id_verified, cp.tags, cp.created_at
                FROM customer_hub_profile cp
                WHERE 1=1
            ";

            $params = [];

            // Search by full-text
            if ($query) {
                $sql .= " AND (
                    cp.full_name LIKE ? OR
                    cp.email LIKE ? OR
                    cp.phone LIKE ? OR
                    cp.vend_customer_id LIKE ? OR
                    cp.notes LIKE ? OR
                    cp.address LIKE ? OR
                    cp.suburb LIKE ?
                )";

                $searchTerm = "%{$query}%";
                $params = array_fill(0, 7, $searchTerm);
            }

            // Apply filters
            if (isset($filters['vip_only']) && $filters['vip_only']) {
                $sql .= " AND cp.is_vip = TRUE";
            }

            if (isset($filters['flagged_only']) && $filters['flagged_only']) {
                $sql .= " AND cp.is_flagged = TRUE";
            }

            if (isset($filters['id_verified']) && $filters['id_verified']) {
                $sql .= " AND cp.id_verified = TRUE";
            }

            if (isset($filters['min_spent']) && $filters['min_spent']) {
                $sql .= " AND cp.total_spent >= ?";
                $params[] = $filters['min_spent'];
            }

            if (isset($filters['sort_by'])) {
                switch ($filters['sort_by']) {
                    case 'recent':
                        $sql .= " ORDER BY cp.created_at DESC";
                        break;
                    case 'spent':
                        $sql .= " ORDER BY cp.total_spent DESC";
                        break;
                    case 'purchases':
                        $sql .= " ORDER BY cp.purchase_count DESC";
                        break;
                    case 'name':
                        $sql .= " ORDER BY cp.full_name ASC";
                        break;
                    default:
                        $sql .= " ORDER BY cp.created_at DESC";
                }
            } else {
                $sql .= " ORDER BY cp.created_at DESC";
            }

            $sql .= " LIMIT ?";
            $params[] = $limit;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'customers' => $customers,
                'count' => count($customers)
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Search emails by subject, body, recipient, sender
     */
    public function searchEmails(string $query, int $limit = 50, array $filters = []): array
    {
        try {
            $query = trim($query);

            $sql = "
                SELECT se.id, se.trace_id, se.from_staff_id, se.to_email, se.customer_id,
                       se.subject, se.status, se.is_r18_flagged, se.assigned_to,
                       se.created_at, se.sent_at, se.tags
                FROM staff_emails se
                WHERE 1=1
            ";

            $params = [];

            // Full-text search
            if ($query) {
                $sql .= " AND (
                    se.subject LIKE ? OR
                    se.to_email LIKE ? OR
                    se.body_html LIKE ? OR
                    se.body_plain LIKE ? OR
                    se.notes LIKE ?
                )";

                $searchTerm = "%{$query}%";
                $params = array_fill(0, 5, $searchTerm);
            }

            // Apply filters
            if (isset($filters['status'])) {
                $sql .= " AND se.status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['r18_only']) && $filters['r18_only']) {
                $sql .= " AND se.is_r18_flagged = TRUE";
            }

            if (isset($filters['assigned_to'])) {
                $sql .= " AND se.assigned_to = ?";
                $params[] = $filters['assigned_to'];
            }

            if (isset($filters['customer_id'])) {
                $sql .= " AND se.customer_id = ?";
                $params[] = $filters['customer_id'];
            }

            if (isset($filters['date_from'])) {
                $sql .= " AND se.created_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $sql .= " AND se.created_at <= ?";
                $params[] = $filters['date_to'];
            }

            // Sorting
            if (isset($filters['sort_by'])) {
                switch ($filters['sort_by']) {
                    case 'recent':
                        $sql .= " ORDER BY se.created_at DESC";
                        break;
                    case 'oldest':
                        $sql .= " ORDER BY se.created_at ASC";
                        break;
                    case 'priority':
                        $sql .= " ORDER BY se.priority DESC";
                        break;
                    case 'subject':
                        $sql .= " ORDER BY se.subject ASC";
                        break;
                    default:
                        $sql .= " ORDER BY se.created_at DESC";
                }
            } else {
                $sql .= " ORDER BY se.created_at DESC";
            }

            $sql .= " LIMIT ?";
            $params[] = $limit;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'emails' => $emails,
                'count' => count($emails)
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Find all emails from/to customer
     */
    public function findCustomerEmails(int $customerId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, trace_id, from_staff_id, subject, status, is_r18_flagged,
                       created_at, sent_at, tags
                FROM staff_emails
                WHERE customer_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$customerId]);

            return [
                'success' => true,
                'emails' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Find customer by email address
     */
    public function findByEmail(string $email): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM customer_hub_profile
                WHERE email = ?
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                return $this->error('Customer not found');
            }

            return [
                'success' => true,
                'customer' => $customer
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Find customer by phone
     */
    public function findByPhone(string $phone): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM customer_hub_profile
                WHERE phone LIKE ?
                LIMIT 1
            ");
            $stmt->execute(["%{$phone}%"]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                return $this->error('Customer not found');
            }

            return [
                'success' => true,
                'customer' => $customer
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Find customer by Vend ID
     */
    public function findByVendId(string $vendId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM customer_hub_profile
                WHERE vend_customer_id = ?
                LIMIT 1
            ");
            $stmt->execute([$vendId]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                return $this->error('Customer not found');
            }

            return [
                'success' => true,
                'customer' => $customer
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Advanced faceted search
     */
    public function getFacets(): array
    {
        try {
            // Count by status
            $statusStmt = $this->db->query("
                SELECT status, COUNT(*) as count
                FROM staff_emails
                GROUP BY status
            ");
            $statuses = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

            // Count VIP customers
            $vipStmt = $this->db->query("
                SELECT COUNT(*) as count FROM customer_hub_profile WHERE is_vip = TRUE
            ");
            $vipCount = $vipStmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Count flagged customers
            $flaggedStmt = $this->db->query("
                SELECT COUNT(*) as count FROM customer_hub_profile WHERE is_flagged = TRUE
            ");
            $flaggedCount = $flaggedStmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Count ID verified
            $verifiedStmt = $this->db->query("
                SELECT COUNT(*) as count FROM customer_hub_profile WHERE id_verified = TRUE
            ");
            $verifiedCount = $verifiedStmt->fetch(PDO::FETCH_ASSOC)['count'];

            return [
                'success' => true,
                'email_statuses' => $statuses,
                'vip_customers' => $vipCount,
                'flagged_customers' => $flaggedCount,
                'id_verified_customers' => $verifiedCount
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    private function error(string $message): array
    {
        error_log("[SearchService] {$message}");
        return ['success' => false, 'error' => $message];
    }
}
