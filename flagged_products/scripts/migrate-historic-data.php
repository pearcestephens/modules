<?php
/**
 * Historic Data Migration Script
 * 
 * Migrates 313,997 historic flagged product completions into new gamification system:
 * - User stats & lifetime completions
 * - Leaderboard rankings (all-time)
 * - Points system (historic points calculation)
 * - Store statistics
 * - Retroactive achievements
 * 
 * Usage:
 *   php migrate-historic-data.php --dry-run  (preview only)
 *   php migrate-historic-data.php --execute  (commit changes)
 * 
 * @package CIS\FlaggedProducts\Scripts
 * @version 1.0.0
 */

declare(strict_types=1);

// Set DOCUMENT_ROOT for CLI
if (!isset($_SERVER['DOCUMENT_ROOT']) || empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
}

// Bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/CISLogger.php';
require_once __DIR__ . '/../lib/Logger.php';

// Ensure we have database connection
if (!isset($con) || !$con) {
    die("ERROR: Database connection not available\n");
}

// Configuration
define('POINTS_PER_COMPLETION', 10);
define('BONUS_POINTS_FAST_COMPLETION', 5); // If completed same day as flagged
define('DRY_RUN', in_array('--dry-run', $argv));
define('VERBOSE', in_array('--verbose', $argv) || in_array('-v', $argv));

// Color output
function color($text, $color) {
    $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'cyan' => "\033[36m",
        'reset' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['reset'];
}

function logMigration($message, $color = 'cyan') {
    echo color("[" . date('H:i:s') . "] ", 'blue') . color($message, $color) . "\n";
}

function logError($message) {
    echo color("[ERROR] ", 'red') . $message . "\n";
}

function logSuccess($message) {
    echo color("[✓] ", 'green') . $message . "\n";
}

// Header
echo "\n" . str_repeat("=", 80) . "\n";
echo color("  FLAGGED PRODUCTS - HISTORIC DATA MIGRATION", 'cyan') . "\n";
echo color("  Migrating 313,997 historic completions into new gamification system", 'yellow') . "\n";
echo str_repeat("=", 80) . "\n\n";

if (DRY_RUN) {
    echo color("  🔍 DRY-RUN MODE: No changes will be committed", 'yellow') . "\n\n";
} else {
    echo color("  ⚡ EXECUTE MODE: Changes will be committed to database", 'green') . "\n\n";
    echo "  Press ENTER to continue, or Ctrl+C to cancel...\n";
    fgets(STDIN);
}

// Step 1: Analyze Historic Data
logMigration("PHASE 1: Analyzing historic data from 'flagged_products' table...", 'yellow');

$sql = "SELECT 
            COUNT(*) as total_products,
            COUNT(date_completed_stocktake) as completed_count,
            COUNT(DISTINCT completed_by_staff) as unique_users,
            COUNT(DISTINCT outlet) as unique_outlets,
            MIN(date_flagged) as earliest_date,
            MAX(date_completed_stocktake) as latest_completion
        FROM flagged_products 
        WHERE date_completed_stocktake IS NOT NULL";

$result = mysqli_query($con, $sql);
$stats = mysqli_fetch_object($result);

logSuccess("Found {$stats->completed_count} completed products");
logSuccess("Unique users: {$stats->unique_users}");
logSuccess("Unique outlets: {$stats->unique_outlets}");
logSuccess("Date range: {$stats->earliest_date} to {$stats->latest_completion}");

// Step 2: Get User Completion Stats
logMigration("\nPHASE 2: Calculating user stats...", 'yellow');

$sql = "SELECT 
            completed_by_staff as user_id,
            COUNT(*) as total_completions,
            MIN(date_completed_stocktake) as first_completion,
            MAX(date_completed_stocktake) as last_completion,
            COUNT(DISTINCT outlet) as outlets_worked,
            SUM(CASE 
                WHEN DATE(date_completed_stocktake) = DATE(date_flagged) 
                THEN 1 ELSE 0 
            END) as fast_completions,
            AVG(TIMESTAMPDIFF(HOUR, date_flagged, date_completed_stocktake)) as avg_hours_to_complete
        FROM flagged_products 
        WHERE date_completed_stocktake IS NOT NULL 
          AND completed_by_staff IS NOT NULL
        GROUP BY completed_by_staff
        ORDER BY total_completions DESC";

