<?php
/**
 * API: Get Transfer Data for Pack Page
 * Returns live transfer, items, shipments, parcels, and metrics
 */

// DEBUGGING - Show all errors
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Load shared base
require_once __DIR__ . '/../../../base/DatabasePDO.php';

use CIS\Base\DatabasePDO;

// Auth check (using Modern PHP standard: user_id)
// Legacy compatibility: Also checks old formats (user_id, USER_ID) for backwards compatibility
// Allow access if user is logged in OR if accessing from internal network
$is_authenticated = isset($_SESSION['user_id']) || isset($_SESSION['user_id']) || isset($_SESSION['USER_ID']);
$is_internal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']);

if (!$is_authenticated && !$is_internal) {
    // For now, just log the access attempt and allow it (auth will be enforced later)
    error_log("Unauthenticated API access from: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

$transfer_id = isset($_GET['transfer_id']) ? (int)$_GET['transfer_id'] : 0;

if (!$transfer_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'transfer_id required']);
    exit;
}

// =========================================================================
// HELPER FUNCTIONS
// =========================================================================

/**
 * Call freight API via cURL
 */
function callFreightAPI($action, $params) {
    $freight_api_url = 'https://' . $_SERVER['HTTP_HOST'] . '/assets/services/core/freight/api.php';

    $params['action'] = $action;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $freight_api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Internal call
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'X-Internal-Call: true'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        error_log("Freight API cURL error: " . $curl_error);
        return ['success' => false, 'error' => 'cURL error: ' . $curl_error];
    }

    if ($http_code !== 200) {
        error_log("Freight API HTTP error: " . $http_code);
        return ['success' => false, 'error' => 'HTTP ' . $http_code];
    }

    $data = json_decode($response, true);
    if (!$data) {
        error_log("Freight API JSON parse error. Response: " . substr($response, 0, 500));
        return ['success' => false, 'error' => 'Invalid JSON response'];
    }

    return $data;
}

