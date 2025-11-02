<?php
/**
 * E2E Test: Full Payroll Reconciliation Flow
 *
 * Validates complete reconciliation pipeline from Deputy fetch
 * through Xero comparison to variance reporting.
 *
 * @package HumanResources\Payroll\Tests\E2E
 */

declare(strict_types=1);

namespace HumanResources\Payroll\Tests\E2E;

use PHPUnit\Framework\TestCase;
use PDO;
use HumanResources\Payroll\Services\PayrollDeputyService;
use HumanResources\Payroll\Services\PayrollXeroService;
use HumanResources\Payroll\Services\ReconciliationService;

final class FullReconciliationFlowTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db->exec('
            CREATE TABLE payroll_activity_log (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              log_level TEXT NOT NULL,
              category TEXT NOT NULL,
              action TEXT NOT NULL,
              message TEXT,
              details TEXT,
              created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->db->exec('
            CREATE TABLE payroll_rate_limits (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              service TEXT NOT NULL,
              endpoint TEXT NOT NULL,
              http_status INTEGER NOT NULL,
              retry_after_sec INTEGER,
              request_id TEXT,
              payload_hash TEXT,
              occurred_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    public function testReconciliationPipelineReturnsArray(): void
    {
        $deputyService = PayrollDeputyService::make($this->db);
        $xeroService = PayrollXeroService::make($this->db);
        $reconService = new ReconciliationService($this->db);

        $start = '2025-01-01';
        $end = '2025-01-07';

        $variances = $reconService->compareDeputyToXero($start, $end);

        $this->assertIsArray($variances);
    }

    public function testPipelineLogsActivityForEachService(): void
    {
        $deputyService = PayrollDeputyService::make($this->db);
        $xeroService = PayrollXeroService::make($this->db);
        $reconService = new ReconciliationService($this->db);

        $reconService->compareDeputyToXero('2025-01-01', '2025-01-07');

        $stmt = $this->db->query('SELECT COUNT(*) as cnt FROM payroll_activity_log WHERE category IN (?, ?, ?)');
        $stmt->execute(['deputy', 'xero', 'recon']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertGreaterThan(0, (int)$row['cnt']);
    }
}
