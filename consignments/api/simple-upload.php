<?php
/**
 * REAL Upload - Actually Creates Vend Consignment with Full Error Handling
 * 
 * @version 3.0.0 - NO MORE BULLSHIT
 */

declare(strict_types=1);

// Disable output buffering for clean JSON
if (ob_get_level()) ob_end_clean();

// Set JSON header immediately
header('Content-Type: application/json');

// Basic validation
if (empty($_POST['transfer_id']) || !is_numeric($_POST['transfer_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'Invalid or missing transfer_id',
        'error_code' => 'INVALID_TRANSFER_ID'
    ]);
    exit;
}

if (empty($_POST['session_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'Invalid or missing session_id',
        'error_code' => 'INVALID_SESSION_ID'
    ]);
    exit;
}

$transferId = (int) $_POST['transfer_id'];
$sessionId = trim($_POST['session_id']);

// Connect to database
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

// Ensure $dbo is bound to the active PDO connection
if (!isset($dbo) || !$dbo instanceof PDO) {
    if (isset($pdo) && $pdo instanceof PDO) {
        $dbo = $pdo; // alias
    } else {
        throw new Exception('Database connection not available');
    }
}

// Load Lightspeed API client
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/api/lightspeed.php';

try {
    // START TRANSACTION
    $dbo->beginTransaction();
    error_log("🔒 [Transfer #{$transferId}] Transaction started");
    
    // ========================================================================
    // STEP 1: Get transfer data
    // ========================================================================
    $stmt = $dbo->prepare("
        SELECT 
            t.*,
            src.outlet_id as source_outlet_id,
            src.name AS source_name,
            dst.outlet_id as destination_outlet_id,
            dst.name AS destination_name
        FROM stock_transfers t
        LEFT JOIN vend_outlets src ON t.source_outlet_id = src.outlet_id
        LEFT JOIN vend_outlets dst ON t.destination_outlet_id = dst.outlet_id
        WHERE t.id = ?
    ");
    $stmt->execute([$transferId]);
    $transfer = $stmt->fetch(PDO::FETCH_OBJ);
    
    if (!$transfer) {
        throw new Exception("Transfer #{$transferId} not found in database");
    }
    
    error_log("📦 [Transfer #{$transferId}] Found: {$transfer->source_name} → {$transfer->destination_name}");
    
    // Check transfer state
    if ($transfer->state === 'SENT') {
        throw new Exception("Transfer #{$transferId} has already been sent (state: {$transfer->state})");
    }
    
    // ========================================================================
    // STEP 2: Get transfer items
    // ========================================================================
    $stmt = $dbo->prepare("
        SELECT 
            ti.*,
            p.name as product_name,
            p.sku,
            p.product_id as vend_product_id
        FROM transfer_items ti
        LEFT JOIN vend_products p ON ti.product_id = p.product_id
        WHERE ti.transfer_id = ?
    ");
    $stmt->execute([$transferId]);
    $items = $stmt->fetchAll(PDO::FETCH_OBJ);
    
    if (empty($items)) {
        throw new Exception("Transfer #{$transferId} has no items - cannot create consignment");
    }
    
    error_log("📋 [Transfer #{$transferId}] Found " . count($items) . " items");
    
    // ========================================================================
    // STEP 3: Build consignment note
    // ========================================================================
    $consignmentNote = sprintf(
        "%s Transfer #%d",
        $transfer->transfer_category === 'SUPPLIER' ? 'Supplier to Outlet' : 'Outlet to Outlet',
        $transferId
    );
    
    error_log("📝 [Transfer #{$transferId}] Consignment note: {$consignmentNote}");
    
    // ========================================================================
    // STEP 4: CREATE REAL VEND CONSIGNMENT
    // ========================================================================
    try {
        $lightspeedApi = new VendLightspeedAPI();
        
        $consignmentData = [
            'outlet_id' => $transfer->destination_outlet_id,
            'source_outlet_id' => $transfer->source_outlet_id,
            'type' => 'OUTLET',
            'status' => 'OPEN',
            'name' => $consignmentNote,
            'reference' => "CIS-{$transferId}"
        ];
        
        error_log("🚀 [Transfer #{$transferId}] Creating Vend consignment...");
        error_log("📤 [Transfer #{$transferId}] Payload: " . json_encode($consignmentData));
        
        $vendResponse = $lightspeedApi->createConsignment($consignmentData);
        
        if (!$vendResponse['success']) {
            $errorMsg = $vendResponse['error'] ?? 'Unknown Vend API error';
            $errorCode = $vendResponse['error_code'] ?? 'VEND_API_ERROR';
            error_log("❌ [Transfer #{$transferId}] Vend API failed: {$errorMsg}");
            throw new Exception("Vend API Error: {$errorMsg} (Code: {$errorCode})");
        }
        
        $vendConsignmentId = $vendResponse['data']['id'] ?? null;
        
        if (!$vendConsignmentId) {
            error_log("❌ [Transfer #{$transferId}] Vend response missing consignment ID");
            error_log("📤 [Transfer #{$transferId}] Full response: " . json_encode($vendResponse));
            throw new Exception("Vend returned success but no consignment ID");
        }
        
        error_log("✅ [Transfer #{$transferId}] Vend consignment created: {$vendConsignmentId}");
        
    } catch (Exception $vendError) {
        error_log("💥 [Transfer #{$transferId}] Vend API exception: " . $vendError->getMessage());
        throw new Exception("Failed to create Vend consignment: " . $vendError->getMessage());
    }
    
    // ========================================================================
    // STEP 5: Store in queue_consignments
    // ========================================================================
    $stmt = $dbo->prepare("
        INSERT INTO queue_consignments 
        (transfer_id, vend_consignment_id, outlet_from_id, outlet_to_id, status, sync_status, created_at)
        VALUES (?, ?, ?, ?, 'OPEN', 'synced', NOW())
        ON DUPLICATE KEY UPDATE
            vend_consignment_id = VALUES(vend_consignment_id),
            sync_status = 'synced',
            updated_at = NOW()
    ");
    
    $stmt->execute([
        $transferId,
        $vendConsignmentId,
        $transfer->source_outlet_id,
        $transfer->destination_outlet_id
    ]);
    
    // Verify insertion
    $stmt = $dbo->prepare("
        SELECT id, vend_consignment_id, status, created_at 
        FROM queue_consignments 
        WHERE transfer_id = ?
    ");
    $stmt->execute([$transferId]);
    $queueRecord = $stmt->fetch(PDO::FETCH_OBJ);
    
    if (!$queueRecord) {
        throw new Exception("Failed to store consignment in queue_consignments table");
    }
    
    error_log("✅ [Transfer #{$transferId}] Queue record created: ID {$queueRecord->id}");
    
    // ========================================================================
    // STEP 6: Add products to Vend consignment
    // ========================================================================
    $productsAdded = 0;
    $productsFailed = 0;
    
    foreach ($items as $item) {
        try {
            $productPayload = [
                'product_id' => $item->vend_product_id,
                'count' => (int)$item->quantity
            ];
            
            $productResponse = $lightspeedApi->addConsignmentProduct($vendConsignmentId, $productPayload);
            
            if ($productResponse['success']) {
                $productsAdded++;
                error_log("✅ [Transfer #{$transferId}] Added product: {$item->product_name} x{$item->quantity}");
            } else {
                $productsFailed++;
                error_log("⚠️ [Transfer #{$transferId}] Failed to add product: {$item->product_name} - " . ($productResponse['error'] ?? 'Unknown error'));
            }
            
        } catch (Exception $productError) {
            $productsFailed++;
            error_log("💥 [Transfer #{$transferId}] Product exception: {$item->product_name} - " . $productError->getMessage());
        }
    }
    
    error_log("📊 [Transfer #{$transferId}] Products: {$productsAdded} added, {$productsFailed} failed");
    
    if ($productsAdded === 0) {
        throw new Exception("Failed to add any products to Vend consignment - consignment created but empty");
    }
    
    // ========================================================================
    // STEP 7: Update transfer state with CORRECT COLUMNS
    // ========================================================================
    $stmt = $dbo->prepare("
        UPDATE transfers 
        SET 
            state = 'SENT', 
            vend_transfer_id = ?,
            consignment_id = ?,
            sent_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$vendConsignmentId, $queueRecord->id, $transferId]);
    
    error_log("✅ [Transfer #{$transferId}] Transfer updated: vend_transfer_id={$vendConsignmentId}, consignment_id={$queueRecord->id}");
    
    // ========================================================================
    // COMMIT TRANSACTION
    // ========================================================================
    $dbo->commit();
    error_log("✅ [Transfer #{$transferId}] Transaction committed successfully");
    
    // ========================================================================
    // SUCCESS RESPONSE
    // ========================================================================
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => "Consignment created successfully in Vend",
        'transfer_id' => $transferId,
        'session_id' => $sessionId,
        'consignment_id' => $vendConsignmentId,
        'consignment_note' => $consignmentNote,
        'vend_url' => "https://vapeshed.vendhq.com/consignment/{$vendConsignmentId}",
        'data' => [
            'total_products' => count($items),
            'products_added' => $productsAdded,
            'products_failed' => $productsFailed,
            'source' => $transfer->source_name,
            'destination' => $transfer->destination_name,
            'queue_id' => $queueRecord->id
        ]
    ]);
    
} catch (Exception $e) {
    // ========================================================================
    // ERROR HANDLING - ROLLBACK EVERYTHING
    // ========================================================================
    if ($dbo && $dbo->inTransaction()) {
        $dbo->rollBack();
        error_log("🔄 [Transfer #{$transferId}] Transaction rolled back");
    }
    
    $errorMessage = $e->getMessage();
    error_log("❌ [Transfer #{$transferId}] UPLOAD FAILED: {$errorMessage}");
    
    // Determine error code
    $errorCode = 'UPLOAD_ERROR';
    if (strpos($errorMessage, 'not found') !== false) {
        $errorCode = 'NOT_FOUND';
        http_response_code(404);
    } elseif (strpos($errorMessage, 'already been sent') !== false) {
        $errorCode = 'ALREADY_SENT';
        http_response_code(409);
    } elseif (strpos($errorMessage, 'Vend') !== false) {
        $errorCode = 'VEND_API_ERROR';
        http_response_code(502);
    } else {
        http_response_code(500);
    }
    
    echo json_encode([
        'success' => false,
        'error' => $errorMessage,
        'error_code' => $errorCode,
        'transfer_id' => $transferId,
        'timestamp' => date('c')
    ]);
}

