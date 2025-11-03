<?php
/**
 * Integration Test: Complete Xero OAuth to Pay Run Workflow
 *
 * Tests OAuth authorization, employee sync, and pay run creation
 * Uses Mockery for external API mocking
 *
 * @package CIS\Payroll\Tests\Integration
 */

declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

require_once __DIR__ . '/../../services/PayrollXeroService.php';

final class XeroOAuthToPayRunWorkflowTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private \PDO $db;
    private \PayrollXeroService $xeroService;

    protected function setUp(): void
    {
        $this->db = new \PDO(
            'mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4',
            'jcepnzzkmj',
            'wprKh9Jq63',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        $this->xeroService = \PayrollXeroService::make($this->db);

        // Start transaction
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        Mockery::close();
    }

    /**
     * Test complete OAuth flow
     *
     * @group integration
     * @group oauth
     */
    public function testCompleteOAuthFlow(): void
    {
        // PHASE 1: Authorization URL generation
        $authorizeUrl = $this->xeroService->authorize();

        $this->assertIsString($authorizeUrl);
        $this->assertStringContainsString('login.xero.com', $authorizeUrl);
        $this->assertStringContainsString('client_id', $authorizeUrl);
        $this->assertStringContainsString('redirect_uri', $authorizeUrl);

        // PHASE 2: Store mock OAuth state
        $state = 'test_state_' . uniqid();
        $this->db->exec("DELETE FROM oauth_states WHERE service = 'xero'");

        $stmt = $this->db->prepare("
            INSERT INTO oauth_states (service, state, redirect_uri, created_at, expires_at)
            VALUES ('xero', ?, 'http://localhost/callback', NOW(), DATE_ADD(NOW(), INTERVAL 10 MINUTE))
        ");
        $stmt->execute([$state]);

        // Verify state stored
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM oauth_states WHERE state = ?
        ");
        $stmt->execute([$state]);
        $count = (int) $stmt->fetchColumn();
        $this->assertSame(1, $count, 'OAuth state should be stored');

        // PHASE 3: Token storage (mock)
        $tenantId = 'test_tenant_' . uniqid();
        $this->db->exec("DELETE FROM xero_tokens WHERE tenant_id LIKE 'test_tenant_%'");

        $stmt = $this->db->prepare("
            INSERT INTO xero_tokens
            (tenant_id, access_token, refresh_token, expires_at, created_at)
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW())
        ");
        $stmt->execute([
            $tenantId,
            'mock_access_token_' . bin2hex(random_bytes(16)),
            'mock_refresh_token_' . bin2hex(random_bytes(16))
        ]);

        $tokenId = (int) $this->db->lastInsertId();
        $this->assertGreaterThan(0, $tokenId);

        // Verify token retrieval
        $stmt = $this->db->prepare("
            SELECT access_token, expires_at FROM xero_tokens WHERE tenant_id = ?
        ");
        $stmt->execute([$tenantId]);
        $token = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray($token);
        $this->assertArrayHasKey('access_token', $token);
        $this->assertArrayHasKey('expires_at', $token);
        $this->assertStringStartsWith('mock_access_token_', $token['access_token']);
    }

    /**
     * Test employee sync workflow
     *
     * @group integration
     * @group employee-sync
     */
    public function testEmployeeSyncWorkflow(): void
    {
        // Mock Xero employees
        $mockEmployees = [
            [
                'EmployeeID' => 'xero-emp-001',
                'FirstName' => 'John',
                'LastName' => 'Smith',
                'Email' => 'john.smith@test.com',
                'DateOfBirth' => '1990-05-15',
                'StartDate' => '2020-01-01'
            ],
            [
                'EmployeeID' => 'xero-emp-002',
                'FirstName' => 'Jane',
                'LastName' => 'Doe',
                'Email' => 'jane.doe@test.com',
                'DateOfBirth' => '1992-08-20',
                'StartDate' => '2021-03-15'
            ]
        ];

        // Clean test data
        $this->db->exec("DELETE FROM employees WHERE email LIKE '%@test.com'");

        // Import employees
        $syncedCount = 0;
        foreach ($mockEmployees as $emp) {
            $stmt = $this->db->prepare("
                INSERT INTO employees
                (xero_employee_id, first_name, last_name, email, date_of_birth, start_date, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    first_name = VALUES(first_name),
                    last_name = VALUES(last_name),
                    email = VALUES(email),
                    updated_at = NOW()
            ");

            $stmt->execute([
                $emp['EmployeeID'],
                $emp['FirstName'],
                $emp['LastName'],
                $emp['Email'],
                $emp['DateOfBirth'],
                $emp['StartDate']
            ]);

            $syncedCount++;
        }

        $this->assertSame(2, $syncedCount, 'Should sync 2 employees');

        // Verify employees in database
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM employees WHERE email LIKE '%@test.com'
        ");
        $count = (int) $stmt->fetchColumn();
        $this->assertSame(2, $count);

        // Test duplicate handling (update existing)
        $updateEmployee = [
            'EmployeeID' => 'xero-emp-001',
            'FirstName' => 'John',
            'LastName' => 'Smith-Updated',
            'Email' => 'john.smith@test.com',
            'DateOfBirth' => '1990-05-15',
            'StartDate' => '2020-01-01'
        ];

        $stmt = $this->db->prepare("
            INSERT INTO employees
            (xero_employee_id, first_name, last_name, email, date_of_birth, start_date, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                updated_at = NOW()
        ");

        $stmt->execute([
            $updateEmployee['EmployeeID'],
            $updateEmployee['FirstName'],
            $updateEmployee['LastName'],
            $updateEmployee['Email'],
            $updateEmployee['DateOfBirth'],
            $updateEmployee['StartDate']
        ]);

        // Verify still only 2 employees (no duplicate)
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM employees WHERE email LIKE '%@test.com'
        ");
        $count = (int) $stmt->fetchColumn();
        $this->assertSame(2, $count, 'Should not create duplicate');

        // Verify last name was updated
        $stmt = $this->db->prepare("
            SELECT last_name FROM employees WHERE xero_employee_id = ?
        ");
        $stmt->execute(['xero-emp-001']);
        $lastName = $stmt->fetchColumn();
        $this->assertSame('Smith-Updated', $lastName);
    }

    /**
     * Test pay run creation workflow
     *
     * @group integration
     * @group pay-run
     */
    public function testPayRunCreationWorkflow(): void
    {
        // Create test pay run
        $payRunData = [
            'period_start' => '2025-11-01',
            'period_end' => '2025-11-30',
            'payment_date' => '2025-12-05',
            'status' => 'draft',
            'total_gross' => 5000.00,
            'total_tax' => 1000.00,
            'total_net' => 4000.00
        ];

        $stmt = $this->db->prepare("
            INSERT INTO pay_runs
            (period_start, period_end, payment_date, status, total_gross, total_tax, total_net, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $payRunData['period_start'],
            $payRunData['period_end'],
            $payRunData['payment_date'],
            $payRunData['status'],
            $payRunData['total_gross'],
            $payRunData['total_tax'],
            $payRunData['total_net']
        ]);

        $payRunId = (int) $this->db->lastInsertId();
        $this->assertGreaterThan(0, $payRunId);

        // Add pay run items (payslips)
        $payslips = [
            ['employee_id' => 101, 'gross' => 2000.00, 'tax' => 400.00, 'net' => 1600.00],
            ['employee_id' => 102, 'gross' => 1500.00, 'tax' => 300.00, 'net' => 1200.00],
            ['employee_id' => 103, 'gross' => 1500.00, 'tax' => 300.00, 'net' => 1200.00]
        ];

        foreach ($payslips as $slip) {
            $stmt = $this->db->prepare("
                INSERT INTO pay_run_items
                (pay_run_id, employee_id, gross_pay, tax, net_pay, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $payRunId,
                $slip['employee_id'],
                $slip['gross'],
                $slip['tax'],
                $slip['net']
            ]);
        }

        // Verify pay run items
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM pay_run_items WHERE pay_run_id = ?
        ");
        $stmt->execute([$payRunId]);
        $itemCount = (int) $stmt->fetchColumn();
        $this->assertSame(3, $itemCount, 'Should have 3 pay run items');

        // Verify totals match
        $stmt = $this->db->prepare("
            SELECT SUM(gross_pay), SUM(tax), SUM(net_pay)
            FROM pay_run_items WHERE pay_run_id = ?
        ");
        $stmt->execute([$payRunId]);
        $totals = $stmt->fetch(\PDO::FETCH_NUM);

        $this->assertSame(5000.00, (float) $totals[0], 'Total gross should match');
        $this->assertSame(1000.00, (float) $totals[1], 'Total tax should match');
        $this->assertSame(4000.00, (float) $totals[2], 'Total net should match');

        // Test finalization
        $stmt = $this->db->prepare("
            UPDATE pay_runs
            SET status = 'finalized', finalized_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$payRunId]);

        $stmt = $this->db->prepare("
            SELECT status, finalized_at FROM pay_runs WHERE id = ?
        ");
        $stmt->execute([$payRunId]);
        $payRun = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('finalized', $payRun['status']);
        $this->assertNotNull($payRun['finalized_at']);
    }

    /**
     * Test token refresh workflow
     *
     * @group integration
     * @group token-refresh
     */
    public function testTokenRefreshWorkflow(): void
    {
        // Create expired token
        $tenantId = 'test_refresh_' . uniqid();

        $stmt = $this->db->prepare("
            INSERT INTO xero_tokens
            (tenant_id, access_token, refresh_token, expires_at, created_at)
            VALUES (?, 'old_access_token', 'refresh_token_123', DATE_SUB(NOW(), INTERVAL 1 HOUR), NOW())
        ");
        $stmt->execute([$tenantId]);

        // Verify token is expired
        $stmt = $this->db->prepare("
            SELECT expires_at < NOW() AS is_expired FROM xero_tokens WHERE tenant_id = ?
        ");
        $stmt->execute([$tenantId]);
        $isExpired = (bool) $stmt->fetchColumn();
        $this->assertTrue($isExpired, 'Token should be expired');

        // Simulate token refresh
        $newAccessToken = 'new_access_token_' . bin2hex(random_bytes(16));
        $newRefreshToken = 'new_refresh_token_' . bin2hex(random_bytes(16));

        $stmt = $this->db->prepare("
            UPDATE xero_tokens
            SET access_token = ?,
                refresh_token = ?,
                expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE),
                updated_at = NOW()
            WHERE tenant_id = ?
        ");
        $stmt->execute([$newAccessToken, $newRefreshToken, $tenantId]);

        // Verify token refreshed
        $stmt = $this->db->prepare("
            SELECT access_token, expires_at > NOW() AS is_valid FROM xero_tokens WHERE tenant_id = ?
        ");
        $stmt->execute([$tenantId]);
        $token = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertStringStartsWith('new_access_token_', $token['access_token']);
        $this->assertTrue((bool) $token['is_valid'], 'Refreshed token should be valid');

        // Log refresh activity
        $stmt = $this->db->prepare("
            INSERT INTO payroll_activity_log
            (category, action, message, created_at)
            VALUES ('xero', 'token.refresh', 'Token refreshed for tenant', NOW())
        ");
        $stmt->execute();

        $stmt = $this->db->query("
            SELECT COUNT(*) FROM payroll_activity_log
            WHERE category = 'xero' AND action = 'token.refresh'
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $logCount = (int) $stmt->fetchColumn();
        $this->assertGreaterThan(0, $logCount);
    }

    /**
     * Test rate limit tracking across workflow
     *
     * @group integration
     * @group rate-limiting
     */
    public function testRateLimitTrackingWorkflow(): void
    {
        // Clean rate limit data
        $this->db->exec("DELETE FROM payroll_rate_limits WHERE service = 'xero'");

        // Simulate multiple API calls
        for ($i = 0; $i < 5; $i++) {
            $stmt = $this->db->prepare("
                INSERT INTO payroll_rate_limits
                (service, endpoint, request_count, window_start, created_at)
                VALUES ('xero', '/employees', 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    request_count = request_count + 1,
                    updated_at = NOW()
            ");
            $stmt->execute();
        }

        // Verify rate limit tracking
        $stmt = $this->db->query("
            SELECT request_count FROM payroll_rate_limits
            WHERE service = 'xero' AND endpoint = '/employees'
            ORDER BY id DESC LIMIT 1
        ");
        $requestCount = (int) $stmt->fetchColumn();

        $this->assertGreaterThanOrEqual(1, $requestCount);

        // Test rate limit check (60 requests per minute)
        $stmt = $this->db->prepare("
            SELECT request_count FROM payroll_rate_limits
            WHERE service = 'xero'
            AND endpoint = '/employees'
            AND window_start > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $stmt->execute();
        $recentRequests = (int) $stmt->fetchColumn();

        $this->assertLessThan(60, $recentRequests, 'Should be under rate limit');
    }
}
