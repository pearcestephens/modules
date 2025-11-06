<?php
/**
 * Flagged Products Module - Bootstrap
 *
 * Initializes the Flagged Products module with proper autoloading,
 * configuration, and dependency injection.
 *
 * @package CIS\Modules\FlaggedProducts
 * @version 1.0.0
 * @since 2025-11-05
 */

declare(strict_types=1);

// Define module constants
define('FLAGGED_PRODUCTS_MODULE_PATH', __DIR__);
define('FLAGGED_PRODUCTS_MODULE_VERSION', '1.0.0');

// Load CIS core
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

// Module autoloader
spl_autoload_register(function ($class) {
    $prefix = 'CIS\\FlaggedProducts\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);

    // Map Controllers\ClassName to controllers/ClassName.php
    // Map Models\ClassName to models/ClassName.php
    $relativeClass = str_replace('Controllers\\', 'controllers/', $relativeClass);
    $relativeClass = str_replace('Models\\', 'models/', $relativeClass);
    $relativeClass = str_replace('Services\\', 'lib/', $relativeClass);

    $file = $baseDir . $relativeClass . '.php';

    if (file_exists($file)) {
        require $file;
    } else {
        error_log("Autoloader: Could not find file: {$file} for class: {$class}");
    }
});

// Load configuration
$config = require __DIR__ . '/config/module.php';

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Return configuration for use by other files
return $config;
