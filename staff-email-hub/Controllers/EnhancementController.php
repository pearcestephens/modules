<?php
/**
 * AI Enhancement API Controller
 *
 * Handles message enhancement requests:
 * - Rewrite messages with tone adjustment
 * - Grammar and clarity checking
 * - Multiple tone variations
 * - Enhancement approval workflow
 *
 * @package StaffEmailHub\Controllers
 */

namespace StaffEmailHub\Controllers;

use StaffEmailHub\Services\MessageEnhancementService;

class EnhancementController
{
    private $db;
    private $logger;
    private $staffId;
    private $enhancer;

    public function __construct($db, $logger, $staffId)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->staffId = $staffId;
        $this->enhancer = new MessageEnhancementService($db, $logger, $staffId);
    }

    /**
     * POST /api/enhance/message
     * Enhance message with AI
     */
    public function enhanceMessage()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['message'])) {
                return $this->errorResponse('message is required', 400);
            }

            $tone = $data['tone'] ?? MessageEnhancementService::TONE_PROFESSIONAL;
            $lengthAdjustment = $data['lengthAdjustment'] ?? null;
            $context = $data['context'] ?? [];

            $result = $this->enhancer->enhanceMessage(
                $data['message'],
                $tone,
                $lengthAdjustment,
                $context
            );

            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/enhance/variations
     * Generate multiple tone variations
     */
    public function generateVariations()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['message'])) {
                return $this->errorResponse('message is required', 400);
            }

            $context = $data['context'] ?? [];

            $result = $this->enhancer->generateToneVariations($data['message'], $context);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/enhance/grammar-check
     * Check grammar and clarity
     */
    public function checkGrammar()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['message'])) {
                return $this->errorResponse('message is required', 400);
            }

            $result = $this->enhancer->checkGrammarAndClarity($data['message']);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/enhance/save
     * Store enhancement for approval
     */
    public function saveEnhancement()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $required = ['emailId', 'originalMessage', 'enhancedMessage', 'tone'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    return $this->errorResponse("$field is required", 400);
                }
            }

            $result = $this->enhancer->storeEnhancementForApproval(
                $data['emailId'],
                $data['originalMessage'],
                $data['enhancedMessage'],
                $data['tone'],
                $data['metadata'] ?? []
            );

            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/enhance/{enhancementId}/approve
     * Approve enhancement
     */
    public function approveEnhancement($enhancementId)
    {
        try {
            $result = $this->enhancer->approveEnhancement($enhancementId);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/enhance/{enhancementId}/reject
     * Reject enhancement
     */
    public function rejectEnhancement($enhancementId)
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $reason = $data['reason'] ?? null;

            $result = $this->enhancer->rejectEnhancement($enhancementId, $reason);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/enhance/pending
     * Get pending enhancements
     */
    public function getPending()
    {
        try {
            $limit = $_GET['limit'] ?? 20;
            $result = $this->enhancer->getPendingEnhancements($limit);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/enhance/history
     * Get enhancement history
     */
    public function getHistory()
    {
        try {
            $emailId = $_GET['emailId'] ?? null;
            $limit = $_GET['limit'] ?? 50;
            $result = $this->enhancer->getEnhancementHistory($emailId, $limit);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // Response helpers
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
