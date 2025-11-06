<?php
/**
 * Staff Performance Tracker Service
 *
 * Aggregates and tracks staff performance metrics
 *
 * @package CIS\Modules\StaffPerformance
 * @version 1.0.0
 */

namespace StaffPerformance\Services;

class StaffPerformanceTracker {

    private $db;
    private $config;

    public function __construct($db, $config = []) {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Update monthly performance stats for all staff
     */
    public function updateMonthlyStats($month = null) {
        if (!$month) {
            $month = date('Y-m');
        }

        try {
            // Get all active staff
            $stmt = $this->db->query("
                SELECT staff_id FROM staff_accounts WHERE is_active = 1
            ");
            $staffList = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            $updated = 0;

            foreach ($staffList as $staffId) {
                if ($this->updateStaffMonthStats($staffId, $month)) {
                    $updated++;
                }
            }

            // Update rankings
            $this->updateRankings($month);

            return [
                'month' => $month,
                'updated' => $updated,
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            error_log("Update Monthly Stats Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Update stats for a single staff member for a month
     */
    private function updateStaffMonthStats($staffId, $month) {
        try {
            // Calculate stats
            $stmt = $this->db->prepare("
                SELECT
                    COALESCE(COUNT(DISTINCT gr.review_id), 0) as review_count,
                    COALESCE(SUM(gr.bonus_amount), 0) as review_earnings,
                    COALESCE(COUNT(DISTINCT vd.drop_id), 0) as drop_count,
                    COALESCE(SUM(CASE WHEN vd.status = 'completed' THEN 6.00 ELSE 0 END), 0) as drop_earnings
                FROM (SELECT ? as staff_id) s
                LEFT JOIN google_reviews gr ON gr.staff_id = s.staff_id
                    AND DATE_FORMAT(gr.review_date, '%Y-%m') = ?
                    AND gr.bonus_processed = 1
                LEFT JOIN vape_drops vd ON vd.staff_id = s.staff_id
                    AND DATE_FORMAT(vd.drop_date, '%Y-%m') = ?
            ");
            $stmt->execute([$staffId, $month, $month]);
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Calculate totals
            $totalPoints = ($stats['review_count'] * 100) + ($stats['drop_count'] * 50);
            $totalEarnings = $stats['review_earnings'] + $stats['drop_earnings'];

            // Upsert into stats table
            $stmt = $this->db->prepare("
                INSERT INTO staff_performance_stats (
                    staff_id, month_year,
                    google_reviews_count, google_reviews_earnings,
                    vape_drops_count, vape_drops_earnings,
                    total_points, total_earnings,
                    calculated_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    google_reviews_count = VALUES(google_reviews_count),
                    google_reviews_earnings = VALUES(google_reviews_earnings),
                    vape_drops_count = VALUES(vape_drops_count),
                    vape_drops_earnings = VALUES(vape_drops_earnings),
                    total_points = VALUES(total_points),
                    total_earnings = VALUES(total_earnings),
                    updated_at = NOW()
            ");

            return $stmt->execute([
                $staffId, $month,
                $stats['review_count'], $stats['review_earnings'],
                $stats['drop_count'], $stats['drop_earnings'],
                $totalPoints, $totalEarnings
            ]);

        } catch (\Exception $e) {
            error_log("Update Staff Month Stats Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update rankings for all staff in a month
     */
    private function updateRankings($month) {
        try {
            // Get staff ordered by points
            $stmt = $this->db->prepare("
                SELECT staff_id, total_points
                FROM staff_performance_stats
                WHERE month_year = ?
                ORDER BY total_points DESC, total_earnings DESC
            ");
            $stmt->execute([$month]);
            $rankings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Update ranks
            $rank = 1;
            foreach ($rankings as $ranking) {
                $stmt = $this->db->prepare("
                    UPDATE staff_performance_stats
                    SET rank_overall = ?
                    WHERE staff_id = ? AND month_year = ?
                ");
                $stmt->execute([$rank, $ranking['staff_id'], $month]);
                $rank++;
            }

            // Save to leaderboard history
            $this->saveLeaderboardHistory($month);

            return true;

        } catch (\Exception $e) {
            error_log("Update Rankings Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save leaderboard snapshot to history
     */
    private function saveLeaderboardHistory($month) {
        try {
            // Get top performers
            $stmt = $this->db->prepare("
                SELECT
                    sps.*,
                    sa.store_id
                FROM staff_performance_stats sps
                JOIN staff_accounts sa ON sps.staff_id = sa.staff_id
                WHERE sps.month_year = ?
                ORDER BY sps.rank_overall ASC
                LIMIT 100
            ");
            $stmt->execute([$month]);
            $performers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($performers as $performer) {
                $stmt = $this->db->prepare("
                    INSERT INTO leaderboard_history (
                        staff_id, period_type, period_date,
                        google_reviews_count, vape_drops_count,
                        total_points, total_earnings, rank
                    ) VALUES (?, 'monthly', ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        google_reviews_count = VALUES(google_reviews_count),
                        vape_drops_count = VALUES(vape_drops_count),
                        total_points = VALUES(total_points),
                        total_earnings = VALUES(total_earnings),
                        rank = VALUES(rank)
                ");

                $stmt->execute([
                    $performer['staff_id'],
                    $month . '-01',
                    $performer['google_reviews_count'],
                    $performer['vape_drops_count'],
                    $performer['total_points'],
                    $performer['total_earnings'],
                    $performer['rank_overall']
                ]);
            }

            return true;

        } catch (\Exception $e) {
            error_log("Save Leaderboard History Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get current leaderboard
     */
    public function getCurrentLeaderboard($limit = 10) {
        try {
            $month = date('Y-m');

            $stmt = $this->db->prepare("
                SELECT
                    sps.*,
                    sa.full_name,
                    sa.store_id,
                    o.name as store_name
                FROM staff_performance_stats sps
                JOIN staff_accounts sa ON sps.staff_id = sa.staff_id
                LEFT JOIN outlets o ON sa.store_id = o.outlet_id
                WHERE sps.month_year = ?
                ORDER BY sps.rank_overall ASC
                LIMIT ?
            ");
            $stmt->execute([$month, $limit]);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            error_log("Get Current Leaderboard Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get staff member's current position
     */
    public function getStaffPosition($staffId) {
        try {
            $month = date('Y-m');

            $stmt = $this->db->prepare("
                SELECT * FROM staff_performance_stats
                WHERE staff_id = ? AND month_year = ?
            ");
            $stmt->execute([$staffId, $month]);

            return $stmt->fetch(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            error_log("Get Staff Position Error: " . $e->getMessage());
            return null;
        }
    }
}
