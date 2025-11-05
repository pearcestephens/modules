<?php
/**
 * Barcode Scan Logging API
 * Records barcode scan events to database
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../../app/bootstrap.php';

$db = getDb();
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

try {
    $stmt = $db->prepare("
        INSERT INTO BARCODE_SCANS (
            transfer_id,
            consignment_id,
            purchase_order_id,
            barcode_value,
            barcode_format,
            scan_method,
            vend_product_id,
            sku,
            product_name,
            scan_result,
            qty_scanned,
            audio_feedback,
            user_id,
            outlet_id,
            scan_duration_ms,
            user_agent,
            scan_timestamp
        ) VALUES (
            :transfer_id,
            :consignment_id,
            :purchase_order_id,
            :barcode_value,
            :barcode_format,
            :scan_method,
            :vend_product_id,
            :sku,
            :product_name,
            :scan_result,
            :qty_scanned,
            :audio_feedback,
            :user_id,
            :outlet_id,
            :scan_duration_ms,
            :user_agent,
            NOW()
        )
    ");

    $stmt->execute([
        'transfer_id' => $input['transfer_id'] ?? null,
        'consignment_id' => $input['consignment_id'] ?? null,
        'purchase_order_id' => $input['purchase_order_id'] ?? null,
        'barcode_value' => $input['barcode_value'],
        'barcode_format' => $input['barcode_format'] ?? 'UNKNOWN',
        'scan_method' => $input['scan_method'] ?? 'manual_entry',
        'vend_product_id' => $input['vend_product_id'] ?? null,
        'sku' => $input['sku'] ?? null,
        'product_name' => $input['product_name'] ?? null,
        'scan_result' => $input['scan_result'] ?? 'success',
        'qty_scanned' => $input['qty_scanned'] ?? 1,
        'audio_feedback' => $input['audio_feedback'] ?? 'tone1',
        'user_id' => $input['user_id'] ?? null,
        'outlet_id' => $input['outlet_id'] ?? null,
        'scan_duration_ms' => $input['scan_duration_ms'] ?? null,
        'user_agent' => $input['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $scanId = $db->lastInsertId();

    // Update user stats
    if ($input['user_id']) {
        updateUserStats($db, $input['user_id'], $input['outlet_id'] ?? null);
    }

    echo json_encode([
        'success' => true,
        'scan_id' => $scanId,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Update user statistics after scan
 */
function updateUserStats($db, $userId, $outletId) {
    try {
        // Check if prefs exist
        $check = $db->prepare("
            SELECT id FROM BARCODE_USER_PREFERENCES
            WHERE user_id = ? AND (outlet_id = ? OR (outlet_id IS NULL AND ? IS NULL))
        ");
        $check->execute([$userId, $outletId, $outletId]);

        if ($check->fetch()) {
            // Update existing
            $stmt = $db->prepare("
                UPDATE BARCODE_USER_PREFERENCES SET
                    total_scans = total_scans + 1,
                    last_scan_at = NOW()
                WHERE user_id = ? AND (outlet_id = ? OR (outlet_id IS NULL AND ? IS NULL))
            ");
            $stmt->execute([$userId, $outletId, $outletId]);
        } else {
            // Create new
            $stmt = $db->prepare("
                INSERT INTO BARCODE_USER_PREFERENCES (user_id, outlet_id, total_scans, last_scan_at)
                VALUES (?, ?, 1, NOW())
            ");
            $stmt->execute([$userId, $outletId]);
        }
    } catch (Exception $e) {
        error_log("Failed to update user stats: " . $e->getMessage());
    }
}
