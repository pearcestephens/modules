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
            $whereConditions[] = 'qc.id = ?';
            $params[] = $filters['id'];
        }

        if (isset($filters['ids']) && is_array($filters['ids'])) {
            $placeholders = str_repeat('?,', count($filters['ids']) - 1) . '?';
            $whereConditions[] = "qc.id IN ($placeholders)";
            $params = array_merge($params, $filters['ids']);
        }

        if (isset($filters['public_id'])) {
            $whereConditions[] = 'qc.public_id = ?';
            $params[] = $filters['public_id'];
        }

        if (isset($filters['vend_id'])) {
            $whereConditions[] = 'qc.vend_consignment_id = ?';
            $params[] = $filters['vend_id'];
        }

        // Category and type filters
        if (isset($filters['transfer_category'])) {
            $whereConditions[] = 'qc.transfer_category = ?';
            $params[] = strtoupper($filters['transfer_category']);
        }

        if (isset($filters['state'])) {
            if (is_array($filters['state'])) {
                $placeholders = str_repeat('?,', count($filters['state']) - 1) . '?';
                $whereConditions[] = "qc.state IN ($placeholders)";
                $params = array_merge($params, $filters['state']);
            } else {
                $whereConditions[] = 'qc.state = ?';
                $params[] = $filters['state'];
            }
        }

        // Outlet filters
        if (isset($filters['outlet_from'])) {
            $whereConditions[] = 'qc.source_outlet_id = ?';
            $params[] = $filters['outlet_from'];
        }

        if (isset($filters['outlet_to'])) {
            $whereConditions[] = 'qc.destination_outlet_id = ?';
            $params[] = $filters['outlet_to'];
        }

        if (isset($filters['outlet_any'])) {
            $whereConditions[] = '(qc.source_outlet_id = ? OR qc.destination_outlet_id = ?)';
            $params[] = $filters['outlet_any'];
            $params[] = $filters['outlet_any'];
        }

        // Date filters
        if (isset($filters['created_after'])) {
            $whereConditions[] = 'qc.created_at >= ?';
            $params[] = $filters['created_after'];
        }

        if (isset($filters['created_before'])) {
            $whereConditions[] = 'qc.created_at <= ?';
            $params[] = $filters['created_before'];
        }

        if (isset($filters['updated_after'])) {
            $whereConditions[] = 'qc.updated_at >= ?';
            $params[] = $filters['updated_after'];
        }

        if (isset($filters['updated_before'])) {
            $whereConditions[] = 'qc.updated_at <= ?';
            $params[] = $filters['updated_before'];
        }

        // Date range shortcuts
        if (isset($filters['today'])) {
            $whereConditions[] = 'DATE(qc.created_at) = CURDATE()';
        }

        if (isset($filters['this_week'])) {
            $whereConditions[] = 'YEARWEEK(qc.created_at) = YEARWEEK(NOW())';
        }

        if (isset($filters['this_month'])) {
            $whereConditions[] = 'YEAR(qc.created_at) = YEAR(NOW()) AND MONTH(qc.created_at) = MONTH(NOW())';
        }

        // User filters
        if (isset($filters['created_by'])) {
            $whereConditions[] = 'qc.cis_user_id = ?';
            $params[] = $filters['created_by'];
        }

        if (isset($filters['updated_by'])) {
            $whereConditions[] = 'qc.updated_by = ?';
            $params[] = $filters['updated_by'];
        }

        // Search filters
        if (isset($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $whereConditions[] = '(qc.name LIKE ? OR qc.vend_consignment_id LIKE ?)';
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
            $sql = "SELECT COUNT(*) as total FROM queue_consignments qc $whereClause";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        }

        // Get transfer IDs first
        $orderBy = $options['order_by'];
        $orderDirection = strtoupper($options['order_direction']);
        $limit = (int)$options['limit'];
        $offset = (int)$options['offset'];

        // Whitelist order by fields
        $allowedOrder = ['created_at','updated_at','id'];
        if (!in_array($orderBy, $allowedOrder, true)) { $orderBy = 'created_at'; }

        $sql = "
            SELECT qc.id
            FROM queue_consignments qc
            $whereClause
            ORDER BY qc.$orderBy $orderDirection
            LIMIT $limit OFFSET $offset
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $transferIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($transferIds)) {
            // Fallback: if a single numeric filter was used, try vend_consignment_id match
            if (isset($filters['id']) && $options['return_type'] === 'single') {
                $stmt = $pdo->prepare("SELECT id FROM queue_consignments WHERE vend_consignment_id = ? LIMIT 1");
                $stmt->execute([$filters['id']]);
                $fallbackId = $stmt->fetchColumn();
                if ($fallbackId) { $transferIds = [$fallbackId]; }
            }
            if (empty($transferIds)) {
                return $options['return_type'] === 'single' ? null : [];
            }
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

    try {
        // Schema-aligned query (see SCHEMA_MAPPING.md)
        // queue_consignments (qc), queue_consignment_products (qcp)
        $sql = "
            SELECT
                qcp.vend_product_id        AS product_id,
                qcp.count_ordered          AS qty_requested,
                COALESCE(qcp.count_received, 0) AS qty_received,
                COALESCE(qcp.count_damaged, 0)  AS qty_damaged,
                -- Prefer denormalized product fields if present
                COALESCE(qcp.product_name, vp.name) AS name,
                COALESCE(qcp.product_sku,  vp.sku)  AS sku,
                vp.image_url,
                COALESCE(vi.current_amount, 0)      AS stock_on_hand
            FROM queue_consignment_products qcp
            LEFT JOIN queue_consignments qc
                   ON qc.id = qcp.consignment_id
            LEFT JOIN vend_products vp
                   ON qcp.vend_product_id = vp.id
            LEFT JOIN vend_inventory vi
                   ON vp.id = vi.product_id
                  AND vi.outlet_id = qc.source_outlet_id
            WHERE qcp.consignment_id = ?
            ORDER BY qcp.id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$transferId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ensure keys expected by UI exist even if NULL
        foreach ($rows as &$r) {
            $r['product_id']   = (string)($r['product_id'] ?? '');
            $r['name']         = (string)($r['name'] ?? '');
            $r['sku']          = (string)($r['sku'] ?? '');
            $r['qty_requested']= (int)($r['qty_requested'] ?? 0);
            $r['stock_on_hand']= (int)($r['stock_on_hand'] ?? 0);
        }
        unset($r);

        return $rows;
    } catch (\Throwable $e) {
        error_log('getTransferItems failed: ' . $e->getMessage());
        return [];
    }
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

/**
 * Get counts by state for transfers of a given category.
 * opts: ['created_by' => (int) userId] to scope counts to a user
 * Returns: [ 'TOTAL' => n, 'OPEN' => n1, 'SENT' => n2, 'RECEIVING' => n3, 'RECEIVED' => n4 ]
 */
function getTransferCountsByState(string $transferType = 'STOCK', array $opts = []): array
{
    global $pdo;
    $out = ['TOTAL' => 0, 'OPEN' => 0, 'SENT' => 0, 'RECEIVING' => 0, 'RECEIVED' => 0];
    try {
        $tcat = strtoupper($transferType);
        $where = 'vc.transfer_category = :tcat AND vc.deleted_at IS NULL';
        $params = [':tcat' => $tcat];
        if (!empty($opts['created_by'])) {
            $where .= ' AND vc.created_by = :uid';
            $params[':uid'] = (int)$opts['created_by'];
        }
        // Total
        $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM vend_consignments vc WHERE $where");
        $stmt->execute($params);
        $out['TOTAL'] = (int)$stmt->fetchColumn();
        // Per-state
        $stmt = $pdo->prepare("SELECT vc.state, COUNT(*) AS c FROM vend_consignments vc WHERE $where GROUP BY vc.state");
        $stmt->execute($params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $state = strtoupper((string)$row['state']);
            $out[$state] = (int)$row['c'];
        }
    } catch (\Throwable $e) {
        error_log('getTransferCountsByState failed: ' . $e->getMessage());
    }
    return $out;
}

/**
 * Get Outlet Meta (name, phone, email, address) by outlet id
 */
if (!function_exists('getOutletMeta')) {
    function getOutletMeta(int $outletId): array
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, physical_phone_number, physical_address_1, physical_address_2, physical_city, physical_postcode FROM vend_outlets WHERE id = ? LIMIT 1");
            $stmt->execute([$outletId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
                'email' => (string)($row['email'] ?? ''),
                'phone' => (string)($row['physical_phone_number'] ?? ''),
                'address_1' => (string)($row['physical_address_1'] ?? ''),
                'address_2' => (string)($row['physical_address_2'] ?? ''),
                'city' => (string)($row['physical_city'] ?? ''),
                'postcode' => (string)($row['physical_postcode'] ?? ''),
            ];
        } catch (\Throwable $e) {
            error_log('getOutletMeta failed: ' . $e->getMessage());
            return [];
        }
    }
}

