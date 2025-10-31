<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Controllers;

use PDO;

/**
 * Leave Controller
 *
 * Handles leave request management:
 * - Pending leave requests
 * - Approval/decline workflow
 * - Leave balances
 * - Leave history
 * - Leave type management
 *
 * @package HumanResources\Payroll\Controllers
 */
class LeaveController extends BaseController
{
    /**
     * Get pending leave requests
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
                    lr.*,
                    u.first_name,
                    u.last_name,
                    u.email,
                    decider.first_name as decided_by_first_name,
                    decider.last_name as decided_by_last_name
                FROM leave_requests lr
                INNER JOIN users u ON lr.staff_id = u.id
                LEFT JOIN users decider ON lr.leave_decided_by_user = decider.id
                WHERE lr.status = 0
                " . ($staffId ? "AND lr.staff_id = ?" : "") . "
                ORDER BY lr.date_from ASC
            ");

            if ($staffId) {
                $stmt->execute([$staffId]);
            } else {
                $stmt->execute();
            }

            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->jsonResponse([
                'success' => true,
                'data' => $requests,
                'count' => count($requests)
            ]);

        } catch (\Exception $e) {
            $this->log('ERROR', 'Failed to get pending leave requests: ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to retrieve pending leave requests'
            ], 500);
        }
    }

    /**
     * Get leave history
     *
     * @return void (outputs JSON)
     */
    public function getHistory(): void
    {
        try {
            $staffId = $_GET['staff_id'] ?? null;
            $limit = min((int)($_GET['limit'] ?? 50), 200);
            $offset = (int)($_GET['offset'] ?? 0);

            // Permission check
            $isAdmin = $this->hasPermission('payroll.admin');
            if ($staffId && !$isAdmin && (int)$staffId !== $this->getCurrentUserId()) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'You can only view your own leave history'
                ], 403);
                return;
            }

            $params = [];
            $where = "1=1";

            if ($staffId) {
                $where .= " AND lr.staff_id = ?";
                $params[] = (int)$staffId;
            }

            $stmt = $this->db->prepare("
                SELECT
                    lr.*,
                    u.first_name,
                    u.last_name,
                    u.email,
                    decider.first_name as decided_by_first_name,
                    decider.last_name as decided_by_last_name
                FROM leave_requests lr
                INNER JOIN users u ON lr.staff_id = u.id
                LEFT JOIN users decider ON lr.leave_decided_by_user = decider.id
                WHERE {$where}
                ORDER BY lr.date_from DESC
                LIMIT ? OFFSET ?
            ");

            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);

            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total
                FROM leave_requests lr
                WHERE {$where}
            ");
            $countStmt->execute(array_slice($params, 0, -2));
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

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
            $this->log('ERROR', 'Failed to get leave history: ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to retrieve leave history'
            ], 500);
        }
    }

    /**
     * Create leave request
     *
     * @return void (outputs JSON)
     */
    public function create(): void
    {
        try {
            $data = $this->getJsonInput();

            // Validate required fields
            $required = ['date_from', 'date_to', 'reason', 'leaveTypeID', 'LeaveTypeName'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $this->jsonResponse([
                        'success' => false,
                        'error' => "Missing required field: {$field}"
                    ], 400);
                    return;
                }
            }

            // Staff can only create for themselves unless admin
            $staffId = $data['staff_id'] ?? $this->getCurrentUserId();
            $isAdmin = $this->hasPermission('payroll.admin');

            if (!$isAdmin && (int)$staffId !== $this->getCurrentUserId()) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'You can only create leave requests for yourself'
                ], 403);
                return;
            }

            $hoursRequested = $data['hours_requested'] ?? null;

            $stmt = $this->db->prepare("
                INSERT INTO leave_requests (
                    staff_id, date_from, date_to, reason,
                    leaveTypeID, LeaveTypeName, hours_requested,
                    status, date_created
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())
            ");

            $stmt->execute([
                (int)$staffId,
                $data['date_from'],
                $data['date_to'],
                $data['reason'],
                $data['leaveTypeID'],
                $data['LeaveTypeName'],
                $hoursRequested
            ]);

            $leaveId = (int)$this->db->lastInsertId();

            $this->log('INFO', "Created leave request #{$leaveId} for staff #{$staffId}");

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'leave_id' => $leaveId,
                    'staff_id' => (int)$staffId,
                    'status' => 'pending'
                ],
                'message' => 'Leave request created successfully'
            ], 201);

        } catch (\Exception $e) {
            $this->log('ERROR', 'Failed to create leave request: ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to create leave request'
            ], 500);
        }
    }

    /**
     * Approve leave request
     *
     * @param int $id Leave request ID
     * @return void (outputs JSON)
     */
    public function approve(int $id): void
    {
        try {
            $this->requirePermission('payroll.approve_leave');

            $decidedBy = $this->getCurrentUserId();

            $stmt = $this->db->prepare("
                UPDATE leave_requests
                SET status = 1,
                    leave_decided_by_user = ?,
                    date_decision_made = NOW()
                WHERE id = ?
                AND status = 0
            ");

            $stmt->execute([$decidedBy, $id]);

            if ($stmt->rowCount() === 0) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Leave request not found or already processed'
                ], 404);
                return;
            }

            $this->log('INFO', "Approved leave request #{$id}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Leave request approved successfully'
            ]);

        } catch (\Exception $e) {
            $this->log('ERROR', "Failed to approve leave request #{$id}: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to approve leave request'
            ], 500);
        }
    }

    /**
     * Decline leave request
     *
     * @param int $id Leave request ID
     * @return void (outputs JSON)
     */
    public function decline(int $id): void
    {
        try {
            $this->requirePermission('payroll.approve_leave');

            $data = $this->getJsonInput();
            $reason = $data['reason'] ?? 'No reason provided';
            $decidedBy = $this->getCurrentUserId();

            $stmt = $this->db->prepare("
                UPDATE leave_requests
                SET status = 2,
                    leave_decided_by_user = ?,
                    date_decision_made = NOW(),
                    denied_reason = ?
                WHERE id = ?
                AND status = 0
            ");

            $stmt->execute([$decidedBy, $reason, $id]);

            if ($stmt->rowCount() === 0) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Leave request not found or already processed'
                ], 404);
                return;
            }

            $this->log('INFO', "Declined leave request #{$id}: {$reason}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Leave request declined successfully'
            ]);

        } catch (\Exception $e) {
            $this->log('ERROR', "Failed to decline leave request #{$id}: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to decline leave request'
            ], 500);
        }
    }

    /**
     * Get leave balances for staff member
     * Note: This would require integration with Xero to get accurate leave balances
     *
     * @return void (outputs JSON)
     */
    public function getBalances(): void
    {
        try {
            $staffId = $_GET['staff_id'] ?? null;

            // Permission check
            $isAdmin = $this->hasPermission('payroll.admin');
            if ($staffId && !$isAdmin && (int)$staffId !== $this->getCurrentUserId()) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'You can only view your own leave balances'
                ], 403);
                return;
            }

            if (!$staffId) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'staff_id is required'
                ], 400);
                return;
            }

            // Get leave types from requests (could also query Xero for accurate balances)
            $stmt = $this->db->prepare("
                SELECT
                    LeaveTypeName,
                    leaveTypeID,
                    SUM(CASE WHEN status = 1 THEN hours_requested ELSE 0 END) as hours_taken,
                    COUNT(CASE WHEN status = 0 THEN 1 END) as pending_requests
                FROM leave_requests
                WHERE staff_id = ?
                GROUP BY LeaveTypeName, leaveTypeID
            ");

            $stmt->execute([(int)$staffId]);
            $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->jsonResponse([
                'success' => true,
                'data' => $balances,
                'note' => 'For accurate leave balances, integrate with Xero Leave API'
            ]);

        } catch (\Exception $e) {
            $this->log('ERROR', 'Failed to get leave balances: ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to retrieve leave balances'
            ], 500);
        }
    }
}
