<?php
/**
 * =====================================================
 * ANALYTICS SETTINGS API
 * =====================================================
 * Manages customization of EVERY feature at multiple levels:
 * - Global defaults
 * - Outlet overrides
 * - User preferences
 * - Transfer-specific overrides
 *
 * Supports complexity presets from VERY BASIC to EXPERT
 * =====================================================
 */

header('Content-Type: application/json');
require_once(__DIR__ . '/../../config/db.php');

// CORS headers if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get request data
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true) ?? [];

// Merge with $_POST for form submissions
$data = array_merge($_POST, $data);

try {
    switch ($action) {
        // =====================================================
        // GET SETTINGS
        // =====================================================
        case 'get_settings':
            echo json_encode(getSettings($data));
            break;

        case 'get_user_settings':
            echo json_encode(getUserSettings($data));
            break;

        case 'get_outlet_settings':
            echo json_encode(getOutletSettings($data));
            break;

        case 'get_global_settings':
            echo json_encode(getGlobalSettings($data));
            break;

        // =====================================================
        // UPDATE SETTINGS
        // =====================================================
        case 'update_user_preference':
            echo json_encode(updateUserPreference($data));
            break;

        case 'update_outlet_setting':
            echo json_encode(updateOutletSetting($data));
            break;

        case 'update_global_setting':
            echo json_encode(updateGlobalSetting($data));
            break;

        case 'set_transfer_override':
            echo json_encode(setTransferOverride($data));
            break;

        // =====================================================
        // COMPLEXITY PRESETS
        // =====================================================
        case 'get_presets':
            echo json_encode(getComplexityPresets());
            break;

        case 'apply_preset':
            echo json_encode(applyComplexityPreset($data));
            break;

        case 'apply_preset_to_outlet':
            echo json_encode(applyPresetToOutlet($data));
            break;

        case 'apply_preset_to_user':
            echo json_encode(applyPresetToUser($data));
            break;

        // =====================================================
        // BULK OPERATIONS
        // =====================================================
        case 'bulk_update_user':
            echo json_encode(bulkUpdateUserSettings($data));
            break;

        case 'bulk_update_outlet':
            echo json_encode(bulkUpdateOutletSettings($data));
            break;

        case 'reset_to_defaults':
            echo json_encode(resetToDefaults($data));
            break;

        // =====================================================
        // FEATURE TOGGLES
        // =====================================================
        case 'toggle_feature':
            echo json_encode(toggleFeature($data));
            break;

        case 'get_feature_status':
            echo json_encode(getFeatureStatus($data));
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action',
                'available_actions' => [
                    'get_settings', 'get_user_settings', 'get_outlet_settings', 'get_global_settings',
                    'update_user_preference', 'update_outlet_setting', 'update_global_setting', 'set_transfer_override',
                    'get_presets', 'apply_preset', 'apply_preset_to_outlet', 'apply_preset_to_user',
                    'bulk_update_user', 'bulk_update_outlet', 'reset_to_defaults',
                    'toggle_feature', 'get_feature_status'
                ]
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

// =====================================================
// FUNCTION: Get Effective Settings for User/Outlet/Transfer
// =====================================================
function getSettings($data) {
    global $pdo;

    $user_id = $data['user_id'] ?? null;
    $outlet_id = $data['outlet_id'] ?? null;
    $transfer_id = $data['transfer_id'] ?? null;
    $category = $data['category'] ?? null; // Optional filter

    if (!$user_id && !$outlet_id) {
        throw new Exception('user_id or outlet_id required');
    }

    // Build query
    $sql = "SELECT * FROM V_EFFECTIVE_SETTINGS WHERE 1=1";
    $params = [];

    if ($user_id) {
        $sql .= " AND user_id = ?";
        $params[] = $user_id;
    }

    if ($outlet_id) {
        $sql .= " AND outlet_id = ?";
        $params[] = $outlet_id;
    }

    if ($category) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }

    $sql .= " ORDER BY category, setting_key";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check for transfer-specific overrides
    $overrides = [];
    if ($transfer_id) {
        $sql = "SELECT category, setting_key, setting_value, override_reason
                FROM ANALYTICS_TRANSFER_OVERRIDES
                WHERE transfer_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$transfer_id]);
        $overrides = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Group by category
    $grouped = [];
    foreach ($settings as $setting) {
        $cat = $setting['category'];
        $key = $setting['setting_key'];

        if (!isset($grouped[$cat])) {
            $grouped[$cat] = [];
        }

        // Check if override exists
        $override = null;
        foreach ($overrides as $ov) {
            if ($ov['category'] === $cat && $ov['setting_key'] === $key) {
                $override = $ov;
                break;
            }
        }

        $grouped[$cat][$key] = [
            'value' => $override ? $override['setting_value'] : $setting['effective_value'],
            'source' => $override ? 'transfer_override' : $setting['source_level'],
            'description' => $setting['description'],
            'data_type' => $setting['data_type'],
            'override_reason' => $override['override_reason'] ?? null
        ];
    }

    return [
        'success' => true,
        'settings' => $grouped,
        'user_id' => $user_id,
        'outlet_id' => $outlet_id,
        'transfer_id' => $transfer_id
    ];
}

