<?php
/**
 * AuditLogModel - Audit trail model
 *
 * Manages bank_audit_trail table for compliance and tracking
 *
 * @package CIS\BankTransactions\Models
 */

declare(strict_types=1);

namespace CIS\BankTransactions\Models;

require_once __DIR__ . '/BaseModel.php';

class AuditLogModel extends BaseModel
{
    protected $table = 'bank_audit_trail';
    protected $primaryKey = 'id';

    /**
     * Log an action
     *
     * @param string $entityType Entity type (transaction, order, payment)
     * @param int $entityId Entity ID
     * @param string $action Action performed
     * @param int|null $userId User ID (null for system actions)
     * @param array $details Additional details
     * @return int|false Log ID or false on failure
     */
    public function log(
        string $entityType,
        int $entityId,
        string $action,
        ?int $userId = null,
        array $details = []
    ) {
        $data = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'user_id' => $userId,
            'user_name' => $userId ? $this->getUserName($userId) : 'SYSTEM',
            'details' => json_encode($details),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        return $this->insert($data);
    }

    /**
     * Get audit trail for entity
     *
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $limit Number of records
     * @return array Audit entries
     */
    public function getTrail(string $entityType, int $entityId, int $limit = 100): array
    {
        $entries = $this->findAll(
            ['entity_type' => $entityType, 'entity_id' => $entityId],
            ['order' => 'created_at DESC', 'limit' => $limit]
        );

        // Decode JSON details
        foreach ($entries as &$entry) {
            $entry['details'] = json_decode($entry['details'] ?? '{}', true);
        }

        return $entries;
    }

    /**
     * Search audit logs
     *
     * @param array $filters Search filters
     * @param int $limit Number of records
     * @param int $offset Offset for pagination
     * @return array Audit entries
     */
    public function search(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        if (!empty($filters['entity_type'])) {
            $sql .= " AND entity_type = ?";
            $params[] = $filters['entity_type'];
        }

        if (!empty($filters['action'])) {
            $sql .= " AND action = ?";
            $params[] = $filters['action'];
        }

        if (!empty($filters['user_id'])) {
            $sql .= " AND user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $entries = $this->query($sql, $params);

        // Decode JSON details
        foreach ($entries as &$entry) {
            $entry['details'] = json_decode($entry['details'] ?? '{}', true);
        }

        return $entries;
    }

    /**
     * Get user name by ID
     *
     * @param int $userId User ID
     * @return string User name
     */
    private function getUserName(int $userId): string
    {
        $sql = "SELECT CONCAT(first_name, ' ', last_name) as name
                FROM users
                WHERE user_id = ?
                LIMIT 1";

        $result = $this->query($sql, [$userId]);
        return $result[0]['name'] ?? 'Unknown User';
    }
}
