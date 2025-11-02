<?php
/**
 * PayrollXeroService skeleton
 */

declare(strict_types=1);

final class PayrollXeroService
{
    private function __construct(private readonly PDO $db)
    {
    }

    public static function make(PDO $connection): self
    {
        return new self($connection);
    }

    public function listEmployees(): array
    {
        return [];
    }

    public function logActivity(string $action, string $message, array $context = []): void
    {
        try {
            $sql = 'INSERT INTO payroll_activity_log (log_level, category, action, message, details, created_at)
                    VALUES (:level, :category, :action, :message, :details, NOW())';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':level' => 'info',
                ':category' => 'xero',
                ':action' => $action,
                ':message' => $message,
                ':details' => empty($context) ? null : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
        } catch (Throwable $e) {
            // Do not interrupt caller; fallback to PHP error log.
            error_log('PayrollXeroService activity log failed: ' . $e->getMessage());
        }
    }
}
