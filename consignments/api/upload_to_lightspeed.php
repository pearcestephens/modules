<?php
declare(strict_types=1);

/**
 * Perform Lightspeed (Vend) upload now.
 * POST: transfer_id, session_id
 */

header('Content-Type: application/json');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method Not Allowed', 'error_code' => 'METHOD_NOT_ALLOWED']);
        exit;
    }

    require_once dirname(__DIR__) . '/bootstrap.php';
    require_once dirname(__DIR__) . '/lib/LightspeedClient.php';
    require_once dirname(__DIR__) . '/lib/ConsignmentsService.php';

    $transferId = (int)($_POST['transfer_id'] ?? 0);
    $sessionId  = trim((string)($_POST['session_id'] ?? ''));

    if ($transferId <= 0 || strlen($sessionId) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid input', 'error_code' => 'INVALID_INPUT']);
        exit;
    }

    // Optional: enforce XHR/CSRF if CIS helpers exist
    if (function_exists('cis_require_xhr') && !cis_require_xhr()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'XHR required', 'error_code' => 'XHR_ONLY']); exit;
    }
    if (function_exists('cis_csrf_verify') && !cis_csrf_verify()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token', 'error_code' => 'CSRF']); exit;
    }

    $pdo = cis_resolve_pdo();
    $vendBase  = \ConsignmentsService::resolveVendBaseUrl($pdo);
    $vendToken = \ConsignmentsService::resolveLightspeedToken($pdo);
    $ls = new \LightspeedClient($vendBase, $vendToken);

    $svc = new \ConsignmentsService($pdo, $ls);
    $res = $svc->uploadNow($transferId, $sessionId);

    if ($res['success'] ?? false) {
        echo json_encode(['success' => true, 'message' => 'Upload complete', 'consignment_id' => $res['consignment_id'], 'added' => $res['added'] ?? 0]);
    } else {
        http_response_code(207);
        echo json_encode(['success' => false, 'error' => 'Some products failed', 'consignment_id' => $res['consignment_id'] ?? null, 'added' => $res['added'] ?? 0, 'failed' => $res['failed'] ?? 0]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'error_code' => 'SERVER_ERROR']);
}
