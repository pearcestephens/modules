<?php declare(strict_types=1);
// Performance summary endpoint (REST JSON)
// Returns fingerprints plus slow pages sorted by lcp_p95.

use CIS\Base\Database;

require_once __DIR__ . '/../Database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $fingerprints = Database::query(
        "SELECT page_url, sample_count, lcp_avg, lcp_p95, cls_avg, cls_p95, inp_avg, inp_p95, last_aggregated_at
         FROM cis_page_fingerprints ORDER BY lcp_p95 DESC"
    );

    // Identify slow pages (heuristic: lcp_p95 > 2500ms or cls_p95 > 0.1)
    $slow = [];
    foreach ($fingerprints as $fp) {
        $isSlow = false;
        if (isset($fp['lcp_p95']) && $fp['lcp_p95'] !== null && (float)$fp['lcp_p95'] > 2500) {
            $isSlow = true;
        }
        if (isset($fp['cls_p95']) && $fp['cls_p95'] !== null && (float)$fp['cls_p95'] > 0.1) {
            $isSlow = true;
        }
        if ($isSlow) {
            $slow[] = $fp;
        }
    }

    echo json_encode([
        'count' => count($fingerprints),
        'fingerprints' => $fingerprints,
        'slow_pages' => $slow,
        'thresholds' => [
            'lcp_p95' => 2500,
            'cls_p95' => 0.1
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
}
