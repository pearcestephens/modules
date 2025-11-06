<?php
/**
 * Google Reviews Gamification Service
 *
 * Processes Google Reviews and awards bonuses
 *
 * @package CIS\Modules\StaffPerformance
 * @version 1.0.0
 */

namespace StaffPerformance\Services;

class GoogleReviewsGamification {

    private $db;
    private $config;

    // Bonus values
    const INDIVIDUAL_BONUS = 10.00;
    const POINTS_PER_REVIEW = 100;

    public function __construct($db, $config = []) {
        $this->db = $db;
        $this->config = array_merge([
            'enabled' => true,
            'individual_bonus' => self::INDIVIDUAL_BONUS,
            'points_per_mention' => self::POINTS_PER_REVIEW,
            'min_rating' => 4.0
        ], $config);
    }

    /**
     * Process unprocessed Google Reviews
     */
    public function processReviews() {
        if (!$this->config['enabled']) {
            return ['processed' => 0, 'bonuses' => 0];
        }

        try {
            // Get unprocessed reviews
            $stmt = $this->db->query("
                SELECT * FROM google_reviews
                WHERE bonus_processed = 0
                AND rating >= {$this->config['min_rating']}
                ORDER BY review_date ASC
            ");
            $reviews = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $processed = 0;
            $totalBonuses = 0;

            foreach ($reviews as $review) {
                $bonus = $this->processReview($review);
                if ($bonus > 0) {
                    $totalBonuses += $bonus;
                }
                $processed++;
            }

            return [
                'processed' => $processed,
                'bonuses' => $totalBonuses,
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            error_log("GoogleReviewsGamification Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Process a single review
     */
    private function processReview($review) {
        try {
            // Check if staff member mentioned
            if (empty($review['staff_id'])) {
                // Mark as processed but no bonus
                $stmt = $this->db->prepare("
                    UPDATE google_reviews
                    SET bonus_processed = 1, processed_at = NOW()
                    WHERE review_id = ?
                ");
                $stmt->execute([$review['review_id']]);
                return 0;
            }

            // Award individual bonus
            $bonus = $this->config['individual_bonus'];
            $points = $this->config['points_per_mention'];

            // Update review record
            $stmt = $this->db->prepare("
                UPDATE google_reviews
                SET bonus_amount = ?,
                    bonus_processed = 1,
                    processed_at = NOW()
                WHERE review_id = ?
            ");
            $stmt->execute([$bonus, $review['review_id']]);

            // Log achievement progress
            $this->checkAchievements($review['staff_id']);

            // Send notification (if enabled)
            if ($this->config['notification_enabled'] ?? false) {
                $this->sendNotification($review['staff_id'], $bonus, $review);
            }

            return $bonus;

        } catch (\Exception $e) {
            error_log("Process Review Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check and update achievement progress
     */
    private function checkAchievements($staffId) {
        try {
            // Get review count
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM google_reviews
                WHERE staff_id = ? AND bonus_processed = 1
            ");
            $stmt->execute([$staffId]);
            $reviewCount = $stmt->fetchColumn();

            // First Review achievement
            if ($reviewCount == 1) {
                $this->unlockAchievement($staffId, 'first_review');
            }

            // Review milestones
            $milestones = [
                10 => 'review_starter',
                25 => 'review_veteran',
                50 => 'review_champion',
                100 => 'review_legend'
            ];

            foreach ($milestones as $threshold => $achievementKey) {
                if ($reviewCount >= $threshold) {
                    $this->unlockAchievement($staffId, $achievementKey);
                }
            }

            // Check monthly achievements
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM google_reviews
                WHERE staff_id = ?
                AND bonus_processed = 1
                AND review_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
            ");
            $stmt->execute([$staffId]);
            $monthlyCount = $stmt->fetchColumn();

            if ($monthlyCount >= 50) {
                $this->unlockAchievement($staffId, 'monthly_champion');
            }

        } catch (\Exception $e) {
            error_log("Check Achievements Error: " . $e->getMessage());
        }
    }

    /**
     * Unlock an achievement for a staff member
     */
    private function unlockAchievement($staffId, $achievementKey) {
        try {
            // Get achievement ID
            $stmt = $this->db->prepare("
                SELECT achievement_id FROM achievements WHERE achievement_key = ?
            ");
            $stmt->execute([$achievementKey]);
            $achievementId = $stmt->fetchColumn();

            if (!$achievementId) {
                return false;
            }

            // Check if already unlocked
            $stmt = $this->db->prepare("
                SELECT unlocked_at FROM staff_achievements
                WHERE staff_id = ? AND achievement_id = ?
            ");
            $stmt->execute([$staffId, $achievementId]);

            if ($stmt->fetchColumn()) {
                return false; // Already unlocked
            }

            // Unlock it
            $stmt = $this->db->prepare("
                INSERT INTO staff_achievements (staff_id, achievement_id, unlocked_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE unlocked_at = NOW()
            ");
            $stmt->execute([$staffId, $achievementId]);

            return true;

        } catch (\Exception $e) {
            error_log("Unlock Achievement Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to staff member
     */
    private function sendNotification($staffId, $bonus, $review) {
        // TODO: Implement notification system
        // Could send email, SMS, or in-app notification
        error_log("Notification: Staff $staffId earned $$bonus for Google Review");
    }

    /**
     * Get staff member's review stats
     */
    public function getStaffStats($staffId) {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as total_reviews,
                    SUM(bonus_amount) as total_earnings,
                    COUNT(CASE WHEN review_date >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN 1 END) as month_reviews,
                    SUM(CASE WHEN review_date >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN bonus_amount ELSE 0 END) as month_earnings
                FROM google_reviews
                WHERE staff_id = ? AND bonus_processed = 1
            ");
            $stmt->execute([$staffId]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            error_log("Get Staff Stats Error: " . $e->getMessage());
            return null;
        }
    }
}
