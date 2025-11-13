<?php
// Behavior collection endpoint (POST JSON array via sendBeacon/fetch)
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    \CIS\Base\ErrorHandler::methodNotAllowed();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    \CIS\Base\ErrorHandler::respondHttpError(400, 'Invalid payload');
}

// Basic size guard
if (strlen($raw) > 200000) { // 200KB
    \CIS\Base\ErrorHandler::respondHttpError(413, 'Payload too large');
}

// Insert batched events
$count = 0;
foreach ($data as $evt) {
    if (!is_array($evt)) continue;
    $type = $evt['type'] ?? 'unknown';
    $occurredAtMs = isset($evt['t']) ? (int)$evt['t'] : null;
    $pageUrl = $evt['pg'] ?? null;
    unset($evt['t'], $evt['pg']);
    CISLogger::behavior($type, $evt, $pageUrl, $occurredAtMs);

    // Performance enrichment: if perf_timing event, map key metrics to performance table
    if ($type === 'perf_timing') {
        // Expected keys: lcp, cls, inp, dcl (DOMContentLoaded), fcp, ttfb
        $metrics = [
            'lcp' => 'LargestContentfulPaint',
            'cls' => 'CumulativeLayoutShift',
            'inp' => 'InteractionToNextPaint',
            'fcp' => 'FirstContentfulPaint',
            'ttfb' => 'TimeToFirstByte'
        ];
        foreach ($metrics as $key => $metricName) {
            if (isset($evt[$key]) && is_numeric($evt[$key])) {
                CISLogger::performance('page_metric', $metricName, (float)$evt[$key], $key === 'cls' ? '' : 'ms', [
                    'page_url' => $pageUrl,
                    'occurred_at_ms' => $occurredAtMs,
                    'raw' => $evt[$key]
                ]);
            }
        }
        // DOMContentLoaded timing (if present as dcl)
        if (isset($evt['dcl']) && is_numeric($evt['dcl'])) {
            CISLogger::performance('page_metric', 'DomContentLoaded', (float)$evt['dcl'], 'ms', [
                'page_url' => $pageUrl,
                'occurred_at_ms' => $occurredAtMs,
            ]);
        }
    }
    $count++;
}

header('Content-Type: application/json');
http_response_code(200);
echo json_encode(['ok' => true, 'count' => $count]);
