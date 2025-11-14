<?php
/**
 * Staff Profile Manager Service
 *
 * Manages multiple email profiles per staff member with role-based access,
 * signature management, and delegation workflow. Enables staff to switch
 * between email accounts, maintain separate conversations, and delegate
 * access to other team members.
 *
 * Features:
 * - Multiple email accounts per staff member
 * - Role-based access control (owner, admin, delegate, read-only)
 * - Custom signatures per profile
 * - Email delegation workflow
 * - Profile switching and context management
 * - Audit trail for all profile changes
 *
 * @package StaffEmailHub\Services
 */

namespace StaffEmailHub\Services;

class StaffProfileManager
{
    private $db;
    private $logger;
    private $currentStaffId;

    const ROLE_OWNER = 'owner';
    const ROLE_ADMIN = 'admin';
    const ROLE_DELEGATE = 'delegate';
    const ROLE_READONLY = 'read_only';

    const VALID_ROLES = ['owner', 'admin', 'delegate', 'read_only'];

    public function __construct($db, $logger, $currentStaffId)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->currentStaffId = $currentStaffId;
    }

    /**
     * Create a new profile for staff member
     */
    public function createProfile($email, $displayName, $signature = null, $accountType = 'standard')
    {
        try {
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email format'];
            }

            // Check if email already exists
            $existingStmt = $this->db->prepare("
                SELECT id FROM staff_email_accounts
                WHERE email = ? AND staff_id = ?
            ");
            $existingStmt->execute([$email, $this->currentStaffId]);

            if ($existingStmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Email already registered for this staff member'];
            }

            // Create profile
            $stmt = $this->db->prepare("
                INSERT INTO staff_email_accounts
                (staff_id, email, display_name, account_type, custom_signature, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([$this->currentStaffId, $email, $displayName, $accountType, $signature]);
            $profileId = $this->db->lastInsertId();

            // Create ownership record
            $this->createAccessRecord($this->currentStaffId, $profileId, self::ROLE_OWNER);

            $this->logger->info('Staff profile created', [
                'staff_id' => $this->currentStaffId,
                'profile_id' => $profileId,
                'email' => $email
            ]);

            return [
                'success' => true,
                'profile_id' => $profileId,
                'email' => $email
            ];
        } catch (\Exception $e) {
            $this->logger->error('Profile creation failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get all profiles for current staff member
     */
    public function getMyProfiles()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    sae.id,
                    sae.email,
                    sae.display_name,
                    sae.account_type,
                    sae.custom_signature,
                    sae.is_default,
                    sae.is_active,
                    sae.sync_status,
                    sae.created_at,
                    COUNT(DISTINCT e.id) as email_count,
                    MAX(e.received_at) as last_email
                FROM staff_email_accounts sae
                LEFT JOIN emails e ON sae.id = e.account_id
                WHERE sae.staff_id = ?
                GROUP BY sae.id
                ORDER BY sae.is_default DESC, sae.created_at ASC
            ");

            $stmt->execute([$this->currentStaffId]);
            return ['success' => true, 'profiles' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get profile details with access permissions
     */
    public function getProfileWithPermissions($profileId)
    {
        try {
            // Get profile info
            $profileStmt = $this->db->prepare("
                SELECT * FROM staff_email_accounts WHERE id = ?
            ");
            $profileStmt->execute([$profileId]);
            $profile = $profileStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$profile) {
                return ['success' => false, 'message' => 'Profile not found'];
            }

            // Check if current user has access
            $accessStmt = $this->db->prepare("
                SELECT role FROM staff_profile_access
                WHERE profile_id = ? AND staff_id = ?
            ");
            $accessStmt->execute([$profileId, $this->currentStaffId]);
            $access = $accessStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$access) {
                return ['success' => false, 'message' => 'Access denied'];
            }

            // Get delegated users
            $delegatesStmt = $this->db->prepare("
                SELECT spa.staff_id, spa.role, spa.granted_at, s.name, s.email
                FROM staff_profile_access spa
                LEFT JOIN staff_accounts s ON spa.staff_id = s.id
                WHERE spa.profile_id = ?
                ORDER BY spa.granted_at DESC
            ");
            $delegatesStmt->execute([$profileId]);
            $delegates = $delegatesStmt->fetchAll(\PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'profile' => $profile,
                'my_role' => $access['role'],
                'delegates' => $delegates
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Delegate profile access to another staff member
     */
    public function delegateAccess($profileId, $delegateStaffId, $role = self::ROLE_DELEGATE)
    {
        try {
            // Verify role is valid
            if (!in_array($role, self::VALID_ROLES)) {
                return ['success' => false, 'message' => 'Invalid role'];
            }

            // Verify delegator has permission
            $myAccessStmt = $this->db->prepare("
                SELECT role FROM staff_profile_access
                WHERE profile_id = ? AND staff_id = ?
            ");
            $myAccessStmt->execute([$profileId, $this->currentStaffId]);
            $myAccess = $myAccessStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$myAccess || !in_array($myAccess['role'], [self::ROLE_OWNER, self::ROLE_ADMIN])) {
                return ['success' => false, 'message' => 'Permission denied'];
            }

            // Check if delegate already has access
            $existingStmt = $this->db->prepare("
                SELECT id FROM staff_profile_access
                WHERE profile_id = ? AND staff_id = ?
            ");
            $existingStmt->execute([$profileId, $delegateStaffId]);

            if ($existingStmt->rowCount() > 0) {
                // Update existing access
                $updateStmt = $this->db->prepare("
                    UPDATE staff_profile_access
                    SET role = ?, updated_at = NOW()
                    WHERE profile_id = ? AND staff_id = ?
                ");
                $updateStmt->execute([$role, $profileId, $delegateStaffId]);
            } else {
                // Create new access record
                $this->createAccessRecord($delegateStaffId, $profileId, $role);
            }

            $this->logger->info('Profile access delegated', [
                'profile_id' => $profileId,
                'delegated_by' => $this->currentStaffId,
                'delegated_to' => $delegateStaffId,
                'role' => $role
            ]);

            return ['success' => true, 'message' => 'Access delegated successfully'];
        } catch (\Exception $e) {
            $this->logger->error('Delegation failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Revoke delegated access
     */
    public function revokeAccess($profileId, $delegateStaffId)
    {
        try {
            // Verify delegator has permission
            $myAccessStmt = $this->db->prepare("
                SELECT role FROM staff_profile_access
                WHERE profile_id = ? AND staff_id = ?
            ");
            $myAccessStmt->execute([$profileId, $this->currentStaffId]);
            $myAccess = $myAccessStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$myAccess || !in_array($myAccess['role'], [self::ROLE_OWNER, self::ROLE_ADMIN])) {
                return ['success' => false, 'message' => 'Permission denied'];
            }

            // Prevent revoking owner's access
            if ($delegateStaffId === $this->currentStaffId && $myAccess['role'] === self::ROLE_OWNER) {
                return ['success' => false, 'message' => 'Cannot revoke owner access'];
            }

            $stmt = $this->db->prepare("
                DELETE FROM staff_profile_access
                WHERE profile_id = ? AND staff_id = ?
            ");
            $stmt->execute([$profileId, $delegateStaffId]);

            $this->logger->info('Profile access revoked', [
                'profile_id' => $profileId,
                'revoked_by' => $this->currentStaffId,
                'revoked_from' => $delegateStaffId
            ]);

            return ['success' => true, 'message' => 'Access revoked'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Update profile signature
     */
    public function updateSignature($profileId, $signature)
    {
        try {
            // Check access
            if (!$this->canAccessProfile($profileId, [self::ROLE_OWNER, self::ROLE_ADMIN])) {
                return ['success' => false, 'message' => 'Permission denied'];
            }

            // Validate signature length
            if (strlen($signature) > 2000) {
                return ['success' => false, 'message' => 'Signature too long (max 2000 chars)'];
            }

            $stmt = $this->db->prepare("
                UPDATE staff_email_accounts
                SET custom_signature = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$signature, $profileId]);

            $this->logger->info('Signature updated', [
                'profile_id' => $profileId,
                'updated_by' => $this->currentStaffId
            ]);

            return ['success' => true, 'message' => 'Signature updated'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Set default profile for sending emails
     */
    public function setDefaultProfile($profileId)
    {
        try {
            // Check access
            if (!$this->canAccessProfile($profileId, [self::ROLE_OWNER, self::ROLE_ADMIN])) {
                return ['success' => false, 'message' => 'Permission denied'];
            }

            // Clear previous default
            $clearStmt = $this->db->prepare("
                UPDATE staff_email_accounts
                SET is_default = 0
                WHERE staff_id = ?
            ");
            $clearStmt->execute([$this->currentStaffId]);

            // Set new default
            $stmt = $this->db->prepare("
                UPDATE staff_email_accounts
                SET is_default = 1, updated_at = NOW()
                WHERE id = ? AND staff_id = ?
            ");
            $stmt->execute([$profileId, $this->currentStaffId]);

            $this->logger->info('Default profile changed', [
                'staff_id' => $this->currentStaffId,
                'profile_id' => $profileId
            ]);

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get audit trail for profile access changes
     */
    public function getAccessAuditTrail($profileId, $limit = 100)
    {
        try {
            if (!$this->canAccessProfile($profileId, [self::ROLE_OWNER, self::ROLE_ADMIN])) {
                return ['success' => false, 'message' => 'Permission denied'];
            }

            $stmt = $this->db->prepare("
                SELECT
                    spa.id,
                    spa.staff_id,
                    spa.role,
                    spa.granted_at,
                    spa.updated_at,
                    s.name as staff_name,
                    s.email as staff_email
                FROM staff_profile_access spa
                LEFT JOIN staff_accounts s ON spa.staff_id = s.id
                WHERE spa.profile_id = ?
                ORDER BY spa.updated_at DESC
                LIMIT ?
            ");
            $stmt->execute([$profileId, $limit]);

            return [
                'success' => true,
                'audit_trail' => $stmt->fetchAll(\PDO::FETCH_ASSOC)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Check if current staff can access profile with required role
     */
    private function canAccessProfile($profileId, $requiredRoles = [])
    {
        try {
            $stmt = $this->db->prepare("
                SELECT role FROM staff_profile_access
                WHERE profile_id = ? AND staff_id = ?
            ");
            $stmt->execute([$profileId, $this->currentStaffId]);
            $access = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$access) {
                return false;
            }

            if (empty($requiredRoles)) {
                return true;
            }

            return in_array($access['role'], $requiredRoles);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Private: Create access record
     */
    private function createAccessRecord($staffId, $profileId, $role)
    {
        $stmt = $this->db->prepare("
            INSERT INTO staff_profile_access
            (staff_id, profile_id, role, granted_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$staffId, $profileId, $role]);
    }

    /**
     * Get all staff who can access this profile
     */
    public function getAccessList($profileId)
    {
        try {
            if (!$this->canAccessProfile($profileId, [self::ROLE_OWNER, self::ROLE_ADMIN])) {
                return ['success' => false, 'message' => 'Permission denied'];
            }

            $stmt = $this->db->prepare("
                SELECT
                    spa.staff_id,
                    spa.role,
                    spa.granted_at,
                    s.name,
                    s.email
                FROM staff_profile_access spa
                LEFT JOIN staff_accounts s ON spa.staff_id = s.id
                WHERE spa.profile_id = ?
                ORDER BY spa.role ASC, s.name ASC
            ");
            $stmt->execute([$profileId]);

            return ['success' => true, 'access_list' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
