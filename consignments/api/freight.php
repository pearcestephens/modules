<?php
declare(strict_types=1);

/**
 * Freight API
 *
 * Exposes safe, non-secret courier configuration state per outlet and
 * provides server-side actions that use credentials without exposing them
 * to the browser.
 *
 * Endpoints (POST JSON):
 * - action: outlet_status { outlet_id:int }
 * - action: multi_outlet_status { outlet_ids:int[] }
 * - action: carriers {}
 *
 * Notes:
 * - Never returns raw secrets. Only masked previews and presence flags.
 * - Real rate quotes and label creations should be separate actions which
 *   call carrier SDKs server-side using resolved credentials.
 */

// Bootstrap and auth
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../infra/Freight/CredentialsProvider.php';

use Consignments\Infra\Freight\CredentialsProvider;

header('Content-Type: application/json');
header('Cache-Control: no-store');

// Require standard role for ops/logistics
ConsignAuth::requireRole('ops');

// Only POST JSON
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'method not allowed']);
    exit;
}

// Input
$inRaw = file_get_contents('php://input') ?: '{}';
$in = json_decode($inRaw, true) ?: [];
$action = (string)($in['action'] ?? '');
$data = (array)($in['data'] ?? []);

// Resolve PDO (re-use pattern from existing APIs)
// Resolve PDO via module bootstrap helper
$pdo = isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? $GLOBALS['pdo'] : (function(){
    if (function_exists('cis_resolve_pdo')) { return cis_resolve_pdo(); }
    // Fallback to new PDO via env constants if available
    if (defined('DB_HOST')) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
        return new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    }
    throw new RuntimeException('PDO not available');
})();
$provider = new CredentialsProvider($pdo);

try {
    switch ($action) {
        case 'carriers':
            echo json_encode(['ok'=>true, 'data'=>['carriers'=>$provider->carriers()]]);
            break;
        case 'outlet_status':
            $oid = (int)($data['outlet_id'] ?? 0);
            if ($oid <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid outlet_id']); break; }
            $res = $provider->getOutletStatus($oid);
            echo json_encode(['ok'=>true,'data'=>$res]);
            break;
        case 'multi_outlet_status':
            $ids = $data['outlet_ids'] ?? [];
            if (!is_array($ids) || empty($ids)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'outlet_ids required']); break; }
            $ids = array_values(array_filter(array_map('intval', $ids), fn($v)=>$v>0));
            $res = $provider->getMultiOutletStatus($ids);
            echo json_encode(['ok'=>true,'data'=>$res]);
            break;
        default:
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'unknown action']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'freight api error']);
}
