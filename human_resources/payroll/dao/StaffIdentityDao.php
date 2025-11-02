<?php
/**
 * Staff Identity Map DAO
 *
 * Data access layer for staff_identity_map table
 * Provides CRUD operations and validation for Xero↔Vend staff mappings
 *
 * Task: T1 - Canonical ID Mapping Table
 * Priority: P0 (Blocker for all allocations)
 *
 * @package CIS\Payroll\DAO
 * @version 1.0.0
 * @created 2025-11-02
 */

declare(strict_types=1);

class StaffIdentityDao
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Create a new staff mapping
     *
     * @param string $xeroEmployeeId Xero employee UUID
     * @param string $vendCustomerId Vend customer ID
     * @param string $displayName Staff full name
     * @param array $options Optional: staff_number, metadata, created_by
     * @return int Inserted mapping ID
     * @throws PDOException If duplicate IDs exist
     */
    public function create(
        string $xeroEmployeeId,
        string $vendCustomerId,
        string $displayName,
        array $options = []
    ): int {
        $sql = <<<SQL
INSERT INTO staff_identity_map (
    xero_employee_id,
    vend_customer_id,
    staff_number,
    display_name,
    active,
    metadata,
    created_by
) VALUES (?, ?, ?, ?, ?, ?, ?)
SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $xeroEmployeeId,
            $vendCustomerId,
            $options['staff_number'] ?? null,
            $displayName,
            $options['active'] ?? 1,
            isset($options['metadata']) ? json_encode($options['metadata']) : null,
            $options['created_by'] ?? null
        ]);

        $mappingId = (int) $this->db->lastInsertId();

        // Log audit trail
        $this->logAudit($mappingId, 'CREATE', null, null, json_encode([
            'xero_employee_id' => $xeroEmployeeId,
            'vend_customer_id' => $vendCustomerId,
            'display_name' => $displayName
        ]), $options['created_by'] ?? null);

        return $mappingId;
    }

    /**
     * Find mapping by Xero employee ID
     *
     * @param string $xeroEmployeeId
     * @return array|null Mapping row or null if not found
     */
    public function findByXeroId(string $xeroEmployeeId): ?array
    {
        $sql = "SELECT * FROM staff_identity_map WHERE xero_employee_id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$xeroEmployeeId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Find mapping by Vend customer ID
     *
     * @param string $vendCustomerId
     * @return array|null Mapping row or null if not found
     */
    public function findByVendId(string $vendCustomerId): ?array
    {
        $sql = "SELECT * FROM staff_identity_map WHERE vend_customer_id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$vendCustomerId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get mapping by internal ID
     *
     * @param int $id Mapping ID
     * @return array|null Mapping row or null if not found
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM staff_identity_map WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get all active mappings
     *
     * @return array List of active staff mappings
     */
    public function getAllActive(): array
    {
        $sql = "SELECT * FROM staff_identity_map WHERE active = 1 ORDER BY display_name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all mappings (active and inactive)
     *
     * @return array List of all staff mappings
     */
    public function getAll(): array
    {
        $sql = "SELECT * FROM staff_identity_map ORDER BY active DESC, display_name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update an existing mapping
     *
     * @param int $id Mapping ID
     * @param array $fields Fields to update (keys: vend_customer_id, staff_number, display_name, active, metadata)
     * @param int|null $updatedBy User ID making the update
     * @return bool Success
     */
    public function update(int $id, array $fields, ?int $updatedBy = null): bool
    {
        $existing = $this->findById($id);
        if (!$existing) {
            throw new RuntimeException("Mapping ID {$id} not found");
        }

        $allowedFields = ['vend_customer_id', 'staff_number', 'display_name', 'active', 'metadata'];
        $updates = [];
        $values = [];

        foreach ($fields as $field => $value) {
            if (!in_array($field, $allowedFields)) {
                continue;
            }

            $updates[] = "{$field} = ?";

            if ($field === 'metadata' && is_array($value)) {
                $values[] = json_encode($value);
            } else {
                $values[] = $value;
            }

            // Log audit for each changed field
            if ($existing[$field] != $value) {
                $oldVal = $existing[$field];
                $newVal = is_array($value) ? json_encode($value) : $value;
                $this->logAudit($id, 'UPDATE', $field, $oldVal, $newVal, $updatedBy);
            }
        }

        if ($updatedBy !== null) {
            $updates[] = "updated_by = ?";
            $values[] = $updatedBy;
        }

        if (empty($updates)) {
            return true; // Nothing to update
        }

        $values[] = $id; // For WHERE clause

        $sql = "UPDATE staff_identity_map SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Deactivate a mapping (soft delete)
     *
     * @param int $id Mapping ID
     * @param int|null $deactivatedBy User ID
     * @return bool Success
     */
    public function deactivate(int $id, ?int $deactivatedBy = null): bool
    {
        $result = $this->update($id, ['active' => 0], $deactivatedBy);

        if ($result) {
            $this->logAudit($id, 'DEACTIVATE', 'active', '1', '0', $deactivatedBy);
        }

        return $result;
    }

    /**
     * Reactivate a mapping
     *
     * @param int $id Mapping ID
     * @param int|null $reactivatedBy User ID
     * @return bool Success
     */
    public function reactivate(int $id, ?int $reactivatedBy = null): bool
    {
        $result = $this->update($id, ['active' => 1], $reactivatedBy);

        if ($result) {
            $this->logAudit($id, 'REACTIVATE', 'active', '0', '1', $reactivatedBy);
        }

        return $result;
    }

    /**
     * Hard delete a mapping (use with caution)
     *
     * @param int $id Mapping ID
     * @param int|null $deletedBy User ID
     * @return bool Success
     */
    public function delete(int $id, ?int $deletedBy = null): bool
    {
        $existing = $this->findById($id);
        if (!$existing) {
            return false;
        }

        // Log before deletion
        $this->logAudit($id, 'DELETE', null, json_encode($existing), null, $deletedBy);

        $sql = "DELETE FROM staff_identity_map WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Check if a Xero employee ID is mapped
     *
     * @param string $xeroEmployeeId
     * @return bool True if mapped
     */
    public function isMapped(string $xeroEmployeeId): bool
    {
        return $this->findByXeroId($xeroEmployeeId) !== null;
    }

    /**
     * Get unmapped Xero employee IDs from a list
     *
     * Useful for validating pay runs before applying
     *
     * @param array $xeroEmployeeIds List of Xero employee IDs
     * @return array List of unmapped IDs
     */
    public function getUnmapped(array $xeroEmployeeIds): array
    {
        if (empty($xeroEmployeeIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($xeroEmployeeIds), '?'));
        $sql = "SELECT xero_employee_id FROM staff_identity_map WHERE xero_employee_id IN ({$placeholders})";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($xeroEmployeeIds);

        $mapped = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'xero_employee_id');

        return array_diff($xeroEmployeeIds, $mapped);
    }

    /**
     * Get mapping statistics
     *
     * @return array Stats: total, active, inactive
     */
    public function getStats(): array
    {
        $sql = <<<SQL
SELECT
    COUNT(*) as total,
    SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN active = 0 THEN 1 ELSE 0 END) as inactive
FROM staff_identity_map
SQL;

        $stmt = $this->db->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Search mappings by display name
     *
     * @param string $search Search term
     * @param bool $activeOnly Only search active mappings
     * @return array Matching mappings
     */
    public function search(string $search, bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM staff_identity_map WHERE display_name LIKE ?";
        $params = ['%' . $search . '%'];

        if ($activeOnly) {
            $sql .= " AND active = 1";
        }

        $sql .= " ORDER BY display_name ASC LIMIT 50";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bulk insert mappings (for initial data load)
     *
     * @param array $mappings Array of mappings: [[xero_id, vend_id, name], ...]
     * @param int|null $createdBy User ID
     * @return int Number of rows inserted
     */
    public function bulkInsert(array $mappings, ?int $createdBy = null): int
    {
        if (empty($mappings)) {
            return 0;
        }

        $this->db->beginTransaction();

        try {
            $sql = <<<SQL
INSERT INTO staff_identity_map (
    xero_employee_id,
    vend_customer_id,
    display_name,
    created_by
) VALUES (?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    updated_at = NOW()
SQL;

            $stmt = $this->db->prepare($sql);
            $inserted = 0;

            foreach ($mappings as $mapping) {
                $stmt->execute([
                    $mapping['xero_employee_id'],
                    $mapping['vend_customer_id'],
                    $mapping['display_name'],
                    $createdBy
                ]);

                if ($stmt->rowCount() > 0) {
                    $inserted++;
                }
            }

            $this->db->commit();
            return $inserted;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Log audit trail for mapping changes
     *
     * @param int $mappingId
     * @param string $action CREATE|UPDATE|DELETE|DEACTIVATE|REACTIVATE
     * @param string|null $field Field changed (for UPDATE)
     * @param string|null $oldValue
     * @param string|null $newValue
     * @param int|null $changedBy User ID
     */
    private function logAudit(
        int $mappingId,
        string $action,
        ?string $field,
        ?string $oldValue,
        ?string $newValue,
        ?int $changedBy
    ): void {
        $sql = <<<SQL
INSERT INTO staff_identity_map_audit (
    mapping_id,
    action,
    field_changed,
    old_value,
    new_value,
    changed_by,
    ip_address,
    user_agent
) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $mappingId,
            $action,
            $field,
            $oldValue,
            $newValue,
            $changedBy,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    /**
     * Get audit history for a mapping
     *
     * @param int $mappingId
     * @param int $limit Max records to return
     * @return array Audit log entries
     */
    public function getAuditHistory(int $mappingId, int $limit = 50): array
    {
        $sql = <<<SQL
SELECT
    a.*,
    u.name as changed_by_name
FROM staff_identity_map_audit a
LEFT JOIN users u ON a.changed_by = u.id
WHERE a.mapping_id = ?
ORDER BY a.changed_at DESC
LIMIT ?
SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$mappingId, $limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
