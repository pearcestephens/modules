<?php
/**
 * Profile Management API Controller
 *
 * Handles staff profile operations:
 * - Create/update multiple email profiles
 * - Signature management
 * - Access delegation
 * - Role-based permission management
 *
 * @package StaffEmailHub\Controllers
 */

namespace StaffEmailHub\Controllers;

use StaffEmailHub\Services\StaffProfileManager;

class ProfileController
{
    private $db;
    private $logger;
    private $staffId;
    private $manager;

    public function __construct($db, $logger, $staffId)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->staffId = $staffId;
        $this->manager = new StaffProfileManager($db, $logger, $staffId);
    }

    /**
     * GET /api/profiles
     * Get all profiles for current staff
     */
    public function listProfiles()
    {
        try {
            $result = $this->manager->getMyProfiles();
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/profiles
     * Create new profile
     */
    public function createProfile()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $required = ['email', 'displayName'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    return $this->errorResponse("$field is required", 400);
                }
            }

            $result = $this->manager->createProfile(
                $data['email'],
                $data['displayName'],
                $data['signature'] ?? null,
                $data['accountType'] ?? 'standard'
            );

            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/profiles/{profileId}
     * Get profile details with permissions
     */
    public function getProfile($profileId)
    {
        try {
            $result = $this->manager->getProfileWithPermissions($profileId);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/profiles/{profileId}/signature
     * Update profile signature
     */
    public function updateSignature($profileId)
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['signature'])) {
                return $this->errorResponse('signature is required', 400);
            }

            $result = $this->manager->updateSignature($profileId, $data['signature']);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/profiles/{profileId}/default
     * Set as default profile
     */
    public function setDefault($profileId)
    {
        try {
            $result = $this->manager->setDefaultProfile($profileId);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/profiles/{profileId}/delegate
     * Delegate profile access to another staff member
     */
    public function delegateAccess($profileId)
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['staffId'])) {
                return $this->errorResponse('staffId is required', 400);
            }

            $role = $data['role'] ?? StaffProfileManager::ROLE_DELEGATE;

            $result = $this->manager->delegateAccess($profileId, $data['staffId'], $role);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/profiles/{profileId}/access/{staffId}
     * Revoke delegated access
     */
    public function revokeAccess($profileId, $staffId)
    {
        try {
            $result = $this->manager->revokeAccess($profileId, $staffId);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/profiles/{profileId}/access
     * Get access list
     */
    public function getAccessList($profileId)
    {
        try {
            $result = $this->manager->getAccessList($profileId);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/profiles/{profileId}/audit
     * Get audit trail
     */
    public function getAuditTrail($profileId)
    {
        try {
            $limit = $_GET['limit'] ?? 100;
            $result = $this->manager->getAccessAuditTrail($profileId, $limit);
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
