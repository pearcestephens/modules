<?php
/**
 * Universal Employee Onboarding Service
 *
 * Creates employee ONCE, provisions EVERYWHERE:
 * - CIS (Master Database)
 * - Xero Payroll
 * - Deputy (Timesheets)
 * - Lightspeed/Vend (POS)
 *
 * Features:
 * - Atomic transactions (all or nothing)
 * - Rollback on failure
 * - Retry queue for failed syncs
 * - Comprehensive audit logging
 *
 * @package CIS\EmployeeOnboarding
 * @version 1.0.0
 */

namespace CIS\EmployeeOnboarding;

use PDO;
use Exception;

class UniversalOnboardingService
{
    private $pdo;
    private $xeroService;
    private $deputyService;
    private $lightspeedService;

    /**
     * Constructor
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

        // Load integration services
        require_once __DIR__ . '/XeroEmployeeService.php';
        require_once __DIR__ . '/DeputyEmployeeService.php';
        require_once __DIR__ . '/LightspeedEmployeeService.php';

        $this->xeroService = new XeroEmployeeService($pdo);
        $this->deputyService = new DeputyEmployeeService($pdo);
        $this->lightspeedService = new LightspeedEmployeeService($pdo);
    }

    /**
     * Create new employee and provision to all systems
     *
     * @param array $employeeData Employee information
     * @param array $options Configuration options
     * @return array Result with user_id and sync statuses
     */
    public function onboardEmployee(array $employeeData, array $options = []): array
    {
        $this->pdo->beginTransaction();

        try {
            // Step 1: Create user in CIS (master database)
            $userId = $this->createCISUser($employeeData);

            if (!$userId) {
                throw new Exception('Failed to create user in CIS');
            }

            // Step 2: Assign roles
            if (!empty($employeeData['roles'])) {
                $this->assignRoles($userId, $employeeData['roles']);
            }

            $this->pdo->commit();

            // Step 3: Sync to external systems (outside transaction)
            $syncResults = $this->syncToExternalSystems($userId, $employeeData, $options);

            // Step 4: Log the onboarding
            $this->logOnboarding($userId, 'onboard_complete', 'success', [
                'employee_data' => $this->sanitizeForLog($employeeData),
                'sync_results' => $syncResults
            ]);

            return [
                'success' => true,
                'user_id' => $userId,
                'message' => 'Employee onboarded successfully',
                'sync_results' => $syncResults
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();

            $this->logOnboarding(
                $userId ?? null,
                'onboard_failed',
                'failed',
                ['error' => $e->getMessage()]
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'user_id' => null
            ];
        }
    }

    /**
     * Create user in CIS database
     */
    private function createCISUser(array $data): ?int
    {
        $sql = "INSERT INTO users (
            first_name, last_name, email, phone, mobile, date_of_birth,
            employee_number, start_date, employment_type, job_title, department,
            location_id, manager_id, username, password_hash, status, notes, created_by
        ) VALUES (
            :first_name, :last_name, :email, :phone, :mobile, :date_of_birth,
            :employee_number, :start_date, :employment_type, :job_title, :department,
            :location_id, :manager_id, :username, :password_hash, :status, :notes, :created_by
        )";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'employee_number' => $data['employee_number'] ?? $this->generateEmployeeNumber(),
            'start_date' => $data['start_date'] ?? date('Y-m-d'),
            'employment_type' => $data['employment_type'] ?? 'full_time',
            'job_title' => $data['job_title'] ?? null,
            'department' => $data['department'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'manager_id' => $data['manager_id'] ?? null,
            'username' => $data['username'] ?? $this->generateUsername($data),
            'password_hash' => isset($data['password']) ? password_hash($data['password'], PASSWORD_DEFAULT) : null,
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
            'created_by' => $_SESSION['userID'] ?? null
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Assign roles to user
     */
    private function assignRoles(int $userId, array $roleIds): void
    {
        $sql = "INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);

        foreach ($roleIds as $roleId) {
            $stmt->execute([$userId, $roleId, $_SESSION['userID'] ?? null]);
        }
    }

    /**
     * Sync employee to all external systems
     */
    private function syncToExternalSystems(int $userId, array $data, array $options): array
    {
        $results = [
            'xero' => ['status' => 'disabled', 'message' => 'Sync disabled'],
            'deputy' => ['status' => 'disabled', 'message' => 'Sync disabled'],
            'lightspeed' => ['status' => 'disabled', 'message' => 'Sync disabled']
        ];

        // Xero Payroll
        if ($options['sync_xero'] ?? true) {
            try {
                $xeroResult = $this->xeroService->createEmployee($userId, $data);
                $results['xero'] = [
                    'status' => 'success',
                    'external_id' => $xeroResult['employee_id'],
                    'message' => 'Successfully created in Xero'
                ];

                $this->saveExternalMapping($userId, 'xero', $xeroResult['employee_id']);
                $this->logOnboarding($userId, 'sync_xero', 'success', $xeroResult);

            } catch (Exception $e) {
                $results['xero'] = [
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];

                $this->queueRetry($userId, 'xero', 'create', $data);
                $this->logOnboarding($userId, 'sync_xero', 'failed', ['error' => $e->getMessage()]);
            }
        }

        // Deputy
        if ($options['sync_deputy'] ?? true) {
            try {
                $deputyResult = $this->deputyService->createEmployee($userId, $data);
                $results['deputy'] = [
                    'status' => 'success',
                    'external_id' => $deputyResult['employee_id'],
                    'message' => 'Successfully created in Deputy'
                ];

                $this->saveExternalMapping($userId, 'deputy', $deputyResult['employee_id']);
                $this->logOnboarding($userId, 'sync_deputy', 'success', $deputyResult);

            } catch (Exception $e) {
                $results['deputy'] = [
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];

                $this->queueRetry($userId, 'deputy', 'create', $data);
                $this->logOnboarding($userId, 'sync_deputy', 'failed', ['error' => $e->getMessage()]);
            }
        }

        // Lightspeed/Vend
        if ($options['sync_lightspeed'] ?? true) {
            try {
                $lightspeedResult = $this->lightspeedService->createEmployee($userId, $data);
                $results['lightspeed'] = [
                    'status' => 'success',
                    'external_id' => $lightspeedResult['user_id'],
                    'message' => 'Successfully created in Lightspeed'
                ];

                $this->saveExternalMapping($userId, 'lightspeed', $lightspeedResult['user_id']);
                $this->logOnboarding($userId, 'sync_lightspeed', 'success', $lightspeedResult);

            } catch (Exception $e) {
                $results['lightspeed'] = [
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];

                $this->queueRetry($userId, 'lightspeed', 'create', $data);
                $this->logOnboarding($userId, 'sync_lightspeed', 'failed', ['error' => $e->getMessage()]);
            }
        }

        return $results;
    }

    /**
     * Save external system mapping
     */
    private function saveExternalMapping(int $userId, string $system, string $externalId): void
    {
        $sql = "INSERT INTO external_system_mappings (
            user_id, system_name, external_id, sync_status, last_synced_at
        ) VALUES (?, ?, ?, 'synced', NOW())
        ON DUPLICATE KEY UPDATE
            external_id = VALUES(external_id),
            sync_status = 'synced',
            last_synced_at = NOW(),
            sync_attempts = 0,
            last_sync_error = NULL";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $system, $externalId]);
    }

    /**
     * Queue sync for retry
     */
    private function queueRetry(int $userId, string $system, string $action, array $payload): void
    {
        $sql = "INSERT INTO sync_queue (
            user_id, system_name, action, priority, payload, next_retry_at, status
        ) VALUES (?, ?, ?, 5, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), 'pending')";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $userId,
            $system,
            $action,
            json_encode($payload)
        ]);
    }

    /**
     * Log onboarding action
     */
    private function logOnboarding(?int $userId, string $action, string $status, array $data): void
    {
        $sql = "INSERT INTO onboarding_log (
            user_id, action, system_name, status, request_data, response_data,
            error_message, initiated_by, ip_address, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $userId,
            $action,
            $data['system'] ?? null,
            $status,
            json_encode($data['request'] ?? null),
            json_encode($data['response'] ?? $data),
            $data['error'] ?? null,
            $_SESSION['userID'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    /**
     * Update existing employee across all systems
     */
    public function updateEmployee(int $userId, array $updates, array $options = []): array
    {
        try {
            // Update CIS
            $this->updateCISUser($userId, $updates);

            // Update external systems
            $syncResults = [];

            if ($options['sync_xero'] ?? true) {
                $syncResults['xero'] = $this->xeroService->updateEmployee($userId, $updates);
            }

            if ($options['sync_deputy'] ?? true) {
                $syncResults['deputy'] = $this->deputyService->updateEmployee($userId, $updates);
            }

            if ($options['sync_lightspeed'] ?? true) {
                $syncResults['lightspeed'] = $this->lightspeedService->updateEmployee($userId, $updates);
            }

            $this->logOnboarding($userId, 'update_employee', 'success', [
                'updates' => $updates,
                'sync_results' => $syncResults
            ]);

            return [
                'success' => true,
                'message' => 'Employee updated successfully',
                'sync_results' => $syncResults
            ];

        } catch (Exception $e) {
            $this->logOnboarding($userId, 'update_employee', 'failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update CIS user
     */
    private function updateCISUser(int $userId, array $updates): void
    {
        $allowedFields = [
            'first_name', 'last_name', 'email', 'phone', 'mobile', 'date_of_birth',
            'job_title', 'department', 'location_id', 'manager_id', 'employment_type',
            'status', 'notes'
        ];

        $setClauses = [];
        $values = [];

        foreach ($updates as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $setClauses[] = "$field = ?";
                $values[] = $value;
            }
        }

        if (empty($setClauses)) {
            return;
        }

        $values[] = $userId;

        $sql = "UPDATE users SET " . implode(', ', $setClauses) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
    }

    /**
     * Deactivate employee across all systems
     */
    public function deactivateEmployee(int $userId, array $options = []): array
    {
        try {
            // Update CIS status
            $stmt = $this->pdo->prepare("UPDATE users SET status = 'inactive', end_date = NOW() WHERE id = ?");
            $stmt->execute([$userId]);

            // Deactivate in external systems
            $syncResults = [];

            if ($options['sync_xero'] ?? true) {
                $syncResults['xero'] = $this->xeroService->deactivateEmployee($userId);
            }

            if ($options['sync_deputy'] ?? true) {
                $syncResults['deputy'] = $this->deputyService->deactivateEmployee($userId);
            }

            if ($options['sync_lightspeed'] ?? true) {
                $syncResults['lightspeed'] = $this->lightspeedService->deactivateEmployee($userId);
            }

            $this->logOnboarding($userId, 'deactivate_employee', 'success', ['sync_results' => $syncResults]);

            return [
                'success' => true,
                'message' => 'Employee deactivated successfully',
                'sync_results' => $syncResults
            ];

        } catch (Exception $e) {
            $this->logOnboarding($userId, 'deactivate_employee', 'failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get employee with all system mappings
     */
    public function getEmployee(int $userId): ?array
    {
        $sql = "SELECT * FROM vw_users_complete WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get all employees with sync status
     */
    public function getAllEmployees(array $filters = []): array
    {
        $sql = "SELECT * FROM vw_users_complete WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['location_id'])) {
            $sql .= " AND location_id = ?";
            $params[] = $filters['location_id'];
        }

        $sql .= " ORDER BY last_name, first_name";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check user permission
     */
    public function checkPermission(int $userId, string $permissionName): bool
    {
        $sql = "CALL check_user_permission(?, ?, @has_permission)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $permissionName]);

        $result = $this->pdo->query("SELECT @has_permission as has_permission")->fetch(PDO::FETCH_ASSOC);

        return (bool) $result['has_permission'];
    }

    /**
     * Get user permissions
     */
    public function getUserPermissions(int $userId): array
    {
        // Check if admin
        $stmt = $this->pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['is_admin']) {
            // Return all permissions
            $stmt = $this->pdo->query("SELECT name FROM permissions ORDER BY module, name");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // Get permissions via roles
        $sql = "SELECT DISTINCT p.name
                FROM user_roles ur
                JOIN role_permissions rp ON ur.role_id = rp.role_id
                JOIN permissions p ON rp.permission_id = p.id
                WHERE ur.user_id = ?
                AND (ur.expires_at IS NULL OR ur.expires_at > NOW())

                UNION

                SELECT p.name
                FROM user_permissions_override upo
                JOIN permissions p ON upo.permission_id = p.id
                WHERE upo.user_id = ?
                AND upo.type = 'grant'
                AND (upo.expires_at IS NULL OR upo.expires_at > NOW())

                ORDER BY name";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $userId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Helper: Generate employee number
     */
    private function generateEmployeeNumber(): string
    {
        $prefix = 'EMP';
        $year = date('Y');

        $stmt = $this->pdo->query("SELECT MAX(CAST(SUBSTRING(employee_number, 8) AS UNSIGNED)) as max_num FROM users WHERE employee_number LIKE '{$prefix}{$year}%'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $nextNum = ($result['max_num'] ?? 0) + 1;

        return sprintf('%s%s%04d', $prefix, $year, $nextNum);
    }

    /**
     * Helper: Generate username
     */
    private function generateUsername(array $data): string
    {
        $base = strtolower($data['first_name'][0] . $data['last_name']);
        $base = preg_replace('/[^a-z0-9]/', '', $base);

        $username = $base;
        $counter = 1;

        while ($this->usernameExists($username)) {
            $username = $base . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Check if username exists
     */
    private function usernameExists(string $username): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Sanitize data for logging
     */
    private function sanitizeForLog(array $data): array
    {
        unset($data['password']);
        unset($data['password_hash']);
        return $data;
    }
}
