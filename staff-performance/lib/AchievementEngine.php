<?php
/**
 * Achievement Engine Service
 *
 * Checks and unlocks achievements based on performance
 *
 * @package CIS\Modules\StaffPerformance
 * @version 1.0.0
 */

namespace StaffPerformance\Services;

class AchievementEngine {

    private $db;
    private $config;

    public function __construct($db, $config = []) {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Check all achievements for a staff member
     */
    public function checkAchievements($staffId) {
        $unlocked = [];

        // Get current stats
        $stats = $this->getStaffStats($staffId);

        // Define achievement criteria
        $criteria = [
            'first_review' => $stats['total_reviews'] >= 1,
            'review_starter' => $stats['total_reviews'] >= 10,
            'review_veteran' => $stats['total_reviews'] >= 25,
            'review_champion' => $stats['total_reviews'] >= 50,
            'review_legend' => $stats['total_reviews'] >= 100,

            'first_drop' => $stats['total_drops'] >= 1,
            'drop_starter' => $stats['total_drops'] >= 25,
            'drop_master' => $stats['total_drops'] >= 100,

            'monthly_champion' => $stats['month_reviews'] >= 50,
            'perfect_month' => $stats['month_reviews'] >= 100,

            'competition_winner' => $this->hasWonCompetition($staffId),
            'triple_crown' => $this->hasTripleCrown($staffId),
            'legend_status' => $this->hasLegendStatus($staffId)
        ];

        // Check each criterion and unlock if met
        foreach ($criteria as $achievementKey => $isMet) {
            if ($isMet && $this->unlockAchievement($staffId, $achievementKey)) {
                $unlocked[] = $achievementKey;
            }
        }

        return $unlocked;
    }

    /**
     * Get staff member's current stats
     */
    private function getStaffStats($staffId) {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    COALESCE(COUNT(DISTINCT gr.review_id), 0) as total_reviews,
                    COALESCE(COUNT(DISTINCT vd.drop_id), 0) as total_drops,
                    COALESCE(COUNT(DISTINCT CASE WHEN gr.review_date >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN gr.review_id END), 0) as month_reviews,
                    COALESCE(COUNT(DISTINCT CASE WHEN vd.drop_date >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN vd.drop_id END), 0) as month_drops
                FROM (SELECT ? as staff_id) s
                LEFT JOIN google_reviews gr ON gr.staff_id = s.staff_id AND gr.bonus_processed = 1
                LEFT JOIN vape_drops vd ON vd.staff_id = s.staff_id
            ");
            $stmt->execute([$staffId]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            error_log("Get Staff Stats Error: " . $e->getMessage());
            return ['total_reviews' => 0, 'total_drops' => 0, 'month_reviews' => 0, 'month_drops' => 0];
        }
    }

    /**
     * Check if staff has won a competition
     */
    private function hasWonCompetition($staffId) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM competition_participants
                WHERE staff_id = ? AND rank = 1 AND prize_awarded = 1
            ");
            $stmt->execute([$staffId]);
            return $stmt->fetchColumn() > 0;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if staff has won all 3 podium positions
     */
    private function hasTripleCrown($staffId) {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT rank FROM competition_participants
                WHERE staff_id = ? AND rank <= 3 AND prize_awarded = 1
            ");
            $stmt->execute([$staffId]);
            $ranks = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            return count($ranks) >= 3; // Has won 1st, 2nd, and 3rd at different times

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if staff has been #1 for 3+ months
     */
    private function hasLegendStatus($staffId) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM leaderboard_history
                WHERE staff_id = ? AND rank = 1 AND period_type = 'monthly'
            ");
            $stmt->execute([$staffId]);
            return $stmt->fetchColumn() >= 3;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Unlock an achievement for a staff member
     */
    private function unlockAchievement($staffId, $achievementKey) {
        try {
            // Get achievement ID
            $stmt = $this->db->prepare("
                SELECT achievement_id, points_value FROM achievements
                WHERE achievement_key = ?
            ");
            $stmt->execute([$achievementKey]);
            $achievement = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$achievement) {
                return false;
            }

            // Check if already unlocked
            $stmt = $this->db->prepare("
                SELECT unlocked_at FROM staff_achievements
                WHERE staff_id = ? AND achievement_id = ?
            ");
            $stmt->execute([$staffId, $achievement['achievement_id']]);

            if ($stmt->fetchColumn()) {
                return false; // Already unlocked
            }

            // Unlock it
            $stmt = $this->db->prepare("
                INSERT INTO staff_achievements (staff_id, achievement_id, unlocked_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$staffId, $achievement['achievement_id']]);

            // Log the unlock
            error_log("Achievement Unlocked: Staff $staffId unlocked $achievementKey ({$achievement['points_value']} points)");

            return true;

        } catch (\Exception $e) {
            error_log("Unlock Achievement Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update achievement progress for a staff member
     */
    public function updateProgress($staffId, $achievementKey, $currentProgress, $totalProgress = 100) {
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

            // Update progress
            $stmt = $this->db->prepare("
                INSERT INTO staff_achievements (staff_id, achievement_id, progress_current, progress_total)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    progress_current = VALUES(progress_current),
                    progress_total = VALUES(progress_total)
            ");

            return $stmt->execute([$staffId, $achievementId, $currentProgress, $totalProgress]);

        } catch (\Exception $e) {
            error_log("Update Progress Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get staff member's achievement summary
     */
    public function getAchievementSummary($staffId) {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as total_achievements,
                    SUM(CASE WHEN sa.unlocked_at IS NOT NULL THEN 1 ELSE 0 END) as unlocked_count,
                    SUM(CASE WHEN sa.unlocked_at IS NOT NULL THEN a.points_value ELSE 0 END) as total_points
                FROM achievements a
                LEFT JOIN staff_achievements sa ON a.achievement_id = sa.achievement_id AND sa.staff_id = ?
            ");
            $stmt->execute([$staffId]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            error_log("Get Achievement Summary Error: " . $e->getMessage());
            return null;
        }
    }
}
