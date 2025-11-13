<?php
declare(strict_types=1);

namespace PayrollModule\Services;

use PDO;

/**
 * Bonus Service
 *
 * Handles all bonus types for payroll:
 * - Vape drop bonuses ($6-7 per delivery)
 * - Google review bonuses ($10 per verified review)
 * - Monthly bonuses (performance, sales targets, discretionary)
 * - Commission (Adam - user ID 5)
 * - Acting position pay (user ID 58 - $3/hour)
 * - Gamification bonuses (future)
 *
 * Based on xero-payruns.php bonus logic
 *
 * @package PayrollModule\Services
 * @version 1.0.0
 */
class BonusService
{
    private PDO $db;

    // Bonus rates (from xero-payruns.php)
    private const VAPE_DROP_RATE = 6.0;  // Can be 6-7 depending on distance
    private const GOOGLE_REVIEW_BONUS = 10.0;
    private const ACTING_POSITION_RATE = 3.0; // Per hour

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get all bonuses for staff member in pay period
     *
     * @param int $staffId Staff member ID
     * @param string $periodStart Period start date
     * @param string $periodEnd Period end date
     * @return array Bonus amounts by type
     */
    public function getBonusesForPeriod(int $staffId, string $periodStart, string $periodEnd): array
    {
        $bonuses = [
            'vape_drops' => $this->getVapeDropBonus($staffId, $periodStart, $periodEnd),
            'google_reviews' => $this->getGoogleReviewBonus($staffId, $periodStart, $periodEnd),
            'monthly' => $this->getMonthlyBonus($staffId, $periodStart, $periodEnd),
            'commission' => 0.0, // Set by PayslipService for user ID 5
            'acting_position' => 0.0, // Set by PayslipService for user ID 58
            'gamification' => 0.0 // Future implementation
        ];

        return $bonuses;
    }

    /**
     * Calculate vape drop bonus for period
     *
     * Fetches completed, unpaid vape drops and calculates bonus
     * Marks them as paid in the payslip
     */
    private function getVapeDropBonus(int $staffId, string $periodStart, string $periodEnd): float
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as drop_count
            FROM vape_drops
            WHERE staff_id = ?
            AND completed = 1
            AND bonus_paid = 0
            AND completed_at BETWEEN ? AND ?
        ");
        $stmt->execute([$staffId, $periodStart, $periodEnd]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $dropCount = (int)($result['drop_count'] ?? 0);

        if ($dropCount === 0) {
            return 0.0;
        }

        // Calculate bonus ($6 per drop - can be adjusted to $7 for far deliveries)
        return $dropCount * self::VAPE_DROP_RATE;
    }

    /**
     * Calculate Google review bonus for period
     *
     * Fetches verified, unpaid Google reviews and calculates bonus
     */
    private function getGoogleReviewBonus(int $staffId, string $periodStart, string $periodEnd): float
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as review_count
            FROM google_reviews
            WHERE staff_id = ?
            AND verified = 1
            AND bonus_paid = 0
            AND review_date BETWEEN ? AND ?
            AND rating >= 4
        ");
        $stmt->execute([$staffId, $periodStart, $periodEnd]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $reviewCount = (int)($result['review_count'] ?? 0);

        if ($reviewCount === 0) {
            return 0.0;
        }

        // $10 per verified review (4+ stars)
        return $reviewCount * self::GOOGLE_REVIEW_BONUS;
    }

