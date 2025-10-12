<?php
declare(strict_types=1);

use Transfers\Lib\Db;
use Transfers\Lib\Security;

require_once __DIR__.'/../lib/Db.php';
require_once __DIR__.'/../lib/Security.php';

header('Content-Type: application/json');

try {
    Security::assertCsrf($_POST['csrf'] ?? '');
    $pdo = Db::pdo();

    $transferId = (int)($_POST['transfer_id'] ?? 0);
    $op = $_POST['op'] ?? 'acquire';
    $ttlMin = max(1, (int)($_POST['ttl_min'] ?? 10));

    if ($op === 'acquire') {
        $stmt = $pdo->prepare("INSERT INTO transfer_pack_locks (transfer_id, user_id, acquired_at, expires_at, heartbeat_at, client_fingerprint)
                               VALUES (?,?, NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW(), ?)
                               ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), heartbeat_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL ? MINUTE), client_fingerprint = VALUES(client_fingerprint)");
        $stmt->execute([$transferId, Security::currentUserId(), $ttlMin, Security::clientFingerprint(), $ttlMin]);
        echo json_encode(['ok'=>true, 'locked'=>true]);
        exit;
    }

    if ($op === 'heartbeat') {
        $stmt = $pdo->prepare("UPDATE transfer_pack_locks SET heartbeat_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE transfer_id = ? AND user_id = ?");
        $stmt->execute([$ttlMin, $transferId, Security::currentUserId()]);
        echo json_encode(['ok'=>true]); exit;
    }

    if ($op === 'release') {
        $stmt = $pdo->prepare("DELETE FROM transfer_pack_locks WHERE transfer_id = ? AND user_id = ?");
        $stmt->execute([$transferId, Security::currentUserId()]);
        echo json_encode(['ok'=>true]); exit;
    }

    throw new RuntimeException('Invalid op');

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
