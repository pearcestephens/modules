<?php
/**
 * Payroll Auth Audit Service
 *
 * Records all authentication flag toggle events for compliance.
 *
 * @package HumanResources\Payroll\Services
 */

declare(strict_types=1);

namespace PayrollModule\Services;

use PDO;

final class PayrollAuthAuditService
{
    private PDO $db;

    private function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public static function make(PDO $db): self
    {
        return new self($db);
    }

    /**
     * Record an auth flag toggle event
     *
     * @param string $actor Username or system identifier
     * @param string $action 'enable' or 'disable'
     * @param bool $flagBefore Previous state
     * @param bool $flagAfter New state
     * @param string|null $ipAddress IP address of actor
     */
    public function recordToggle(
        string $actor,
        string $action,
        bool $flagBefore,
        bool $flagAfter,
        ?string $ipAddress = null
    ): void {
        $stmt = $this->db->prepare('
            INSERT INTO payroll_auth_audit_log
            (actor, action, flag_before, flag_after, ip_address)
            VALUES (?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $actor,
            $action,
            (int)$flagBefore,
            (int)$flagAfter,
            $ipAddress,
        ]);
    }

    /**
     * Get recent audit entries
     *
     * @param int $limit Maximum number of entries
     * @return array<int, array<string, mixed>>
     */
    public function getRecentEntries(int $limit = 50): array
    {
        $stmt = $this->db->prepare('
            SELECT
                id,
                timestamp,
                actor,
                action,
                flag_before,
                flag_after,
                ip_address
            FROM payroll_auth_audit_log
            ORDER BY timestamp DESC
            LIMIT ?
        ');

        $stmt->execute([$limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get audit entries for a specific actor
     *
     * @param string $actor Actor identifier
     * @param int $limit Maximum number of entries
     * @return array<int, array<string, mixed>>
     */
    public function getEntriesByActor(string $actor, int $limit = 50): array
    {
        $stmt = $this->db->prepare('
            SELECT
                id,
                timestamp,
                actor,
                action,
                flag_before,
                flag_after,
                ip_address
            FROM payroll_auth_audit_log
            WHERE actor = ?
            ORDER BY timestamp DESC
            LIMIT ?
        ');

        $stmt->execute([$actor, $limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
