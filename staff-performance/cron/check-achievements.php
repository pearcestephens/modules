#!/usr/bin/env php
<?php
/**
 * Cron Job: Check Achievements
 *
 * Run daily at 2am to unlock new achievements
 *
 * @package CIS\Modules\StaffPerformance\Cron
 * @version 1.0.0
 */

// Load bootstrap
require_once dirname(__DIR__) . '/bootstrap.php';

try {
    $engine = new StaffPerformance\Services\AchievementEngine($db, $config);

    // Get all active staff
    $stmt = $db->query("SELECT staff_id FROM staff_accounts WHERE is_active = 1");
    $staffList = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $totalUnlocked = 0;

    foreach ($staffList as $staffId) {
        $unlocked = $engine->checkAchievements($staffId);
        $totalUnlocked += count($unlocked);
    }

    echo "[" . date('Y-m-d H:i:s') . "] Check Achievements Completed\n";
    echo "Checked: " . count($staffList) . " staff members\n";
    echo "New Unlocks: " . $totalUnlocked . " achievements\n";

    // Log to file
    $logFile = __DIR__ . '/../logs/check-achievements.log';
    file_put_contents(
        $logFile,
        date('Y-m-d H:i:s') . " | Checked: " . count($staffList) . " | Unlocked: " . $totalUnlocked . "\n",
        FILE_APPEND
    );

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    error_log("Check Achievements Cron Error: " . $e->getMessage());
    exit(1);
}

exit(0);
