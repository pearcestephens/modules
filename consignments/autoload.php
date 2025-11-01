<?php
declare(strict_types=1);

/**
 * Consignments Module PSR-4 Autoloader
 *
 * Handles autoloading for Consignments\* namespace.
 * Maps Consignments\Services\X to consignments/src/Services/X.php
 *
 * @package CIS\Consignments
 */

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Consignments\\')) {
        $relativePath = substr($class, strlen('Consignments\\'));
        $filePath = __DIR__ . '/src/' . str_replace('\\', '/', $relativePath) . '.php';

        if (is_readable($filePath)) {
            require_once $filePath;
        }
    }
});
