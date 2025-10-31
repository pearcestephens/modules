<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Controllers;

use HumanResources\Payroll\Services\BonusService;
use PDO;

/**
 * Bonus Controller
 *
 * Handles bonus management:
 * - Vape drops (automatic from sales)
 * - Google reviews (automatic)
 * - Monthly bonuses (manual entry)
 * - Commission calculations
 * - Approval workflow
 *
 * @package HumanResources\Payroll\Controllers
 */
class BonusController extends BaseController
{
    private BonusService $bonusService;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->bonusService = new BonusService($db);
    }

    /**
     * Get pending bonuses for approval
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
                    mb.*,
                    u.first_name,
                    u.last_name,
                    u.email,
                    creator.first_name as creator_first_name,
                    creator.last_name as creator_last_name
                FROM monthly_bonuses mb
                INNER JOIN users u ON mb.staff_id = u.id
                LEFT JOIN users creator ON mb.created_by = creator.id
                WHERE mb.approved = 0
                " . ($staffId ? "AND mb.staff_id = ?" : "") . "
                ORDER BY mb.created_at DESC
            ");

            if ($staffId) {
                $stmt->execute([$staffId]);
            } else {
                $stmt->execute();
            }

            $bonuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get unpaid bonus summaries for each staff member
            foreach ($bonuses as &$bonus) {
                $bonus['unpaid_summary'] = $this->bonusService->getUnpaidBonusSummary((int)$bonus['staff_id']);
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $bonuses,
                'count' => count($bonuses)
            ]);

        } catch (\Exception $e) {
            $this->log('ERROR', 'Failed to get pending bonuses: ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to retrieve pending bonuses'
            ], 500);
        }
    }    /**
     * Get bonus history
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
                    'error' => 'You can only view your own bonus history'
                ], 403);
                return;
            }

            // Get monthly bonuses
            $params = [];
            $where = "1=1";

            if ($staffId) {
                $where .= " AND mb.staff_id = ?";
                $params[] = (int)$staffId;
            }

            $stmt = $this->db->prepare("
                SELECT
                    mb.*,
                    u.first_name,
                    u.last_name,
                    u.email,
                    creator.first_name as creator_first_name,
                    creator.last_name as creator_last_name,
                    approver.first_name as approver_first_name,
                    approver.last_name as approver_last_name
                FROM monthly_bonuses mb
                INNER JOIN users u ON mb.staff_id = u.id
                LEFT JOIN users creator ON mb.created_by = creator.id
                LEFT JOIN users approver ON mb.approved_by = approver.id
                WHERE {$where}
                ORDER BY mb.created_at DESC
                LIMIT ? OFFSET ?
            ");

            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);

            $bonuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total
                FROM monthly_bonuses mb
                WHERE {$where}
            ");
            $countStmt->execute(array_slice($params, 0, -2));
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            $this->jsonResponse([
                'success' => true,
                'data' => $bonuses,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total
                ]
            ]);

        } catch (\Exception $e) {
            $this->log('ERROR', 'Failed to get bonus history: ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to retrieve bonus history'
            ], 500);
        }
    }

    /**
     * Create manual bonus
     *
     * @return void (outputs JSON)
     */
    public function create(): void
    {
        try {
            $this->requirePermission('payroll.create_bonus');

            $data = $this->getJsonInput();

            // Validate required fields
            $required = ['staff_id', 'bonus_amount', 'bonus_type', 'reason', 'pay_period_start', 'pay_period_end'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $this->jsonResponse([
                        'success' => false,
                        'error' => "Missing required field: {$field}"
                    ], 400);
                    return;
                }
            }

            $staffId = (int)$data['staff_id'];
            $amount = (float)$data['bonus_amount'];
            $type = $data['bonus_type'];
            $reason = $data['reason'];
            $periodStart = $data['pay_period_start'];
            $periodEnd = $data['pay_period_end'];
            $createdBy = $this->getCurrentUserId();

            // Validate amount
            if ($amount <= 0) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Bonus amount must be greater than zero'
                ], 400);
                return;
            }

            // Validate type
            $validTypes = ['performance', 'one_off', 'commission', 'referral', 'other'];
            if (!in_array($type, $validTypes)) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Invalid bonus type'
                ], 400);
                return;
            }

            // Insert into monthly_bonuses table
            $stmt = $this->db->prepare("
                INSERT INTO monthly_bonuses (
                    staff_id, bonus_type, bonus_amount,
                    pay_period_start, pay_period_end, reason,
                    approved, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 0, ?, NOW())
            ");

            $stmt->execute([
                $staffId, $type, $amount,
                $periodStart, $periodEnd, $reason,
                $createdBy
            ]);

            $bonusId = (int)$this->db->lastInsertId();

            $this->log('INFO', "Created bonus #{$bonusId} for staff #{$staffId}: \${$amount} ({$type})");

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'bonus_id' => $bonusId,
                    'staff_id' => $staffId,
                    'bonus_amount' => $amount,
                    'bonus_type' => $type,
                    'status' => 'pending'
                ],
                'message' => 'Bonus created successfully and pending approval'
            ], 201);

        } catch (\Exception $e) {
            $this->log('ERROR', 'Failed to create bonus: ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to create bonus'
            ], 500);
        }
    }        /**
     * Approve bonus
     *
     * @param int $id Bonus ID from route parameter
     * @return void (outputs JSON)
     */
    public function approve(int $id): void
    {
        try {
            $this->requirePermission('payroll.approve_bonus');

            $approvedBy = $this->getCurrentUserId();

            $stmt = $this->db->prepare("
                UPDATE monthly_bonuses
                SET approved = 1,
                    approved_by = ?,
                    approved_at = NOW()
                WHERE id = ?
                AND approved = 0
            ");

            $stmt->execute([$approvedBy, $id]);

            if ($stmt->rowCount() === 0) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Bonus not found or already approved'
                ], 404);
                return;
            }

            $this->log('INFO', "Approved bonus #{$id}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Bonus approved successfully'
            ]);

        } catch (\Exception $e) {
            $this->log('ERROR', "Failed to approve bonus #{$id}: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to approve bonus'
            ], 500);
        }
    }

    /**
     * Decline bonus
     *
     * @param int $id Bonus ID from route parameter
     * @return void (outputs JSON)
     */
    public function decline(int $id): void
    {
        try {
            $this->requirePermission('payroll.approve_bonus');

            $data = $this->getJsonInput();
            $reason = $data['reason'] ?? 'No reason provided';
            $declinedBy = $this->getCurrentUserId();

            $stmt = $this->db->prepare("
                UPDATE monthly_bonuses
                SET status = 'declined',
                    approved_by = ?,
                    approved_at = NOW(),
                    notes = CONCAT(COALESCE(notes, ''), '\nDeclined: ', ?)
                WHERE id = ?
                AND status = 'pending'
            ");

            $stmt->execute([$declinedBy, $reason, $id]);

            if ($stmt->rowCount() === 0) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Bonus not found or already processed'
                ], 404);
                return;
            }

            $this->log('INFO', "Declined bonus #{$id}: {$reason}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Bonus declined successfully'
            ]);

        } catch (\Exception $e) {
            $this->log('ERROR', "Failed to decline bonus #{$id}: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to decline bonus'
            ], 500);
        }
    }

    /**
     * Get staff bonus summary
     *
     * @return void (outputs JSON)
     */
    public function getSummary(): void
    {
        try {
            $staffId = $_GET['staff_id'] ?? null;

            // Permission check
            $isAdmin = $this->hasPermission('payroll.admin');
            if ($staffId && !$isAdmin && (int)$staffId !== $this->getCurrentUserId()) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'You can only view your own bonus summary'
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

            $summary = $this->bonusService->getUnpaidBonusSummary((int)$staffId);

            $this->jsonResponse([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            $this->log('ERROR', 'Failed to get bonus summary: ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to retrieve bonus summary'
            ], 500);
        }
    }

    /**
     * Get vape drops for period
     *
     * @return void (outputs JSON)
     */
    public function getVapeDrops(): void
    {
        try {
            $staffId = $_GET['staff_id'] ?? null;
            $periodStart = $_GET['period_start'] ?? null;
            $periodEnd = $_GET['period_end'] ?? null;

            if (!$staffId || !$periodStart || !$periodEnd) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'staff_id, period_start, and period_end are required'
                ], 400);
                return;
            }

            // Permission check
            $isAdmin = $this->hasPermission('payroll.admin');
            if (!$isAdmin && (int)$staffId !== $this->getCurrentUserId()) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'You can only view your own vape drops'
                ], 403);
                return;
            }

            $stmt = $this->db->prepare("
                SELECT
                    vd.*,
                    u.first_name,
                    u.last_name
                FROM vape_drops vd
                INNER JOIN users u ON vd.staff_id = u.id
                WHERE vd.staff_id = ?
                AND vd.completed_at BETWEEN ? AND ?
                AND vd.completed = 1
                ORDER BY vd.completed_at DESC
            ");

            $stmt->execute([(int)$staffId, $periodStart, $periodEnd]);
            $drops = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Count unpaid drops
            $unpaidCount = 0;
            foreach ($drops as $drop) {
                if (!$drop['bonus_paid']) {
                    $unpaidCount++;
                }
            }

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'drops' => $drops,
                    'total_count' => count($drops),
                    'unpaid_count' => $unpaidCount,
                    'rate_per_drop' => 6.00 // From xero-payruns.php
                ]
            ]);

        } catch (\Exception $e) {
            $this->log('ERROR', 'Failed to get vape drops: ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to retrieve vape drops'
            ], 500);
        }
    }

    /**
     * Get Google review bonuses for period
     *
     * @return void (outputs JSON)
     */
    public function getGoogleReviews(): void
    {
        try {
            $staffId = $_GET['staff_id'] ?? null;
            $periodStart = $_GET['period_start'] ?? null;
            $periodEnd = $_GET['period_end'] ?? null;

            if (!$staffId || !$periodStart || !$periodEnd) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'staff_id, period_start, and period_end are required'
                ], 400);
                return;
            }

            // Permission check
            $isAdmin = $this->hasPermission('payroll.admin');
            if (!$isAdmin && (int)$staffId !== $this->getCurrentUserId()) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'You can only view your own Google review bonuses'
                ], 403);
                return;
            }

            $stmt = $this->db->prepare("
                SELECT
                    grg.*,
                    u.first_name,
                    u.last_name,
                    gr.reviewer_name,
                    gr.star_rating as review_star_rating,
                    gr.comment as review_comment
                FROM google_reviews_gamification grg
                INNER JOIN users u ON grg.staff_id = u.id
                LEFT JOIN google_reviews gr ON grg.review_id = gr.id
                WHERE grg.staff_id = ?
                AND grg.processed_at BETWEEN ? AND ?
                AND grg.false_positive = 0
                ORDER BY grg.processed_at DESC
            ");

            $stmt->execute([(int)$staffId, $periodStart, $periodEnd]);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate totals
            $totalBonus = 0;
            $unpaidCount = 0;
            foreach ($reviews as $review) {
                $totalBonus += (float)$review['final_bonus'];
                if (!$review['bonus_paid']) {
                    $unpaidCount++;
                }
            }

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'reviews' => $reviews,
                    'total_count' => count($reviews),
                    'unpaid_count' => $unpaidCount,
                    'total_bonus' => round($totalBonus, 2)
                ]
            ]);

        } catch (\Exception $e) {
            $this->log('ERROR', 'Failed to get Google review bonuses: ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to retrieve Google review bonuses'
            ], 500);
        }
    }
}
