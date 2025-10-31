<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

try {
    $service = new \CIS\Consignments\Services\TransferReviewService($pdo);
    $service->scheduleWeeklyReports();
    echo "Weekly transfer reports job completed.\n";
    exit(0);
} catch (\Exception $e) {
    error_log('[send_weekly_transfer_reports] Error: ' . $e->getMessage());
    echo "Failed to run weekly reports: " . $e->getMessage() . "\n";
    exit(1);
}
