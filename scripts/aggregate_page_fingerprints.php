#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * Page Fingerprint Aggregation Script
 * -----------------------------------
 * Aggregates recent performance timing behavior events (event_type=perf_timing)
 * into cis_page_fingerprints table as rolling metrics.
 *
 * Placeholder metrics for now: LCP, CLS, INP extracted from event_data JSON if present.
 * Calculates count, avg, p95 (approx via ordering), last_updated.
 *
 * Cron: run every 5 minutes.
 */

// Bootstrap minimal environment
chdir(dirname(__DIR__)); // move to modules root
require_once __DIR__ . '/../base/Database.php';

use CIS\Base\Database;

// Time window (last 24h)
$windowStart = date('Y-m-d H:i:s', time() - 86400);

// Fetch raw perf events
$rows = Database::query(
    "SELECT page_url, event_data, occurred_at_ms FROM cis_user_events
     WHERE event_type = 'perf_timing' AND created_at >= ?",
    [$windowStart]
);

$pages = [];
foreach ($rows as $r) {
    $page = $r['page_url'] ?: 'unknown';
    $data = json_decode($r['event_data'], true) ?: [];
    $lcp = isset($data['lcp']) ? (float)$data['lcp'] : null;
    $cls = isset($data['cls']) ? (float)$data['cls'] : null;
    $inp = isset($data['inp']) ? (float)$data['inp'] : null;

    if (!isset($pages[$page])) {
        $pages[$page] = [
            'lcp' => [], 'cls' => [], 'inp' => [], 'count' => 0
        ];
    }
    $pages[$page]['count']++;
    if ($lcp !== null) { $pages[$page]['lcp'][] = $lcp; }
    if ($cls !== null) { $pages[$page]['cls'][] = $cls; }
    if ($inp !== null) { $pages[$page]['inp'][] = $inp; }
}

function stats(array $values): array {
    if (!$values) return ['avg' => null, 'p95' => null];
    sort($values);
    $count = count($values);
    $avg = array_sum($values) / $count;
    $p95Index = (int) floor(0.95 * ($count - 1));
    return [
        'avg' => $avg,
        'p95' => $values[$p95Index] ?? null
    ];
}

$now = date('Y-m-d H:i:s');
$updated = 0;
foreach ($pages as $pageUrl => $bucket) {
    $lcpStats = stats($bucket['lcp']);
    $clsStats = stats($bucket['cls']);
    $inpStats = stats($bucket['inp']);

    // Upsert logic
    Database::execute(
        "INSERT INTO cis_page_fingerprints
         (page_url, sample_count, lcp_avg, lcp_p95, cls_avg, cls_p95, inp_avg, inp_p95, last_aggregated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           sample_count = VALUES(sample_count),
           lcp_avg = VALUES(lcp_avg), lcp_p95 = VALUES(lcp_p95),
           cls_avg = VALUES(cls_avg), cls_p95 = VALUES(cls_p95),
           inp_avg = VALUES(inp_avg), inp_p95 = VALUES(inp_p95),
           last_aggregated_at = VALUES(last_aggregated_at)" , [
            $pageUrl,
            $bucket['count'],
            $lcpStats['avg'], $lcpStats['p95'],
            $clsStats['avg'], $clsStats['p95'],
            $inpStats['avg'], $inpStats['p95'],
            $now
        ]
    );
    $updated++;
}

echo "Aggregated fingerprints for {$updated} pages (" . count($rows) . " raw events)\n";
exit(0);
