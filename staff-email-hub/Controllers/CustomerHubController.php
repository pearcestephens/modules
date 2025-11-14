<?php
declare(strict_types=1);

namespace StaffEmailHub\Controllers;

use StaffEmailHub\Services\CustomerHubService;
use StaffEmailHub\Services\SearchService;
use PDO;
use Exception;

/**
 * CustomerHubController - Customer profile, history, notes, flags, VIP status
 *
 * Routes:
 * GET /customers/search - Search customers
 * GET /customers/{id} - Get customer profile
 * PUT /customers/{id} - Update customer profile
 * GET /customers/{id}/emails - Get customer's emails
 * GET /customers/{id}/history - Get purchase history
 * GET /customers/{id}/communications - Get communication log
 * POST /customers/{id}/note - Add note
 * POST /customers/{id}/flag - Flag customer
 * POST /customers/{id}/unflag - Remove flag
 * POST /customers/{id}/vip - Set VIP status
 * POST /customers/{id}/tag - Add tag
 * GET /customers/{id}/id-status - Get ID verification status
 *
 * @version 1.0.0
 */
class CustomerHubController
{
    private CustomerHubService $customerService;
    private SearchService $searchService;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->customerService = new CustomerHubService($db);
        $this->searchService = new SearchService($db);
    }

    /**
     * GET /customers/search - Search customers with filters
     */
    public function search(string $query = '', int $page = 1, int $perPage = 20, array $filters = []): array
    {
        try {
            if (strlen($query) < 1 && empty($filters)) {
                return $this->error('Please provide search query or filters');
            }

            $result = $this->searchService->searchCustomers($query, $perPage, $filters);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => [
                    'customers' => $result['customers'],
                    'count' => $result['count'],
                    'query' => $query,
                    'page' => $page,
                    'per_page' => $perPage
                ]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /customers/{id} - Get complete customer profile with all related data
     */
    public function getProfile(int $customerId): array
    {
        try {
            $result = $this->customerService->getCustomerProfile($customerId);

            if (!$result['success']) {
                return $this->error('Customer not found', 404);
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
     * PUT /customers/{id} - Update customer profile fields
     */
    public function updateProfile(int $customerId, array $data): array
    {
        try {
            // Get current profile
            $getResult = $this->customerService->getCustomerProfile($customerId);
            if (!$getResult['success']) {
                return $this->error('Customer not found', 404);
            }

            // Only allow certain fields to be updated
            $updateable = [
                'full_name', 'email', 'phone', 'date_of_birth', 'address',
                'suburb', 'postcode', 'preferred_contact', 'communication_preference'
            ];

            $updates = [];
            $values = [];

            foreach ($updateable as $field) {
                if (isset($data[$field])) {
                    $updates[] = "{$field} = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($updates)) {
                return $this->error('No valid fields to update');
            }

            $values[] = $customerId;

            $sql = "UPDATE customer_hub_profile SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            return [
                'status' => 'success',
                'data' => ['message' => 'Customer profile updated']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /customers/{id}/emails - Get all emails for customer
     */
    public function getEmails(int $customerId, int $limit = 50): array
    {
        try {
            $result = $this->customerService->getCustomerEmails($customerId, $limit);

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
     * GET /customers/{id}/history - Get purchase history
     */
    public function getPurchaseHistory(int $customerId, int $limit = 100): array
    {
        try {
            $result = $this->customerService->getPurchaseHistory($customerId, $limit);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            // Calculate aggregate stats
            $purchases = $result['purchases'] ?? [];
            $totalSpent = array_sum(array_column($purchases, 'total_amount'));
            $averageOrder = count($purchases) > 0 ? $totalSpent / count($purchases) : 0;

            return [
                'status' => 'success',
                'data' => [
                    'purchases' => $purchases,
                    'count' => count($purchases),
                    'total_spent' => round($totalSpent, 2),
                    'average_order' => round($averageOrder, 2),
                    'frequency' => $this->calculatePurchaseFrequency($purchases)
                ]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /customers/{id}/communications - Get full communication log
     */
    public function getCommunications(int $customerId, int $limit = 100): array
    {
        try {
            $result = $this->customerService->getCommunicationLog($customerId, $limit);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            // Group by type for dashboard
            $byType = [];
            foreach ($result['communications'] ?? [] as $comm) {
                $type = $comm['communication_type'] ?? 'unknown';
                if (!isset($byType[$type])) {
                    $byType[$type] = [];
                }
                $byType[$type][] = $comm;
            }

            return [
                'status' => 'success',
                'data' => [
                    'communications' => $result['communications'],
                    'by_type' => $byType,
                    'count' => count($result['communications'] ?? [])
                ]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /customers/{id}/note - Add note to customer
     */
    public function addNote(int $customerId, string $note, int $staffId): array
    {
        try {
            if (empty(trim($note))) {
                return $this->error('Note cannot be empty');
            }

            $result = $this->customerService->addNote($customerId, $note, $staffId);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => ['message' => 'Note added']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /customers/{id}/flag - Flag customer with reason
     */
    public function flagCustomer(int $customerId, string $reason): array
    {
        try {
            if (empty(trim($reason))) {
                return $this->error('Reason is required');
            }

            $result = $this->customerService->flagCustomer($customerId, $reason);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => ['message' => 'Customer flagged']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /customers/{id}/unflag - Remove flag
     */
    public function unflagCustomer(int $customerId): array
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE customer_hub_profile
                SET is_flagged = FALSE, flag_reason = NULL
                WHERE id = ?
            ");
            $stmt->execute([$customerId]);

            return [
                'status' => 'success',
                'data' => ['message' => 'Customer flag removed']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /customers/{id}/vip - Set VIP status
     */
    public function setVIP(int $customerId, bool $isVip): array
    {
        try {
            $result = $this->customerService->setVIP($customerId, $isVip);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            $message = $isVip ? 'Customer marked as VIP' : 'VIP status removed';

            return [
                'status' => 'success',
                'data' => ['message' => $message]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /customers/{id}/tag - Add tag to customer
     */
    public function addTag(int $customerId, string $tag): array
    {
        try {
            if (empty(trim($tag))) {
                return $this->error('Tag cannot be empty');
            }

            // Validate tag format (alphanumeric + dash/underscore)
            if (!preg_match('/^[a-zA-Z0-9_-]{2,20}$/', trim($tag))) {
                return $this->error('Invalid tag format');
            }

            $result = $this->customerService->addTag($customerId, trim($tag));

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => ['message' => 'Tag added']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /customers/{id}/record-communication - Log a communication event
     */
    public function recordCommunication(int $customerId, array $data, int $staffId): array
    {
        try {
            // Validate required fields
            if (!isset($data['communication_type']) || !isset($data['direction'])) {
                return $this->error('Missing required fields: communication_type, direction');
            }

            $commData = [
                'communication_type' => $data['communication_type'],
                'direction' => $data['direction'],
                'subject' => $data['subject'] ?? null,
                'summary' => $data['summary'] ?? null,
                'staff_id' => $staffId,
                'email_id' => $data['email_id'] ?? null,
                'tags' => $data['tags'] ?? null
            ];

            $result = $this->customerService->recordCommunication($customerId, $commData);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => ['message' => 'Communication recorded']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /customers/{id}/id-status - Get ID verification status
     */
    public function getIdStatus(int $customerId): array
    {
        try {
            $result = $this->customerService->getIdVerificationStatus($customerId);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => $result['status']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /customers - List all customers (paginated)
     */
    public function listAll(int $page = 1, int $perPage = 50, array $filters = []): array
    {
        try {
            $offset = ($page - 1) * $perPage;

            $sql = "SELECT id, customer_id, full_name, email, phone, is_vip, is_flagged,
                   purchase_count, total_spent, id_verified, created_at
                   FROM customer_hub_profile";

            // Apply filters
            $where = [];
            $params = [];

            if (isset($filters['vip_only']) && $filters['vip_only']) {
                $where[] = "is_vip = TRUE";
            }

            if (isset($filters['flagged_only']) && $filters['flagged_only']) {
                $where[] = "is_flagged = TRUE";
            }

            if (isset($filters['id_verified']) && $filters['id_verified']) {
                $where[] = "id_verified = TRUE";
            }

            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }

            // Count total
            $countStmt = $this->db->prepare("SELECT COUNT(*) as count FROM customer_hub_profile" .
                (!empty($where) ? " WHERE " . implode(" AND ", $where) : ""));
            $countStmt->execute($params);
            $total = $countStmt->fetch(\PDO::FETCH_ASSOC)['count'];

            // Get paginated results
            $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $perPage;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $customers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'data' => [
                    'customers' => $customers,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'pages' => ceil($total / $perPage)
                ]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /customers/search/facets - Get search facets for filtering
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
                'data' => $result
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    // ============ PRIVATE HELPERS ============

    /**
     * Calculate purchase frequency
     */
    private function calculatePurchaseFrequency(array $purchases): string
    {
        if (empty($purchases)) {
            return 'never';
        }

        if (count($purchases) === 1) {
            return 'one-time';
        }

        // Check dates
        $first = new \DateTime($purchases[count($purchases) - 1]['sale_date']);
        $last = new \DateTime($purchases[0]['sale_date']);
        $days = $last->diff($first)->days;

        if ($days === 0) {
            return 'multiple-same-day';
        }

        $frequency = $days / (count($purchases) - 1);

        if ($frequency < 30) return 'very-frequent';
        if ($frequency < 90) return 'frequent';
        if ($frequency < 180) return 'regular';
        return 'occasional';
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
