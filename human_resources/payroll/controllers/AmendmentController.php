<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Controllers;

/**
 * Amendment Controller
 *
 * HTTP API endpoints for timesheet amendments:
 * - POST /api/payroll/amendments/create
 * - GET /api/payroll/amendments/:id
 * - POST /api/payroll/amendments/:id/approve
 * - POST /api/payroll/amendments/:id/decline
 * - GET /api/payroll/amendments/pending
 * - GET /api/payroll/amendments/history
 *
 * @package PayrollModule\Controllers
 * @version 1.0.0
 */

use PayrollModule\Services\AmendmentService;
use PayrollModule\Lib\PayrollLogger;
use PDO;

class AmendmentController extends BaseController
{
    private PDO $db;
    private AmendmentService $amendmentService;

    /**
     * Constructor
     */
    public function __construct(PDO $db)
    {
        parent::__construct();
        $this->db = $db;
        $this->amendmentService = new AmendmentService($db);
    }

    /**
     * Create a new timesheet amendment
     *
     * POST /api/payroll/amendments/create
     *
     * Required POST fields:
     * - staff_id (int)
     * - pay_period_id (int)
     * - original_start (datetime)
     * - original_end (datetime)
     * - new_start (datetime)
     * - new_end (datetime)
     * - reason (string)
     *
     * Optional fields:
     * - deputy_timesheet_id (int)
     * - original_break_minutes (int)
     * - new_break_minutes (int)
     * - notes (string)
     *
     * @return void Outputs JSON response
     */
    public function create(): void
    {
        $this->requireAuth();
        $this->requirePost();
        $this->verifyCsrf();

        try {
            // Validate input
            $data = $this->validateInput([
                'staff_id' => ['required', 'integer'],
                'pay_period_id' => ['required', 'integer'],
                'original_start' => ['required', 'datetime'],
                'original_end' => ['required', 'datetime'],
                'new_start' => ['required', 'datetime'],
                'new_end' => ['required', 'datetime'],
                'reason' => ['required', 'string', 'min:10'],
                'deputy_timesheet_id' => ['optional', 'integer'],
                'original_break_minutes' => ['optional', 'integer'],
                'new_break_minutes' => ['optional', 'integer'],
                'notes' => ['optional', 'string']
            ]);

            // Create amendment (will auto-submit to AI)
            $result = $this->amendmentService->createAmendment($data);

            if ($result['success']) {
                $this->logger->info('Amendment created successfully', [
                    'amendment_id' => $result['amendment_id'],
                    'staff_id' => $data['staff_id'],
                    'submitted_by' => $this->getCurrentUserId()
                ]);

                $this->jsonSuccess([
                    'amendment_id' => $result['amendment_id'],
                    'ai_decision_id' => $result['ai_decision_id'] ?? null,
                    'message' => 'Amendment created and submitted for AI review'
                ]);
            } else {
                $this->jsonError('Failed to create amendment', $result['error'] ?? 'Unknown error');
            }

        } catch (\InvalidArgumentException $e) {
            $this->jsonError('Validation failed: ' . $e->getMessage(), [], 400);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create amendment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    /**
     * Get amendment details
     *
     * GET /api/payroll/amendments/:id
     *
     * @param int $id Amendment ID
     * @return void Outputs JSON response
     */
    public function view(int $id): void
    {
        $this->requireAuth();

        try {
            $amendment = $this->amendmentService->getAmendment($id);

            if ($amendment) {
                $this->jsonSuccess(['amendment' => $amendment]);
            } else {
                $this->jsonError('Amendment not found', [], 404);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch amendment', [
                'amendment_id' => $id,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    /**
     * Approve an amendment
     *
     * POST /api/payroll/amendments/:id/approve
     *
     * Optional POST fields:
     * - notes (string) - Manager approval notes
     *
     * @param int $id Amendment ID
     * @return void Outputs JSON response
     */
    public function approve(int $id): void
    {
        $this->requireAuth();
        $this->requirePost();
        $this->verifyCsrf();

        try {
            $data = [
                'approved_by' => $this->getCurrentUserId(),
                'notes' => $_POST['notes'] ?? null
            ];

            $result = $this->amendmentService->approveAmendment($id, $data);

            if ($result['success']) {
                $this->logger->info('Amendment approved', [
                    'amendment_id' => $id,
                    'approved_by' => $data['approved_by']
                ]);

                $this->jsonSuccess([
                    'message' => 'Amendment approved and synced to Deputy',
                    'deputy_synced' => $result['deputy_synced'] ?? false
                ]);
            } else {
                $this->jsonError('Failed to approve amendment', $result['error'] ?? 'Unknown error');
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to approve amendment', [
                'amendment_id' => $id,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    /**
     * Decline an amendment
     *
     * POST /api/payroll/amendments/:id/decline
     *
     * Required POST fields:
     * - reason (string) - Reason for declining
     *
     * @param int $id Amendment ID
     * @return void Outputs JSON response
     */
    public function decline(int $id): void
    {
        $this->requireAuth();
        $this->requirePost();
        $this->verifyCsrf();

        try {
            // Validate reason
            if (empty($_POST['reason'])) {
                $this->jsonError('Decline reason is required', [], 400);
                return;
            }

            $data = [
                'declined_by' => $this->getCurrentUserId(),
                'decline_reason' => trim($_POST['reason']),
                'notes' => $_POST['notes'] ?? null
            ];

            $result = $this->amendmentService->declineAmendment($id, $data);

            if ($result['success']) {
                $this->logger->info('Amendment declined', [
                    'amendment_id' => $id,
                    'declined_by' => $data['declined_by'],
                    'reason' => $data['decline_reason']
                ]);

                $this->jsonSuccess([
                    'message' => 'Amendment declined'
                ]);
            } else {
                $this->jsonError('Failed to decline amendment', $result['error'] ?? 'Unknown error');
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to decline amendment', [
                'amendment_id' => $id,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    /**
     * Get pending amendments
     *
     * GET /api/payroll/amendments/pending
     *
     * Query params:
     * - limit (int, default: 50) - Max amendments to return
     * - staff_id (int, optional) - Filter by staff member
     *
     * @return void Outputs JSON response
     */
    public function pending(): void
    {
        $this->requireAuth();

        try {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $staffId = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : null;

            $amendments = $this->amendmentService->getPendingAmendments($limit);

            // Filter by staff if specified
            if ($staffId) {
                $amendments = array_filter($amendments, function($a) use ($staffId) {
                    return $a['staff_id'] === $staffId;
                });
                $amendments = array_values($amendments); // Re-index
            }

            $this->jsonSuccess('Success', [
                'amendments' => $amendments,
                'count' => count($amendments)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch pending amendments', [
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    /**
     * Get amendment history for a staff member
     *
     * GET /api/payroll/amendments/history
     *
     * Query params:
     * - staff_id (int, required) - Staff member ID
     * - limit (int, default: 100) - Max records to return
     *
     * @return void Outputs JSON response
     */
    public function history(): void
    {
        $this->requireAuth();

        try {
            if (!isset($_GET['staff_id'])) {
                $this->jsonError('staff_id parameter is required', [], 400);
                return;
            }

            $staffId = (int)$_GET['staff_id'];
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

            // Query amendment history
            $sql = "SELECT
                        ah.*,
                        u.full_name as changed_by_name
                    FROM payroll_timesheet_amendment_history ah
                    LEFT JOIN users u ON ah.changed_by = u.id
                    WHERE ah.staff_id = ?
                    ORDER BY ah.created_at DESC
                    LIMIT ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$staffId, $limit]);
            $history = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $this->jsonSuccess('Success', [
                'history' => $history,
                'count' => count($history),
                'staff_id' => $staffId
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch amendment history', [
                'staff_id' => $_GET['staff_id'] ?? null,
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', [], 500);
        }
    }

    /**
     * Get current authenticated user ID
     *
     * @return int User ID
     */
    protected function getCurrentUserId(): ?int
    {
        // Prefer BaseController user resolution; fallback to CIS session
        try {
            return parent::getCurrentUserId();
        } catch (\Throwable $e) {
            // Fallback to common CIS session keys
            if (isset($_SESSION['userID'])) {
                return (int)$_SESSION['userID'];
            }
            if (isset($_SESSION['user_id'])) {
                return (int)$_SESSION['user_id'];
            }
        }
        return null;
    }
}
