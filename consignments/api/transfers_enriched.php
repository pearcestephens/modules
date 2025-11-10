<?php
declare(strict_types=1);

// Consignments Enriched Transfers API (internal)
// GET params: limit (int, optional, default 25), type (default 'STOCK')

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../bootstrap.php';

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
    if ($limit <= 0) { $limit = 25; }
    if ($limit > 100) { $limit = 100; }
    $type = isset($_GET['type']) ? (string)$_GET['type'] : 'STOCK';
    $state = isset($_GET['state']) ? (string)$_GET['state'] : '';

    if (!function_exists('getRecentTransfersEnrichedDB')) {
        throw new RuntimeException('Enrichment helper unavailable');
    }

    $opts = [];
    if ($state !== '') { $opts['state'] = $state; }
    $data = getRecentTransfersEnrichedDB($limit, $type, $opts);

    echo json_encode([
        'success' => true,
        'count' => count($data),
        'data' => $data,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'internal_error',
        'message' => $e->getMessage(),
    ]);
}