// =====================================================
// FUNCTION: Get User Preferences
// =====================================================
function getUserSettings($data) {
    global $pdo;

    $user_id = $data['user_id'] ?? null;
    if (!$user_id) throw new Exception('user_id required');

    $sql = "SELECT category, setting_key, setting_value, inherit_from_outlet, is_enabled, updated_at
            FROM ANALYTICS_USER_PREFERENCES
            WHERE user_id = ?
            ORDER BY category, setting_key";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $preferences = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by category
    $grouped = [];
    foreach ($preferences as $pref) {
        $cat = $pref['category'];
        if (!isset($grouped[$cat])) {
            $grouped[$cat] = [];
        }
        $grouped[$cat][$pref['setting_key']] = [
            'value' => $pref['setting_value'],
            'inherit' => (bool)$pref['inherit_from_outlet'],
            'enabled' => (bool)$pref['is_enabled'],
            'updated_at' => $pref['updated_at']
        ];
    }

    return [
        'success' => true,
        'user_id' => $user_id,
        'preferences' => $grouped
    ];
}

// =====================================================
// FUNCTION: Get Outlet Settings
// =====================================================
function getOutletSettings($data) {
    global $pdo;

    $outlet_id = $data['outlet_id'] ?? null;
    if (!$outlet_id) throw new Exception('outlet_id required');

    $sql = "SELECT category, setting_key, setting_value, inherit_from_global, is_enabled, notes, updated_at
            FROM ANALYTICS_OUTLET_SETTINGS
            WHERE outlet_id = ?
            ORDER BY category, setting_key";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$outlet_id]);
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grouped = [];
    foreach ($settings as $set) {
        $cat = $set['category'];
        if (!isset($grouped[$cat])) {
            $grouped[$cat] = [];
        }
        $grouped[$cat][$set['setting_key']] = [
            'value' => $set['setting_value'],
            'inherit' => (bool)$set['inherit_from_global'],
            'enabled' => (bool)$set['is_enabled'],
            'notes' => $set['notes'],
            'updated_at' => $set['updated_at']
        ];
    }

    return [
        'success' => true,
        'outlet_id' => $outlet_id,
        'settings' => $grouped
    ];
}

// =====================================================
// FUNCTION: Get Global Settings
// =====================================================
function getGlobalSettings($data) {
    global $pdo;

    $category = $data['category'] ?? null;

    $sql = "SELECT category, setting_key, setting_value, data_type, description, is_enabled, updated_at
            FROM ANALYTICS_GLOBAL_SETTINGS";
    $params = [];

    if ($category) {
        $sql .= " WHERE category = ?";
        $params[] = $category;
    }

    $sql .= " ORDER BY category, setting_key";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grouped = [];
    foreach ($settings as $set) {
        $cat = $set['category'];
        if (!isset($grouped[$cat])) {
            $grouped[$cat] = [];
        }
        $grouped[$cat][$set['setting_key']] = [
            'value' => $set['setting_value'],
            'data_type' => $set['data_type'],
            'description' => $set['description'],
            'enabled' => (bool)$set['is_enabled'],
            'updated_at' => $set['updated_at']
        ];
    }

    return [
        'success' => true,
        'settings' => $grouped
    ];
}

