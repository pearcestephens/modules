<?php
/**
 * Staff Performance Module Bootstrap
 *
 * Initializes the staff performance and Google Reviews gamification module by:
 * - Loading shared base bootstrap (sessions, DB, error handling, etc.)
 * - Defining module-specific constants
 * - Loading module service classes
 * - Setting up authentication checks
 *
 * @package CIS\Modules\StaffPerformance
 * @version 1.0.0
 */

// ============================================================================
// 1. LOAD SHARED BASE BOOTSTRAP (provides sessions, DB, ErrorHub, etc.)
// ============================================================================

// Check if shared bootstrap exists, otherwise use legacy path
if (file_exists(__DIR__ . '/../shared/bootstrap.php')) {
    require_once __DIR__ . '/../shared/bootstrap.php';
} else {
    // Fallback to legacy bootstrap
    require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/config.php';
    cis_require_login();
}

// ============================================================================
// 2. DEFINE MODULE-SPECIFIC CONSTANTS
// ============================================================================

define('STAFF_PERFORMANCE_MODULE_PATH', __DIR__);
define('STAFF_PERFORMANCE_API_PATH', __DIR__ . '/api');
define('STAFF_PERFORMANCE_LIB_PATH', __DIR__ . '/lib');
define('STAFF_PERFORMANCE_VIEWS_PATH', __DIR__ . '/views');
define('STAFF_PERFORMANCE_CSS_PATH', __DIR__ . '/css');
define('STAFF_PERFORMANCE_JS_PATH', __DIR__ . '/js');
define('STAFF_PERFORMANCE_DATABASE_PATH', __DIR__ . '/database');

// ============================================================================
// 3. LOAD MODULE AUTOLOADER AND SERVICES
// ============================================================================

// Load widgets library
if (file_exists(STAFF_PERFORMANCE_LIB_PATH . '/PerformanceWidgets.php')) {
    require_once STAFF_PERFORMANCE_LIB_PATH . '/PerformanceWidgets.php';
}

// Module-specific service classes (if they exist)
$serviceFiles = [
    'GoogleReviewsGamification.php',
    'StaffPerformanceTracker.php',
    'AchievementEngine.php'
];

foreach ($serviceFiles as $file) {
    $path = STAFF_PERFORMANCE_LIB_PATH . '/' . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}

// ============================================================================
// 4. DATABASE CONNECTION
// ============================================================================

// Get database connection
try {
    if (class_exists('CIS\\Base\\Database')) {
        $db = CIS\Base\Database::getConnection();
    } else {
        // Fallback to legacy connection
        require_once $_SERVER['DOCUMENT_ROOT'] . '/mysql.php';
        $db = new PDO("mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4", "jcepnzzkmj", "wprKh9Jq63");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
} catch (Exception $e) {
    error_log("Staff Performance DB Error: " . $e->getMessage());
    die("Database connection failed. Please contact support.");
}

// ============================================================================
// 5. AUTHENTICATION AND PERMISSIONS (Simplified)
// ============================================================================

// Basic auth check - ensure user is logged in
if (!isset($_SESSION['userID'])) {
    header('Location: /login.php');
    exit;
}

// Get current user ID
$current_user_id = $_SESSION['userID'];

// ============================================================================
// 6. MODULE-SPECIFIC CONFIGURATIONS
// ============================================================================

// Google Reviews Gamification settings
$config['gamification'] = [
    'enabled' => true,
    'individual_bonus' => 10.00,  // $10 per name mention
    'team_bonus' => 10.00,       // $10 split when no names mentioned
    'points_per_mention' => 100,  // Points for individual mentions
    'team_points' => 20,         // Points for team bonuses
    'processing_frequency' => '6 hours',
    'notification_enabled' => false,  // Disabled - notification system being rebuilt
];

// Performance tracking settings
$config['performance'] = [
    'track_google_reviews' => true,
    'track_sales_performance' => true,
    'track_customer_feedback' => true,
    'monthly_reports' => true,
    'achievement_system' => true,
];

// ============================================================================
// 7. INITIALIZE MODULE SERVICES (Optional)
// ============================================================================

// Services will be initialized on-demand in controllers/views

// ============================================================================
// 8. SET MODULE METADATA
// ============================================================================

$moduleInfo = [
    'name' => 'Staff Performance & Google Reviews Gamification',
    'version' => '1.0.0',
    'description' => 'Track staff performance through Google Reviews mentions and award gamification bonuses',
    'author' => 'CIS Development Team',
    'requires' => ['shared', 'authentication'],
    'features' => [
        'google_reviews_gamification',
        'staff_performance_tracking',
        'achievement_system',
        'automated_bonus_calculation',
        'performance_reporting'
    ]
];

// ============================================================================
// 8. MODULE READY
// ============================================================================

// Module is now ready for use
define('STAFF_PERFORMANCE_MODULE_LOADED', true);
