<?php
declare(strict_types=1);

/**
 * Consignments API Router (JSON)
 * - POST only, JSON or form-data
 * - action=submit_transfer
 *
 * Returns an "upload contract" for the JS pipeline:
 *   - upload_session_id
 *   - upload_url
 *   - progress_url
 */

header('Content-Type: application/json');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method Not Allowed', 'error_code' => 'METHOD_NOT_ALLOWED']);
        exit;
    }

    // Always bootstrap CIS (one source of truth for DB + sessions)
    require_once dirname(__DIR__) . '/bootstrap.php';
    require_once dirname(__DIR__) . '/lib/LightspeedClient.php';
    require_once dirname(__DIR__) . '/lib/ConsignmentsService.php';

    // JSON preferred, fallback to POST fields
    $raw  = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) { $data = $_POST; }

    $action = (string)($data['action'] ?? '');
    $requestId = 'req_' . bin2hex(random_bytes(6));

    // Optional: CSRF + XHR checks (use CIS helpers if present)
    if (function_exists('cis_require_xhr') && !cis_require_xhr()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'XHR required', 'error_code' => 'XHR_ONLY', 'request_id' => $requestId]);
        exit;
    }
    if (function_exists('cis_csrf_verify') && !cis_csrf_verify()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token', 'error_code' => 'CSRF', 'request_id' => $requestId]);
        exit;
    }

    // Prepare dependencies
    $pdo = cis_resolve_pdo(); // from CIS app bootstrap
    $vendBase  = \ConsignmentsService::resolveVendBaseUrl($pdo);
    $vendToken = \ConsignmentsService::resolveLightspeedToken($pdo);
    $ls = new \LightspeedClient($vendBase, $vendToken);

    switch ($action) {
        case 'submit_transfer': {
            $transferId = (int)($data['transfer_id'] ?? 0);
            $items = $data['items'] ?? $data['products'] ?? [];
            $notes = (string)($data['notes']['internal'] ?? $data['notes'] ?? '');

            if ($transferId <= 0 || !is_array($items) || !$items) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid input',
                    'error_code' => 'INVALID_INPUT',
                    'request_id' => $requestId
                ]);
                exit;
            }

            $svc = new \ConsignmentsService($pdo, $ls);
            $contract = $svc->submitTransferAndPrepareUpload($transferId, $items, $notes);

            echo json_encode([
                'success' => true,
                'request_id' => $requestId,
                'message' => 'Transfer saved. Ready to upload to Lightspeed.',
                'upload_mode' => 'direct',
                'upload_session_id' => $contract['upload_session_id'],
                'upload_url' => $contract['upload_url'],
                'progress_url' => $contract['progress_url'],
            ]);
            exit;
        }

        case 'ping': {
            echo json_encode(['success' => true, 'pong' => true, 'request_id' => $requestId]); exit;
        }

        default: {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action', 'error_code' => 'UNKNOWN_ACTION', 'request_id' => $requestId]);
            exit;
        }
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'error_code' => 'SERVER_ERROR']);
}
