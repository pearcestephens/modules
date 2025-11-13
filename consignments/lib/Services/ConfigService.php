<?php
/**
 * ConfigService - Configuration and reference data operations
 *
 * Handles configuration lookups for transfer management:
 * - Outlets (stores/warehouses)
 * - Suppliers
 * - Transfer types and categories
 * - System configuration
 *
 * Extracted from TransferManagerAPI to follow proper MVC pattern.
 *
 * @package CIS\Consignments\Services
 * @version 1.0.0
 * @author CIS Development Team
 * @created 2025-11-05
 */

declare(strict_types=1);

namespace CIS\Consignments\Services;

use PDO;

class ConfigService
{
    /**
     * Read-only database connection
     * @var PDO
     */
    private PDO $ro;

    /**
     * Constructor
     *
     * @param PDO $ro Read-only connection
     */
    public function __construct(PDO $ro)
    {
        $this->ro = $ro;
    }

    /**
     * Factory method using global database helpers
     *
     * @return self
     */
    public static function make(): self
    {
        return new self(db_ro());
    }

    // ========================================================================
    // OUTLETS
    // ========================================================================

    /**
     * Get all active outlets
     *
     * @param bool $activeOnly Filter to active outlets only
     * @return array Array of outlet records
     */
    public function getOutlets(bool $activeOnly = true): array
    {
        $sql = "SELECT
                    id,
                    name,
                    physical_city as city,
                    physical_state as state,
                    physical_postcode as postcode,
                    physical_address_1 as address,
                    time_zone
                FROM vend_outlets";

        // Note: vend_outlets doesn't have is_active column, so all outlets are considered active
        // If you need to filter, add WHERE clause based on your business logic

        $sql .= " ORDER BY name";

        $stmt = $this->ro->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get outlet by ID
     *
     * @param int $id Outlet ID
     * @return array|null Outlet record or null if not found
     */
    public function getOutlet(int $id): ?array
    {
        $sql = "SELECT
                    id,
                    name,
                    physical_city as city,
                    physical_state as state,
                    physical_postcode as postcode,
                    physical_address_1 as address,
                    time_zone
                FROM vend_outlets
                WHERE id = :id
                LIMIT 1";

        $stmt = $this->ro->prepare($sql);
        $stmt->execute([':id' => $id]);

        $outlet = $stmt->fetch(PDO::FETCH_ASSOC);

        return $outlet ?: null;
    }

    /**
     * Get outlets by type
     *
     * @param string $type Outlet type (e.g., 'store', 'warehouse')
     * @return array Array of outlet records
     */
    public function getOutletsByType(string $type): array
    {
        // Note: vend_outlets may not have outlet_type column
        // For now, return all outlets as this field doesn't exist in schema
        return $this->getOutlets();
    }

    // ========================================================================
    // SUPPLIERS
    // ========================================================================

    /**
     * Get all active suppliers
     *
     * @param bool $activeOnly Filter to active suppliers only
     * @return array Array of supplier records
     */
    public function getSuppliers(bool $activeOnly = true): array
    {
        $sql = "SELECT
                    supplier_id as id,
                    name as supplier_name,
                    is_active as active
                FROM ls_suppliers";

        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }

        $sql .= " ORDER BY name";

        $stmt = $this->ro->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }    /**
     * Get supplier by ID
     *
     * @param int $id Supplier ID
     * @return array|null Supplier record or null if not found
     */
    public function getSupplier(int $id): ?array
    {
        $sql = "SELECT
                    supplier_id as id,
                    name as supplier_name,
                    is_active as active
                FROM ls_suppliers
                WHERE supplier_id = :id
                LIMIT 1";

        $stmt = $this->ro->prepare($sql);
        $stmt->execute([':id' => $id]);

        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

        return $supplier ?: null;
    }

