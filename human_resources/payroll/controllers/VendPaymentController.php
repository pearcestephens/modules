<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Controllers;

use PayrollModule\Services\VendService;
use PDO;

/**
 * Vend Payment Controller
 *
 * Handles Vend account payment allocations for payroll:
 * - Pending payment requests
 * - Payment allocation status
 * - Manual payment entry
 * - Payment history
 * - AI review and approval workflow
 *
 * @package HumanResources\Payroll\Controllers
 */
class VendPaymentController extends BaseController
{
    private PDO $db;
    private VendService $vendService;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->db = $db;
        $this->vendService = new VendService($db);
    }

    /**
     * Get pending Vend payment requests
     *
     * @return void (outputs JSON)
     */
    public function getPending(): void
    {
        try {
            // Admin can see all, staff can see own
            $isAdmin = $this->hasPermission('payroll.admin');
            $staffId = $isAdmin ? null : $this->getCurrentUserId();

            $stmt = $this->db->prepare("
                SELECT
                    vpr.*,
                    u.first_name,
                    u.last_name,
                    u.email,
                    COUNT(vpa.id) as allocation_count
                FROM payroll_vend_payment_requests vpr
                INNER JOIN users u ON vpr.staff_id = u.id
                LEFT JOIN payroll_vend_payment_allocations vpa
                    ON vpr.id = vpa.payment_request_id
                WHERE vpr.status IN ('pending', 'ai_review', 'approved')
                " . ($staffId ? "AND vpr.staff_id = ?" : "") . "
                GROUP BY vpr.id
                ORDER BY
                    CASE vpr.status
                        WHEN 'ai_review' THEN 1
                        WHEN 'approved' THEN 2
                        WHEN 'pending' THEN 3
                    END,
                    vpr.created_at DESC
            ");

            if ($staffId) {
                $stmt->execute([$staffId]);
            } else {
                $stmt->execute();
            }

            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decode JSON fields
            foreach ($requests as &$request) {
                $request['sales_json'] = !empty($request['sales_json']) ? json_decode($request['sales_json'], true) : [];
                $request['ai_reasoning'] = !empty($request['ai_reasoning']) ? $request['ai_reasoning'] : null;
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $requests,
                'count' => count($requests)
            ]);

        } catch (\Exception $e) {
            $this->log('ERROR', 'Failed to get pending Vend payments: ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to retrieve pending Vend payments'
            ], 500);
        }
    }

    /**
     * Get Vend payment history
     *
     * @return void (outputs JSON)
     */
    public function getHistory(): void
    {
        try {
            $staffId = $_GET['staff_id'] ?? null;
            $payrollRunId = $_GET['payroll_run_id'] ?? null;
            $limit = min((int)($_GET['limit'] ?? 50), 200);
            $offset = (int)($_GET['offset'] ?? 0);

            // Permission check
            $isAdmin = $this->hasPermission('payroll.admin');
            if ($staffId && !$isAdmin && (int)$staffId !== $this->getCurrentUserId()) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'You can only view your own payment history'
                ], 403);
                return;
            }

            $params = [];
            $where = "1=1";

            if ($staffId) {
                $where .= " AND vpr.staff_id = ?";
                $params[] = (int)$staffId;
            }

            if ($payrollRunId) {
                $where .= " AND vpr.payroll_run_id = ?";
                $params[] = $payrollRunId;
            }

            $stmt = $this->db->prepare("
                SELECT
                    vpr.*,
                    u.first_name,
                    u.last_name,
                    u.email,
                    COUNT(vpa.id) as allocation_count,
                    SUM(vpa.payment_amount) as total_allocated
                FROM payroll_vend_payment_requests vpr
                INNER JOIN users u ON vpr.staff_id = u.id
                LEFT JOIN payroll_vend_payment_allocations vpa
                    ON vpr.id = vpa.payment_request_id
                WHERE {$where}
                GROUP BY vpr.id
                ORDER BY vpr.created_at DESC
                LIMIT ? OFFSET ?
            ");

            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);

            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(DISTINCT vpr.id) as total
                FROM payroll_vend_payment_requests vpr
                WHERE {$where}
            ");
            $countStmt->execute(array_slice($params, 0, -2));
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Decode JSON fields
            foreach ($requests as &$request) {
                $request['sales_json'] = !empty($request['sales_json']) ? json_decode($request['sales_json'], true) : [];
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $requests,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total
                ]
            ]);

        } catch (\Exception $e) {
            // Log error
            error_log('Failed to get Vend payment history: ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to retrieve payment history',
                'debug' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment allocations for a request
     *
     * @param int $id Payment request ID
     * @return void (outputs JSON)
     */
    public function getAllocations(int $id): void
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    vpa.*,
                    vpr.staff_id,
                    u.first_name,
                    u.last_name
                FROM payroll_vend_payment_allocations vpa
                INNER JOIN payroll_vend_payment_requests vpr ON vpa.payment_request_id = vpr.id
                INNER JOIN users u ON vpr.staff_id = u.id
                WHERE vpa.payment_request_id = ?
                ORDER BY vpa.created_at DESC
            ");

            $stmt->execute([$id]);
            $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decode JSON fields
            foreach ($allocations as &$allocation) {
                $allocation['sale_details'] = !empty($allocation['sale_details']) ? json_decode($allocation['sale_details'], true) : null;
                $allocation['vend_response'] = !empty($allocation['vend_response']) ? json_decode($allocation['vend_response'], true) : null;
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $allocations,
                'count' => count($allocations)
            ]);

        } catch (\Exception $e) {
            $this->log('ERROR', "Failed to get allocations for request #{$id}: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to retrieve payment allocations'
            ], 500);
        }
    }

    /**
     * Approve payment request
     *
     * @param int $id Payment request ID
     * @return void (outputs JSON)
     */
    public function approve(int $id): void
    {
        try {
            $this->requirePermission('payroll.approve_vend_payments');

            $stmt = $this->db->prepare("
                UPDATE payroll_vend_payment_requests
                SET status = 'approved',
                    status_changed_at = NOW()
                WHERE id = ?
                AND status IN ('pending', 'ai_review')
            ");

            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Payment request not found or already processed'
                ], 404);
                return;
            }

            $this->log('INFO', "Approved Vend payment request #{$id}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Payment request approved successfully'
            ]);

        } catch (\Exception $e) {
            $this->log('ERROR', "Failed to approve payment request #{$id}: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to approve payment request'
            ], 500);
        }
    }

    /**
     * Decline payment request
     *
     * @param int $id Payment request ID
     * @return void (outputs JSON)
     */
    public function decline(int $id): void
    {
        try {
            $this->requirePermission('payroll.approve_vend_payments');

            $data = $this->getJsonInput();
            $reason = $data['reason'] ?? 'No reason provided';

            $stmt = $this->db->prepare("
                UPDATE payroll_vend_payment_requests
                SET status = 'cancelled',
                    status_changed_at = NOW(),
                    processing_errors = ?
                WHERE id = ?
                AND status IN ('pending', 'ai_review')
            ");

            $stmt->execute([$reason, $id]);

            if ($stmt->rowCount() === 0) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Payment request not found or already processed'
                ], 404);
                return;
            }

            $this->log('INFO', "Declined Vend payment request #{$id}: {$reason}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Payment request declined successfully'
            ]);

        } catch (\Exception $e) {
            $this->log('ERROR', "Failed to decline payment request #{$id}: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to decline payment request'
            ], 500);
        }
    }

    /**
     * Get payment statistics
     *
     * @return void (outputs JSON)
     */
    public function getStatistics(): void
    {
        try {
            $this->requirePermission('payroll.admin');

            $payrollRunId = $_GET['payroll_run_id'] ?? null;

            $where = $payrollRunId ? "WHERE payroll_run_id = ?" : "";
            $params = $payrollRunId ? [$payrollRunId] : [];

            $stmt = $this->db->prepare("
                SELECT
                    status,
                    COUNT(*) as count,
                    SUM(payment_amount) as total_amount,
                    SUM(total_allocated) as total_allocated,
                    AVG(ai_confidence_score) as avg_ai_confidence
                FROM payroll_vend_payment_requests
                {$where}
                GROUP BY status
            ");

            $stmt->execute($params);
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->jsonResponse([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            $this->log('ERROR', 'Failed to get payment statistics: ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to retrieve statistics'
            ], 500);
        }
    }
}
