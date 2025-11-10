<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

try {
    $pdo = cisv2_pdo();
    // Basic example — replace with real presence table if exists
    $rows = tryQuery($pdo, "SELECT id, first_name, last_name, outlet_name FROM vend_users WHERE active=1 ORDER BY RAND() LIMIT 6");
    $staff = array_map(function($r){
        return [
            'id' => (int)$r['id'],
            'name' => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
            'outlet' => $r['outlet_name'] ?? 'Store'
        ];
    }, $rows);
    respond(['ok'=>true,'data'=>$staff,'time'=>gmdate('c')]);
} catch (Throwable $e) {
    respond(['ok'=>false,'error'=>'staff_unavailable'], 500);
}