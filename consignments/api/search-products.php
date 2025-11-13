<?php

/**
 * Quick Product Search API
 * Returns JSON: { ok: true, items: [...] }
 */

declare(strict_types=1);

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../base/bootstrap.php';
    require_once __DIR__ . '/../lib/Services/ProductService.php';

    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $outletId = isset($_GET['outlet']) ? (int)$_GET['outlet'] : null;

    if ($q === '' || mb_strlen($q) < 2) {
        echo json_encode(['ok' => false, 'message' => 'Query too short']);
        exit;
    }

    $svc = \CIS\Consignments\Services\ProductService::make();
    $items = $svc->search($q, $limit, $outletId);

    echo json_encode(['ok' => true, 'items' => $items]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error']);
}
