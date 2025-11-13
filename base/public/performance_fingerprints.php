<?php declare(strict_types=1);
// Performance fingerprints JSON endpoint
// Returns all rows from cis_page_fingerprints for dashboard consumption.

use CIS\Base\Database;

require_once __DIR__ . '/../Database.php';

header('Content-Type: application/json');

try {
    // Table existence check (fast metadata probe)
    $exists = true;
    try {
        Database::queryOne('SELECT page_url FROM cis_page_fingerprints LIMIT 1');
    } catch (Exception $inner) {
        $exists = false;
    }
    if (!$exists) {
        echo json_encode([
            'count' => 0,
            'fingerprints' => [],
            'missing_table' => true,
            'schema' => [
                'page_url' => 'string',
                'sample_count' => 'int',
                'lcp_avg' => 'float|null',
                'lcp_p95' => 'float|null',
                'cls_avg' => 'float|null',
                'cls_p95' => 'float|null',
                'inp_avg' => 'float|null',
                'inp_p95' => 'float|null',
                'last_aggregated_at' => 'datetime|null'
            ],
            'notes' => 'Migration required: cis_page_fingerprints'
        ]);
        exit;
    }
    $rows = Database::query(
        "SELECT page_url, sample_count, lcp_avg, lcp_p95, cls_avg, cls_p95, inp_avg, inp_p95, last_aggregated_at
         FROM cis_page_fingerprints ORDER BY sample_count DESC"
    );
    echo json_encode([
        'count' => count($rows),
        'fingerprints' => $rows,
        'missing_table' => false
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'query_failed',
        'message' => 'Unable to fetch performance fingerprints',
        'trace_id' => substr(sha1($e->getMessage() . microtime()), 0, 12)
    ]);
}
