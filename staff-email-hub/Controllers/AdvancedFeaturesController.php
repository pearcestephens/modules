<?php
/**
 * Advanced Features API Controller
 *
 * Handles advanced email features:
 * - Email templates
 * - Send scheduling
 * - Follow-up reminders
 * - Read receipts
 * - Conversation analysis
 * - Priority inbox
 *
 * @package StaffEmailHub\Controllers
 */

namespace StaffEmailHub\Controllers;

use StaffEmailHub\Services\AdvancedEmailFeaturesService;

class AdvancedFeaturesController
{
    private $db;
    private $logger;
    private $staffId;
    private $features;

    public function __construct($db, $logger, $staffId)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->staffId = $staffId;
        $this->features = new AdvancedEmailFeaturesService($db, $logger, $staffId);
    }

    // ============================================================================
    // TEMPLATE ENDPOINTS
    // ============================================================================

    /**
     * POST /api/templates
     * Create email template
     */
    public function createTemplate()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $required = ['name', 'body'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    return $this->errorResponse("$field is required", 400);
                }
            }

            $result = $this->features->createTemplate(
                $data['name'],
                $data['subject'] ?? '',
                $data['body'],
                $data['category'] ?? 'general',
                $data['tags'] ?? []
            );

            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/templates
     * Get all templates
     */
    public function getTemplates()
    {
        try {
            $category = $_GET['category'] ?? null;
            $result = $this->features->getTemplates($category);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ============================================================================
    // SCHEDULING ENDPOINTS
    // ============================================================================

    /**
     * POST /api/schedule/email
     * Schedule email for later
     */
    public function scheduleEmail()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $required = ['to', 'subject', 'body', 'sendAt'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    return $this->errorResponse("$field is required", 400);
                }
            }

            $result = $this->features->scheduleEmail(
                $data['to'],
                $data['subject'],
                $data['body'],
                $data['sendAt'],
                $data['fromProfile'] ?? null
            );

            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/schedule/emails
     * Get scheduled emails
     */
    public function getScheduledEmails()
    {
        try {
            $status = $_GET['status'] ?? 'pending';
            $result = $this->features->getScheduledEmails($status);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/schedule/{scheduleId}
     * Cancel scheduled email
     */
    public function cancelSchedule($scheduleId)
    {
        try {
            $result = $this->features->cancelScheduledEmail($scheduleId);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ============================================================================
    // FOLLOW-UP REMINDERS
    // ============================================================================

    /**
     * POST /api/reminders
     * Add follow-up reminder
     */
    public function addReminder()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $required = ['emailId', 'remindAt'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    return $this->errorResponse("$field is required", 400);
                }
            }

            $result = $this->features->addFollowUpReminder(
                $data['emailId'],
                $data['remindAt'],
                $data['note'] ?? null
            );

            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/reminders
     * Get pending reminders
     */
    public function getPendingReminders()
    {
        try {
            $result = $this->features->getPendingReminders();
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ============================================================================
    // READ RECEIPTS
    // ============================================================================

    /**
     * POST /api/tracking/{emailId}/enable
     * Enable read receipt tracking
     */
    public function enableTracking($emailId)
    {
        try {
            $result = $this->features->enableReadReceipt($emailId);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/tracking/{emailId}/stats
     * Get open statistics
     */
    public function getOpenStats($emailId)
    {
        try {
            $result = $this->features->getOpenStatistics($emailId);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/tracking/record
     * Record email open (tracking pixel endpoint)
     */
    public function recordOpen()
    {
        try {
            $token = $_GET['token'] ?? null;
            if (!$token) {
                return $this->errorResponse('token is required', 400);
            }

            $result = $this->features->recordEmailOpen($token);

            // Return 1x1 tracking pixel
            header('Content-Type: image/gif');
            echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            exit;
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ============================================================================
    // CONVERSATION ANALYSIS
    // ============================================================================

    /**
     * GET /api/conversation/{conversationId}/analysis
     * Analyze conversation
     */
    public function analyzeConversation($conversationId)
    {
        try {
            $result = $this->features->analyzeConversation($conversationId);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ============================================================================
    // PRIORITY INBOX
    // ============================================================================

    /**
     * GET /api/inbox/priority
     * Get priority inbox
     */
    public function getPriorityInbox()
    {
        try {
            $days = $_GET['days'] ?? 7;
            $result = $this->features->getPriorityInbox($days);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/email/{emailId}/flag
     * Flag email as priority
     */
    public function flagEmail($emailId)
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $flag = $data['flag'] ?? true;

            $result = $this->features->flagEmail($emailId, $flag);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ============================================================================
    // RESPONSE HELPERS
    // ============================================================================

    private function jsonResponse($data, $statusCode = 200)
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    private function errorResponse($message, $statusCode = 400)
    {
        return $this->jsonResponse(['success' => false, 'message' => $message], $statusCode);
    }
}
