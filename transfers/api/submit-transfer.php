<?php
/**
 * Submit Transfer API (transactional, idempotent-friendly)
 */

declare(strict_types=1);

header('Content-Type: application/json');

use PDO;
use RuntimeException;
use Throwable;

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST', true, 405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
        exit;
    }

    if (!function_exists('db')) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
    }
    require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/shared/functions/config.php';

    $pdo = cis_resolve_pdo();

    $rawInput = file_get_contents('php://input') ?: '';
    $payload = json_decode($rawInput, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $transferId = (int)($payload['transfer_id'] ?? 0);
    $items = $payload['items'] ?? [];
    $notes = trim((string)($payload['notes'] ?? ''));
    $requestId = substr((string)($_SERVER['HTTP_X_REQUEST_ID'] ?? ''), 0, 128);

    if ($transferId <= 0 || !is_array($items)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Bad input']);
        exit;
    }

    $pdo->beginTransaction();

    $transferStmt = $pdo->prepare('SELECT id, state FROM transfers WHERE id = ? FOR UPDATE');
    $transferStmt->execute([$transferId]);
    $transfer = $transferStmt->fetch(PDO::FETCH_ASSOC);
    if (!$transfer) {
        throw new RuntimeException('Transfer not found');
    }
    if (!in_array($transfer['state'], ['OPEN', 'PACKING', 'SUBMITTED'], true)) {
        throw new RuntimeException('Transfer not in a submit-ready state');
    }

    $selectItem = $pdo->prepare('SELECT product_id FROM transfer_items WHERE transfer_id = ? AND product_id = ?');
    $updateItem = $pdo->prepare('UPDATE transfer_items SET counted_qty = ?, updated_at = NOW() WHERE transfer_id = ? AND product_id = ?');
    $insertItem = $pdo->prepare(
        'INSERT INTO transfer_items (transfer_id, product_id, requested_qty, counted_qty, created_at, updated_at)
         VALUES (?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE counted_qty = VALUES(counted_qty), updated_at = NOW()'
    );

    foreach ($items as $item) {
        $productId = (string)($item['product_id'] ?? '');
        if ($productId === '') {
            continue;
        }
        $countedQty = (float)($item['counted_qty'] ?? 0);
        $requestedQty = (float)($item['requested_qty'] ?? $item['planned_qty'] ?? $countedQty);

        $selectItem->execute([$transferId, $productId]);
        if ($selectItem->fetch()) {
            $updateItem->execute([$countedQty, $transferId, $productId]);
        } else {
            $insertItem->execute([$transferId, $productId, $requestedQty, $countedQty]);
        }
    }

    $updateTransfer = $pdo->prepare(
        "UPDATE transfers
            SET state = 'SUBMITTED',
                notes = CONCAT(COALESCE(notes, ''), :notes),
                updated_at = NOW()
          WHERE id = :id"
    );
    $updateTransfer->execute([
        ':id' => $transferId,
        ':notes' => $notes !== '' ? "\n[" . date('c') . "] " . $notes : ''
    ]);

    $audit = $pdo->prepare(
        'INSERT INTO transfer_submissions (transfer_id, request_id, payload_json, created_at)
         VALUES (?, ?, ?, NOW())'
    );
    $audit->execute([
        $transferId,
        $requestId,
        json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    ]);

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'transfer_id' => $transferId,
        'state' => 'SUBMITTED'
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $status = $e instanceof RuntimeException ? 400 : 500;
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
