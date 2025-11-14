<?php
declare(strict_types=1);

namespace StaffEmailHub\Controllers;

use StaffEmailHub\Services\SearchService;
use PDO;
use Exception;

/**
 * SearchController - Global and advanced search across customers and emails
 *
 * Routes:
 * GET /search - Global search across all data
 * GET /search/customers - Search customers
 * GET /search/emails - Search emails
 * GET /search/facets - Get search facets for filtering
 * GET /search/by-email/{email} - Find customer by email
 * GET /search/by-phone/{phone} - Find customer by phone
 * GET /search/by-vend-id/{vendId} - Find customer by Vend ID
 * GET /search/customer/{id}/emails - Find emails for customer
 *
 * @version 1.0.0
 */
class SearchController
{
    private SearchService $searchService;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->searchService = new SearchService($db);
    }

    /**
     * GET /search - Global search across customers and emails
     */
    public function globalSearch(string $query, int $page = 1, int $perPage = 20): array
    {
        try {
            if (strlen($query) < 2) {
                return $this->error('Search query too short (minimum 2 characters)');
            }

            $result = $this->searchService->globalSearch($query, $page, $perPage);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => [
                    'query' => $query,
                    'customers' => $result['results']['customers'],
                    'emails' => $result['results']['emails'],
                    'total_results' => $result['total'],
                    'page' => $page,
                    'per_page' => $perPage
                ]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /search/customers - Search customers with advanced filters
     *
     * Filters:
     * - vip_only: bool - Show only VIP customers
     * - flagged_only: bool - Show only flagged customers
     * - id_verified: bool - Show only ID verified customers
     * - min_spent: number - Minimum total spent
     * - sort_by: recent|spent|purchases|name
     */
    public function searchCustomers(
        string $query = '',
        int $limit = 50,
        array $filters = [],
        int $page = 1
    ): array {
        try {
            if (strlen($query) < 1 && empty($filters)) {
                return $this->error('Please provide a search query or select filters');
            }

            $result = $this->searchService->searchCustomers($query, $limit, $filters);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => [
                    'customers' => $result['customers'],
                    'count' => $result['count'],
                    'query' => $query,
                    'filters' => $filters,
                    'page' => $page,
                    'per_page' => $limit
                ]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /search/emails - Search emails with advanced filters
     *
     * Filters:
     * - status: draft|sent|bounced|failed - Email status
     * - r18_only: bool - Show only R18 flagged emails
     * - assigned_to: int - Staff ID emails are assigned to
     * - customer_id: int - Emails from specific customer
     * - date_from: YYYY-MM-DD - Start date
     * - date_to: YYYY-MM-DD - End date
     * - sort_by: recent|oldest|priority|subject
     */
    public function searchEmails(
        string $query = '',
        int $limit = 50,
        array $filters = [],
        int $page = 1
    ): array {
        try {
            if (strlen($query) < 1 && empty($filters)) {
                return $this->error('Please provide a search query or select filters');
            }

            $result = $this->searchService->searchEmails($query, $limit, $filters);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => [
                    'emails' => $result['emails'],
                    'count' => $result['count'],
                    'query' => $query,
                    'filters' => $filters,
                    'page' => $page,
                    'per_page' => $limit
                ]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /search/facets - Get search facets for building filter UI
     */
    public function getFacets(): array
    {
        try {
            $result = $this->searchService->getFacets();

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => [
                    'email_statuses' => $result['email_statuses'],
                    'vip_customers' => $result['vip_customers'],
                    'flagged_customers' => $result['flagged_customers'],
                    'id_verified_customers' => $result['id_verified_customers']
                ]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /search/by-email/{email} - Find customer by email address
     */
    public function findByEmail(string $email): array
    {
        try {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->error('Invalid email address');
            }

            $result = $this->searchService->findByEmail($email);

            if (!$result['success']) {
                return [
                    'status' => 'success',
                    'data' => null,
                    'message' => 'Customer not found'
                ];
            }

            return [
                'status' => 'success',
                'data' => $result['customer']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /search/by-phone/{phone} - Find customer by phone number
     */
    public function findByPhone(string $phone): array
    {
        try {
            if (strlen(preg_replace('/[^0-9]/', '', $phone)) < 7) {
                return $this->error('Invalid phone number');
            }

            $result = $this->searchService->findByPhone($phone);

            if (!$result['success']) {
                return [
                    'status' => 'success',
                    'data' => null,
                    'message' => 'Customer not found'
                ];
            }

            return [
                'status' => 'success',
                'data' => $result['customer']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /search/by-vend-id/{vendId} - Find customer by Vend customer ID
     */
    public function findByVendId(string $vendId): array
    {
        try {
            if (empty($vendId)) {
                return $this->error('Vend ID is required');
            }

            $result = $this->searchService->findByVendId($vendId);

            if (!$result['success']) {
                return [
                    'status' => 'success',
                    'data' => null,
                    'message' => 'Customer not found'
                ];
            }

            return [
                'status' => 'success',
                'data' => $result['customer']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /search/customer/{customerId}/emails - Find all emails for customer
     */
    public function findCustomerEmails(int $customerId): array
    {
        try {
            $result = $this->searchService->findCustomerEmails($customerId);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => [
                    'emails' => $result['emails'],
                    'count' => count($result['emails'])
                ]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /search/advanced - Advanced search with multiple filters
     */
    public function advancedSearch(array $criteria): array
    {
        try {
            // This method builds complex queries from multiple criteria
            $results = [];

            // Search customers if customer criteria provided
            if (isset($criteria['customer_search'])) {
                $custResult = $this->searchService->searchCustomers(
                    $criteria['customer_search']['query'] ?? '',
                    $criteria['customer_search']['limit'] ?? 50,
                    $criteria['customer_search']['filters'] ?? []
                );
                $results['customers'] = $custResult['success'] ? $custResult['customers'] : [];
            }

            // Search emails if email criteria provided
            if (isset($criteria['email_search'])) {
                $emailResult = $this->searchService->searchEmails(
                    $criteria['email_search']['query'] ?? '',
                    $criteria['email_search']['limit'] ?? 50,
                    $criteria['email_search']['filters'] ?? []
                );
                $results['emails'] = $emailResult['success'] ? $emailResult['emails'] : [];
            }

            return [
                'status' => 'success',
                'data' => $results
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /search/recent - Get recent customers and emails
     */
    public function getRecent(int $limit = 20): array
    {
        try {
            // Recent customers
            $custStmt = $this->db->prepare("
                SELECT id, customer_id, full_name, email, purchase_count, total_spent
                FROM customer_hub_profile
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $custStmt->execute([$limit]);
            $customers = $custStmt->fetchAll(PDO::FETCH_ASSOC);

            // Recent emails
            $emailStmt = $this->db->prepare("
                SELECT id, trace_id, subject, to_email, status, created_at
                FROM staff_emails
                WHERE deleted_at IS NULL
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $emailStmt->execute([$limit]);
            $emails = $emailStmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'data' => [
                    'recent_customers' => $customers,
                    'recent_emails' => $emails
                ]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /search/top - Get top customers and emails by various metrics
     */
    public function getTop(string $metric = 'spent', int $limit = 10): array
    {
        try {
            $results = [];

            // Top customers by metric
            $orderBy = match($metric) {
                'spent' => 'total_spent DESC',
                'purchases' => 'purchase_count DESC',
                'recent' => 'created_at DESC',
                default => 'total_spent DESC'
            };

            $stmt = $this->db->prepare("
                SELECT id, customer_id, full_name, email, total_spent, purchase_count, is_vip
                FROM customer_hub_profile
                WHERE is_vip = TRUE OR total_spent > 0
                ORDER BY {$orderBy}
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $results['customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'data' => [
                    'metric' => $metric,
                    'customers' => $results['customers']
                ]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    protected function error(string $message, int $code = 400): array
    {
        return [
            'status' => 'error',
            'error' => $message,
            'code' => $code
        ];
    }
}
