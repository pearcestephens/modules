<?php
declare(strict_types=1);

/**
 * Universal Transfer Functions
 * 
 * Comprehensive transfer data retrieval with flexible filtering
 * Location: /modules/consignments/shared/functions/transfers.php
 * 
 * @package CIS\Consignments\Functions
 * @version 1.0.0
 * @created 2025-10-15
 */

/**
 * Universal Transfer Function - Get any transfer with any filter combination
 * 
 * @param int|array $filters Transfer ID (int) or associative array of filters
 * @param array $options Additional options for data retrieval
 * @return stdClass|array|null Single transfer object, array of transfers, or null
 */
function getUniversalTransfer($filters = [], array $options = [])
{
    global $pdo;
    
    // Check for PDO connection - support both global $pdo and $GLOBALS['pdo']
    if (!isset($pdo) || $pdo === null) {
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] !== null) {
            $pdo = $GLOBALS['pdo'];
        } else {
            throw new Exception('Database connection not available. Ensure config.php is included and creates $pdo or $GLOBALS[\'pdo\'].');
        }
    }
    
    // If $filters is an integer, convert it to ['id' => $filters]
    if (is_int($filters)) {
        $filters = ['id' => $filters];
    }
    
    // Default options - optimized for typical use (pack/receive pages)
    $defaultOptions = [
        'return_type' => 'single', // 'single', 'multiple', 'count'
        'include_items' => true,
        'include_shipments' => false, // Skip by default - pack pages don't need this
        'include_receipts' => false, // Skip by default - pack pages don't need this
        'include_notes' => true,
        'include_audit_log' => false, // Skip for performance
        'include_ai_insights' => false, // Skip for performance
        'include_metrics' => false, // Skip for performance
        'audit_limit' => 50,
        'limit' => 100,
        'offset' => 0,
        'order_by' => 'created_at',
        'order_direction' => 'DESC'
    ];
    
    $options = array_merge($defaultOptions, $options);
    
    try {
        // Build WHERE clause and parameters
        $whereConditions = [];
        $params = [];
        
        // ID filters
        if (isset($filters['id'])) {
            $whereConditions[] = 't.id = ?';
            $params[] = $filters['id'];
        }
        
        if (isset($filters['ids']) && is_array($filters['ids'])) {
            $placeholders = str_repeat('?,', count($filters['ids']) - 1) . '?';
            $whereConditions[] = "t.id IN ($placeholders)";
            $params = array_merge($params, $filters['ids']);
        }
        
        if (isset($filters['public_id'])) {
            $whereConditions[] = 't.public_id = ?';
            $params[] = $filters['public_id'];
        }
        
        if (isset($filters['vend_id'])) {
            $whereConditions[] = 't.vend_transfer_id = ?';
            $params[] = $filters['vend_id'];
        }
        
        // Category and type filters
        if (isset($filters['transfer_category'])) {
            $whereConditions[] = 't.transfer_category = ?';
            $params[] = strtoupper($filters['transfer_category']);
        }
        
        if (isset($filters['state'])) {
            if (is_array($filters['state'])) {
                $placeholders = str_repeat('?,', count($filters['state']) - 1) . '?';
                $whereConditions[] = "t.state IN ($placeholders)";
                $params = array_merge($params, $filters['state']);
            } else {
                $whereConditions[] = 't.state = ?';
                $params[] = $filters['state'];
            }
        }
        
        // Outlet filters
        if (isset($filters['outlet_from'])) {
            $whereConditions[] = 't.outlet_from = ?';
            $params[] = $filters['outlet_from'];
        }
        
        if (isset($filters['outlet_to'])) {
            $whereConditions[] = 't.outlet_to = ?';
            $params[] = $filters['outlet_to'];
        }
        
        if (isset($filters['outlet_any'])) {
            $whereConditions[] = '(t.outlet_from = ? OR t.outlet_to = ?)';
            $params[] = $filters['outlet_any'];
            $params[] = $filters['outlet_any'];
        }
        
        // Date filters
        if (isset($filters['created_after'])) {
            $whereConditions[] = 't.created_at >= ?';
            $params[] = $filters['created_after'];
        }
        
        if (isset($filters['created_before'])) {
            $whereConditions[] = 't.created_at <= ?';
            $params[] = $filters['created_before'];
        }
        
        if (isset($filters['updated_after'])) {
            $whereConditions[] = 't.updated_at >= ?';
            $params[] = $filters['updated_after'];
        }
        
        if (isset($filters['updated_before'])) {
            $whereConditions[] = 't.updated_at <= ?';
            $params[] = $filters['updated_before'];
        }
        
        // Date range shortcuts
        if (isset($filters['today'])) {
            $whereConditions[] = 'DATE(t.created_at) = CURDATE()';
        }
        
        if (isset($filters['this_week'])) {
            $whereConditions[] = 'YEARWEEK(t.created_at) = YEARWEEK(NOW())';
        }
        
        if (isset($filters['this_month'])) {
            $whereConditions[] = 'YEAR(t.created_at) = YEAR(NOW()) AND MONTH(t.created_at) = MONTH(NOW())';
        }
        
        // User filters
        if (isset($filters['created_by'])) {
            $whereConditions[] = 't.created_by = ?';
            $params[] = $filters['created_by'];
        }
        
        if (isset($filters['updated_by'])) {
            $whereConditions[] = 't.updated_by = ?';
            $params[] = $filters['updated_by'];
        }
        
        // Search filters
        if (isset($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $whereConditions[] = '(t.public_id LIKE ? OR t.vend_number LIKE ?)';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Custom SQL filters
        if (isset($filters['custom_where'])) {
            $whereConditions[] = $filters['custom_where'];
        }
        
        if (isset($filters['custom_params']) && is_array($filters['custom_params'])) {
            $params = array_merge($params, $filters['custom_params']);
        }
        
        // Build the base query
        $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);
        
        // Handle different return types
        if ($options['return_type'] === 'count') {
            $sql = "SELECT COUNT(*) as total FROM transfers t $whereClause";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        }
        
        // Get transfer IDs first
        $orderBy = $options['order_by'];
        $orderDirection = strtoupper($options['order_direction']);
        $limit = (int)$options['limit'];
        $offset = (int)$options['offset'];
        
        $sql = "
            SELECT t.id 
            FROM transfers t 
            $whereClause 
            ORDER BY t.$orderBy $orderDirection 
            LIMIT $limit OFFSET $offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $transferIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($transferIds)) {
            return $options['return_type'] === 'single' ? null : [];
        }
        
        // Get complete transfer data for each ID
        $transfers = [];
        foreach ($transferIds as $transferId) {
            $transfer = getCompleteTransferDataById((int)$transferId, $options);
            if ($transfer) {
                $transfers[] = $transfer;
            }
        }
        
        // Return based on type
        if ($options['return_type'] === 'single') {
            return !empty($transfers) ? $transfers[0] : null;
        }
        
        return $transfers;
        
    } catch (Exception $e) {
        error_log("Universal Transfer Function Error: " . $e->getMessage());
        return $options['return_type'] === 'single' ? null : [];
    }
}