// =====================================================
// FUNCTION: Update User Preference
// =====================================================
function updateUserPreference($data) {
    global $pdo;

    $user_id = $data['user_id'] ?? null;
    $category = $data['category'] ?? null;
    $setting_key = $data['setting_key'] ?? null;
    $setting_value = $data['setting_value'] ?? null;
    $is_enabled = isset($data['is_enabled']) ? (bool)$data['is_enabled'] : true;

    if (!$user_id || !$category || !$setting_key) {
        throw new Exception('user_id, category, and setting_key required');
    }

    $sql = "INSERT INTO ANALYTICS_USER_PREFERENCES
            (user_id, category, setting_key, setting_value, is_enabled)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            is_enabled = VALUES(is_enabled),
            updated_at = CURRENT_TIMESTAMP";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $category, $setting_key, $setting_value, $is_enabled]);

    return [
        'success' => true,
        'message' => 'User preference updated',
        'user_id' => $user_id,
        'category' => $category,
        'setting_key' => $setting_key
    ];
}

// =====================================================
// FUNCTION: Update Outlet Setting
// =====================================================
function updateOutletSetting($data) {
    global $pdo;

    $outlet_id = $data['outlet_id'] ?? null;
    $category = $data['category'] ?? null;
    $setting_key = $data['setting_key'] ?? null;
    $setting_value = $data['setting_value'] ?? null;
    $is_enabled = isset($data['is_enabled']) ? (bool)$data['is_enabled'] : true;
    $notes = $data['notes'] ?? null;
    $updated_by = $data['updated_by'] ?? null;

    if (!$outlet_id || !$category || !$setting_key) {
        throw new Exception('outlet_id, category, and setting_key required');
    }

    $sql = "INSERT INTO ANALYTICS_OUTLET_SETTINGS
            (outlet_id, category, setting_key, setting_value, is_enabled, notes, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            is_enabled = VALUES(is_enabled),
            notes = VALUES(notes),
            updated_by = VALUES(updated_by),
            updated_at = CURRENT_TIMESTAMP";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$outlet_id, $category, $setting_key, $setting_value, $is_enabled, $notes, $updated_by]);

    return [
        'success' => true,
        'message' => 'Outlet setting updated',
        'outlet_id' => $outlet_id,
        'category' => $category,
        'setting_key' => $setting_key
    ];
}

// =====================================================
// FUNCTION: Update Global Setting
// =====================================================
function updateGlobalSetting($data) {
    global $pdo;

    $category = $data['category'] ?? null;
    $setting_key = $data['setting_key'] ?? null;
    $setting_value = $data['setting_value'] ?? null;
    $is_enabled = isset($data['is_enabled']) ? (bool)$data['is_enabled'] : true;
    $updated_by = $data['updated_by'] ?? null;

    if (!$category || !$setting_key) {
        throw new Exception('category and setting_key required');
    }

    $sql = "UPDATE ANALYTICS_GLOBAL_SETTINGS
            SET setting_value = ?,
                is_enabled = ?,
                updated_by = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE category = ? AND setting_key = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$setting_value, $is_enabled, $updated_by, $category, $setting_key]);

    return [
        'success' => true,
        'message' => 'Global setting updated',
        'category' => $category,
        'setting_key' => $setting_key
    ];
}