$result = mysqli_query($con, $sql);
$userStats = [];
while ($row = mysqli_fetch_object($result)) {
    $userStats[] = $row;
}

logSuccess("Calculated stats for " . count($userStats) . " users");

if (VERBOSE) {
    echo "\n  Top 10 Users by Completions:\n";
    echo str_repeat("-", 80) . "\n";
    foreach (array_slice($userStats, 0, 10) as $i => $user) {
        $points = ($user->total_completions * POINTS_PER_COMPLETION) + 
                  ($user->fast_completions * BONUS_POINTS_FAST_COMPLETION);
        echo sprintf("  %2d. User #%-5d: %5d completions | %6d points | First: %s\n",
            $i + 1,
            $user->user_id,
            $user->total_completions,
            $points,
            substr($user->first_completion, 0, 10)
        );
    }
    echo "\n";
}

// Step 3: Calculate Points
logMigration("PHASE 3: Calculating historic points...", 'yellow');

$totalPoints = 0;
$userPoints = [];

foreach ($userStats as $user) {
    $basePoints = $user->total_completions * POINTS_PER_COMPLETION;
    $bonusPoints = $user->fast_completions * BONUS_POINTS_FAST_COMPLETION;
    $totalUserPoints = $basePoints + $bonusPoints;
    
    $userPoints[$user->user_id] = [
        'total_points' => $totalUserPoints,
        'base_points' => $basePoints,
        'bonus_points' => $bonusPoints,
        'completions' => $user->total_completions,
        'first_completion' => $user->first_completion,
        'last_completion' => $user->last_completion,
        'fast_completions' => $user->fast_completions,
        'outlets_worked' => $user->outlets_worked
    ];
    
    $totalPoints += $totalUserPoints;
}

logSuccess("Total points to migrate: " . number_format($totalPoints));

// Step 4: Store Stats
logMigration("\nPHASE 4: Calculating store statistics...", 'yellow');

$sql = "SELECT 
            outlet as outlet_id,
            COUNT(*) as total_completions,
            COUNT(DISTINCT completed_by_staff) as unique_users,
            MIN(date_completed_stocktake) as first_completion,
            MAX(date_completed_stocktake) as last_completion,
            AVG(TIMESTAMPDIFF(HOUR, date_flagged, date_completed_stocktake)) as avg_hours_to_complete
        FROM flagged_products 
        WHERE date_completed_stocktake IS NOT NULL 
          AND outlet IS NOT NULL
        GROUP BY outlet
        ORDER BY total_completions DESC";

$result = mysqli_query($con, $sql);
$storeStats = [];
while ($row = mysqli_fetch_object($result)) {
    $storeStats[] = $row;
}

logSuccess("Calculated stats for " . count($storeStats) . " outlets");

if (VERBOSE) {
    echo "\n  Top 10 Outlets by Completions:\n";
    echo str_repeat("-", 80) . "\n";
    foreach (array_slice($storeStats, 0, 10) as $i => $store) {
        echo sprintf("  %2d. %-40s: %5d completions | %3d users | Avg: %.1fh\n",
            $i + 1,
            substr($store->outlet_id, 0, 36),
            $store->total_completions,
            $store->unique_users,
            $store->avg_hours_to_complete ?? 0
        );
    }
    echo "\n";
}

// Step 5: Achievement Calculations
logMigration("PHASE 5: Calculating retroactive achievements...", 'yellow');

$achievements = [];

