<?php
declare(strict_types=1);

use Transfers\Lib\Db;
use Transfers\Lib\Security;

require_once __DIR__.'/../lib/Db.php';
require_once __DIR__.'/../lib/Security.php';

header('Content-Type: application/json');

try {
    Security::assertCsrf($_POST['csrf'] ?? '');
    $transferId = (int)($_POST['transfer_id'] ?? 0);
    $stateJson  = (string)($_POST['state_json'] ?? '{}');

    $pdo = Db::pdo();
    $stmt = $pdo->prepare("INSERT INTO transfer_ui_sessions (transfer_id, user_id, state_json, autosave_at)
                           VALUES (?,?,?, NOW())
                           ON DUPLICATE KEY UPDATE state_json = VALUES(state_json), autosave_at = NOW()");
    $stmt->execute([$transferId, Security::currentUserId(), $stateJson]);
    echo json_encode(['ok'=>true]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
