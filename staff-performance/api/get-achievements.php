<?php
/**
 * Staff Performance API - Get Achievements
 *
 * Returns user's achievement progress
 *
 * @package CIS\Modules\StaffPerformance\API
 * @version 1.0.0
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

try {
    // Get all achievements with user's progress
    $stmt = $db->prepare("
        SELECT
            a.*,
            sa.unlocked_at,
            sa.progress_current,
            sa.progress_total,
            CASE WHEN sa.unlocked_at IS NOT NULL THEN 1 ELSE 0 END as is_unlocked
        FROM achievements a
        LEFT JOIN staff_achievements sa ON a.achievement_id = sa.achievement_id AND sa.staff_id = ?
        ORDER BY a.category, a.difficulty, a.achievement_id
    ");
    $stmt->execute([$current_user_id]);
    $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate stats
    $unlocked = array_filter($achievements, fn($a) => $a['is_unlocked']);
    $total_points = array_sum(array_column($unlocked, 'points_value'));
    $progress_percent = count($achievements) > 0 ? round((count($unlocked) / count($achievements)) * 100) : 0;

    // Group by category
    $by_category = [];
    foreach ($achievements as $achievement) {
        $category = $achievement['category'];
        if (!isset($by_category[$category])) {
            $by_category[$category] = [];
        }
        $by_category[$category][] = $achievement;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'achievements' => $achievements,
            'by_category' => $by_category,
            'stats' => [
                'total' => count($achievements),
                'unlocked' => count($unlocked),
                'locked' => count($achievements) - count($unlocked),
                'progress_percent' => $progress_percent,
                'total_points' => $total_points
            ]
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch achievements',
        'message' => $e->getMessage()
    ]);
}
