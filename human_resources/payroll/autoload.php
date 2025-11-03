<?php
/**
 * Payroll Module - PSR-4 Autoloader
 *
 * Maps Payroll\ namespace to human_resources/payroll directory
 *
 * @package Payroll
 * @version 1.0.0
 * @created 2025-11-02
 */

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'Payroll\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return; // Not our namespace, let other autoloaders try
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