// =====================================================
// FUNCTION: Set Transfer Override
// =====================================================
function setTransferOverride($data) {
    global $pdo;

    $transfer_id = $data['transfer_id'] ?? null;
    $category = $data['category'] ?? null;
    $setting_key = $data['setting_key'] ?? null;
    $setting_value = $data['setting_value'] ?? null;
    $override_reason = $data['override_reason'] ?? null;
    $approved_by = $data['approved_by'] ?? null;
    $created_by = $data['created_by'] ?? null;

    if (!$transfer_id || !$category || !$setting_key || !$override_reason) {
        throw new Exception('transfer_id, category, setting_key, and override_reason required');
    }

    $sql = "INSERT INTO ANALYTICS_TRANSFER_OVERRIDES
            (transfer_id, category, setting_key, setting_value, override_reason, approved_by, created_by, approved_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$transfer_id, $category, $setting_key, $setting_value, $override_reason, $approved_by, $created_by]);

    return [
        'success' => true,
        'message' => 'Transfer override set',
        'transfer_id' => $transfer_id,
        'override_id' => $pdo->lastInsertId()
    ];
}

// =====================================================
// FUNCTION: Get Complexity Presets
// =====================================================
function getComplexityPresets() {
    global $pdo;

    $sql = "SELECT preset_id, preset_name, preset_level, description, settings_json, is_active
            FROM ANALYTICS_COMPLEXITY_PRESETS
            WHERE is_active = TRUE
            ORDER BY
                CASE preset_level
                    WHEN 'very_basic' THEN 1
                    WHEN 'basic' THEN 2
                    WHEN 'intermediate' THEN 3
                    WHEN 'advanced' THEN 4
                    WHEN 'very_advanced' THEN 5
                    WHEN 'expert' THEN 6
                END";

    $stmt = $pdo->query($sql);
    $presets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($presets as &$preset) {
        $preset['settings_json'] = json_decode($preset['settings_json'], true);
    }

    return [
        'success' => true,
        'presets' => $presets
    ];
}

// =====================================================
// FUNCTION: Apply Complexity Preset to User
// =====================================================
function applyPresetToUser($data) {
    global $pdo;

    $user_id = $data['user_id'] ?? null;
    $preset_name = $data['preset_name'] ?? null;

    if (!$user_id || !$preset_name) {
        throw new Exception('user_id and preset_name required');
    }

    // Get preset
    $sql = "SELECT settings_json FROM ANALYTICS_COMPLEXITY_PRESETS WHERE preset_name = ? AND is_active = TRUE";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$preset_name]);
    $preset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$preset) {
        throw new Exception('Preset not found');
    }

    $settings = json_decode($preset['settings_json'], true);

    // Delete existing preferences
    $sql = "DELETE FROM ANALYTICS_USER_PREFERENCES WHERE user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);

    // Insert new preferences
    $inserted = 0;
    foreach ($settings as $category => $categorySettings) {
        foreach ($categorySettings as $key => $value) {
            $sql = "INSERT INTO ANALYTICS_USER_PREFERENCES
                    (user_id, category, setting_key, setting_value, is_enabled)
                    VALUES (?, ?, ?, ?, TRUE)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $category, $key, $value]);
            $inserted++;
        }
    }

    return [
        'success' => true,
        'message' => "Applied preset '{$preset_name}' to user",
        'user_id' => $user_id,
        'preset_name' => $preset_name,
        'settings_applied' => $inserted
    ];
}

