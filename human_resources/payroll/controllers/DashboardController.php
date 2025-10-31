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
    private PDO $db;

    public function __construct()
    {
        parent::__construct();

        // Get database connection from global function
        $this->db = getPayrollDb();
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
    private function getAmendmentCounts(bool $isAdmin, int $staffId): array
    {
        $where = $isAdmin ? "1=1" : "staff_id = {$staffId}";

        $stmt = $this->db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined
            FROM payroll_timesheet_amendments
            WHERE {$where}
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get discrepancy counts
     */
    private function getDiscrepancyCounts(bool $isAdmin, int $staffId): array
    {
        $where = $isAdmin ? "1=1" : "staff_id = {$staffId}";

        $stmt = $this->db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'ai_review' THEN 1 ELSE 0 END) as ai_review,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined,
                SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent
            FROM payroll_wage_discrepancies
            WHERE {$where}
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get leave counts
     */
    private function getLeaveCounts(bool $isAdmin, int $staffId): array
    {
        $where = $isAdmin ? "1=1" : "staff_id = {$staffId}";

        $stmt = $this->db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as declined
            FROM leave_requests
            WHERE {$where}
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get bonus counts
     */
    private function getBonusCounts(bool $isAdmin, int $staffId): array
    {
        $where = $isAdmin ? "1=1" : "staff_id = {$staffId}";

        $monthly = $this->db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN approved = 0 THEN 1 ELSE 0 END) as pending,
                SUM(bonus_amount) as total_amount
            FROM monthly_bonuses
            WHERE {$where}
        ")->fetch(PDO::FETCH_ASSOC);

        $vapeDrops = $this->db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN bonus_paid = 0 AND completed = 1 THEN 1 ELSE 0 END) as unpaid
            FROM vape_drops
            WHERE {$where}
        ")->fetch(PDO::FETCH_ASSOC);

        $googleReviews = $this->db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN bonus_paid = 0 THEN 1 ELSE 0 END) as unpaid,
                SUM(final_bonus) as total_bonus
            FROM google_reviews_gamification
            WHERE {$where} AND false_positive = 0
        ")->fetch(PDO::FETCH_ASSOC);

        return [
            'monthly' => $monthly,
            'vape_drops' => $vapeDrops,
            'google_reviews' => $googleReviews
        ];
    }

    /**
     * Get Vend payment counts
     */
    private function getVendPaymentCounts(bool $isAdmin, int $staffId): array
    {
        $where = $isAdmin ? "1=1" : "staff_id = {$staffId}";

        $stmt = $this->db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'ai_review' THEN 1 ELSE 0 END) as ai_review,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(payment_amount) as total_amount
            FROM payroll_vend_payment_requests
            WHERE {$where}
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get automation stats
     */
    private function getAutomationStats(bool $isAdmin): array
    {
        if (!$isAdmin) {
            return ['available' => false];
        }

        $stmt = $this->db->query("
            SELECT
                COUNT(*) as total_decisions,
                SUM(CASE WHEN decision = 'auto_approve' THEN 1 ELSE 0 END) as auto_approved,
                SUM(CASE WHEN decision = 'escalate' THEN 1 ELSE 0 END) as escalated,
                AVG(confidence_score) as avg_confidence
            FROM payroll_ai_decisions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
