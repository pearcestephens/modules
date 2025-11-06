<?php
/**
 * Staff Performance API - Get Personal Stats
 *
 * Returns current user's performance statistics
 *
 * @package CIS\Modules\StaffPerformance\API
 * @version 1.0.0
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

try {
    // Get current month stats
    $stmt = $db->prepare("
        SELECT
            COALESCE(COUNT(DISTINCT gr.review_id), 0) as reviews_this_month,
            COALESCE(COUNT(DISTINCT vd.drop_id), 0) as drops_this_month,
            COALESCE(COUNT(DISTINCT gr.review_id) * 10.00, 0) +
            COALESCE(COUNT(DISTINCT vd.drop_id) * 6.00, 0) as earnings_this_month,
            COALESCE(COUNT(DISTINCT gr.review_id) * 100, 0) +
            COALESCE(COUNT(DISTINCT vd.drop_id) * 50, 0) as points_this_month
        FROM (SELECT ? as staff_id) s
        LEFT JOIN google_reviews gr ON gr.staff_id = s.staff_id
            AND gr.review_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
        LEFT JOIN vape_drops vd ON vd.staff_id = s.staff_id
            AND vd.drop_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
    ");
    $stmt->execute([$current_user_id]);
    $monthStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get all-time stats
    $stmt = $db->prepare("
        SELECT
            COALESCE(COUNT(DISTINCT gr.review_id), 0) as total_reviews,
            COALESCE(COUNT(DISTINCT vd.drop_id), 0) as total_drops,
            COALESCE(COUNT(DISTINCT gr.review_id) * 10.00, 0) +
            COALESCE(COUNT(DISTINCT vd.drop_id) * 6.00, 0) as total_earnings,
            COALESCE(COUNT(DISTINCT gr.review_id) * 100, 0) +
            COALESCE(COUNT(DISTINCT vd.drop_id) * 50, 0) as total_points
        FROM (SELECT ? as staff_id) s
        LEFT JOIN google_reviews gr ON gr.staff_id = s.staff_id
        LEFT JOIN vape_drops vd ON vd.staff_id = s.staff_id
    ");
    $stmt->execute([$current_user_id]);
    $allTimeStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get current rank
    $stmt = $db->prepare("
        SELECT COUNT(*) + 1 as rank
        FROM (
            SELECT
                sa.staff_id,
                COALESCE(COUNT(DISTINCT gr.review_id) * 100, 0) +
                COALESCE(COUNT(DISTINCT vd.drop_id) * 50, 0) as points
            FROM staff_accounts sa
            LEFT JOIN google_reviews gr ON sa.staff_id = gr.staff_id
                AND gr.review_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
            LEFT JOIN vape_drops vd ON sa.staff_id = vd.staff_id
                AND vd.drop_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
            WHERE sa.is_active = 1
            GROUP BY sa.staff_id
            HAVING points > (
                SELECT
                    COALESCE(COUNT(DISTINCT gr2.review_id) * 100, 0) +
                    COALESCE(COUNT(DISTINCT vd2.drop_id) * 50, 0)
                FROM google_reviews gr2
                LEFT JOIN vape_drops vd2 ON 1=1
                WHERE gr2.staff_id = ? AND gr2.review_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
                   OR vd2.staff_id = ? AND vd2.drop_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
            )
        ) as rankings
    ");
    $stmt->execute([$current_user_id, $current_user_id]);
    $rank = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => [
            'this_month' => $monthStats,
            'all_time' => $allTimeStats,
            'rank' => $rank,
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch stats',
        'message' => $e->getMessage()
    ]);
}
