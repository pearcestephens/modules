<?php
/**
 * CIS Base Module Bootstrap
 * 
 * Ultra-minimal bootstrap for all CIS modules.
 * Just loads core services and initializes environment.
 * 
 * Usage in any module:
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/base/bootstrap.php';
 * 
 * That's it! Everything else is ready.
 * 
 * @package CIS\Base
 * @version 1.0.0
 */

declare(strict_types=1);

// Prevent multiple initialization
if (defined('CIS_BASE_INITIALIZED')) {
    return;
}

// 1. DON'T start session here - let Session::init() handle it properly
// (session_start() was causing "parameters cannot be changed" warning)

// 2. Load advanced CISLogger first (before ErrorHandler needs it)
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/CISLogger.php';

// 3. Load base services (lightweight wrappers)
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/ErrorHandler.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/SecurityMiddleware.php';

// 4. Auto-initialize core FIRST (before loading services that depend on them!)
CIS\Base\Database::init();          // Initialize PDO first
CIS\Base\Session::init();           // Initialize session BEFORE Auth.php loads
CIS\Base\ErrorHandler::init();      // Set up error/exception handlers

// 3. Load services from assets/services/ (heavier utilities) - NOW they can use Database & Session
$servicesPath = $_SERVER['DOCUMENT_ROOT'] . '/assets/services/';
require_once $servicesPath . 'RateLimiter.php';
require_once $servicesPath . 'Cache.php';
require_once $servicesPath . 'Auth.php';          // Now Session class is available!
require_once $servicesPath . 'Encryption.php';
require_once $servicesPath . 'Sanitizer.php';
require_once $servicesPath . 'FileUpload.php';
require_once $servicesPath . 'Notification.php';

// CISLogger auto-initializes at bottom of CISLogger.php (already loaded, captures user ID from session)
CIS\Base\SecurityMiddleware::init(); // Initialize CSRF tokens and security features

// Services auto-initialize themselves (they call ::init() at bottom of file)

// 4. Mark as initialized
define('CIS_BASE_INITIALIZED', true);

// ✅ DONE! Your module code starts here.
// Available:
// - $con (MySQLi from app.php)
// - CIS\Base\Database::pdo() / mysqli() (Dual database support)
// - CIS\Base\Session (Session management)
// - CISLogger (Universal logging - captures user ID, session, trace IDs automatically)
// - Response (JSON/HTML helpers)
// - Validator (Input validation)
// - SecurityMiddleware (CSRF protection)
// - RateLimiter (Rate limiting / abuse prevention)
// - Cache (Performance caching)
// - Auth (Permission checking)
// - Encryption (Encrypt/decrypt sensitive data)
// - Sanitizer (Clean user input)
// - FileUpload (Safe file uploads)
// - Notification (Multi-channel notifications)
// - CIS\Base\Database::mysqli() (same as $con)
// - $_SESSION (properly configured, shared across CIS)
