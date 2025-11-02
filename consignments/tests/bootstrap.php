<?php declare(strict_types=1);

/**
 * PHPUnit Bootstrap for Consignments Module Tests
 */

// Autoloader setup - adjust path based on your project structure
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
];

$autoloaderFound = false;
foreach ($autoloadPaths as $file) {
    if (file_exists($file)) {
        require_once $file;
        $autoloaderFound = true;
        break;
    }
}

if (!$autoloaderFound) {
    // Fallback: manual PSR-4 autoloader for testing without Composer
    spl_autoload_register(function ($class) {
        $prefix = 'Consignments\\';
        $baseDir = __DIR__ . '/../';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    });
}

// Set timezone
date_default_timezone_set('UTC');

// Load test environment config if exists
$testEnvFile = __DIR__ . '/../.env.testing';
if (file_exists($testEnvFile)) {
    // Simple .env parser for tests
    $lines = file($testEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}
