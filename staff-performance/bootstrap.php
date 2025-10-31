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

require_once __DIR__ . '/../shared/bootstrap.php';

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

// Module-specific service classes
require_once STAFF_PERFORMANCE_LIB_PATH . '/GoogleReviewsGamification.php';
require_once STAFF_PERFORMANCE_LIB_PATH . '/StaffPerformanceTracker.php';
require_once STAFF_PERFORMANCE_LIB_PATH . '/AchievementEngine.php';

// ============================================================================
// 4. AUTHENTICATION AND PERMISSIONS
// ============================================================================

// Ensure user is logged in (provided by shared bootstrap)
requireLoggedInUser();

// Check if user has access to staff performance features
if (!hasPermission('view_staff_performance')) {
    redirectToLogin('Insufficient permissions for Staff Performance module');
}

// ============================================================================
// 5. MODULE-SPECIFIC CONFIGURATIONS
// ============================================================================

// Google Reviews Gamification settings
$config['gamification'] = [
    'enabled' => true,
    'individual_bonus' => 10.00,  // $10 per name mention
    'team_bonus' => 10.00,       // $10 split when no names mentioned
    'points_per_mention' => 100,  // Points for individual mentions
    'team_points' => 20,         // Points for team bonuses
    'processing_frequency' => '6 hours',
    'notification_enabled' => true,
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
// 6. INITIALIZE MODULE SERVICES
// ============================================================================

try {
    // Initialize the Google Reviews Gamification service
    $googleReviewsGamification = new GoogleReviewsGamification($pdo, $config['gamification']);
    
    // Initialize the Staff Performance Tracker
    $staffPerformanceTracker = new StaffPerformanceTracker($pdo, $config['performance']);
    
    // Initialize the Achievement Engine
    $achievementEngine = new AchievementEngine($pdo, $config);
    
} catch (Exception $e) {
    error_log("Staff Performance Module initialization error: " . $e->getMessage());
    showError("Failed to initialize Staff Performance module. Please try again.");
}

// ============================================================================
// 7. SET MODULE METADATA
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