    /**
     * Get monthly bonuses for staff member
     *
     * Includes:
     * - Performance bonuses
     * - Sales target bonuses
     * - Manager discretionary bonuses
     */
    private function getMonthlyBonus(int $staffId, string $periodStart, string $periodEnd): float
    {
        $stmt = $this->db->prepare("
            SELECT SUM(bonus_amount) as total_bonus
            FROM monthly_bonuses
            WHERE staff_id = ?
            AND approved = 1
            AND paid_in_payslip_id IS NULL
            AND pay_period_start <= ?
            AND pay_period_end >= ?
        ");
        $stmt->execute([$staffId, $periodEnd, $periodStart]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (float)($result['total_bonus'] ?? 0.0);
    }

    /**
     * Mark vape drops as paid in payslip
     */
    public function markVapeDropsAsPaid(int $staffId, int $payslipId, string $periodStart, string $periodEnd): int
    {
        $stmt = $this->db->prepare("
            UPDATE vape_drops
            SET bonus_paid = 1,
                bonus_paid_in_payslip_id = ?
            WHERE staff_id = ?
            AND completed = 1
            AND bonus_paid = 0
            AND completed_at BETWEEN ? AND ?
        ");
        $stmt->execute([$payslipId, $staffId, $periodStart, $periodEnd]);

        return $stmt->rowCount();
    }

    /**
     * Mark Google reviews as paid in payslip
     */
    public function markGoogleReviewsAsPaid(int $staffId, int $payslipId, string $periodStart, string $periodEnd): int
    {
        $stmt = $this->db->prepare("
            UPDATE google_reviews
            SET bonus_paid = 1,
                bonus_paid_in_payslip_id = ?
            WHERE staff_id = ?
            AND verified = 1
            AND bonus_paid = 0
            AND review_date BETWEEN ? AND ?
            AND rating >= 4
        ");
        $stmt->execute([$payslipId, $staffId, $periodStart, $periodEnd]);

        return $stmt->rowCount();
    }

    /**
     * Mark monthly bonuses as paid in payslip
     */
    public function markMonthlyBonusesAsPaid(int $staffId, int $payslipId, string $periodStart, string $periodEnd): int
    {
        $stmt = $this->db->prepare("
            UPDATE monthly_bonuses
            SET paid_in_payslip_id = ?
            WHERE staff_id = ?
            AND approved = 1
            AND paid_in_payslip_id IS NULL
            AND pay_period_start <= ?
            AND pay_period_end >= ?
        ");
        $stmt->execute([$payslipId, $staffId, $periodEnd, $periodStart]);

        return $stmt->rowCount();
    }

    /**
     * Mark all bonuses as paid (called after payslip finalized)
     */
    public function markAllBonusesAsPaid(int $staffId, int $payslipId, string $periodStart, string $periodEnd): array
    {
        return [
            'vape_drops' => $this->markVapeDropsAsPaid($staffId, $payslipId, $periodStart, $periodEnd),
            'google_reviews' => $this->markGoogleReviewsAsPaid($staffId, $payslipId, $periodStart, $periodEnd),
            'monthly_bonuses' => $this->markMonthlyBonusesAsPaid($staffId, $payslipId, $periodStart, $periodEnd)
        ];
    }

    /**
     * Create a new monthly bonus
     */
    public function createMonthlyBonus(int $staffId, float $amount, string $type, string $reason, int $createdBy, string $periodStart, string $periodEnd): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO monthly_bonuses
            (staff_id, bonus_type, bonus_amount, pay_period_start, pay_period_end, reason, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$staffId, $type, $amount, $periodStart, $periodEnd, $reason, $createdBy]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Approve a monthly bonus
     */
    public function approveMonthlyBonus(int $bonusId, int $approvedBy): bool
    {
        $stmt = $this->db->prepare("
            UPDATE monthly_bonuses
            SET approved = 1,
                approved_by = ?,
                approved_at = NOW()
            WHERE id = ?
            AND approved = 0
        ");

        return $stmt->execute([$approvedBy, $bonusId]);
    }

    /**
     * Get unpaid bonus summary for staff member
     */
    public function getUnpaidBonusSummary(int $staffId): array
    {
        $summary = [
            'vape_drops' => 0,
            'google_reviews' => 0,
            'monthly_bonuses' => 0,
            'total_amount' => 0.0
        ];

        try {
            // Vape drops - graceful fallback if table doesn't exist
            try {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as count
                    FROM vape_drops
                    WHERE staff_id = ? AND completed = 1 AND bonus_paid = 0
                ");
                $stmt->execute([$staffId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $summary['vape_drops'] = (int)($result['count'] ?? 0);
            } catch (\PDOException $e) {
                // Table doesn't exist yet - return 0
                $summary['vape_drops'] = 0;
            }

            // Google reviews
            // TODO: google_reviews uses staff_mentions JSON field, not staff_id
            // Need to implement JSON_CONTAINS or similar query
            // For now, return 0 to prevent SQL errors
            $summary['google_reviews'] = 0;

            // Monthly bonuses - graceful fallback if table doesn't exist
            try {
                $stmt = $this->db->prepare("
                    SELECT SUM(bonus_amount) as total
                    FROM monthly_bonuses
                    WHERE staff_id = ? AND approved = 1 AND paid_in_payslip_id IS NULL
                ");
                $stmt->execute([$staffId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $summary['monthly_bonuses'] = (float)($result['total'] ?? 0.0);
            } catch (\PDOException $e) {
                // Table doesn't exist yet - return 0
                $summary['monthly_bonuses'] = 0.0;
            }

            // Calculate total
            $summary['total_amount'] =
                ($summary['vape_drops'] * self::VAPE_DROP_RATE) +
                ($summary['google_reviews'] * self::GOOGLE_REVIEW_BONUS) +
                $summary['monthly_bonuses'];

        } catch (\Exception $e) {
            // Any other error - return empty summary
            error_log('BonusService::getUnpaidBonusSummary error: ' . $e->getMessage());
        }

        return $summary;
    }
}
