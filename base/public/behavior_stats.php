<?php declare(strict_types=1);
// Behavior statistics endpoint (REST JSON)
// Returns event counts by type (last 5 minutes), suspicious totals, top pages.

use CIS\Base\Database;

require_once __DIR__ . '/../Database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$flags = require __DIR__ . '/../../../config/feature-flags.php';
// Optionally gate with behavior_debug (if disabled, still allow minimal stats?)
if (isset($flags['behavior_debug']) && !$flags['behavior_debug']) {
    // Provide minimal safe summary without raw events
    $restrict = true;
} else {
    $restrict = false;
}

header('Content-Type: application/json');

$now = time();
$windowStart = date('Y-m-d H:i:s', $now - 300); // last 5 min

try {
    // Counts by event type (last 5 min)
    $byType = Database::query(
        "SELECT event_type, COUNT(*) as cnt
         FROM cis_user_events
         WHERE created_at >= ?
         GROUP BY event_type"
        , [$windowStart]
    );

    $counts = [];
    foreach ($byType as $row) {
        $counts[$row['event_type']] = (int)$row['cnt'];
    }

    // Suspicious totals (last 5 min)
    $suspicious = Database::query(
        "SELECT COUNT(*) as total FROM cis_user_events
         WHERE created_at >= ? AND event_type LIKE 'suspicious_%'",
        [$windowStart]
    );
    $suspiciousTotal = (int)($suspicious[0]['total'] ?? 0);

    // Top pages (by event volume) last 5 min (exclude unknown)
    $topPages = Database::query(
        "SELECT page_url, COUNT(*) as cnt
         FROM cis_user_events
         WHERE created_at >= ? AND page_url IS NOT NULL AND page_url <> ''
         GROUP BY page_url ORDER BY cnt DESC LIMIT 10",
        [$windowStart]
    );

    $response = [
        'window_start' => $windowStart,
        'window_seconds' => 300,
        'event_counts' => $counts,
        'suspicious_total' => $suspiciousTotal,
        'top_pages' => $topPages,
        'restricted' => $restrict
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
}
