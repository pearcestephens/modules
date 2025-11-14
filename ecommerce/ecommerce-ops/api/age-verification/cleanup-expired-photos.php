<?php
/**
 * Age Verification Photo Cleanup Script
 *
 * Deletes expired ID photos per retention policy:
 * - Approved: 7 days after approval
 * - Rejected: 30 days after rejection
 * - Abandoned: 30 days if uploaded but not reviewed
 *
 * Run via CRON daily at 2:00 AM
 *
 * @package CIS\Modules\EcommerceOps\CRON
 */

// Disable output buffering for CRON
ini_set('output_buffering', 'off');
ini_set('implicit_flush', 'on');
ob_implicit_flush(true);

require_once __DIR__ . '/../../bootstrap.php';

echo "[" . date('Y-m-d H:i:s') . "] Age Verification Photo Cleanup Started\n";
echo str_repeat('-', 60) . "\n";

// Initialize service
use CIS\Modules\EcommerceOps\AgeVerificationService;
$service = new AgeVerificationService();

// Run auto-deletion
echo "Processing expired photos...\n";
$results = $service->autoDeleteExpiredPhotos();

// Display results
echo "\nDeletion Summary:\n";
echo "- Approved verifications (>7 days): {$results['approved']} photos deleted\n";
echo "- Rejected verifications (>30 days): {$results['rejected']} photos deleted\n";
echo "- Abandoned verifications (>30 days): {$results['abandoned']} photos deleted\n";
echo "- Total: " . array_sum($results) . " photos deleted\n";

// Check disk space in storage directory
$storagePath = ecomm_env('AGE_VERIFICATION_STORAGE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/secure/id-photos/');
$diskFree = disk_free_space($storagePath);
$diskTotal = disk_total_space($storagePath);
$diskUsed = $diskTotal - $diskFree;
$diskUsedPercent = round(($diskUsed / $diskTotal) * 100, 2);

echo "\nStorage Statistics:\n";
echo "- Path: $storagePath\n";
echo "- Used: " . formatBytes($diskUsed) . " / " . formatBytes($diskTotal) . " ($diskUsedPercent%)\n";
echo "- Free: " . formatBytes($diskFree) . "\n";

// Count remaining photos
$photoCount = count(glob($storagePath . '*.jpg'));
echo "- Remaining photos: $photoCount\n";

// Alert if disk usage is high
if ($diskUsedPercent > 80) {
    echo "\n⚠️  WARNING: Disk usage exceeds 80%\n";
    ecomm_log_error("High disk usage in ID photo storage", [
        'used_percent' => $diskUsedPercent,
        'used_bytes' => $diskUsed,
        'free_bytes' => $diskFree
    ]);
}

echo "\n" . str_repeat('-', 60) . "\n";
echo "[" . date('Y-m-d H:i:s') . "] Cleanup Completed\n";

// Helper function to format bytes
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

exit(0);
