<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Controllers;

use PDO;

/**
 * Dashboard Controller
 *
 * Main payroll dashboard with all sections:
 * - Timesheet Amendments
 * - Wage Discrepancies
 * - Leave Requests
 * - Bonuses (vape drops, Google reviews, monthly)
 * - Vend Account Payments
 *
 * @package HumanResources\Payroll\Controllers
 */
class DashboardController extends BaseController
{
    private ?PDO $db = null;

    public function __construct()
    {
        parent::__construct();

        // Lazy: don't hard-fail if DB env is missing; defer to method-level guards
        try {
            $this->db = getPayrollDb();
        } catch (\Throwable $e) {
            $this->db = null;
        }
    }

    /**
     * Display main dashboard
     *
     * @return void (renders view)
     */
    public function index(): void
    {
        // Check if user is authenticated first
        if (empty($_SESSION['authenticated']) || empty($_SESSION['userID'])) {
            header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
            exit;
        }

        // Check if user has payroll access
        if (!$this->hasPermission('payroll.view_dashboard')) {
            http_response_code(403);
            echo '<!DOCTYPE html><html><head><title>Access Denied</title></head><body>';
            echo '<h1>403 - Access Denied</h1>';
            echo '<p>You do not have permission to access the payroll dashboard.</p>';
            echo '<p><a href="/">Return to Home</a></p>';
            echo '</body></html>';
            exit;
        }

        $isAdmin = $this->hasPermission('payroll.admin');
        $currentUserId = $this->getCurrentUserId();

        // Load the dashboard view
        require_once __DIR__ . '/../views/dashboard.php';
    }

