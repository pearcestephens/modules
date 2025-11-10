<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

try {
    $pdo = cisv2_pdo();
    $sql = "
        SELECT 'Transfer' as type, created_at, CONCAT('Transfer ', public_id, ' ', status) as text
        FROM stock_transfers
        ORDER BY created_at DESC LIMIT 5
    ";
    $rows = tryQuery($pdo, $sql);
    $items = array_map(function($r){
        return [
            'type' => $r['type'],
            'when' => substr(str_replace('T',' ', $r['created_at'] ?? ''), 0, 16),
            'text' => $r['text']
        ];
    }, $rows);
    respond(['ok'=>true,'data'=>$items,'time'=>gmdate('c')]);
} catch (Throwable $e) {
    respond(['ok'=>false,'error'=>'activity_unavailable'], 500);
}