    /**
     * Search suppliers by name
     *
     * @param string $query Search term
     * @param int $limit Maximum results
     * @return array Array of supplier records
     */
    public function searchSuppliers(string $query, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $searchTerm = "%{$query}%";

        $sql = "SELECT
                    supplier_id as id,
                    name as supplier_name
                FROM ls_suppliers
                WHERE name LIKE :search
                ORDER BY name
                LIMIT :limit";

        $stmt = $this->ro->prepare($sql);
        $stmt->bindValue(':search', $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========================================================================
    // TRANSFER TYPES & CATEGORIES
    // ========================================================================

    /**
     * Get available transfer types
     *
     * @return array Array of transfer type definitions
     */
    public function getTransferTypes(): array
    {
        return [
            [
                'value' => 'STOCK',
                'label' => 'Stock Transfer',
                'description' => 'Transfer stock between outlets',
                'requires_outlet_from' => true,
                'requires_outlet_to' => true,
                'requires_supplier' => false
            ],
            [
                'value' => 'JUICE',
                'label' => 'Juice Transfer',
                'description' => 'Transfer juice products',
                'requires_outlet_from' => true,
                'requires_outlet_to' => true,
                'requires_supplier' => false
            ],
            [
                'value' => 'PURCHASE_ORDER',
                'label' => 'Purchase Order',
                'description' => 'Order from supplier',
                'requires_outlet_from' => false,
                'requires_outlet_to' => true,
                'requires_supplier' => true
            ],
            [
                'value' => 'INTERNAL',
                'label' => 'Internal Transfer',
                'description' => 'Internal stock movement',
                'requires_outlet_from' => true,
                'requires_outlet_to' => true,
                'requires_supplier' => false
            ],
            [
                'value' => 'RETURN',
                'label' => 'Return to Supplier',
                'description' => 'Return stock to supplier',
                'requires_outlet_from' => true,
                'requires_outlet_to' => false,
                'requires_supplier' => true
            ],
            [
                'value' => 'STAFF',
                'label' => 'Staff Transfer',
                'description' => 'Staff product allocation',
                'requires_outlet_from' => true,
                'requires_outlet_to' => false,
                'requires_supplier' => false
            ]
        ];
    }

    /**
     * Get transfer type definition by value
     *
     * @param string $value Transfer type value (e.g., 'STOCK')
     * @return array|null Transfer type definition or null
     */
    public function getTransferType(string $value): ?array
    {
        $types = $this->getTransferTypes();

        foreach ($types as $type) {
            if ($type['value'] === $value) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Get available transfer statuses
     *
     * @return array Array of status definitions
     */
    public function getTransferStatuses(): array
    {
        return [
            [
                'value' => 'draft',
                'label' => 'Draft',
                'color' => 'secondary',
                'description' => 'Transfer is being created'
            ],
            [
                'value' => 'sent',
                'label' => 'Sent',
                'color' => 'info',
                'description' => 'Transfer has been sent'
            ],
            [
                'value' => 'receiving',
                'label' => 'Receiving',
                'color' => 'warning',
                'description' => 'Items being received'
            ],
            [
                'value' => 'received',
                'label' => 'Received',
                'color' => 'primary',
                'description' => 'All items received'
            ],
            [
                'value' => 'completed',
                'label' => 'Completed',
                'color' => 'success',
                'description' => 'Transfer completed'
            ],
            [
                'value' => 'cancelled',
                'label' => 'Cancelled',
                'color' => 'danger',
                'description' => 'Transfer cancelled'
            ]
        ];
    }

    // ========================================================================
    // SYSTEM CONFIGURATION
    // ========================================================================

    /**
     * Get user's accessible outlets
     *
     * @param int $userId User ID
     * @return array Array of outlet IDs
     */
    public function getUserOutlets(int $userId): array
    {
        // Check if user has outlet restrictions
        $sql = "SELECT outlet_id
                FROM user_outlet_access
                WHERE user_id = :user_id";

        $stmt = $this->ro->prepare($sql);
        $stmt->execute([':user_id' => $userId]);

        $outlets = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // If no restrictions found, return all active outlets
        if (empty($outlets)) {
            $sql = "SELECT id FROM vend_outlets WHERE is_active = 1";
            $stmt = $this->ro->prepare($sql);
            $stmt->execute();
            $outlets = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        return array_map('intval', $outlets);
    }

    /**
     * Get system settings
     *
     * @param string|null $key Specific setting key, or null for all
     * @return mixed Setting value or array of all settings
     */
    public function getSetting(?string $key = null): mixed
    {
        if ($key !== null) {
            $sql = "SELECT setting_value
                    FROM system_settings
                    WHERE setting_key = :key
                    LIMIT 1";

            $stmt = $this->ro->prepare($sql);
            $stmt->execute([':key' => $key]);

            $value = $stmt->fetchColumn();

            return $value !== false ? $value : null;
        }

        // Get all settings
        $sql = "SELECT setting_key, setting_value
                FROM system_settings";

        $stmt = $this->ro->prepare($sql);
        $stmt->execute();

        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }

    /**
     * Get CSRF token from session
     *
     * @return string CSRF token
     */
    public function getCsrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (!isset($_SESSION['tt_csrf'])) {
            $_SESSION['tt_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['tt_csrf'];
    }

    /**
     * Get current user info
     *
     * @return array|null User data or null if not logged in
     */
    public function getCurrentUser(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $sql = "SELECT
                    id,
                    name,
                    email,
                    role,
                    is_active
                FROM users
                WHERE id = :id
                LIMIT 1";

        $stmt = $this->ro->prepare($sql);
        $stmt->execute([':id' => $_SESSION['user_id']]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }
}
