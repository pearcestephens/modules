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
    if ($transferId <= 0) throw new RuntimeException('transfer_id required');

    $pdo = Db::pdo();
    $stmt = $pdo->prepare("SELECT state_json FROM transfer_ui_sessions WHERE transfer_id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$transferId, Security::currentUserId()]);
    $row = $stmt->fetch();

    echo json_encode(['ok'=>true, 'state'=> $row['state_json'] ?? '{}']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