/**
 * Get complete transfer data by ID with options
 */
function getCompleteTransferDataById(int $transferId, array $options = []): ?stdClass
{
    global $pdo;
    
    try {
        // Check PDO connection
        if (!$pdo) {
            error_log("getCompleteTransferDataById($transferId): PDO connection is NULL");
            return null;
        }
        
        error_log("getCompleteTransferDataById($transferId): Starting query");
        
        // Get main transfer data
        $stmt = $pdo->prepare("
            SELECT t.*,
                   of.name as outlet_from_name,
                   ot.name as outlet_to_name
            FROM transfers t
            LEFT JOIN vend_outlets of ON t.outlet_from = of.id
            LEFT JOIN vend_outlets ot ON t.outlet_to = ot.id
            WHERE t.id = ?
        ");
        
        $executeResult = $stmt->execute([$transferId]);
        error_log("getCompleteTransferDataById($transferId): Query executed, result=" . ($executeResult ? 'TRUE' : 'FALSE'));
        
        $transferData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transferData) {
            error_log("getCompleteTransferDataById($transferId): No data returned from query");
            return null;
        }
        
        error_log("getCompleteTransferDataById($transferId): Found transfer with category={$transferData['transfer_category']}, state={$transferData['state']}");
        
        // Build transfer object with flat structure (all transfer columns at top level)
        $transfer = (object)$transferData;
        
        // Add structured outlet objects (use name for both name and short_name)
        $transfer->outlet_from = (object)[
            'id' => $transferData['outlet_from'],
            'name' => $transferData['outlet_from_name'],
            'short_name' => $transferData['outlet_from_name'] // Use full name since short_name doesn't exist
        ];
        $transfer->outlet_to = (object)[
            'id' => $transferData['outlet_to'],
            'name' => $transferData['outlet_to_name'],
            'short_name' => $transferData['outlet_to_name'] // Use full name since short_name doesn't exist
        ];
        
        // Initialize arrays
        $transfer->items = [];
        $transfer->shipments = [];
        $transfer->receipts = [];
        $transfer->notes = [];
        $transfer->audit_log = [];
        $transfer->ai_insights = [];
        $transfer->metrics = [];
        $transfer->summary = (object)[];
        
        // Get items if requested
        if ($options['include_items'] ?? true) {
            $transfer->items = getTransferItems($transferId);
        }
        
        // Get shipments if requested
        if ($options['include_shipments'] ?? true) {
            $transfer->shipments = getTransferShipments($transferId);
        }
        
        // Get receipts if requested
        if ($options['include_receipts'] ?? true) {
            $transfer->receipts = getTransferReceipts($transferId);
        }
        
        // Get notes if requested
        if ($options['include_notes'] ?? true) {
            $transfer->notes = getTransferNotes($transferId);
        }
        
        // Get audit log if requested
        if ($options['include_audit_log'] ?? false) {
            $limit = $options['audit_limit'] ?? 50;
            $transfer->audit_log = getTransferAuditLog($transferId, $limit);
        }
        
        // Get AI insights if requested
        if ($options['include_ai_insights'] ?? false) {
            $transfer->ai_insights = getTransferAIInsights($transferId);
        }
        
        // Get metrics if requested
        if ($options['include_metrics'] ?? false) {
            $transfer->metrics = getTransferMetrics($transferId);
        }
        
        // Calculate summary
        $transfer->summary = calculateTransferSummary($transfer);
        
        return $transfer;
        
    } catch (Exception $e) {
        error_log("Get Complete Transfer Data Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get transfer items
 */
function getTransferItems(int $transferId): array
{
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT ti.*, 
               vp.name as product_name, vp.sku, vp.handle,
               vp.image_url,
               COALESCE(vi.current_amount, 0) as current_stock
        FROM transfer_items ti
        LEFT JOIN vend_products vp ON ti.product_id = vp.id
        LEFT JOIN vend_inventory vi ON vp.id = vi.product_id 
            AND vi.outlet_id = (SELECT outlet_from FROM transfers WHERE id = ?)
        WHERE ti.transfer_id = ?
        ORDER BY ti.created_at ASC
    ");
    $stmt->execute([$transferId, $transferId]);
    
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

/**
 * Get transfer shipments
 */
function getTransferShipments(int $transferId): array
{
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT ts.*, 
               (SELECT COUNT(*) FROM consignment_parcels WHERE shipment_id = ts.id) as parcel_count
        FROM consignment_shipments ts
        WHERE ts.transfer_id = ?
        ORDER BY ts.created_at ASC
    ");
    $stmt->execute([$transferId]);
    
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

/**
 * Get transfer receipts
 */
function getTransferReceipts(int $transferId): array
{
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT tr.*, 
               (SELECT COUNT(*) FROM consignment_receipt_items WHERE receipt_id = tr.id) as item_count
        FROM consignment_receipts tr
        WHERE tr.transfer_id = ?
        ORDER BY tr.created_at ASC
    ");
    $stmt->execute([$transferId]);
    
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

/**
 * Get transfer notes
 */
function getTransferNotes(int $transferId): array
{
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT tn.*, 
               CONCAT(u.first_name, ' ', u.last_name) as user_name
        FROM consignment_notes tn
        LEFT JOIN vend_users u ON tn.created_by = u.id
        WHERE tn.transfer_id = ?
        ORDER BY tn.created_at DESC
    ");
    $stmt->execute([$transferId]);
    
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

/**
 * Get transfer audit log
 */
function getTransferAuditLog(int $transferId, int $limit = 50): array
{
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT tal.*, 
               CONCAT(u.first_name, ' ', u.last_name) as user_name
        FROM consignment_audit_log tal
        LEFT JOIN vend_users u ON tal.user_id = u.id
        WHERE tal.transfer_id = ?
        ORDER BY tal.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$transferId, $limit]);
    
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

/**
 * Get transfer AI insights
 */
function getTransferAIInsights(int $transferId): array
{
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM consignment_ai_insights
        WHERE transfer_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$transferId]);
    
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

/**
 * Get transfer metrics
 */
function getTransferMetrics(int $transferId): array
{
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM consignment_metrics
        WHERE transfer_id = ?
        ORDER BY recorded_at DESC
    ");
    $stmt->execute([$transferId]);
    
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

/**
 * Calculate transfer summary
 */
function calculateTransferSummary(stdClass $transfer): stdClass
{
    $summary = (object)[
        'total_items' => count($transfer->items),
        'total_qty_requested' => 0,
        'total_qty_sent' => 0,
        'total_qty_received' => 0,
        'items_complete' => 0,
        'items_partial' => 0,
        'items_pending' => 0,
        'pack_completion_pct' => 0.0,
        'receive_completion_pct' => 0.0,
        'total_shipments' => count($transfer->shipments),
        'total_receipts' => count($transfer->receipts),
        'total_notes' => count($transfer->notes)
    ];
    
    foreach ($transfer->items as $item) {
        $summary->total_qty_requested += (int)$item->qty_requested;
        $summary->total_qty_sent += (int)$item->qty_sent_total;
        $summary->total_qty_received += (int)$item->qty_received_total;
        
        if ($item->qty_sent_total >= $item->qty_requested) {
            $summary->items_complete++;
        } elseif ($item->qty_sent_total > 0) {
            $summary->items_partial++;
        } else {
            $summary->items_pending++;
        }
    }
    
    // Calculate percentages
    if ($summary->total_qty_requested > 0) {
        $summary->pack_completion_pct = round(($summary->total_qty_sent / $summary->total_qty_requested) * 100, 2);
        $summary->receive_completion_pct = round(($summary->total_qty_received / $summary->total_qty_requested) * 100, 2);
    }
    
    return $summary;
}

/**
 * Simple helper functions for easy access
 */

/**
 * Get transfer by ID (simple interface)
 */
function getTransferById(int $transferId, string $transferType = 'STOCK'): ?stdClass
{
    return getUniversalTransfer(['id' => $transferId, 'transfer_category' => $transferType]);
}

/**
 * Get transfer by public ID
 */
function getTransferByPublicId(string $publicId, string $transferType = 'STOCK'): ?stdClass
{
    return getUniversalTransfer(['public_id' => $publicId, 'transfer_category' => $transferType]);
}

/**
 * Get transfer by Vend ID
 */
function getTransferByVendId(string $vendId, string $transferType = 'STOCK'): ?stdClass
{
    return getUniversalTransfer(['vend_id' => $vendId, 'transfer_category' => $transferType]);
}

/**
 * Get recent transfers
 */
function getRecentTransfers(int $limit = 10, string $transferType = 'STOCK'): array
{
    return getUniversalTransfer(
        ['transfer_category' => $transferType],
        ['return_type' => 'multiple', 'limit' => $limit]
    );
}

/**
 * Get transfers by state
 */
function getTransfersByState(string $state, string $transferType = 'STOCK', int $limit = 50): array
{
    return getUniversalTransfer(
        ['state' => $state, 'transfer_category' => $transferType],
        ['return_type' => 'multiple', 'limit' => $limit]
    );
}

/**
 * Get transfers between outlets
 */
function getTransfersBetweenOutlets(int $outletFrom, int $outletTo, string $transferType = 'STOCK', int $limit = 50): array
{
    return getUniversalTransfer(
        ['outlet_from' => $outletFrom, 'outlet_to' => $outletTo, 'transfer_category' => $transferType],
        ['return_type' => 'multiple', 'limit' => $limit]
    );
}

/**
 * Check if transfer exists
 */
function transferExists(int $transferId, string $transferType = 'STOCK'): bool
{
    $count = getUniversalTransfer(
        ['id' => $transferId, 'transfer_category' => $transferType],
        ['return_type' => 'count']
    );
    return $count > 0;
}

?>