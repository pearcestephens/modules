<?php
declare(strict_types=1);

namespace StaffEmailHub\Controllers;

use StaffEmailHub\Services\StaffEmailService;
use StaffEmailHub\Services\CustomerHubService;
use StaffEmailHub\Services\SearchService;
use PDO;

/**
 * EmailController - Handle email CRUD, sending, and email-related actions
 *
 * Routes:
 * GET /emails/inbox - List user's inbox
 * GET /emails/{id} - View single email
 * POST /emails - Create draft
 * PUT /emails/{id} - Update draft
 * POST /emails/{id}/send - Send email
 * POST /emails/{id}/assign - Assign to staff
 * POST /emails/{id}/flag-r18 - Flag as R18
 * POST /emails/{id}/note - Add note
 * POST /emails/{id}/delete - Soft delete
 *
 * @version 1.0.0
 */
class EmailController
{
    private StaffEmailService $emailService;
    private CustomerHubService $customerService;
    private SearchService $searchService;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->emailService = new StaffEmailService($db);
        $this->customerService = new CustomerHubService($db);
        $this->searchService = new SearchService($db);
    }

    /**
     * GET /emails/inbox - Get user's inbox with pagination and filtering
     */
    public function getInbox(int $staffId, int $page = 1, int $perPage = 50, array $filters = []): array
    {
        try {
            $result = $this->emailService->getInbox($staffId, $page, $perPage);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => [
                    'emails' => $result['emails'] ?? [],
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $result['total'] ?? count($result['emails'] ?? [])
                ]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /emails/{id} - Get single email with full details
     */
    public function getEmail(int $emailId, int $staffId): array
    {
        try {
            $result = $this->emailService->getEmailById($emailId);

            if (!$result['success']) {
                return $this->error('Email not found');
            }

            $email = $result['email'];

            // Verify access (owns it, assigned to them, or admin)
            if ($email['from_staff_id'] !== $staffId && $email['assigned_to'] !== $staffId) {
                return $this->error('Access denied', 403);
            }

            // Load related customer data if applicable
            if ($email['customer_id']) {
                $custResult = $this->customerService->getCustomerProfile($email['customer_id']);
                if ($custResult['success']) {
                    $email['customer'] = $custResult['customer'];
                }
            }

            return [
                'status' => 'success',
                'data' => $email
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /emails - Create draft email
     */
    public function createDraft(int $staffId, array $data): array
    {
        try {
            // Validate required fields
            $required = ['to_email', 'subject', 'body_plain'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->error("Missing required field: {$field}");
                }
            }

            // Look up customer by email if not provided
            $customerId = $data['customer_id'] ?? null;
            if (!$customerId && $data['to_email']) {
                $custResult = $this->searchService->findByEmail($data['to_email']);
                if ($custResult['success']) {
                    $customerId = $custResult['customer']['id'];
                }
            }

            // Create draft
            $draftData = [
                'to_email' => $data['to_email'],
                'customer_id' => $customerId,
                'subject' => $data['subject'],
                'body_plain' => $data['body_plain'],
                'body_html' => $data['body_html'] ?? null,
                'template_id' => $data['template_id'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'tags' => $data['tags'] ?? null
            ];

            $result = $this->emailService->createDraft($staffId, $draftData);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => [
                    'id' => $result['id'],
                    'trace_id' => $result['trace_id'],
                    'message' => 'Draft created'
                ]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * PUT /emails/{id} - Update draft (only before sending)
     */
    public function updateDraft(int $emailId, int $staffId, array $data): array
    {
        try {
            // Get email to verify ownership and status
            $getResult = $this->emailService->getEmailById($emailId);
            if (!$getResult['success']) {
                return $this->error('Email not found');
            }

            $email = $getResult['email'];

            // Can only edit own drafts
            if ($email['from_staff_id'] !== $staffId) {
                return $this->error('Access denied', 403);
            }

            if ($email['status'] !== 'draft') {
                return $this->error('Only drafts can be edited');
            }

            // Build update query
            $updates = [];
            $values = [];

            $updateableFields = ['subject', 'body_plain', 'body_html', 'to_email', 'priority', 'tags'];
            foreach ($updateableFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "{$field} = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($updates)) {
                return $this->error('No fields to update');
            }

            $values[] = $emailId;

            $sql = "UPDATE staff_emails SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            return [
                'status' => 'success',
                'data' => ['message' => 'Draft updated']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /emails/{id}/send - Send email
     */
    public function sendEmail(int $emailId, int $staffId): array
    {
        try {
            // Get email
            $getResult = $this->emailService->getEmailById($emailId);
            if (!$getResult['success']) {
                return $this->error('Email not found');
            }

            $email = $getResult['email'];

            // Verify ownership
            if ($email['from_staff_id'] !== $staffId) {
                return $this->error('Access denied', 403);
            }

            if (!in_array($email['status'], ['draft', 'scheduled'])) {
                return $this->error('Email already sent or failed');
            }

            // Send email
            $result = $this->emailService->sendEmail($emailId, $staffId);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => [
                    'message' => 'Email sent successfully',
                    'sent_at' => $result['sent_at'] ?? date('Y-m-d H:i:s')
                ]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /emails/{id}/assign - Assign to staff member
     */
    public function assignEmail(int $emailId, int $assignToStaffId, int $currentStaffId): array
    {
        try {
            // Get email
            $getResult = $this->emailService->getEmailById($emailId);
            if (!$getResult['success']) {
                return $this->error('Email not found');
            }

            $result = $this->emailService->assignEmail($emailId, $assignToStaffId);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => ['message' => 'Email assigned']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /emails/{id}/flag-r18 - Flag email as R18 restricted
     */
    public function flagR18(int $emailId, string $reason, int $staffId): array
    {
        try {
            // Get email to verify access
            $getResult = $this->emailService->getEmailById($emailId);
            if (!$getResult['success']) {
                return $this->error('Email not found');
            }

            $result = $this->emailService->flagR18($emailId, $reason);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            // Also flag customer if applicable
            $email = $getResult['email'];
            if ($email['customer_id']) {
                $this->customerService->flagCustomer($email['customer_id'], "R18 email: {$reason}");
            }

            return [
                'status' => 'success',
                'data' => ['message' => 'Email flagged as R18']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /emails/{id}/note - Add note to email
     */
    public function addNote(int $emailId, string $note, int $staffId): array
    {
        try {
            if (empty(trim($note))) {
                return $this->error('Note cannot be empty');
            }

            $result = $this->emailService->addNote($emailId, $note);

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
     * POST /emails/{id}/delete - Soft delete email
     */
    public function deleteEmail(int $emailId, int $staffId): array
    {
        try {
            // Get email
            $getResult = $this->emailService->getEmailById($emailId);
            if (!$getResult['success']) {
                return $this->error('Email not found');
            }

            $email = $getResult['email'];

            // Verify permission
            if ($email['from_staff_id'] !== $staffId && $email['assigned_to'] !== $staffId) {
                return $this->error('Access denied', 403);
            }

            // Soft delete
            $stmt = $this->db->prepare("UPDATE staff_emails SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$emailId]);

            return [
                'status' => 'success',
                'data' => ['message' => 'Email deleted']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /emails/search - Search emails
     */
    public function search(string $query, int $page = 1, int $perPage = 20, array $filters = []): array
    {
        try {
            if (strlen($query) < 2) {
                return $this->error('Search query too short (min 2 characters)');
            }

            $result = $this->searchService->searchEmails($query, $perPage, $filters);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => [
                    'emails' => $result['emails'],
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
     * GET /emails/templates - List email templates
     */
    public function getTemplates(): array
    {
        try {
            $result = $this->emailService->getTemplates();

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => [
                    'templates' => $result['templates']
                ]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /emails/{id}/apply-template - Apply template to email
     */
    public function applyTemplate(int $emailId, int $templateId, array $variables = []): array
    {
        try {
            $result = $this->emailService->applyTemplate($emailId, $templateId, $variables);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => ['message' => 'Template applied']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    // ============ HELPER METHODS ============

    protected function error(string $message, int $code = 400): array
    {
        return [
            'status' => 'error',
            'error' => $message,
            'code' => $code
        ];
    }
}