// =====================================================
// FUNCTION: Apply Complexity Preset to Outlet
// =====================================================
function applyPresetToOutlet($data) {
    global $pdo;

    $outlet_id = $data['outlet_id'] ?? null;
    $preset_name = $data['preset_name'] ?? null;
    $updated_by = $data['updated_by'] ?? null;

    if (!$outlet_id || !$preset_name) {
        throw new Exception('outlet_id and preset_name required');
    }

    // Get preset
    $sql = "SELECT settings_json FROM ANALYTICS_COMPLEXITY_PRESETS WHERE preset_name = ? AND is_active = TRUE";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$preset_name]);
    $preset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$preset) {
        throw new Exception('Preset not found');
    }

    $settings = json_decode($preset['settings_json'], true);

    // Delete existing settings
    $sql = "DELETE FROM ANALYTICS_OUTLET_SETTINGS WHERE outlet_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$outlet_id]);

    // Insert new settings
    $inserted = 0;
    foreach ($settings as $category => $categorySettings) {
        foreach ($categorySettings as $key => $value) {
            $sql = "INSERT INTO ANALYTICS_OUTLET_SETTINGS
                    (outlet_id, category, setting_key, setting_value, is_enabled, updated_by, notes)
                    VALUES (?, ?, ?, ?, TRUE, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$outlet_id, $category, $key, $value, $updated_by, "Applied preset: {$preset_name}"]);
            $inserted++;
        }
    }

    return [
        'success' => true,
        'message' => "Applied preset '{$preset_name}' to outlet",
        'outlet_id' => $outlet_id,
        'preset_name' => $preset_name,
        'settings_applied' => $inserted
    ];
}

// =====================================================
// FUNCTION: Bulk Update User Settings
// =====================================================
function bulkUpdateUserSettings($data) {
    global $pdo;

    $user_id = $data['user_id'] ?? null;
    $settings = $data['settings'] ?? null;

    if (!$user_id || !$settings || !is_array($settings)) {
        throw new Exception('user_id and settings array required');
    }

    $updated = 0;
    foreach ($settings as $category => $categorySettings) {
        foreach ($categorySettings as $key => $value) {
            $sql = "INSERT INTO ANALYTICS_USER_PREFERENCES
                    (user_id, category, setting_key, setting_value, is_enabled)
                    VALUES (?, ?, ?, ?, TRUE)
                    ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    updated_at = CURRENT_TIMESTAMP";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $category, $key, $value]);
            $updated++;
        }
    }

    return [
        'success' => true,
        'message' => 'Bulk user settings updated',
        'user_id' => $user_id,
        'settings_updated' => $updated
    ];
}

// =====================================================
// FUNCTION: Bulk Update Outlet Settings
// =====================================================
function bulkUpdateOutletSettings($data) {
    global $pdo;

    $outlet_id = $data['outlet_id'] ?? null;
    $settings = $data['settings'] ?? null;
    $updated_by = $data['updated_by'] ?? null;

    if (!$outlet_id || !$settings || !is_array($settings)) {
        throw new Exception('outlet_id and settings array required');
    }

    $updated = 0;
    foreach ($settings as $category => $categorySettings) {
        foreach ($categorySettings as $key => $value) {
            $sql = "INSERT INTO ANALYTICS_OUTLET_SETTINGS
                    (outlet_id, category, setting_key, setting_value, is_enabled, updated_by)
                    VALUES (?, ?, ?, ?, TRUE, ?)
                    ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    updated_by = VALUES(updated_by),
                    updated_at = CURRENT_TIMESTAMP";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$outlet_id, $category, $key, $value, $updated_by]);
            $updated++;
        }
    }

    return [
        'success' => true,
        'message' => 'Bulk outlet settings updated',
        'outlet_id' => $outlet_id,
        'settings_updated' => $updated
    ];
}

// =====================================================
// FUNCTION: Reset to Defaults
// =====================================================
function resetToDefaults($data) {
    global $pdo;

    $level = $data['level'] ?? null; // 'user' or 'outlet'
    $id = $data['id'] ?? null;

    if (!$level || !$id) {
        throw new Exception('level (user/outlet) and id required');
    }

    if ($level === 'user') {
        $sql = "DELETE FROM ANALYTICS_USER_PREFERENCES WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $deleted = $stmt->rowCount();

        return [
            'success' => true,
            'message' => 'User preferences reset to defaults',
            'user_id' => $id,
            'preferences_removed' => $deleted
        ];
    } elseif ($level === 'outlet') {
        $sql = "DELETE FROM ANALYTICS_OUTLET_SETTINGS WHERE outlet_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $deleted = $stmt->rowCount();

        return [
            'success' => true,
            'message' => 'Outlet settings reset to defaults',
            'outlet_id' => $id,
            'settings_removed' => $deleted
        ];
    } else {
        throw new Exception('Invalid level. Use "user" or "outlet"');
    }
}

