<?php
/**
 * Barcode Scanner Configuration API
 * Handles CRUD operations for barcode scanner settings
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/auth.php';

// Check permissions
if (!hasPermission('barcode_admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$db = getDb();
$currentUser = getCurrentUser();
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'update_global':
            updateGlobalConfig($db, $input, $currentUser);
            break;

        case 'get_global':
            getGlobalConfig($db);
            break;

        case 'update_outlet':
            updateOutletConfig($db, $input, $currentUser);
            break;

        case 'get_outlet':
            getOutletConfig($db, $input['outlet_id']);
            break;

        case 'get_all_outlets':
            getAllOutletConfigs($db);
            break;

        case 'delete_outlet_config':
            deleteOutletConfig($db, $input['outlet_id'], $currentUser);
            break;

        case 'update_user_prefs':
            updateUserPrefs($db, $input, $currentUser);
            break;

        case 'get_user_prefs':
            getUserPrefs($db, $input['user_id'], $input['outlet_id'] ?? null);
            break;

        case 'get_all_user_prefs':
            getAllUserPrefs($db);
            break;

        case 'delete_user_prefs':
            deleteUserPrefs($db, $input['user_id'], $input['outlet_id'] ?? null, $currentUser);
            break;

        case 'get_effective_config':
            getEffectiveConfig($db, $input['user_id'] ?? null, $input['outlet_id'] ?? null);
            break;

        case 'get_scan_history':
            getScanHistory($db, $input);
            break;

        case 'get_analytics':
            getAnalytics($db, $input);
            break;

        case 'test_audio':
            echo json_encode(['success' => true, 'message' => 'Audio test endpoint']);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Update global configuration
 */
function updateGlobalConfig($db, $data, $user) {
    $stmt = $db->prepare("
        UPDATE BARCODE_CONFIGURATION SET
            enabled = :enabled,
            usb_scanner_enabled = :usb_scanner_enabled,
            camera_scanner_enabled = :camera_scanner_enabled,
            manual_entry_enabled = :manual_entry_enabled,
            scan_mode = :scan_mode,
            require_exact_match = :require_exact_match,
            allow_duplicate_scans = :allow_duplicate_scans,
            block_on_qty_exceed = :block_on_qty_exceed,
            audio_enabled = :audio_enabled,
            audio_volume = :audio_volume,
            tone1_frequency = :tone1_frequency,
            tone2_frequency = :tone2_frequency,
            tone3_frequency = :tone3_frequency,
            tone_duration_ms = :tone_duration_ms,
            visual_feedback_enabled = :visual_feedback_enabled,
            success_color = :success_color,
            warning_color = :warning_color,
            error_color = :error_color,
            flash_duration_ms = :flash_duration_ms,
            scan_cooldown_ms = :scan_cooldown_ms,
            log_all_scans = :log_all_scans,
            log_failed_scans = :log_failed_scans,
            log_retention_days = :log_retention_days,
            updated_by = :updated_by
        WHERE outlet_id IS NULL
    ");

    $stmt->execute([
        'enabled' => $data['enabled'] ?? 1,
        'usb_scanner_enabled' => $data['usb_scanner_enabled'] ?? 1,
        'camera_scanner_enabled' => $data['camera_scanner_enabled'] ?? 1,
        'manual_entry_enabled' => $data['manual_entry_enabled'] ?? 1,
        'scan_mode' => $data['scan_mode'] ?? 'auto',
        'require_exact_match' => $data['require_exact_match'] ?? 0,
        'allow_duplicate_scans' => $data['allow_duplicate_scans'] ?? 1,
        'block_on_qty_exceed' => $data['block_on_qty_exceed'] ?? 0,
        'audio_enabled' => $data['audio_enabled'] ?? 1,
        'audio_volume' => $data['audio_volume'] ?? 0.5,
        'tone1_frequency' => $data['tone1_frequency'] ?? 1200,
        'tone2_frequency' => $data['tone2_frequency'] ?? 800,
        'tone3_frequency' => $data['tone3_frequency'] ?? 400,
        'tone_duration_ms' => $data['tone_duration_ms'] ?? 100,
        'visual_feedback_enabled' => $data['visual_feedback_enabled'] ?? 1,
        'success_color' => $data['success_color'] ?? '#28a745',
        'warning_color' => $data['warning_color'] ?? '#ffc107',
        'error_color' => $data['error_color'] ?? '#dc3545',
        'flash_duration_ms' => $data['flash_duration_ms'] ?? 500,
        'scan_cooldown_ms' => $data['scan_cooldown_ms'] ?? 100,
        'log_all_scans' => $data['log_all_scans'] ?? 1,
        'log_failed_scans' => $data['log_failed_scans'] ?? 1,
        'log_retention_days' => $data['log_retention_days'] ?? 90,
        'updated_by' => $user['id']
    ]);

    // Log audit
    logAudit($db, 'config_updated', 'global', null, $user['id']);

    echo json_encode(['success' => true, 'message' => 'Global configuration updated']);
}

/**
 * Get global configuration
 */
function getGlobalConfig($db) {
    $stmt = $db->query("SELECT * FROM BARCODE_CONFIGURATION WHERE outlet_id IS NULL");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'config' => $config]);
}

