<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Middleware;

/**
 * Payroll Authentication Middleware
 *
 * Role-based access control for payroll operations
 *
 * Roles hierarchy:
 * - super_admin: Full access to everything
 * - payroll_admin: Full payroll access (view all, edit all)
 * - payroll_manager: View all, edit own team
 * - store_manager: View own store team
 * - staff: View own payslips only
 *
 * @package HumanResources\Payroll\Middleware
 * @version 1.0.0
 */
class PayrollAuthMiddleware
{
    /** Admin roles with full access */
    private const ADMIN_ROLES = ['super_admin', 'payroll_admin'];

    /** Manager roles with team access */
    private const MANAGER_ROLES = ['payroll_manager', 'store_manager'];

    /** Staff role with self-access only */
    private const STAFF_ROLE = 'staff';

    private array $user;

    public function __construct(array $user)
    {
        $this->user = $user;
    }

    /**
     * Check if user can perform action
     *
     * @param string $action Action name (view_own, view_team, view_all, edit_own, edit_team, edit_all, admin)
     * @param int|null $targetUserId Target user ID (for view_own, edit_own checks)
     * @param int|null $targetOutletId Target outlet ID (for store manager checks)
     * @return bool True if authorized
     */
    public function can(string $action, ?int $targetUserId = null, ?int $targetOutletId = null): bool
    {
        $role = $this->user['role'] ?? self::STAFF_ROLE;
        $userId = $this->user['id'] ?? 0;
        $userOutletId = $this->user['outlet_id'] ?? null;

        // Super admin and payroll admin have full access
        if (in_array($role, self::ADMIN_ROLES)) {
            return true;
        }

        // Manager role logic
        if (in_array($role, self::MANAGER_ROLES)) {
            return match($action) {
                'view_all', 'admin' => false,
                'edit_all' => false,
                'view_own' => $targetUserId === $userId,
                'edit_own' => $targetUserId === $userId,
                'view_team' => $this->isInTeam($targetUserId, $targetOutletId, $userOutletId, $role),
                'edit_team' => $role === 'payroll_manager' && $this->isInTeam($targetUserId, $targetOutletId, $userOutletId, $role),
                default => false
            };
        }

        // Staff can only view their own
        return match($action) {
            'view_own' => $targetUserId === $userId,
            default => false
        };
    }

    /**
     * Check if target is in user's team
     */
    private function isInTeam(?int $targetUserId, ?int $targetOutletId, ?int $userOutletId, string $role): bool
    {
        // Payroll managers can see all employees
        if ($role === 'payroll_manager') {
            return true;
        }

        // Store managers can only see their outlet
        if ($role === 'store_manager' && $targetOutletId !== null && $userOutletId !== null) {
            return $targetOutletId === $userOutletId;
        }

        return false;
    }

    /**
     * Require admin access (throws exception if not authorized)
     *
     * @throws \RuntimeException If not authorized
     */
    public function requireAdmin(): void
    {
        if (!$this->can('admin')) {
            throw new \RuntimeException('Admin access required', 403);
        }
    }

    /**
     * Require ability to view target user
     *
     * @throws \RuntimeException If not authorized
     */
    public function requireCanView(int $targetUserId, ?int $targetOutletId = null): void
    {
        if (!$this->canViewUser($targetUserId, $targetOutletId)) {
            throw new \RuntimeException('Not authorized to view this user', 403);
        }
    }

    /**
     * Require ability to edit target user
     *
     * @throws \RuntimeException If not authorized
     */
    public function requireCanEdit(int $targetUserId, ?int $targetOutletId = null): void
    {
        if (!$this->canEditUser($targetUserId, $targetOutletId)) {
            throw new \RuntimeException('Not authorized to edit this user', 403);
        }
    }

    /**
     * Check if user can view target user
     */
    public function canViewUser(int $targetUserId, ?int $targetOutletId = null): bool
    {
        return $this->can('view_all')
            || $this->can('view_own', $targetUserId)
            || $this->can('view_team', $targetUserId, $targetOutletId);
    }

    /**
     * Check if user can edit target user
     */
    public function canEditUser(int $targetUserId, ?int $targetOutletId = null): bool
    {
        return $this->can('edit_all')
            || $this->can('edit_own', $targetUserId)
            || $this->can('edit_team', $targetUserId, $targetOutletId);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return in_array($this->user['role'] ?? '', self::ADMIN_ROLES);
    }

    /**
     * Check if user is manager
     */
    public function isManager(): bool
    {
        return in_array($this->user['role'] ?? '', self::MANAGER_ROLES);
    }

    /**
     * Get user role
     */
    public function getRole(): string
    {
        return $this->user['role'] ?? self::STAFF_ROLE;
    }

    /**
     * Get user ID
     */
    public function getUserId(): int
    {
        return $this->user['id'] ?? 0;
    }
}
