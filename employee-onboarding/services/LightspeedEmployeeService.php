<?php
/**
 * Lightspeed Employee Service
 *
 * Manages employee/user creation in Lightspeed/Vend POS
 *
 * @package CIS\EmployeeOnboarding
 */

namespace CIS\EmployeeOnboarding;

use PDO;
use Exception;

class LightspeedEmployeeService
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

        // Load Vend/Lightspeed functions if available
        $vendFile = $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/vend-functions.php';
        if (file_exists($vendFile)) {
            require_once $vendFile;
        }
    }

    /**
     * Create user in Lightspeed
     */
    public function createEmployee(int $userId, array $data): array
    {
        if (!function_exists('getVendAPI')) {
            throw new Exception('Vend/Lightspeed API functions not available');
        }

        $vend = getVendAPI();

        // Build user payload
        $user = [
            'username' => $data['username'] ?? $this->generateUsername($data),
            'display_name' => $data['first_name'] . ' ' . $data['last_name'],
            'email' => $data['email'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'account_type' => 'cashier', // or 'manager', 'admin'
            'target_daily' => 0,
            'target_weekly' => 0,
            'target_monthly' => 0,
            'primary_outlet_id' => $this->getVendOutletId($data['location_id'] ?? null)
        ];

        // Set user type based on role
        if (!empty($data['roles'])) {
            $user['account_type'] = $this->mapRoleToVendAccountType($data['roles']);
        }

        try {
            $response = $vend->createUser($user);

            if ($response && isset($response['users'][0]['id'])) {
                $vendUserId = $response['users'][0]['id'];

                return [
                    'success' => true,
                    'user_id' => $vendUserId,
                    'response' => $response
                ];
            }

            throw new Exception('Vend API returned no user ID');

        } catch (Exception $e) {
            throw new Exception('Vend API error: ' . $e->getMessage());
        }
    }

    /**
     * Update user in Lightspeed
     */
    public function updateEmployee(int $userId, array $updates): array
    {
        $vendId = $this->getVendUserId($userId);

        if (!$vendId) {
            throw new Exception('Employee not linked to Lightspeed');
        }

        $vend = getVendAPI();

        // Build update payload
        $user = ['id' => $vendId];

        if (isset($updates['first_name']) || isset($updates['last_name'])) {
            $currentData = $this->getCurrentUserData($userId);
            $user['display_name'] = ($updates['first_name'] ?? $currentData['first_name']) . ' ' .
                                   ($updates['last_name'] ?? $currentData['last_name']);
        }

        $fieldMap = [
            'email' => 'email',
            'first_name' => 'first_name',
            'last_name' => 'last_name'
        ];

        foreach ($fieldMap as $cisField => $vendField) {
            if (isset($updates[$cisField])) {
                $user[$vendField] = $updates[$cisField];
            }
        }

        try {
            $response = $vend->updateUser($vendId, $user);

            return [
                'success' => true,
                'response' => $response
            ];

        } catch (Exception $e) {
            throw new Exception('Vend update error: ' . $e->getMessage());
        }
    }

    /**
     * Deactivate user in Lightspeed
     */
    public function deactivateEmployee(int $userId): array
    {
        $vendId = $this->getVendUserId($userId);

        if (!$vendId) {
            return ['success' => true, 'message' => 'Not linked to Lightspeed'];
        }

        $vend = getVendAPI();

        try {
            // Vend doesn't have a "deactivate" - typically you delete the user
            // or set them to inactive status if supported
            $response = $vend->deleteUser($vendId);

            return [
                'success' => true,
                'response' => $response
            ];

        } catch (Exception $e) {
            throw new Exception('Vend deactivation error: ' . $e->getMessage());
        }
    }

    /**
     * Get Vend user ID from mapping
     */
    private function getVendUserId(int $userId): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT external_id
            FROM external_system_mappings
            WHERE user_id = ? AND system_name IN ('lightspeed', 'vend')
        ");
        $stmt->execute([$userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['external_id'] ?? null;
    }

    /**
     * Get current user data
     */
    private function getCurrentUserData(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
        $stmt->execute([$userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Map CIS location to Vend outlet ID
     */
    private function getVendOutletId(?int $cisLocationId): ?string
    {
        if (!$cisLocationId) {
            return null;
        }

        // This should be a mapping table
        // For now, query vend_outlets or similar
        $stmt = $this->pdo->prepare("
            SELECT vend_outlet_id
            FROM locations
            WHERE id = ?
        ");
        $stmt->execute([$cisLocationId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['vend_outlet_id'] ?? null;
    }

    /**
     * Map CIS roles to Vend account type
     */
    private function mapRoleToVendAccountType(array $roleIds): string
    {
        // Get role names
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->pdo->prepare("SELECT name FROM roles WHERE id IN ($placeholders)");
        $stmt->execute($roleIds);
        $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Map to Vend types
        if (in_array('director', $roles) || in_array('it_admin', $roles)) {
            return 'admin';
        }

        if (in_array('store_manager', $roles) || in_array('retail_ops_manager', $roles)) {
            return 'manager';
        }

        return 'cashier';
    }

    /**
     * Generate Vend username
     */
    private function generateUsername(array $data): string
    {
        return strtolower($data['first_name'][0] . $data['last_name']);
    }
}
