<?php
/**
 * CIS CONTROL PANEL - Master Configuration & Management System
 *
 * Centralized control for:
 * - Module inventory & versioning
 * - Configuration management
 * - Backup & restore
 * - Environment sync (Dev/Staging/Production)
 * - System documentation
 *
 * @package CIS\Modules\ControlPanel
 * @version 1.0.0
 * @author The Vape Shed Development Team
 */

// ============================================================================
// 1. LOAD SHARED BASE BOOTSTRAP
// ============================================================================

if (file_exists(__DIR__ . '/../shared/bootstrap.php')) {
    require_once __DIR__ . '/../shared/bootstrap.php';
} else {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/config.php';
    cis_require_login();
}

// ============================================================================
// 2. DEFINE MODULE CONSTANTS
// ============================================================================

define('CONTROL_PANEL_MODULE_PATH', '/modules/control-panel');
define('CONTROL_PANEL_API_PATH', CONTROL_PANEL_MODULE_PATH . '/api');
define('CONTROL_PANEL_LIB_PATH', __DIR__ . '/lib');
define('CONTROL_PANEL_VIEWS_PATH', __DIR__ . '/views');
define('CONTROL_PANEL_CSS_PATH', CONTROL_PANEL_MODULE_PATH . '/assets/css');
define('CONTROL_PANEL_JS_PATH', CONTROL_PANEL_MODULE_PATH . '/assets/js');
define('CONTROL_PANEL_BACKUPS_PATH', __DIR__ . '/backups');
define('CONTROL_PANEL_DOCS_PATH', __DIR__ . '/docs');

// ============================================================================
// 3. DATABASE CONNECTION
// ============================================================================

try {
    if (class_exists('CIS\\Base\\Database')) {
        $db = CIS\Base\Database::getConnection();
    } else {
        // Fixed: Removed leading slash to prevent double-slash (DOCUMENT_ROOT already ends with /)
        require_once $_SERVER['DOCUMENT_ROOT'] . 'assets/functions/mysql.php';
        $db = new PDO("mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4", "jcepnzzkmj", "wprKh9Jq63");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
} catch (Exception $e) {
    error_log("Control Panel DB Error: " . $e->getMessage());
    die("Database connection failed. Please contact support.");
}

// ============================================================================
// 4. AUTHENTICATION - ADMIN ONLY
// ============================================================================

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Check if user is admin (you can adjust this logic)
$current_user_id = $_SESSION['user_id'];
$is_admin = false;

try {
    $stmt = $db->prepare("
        SELECT role, permissions
        FROM staff_accounts
        WHERE staff_id = ?
    ");
    $stmt->execute([$current_user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if user is admin or has control panel access
    $is_admin = (
        $user['role'] === 'admin' ||
        $user['role'] === 'manager' ||
        strpos($user['permissions'], 'control_panel') !== false
    );
} catch (Exception $e) {
    error_log("Control Panel Auth Error: " . $e->getMessage());
}

if (!$is_admin) {
    die('<h1>Access Denied</h1><p>You do not have permission to access the Control Panel.</p>');
}

// ============================================================================
// 5. LOAD AUTOLOADER AND SERVICES
// ============================================================================

// Service classes
$serviceFiles = [
    'ModuleRegistry.php',
    'ConfigManager.php',
    'BackupManager.php',
    'EnvironmentSync.php',
    'DocumentationBuilder.php'
];

foreach ($serviceFiles as $file) {
    $path = CONTROL_PANEL_LIB_PATH . '/' . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}

// ============================================================================
// 6. MODULE CONFIGURATION
// ============================================================================

$controlPanelConfig = [
    'version' => '1.0.0',
    'name' => 'CIS Control Panel',
    'description' => 'Master configuration and management system',

    'environments' => [
        'production' => [
            'name' => 'Production',
            'url' => 'https://staff.vapeshed.co.nz',
            'db_host' => '127.0.0.1',
            'db_name' => 'jcepnzzkmj',
            'backup_enabled' => true
        ],
        'staging' => [
            'name' => 'Staging',
            'url' => 'https://staging.staff.vapeshed.co.nz',
            'db_host' => '127.0.0.1',
            'db_name' => 'jcepnzzkmj_staging',
            'backup_enabled' => true
        ],
        'development' => [
            'name' => 'Development',
            'url' => 'http://localhost:8000',
            'db_host' => '127.0.0.1',
            'db_name' => 'jcepnzzkmj_dev',
            'backup_enabled' => false
        ]
    ],

    'backup' => [
        'retention_days' => 30,
        'max_backups' => 50,
        'auto_backup' => true,
        'backup_schedule' => 'daily',
        'compression' => true
    ],

    'modules' => [
        'auto_discover' => true,
        'version_tracking' => true,
        'dependency_check' => true
    ]
];

// ============================================================================
// 7. MODULE READY
// ============================================================================

// ✅ CRITICAL FIX: Register shutdown handler to cleanup PDO connection
register_shutdown_function(function() {
    global $db;
    if (isset($db) && $db instanceof PDO) {
        $db = null;
    }
});

define('CONTROL_PANEL_MODULE_LOADED', true);