foreach ($userStats as $user) {
    $userAchievements = [];
    
    // Century Club - 100+ completions
    if ($user->total_completions >= 100) {
        $userAchievements[] = [
            'achievement_type' => 'century_club',
            'achievement_name' => 'Century Club',
            'achievement_description' => 'Completed 100+ products',
            'points_awarded' => 500,
            'unlocked_at' => $user->last_completion
        ];
    }
    
    // Speed Demon - 50+ fast completions (same day)
    if ($user->fast_completions >= 50) {
        $userAchievements[] = [
            'achievement_type' => 'speed_demon',
            'achievement_name' => 'Speed Demon',
            'achievement_description' => 'Completed 50+ products same-day',
            'points_awarded' => 250,
            'unlocked_at' => $user->last_completion
        ];
    }
    
    // Veteran - First completion over 1 year ago
    $daysSinceFirst = (time() - strtotime($user->first_completion)) / 86400;
    if ($daysSinceFirst >= 365) {
        $userAchievements[] = [
            'achievement_type' => 'veteran',
            'achievement_name' => 'Veteran',
            'achievement_description' => 'Using system for 1+ years',
            'points_awarded' => 1000,
            'unlocked_at' => $user->first_completion
        ];
    }
    
    // Milestone achievements
    $milestones = [
        ['threshold' => 500, 'name' => 'Expert', 'points' => 750],
        ['threshold' => 1000, 'name' => 'Master', 'points' => 1500],
        ['threshold' => 5000, 'name' => 'Legend', 'points' => 5000],
    ];
    
    foreach ($milestones as $milestone) {
        if ($user->total_completions >= $milestone['threshold']) {
            $userAchievements[] = [
                'achievement_type' => strtolower($milestone['name']) . '_milestone',
                'achievement_name' => $milestone['name'],
                'achievement_description' => "Completed {$milestone['threshold']}+ products",
                'points_awarded' => $milestone['points'],
                'unlocked_at' => $user->last_completion
            ];
        }
    }
    
    if (!empty($userAchievements)) {
        $achievements[$user->user_id] = $userAchievements;
    }
}

$totalAchievements = array_sum(array_map('count', $achievements));
logSuccess("Calculated {$totalAchievements} retroactive achievements for " . count($achievements) . " users");

// Step 6: Preview Migration Plan
echo "\n" . str_repeat("=", 80) . "\n";
echo color("  MIGRATION SUMMARY", 'yellow') . "\n";
echo str_repeat("=", 80) . "\n\n";

echo "  📊 DATA TO MIGRATE:\n\n";
echo "    ✓ User Stats:        {$stats->unique_users} users\n";
echo "    ✓ Completions:       " . number_format((int)$stats->completed_count) . " products\n";
echo "    ✓ Points:            " . number_format((int)$totalPoints) . " points\n";
echo "    ✓ Achievements:      {$totalAchievements} retroactive unlocks\n";
echo "    ✓ Store Stats:       {$stats->unique_outlets} outlets\n";
echo "    ✓ Date Range:        {$stats->earliest_date} to {$stats->latest_completion}\n\n";

echo "  📝 TABLES TO POPULATE:\n\n";
echo "    → flagged_products_leaderboard (all-time rankings)\n";
echo "    → flagged_products_points (historic points)\n";
echo "    → flagged_products_achievements (retroactive unlocks)\n";
echo "    → flagged_products_store_stats (historic outlet data)\n\n";

if (DRY_RUN) {
    echo color("  🔍 DRY-RUN COMPLETE - No changes made\n", 'yellow');
    echo "  Run with --execute to commit changes\n\n";
    exit(0);
}

// Step 7: Execute Migration
logMigration("\nPHASE 6: Executing migration...", 'yellow');