/**
 * Update outlet-specific configuration
 */
function updateOutletConfig($db, $data, $user) {
    $outletId = $data['outlet_id'];

    // Check if config exists
    $existing = $db->prepare("SELECT id FROM BARCODE_CONFIGURATION WHERE outlet_id = ?");
    $existing->execute([$outletId]);

    if ($existing->fetch()) {
        // Update
        $stmt = $db->prepare("
            UPDATE BARCODE_CONFIGURATION SET
                enabled = :enabled,
                usb_scanner_enabled = :usb_scanner_enabled,
                camera_scanner_enabled = :camera_scanner_enabled,
                manual_entry_enabled = :manual_entry_enabled,
                scan_mode = :scan_mode,
                audio_enabled = :audio_enabled,
                audio_volume = :audio_volume,
                updated_by = :updated_by
            WHERE outlet_id = :outlet_id
        ");
    } else {
        // Insert
        $stmt = $db->prepare("
            INSERT INTO BARCODE_CONFIGURATION (
                outlet_id, enabled, usb_scanner_enabled, camera_scanner_enabled,
                manual_entry_enabled, scan_mode, audio_enabled, audio_volume, created_by
            ) VALUES (
                :outlet_id, :enabled, :usb_scanner_enabled, :camera_scanner_enabled,
                :manual_entry_enabled, :scan_mode, :audio_enabled, :audio_volume, :updated_by
            )
        ");
    }

    $stmt->execute([
        'outlet_id' => $outletId,
        'enabled' => $data['enabled'] ?? 1,
        'usb_scanner_enabled' => $data['usb_scanner_enabled'] ?? 1,
        'camera_scanner_enabled' => $data['camera_scanner_enabled'] ?? 1,
        'manual_entry_enabled' => $data['manual_entry_enabled'] ?? 1,
        'scan_mode' => $data['scan_mode'] ?? 'auto',
        'audio_enabled' => $data['audio_enabled'] ?? 1,
        'audio_volume' => $data['audio_volume'] ?? 0.5,
        'updated_by' => $user['id']
    ]);

    logAudit($db, 'config_updated', 'outlet', $outletId, $user['id']);

    echo json_encode(['success' => true, 'message' => 'Outlet configuration updated']);
}

/**
 * Get outlet configuration
 */
function getOutletConfig($db, $outletId) {
    $stmt = $db->prepare("SELECT * FROM BARCODE_CONFIGURATION WHERE outlet_id = ?");
    $stmt->execute([$outletId]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'config' => $config]);
}

/**
 * Get all outlet configurations
 */
function getAllOutletConfigs($db) {
    $stmt = $db->query("
        SELECT bc.*, o.name as outlet_name, o.code as outlet_code
        FROM BARCODE_CONFIGURATION bc
        JOIN outlets o ON o.id = bc.outlet_id
        WHERE bc.outlet_id IS NOT NULL
        ORDER BY o.name
    ");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'configs' => $configs]);
}

/**
 * Delete outlet configuration (revert to global)
 */
function deleteOutletConfig($db, $outletId, $user) {
    $stmt = $db->prepare("DELETE FROM BARCODE_CONFIGURATION WHERE outlet_id = ?");
    $stmt->execute([$outletId]);

    logAudit($db, 'config_deleted', 'outlet', $outletId, $user['id']);

    echo json_encode(['success' => true, 'message' => 'Outlet configuration deleted (reverted to global)']);
}

/**
 * Update user preferences
 */
function updateUserPrefs($db, $data, $currentUser) {
    $userId = $data['user_id'];
    $outletId = $data['outlet_id'] ?? null;

    // Check if prefs exist
    $existing = $db->prepare("SELECT id FROM BARCODE_USER_PREFERENCES WHERE user_id = ? AND (outlet_id = ? OR (outlet_id IS NULL AND ? IS NULL))");
    $existing->execute([$userId, $outletId, $outletId]);

    if ($existing->fetch()) {
        // Update
        $stmt = $db->prepare("
            UPDATE BARCODE_USER_PREFERENCES SET
                usb_scanner_enabled = :usb_scanner_enabled,
                camera_scanner_enabled = :camera_scanner_enabled,
                audio_enabled = :audio_enabled,
                audio_volume = :audio_volume,
                preferred_scan_method = :preferred_scan_method
            WHERE user_id = :user_id AND (outlet_id = :outlet_id OR (outlet_id IS NULL AND :outlet_id IS NULL))
        ");
    } else {
        // Insert
        $stmt = $db->prepare("
            INSERT INTO BARCODE_USER_PREFERENCES (
                user_id, outlet_id, usb_scanner_enabled, camera_scanner_enabled,
                audio_enabled, audio_volume, preferred_scan_method
            ) VALUES (
                :user_id, :outlet_id, :usb_scanner_enabled, :camera_scanner_enabled,
                :audio_enabled, :audio_volume, :preferred_scan_method
            )
        ");
    }

    $stmt->execute([
        'user_id' => $userId,
        'outlet_id' => $outletId,
        'usb_scanner_enabled' => $data['usb_scanner_enabled'] ?? null,
        'camera_scanner_enabled' => $data['camera_scanner_enabled'] ?? null,
        'audio_enabled' => $data['audio_enabled'] ?? null,
        'audio_volume' => $data['audio_volume'] ?? null,
        'preferred_scan_method' => $data['preferred_scan_method'] ?? 'auto'
    ]);

    logAudit($db, 'setting_changed', 'user', $userId, $currentUser['id']);

    echo json_encode(['success' => true, 'message' => 'User preferences updated']);
}

/**
 * Get user preferences
 */
function getUserPrefs($db, $userId, $outletId = null) {
    $stmt = $db->prepare("
        SELECT * FROM BARCODE_USER_PREFERENCES
        WHERE user_id = ? AND (outlet_id = ? OR (outlet_id IS NULL AND ? IS NULL))
    ");
    $stmt->execute([$userId, $outletId, $outletId]);
    $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'preferences' => $prefs]);
}

/**
 * Get all user preferences
 */
function getAllUserPrefs($db) {
    $stmt = $db->query("
        SELECT up.*, u.name as user_name, o.name as outlet_name
        FROM BARCODE_USER_PREFERENCES up
        JOIN users u ON u.id = up.user_id
        LEFT JOIN outlets o ON o.id = up.outlet_id
        ORDER BY u.name
    ");
    $prefs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'preferences' => $prefs]);
}

/**
 * Delete user preferences
 */
function deleteUserPrefs($db, $userId, $outletId, $currentUser) {
    $stmt = $db->prepare("
        DELETE FROM BARCODE_USER_PREFERENCES
        WHERE user_id = ? AND (outlet_id = ? OR (outlet_id IS NULL AND ? IS NULL))
    ");
    $stmt->execute([$userId, $outletId, $outletId]);

    logAudit($db, 'setting_changed', 'user', $userId, $currentUser['id']);

    echo json_encode(['success' => true, 'message' => 'User preferences deleted']);
}

/**
 * Get effective configuration for a specific user/outlet combination
 * Priority: User Prefs > Outlet Config > Global Config
 */
function getEffectiveConfig($db, $userId = null, $outletId = null) {
    // Get global config
    $global = $db->query("SELECT * FROM BARCODE_CONFIGURATION WHERE outlet_id IS NULL")->fetch(PDO::FETCH_ASSOC);

    // Get outlet config if specified
    $outlet = null;
    if ($outletId) {
        $stmt = $db->prepare("SELECT * FROM BARCODE_CONFIGURATION WHERE outlet_id = ?");
        $stmt->execute([$outletId]);
        $outlet = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get user prefs if specified
    $user = null;
    if ($userId) {
        $stmt = $db->prepare("
            SELECT * FROM BARCODE_USER_PREFERENCES
            WHERE user_id = ? AND (outlet_id = ? OR outlet_id IS NULL)
            ORDER BY outlet_id DESC LIMIT 1
        ");
        $stmt->execute([$userId, $outletId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Merge configs (user > outlet > global)
    $effective = $global;
    if ($outlet) {
        foreach ($outlet as $key => $value) {
            if ($value !== null && $key !== 'id' && $key !== 'outlet_id') {
                $effective[$key] = $value;
            }
        }
    }
    if ($user) {
        foreach ($user as $key => $value) {
            if ($value !== null && $key !== 'id' && $key !== 'user_id' && $key !== 'outlet_id') {
                $effective[$key] = $value;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'effective_config' => $effective,
        'sources' => [
            'global' => $global ? true : false,
            'outlet' => $outlet ? true : false,
            'user' => $user ? true : false
        ]
    ]);
}

/**
 * Get scan history with filters
 */
function getScanHistory($db, $filters) {
    $where = ['1=1'];
    $params = [];

    if (!empty($filters['date_range'])) {
        $where[] = "scan_timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params[] = (int)$filters['date_range'];
    }

    if (!empty($filters['outlet_id'])) {
        $where[] = "outlet_id = ?";
        $params[] = $filters['outlet_id'];
    }

    if (!empty($filters['user_id'])) {
        $where[] = "user_id = ?";
        $params[] = $filters['user_id'];
    }

    if (!empty($filters['scan_method'])) {
        $where[] = "scan_method = ?";
        $params[] = $filters['scan_method'];
    }

    if (!empty($filters['scan_result'])) {
        $where[] = "scan_result = ?";
        $params[] = $filters['scan_result'];
    }

    $limit = min(1000, (int)($filters['limit'] ?? 100));
    $offset = (int)($filters['offset'] ?? 0);

    $sql = "
        SELECT bs.*, o.name as outlet_name, u.name as user_name
        FROM BARCODE_SCANS bs
        LEFT JOIN outlets o ON o.id = bs.outlet_id
        LEFT JOIN users u ON u.id = bs.user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY scan_timestamp DESC
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countSql = "SELECT COUNT(*) FROM BARCODE_SCANS WHERE " . implode(' AND ', $where);
    $countStmt = $db->prepare($countSql);
    $countStmt->execute(array_slice($params, 0, -2));
    $total = $countStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'scans' => $scans,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

/**
 * Get analytics data
 */
function getAnalytics($db, $filters) {
    $dateRange = (int)($filters['date_range'] ?? 30);
    $outletId = $filters['outlet_id'] ?? null;

    $where = ["scan_timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)"];
    $params = [$dateRange];

    if ($outletId) {
        $where[] = "outlet_id = ?";
        $params[] = $outletId;
    }

    // Daily scan counts
    $dailyStmt = $db->prepare("
        SELECT
            DATE(scan_timestamp) as date,
            COUNT(*) as total,
            SUM(CASE WHEN scan_result = 'success' THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN scan_method = 'usb_scanner' THEN 1 ELSE 0 END) as usb,
            SUM(CASE WHEN scan_method = 'camera' THEN 1 ELSE 0 END) as camera,
            SUM(CASE WHEN scan_method = 'manual_entry' THEN 1 ELSE 0 END) as manual
        FROM BARCODE_SCANS
        WHERE " . implode(' AND ', $where) . "
        GROUP BY DATE(scan_timestamp)
        ORDER BY date DESC
    ");
    $dailyStmt->execute($params);
    $daily = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Top users
    $usersStmt = $db->prepare("
        SELECT
            u.name,
            COUNT(*) as scan_count,
            AVG(bs.scan_duration_ms) as avg_duration
        FROM BARCODE_SCANS bs
        JOIN users u ON u.id = bs.user_id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY bs.user_id
        ORDER BY scan_count DESC
        LIMIT 10
    ");
    $usersStmt->execute($params);
    $topUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

    // Top products
    $productsStmt = $db->prepare("
        SELECT
            product_name,
            sku,
            COUNT(*) as scan_count
        FROM BARCODE_SCANS
        WHERE " . implode(' AND ', $where) . " AND product_name IS NOT NULL
        GROUP BY vend_product_id
        ORDER BY scan_count DESC
        LIMIT 10
    ");
    $productsStmt->execute($params);
    $topProducts = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'daily_stats' => $daily,
        'top_users' => $topUsers,
        'top_products' => $topProducts
    ]);
}

/**
 * Log audit entry
 */
function logAudit($db, $action, $targetType, $targetId, $userId) {
    $stmt = $db->prepare("
        INSERT INTO BARCODE_AUDIT_LOG (action, target_type, target_id, changed_by, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $action,
        $targetType,
        $targetId,
        $userId,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}