try {
    // Configure and get database connection
    DatabasePDO::configure([
        'host' => 'localhost',
        'database' => 'jcepnzzkmj',
        'username' => 'jcepnzzkmj',
        'password' => 'wprKh9Jq63',
    ]);

    $db = DatabasePDO::connection();

    // Get transfer header
    // First check if transfer exists at all (ignoring soft delete) and verify it's a STOCK_TRANSFER
    $check_stmt = $db->prepare("SELECT id, deleted_at, transfer_category FROM vend_consignments WHERE id = ?");
    $check_stmt->execute([$transfer_id]);
    $exists = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exists) {
        error_log("Transfer {$transfer_id} does not exist in database at all");
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Transfer not found',
            'message' => "Transfer ID {$transfer_id} does not exist in the system"
        ]);
        exit;
    }

    if ($exists['transfer_category'] !== 'STOCK') {
        error_log("ID {$transfer_id} exists but is transfer_category '{$exists['transfer_category']}', not STOCK");
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid type',
            'message' => "ID {$transfer_id} is a {$exists['transfer_category']} transfer, not a stock transfer"
        ]);
        exit;
    }

    if ($exists['deleted_at'] !== null) {
        error_log("Transfer {$transfer_id} was soft-deleted at: " . $exists['deleted_at']);
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Transfer deleted',
            'message' => "Transfer ID {$transfer_id} has been deleted and is no longer available"
        ]);
        exit;
    }

    // Get basic transfer data first without joins to debug
    $stmt = $db->prepare("
        SELECT *
        FROM vend_consignments t
        WHERE t.id = ?
        AND t.transfer_category = 'STOCK'
        AND t.deleted_at IS NULL
    ");
    $stmt->execute([$transfer_id]);
    $transfer_basic = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transfer_basic) {
        error_log("Transfer {$transfer_id} not found with transfer_category=STOCK (no status filter)");
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Transfer not found',
            'message' => "Transfer ID {$transfer_id} not found (must be STOCK category)"
        ]);
        exit;
    }

    error_log("Transfer {$transfer_id} found: outlet_from={$transfer_basic['outlet_from']}, outlet_to={$transfer_basic['outlet_to']}");

    // Now get with outlet joins
    $stmt = $db->prepare("
        SELECT
            t.id,
            t.public_id,
            t.vend_transfer_id,
            t.state,
            t.status,
            t.outlet_from,
            t.outlet_to,
            t.created_at,
            t.sent_at,
            t.received_at,
            t.total_count,
            t.total_boxes,
            t.total_weight_g,
            t.total_cost,
            t.tracking_number,
            t.tracking_carrier,
            o_from.name as outlet_from_name,
            o_from.physical_address_1 as outlet_from_address,
            o_from.physical_city as outlet_from_city,
            o_from.physical_postcode as outlet_from_postcode,
            o_from.physical_phone_number as outlet_from_phone,
            o_to.name as outlet_to_name,
            o_to.physical_address_1 as outlet_to_address,
            o_to.physical_city as outlet_to_city,
            o_to.physical_postcode as outlet_to_postcode,
            o_to.physical_phone_number as outlet_to_phone,
            o_to.email as outlet_to_email
        FROM vend_consignments t
        LEFT JOIN vend_outlets o_from ON t.outlet_from = o_from.id
        LEFT JOIN vend_outlets o_to ON t.outlet_to = o_to.id
        WHERE t.id = ?
    ");
    $stmt->execute([$transfer_id]);
    $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transfer) {
        error_log("Transfer {$transfer_id} query with outlets returned no results");
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Query error',
            'message' => "Transfer found but outlet join failed"
        ]);
        exit;
    }

    // Get line items with product details
    $stmt = $db->prepare("
        SELECT
            li.id,
            li.product_id,
            li.sku,
            li.name,
            li.quantity,
            li.quantity_sent,
            li.quantity_received,
            li.unit_cost,
            li.total_cost,
            li.status,
            li.confirmation_status,
            p.name as product_name,
            p.sku as product_sku
        FROM vend_consignment_line_items li
        LEFT JOIN vend_products p ON li.product_id = p.id
        WHERE li.transfer_id = ? AND li.deleted_at IS NULL
        ORDER BY li.name ASC
    ");
    $stmt->execute([$transfer_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get shipments and parcels
    $stmt = $db->prepare("
        SELECT
            s.id,
            s.status as shipment_status,
            s.delivery_mode,
            s.carrier_name,
            s.tracking_number as shipment_tracking,
            s.packed_at,
            s.dispatched_at,
            COUNT(p.id) as parcel_count,
            SUM(p.weight_kg) as total_weight_kg
        FROM consignment_shipments s
        LEFT JOIN consignment_parcels p ON s.id = p.shipment_id AND p.deleted_at IS NULL
        WHERE s.transfer_id = ? AND s.deleted_at IS NULL
        GROUP BY s.id
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$transfer_id]);
    $shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get parcels with tracking
    $stmt = $db->prepare("
        SELECT
            p.id,
            p.shipment_id,
            p.box_number,
            p.tracking_number,
            p.courier,
            p.weight_kg,
            p.length_mm,
            p.width_mm,
            p.height_mm,
            p.status,
            p.label_url,
            p.created_at
        FROM consignment_parcels p
        WHERE p.shipment_id IN (
            SELECT id FROM consignment_shipments
            WHERE transfer_id = ? AND deleted_at IS NULL
        )
        AND p.deleted_at IS NULL
        ORDER BY p.box_number ASC
    ");
    $stmt->execute([$transfer_id]);
    $parcels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent notes
    $stmt = $db->prepare("
        SELECT
            n.id,
            n.note_text,
            n.created_at,
            n.created_by,
            e.firstname,
            e.surname
        FROM consignment_notes n
        LEFT JOIN employee e ON n.created_by = e.id
        WHERE n.transfer_id = ? AND n.deleted_at IS NULL
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$transfer_id]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get AI insights if available
    $stmt = $db->prepare("
        SELECT
            insight_text,
            insight_type,
            priority,
            confidence_score,
            generated_at
        FROM consignment_ai_insights
        WHERE transfer_id = ?
        AND expires_at > NOW()
        ORDER BY priority DESC, generated_at DESC
        LIMIT 5
    ");
    $stmt->execute([$transfer_id]);
    $ai_insights = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate metrics
    $total_items = array_sum(array_column($items, 'quantity'));
    $total_sent = array_sum(array_column($items, 'quantity_sent'));
    $total_received = array_sum(array_column($items, 'quantity_received'));
    $packing_progress = $total_items > 0 ? round(($total_sent / $total_items) * 100, 1) : 0;

    $over_picks = 0;
    $under_picks = 0;
    foreach ($items as $item) {
        $diff = $item['quantity_sent'] - $item['quantity'];
        if ($diff > 0) $over_picks++;
        if ($diff < 0) $under_picks++;
    }

    // =========================================================================
    // REAL FREIGHT CALCULATIONS VIA API
    // =========================================================================

    $freight_weight_kg = 0;
    $freight_cost = 0;
    $freight_carrier = null;
    $freight_service = null;
    $freight_errors = [];
    $address_validation_required = false;

    // Check if we have minimum required address data
    $has_from_address = !empty($transfer['outlet_from_address']) && !empty($transfer['outlet_from_city']) && !empty($transfer['outlet_from_postcode']);
    $has_to_address = !empty($transfer['outlet_to_address']) && !empty($transfer['outlet_to_city']) && !empty($transfer['outlet_to_postcode']);

    if (!$has_from_address) {
        $freight_errors[] = "FROM outlet ({$transfer['outlet_from_name']}) missing address data - manage in Outlets";
        $address_validation_required = true;
    }
    if (!$has_to_address) {
        $freight_errors[] = "TO outlet ({$transfer['outlet_to_name']}) missing address data - manage in Outlets";
        $address_validation_required = true;
    }

    // Only call freight API if addresses are complete
    if ($has_from_address && $has_to_address) {

        try {
            // STEP 1: Calculate weight via freight API
            $freight_items = [];
            foreach ($items as $item) {
                $freight_items[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => (int)$item['quantity']
                ];
            }

            $weight_response = callFreightAPI('calculate_weight', [
                'items' => json_encode($freight_items)
            ]);

            if ($weight_response['success']) {
                $freight_weight_kg = $weight_response['data']['total_weight_kg'] ?? 0;
            } else {
                $freight_errors[] = 'Weight calculation failed: ' . ($weight_response['error'] ?? 'Unknown error');
                error_log("Freight weight calc error: " . json_encode($weight_response));
            }
        } catch (Exception $e) {
            $freight_errors[] = 'Weight calculation exception: ' . $e->getMessage();
            error_log("Freight weight exception: " . $e->getMessage());
        }

        try {
            // STEP 2: Get freight rates via freight API
            $from_address = [
                'name' => $transfer['outlet_from_name'] ?? 'Unknown',
                'street' => $transfer['outlet_from_address'] ?? '',
                'city' => $transfer['outlet_from_city'] ?? '',
                'postcode' => $transfer['outlet_from_postcode'] ?? '',
                'country' => 'NZ'
            ];

            $to_address = [
                'name' => $transfer['outlet_to_name'] ?? 'Unknown',
                'street' => $transfer['outlet_to_address'] ?? '',
                'city' => $transfer['outlet_to_city'] ?? '',
                'postcode' => $transfer['outlet_to_postcode'] ?? '',
                'country' => 'NZ'
            ];

            $rates_response = callFreightAPI('get_rates', [
                'items' => json_encode($freight_items),
                'from_address' => json_encode($from_address),
                'to_address' => json_encode($to_address)
            ]);

            if ($rates_response['success'] && !empty($rates_response['data']['rates'])) {
                $rates = $rates_response['data']['rates'];

                // Prefer recommended rate, fallback to cheapest
                $best_rate = $rates_response['data']['recommended'] ?? $rates_response['data']['cheapest'] ?? $rates[0];

                $freight_cost = $best_rate['price'] ?? 0;
                $freight_carrier = $best_rate['carrier'] ?? null;
                $freight_service = $best_rate['service'] ?? null;

            } else {
                $freight_errors[] = 'Rate calculation failed: ' . ($rates_response['error']['message'] ?? 'Unknown error');
                error_log("Freight rates error: " . json_encode($rates_response));

                // Fallback to database values if API fails
                $freight_cost = (float)($transfer['total_cost'] ?? 0);
                $freight_carrier = $transfer['tracking_carrier'] ?? null;
            }
        } catch (Exception $e) {
            $freight_errors[] = 'Rate calculation exception: ' . $e->getMessage();
            error_log("Freight rates exception: " . $e->getMessage());

            // Fallback to database values
            $freight_cost = (float)($transfer['total_cost'] ?? 0);
            $freight_carrier = $transfer['tracking_carrier'] ?? null;
        }

    } else {
        // Address validation required - use database fallback values
        $freight_weight_kg = round(($transfer['total_weight_g'] ?? 0) / 1000, 2);
        $freight_cost = (float)($transfer['total_cost'] ?? 0);
        $freight_carrier = $transfer['tracking_carrier'] ?? null;
    }

    // Get performance metrics if in packing state
    $pacing = null;
    if (in_array($transfer['state'], ['PACKING', 'PACKAGED'])) {
        $stmt = $db->prepare("
            SELECT
                TIMESTAMPDIFF(SECOND, MIN(event_timestamp), NOW()) as elapsed_seconds,
                COUNT(*) as events_count
            FROM consignment_performance_logs
            WHERE transfer_id = ?
            AND event_type IN ('PACKING_STARTED', 'PACKING_COMPLETED')
        ");
        $stmt->execute([$transfer_id]);
        $perf = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($perf && $perf['elapsed_seconds'] > 0) {
            $hours = $perf['elapsed_seconds'] / 3600;
            $items_per_hour = $hours > 0 ? round($total_sent / $hours, 1) : 0;
            $remaining_items = $total_items - $total_sent;
            $projected_finish_hours = $items_per_hour > 0 ? round($remaining_items / $items_per_hour, 1) : 0;

            $pacing = [
                'items_per_hour' => $items_per_hour,
                'elapsed_seconds' => $perf['elapsed_seconds'],
                'projected_finish_hours' => $projected_finish_hours
            ];
        }
    }

    // Response payload
    $response = [
        'success' => true,
        'transfer' => $transfer,
        'items' => $items,
        'shipments' => $shipments,
        'parcels' => $parcels,
        'notes' => $notes,
        'ai_insights' => $ai_insights,
        'metrics' => [
            'total_items' => $total_items,
            'total_sent' => $total_sent,
            'total_received' => $total_received,
            'packing_progress' => $packing_progress,
            'over_picks' => $over_picks,
            'under_picks' => $under_picks,
            'total_boxes' => count($parcels) > 0 ? count($parcels) : (int)$transfer['total_boxes'],
            'total_weight_kg' => $freight_weight_kg,
            'total_cost' => $freight_cost,
            'freight_carrier' => $freight_carrier,
            'freight_service' => $freight_service
        ],
        'freight' => [
            'weight_kg' => $freight_weight_kg,
            'cost' => $freight_cost,
            'carrier' => $freight_carrier,
            'service' => $freight_service,
            'errors' => $freight_errors,
            'address_validation_required' => $address_validation_required
        ],
        'pacing' => $pacing
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log("Transfer data API error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
