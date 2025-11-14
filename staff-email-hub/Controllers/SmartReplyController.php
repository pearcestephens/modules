<?php
/**
 * Smart Reply API Controller
 *
 * Handles smart reply endpoints:
 * - Generate contextual reply suggestions
 * - Quality scoring and feedback
 * - Usage tracking and analytics
 *
 * @package StaffEmailHub\Controllers
 */

namespace StaffEmailHub\Controllers;

use StaffEmailHub\Services\SmartReplyService;
use StaffEmailHub\Services\SearchService;

class SmartReplyController
{
    private $db;
    private $logger;
    private $staffId;
    private $smartReply;

    public function __construct($db, $logger, $staffId)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->staffId = $staffId;
        $searchService = new SearchService($db, $logger, $staffId);
        $this->smartReply = new SmartReplyService($db, $logger, $staffId, $searchService);
    }

    /**
     * POST /api/smart-reply/generate
     * Generate reply suggestions
     */
    public function generateSuggestions()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['emailId'])) {
                return $this->errorResponse('emailId is required', 400);
            }

            $count = $data['count'] ?? 5;

            $result = $this->smartReply->generateReplySuggestions($data['emailId'], $count);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/smart-reply/{emailId}
     * Get suggestions for email
     */
    public function getSuggestions($emailId)
    {
        try {
            $limit = $_GET['limit'] ?? 5;
            $result = $this->smartReply->getSuggestions($emailId, $limit);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/smart-reply/{suggestionId}/use
     * Use suggestion as reply
     */
    public function useSuggestion($suggestionId)
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $customization = $data['customization'] ?? null;

            $result = $this->smartReply->useSuggestion($suggestionId, $customization);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/smart-reply/{suggestionId}/feedback
     * Record feedback on suggestion
     */
    public function recordFeedback($suggestionId)
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['helpful'])) {
                return $this->errorResponse('helpful is required', 400);
            }

            $result = $this->smartReply->recordFeedback(
                $suggestionId,
                $data['helpful'],
                $data['notes'] ?? null
            );
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/smart-reply/analytics/effectiveness
     * Get effectiveness metrics
     */
    public function getMetrics()
    {
        try {
            $result = $this->smartReply->getEffectivenessMetrics();
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/smart-reply/analytics/top
     * Get top performing suggestions
     */
    public function getTopSuggestions()
    {
        try {
            $limit = $_GET['limit'] ?? 10;
            $result = $this->smartReply->getTopSuggestions($limit);
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
