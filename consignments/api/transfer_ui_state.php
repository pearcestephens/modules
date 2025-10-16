<?php
declare(strict_types=1);

/**
 * Autosave/Load + Draft + State Changes
 * POST JSON:
 *   { action:'save', state:{transfer_id:int, notes:string, items:{product_id:int}, freight:{...}} }
 *   { action:'save_draft', state:{...} }  // mirrors to transfers.draft_data + draft_updated_at
 *   { action:'load', transfer_id:int }
 *   { action:'set_state', transfer_id:int, state:'PACKAGED'|'CANCELLED'|... }
 */

header('Content-Type: application/json');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method Not Allowed']); exit; }

    require_once dirname(__DIR__) . '/bootstrap.php';
    $raw = file_get_contents('php://input') ?: '';
    $j = json_decode($raw, true);
    if (!is_array($j)) $j = $_POST;

    $action = (string)($j['action'] ?? 'save');

    $userId = null;
    if (function_exists('current_user_id')) $userId = (int)current_user_id();
    elseif (function_exists('cis_current_user_id')) $userId = (int)cis_current_user_id();
    else $userId = (int)($_SESSION['user']['id'] ?? 0);

    $pdo = cis_resolve_pdo();

    if ($action === 'save' || $action === 'save_draft') {
        $state = $j['state'] ?? null;
        if (!is_array($state)) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Missing state']); exit; }
        $tid = (int)($state['transfer_id'] ?? ($j['transfer_id'] ?? 0));
        if ($tid <= 0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Invalid transfer_id']); exit; }
        $json = json_encode($state, JSON_UNESCAPED_UNICODE);
        // session save
        $sql = "
            INSERT INTO transfer_ui_sessions (transfer_id, user_id, state_json, autosave_at, resumed_at, expires_at)
            VALUES (:tid, :uid, :json, NOW(), NULL, DATE_ADD(NOW(), INTERVAL 4 HOUR))
            ON DUPLICATE KEY UPDATE state_json=VALUES(state_json), autosave_at=VALUES(autosave_at), expires_at=VALUES(expires_at)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':tid'=>$tid, ':uid'=>$userId, ':json'=>$json]);

        if ($action === 'save_draft') {
            $stmt = $pdo->prepare("UPDATE transfers SET draft_data=:d, draft_updated_at=NOW(), updated_at=NOW() WHERE id=:id");
            $stmt->execute([':d'=>$json, ':id'=>$tid]);
        }

        echo json_encode(['success'=>true]); exit;
    }

    if ($action === 'load') {
        $tid = (int)($j['transfer_id'] ?? 0);
        if ($tid <= 0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Invalid transfer_id']); exit; }

        // 1) session
        $s = $pdo->prepare("SELECT state_json, autosave_at FROM transfer_ui_sessions WHERE transfer_id=? AND user_id=? LIMIT 1");
        $s->execute([$tid, $userId]);
        $sess = $s->fetch(PDO::FETCH_ASSOC);

        // 2) draft
        $d = $pdo->prepare("SELECT draft_data, draft_updated_at FROM transfers WHERE id=? LIMIT 1");
        $d->execute([$tid]);
        $draft = $d->fetch(PDO::FETCH_ASSOC);

        $use = null; $src = null;
        if ($sess && $sess['state_json']) { $use = $sess['state_json']; $src='session'; }
        elseif ($draft && $draft['draft_data']) { $use = $draft['draft_data']; $src='draft'; }

        echo json_encode(['success'=>true, 'source'=>$src, 'state'=>$use ? json_decode((string)$use, true) : null]); exit;
    }

    if ($action === 'set_state') {
        $tid = (int)($j['transfer_id'] ?? 0);
        $state = (string)($j['state'] ?? '');
        if ($tid <= 0 || $state === '') { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Invalid input']); exit; }

        $okStates = ['PACKAGED','CANCELLED','OPEN','PACKING','SENT','RECEIVING','RECEIVED','CLOSED'];
        if (!in_array($state, $okStates, true)) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Invalid state']); exit; }

        $pdo->prepare("UPDATE transfers SET state=?, updated_at=NOW() WHERE id=?")->execute([$state, $tid]);
        $pdo->prepare("INSERT INTO transfer_logs (transfer_id, event_type, severity, event_data, created_at) VALUES (?, 'STATE_CHANGED', 'info', ?, NOW())")
            ->execute([$tid, json_encode(['to'=>$state], JSON_UNESCAPED_UNICODE)]);

        echo json_encode(['success'=>true]); exit;
    }

    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Unknown action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