/**
 * Get recent transfers with DB enrichment for list views
 * Returns associative arrays suitable for list/summary UIs
 */
if (!function_exists('getRecentTransfersEnrichedDB')) {
    function getRecentTransfersEnrichedDB(int $limit = 18, string $transferType = 'STOCK', array $opts = []): array
    {
        global $pdo;
        $limit = max(1, min(100, (int)$limit));
        try {
            $whereExtra = '';
            $params = [];
            if (!empty($opts['state'])) {
                $whereExtra .= ' AND qc.state = :state';
                $params[':state'] = (string)$opts['state'];
            }
            if (!empty($opts['created_by'])) {
                $whereExtra .= ' AND qc.cis_user_id = :created_by';
                $params[':created_by'] = (int)$opts['created_by'];
            }
            $sql = "
            SELECT
                vc.id AS cis_internal_id,
                vc.vend_transfer_id AS id,
                vc.vend_number AS consignment_number,
                vc.state,
                vc.status,
                vc.created_at,
                vc.updated_at,
                vc.outlet_from,
                vc.outlet_to,
                of.name AS from_outlet_name,
                ot.name AS to_outlet_name,
                of.physical_phone_number AS from_outlet_phone,
                ot.physical_phone_number AS to_outlet_phone,
                of.email AS from_outlet_email,
                ot.email AS to_outlet_email,
                (
                    SELECT COALESCE(SUM(vcli.quantity),0)
                    FROM vend_consignment_line_items vcli
                    WHERE vcli.transfer_id = vc.id AND vcli.deleted_at IS NULL
                ) AS item_count_total,
                (
                    SELECT COALESCE(SUM(vcli.quantity_received),0)
                    FROM vend_consignment_line_items vcli
                    WHERE vcli.transfer_id = vc.id AND vcli.deleted_at IS NULL
                ) AS items_received,
                vc.total_boxes AS parcels_count,
                0 AS shipments_count,
                vc.consignment_notes AS latest_note,
                vc.tracking_carrier AS latest_shipment_carrier,
                vc.tracking_number AS latest_tracking,
                vc.total_cost
            FROM vend_consignments vc
            LEFT JOIN vend_outlets of ON of.id = vc.outlet_from
            LEFT JOIN vend_outlets ot ON ot.id = vc.outlet_to
            WHERE vc.transfer_category = :tcat
              AND vc.deleted_at IS NULL
              $whereExtra
            ORDER BY vc.created_at DESC
            LIMIT :lim
        ";
            $stmt = $pdo->prepare($sql);
            $tcat = strtoupper($transferType);
            $stmt->bindParam(':tcat', $tcat, PDO::PARAM_STR);
            $stmt->bindParam(':lim', $limit, PDO::PARAM_INT);
        foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Post-process date/tz fields for NZ and links
            $out = [];
            $tz = new DateTimeZone('Pacific/Auckland');
            foreach ($rows as $r) {
                $nz = null; $age = null;
                if (!empty($r['created_at'])) {
                    try {
                        $dt = new DateTime($r['created_at']);
                        $dt->setTimezone($tz);
                        $nz = $dt->format(DateTime::ATOM);
                        $age = (int)floor((time() - $dt->getTimestamp()) / 3600);
                    } catch (\Throwable $e) { /* ignore */ }
                }
                $out[] = [
                    'cis_internal_id' => (int)$r['cis_internal_id'],
                    'id' => (string)($r['vend_id'] ?? ''),
                    'consignment_number' => (string)($r['consignment_number'] ?? ''),
                    'status' => (string)($r['state'] ?? ''),
                    'from_outlet_name' => (string)($r['from_outlet_name'] ?? ''),
                    'to_outlet_name' => (string)($r['to_outlet_name'] ?? ''),
                    'to_outlet_phone' => (string)($r['to_outlet_phone'] ?? ''),
                    'to_outlet_email' => (string)($r['to_outlet_email'] ?? ''),
                    'item_count_total' => (int)($r['item_count_total'] ?? 0),
                    'items_received' => (int)($r['items_received'] ?? 0),
                    'shipments_count' => (int)($r['shipments_count'] ?? 0),
                    'parcels_count' => (int)($r['parcels_count'] ?? 0),
                    'created_at' => (string)($r['created_at'] ?? ''),
                    'created_at_nz' => $nz,
                    'age_hours_nz' => $age,
                    'latest_note' => (string)($r['latest_note'] ?? ''),
                    'latest_shipment_carrier' => (string)($r['latest_shipment_carrier'] ?? ''),
                    'latest_tracking' => (string)($r['latest_tracking'] ?? ''),
                    'links' => [ 'vend_console' => '#' ],
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            error_log('getRecentTransfersEnrichedDB failed: ' . $e->getMessage());
            return [];
        }
    }
}

?>