    /**
     * Get aggregated dashboard data via API
     *
     * @return void (outputs JSON)
     */
    public function getData(): void
    {
        try {
            $isAdmin = $this->hasPermission('payroll.admin');
            $staffId = $this->getCurrentUserId();

            // Get counts for all sections
            $data = [
                'amendments' => $this->getAmendmentCounts($isAdmin, $staffId),
                'discrepancies' => $this->getDiscrepancyCounts($isAdmin, $staffId),
                'leave' => $this->getLeaveCounts($isAdmin, $staffId),
                'bonuses' => $this->getBonusCounts($isAdmin, $staffId),
                'vend_payments' => $this->getVendPaymentCounts($isAdmin, $staffId),
                'automation' => $this->getAutomationStats($isAdmin)
            ];

            $this->jsonResponse([
                'success' => true,
                'data' => $data,
                'is_admin' => $isAdmin,
                'staff_id' => $staffId
            ]);

        } catch (\Exception $e) {
            $this->log('ERROR', 'Failed to get dashboard data: ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to retrieve dashboard data'
            ], 500);
        }
    }

    /**
     * Get amendment counts
     */
    private function getAmendmentCounts(bool $isAdmin, ?int $staffId): array
    {
        $defaults = ['total' => 0, 'pending' => 0, 'approved' => 0, 'declined' => 0];
        if (!$this->tableExists('payroll_timesheet_amendments')) {
            return $defaults;
        }
        $where = $isAdmin ? "1=1" : ($staffId !== null ? "staff_id = " . (int)$staffId : "1=0");
        $sql = "
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined
            FROM payroll_timesheet_amendments
            WHERE {$where}
        ";
        return $this->safeQueryFetchAssoc($sql, $defaults);
    }

    /**
     * Get discrepancy counts
     */
    private function getDiscrepancyCounts(bool $isAdmin, ?int $staffId): array
    {
        $defaults = ['total' => 0, 'pending' => 0, 'ai_review' => 0, 'approved' => 0, 'declined' => 0, 'urgent' => 0];
        if (!$this->tableExists('payroll_wage_discrepancies')) {
            return $defaults;
        }
        $where = $isAdmin ? "1=1" : ($staffId !== null ? "staff_id = " . (int)$staffId : "1=0");
        $sql = "
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'ai_review' THEN 1 ELSE 0 END) as ai_review,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined,
                SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent
            FROM payroll_wage_discrepancies
            WHERE {$where}
        ";
        return $this->safeQueryFetchAssoc($sql, $defaults);
    }

    /**
     * Get leave counts
     */
    private function getLeaveCounts(bool $isAdmin, ?int $staffId): array
    {
        $defaults = ['total' => 0, 'pending' => 0, 'approved' => 0, 'declined' => 0];
        if (!$this->tableExists('leave_requests')) {
            return $defaults;
        }
        $where = $isAdmin ? "1=1" : ($staffId !== null ? "staff_id = " . (int)$staffId : "1=0");
        $sql = "
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as declined
            FROM leave_requests
            WHERE {$where}
        ";
        return $this->safeQueryFetchAssoc($sql, $defaults);
    }

    /**
     * Get bonus counts
     */
    private function getBonusCounts(bool $isAdmin, ?int $staffId): array
    {
        $where = $isAdmin ? "1=1" : ($staffId !== null ? "staff_id = " . (int)$staffId : "1=0");

        $monthlyDefaults = ['total' => 0, 'pending' => 0, 'total_amount' => 0];
        $vapeDefaults = ['total' => 0, 'unpaid' => 0];
        $googleDefaults = ['total' => 0, 'unpaid' => 0, 'total_bonus' => 0];

        $monthly = $this->tableExists('monthly_bonuses')
            ? $this->safeQueryFetchAssoc("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN approved = 0 THEN 1 ELSE 0 END) as pending,
                    SUM(bonus_amount) as total_amount
                FROM monthly_bonuses
                WHERE {$where}
            ", $monthlyDefaults)
            : $monthlyDefaults;

        $vapeDrops = $this->tableExists('vape_drops')
            ? $this->safeQueryFetchAssoc("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN bonus_paid = 0 AND completed = 1 THEN 1 ELSE 0 END) as unpaid
                FROM vape_drops
                WHERE {$where}
            ", $vapeDefaults)
            : $vapeDefaults;

        $googleReviews = $this->tableExists('google_reviews_gamification')
            ? $this->safeQueryFetchAssoc("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN bonus_paid = 0 THEN 1 ELSE 0 END) as unpaid,
                    SUM(final_bonus) as total_bonus
                FROM google_reviews_gamification
                WHERE {$where} AND false_positive = 0
            ", $googleDefaults)
            : $googleDefaults;

        return [
            'monthly' => $monthly,
            'vape_drops' => $vapeDrops,
            'google_reviews' => $googleReviews
        ];
    }

    /**
     * Get Vend payment counts
     */
    private function getVendPaymentCounts(bool $isAdmin, ?int $staffId): array
    {
        $defaults = ['total' => 0, 'pending' => 0, 'ai_review' => 0, 'approved' => 0, 'completed' => 0, 'total_amount' => 0];
        if (!$this->tableExists('payroll_vend_payment_requests')) {
            return $defaults;
        }
        $where = $isAdmin ? "1=1" : ($staffId !== null ? "staff_id = " . (int)$staffId : "1=0");
        $sql = "
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'ai_review' THEN 1 ELSE 0 END) as ai_review,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(payment_amount) as total_amount
            FROM payroll_vend_payment_requests
            WHERE {$where}
        ";
        return $this->safeQueryFetchAssoc($sql, $defaults);
    }

    /**
     * Get automation stats
     */
    private function getAutomationStats(bool $isAdmin): array
    {
        if (!$isAdmin) {
            return ['available' => false];
        }
        $defaults = [
            'total_decisions' => 0,
            'auto_approved' => 0,
            'escalated' => 0,
            'avg_confidence' => 0,
        ];
        if (!$this->tableExists('payroll_ai_decisions')) {
            return $defaults;
        }
        $sql = "
            SELECT
                COUNT(*) as total_decisions,
                SUM(CASE WHEN decision = 'auto_approve' THEN 1 ELSE 0 END) as auto_approved,
                SUM(CASE WHEN decision = 'escalate' THEN 1 ELSE 0 END) as escalated,
                AVG(confidence_score) as avg_confidence
            FROM payroll_ai_decisions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ";
        return $this->safeQueryFetchAssoc($sql, $defaults);
    }

    /**
     * Check if a table exists in the current database
     */
    private function tableExists(string $tableName): bool
    {
        if (!$this->db instanceof PDO) {
            return false;
        }
        try {
            $dbName = $this->db->query('SELECT DATABASE()')->fetchColumn();
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :db AND table_name = :tbl');
            $stmt->execute([':db' => $dbName, ':tbl' => $tableName]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Execute a query and fetch assoc safely, returning defaults on failure
     */
    private function safeQueryFetchAssoc(string $sql, array $defaults): array
    {
        if (!$this->db instanceof PDO) {
            return $defaults;
        }
        try {
            $stmt = $this->db->query($sql);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return $defaults;
            }
            // Ensure all default keys exist
            foreach ($defaults as $k => $v) {
                if (!array_key_exists($k, $row)) {
                    $row[$k] = $v;
                }
            }
            return $row;
        } catch (\Throwable $e) {
            return $defaults;
        }
    }
}
