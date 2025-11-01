<?php
/**
 * Consignments Module Bootstrap
 *
 * Auto-loads common dependencies for the Consignments module.
 * Include this file at the top of any Consignments module file.
 *
 * Usage:
 *   require_once __DIR__ . '/bootstrap.php';
 *
 * What it loads:
 *   - Base application (sessions, DB, etc.)
 *   - Shared API response envelope
 *   - Module-specific shared files (if they exist)
 *
 * @package CIS\Consignments
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

// Load base/bootstrap.php for core services (Database, Session, Logger, etc.)
require_once __DIR__ . '/../base/bootstrap.php';

// ============================================================================
// 2. LOAD CONSIGNMENTS PSR-4 AUTOLOADER
// ============================================================================

// Load autoloader for Consignments\* namespace
require_once __DIR__ . '/autoload.php';

// ============================================================================
// 3. LOAD SHARED MODULES (cross-module utilities)
// ============================================================================

// Define module constants first (needed for loading)
if (!defined('CONSIGNMENTS_MODULE_PATH')) {
    define('CONSIGNMENTS_MODULE_PATH', ROOT_PATH . '/modules/consignments');
}

if (!defined('CONSIGNMENTS_API_PATH')) {
    define('CONSIGNMENTS_API_PATH', CONSIGNMENTS_MODULE_PATH . '/api');
}

if (!defined('CONSIGNMENTS_SHARED_PATH')) {
    define('CONSIGNMENTS_SHARED_PATH', CONSIGNMENTS_MODULE_PATH . '/shared');
}

// Load all shared utilities (cross-module helpers)
foreach (glob(ROOT_PATH . '/modules/shared/functions/*.php') as $sharedFunc) {
    require_once $sharedFunc;
}

// ============================================================================
// 3. LOAD BASE MODULE (if in subfolder like stock-transfers)
// ============================================================================

// Base module Session class
if (file_exists(ROOT_PATH . '/modules/base/lib/Session.php')) {
    require_once ROOT_PATH . '/modules/base/lib/Session.php';
}

// ============================================================================
// 4. LOAD SHARED API COMPONENTS
// ============================================================================

// 🆕 NEW STANDARD: CIS API Response Contract (v1.0.0)
// ALL APIs must use this standardized response envelope
if (file_exists(ROOT_PATH . '/modules/shared/api/StandardResponse.php')) {
    require_once ROOT_PATH . '/modules/shared/api/StandardResponse.php';
}

// Legacy API Response envelope (backwards compatibility)
// TODO: Migrate all endpoints to StandardResponse, then remove this
if (file_exists(ROOT_PATH . '/modules/shared/api/ApiResponse.php')) {
    require_once ROOT_PATH . '/modules/shared/api/ApiResponse.php';
}

// ============================================================================
// 5. AUTO-LOAD CONSIGNMENTS SHARED FILES
// ============================================================================

$consignmentsSharedDir = CONSIGNMENTS_MODULE_PATH . '/shared';

if (file_exists($consignmentsSharedDir . '/functions/transfers.php')) {
    require_once $consignmentsSharedDir . '/functions/transfers.php';
}
if (is_dir($consignmentsSharedDir . '/lib')) {
    foreach (glob($consignmentsSharedDir . '/lib/*.php') as $libFile) {
        require_once $libFile;
    }
}
if (is_dir($consignmentsSharedDir . '/functions')) {
    foreach (glob($consignmentsSharedDir . '/functions/*.php') as $functionFile) {
        if (basename($functionFile) !== 'transfers.php') {
            require_once $functionFile;
        }
    }
}

// ============================================================================
// 6. HELPER FUNCTION: Load submodule files
// ============================================================================

/**
 * Load files from a specific Consignments subfolder
 *
 * Example:
 *   consignments_load_subfolder('stock-transfers/functions');
 *
 * This will load all PHP files from:
 *   /modules/consignments/stock-transfers/functions/*.php
 *
 * @param string $subfolder Path relative to consignments module
 * @return int Number of files loaded
 */
function consignments_load_subfolder(string $subfolder): int {
    $fullPath = CONSIGNMENTS_MODULE_PATH . '/' . ltrim($subfolder, '/');
    $loadedCount = 0;

    if (is_dir($fullPath)) {
        foreach (glob($fullPath . '/*.php') as $file) {
            // Skip bootstrap.php to prevent recursion
            if (basename($file) === 'bootstrap.php') {
                continue;
            }

            require_once $file;
            $loadedCount++;
        }
    }

    return $loadedCount;
}

/**
 * Load a specific file from Consignments module
 *
 * Example:
 *   consignments_load_file('stock-transfers/functions/pack.php');
 *
 * @param string $filePath Path relative to consignments module
 * @return bool True if file was loaded, false if not found
 */
function consignments_load_file(string $filePath): bool {
    $fullPath = CONSIGNMENTS_MODULE_PATH . '/' . ltrim($filePath, '/');

    if (file_exists($fullPath)) {
        require_once $fullPath;
        return true;
    }

    return false;
}

// ============================================================================
// 7. AUTO-DETECT AND LOAD CURRENT SUBFOLDER'S FUNCTIONS
// ============================================================================

// Get the calling file's directory
$callingFile = debug_backtrace()[0]['file'] ?? '';
$callingDir = dirname($callingFile);

// Check if calling file is within a subfolder (e.g., stock-transfers, purchase-orders)
if (strpos($callingDir, CONSIGNMENTS_MODULE_PATH) === 0) {
    // Extract subfolder path (e.g., "stock-transfers")
    $relativePath = str_replace(CONSIGNMENTS_MODULE_PATH . '/', '', $callingDir);

    // Only auto-load if it's a subfolder (not root consignments)
    if (strpos($relativePath, '/') !== false || $relativePath !== 'consignments') {
        // Try to load functions folder for this subfolder
        $functionsFolder = dirname($callingDir) . '/functions';

        if (is_dir($functionsFolder)) {
            foreach (glob($functionsFolder . '/*.php') as $functionFile) {
                // Skip if already loaded
                if (!in_array(realpath($functionFile), get_included_files())) {
                    require_once $functionFile;
                }
            }
        }
    }
}

// ============================================================================
// BOOTSTRAP COMPLETE
// ============================================================================

// Set flag to indicate bootstrap is loaded
if (!defined('CONSIGNMENTS_BOOTSTRAP_LOADED')) {
    define('CONSIGNMENTS_BOOTSTRAP_LOADED', true);
}

// ============================================================================
// 8. DATABASE HELPER FUNCTIONS FOR SERVICES
// ============================================================================

/**
 * Get read-only database connection (PDO)
 * Used by service layer for queries
 *
 * @return PDO Read-only database connection
 */
function db_ro(): PDO {
    return CIS\Base\Database::pdo();
}

/**
 * Get read-write database connection (PDO) or null
 * Used by service layer for writes
 *
 * On production slaves without write access, this returns null
 * and write operations will throw exceptions.
 *
 * @return PDO|null Read-write database connection or null
 */
function db_rw_or_null(): ?PDO {
    // For now, same as read-only (single master setup)
    // In future multi-master setup, this would check for write permissions
    return CIS\Base\Database::pdo();
}
