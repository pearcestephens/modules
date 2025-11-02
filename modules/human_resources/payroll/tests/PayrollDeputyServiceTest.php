<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Payroll\Services\PayrollDeputyService;

final class PayrollDeputyServiceTest extends TestCase
{
    public function testClassLoadsAndReturnsArray(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // minimal table to satisfy SELECT (no rows needed)
        $pdo->exec("CREATE TABLE deputy_timesheets (id INTEGER, employee_id TEXT, ts_date TEXT)");

        $svc = PayrollDeputyService::make($pdo);
        $out = $svc->fetchTimesheets('E0', '2025-01-01', '2025-01-31');
        $this->assertIsArray($out);
    }
}
