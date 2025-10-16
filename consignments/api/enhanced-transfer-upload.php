<?php
/**
 * Enhanced Transfer Upload — QUEUE MODE (progress via SSE)
 * - Validates transfer
 * - Prepares progress row
 * - Creates queue_jobs entry
 * - Kicks off process-consignment-upload.php in background
 * - Returns progress_url for SSE modal
 *
 * POST: transfer_id (int), force_create=0|1 (optional)
 */
declare(strict_types=1);

header('Content-Type: application/json');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new Exception('Method not allowed (POST only)');
    }

    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    $data = is_array($json) ? $json : $_POST;

    $transferId  = (int)($data['transfer_id'] ?? 0);
    $forceCreate = !empty($data['force_create']);
    if ($transferId <= 0) throw new Exception('Invalid transfer_id');

    require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php'; // $pdo
    if (!isset($pdo) || !$pdo instanceof PDO) throw new Exception('DB unavailable');

    // Ensure progress tables exist
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS consignment_upload_progress (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        transfer_id INT UNSIGNED NOT NULL,
        session_id VARCHAR(64) NOT NULL,
        status ENUM('pending','connecting','created','adding_products','updating_state','completed','failed') NOT NULL DEFAULT 'pending',
        total_products INT UNSIGNED NOT NULL DEFAULT 0,
        completed_products INT UNSIGNED NOT NULL DEFAULT 0,
        failed_products INT UNSIGNED NOT NULL DEFAULT 0,
        current_operation VARCHAR(255) NULL,
        last_message TEXT NULL,
        performance_metrics JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_transfer_session (transfer_id, session_id),
        INDEX idx_status (status),
        INDEX idx_updated (updated_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Validate transfer & compute total to upload
    $tStmt = $pdo->prepare("
      SELECT id, state, outlet_from, outlet_to, vend_transfer_id
      FROM transfers WHERE id = ?
    ");
    $tStmt->execute([$transferId]);
    $transfer = $tStmt->fetch(PDO::FETCH_ASSOC);
    if (!$transfer) throw new Exception('Transfer not found');

    if (!$forceCreate && $transfer['state'] === 'SENT' && !empty($transfer['vend_transfer_id'])) {
        echo json_encode([
            'success' => true,
            'message' => 'Transfer already uploaded',
            'transfer_id' => $transferId,
            'idempotent' => true
        ]);
        exit;
    }

    $iStmt = $pdo->prepare("
      SELECT ti.*, vp.name AS product_name, vp.sku, vp.product_id AS vend_product_id
      FROM transfer_items ti
      LEFT JOIN vend_products vp ON vp.id = ti.product_id
      WHERE ti.transfer_id = ?
    ");
    $iStmt->execute([$transferId]);
    $items = $iStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$items) throw new Exception('No items on transfer');

    $toUpload = array_values(array_filter($items, fn($r) => (int)($r['qty_sent_total'] ?? 0) > 0));
    $totalProducts = count($toUpload);
    if ($totalProducts === 0) throw new Exception('All counted quantities are zero');

    // Session id for progress
    $sessionId = bin2hex(random_bytes(16));

    // Init progress row
    $ins = $pdo->prepare("
      INSERT INTO consignment_upload_progress
        (transfer_id, session_id, status, total_products, completed_products, failed_products, current_operation, last_message, created_at, updated_at)
      VALUES (?, ?, 'pending', ?, 0, 0, 'Queued for processing…', 'Queued', NOW(), NOW())
      ON DUPLICATE KEY UPDATE status='pending', total_products=VALUES(total_products),
        completed_products=0, failed_products=0, current_operation='Queued for processing…',
        last_message='Queued', updated_at=NOW()
    ");
    $ins->execute([$transferId, $sessionId, $totalProducts]);

    // Create queue job
    $payload = [
        'transfer_id' => $transferId,
        'session_id'  => $sessionId,
    ];
    $jobId = null;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
          INSERT INTO queue_jobs (job_type, payload, status, priority, created_at)
          VALUES ('transfer.create_consignment_with_progress', ?, 'pending', 8, NOW())_
