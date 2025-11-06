<?php
/**
 * Staff Performance API - Get Leaderboard
 *
 * Returns current leaderboard rankings
 *
 * @package CIS\Modules\StaffPerformance\API
 * @version 1.0.0
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

try {
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 10;
    $period = $_GET['period'] ?? 'current_month';

    if ($period === 'current_month') {
        $stmt = $db->prepare("
            SELECT
                sa.staff_id,
                sa.full_name,
                sa.store_id,
                o.name as store_name,
                COALESCE(COUNT(DISTINCT gr.review_id), 0) as google_reviews,
                COALESCE(COUNT(DISTINCT vd.drop_id), 0) as vape_drops,
                COALESCE(COUNT(DISTINCT gr.review_id) * 10.00, 0) +
                COALESCE(COUNT(DISTINCT vd.drop_id) * 6.00, 0) as earnings,
                COALESCE(COUNT(DISTINCT gr.review_id) * 100, 0) +
                COALESCE(COUNT(DISTINCT vd.drop_id) * 50, 0) as points
            FROM staff_accounts sa
            LEFT JOIN outlets o ON sa.store_id = o.outlet_id
            LEFT JOIN google_reviews gr ON sa.staff_id = gr.staff_id
                AND gr.review_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
            LEFT JOIN vape_drops vd ON sa.staff_id = vd.staff_id
                AND vd.drop_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
            WHERE sa.is_active = 1
            GROUP BY sa.staff_id
            ORDER BY points DESC, earnings DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);

    } else {
        // All time
        $stmt = $db->prepare("
            SELECT
                sa.staff_id,
                sa.full_name,
                sa.store_id,
                o.name as store_name,
                COALESCE(COUNT(DISTINCT gr.review_id), 0) as google_reviews,
                COALESCE(COUNT(DISTINCT vd.drop_id), 0) as vape_drops,
                COALESCE(COUNT(DISTINCT gr.review_id) * 10.00, 0) +
                COALESCE(COUNT(DISTINCT vd.drop_id) * 6.00, 0) as earnings,
                COALESCE(COUNT(DISTINCT gr.review_id) * 100, 0) +
                COALESCE(COUNT(DISTINCT vd.drop_id) * 50, 0) as points
            FROM staff_accounts sa
            LEFT JOIN outlets o ON sa.store_id = o.outlet_id
            LEFT JOIN google_reviews gr ON sa.staff_id = gr.staff_id
            LEFT JOIN vape_drops vd ON sa.staff_id = vd.staff_id
            WHERE sa.is_active = 1
            GROUP BY sa.staff_id
            ORDER BY points DESC, earnings DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
    }

    $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add ranks
    foreach ($leaderboard as $index => &$row) {
        $row['rank'] = $index + 1;
    }

    echo json_encode([
        'success' => true,
        'data' => $leaderboard,
        'meta' => [
            'period' => $period,
            'count' => count($leaderboard),
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch leaderboard',
        'message' => $e->getMessage()
    ]);
}
