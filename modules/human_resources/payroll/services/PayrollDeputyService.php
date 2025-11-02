<?php
declare(strict_types=1);

namespace Payroll\Services;

use PDO;
use PDOException;

final class PayrollDeputyService
{
    private PDO $pdo;

    private function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function make(PDO $pdo): self
    {
        return new self($pdo);
    }

    /**
     * Read-only fetch from deputy_timesheets
     * @return array<int,array<string,mixed>>
     */
    public function fetchTimesheets(string $employeeId, string $start, string $end): array
    {
        $sql = "SELECT * FROM deputy_timesheets
                WHERE employee_id = :emp
                  AND ts_date >= :start
                  AND ts_date <= :end
                ORDER BY ts_date ASC, id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':emp'=>$employeeId, ':start'=>$start, ':end'=>$end]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $this->logActivity('timesheets.fetch', [
            'employee_id' => $employeeId,
            'from' => $start,
            'to'   => $end,
            'count'=> count($rows),
        ]);
        return $rows;
    }

    private function logActivity(string $event, array $meta = []): void
    {
        try {
            $sql = "INSERT INTO payroll_activity_log (event, meta_json, created_at)
                    VALUES (:e, :j, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':e'=>$event, ':j'=>json_encode($meta, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
        } catch (PDOException $e) {
            // best-effort logging; never throw
        }
    }
}
