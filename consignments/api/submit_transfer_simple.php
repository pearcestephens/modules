<?php
declare(strict_types=1);

/**
 * Submit Transfer API — UNIFIED VERSION (uses CIS bootstrap)
 * -----------------------------------------------------------------------
 * - Uses bootstrap + cis_resolve_pdo() (no ad-hoc DB)
 * - Locks transfer, writes per-line qty_sent_total, moves to PACKING (not SENT) until Vend confirms
 * - Returns upload contract (upload_mode, upload_session_id, upload_url, progress_url) for JS pipeline
 * 
 * @version 3.0.0 - Unified with CIS bootstrap, PACKING state until Vend confirms
 */

// submit_transfer_simple.php (REPLACEMENT - unified via CIS bootstrap)
require_once __DIR__ . '/../bootstrap.php';

use PDO;
use RuntimeException;
use Throwable;

header('Content-Type: application/json');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed', 'error_code' => 'METHOD_NOT_ALLOWED']);
        exit;
    }

    // JSON first, then form-data
    $raw  = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) { $data = $_POST; }

    $transferId = (int)($data['transfer_id'] ?? 0);
    $items      = $data['items'] ?? $data['products'] ?? [];
    $notes      = trim((string)($data['notes']['internal'] ?? $data['notes'] ?? ''));

    if ($transferId <= 0 || !is_array($items) || !$items) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Bad input', 'error_code' => 'INVALID_INPUT']);
        exit;
    }

    // 🔗 One source of truth for DB (CIS)
    $pdo = cis_resolve_pdo(); // from shared/config.php
    $pdo->beginTransaction();

    // Lock transfer; only OPEN/PACKING allowed
    $t = $pdo->prepare("SELECT id, public_id, state, outlet_from, outlet_to, created_by
                        FROM transfers
                        WHERE id = ? AND state IN ('OPEN','PACKING')
                        FOR UPDATE");
    $t->execute([$transferId]);
    $transfer = $t->fetch(PDO::FETCH_ASSOC);
    if (!$transfer) {
        throw new RuntimeException("Transfer not found or not in OPEN/PACKING");
    }

    // Prepare statements
    $sel = $pdo->prepare("SELECT id, qty_requested FROM transfer_items WHERE transfer_id = ? AND product_id = ?");
    $upd = $pdo->prepare("UPDATE transfer_items
                          SET qty_sent_total = ?, updated_at = CURRENT_TIMESTAMP
                          WHERE id = ?");

    $processed = 0;
    foreach ($items as $row) {
        if (!is_array($row)) continue;
        $productId  = (string)($row['product_id'] ?? '');
        $countedQty = (int)($row['counted_qty'] ?? 0);
        if ($productId === '' || $countedQty < 0) continue;

        $sel->execute([$transferId, $productId]);
        $line = $sel->fetch(PDO::FETCH_ASSOC);
        if (!$line) { throw new RuntimeException("Product {$productId} not in transfer"); }

        $toSend = min($countedQty, (int)$line['qty_requested']); // clamp
        $upd->execute([$toSend, (int)$line['id']]);
        $processed++;
    }
    if ($processed === 0) throw new RuntimeException('No valid items processed');

    // Put transfer into PACKING, append notes
    $u = $pdo->prepare("UPDATE transfers
                        SET state = 'PACKING',
                            notes = CONCAT(COALESCE(notes,''), :notes),
                            updated_at = NOW()
                        WHERE id = :id");
    $u->execute([':id' => $transferId, ':notes' => $notes !== '' ? ("\n[" . date('c') . "] " . $notes) : '']);

    // Create (or reuse) an upload session id
    $uploadSessionId = 'upload_' . bin2hex(random_bytes(8));
    $pdo->commit();

    // Hand the pipeline everything it needs
    echo json_encode([
        'success' => true,
        'message' => 'Transfer saved. Ready to upload to Vend.',
        'upload_mode' => 'direct', // or 'queue' if you want workers
        'upload_session_id' => $uploadSessionId,
        'upload_url'    => "/modules/consignments/api/simple-upload-direct.php",
        'progress_url'  => "/modules/consignments/api/consignment-upload-progress-simple.php?transfer_id={$transferId}&session_id={$uploadSessionId}",
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => 'SERVER_ERROR'
    ]);
}
