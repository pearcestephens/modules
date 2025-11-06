<?php
/**
 * Smart-Cron Task: Achievement Check and Award
 *
 * Checks user stats and awards achievements/badges
 * Runs every 6 hours
 *
 * @package CIS\FlaggedProducts\Cron
 */

require_once __DIR__ . '/bootstrap.php';

use FlaggedProducts\Lib\Logger;

// Track execution start time
$executionStart = microtime(true);

try {
    // Log task start
    Logger::cronTaskStarted('check_achievements', [
        'scheduled_time' => date('Y-m-d H:i:s'),
        'check_interval' => '6 hours'
    ]);

    CISLogger::info('flagged_products_cron', 'Starting achievement check');

    // Define achievement criteria
    $achievements = [
        'first_completion' => [
            'name' => 'First Step',
            'description' => 'Complete your first product',
            'icon' => '🎯',
            'criteria' => function($stats) {
                return $stats->products_completed >= 1;
            }
        ],
        'perfect_10' => [
            'name' => 'Perfect 10',
            'description' => 'Complete 10 products with 100% accuracy',
            'icon' => '💯',
            'criteria' => function($stats) {
                return $stats->products_completed >= 10 && $stats->accuracy >= 100;
            }
        ],
        'speed_demon' => [
            'name' => 'Speed Demon',
            'description' => 'Average under 30 seconds per product',
            'icon' => '⚡',
            'criteria' => function($stats) {
                return $stats->avg_time_per_product <= 30 && $stats->products_completed >= 10;
            }
        ],
        'week_warrior' => [
            'name' => 'Week Warrior',
            'description' => 'Maintain a 7-day streak',
            'icon' => '🔥',
            'criteria' => function($stats) {
                return $stats->current_streak >= 7;
            }
        ],
        'century_club' => [
            'name' => 'Century Club',
            'description' => 'Complete 100 products',
            'icon' => '💯',
            'criteria' => function($stats) {
                return $stats->products_completed >= 100;
            }
        ],
        'accuracy_master' => [
            'name' => 'Accuracy Master',
            'description' => 'Maintain 98%+ accuracy over 50 products',
            'icon' => '🎯',
            'criteria' => function($stats) {
                return $stats->products_completed >= 50 && $stats->accuracy >= 98;
            }
        ],
        'point_millionaire' => [
            'name' => 'Point Millionaire',
            'description' => 'Earn 1,000 points',
            'icon' => '💰',
            'criteria' => function($stats) {
                return $stats->total_points >= 1000;
            }
        ]
    ];

    // Get all active users with stats
    $sql = "SELECT
                us.user_id,
                us.points_earned as total_points,
                us.current_streak,
                COUNT(fp.id) as products_completed,
                AVG(CASE WHEN fp.qty_before = fp.qty_after THEN 100 ELSE 0 END) as accuracy,
                AVG(fpa.time_spent_seconds) as avg_time_per_product
            FROM flagged_products_user_stats us
            LEFT JOIN flagged_products fp ON fp.completed_by_staff = us.user_id
            LEFT JOIN flagged_products_completion_attempts fpa ON fpa.user_id = us.user_id
            GROUP BY us.user_id, us.points_earned, us.current_streak
            HAVING products_completed > 0";

    $users = sql_query_collection_safe($sql, []);

    $awarded = 0;
    $checked = 0;

    foreach ($users as $userStats) {
        $checked++;

        // Get existing achievements for user
        $existingSql = "SELECT achievement_key FROM flagged_products_achievements WHERE user_id = ?";
        $existing = sql_query_collection_safe($existingSql, [$userStats->user_id]);
        $existingKeys = array_column($existing, 'achievement_key');

        // Check each achievement
        foreach ($achievements as $key => $achievement) {
            // Skip if already awarded
            if (in_array($key, $existingKeys)) {
                continue;
            }

            // Check if criteria met
            if ($achievement['criteria']($userStats)) {
                // Award achievement
                $insertSql = "INSERT INTO flagged_products_achievements
                              (user_id, achievement_key, achievement_name, achievement_description, achievement_icon, awarded_at)
                              VALUES (?, ?, ?, ?, ?, NOW())";

                sql_query_update_or_insert_safe($insertSql, [
                    $userStats->user_id,
                    $key,
                    $achievement['name'],
                    $achievement['description'],
                    $achievement['icon']
                ]);

                CISLogger::info('flagged_products_cron', "Achievement '{$achievement['name']}' awarded to user {$userStats->user_id}");

                // Log achievement earned with enhanced logger
                Logger::achievementEarned(
                    $userStats->user_id,
                    0, // Achievement ID (we could add this if needed)
                    $achievement['name'],
                    50, // Bonus points
                    [
                        'achievement_key' => $key,
                        'description' => $achievement['description'],
                        'icon' => $achievement['icon'],
                        'user_stats' => [
                            'products_completed' => $userStats->products_completed,
                            'accuracy' => round($userStats->accuracy, 2),
                            'current_streak' => $userStats->current_streak
                        ]
                    ]
                );

                $awarded++;

                // Award bonus points (50 points per achievement)
                $updateSql = "UPDATE flagged_products_user_stats
                              SET points_earned = points_earned + 50,
                                  updated_at = NOW()
                              WHERE user_id = ?";

                sql_query_update_or_insert_safe($updateSql, [$userStats->user_id]);
            }
        }
    }

    // Calculate execution time
    $executionTime = microtime(true) - $executionStart;

    CISLogger::info('flagged_products_cron', "Achievement check completed: {$checked} users checked, {$awarded} achievements awarded");

    // Log task completion with metrics
    Logger::cronTaskCompleted('check_achievements', true, [
        'users_checked' => $checked,
        'achievements_awarded' => $awarded,
        'achievement_types' => count($achievements),
        'execution_time_seconds' => round($executionTime, 2)
    ]);

    echo json_encode([
        'success' => true,
        'users_checked' => $checked,
        'achievements_awarded' => $awarded,
        'execution_time' => round($executionTime, 2)
    ]);

} catch (Exception $e) {
    // Calculate execution time for failure case
    $executionTime = microtime(true) - $executionStart;

    CISLogger::error('flagged_products_cron', 'Achievement check failed: ' . $e->getMessage());

    // Log task failure
    Logger::cronTaskCompleted('check_achievements', false, [
        'users_checked' => $checked ?? 0,
        'achievements_awarded' => $awarded ?? 0,
        'execution_time_seconds' => round($executionTime, 2)
    ], $e->getMessage());

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
