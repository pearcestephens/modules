<?php
/**
 * Legacy Email API Controller
 *
 * Handles all legacy Rackspace email integration endpoints:
 * - Account validation and registration
 * - Email import with date range selection
 * - Folder management
 * - Incremental sync configuration
 * - Migration status tracking
 *
 * @package StaffEmailHub\Controllers
 */

namespace StaffEmailHub\Controllers;

use StaffEmailHub\Services\RackspaceLegacyEmailImporter;

class LegacyEmailController
{
    private $db;
    private $logger;
    private $staffId;
    private $importer;

    public function __construct($db, $logger, $staffId)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->staffId = $staffId;
        $this->importer = new RackspaceLegacyEmailImporter($db, $logger, $staffId, []);
    }

    /**
     * POST /api/legacy-email/validate
     * Validate legacy Rackspace account credentials
     */
    public function validateAccount()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['email']) || !isset($data['password'])) {
                return $this->errorResponse('Email and password required', 400);
            }

            $result = $this->importer->validateLegacyAccount($data['email'], $data['password']);

            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/legacy-email/register
     * Register validated legacy account
     */
    public function registerAccount()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $required = ['email', 'password'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    return $this->errorResponse("$field is required", 400);
                }
            }

            $result = $this->importer->registerLegacyAccount(
                $data['email'],
                $data['password'],
                $data['display_name'] ?? null
            );

            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/legacy-email/import
     * Start email import from legacy account
     */
    public function importEmails()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['account_id'])) {
                return $this->errorResponse('account_id is required', 400);
            }

            $result = $this->importer->importEmailHistory(
                $data['account_id'],
                $data['date_from'] ?? null,
                $data['date_to'] ?? null,
                $data['folder'] ?? 'INBOX',
                $data['limit'] ?? 500
            );

            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/legacy-email/sync/setup
     * Configure incremental sync
     */
    public function setupSync()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['account_id'])) {
                return $this->errorResponse('account_id is required', 400);
            }

            $result = $this->importer->setupIncrementalSync(
                $data['account_id'],
                $data['sync_interval'] ?? 300
            );

            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/legacy-email/status/{account_id}
     * Get migration status
     */
    public function getMigrationStatus($accountId)
    {
        try {
            $result = $this->importer->getMigrationStatus($accountId);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/legacy-email/thread/{email_id}
     * Create conversation thread from legacy email
     */
    public function createThread($emailId)
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $result = $this->importer->createConversationThread(
                $emailId,
                $data['account_id'] ?? null
            );

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