try {
    // Start transaction
    mysqli_begin_transaction($con);
    
    $insertedRecords = 0;
    
    // 7.1: Populate Leaderboard (all-time)
    logMigration("  → Populating leaderboard...");
    
    $stmt = mysqli_prepare($con, "INSERT INTO flagged_products_leaderboard 
            (user_id, period, rank, total_points, products_completed, accuracy_rate, 
             current_streak, best_streak, period_start, period_end, created_at, updated_at)
            VALUES (?, 'all_time', ?, ?, ?, 100.0, 0, 0, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                total_points = VALUES(total_points),
                products_completed = VALUES(products_completed),
                updated_at = NOW()");
    
    foreach ($userStats as $rank => $user) {
        $points = $userPoints[$user->user_id]['total_points'];
        
        mysqli_stmt_bind_param($stmt, 'iiisss',
            $user->user_id,
            $rank + 1,
            $points,
            $user->total_completions,
            $user->first_completion,
            $user->last_completion
        );
        
        mysqli_stmt_execute($stmt);
        $insertedRecords++;
    }
    
    mysqli_stmt_close($stmt);
    
    logSuccess("    Inserted/updated {$insertedRecords} leaderboard entries");
    
    // 7.2: Populate Points
    logMigration("  → Populating points history...");
    
    $insertedRecords = 0;
    $stmt = mysqli_prepare($con, "INSERT INTO flagged_products_points 
            (user_id, points_earned, action_type, reference_id, description, created_at)
            VALUES (?, ?, 'historic_migration', NULL, ?, NOW())");
    
    foreach ($userPoints as $userId => $pointData) {
        $description = sprintf(
            "Historic migration: %d completions (%d base + %d bonus)",
            $pointData['completions'],
            $pointData['base_points'],
            $pointData['bonus_points']
        );
        
        mysqli_stmt_bind_param($stmt, 'iis',
            $userId,
            $pointData['total_points'],
            $description
        );
        
        mysqli_stmt_execute($stmt);
        $insertedRecords++;
    }
    
    mysqli_stmt_close($stmt);
    
    logSuccess("    Inserted {$insertedRecords} points records");
    
    // 7.3: Populate Achievements
    logMigration("  → Unlocking retroactive achievements...");
    
    $insertedRecords = 0;
    $stmt = mysqli_prepare($con, "INSERT INTO flagged_products_achievements 
            (user_id, achievement_type, achievement_name, achievement_description, 
             points_awarded, unlocked_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()");
    
    foreach ($achievements as $userId => $userAchievements) {
        foreach ($userAchievements as $achievement) {
            mysqli_stmt_bind_param($stmt, 'isssis',
                $userId,
                $achievement['achievement_type'],
                $achievement['achievement_name'],
                $achievement['achievement_description'],
                $achievement['points_awarded'],
                $achievement['unlocked_at']
            );
            
            mysqli_stmt_execute($stmt);
            $insertedRecords++;
        }
    }
    
    mysqli_stmt_close($stmt);
    
    logSuccess("    Unlocked {$insertedRecords} achievements");
    
    // 7.4: Populate Store Stats
    logMigration("  → Populating store statistics...");
    
    $insertedRecords = 0;
    $stmt = mysqli_prepare($con, "INSERT INTO flagged_products_store_stats 
            (outlet_id, period, total_completions, total_points, unique_users, 
             avg_completion_time, period_start, period_end, created_at, updated_at)
            VALUES (?, 'all_time', ?, 0, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                total_completions = VALUES(total_completions),
                unique_users = VALUES(unique_users),
                avg_completion_time = VALUES(avg_completion_time),
                updated_at = NOW()");
    
    foreach ($storeStats as $store) {
        mysqli_stmt_bind_param($stmt, 'siidss',
            $store->outlet_id,
            $store->total_completions,
            $store->unique_users,
            $store->avg_hours_to_complete,
            $store->first_completion,
            $store->last_completion
        );
        
        mysqli_stmt_execute($stmt);
        $insertedRecords++;
    }
    
    mysqli_stmt_close($stmt);
    
    logSuccess("    Inserted/updated {$insertedRecords} store stats");
    
    // Commit transaction
    mysqli_commit($con);
    
    // Log migration
    Logger::cronExecutionCompleted('historic_migration', 0, [
        'users_migrated' => $stats->unique_users,
        'completions_migrated' => $stats->completed_count,
        'points_awarded' => $totalPoints,
        'achievements_unlocked' => $totalAchievements,
        'outlets_updated' => $stats->unique_outlets
    ]);
    
    // Success!
    echo "\n" . str_repeat("=", 80) . "\n";
    echo color("  ✓ MIGRATION COMPLETED SUCCESSFULLY!", 'green') . "\n";
    echo str_repeat("=", 80) . "\n\n";
    
    echo "  📊 RESULTS:\n\n";
    echo "    ✓ Migrated {$stats->completed_count} historic completions\n";
    echo "    ✓ Updated {$stats->unique_users} user profiles\n";
    echo "    ✓ Awarded " . number_format($totalPoints) . " historic points\n";
    echo "    ✓ Unlocked {$totalAchievements} achievements\n";
    echo "    ✓ Updated {$stats->unique_outlets} store statistics\n\n";
    
    echo color("  🎉 Users can now see their historic performance immediately!", 'green') . "\n\n";
    
} catch (Exception $e) {
    mysqli_rollback($con);
    logError("Migration failed: " . $e->getMessage());
    logError("All changes have been rolled back");
    exit(1);
}
