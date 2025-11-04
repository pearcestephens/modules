<?php
/**
 * CIS Base Module Bootstrap
 *
 * Modern bootstrap using Application container and Composer autoloading
 *
 * @package CIS\Base
 * @version 2.0.0
 */

declare(strict_types=1);

// Prevent multiple initialization
if (defined('CIS_BASE_INITIALIZED')) {
    return $app ?? null;
}

// Load Composer autoloader
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    // Fallback: Generate autoloader if not exists
    exec('cd ' . dirname(__DIR__) . ' && composer dump-autoload 2>&1', $output, $return);
    if ($return !== 0 && !file_exists($autoloadPath)) {
        die('Composer autoloader not found. Run: composer dump-autoload');
    }
}
require_once $autoloadPath;

// Create application instance
$app = \CIS\Base\Core\Application::getInstance();

// Load configuration
$app->withConfig(__DIR__ . '/../config/app.php');

// Register all services
$app->registerServices();

// Boot the application
$app->boot();

// Mark as initialized
define('CIS_BASE_INITIALIZED', true);

// Backward compatibility: Create global helper for database
if (!function_exists('app')) {
    /**
     * Get application instance or service from container
     */
    function app(?string $service = null) {
        $app = \CIS\Base\Core\Application::getInstance();
        return $service ? $app->make($service) : $app;
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value
     */
    function config(string $key, $default = null) {
        return app()->config($key, $default);
    }
}

// Return application instance
return $app;