// =====================================================
// FUNCTION: Toggle Feature (Quick On/Off)
// =====================================================
function toggleFeature($data) {
    global $pdo;

    $level = $data['level'] ?? 'user'; // user, outlet, or global
    $id = $data['id'] ?? null;
    $category = $data['category'] ?? null;
    $setting_key = $data['setting_key'] ?? null;
    $enabled = isset($data['enabled']) ? (bool)$data['enabled'] : null;

    if (!$category || !$setting_key) {
        throw new Exception('category and setting_key required');
    }

    if ($level === 'user') {
        if (!$id) throw new Exception('user_id required');

        // Get current value
        if ($enabled === null) {
            $sql = "SELECT is_enabled FROM ANALYTICS_USER_PREFERENCES
                    WHERE user_id = ? AND category = ? AND setting_key = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id, $category, $setting_key]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            $enabled = $current ? !$current['is_enabled'] : true;
        }

        $sql = "INSERT INTO ANALYTICS_USER_PREFERENCES
                (user_id, category, setting_key, setting_value, is_enabled)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                is_enabled = VALUES(is_enabled),
                updated_at = CURRENT_TIMESTAMP";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id, $category, $setting_key, $enabled ? 'true' : 'false', $enabled]);

        return [
            'success' => true,
            'message' => 'Feature toggled',
            'user_id' => $id,
            'enabled' => $enabled
        ];

    } elseif ($level === 'outlet') {
        if (!$id) throw new Exception('outlet_id required');

        if ($enabled === null) {
            $sql = "SELECT is_enabled FROM ANALYTICS_OUTLET_SETTINGS
                    WHERE outlet_id = ? AND category = ? AND setting_key = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id, $category, $setting_key]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            $enabled = $current ? !$current['is_enabled'] : true;
        }

        $sql = "INSERT INTO ANALYTICS_OUTLET_SETTINGS
                (outlet_id, category, setting_key, setting_value, is_enabled)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                is_enabled = VALUES(is_enabled),
                updated_at = CURRENT_TIMESTAMP";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id, $category, $setting_key, $enabled ? 'true' : 'false', $enabled]);

        return [
            'success' => true,
            'message' => 'Feature toggled',
            'outlet_id' => $id,
            'enabled' => $enabled
        ];

    } elseif ($level === 'global') {
        if ($enabled === null) {
            $sql = "SELECT is_enabled FROM ANALYTICS_GLOBAL_SETTINGS
                    WHERE category = ? AND setting_key = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$category, $setting_key]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            $enabled = $current ? !$current['is_enabled'] : true;
        }

        $sql = "UPDATE ANALYTICS_GLOBAL_SETTINGS
                SET is_enabled = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE category = ? AND setting_key = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$enabled, $category, $setting_key]);

        return [
            'success' => true,
            'message' => 'Global feature toggled',
            'enabled' => $enabled
        ];
    } else {
        throw new Exception('Invalid level. Use "user", "outlet", or "global"');
    }
}

// =====================================================
// FUNCTION: Get Feature Status
// =====================================================
function getFeatureStatus($data) {
    global $pdo;

    $user_id = $data['user_id'] ?? null;
    $category = $data['category'] ?? null;
    $setting_key = $data['setting_key'] ?? null;

    if (!$user_id || !$category || !$setting_key) {
        throw new Exception('user_id, category, and setting_key required');
    }

    $sql = "SELECT effective_value, source_level
            FROM V_EFFECTIVE_SETTINGS
            WHERE user_id = ? AND category = ? AND setting_key = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $category, $setting_key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        throw new Exception('Setting not found');
    }

    $enabled = strtolower($result['effective_value']) === 'true';

    return [
        'success' => true,
        'user_id' => $user_id,
        'category' => $category,
        'setting_key' => $setting_key,
        'enabled' => $enabled,
        'source' => $result['source_level']
    ];
}
