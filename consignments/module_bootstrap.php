<?php
declare(strict_types=1);

// Minimal module bootstrap for Consignments module.
// Keep side-effects small; Kernel::boot() will load app.php and register autoloaders.

// Define module root for downstream includes if needed
if (!defined('MODULE_CONSIGNMENTS_ROOT')) {
    define('MODULE_CONSIGNMENTS_ROOT', __DIR__);
}

// Timezone from env if provided
$__tz = $_ENV['APP_TZ'] ?? '';
if (is_string($__tz) && $__tz !== '') {
    @date_default_timezone_set($__tz);
}

// Error reporting based on APP_DEBUG env
$__debug = $_ENV['APP_DEBUG'] ?? '';
$__isDebug = ($__debug === '1' || strtolower((string)$__debug) === 'true');
if ($__isDebug) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
}

unset($__tz, $__debug, $__isDebug);
