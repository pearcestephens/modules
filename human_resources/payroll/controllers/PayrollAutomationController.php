<?php
declare(strict_types=1);

namespace PayrollModule\Controllers;

/**
 * Payroll Automation Controller
 *
 * HTTP API endpoints for AI automation:
 * - GET /api/payroll/automation/dashboard
 * - GET /api/payroll/automation/reviews/pending
 * - POST /api/payroll/automation/process (manual trigger)
 * - GET /api/payroll/automation/rules
 * - GET /api/payroll/automation/stats
 *
 * @package PayrollModule\Controllers
 * @version 1.0.0
 */

use PayrollModule\Services\PayrollAutomationService;
use PayrollModule\Lib\PayrollLogger;

class PayrollAutomationController extends BaseController
{
    private PayrollAutomationService $automationService;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->automationService = new PayrollAutomationService();
    }

    /**
     * Get automation dashboard data
     *
     * GET /api/payroll/automation/dashboard
     *
     * Returns:
     * - Pending review count
     * - Auto-approval rate
     * - Average processing time
     * - Recent decisions
     * - Rule execution stats
     *
     * @return void Outputs JSON response
     */
    public function dashboard(): void
    {
        $this->requireAuth();

        try {
            $stats = $this->automationService->getDashboardStats();

            // Add additional dashboard data
            $sql = "SELECT
                        DATE(created_at) as date,
                        COUNT(*) as total,
                        SUM(CASE WHEN decision = 'approved' THEN 1 ELSE 0 END) as approved,
                        SUM(CASE WHEN decision = 'declined' THEN 1 ELSE 0 END) as declined,
                        SUM(CASE WHEN decision = 'manual_review' THEN 1 ELSE 0 END) as manual_review
                    FROM payroll_ai_decisions
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY date DESC";

            $stmt = $this->automationService->query($sql);
            $dailyStats = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $this->jsonSuccess([
                'stats' => $stats,
                'daily_stats' => $dailyStats,
                'timestamp' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch dashboard data', [
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', null, 500);
        }
    }

    /**
     * Get pending AI reviews
     *
     * GET /api/payroll/automation/reviews/pending
     *
     * Query params:
     * - limit (int, default: 50)
     * - entity_type (string, optional) - Filter by entity type
     *
     * @return void Outputs JSON response
     */
    public function pendingReviews(): void
    {
        $this->requireAuth();

        try {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $entityType = $_GET['entity_type'] ?? null;

            $sql = "SELECT
                        ad.*,
                        CASE
                            WHEN ad.entity_type = 'timesheet_amendment' THEN
                                CONCAT(ps.first_name, ' ', ps.last_name)
                            ELSE NULL
                        END as staff_name,
                        CASE
                            WHEN ad.entity_type = 'timesheet_amendment' THEN
                                ta.reason
                            ELSE NULL
                        END as entity_reason
                    FROM payroll_ai_decisions ad
                    LEFT JOIN payroll_timesheet_amendments ta
                        ON ad.entity_type = 'timesheet_amendment'
                        AND ad.entity_id = ta.id
                    LEFT JOIN payroll_staff ps ON ta.staff_id = ps.id
                    WHERE ad.status = 'pending'";

            $params = [];

            if ($entityType) {
                $sql .= " AND ad.entity_type = ?";
                $params[] = $entityType;
            }

            $sql .= " ORDER BY ad.priority DESC, ad.created_at ASC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->automationService->query($sql, $params);
            $reviews = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $this->jsonSuccess([
                'reviews' => $reviews,
                'count' => count($reviews)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch pending reviews', [
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', null, 500);
        }
    }

    /**
     * Manually trigger automation processing
     *
     * POST /api/payroll/automation/process
     *
     * Note: This is normally run via cron, but can be manually triggered
     * Requires admin permission
     *
     * @return void Outputs JSON response
     */
    public function processNow(): void
    {
        $this->requireAuth();
        $this->requirePost();
        $this->verifyCsrf();

        // Check if user has admin permission
        if (!$this->isAdmin()) {
            $this->jsonError('Admin permission required', null, 403);
            return;
        }

        try {
            $this->logger->info('Manual automation processing triggered', [
                'triggered_by' => $this->getCurrentUserId()
            ]);

            $result = $this->automationService->processAutomatedReviews();

            $this->logger->info('Manual automation processing completed', [
                'result' => $result,
                'triggered_by' => $this->getCurrentUserId()
            ]);

            $this->jsonSuccess([
                'message' => 'Automation processing completed',
                'results' => $result
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to process automation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->jsonError('Internal server error', null, 500);
        }
    }

    /**
     * Get active AI rules
     *
     * GET /api/payroll/automation/rules
     *
     * Query params:
     * - entity_type (string, optional) - Filter by entity type
     * - active_only (bool, default: true) - Only show active rules
     *
     * @return void Outputs JSON response
     */
    public function rules(): void
    {
        $this->requireAuth();

        try {
            $entityType = $_GET['entity_type'] ?? null;
            $activeOnly = !isset($_GET['active_only']) || $_GET['active_only'] !== 'false';

            $sql = "SELECT * FROM payroll_ai_rules WHERE 1=1";
            $params = [];

            if ($activeOnly) {
                $sql .= " AND is_active = 1";
            }

            if ($entityType) {
                $sql .= " AND entity_type = ?";
                $params[] = $entityType;
            }

            $sql .= " ORDER BY priority DESC, created_at DESC";

            $stmt = $this->automationService->query($sql, $params);
            $rules = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Decode JSON fields
            foreach ($rules as &$rule) {
                $rule['conditions'] = json_decode($rule['conditions'], true);
                $rule['actions'] = json_decode($rule['actions'], true);
            }

            $this->jsonSuccess([
                'rules' => $rules,
                'count' => count($rules)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch AI rules', [
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', null, 500);
        }
    }

    /**
     * Get automation statistics
     *
     * GET /api/payroll/automation/stats
     *
     * Query params:
     * - period (string) - 'today', 'week', 'month' (default: 'week')
     *
     * @return void Outputs JSON response
     */
    public function stats(): void
    {
        $this->requireAuth();

        try {
            $period = $_GET['period'] ?? 'week';

            $dateCondition = match($period) {
                'today' => "DATE(ad.created_at) = CURDATE()",
                'month' => "ad.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
                default => "ad.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)"
            };

            $sql = "SELECT
                        COUNT(*) as total_decisions,
                        SUM(CASE WHEN decision = 'approved' THEN 1 ELSE 0 END) as auto_approved,
                        SUM(CASE WHEN decision = 'declined' THEN 1 ELSE 0 END) as auto_declined,
                        SUM(CASE WHEN decision = 'manual_review' THEN 1 ELSE 0 END) as manual_review,
                        SUM(CASE WHEN decision = 'escalate' THEN 1 ELSE 0 END) as escalated,
                        AVG(confidence_score) as avg_confidence,
                        AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as avg_processing_seconds
                    FROM payroll_ai_decisions ad
                    WHERE {$dateCondition}
                    AND status = 'completed'";

            $stmt = $this->automationService->query($sql);
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Get rule execution stats
            $sql = "SELECT
                        r.rule_name,
                        COUNT(*) as execution_count,
                        SUM(CASE WHEN re.passed THEN 1 ELSE 0 END) as passed_count,
                        AVG(re.confidence_adjustment) as avg_confidence_adjustment
                    FROM payroll_ai_rule_executions re
                    JOIN payroll_ai_rules r ON re.rule_id = r.id
                    JOIN payroll_ai_decisions ad ON re.ai_decision_id = ad.id
                    WHERE {$dateCondition}
                    GROUP BY r.id, r.rule_name
                    ORDER BY execution_count DESC
                    LIMIT 10";

            $stmt = $this->automationService->query($sql);
            $ruleStats = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $this->jsonSuccess([
                'period' => $period,
                'overall' => $stats,
                'top_rules' => $ruleStats,
                'timestamp' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch automation stats', [
                'error' => $e->getMessage()
            ]);
            $this->jsonError('Internal server error', null, 500);
        }
    }

    /**
     * Check if current user is admin
     *
     * @return bool
     */
    private function isAdmin(): bool
    {
        // TODO: Implement based on your auth system
        return $_SESSION['is_admin'] ?? false;
    }

    /**
     * Get current authenticated user ID
     *
     * @return int User ID
     */
    private function getCurrentUserId(): int
    {
        // TODO: Implement based on your auth system
        return $_SESSION['user_id'] ?? 0;
    }
}
