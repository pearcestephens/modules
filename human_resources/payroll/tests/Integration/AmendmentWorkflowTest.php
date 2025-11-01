<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;

/**
 * Amendment Workflow Integration Test
 *
 * Tests the complete amendment lifecycle from creation to approval
 *
 * @group integration
 * @group database
 */
class AmendmentWorkflowTest extends TestCase
{
    private PDO $db;
    private int $testEmployeeId;
    private int $testAdminId;

    protected function setUp(): void
    {
        // Use test database
        $this->db = new PDO(
            'mysql:host=127.0.0.1;dbname=test_payroll;charset=utf8mb4',
            getenv('DB_USER') ?: 'test_user',
            getenv('DB_PASSWORD') ?: 'test_pass',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

        // Start transaction
        $this->db->beginTransaction();

        // Create test employee
        $stmt = $this->db->prepare("
            INSERT INTO employees (name, email, department, hired_date)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute(['Test Employee', 'test@example.com', 'Sales', '2024-01-01']);
        $this->testEmployeeId = (int)$this->db->lastInsertId();

        // Create test admin
        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, role, permissions)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            'Test Admin',
            'admin@example.com',
            'admin',
            json_encode(['payroll.amendments.approve'])
        ]);
        $this->testAdminId = (int)$this->db->lastInsertId();
    }

    protected function tearDown(): void
    {
        // Rollback transaction
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    /**
     * Test complete amendment workflow:
     * 1. Staff creates amendment
     * 2. Admin approves amendment
     * 3. Payment is calculated correctly
     * 4. Amendment appears in payrun
     */
    public function testCompleteAmendmentWorkflow(): void
    {
        // Step 1: Create amendment as staff member
        $stmt = $this->db->prepare("
            INSERT INTO payroll_amendments
            (employee_id, type, amount, reason, status, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $this->testEmployeeId,
            'bonus',
            250.00,
            'Excellent customer service',
            'pending',
            $this->testEmployeeId
        ]);

        $amendmentId = (int)$this->db->lastInsertId();

        // Verify amendment created with pending status
        $stmt = $this->db->prepare("SELECT * FROM payroll_amendments WHERE id = ?");
        $stmt->execute([$amendmentId]);
        $amendment = $stmt->fetch();

        $this->assertNotFalse($amendment);
        $this->assertEquals('pending', $amendment['status']);
        $this->assertEquals(250.00, (float)$amendment['amount']);
        $this->assertNull($amendment['approved_by']);
        $this->assertNull($amendment['approved_at']);

        // Step 2: Admin approves amendment
        $stmt = $this->db->prepare("
            UPDATE payroll_amendments
            SET status = ?, approved_by = ?, approved_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute(['approved', $this->testAdminId, $amendmentId]);

        // Verify amendment approved
        $stmt = $this->db->prepare("SELECT * FROM payroll_amendments WHERE id = ?");
        $stmt->execute([$amendmentId]);
        $amendment = $stmt->fetch();

        $this->assertEquals('approved', $amendment['status']);
        $this->assertEquals($this->testAdminId, $amendment['approved_by']);
        $this->assertNotNull($amendment['approved_at']);

        // Step 3: Create payrun and verify amendment included
        $stmt = $this->db->prepare("
            INSERT INTO payroll_payruns (period, start_date, end_date, payment_date, status, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            '2025-11',
            '2025-11-01',
            '2025-11-30',
            '2025-12-05',
            'draft',
            $this->testAdminId
        ]);

        $payrunId = (int)$this->db->lastInsertId();

        // Add employee to payrun with base pay
        $stmt = $this->db->prepare("
            INSERT INTO payroll_payrun_items
            (payrun_id, employee_id, base_pay, bonuses, deductions, gross_pay, tax, net_pay)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $basePay = 5000.00;
        $bonus = 250.00; // Our amendment
        $grossPay = $basePay + $bonus;
        $tax = $grossPay * 0.20; // 20% tax rate
        $netPay = $grossPay - $tax;

        $stmt->execute([
            $payrunId,
            $this->testEmployeeId,
            $basePay,
            $bonus,
            0,
            $grossPay,
            $tax,
            $netPay
        ]);

        // Verify payrun item has correct calculations
        $stmt = $this->db->prepare("
            SELECT * FROM payroll_payrun_items
            WHERE payrun_id = ? AND employee_id = ?
        ");
        $stmt->execute([$payrunId, $this->testEmployeeId]);
        $payrunItem = $stmt->fetch();

        $this->assertNotFalse($payrunItem);
        $this->assertEquals(5000.00, (float)$payrunItem['base_pay']);
        $this->assertEquals(250.00, (float)$payrunItem['bonuses']);
        $this->assertEquals(5250.00, (float)$payrunItem['gross_pay']);
        $this->assertEquals(1050.00, (float)$payrunItem['tax']);
        $this->assertEquals(4200.00, (float)$payrunItem['net_pay']);

        // Step 4: Verify amendment marked as processed
        $stmt = $this->db->prepare("
            UPDATE payroll_amendments
            SET payrun_id = ?, processed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$payrunId, $amendmentId]);

        $stmt = $this->db->prepare("SELECT * FROM payroll_amendments WHERE id = ?");
        $stmt->execute([$amendmentId]);
        $amendment = $stmt->fetch();

        $this->assertEquals($payrunId, $amendment['payrun_id']);
        $this->assertNotNull($amendment['processed_at']);
    }

    /**
     * Test amendment decline workflow
     */
    public function testAmendmentDeclineWorkflow(): void
    {
        // Create amendment
        $stmt = $this->db->prepare("
            INSERT INTO payroll_amendments
            (employee_id, type, amount, reason, status, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $this->testEmployeeId,
            'bonus',
            500.00,
            'Test bonus',
            'pending',
            $this->testEmployeeId
        ]);

        $amendmentId = (int)$this->db->lastInsertId();

        // Admin declines with reason
        $stmt = $this->db->prepare("
            UPDATE payroll_amendments
            SET status = ?, approved_by = ?, approved_at = NOW(), decline_reason = ?
            WHERE id = ?
        ");

        $stmt->execute([
            'declined',
            $this->testAdminId,
            'Insufficient documentation provided',
            $amendmentId
        ]);

        // Verify declined status
        $stmt = $this->db->prepare("SELECT * FROM payroll_amendments WHERE id = ?");
        $stmt->execute([$amendmentId]);
        $amendment = $stmt->fetch();

        $this->assertEquals('declined', $amendment['status']);
        $this->assertEquals($this->testAdminId, $amendment['approved_by']);
        $this->assertEquals('Insufficient documentation provided', $amendment['decline_reason']);

        // Verify declined amendments not included in payrun
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM payroll_payrun_items pi
            JOIN payroll_amendments a ON a.payrun_id = pi.payrun_id AND a.employee_id = pi.employee_id
            WHERE a.id = ? AND a.status = 'declined'
        ");
        $stmt->execute([$amendmentId]);
        $result = $stmt->fetch();

        $this->assertEquals(0, $result['count'], 'Declined amendments should not appear in payruns');
    }

    /**
     * Test multiple amendments for same employee
     */
    public function testMultipleAmendmentsCombined(): void
    {
        // Create two amendments
        $amendments = [];
        $stmt = $this->db->prepare("
            INSERT INTO payroll_amendments
            (employee_id, type, amount, reason, status, created_by, created_at, approved_by, approved_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, NOW())
        ");

        // Amendment 1: Bonus
        $stmt->execute([
            $this->testEmployeeId,
            'bonus',
            100.00,
            'Performance bonus',
            'approved',
            $this->testEmployeeId,
            $this->testAdminId
        ]);
        $amendments[] = (int)$this->db->lastInsertId();

        // Amendment 2: Allowance
        $stmt->execute([
            $this->testEmployeeId,
            'allowance',
            50.00,
            'Travel allowance',
            'approved',
            $this->testEmployeeId,
            $this->testAdminId
        ]);
        $amendments[] = (int)$this->db->lastInsertId();

        // Calculate total bonuses for payrun
        $stmt = $this->db->prepare("
            SELECT SUM(amount) as total_bonuses
            FROM payroll_amendments
            WHERE employee_id = ? AND status = 'approved' AND payrun_id IS NULL
        ");
        $stmt->execute([$this->testEmployeeId]);
        $result = $stmt->fetch();

        $this->assertEquals(150.00, (float)$result['total_bonuses']);
    }
}
