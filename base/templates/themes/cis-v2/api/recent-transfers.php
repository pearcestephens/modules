<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$limit = (int)($_GET['limit'] ?? 5);
if ($limit < 1 || $limit > 50) $limit = 5;

try {
    $pdo = cisv2_pdo();
    $rows = tryQuery($pdo, "SELECT id, public_id, outlet_from, outlet_to, total_qty, total_value, status, DATE_FORMAT(created_at,'%b %e') as created FROM stock_transfers ORDER BY created_at DESC LIMIT $limit");
    // Map rows for UI
    $data = array_map(function($r){
        return [
            'id' => (int)$r['id'],
            'public_id' => $r['public_id'] ?? ('TR-' . $r['id']),
            'from' => $r['outlet_from'] ?? null,
            'to' => $r['outlet_to'] ?? null,
            'qty' => (int)($r['total_qty'] ?? 0),
            'value' => (float)($r['total_value'] ?? 0),
            'status' => $r['status'] ?? 'UNKNOWN',
            'date' => $r['created'] ?? ''
        ];
    }, $rows);
    respond(['ok'=>true,'data'=>$data,'time'=>gmdate('c')]);
} catch (Throwable $e) {
    respond(['ok'=>false,'error'=>'transfers_unavailable'], 500);
}