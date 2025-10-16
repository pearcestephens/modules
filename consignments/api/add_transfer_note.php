<?php
declare(strict_types=1);

/**
 * Add a staff comment to transfer_notes
 * POST JSON: { transfer_id:int, note_text:string }
 */

header('Content-Type: application/json');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method Not Allowed']); exit; }

    require_once dirname(__DIR__) . '/bootstrap.php';
    $raw = file_get_contents('php://input') ?: '';
    $j = json_decode($raw, true);
    if (!is_array($j)) { $j = $_POST; }

    $tid  = (int)($j['transfer_id'] ?? 0);
    $text = trim((string)($j['note_text'] ?? ''));

    if ($tid <= 0 || $text === '') { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Invalid input']); exit; }

    $userId = null;
    if (function_exists('current_user_id')) $userId = (int)current_user_id();
    elseif (function_exists('cis_current_user_id')) $userId = (int)cis_current_user_id();
    else $userId = (int)($_SESSION['user']['id'] ?? 0);

    $pdo = cis_resolve_pdo();
    $pdo->prepare("INSERT INTO transfer_notes (transfer_id, note_text, created_by, created_at) VALUES (?, ?, ?, NOW())")
        ->execute([$tid, $text, $userId]);

    echo json_encode(['success'=>true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
