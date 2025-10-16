<?php
declare(strict_types=1);

header('Content-Type: application/json');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method Not Allowed']); exit; }
    require_once dirname(__DIR__) . '/bootstrap.php';
    $j = json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST;

    $tid  = (int)($j['transfer_id'] ?? 0);
    $lim  = max(10, min(100, (int)($j['limit'] ?? 50)));
    if ($tid <= 0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Invalid transfer_id']); exit; }

    $pdo = cis_resolve_pdo();

    $notes = $pdo->prepare("SELECT 'note' AS kind, tn.created_at AS created_at,
                                   tn.note_text AS text, COALESCE(u.display_name, CONCAT('User#', tn.created_by)) AS actor_display,
                                   'user' AS actor_type, NULL AS severity
                              FROM transfer_notes tn
                         LEFT JOIN vend_users u ON u.id = CAST(tn.created_by AS CHAR)
                             WHERE tn.transfer_id = ?
                          ORDER BY tn.created_at DESC
                             LIMIT ?");
    $notes->execute([$tid, $lim]);
    $N = $notes->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $logs = $pdo->prepare("SELECT 'system' AS kind, tl.created_at AS created_at,
                                  CONCAT(tl.event_type, COALESCE(CONCAT(': ', tl.event_data), '')) AS text,
                                  'system' AS actor_type, tl.severity AS severity, NULL AS actor_display
                             FROM transfer_logs tl
                            WHERE tl.transfer_id = ?
                              AND tl.event_type IN ('CONS_CREATED','CONS_PRODUCTS_ADDED','CONS_SENT','FREIGHT_UPDATED','UPLOAD_STARTED','UPLOAD_COMPLETED','UPLOAD_FAILED','STATE_CHANGED')
                         ORDER BY tl.created_at DESC
                            LIMIT ?");
    $logs->execute([$tid, $lim]);
    $L = $logs->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $items = array_merge($N, $L);
    usort($items, fn($a,$b)=> strcmp((string)$b['created_at'], (string)$a['created_at']));
    if (count($items) > $lim) $items = array_slice($items, 0, $lim);

    // Placeholders if empty
    $placeholders = [];
    if (!$items) {
        $t = $pdo->prepare("SELECT created_at, state FROM transfers WHERE id=? LIMIT 1");
        $t->execute([$tid]);
        $row = $t->fetch(PDO::FETCH_ASSOC) ?: [];
        $when = $row['created_at'] ?? date('Y-m-d H:i:s');
        $state = $row['state'] ?? 'OPEN';
        $placeholders = [
            ['kind'=>'system','created_at'=>$when,'text'=>'Transfer created','actor_type'=>'system','actor_display'=>'System'],
            ['kind'=>'system','created_at'=>$when,'text'=>"State: {$state}",'actor_type'=>'system','actor_display'=>'System']
        ];
    }

    echo json_encode(['success'=>true, 'items'=>$items, 'placeholders'=>$placeholders]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
