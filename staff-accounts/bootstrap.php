<?php
/**
 * Staff Accounts Module Bootstrap
 * 
 * Initializes the staff accounts management module by:
 * - Loading shared base bootstrap (sessions, DB, error handling, etc.)
 * - Defining module-specific constants
 * - Loading module service classes
 * - Setting up authentication checks
 * 
 * @package CIS\Modules\StaffAccounts
 * @version 2.0.0
 */

// ============================================================================
// 1. LOAD SHARED BASE BOOTSTRAP (provides sessions, DB, ErrorHub, etc.)
// ============================================================================

require_once __DIR__ . '/../shared/bootstrap.php';

// ============================================================================
// 2. DEFINE MODULE-SPECIFIC CONSTANTS
// ============================================================================

define('STAFF_ACCOUNTS_MODULE_PATH', __DIR__);
define('STAFF_ACCOUNTS_API_PATH', __DIR__ . '/api');
define('STAFF_ACCOUNTS_LIB_PATH', __DIR__ . '/lib');
define('STAFF_ACCOUNTS_VIEWS_PATH', __DIR__ . '/views');
define('STAFF_ACCOUNTS_CSS_PATH', __DIR__ . '/css');
define('STAFF_ACCOUNTS_JS_PATH', __DIR__ . '/js');

// ============================================================================
// 3. LOAD MODULE SERVICE CLASSES
// ============================================================================
require_once STAFF_ACCOUNTS_LIB_PATH . '/StaffAccountService.php';
require_once STAFF_ACCOUNTS_LIB_PATH . '/PaymentService.php';
require_once STAFF_ACCOUNTS_LIB_PATH . '/PaymentAllocationService.php';
require_once STAFF_ACCOUNTS_LIB_PATH . '/XeroPayrollService.php';
require_once STAFF_ACCOUNTS_LIB_PATH . '/ReconciliationService.php';
require_once STAFF_ACCOUNTS_LIB_PATH . '/VendApiService.php';
require_once STAFF_ACCOUNTS_LIB_PATH . '/XeroApiService.php';
require_once STAFF_ACCOUNTS_LIB_PATH . '/EmployeeMappingService.php';

// Load legacy dependencies ONLY in web mode (skip for CLI to avoid Xero/Vend dependencies)
if (!defined('CIS_CLI_MODE')) {
    require_once ROOT_PATH . '/assets/functions/VendAPI.php';
    require_once ROOT_PATH . '/assets/services/xero-sdk/xero-init.php';

    // Define BASE_PATH for vend-payment-lib.php (it checks for this constant)
    if (!defined('BASE_PATH')) {
        define('BASE_PATH', ROOT_PATH);
    }
    require_once ROOT_PATH . '/assets/functions/xeroAPI/vend-payment-lib.php';
}

// Use namespaces
use CIS\Modules\StaffAccounts\StaffAccountService;
use CIS\Modules\StaffAccounts\PaymentService;
use CIS\Modules\StaffAccounts\SnapshotService;
use CIS\Modules\StaffAccounts\VendApiService;
use CIS\Modules\StaffAccounts\XeroApiService;
use CIS\API\StandardResponse;

// ============================================================================
// 4. AUTHENTICATION CHECK (Web mode only)
// ============================================================================

// Bot bypass for testing - bypasses auth completely
$bot_bypass = isset($_GET['bot_test']) && $_GET['bot_test'] === 'comprehensive_2025';

// Check if user is logged in (session variable is 'userID' not 'user_id')
// Skip authentication check in CLI mode or bot bypass mode
if (!defined('CIS_CLI_MODE') && !$bot_bypass && !isset($_SESSION['userID'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// ============================================================================
// MODULE LOADED SUCCESSFULLY
// ============================================================================
define('STAFF_ACCOUNTS_MODULE_LOADED', true);
