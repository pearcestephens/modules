<?php
/**
 * Flagged Products Module Bootstrap
 * 
 * Auto-loads common dependencies for the Flagged Products module.
 * Include this file at the top of any Flagged Products module file.
 * 
 * Usage:
 *   require_once __DIR__ . '/bootstrap.php';
 * 
 * What it loads:
 *   - Base application (sessions, DB, etc.)
 *   - CIS Logger service
 *   - Anti-Cheat library
 *   - Repository layer
 *   - Shared module utilities
 * 
 * @package CIS\FlaggedProducts
 * @version 1.0.0
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT']);
}

// ============================================================================
// 1. LOAD BASE APPLICATION
// ============================================================================

// Base app (sessions, database, core functions)
if (file_exists(ROOT_PATH . '/app.php')) {
    require_once ROOT_PATH . '/app.php';
}

// Base config if separate
if (file_exists(ROOT_PATH . '/assets/functions/config.php')) {
    require_once ROOT_PATH . '/assets/functions/config.php';
}

// ============================================================================
// 2. LOAD CORE SERVICES
// ============================================================================

// Universal Logger
if (file_exists(ROOT_PATH . '/assets/services/CISLogger.php')) {
    require_once ROOT_PATH . '/assets/services/CISLogger.php';
}

// Query Builder (for secure SQL)
if (file_exists(ROOT_PATH . '/assets/functions/query-builder.php')) {
    require_once ROOT_PATH . '/assets/functions/query-builder.php';
}

// ============================================================================
// 3. LOAD MODULE COMPONENTS
// ============================================================================

define('FLAGGED_PRODUCTS_MODULE_PATH', __DIR__);

// Module Logger (wraps CISLogger with convenience methods)
if (file_exists(FLAGGED_PRODUCTS_MODULE_PATH . '/lib/Logger.php')) {
    require_once FLAGGED_PRODUCTS_MODULE_PATH . '/lib/Logger.php';
}

// Anti-Cheat Library
if (file_exists(FLAGGED_PRODUCTS_MODULE_PATH . '/lib/AntiCheat.php')) {
    require_once FLAGGED_PRODUCTS_MODULE_PATH . '/lib/AntiCheat.php';
}

// Repository Layer
if (file_exists(FLAGGED_PRODUCTS_MODULE_PATH . '/models/FlaggedProductsRepository.php')) {
    require_once FLAGGED_PRODUCTS_MODULE_PATH . '/models/FlaggedProductsRepository.php';
}

// Legacy functions (for backward compatibility)
if (file_exists(ROOT_PATH . '/assets/functions/flagged-product-functions.php')) {
    require_once ROOT_PATH . '/assets/functions/flagged-product-functions.php';
}

// ============================================================================
// 4. MODULE CONFIGURATION
// ============================================================================

// Module constants
if (!defined('FLAGGED_PRODUCTS_VERSION')) {
    define('FLAGGED_PRODUCTS_VERSION', '2.0.0');
}

if (!defined('FLAGGED_PRODUCTS_SECURITY_LEVEL')) {
    define('FLAGGED_PRODUCTS_SECURITY_LEVEL', 'MAXIMUM');
}

// ============================================================================
// 5. HELPER FUNCTIONS
// ============================================================================

/**
 * Check if user has permission to access flagged products
 */
function canAccessFlaggedProducts(): bool {
    if (!isset($_SESSION['userID'])) {
        return false;
    }
    
    // Check if user is blocked by anti-cheat
    if (class_exists('AntiCheat')) {
        if (AntiCheat::shouldBlockUser($_SESSION['userID'])) {
            return false;
        }
    }
    
    return true;
}

/**
 * Get current user's security score
 */
function getUserSecurityScore(): int {
    if (!isset($_SESSION['userID'])) {
        return 0;
    }
    
    if (class_exists('AntiCheat')) {
        $cheatScore = AntiCheat::getUserCheatScore($_SESSION['userID']);
        return $cheatScore['security_score'] ?? 100;
    }
    
    return 100;
}

/**
 * Log flagged products action
 */
function logFlaggedProductsAction(
    string $actionType,
    string $result = 'success',
    ?string $entityType = null,
    ?string $entityId = null,
    array $context = []
): ?int {
    if (class_exists('CISLogger')) {
        return CISLogger::action(
            'flagged_products',
            $actionType,
            $result,
            $entityType,
            $entityId,
            $context
        );
    }
    return null;
}

// ============================================================================
// 6. INITIALIZATION
// ============================================================================

// Initialize CIS Logger if available
if (class_exists('CISLogger')) {
    CISLogger::init();
}

// Initialize module logger
if (class_exists('FlaggedProducts\Lib\Logger')) {
    FlaggedProducts\Lib\Logger::init();
}

// Log module bootstrap
if (function_exists('logFlaggedProductsAction')) {
    logFlaggedProductsAction(
        'module_bootstrap',
        'success',
        null,
        null,
        ['version' => FLAGGED_PRODUCTS_VERSION]
    );
}
