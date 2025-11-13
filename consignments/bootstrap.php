<?php
/**
 * Bootstrap for Consignments Module
 *
 * Loads the base framework and initializes module-specific services.
 *
 * @package CIS\Consignments
 * @version 2.0.0
 */

declare(strict_types=1);

// Load BASE framework bootstrap
require_once __DIR__ . '/../base/bootstrap.php';

// Load consolidated services autoloader
require_once __DIR__ . '/autoload_services.php';


// Module-specific autoloader for legacy classes
spl_autoload_register(function ($class) {
    // Handle CIS\Consignments namespace
    if (strpos($class, 'CIS\\Consignments\\') === 0) {
        $relativePath = str_replace('CIS\\Consignments\\', '', $class);
        $relativePath = str_replace('\\', '/', $relativePath);

        // Try multiple locations
        $paths = [
            __DIR__ . '/controllers/' . $relativePath . '.php',
            __DIR__ . '/services/' . $relativePath . '.php',
            __DIR__ . '/lib/' . $relativePath . '.php',
            __DIR__ . '/lib/Services/' . $relativePath . '.php',
            __DIR__ . '/infra/' . $relativePath . '.php',
        ];

        foreach ($paths as $file) {
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }

    // Handle CIS\Modules\Consignments namespace (FreightIntegration)
    if (strpos($class, 'CIS\\Modules\\Consignments\\') === 0) {
        $relativePath = str_replace('CIS\\Modules\\Consignments\\', '', $class);
        $relativePath = str_replace('\\', '/', $relativePath);

        $file = __DIR__ . '/lib/' . $relativePath . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Module constants
define('CONSIGNMENTS_MODULE_PATH', __DIR__);
define('CONSIGNMENTS_ASSETS_PATH', __DIR__ . '/assets');
define('CONSIGNMENTS_VIEWS_PATH', __DIR__ . '/views');

// Logging helper for consignments
if (!function_exists('consignments_log')) {
    /**
     * Log consignments-specific messages
     */
    function consignments_log(string $message, string $level = 'info', array $context = []): void {
        $logFile = __DIR__ . '/_logs/consignments.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextJson = !empty($context) ? json_encode($context) : '';
        $logLine = "[{$timestamp}] [{$level}] {$message} {$contextJson}\n";

        @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
}

// Module initialization complete
consignments_log('Module bootstrap complete', 'info');
