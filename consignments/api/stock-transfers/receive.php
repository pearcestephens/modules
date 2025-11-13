<?php
/**
 * Stock Transfer Receive API (Stub)
 * POST /api/stock-transfers/receive.php
 *
 * Accepts: { transfer_id:int, notes?:string, items:[{ product_id:int, expected:int, received:int, damaged?:int, barcode?:string }] }
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

function respond(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

try {
    if (!isset($_SESSION['user_id'])) {
        respond(401, ['success'=>false,'error'=>['code'=>'UNAUTHORIZED','message'=>'Authentication required']]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(405, ['success'=>false,'error'=>['code'=>'METHOD_NOT_ALLOWED','message'=>'Only POST allowed']]);
    }
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        respond(400, ['success'=>false,'error'=>['code'=>'INVALID_JSON','message'=>'Malformed JSON body']]);
    }
    if (!isset($data['transfer_id'])) {
        respond(400, ['success'=>false,'error'=>['code'=>'MISSING_TRANSFER_ID','message'=>'Transfer ID required']]);
    }
    if (!isset($data['items']) || !is_array($data['items']) || count($data['items']) === 0) {
        respond(400, ['success'=>false,'error'=>['code'=>'MISSING_ITEMS','message'=>'At least one item required']]);
    }

    $transferId = (int)$data['transfer_id'];

    $received = []; $errors = [];
    foreach ($data['items'] as $it) {
        $pid = (int)($it['product_id'] ?? 0);
        $expected = (int)($it['expected'] ?? 0);
        $receivedQty = (int)($it['received'] ?? 0);
        $damaged = (int)($it['damaged'] ?? 0);
        if ($pid <= 0) {
            $errors[] = ['item'=>$it,'error'=>'INVALID_PRODUCT'];
            continue;
        }
        $received[] = [
            'product_id'=>$pid,
            'expected'=>$expected,
            'received'=>$receivedQty,
            'damaged'=>$damaged,
            'over_under'=>$receivedQty - $expected
        ];
    }

    $success = count($errors) === 0;

    respond(200, [
        'success'=>$success,
        'data'=>[
            'transfer_id'=>$transferId,
            'received_items'=>$received,
            'errors'=>$errors,
            'timestamp'=>date('Y-m-d H:i:s')
        ],
        'message'=>$success ? 'Transfer receive recorded (stub)' : 'Recorded with item errors'
    ]);

} catch (Throwable $e) {
    respond(500, ['success'=>false,'error'=>['code'=>'INTERNAL_ERROR','message'=>'Unhandled exception','debug'=>$e->getMessage()]]);
}
