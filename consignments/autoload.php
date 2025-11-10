<?php
declare(strict_types=1);

/**
 * Consignments Module PSR-4 Autoloader
 *
 * Handles autoloading for CIS\Consignments\* namespace.
 * Maps CIS\Consignments\Services\X to consignments/lib/Services/X.php
 *
 * @package CIS\Consignments
 */

spl_autoload_register(function (string $class): void {
    // Handle CIS\Consignments\* namespace
    if (str_starts_with($class, 'CIS\\Consignments\\')) {
        $relativePath = substr($class, strlen('CIS\\Consignments\\'));

        // Try lib/ directory first (where Services/ lives)
        $filePath = __DIR__ . '/lib/' . str_replace('\\', '/', $relativePath) . '.php';
        if (is_readable($filePath)) {
            require_once $filePath;
            return;
        }

        // Fall back to src/ directory
        $filePath = __DIR__ . '/src/' . str_replace('\\', '/', $relativePath) . '.php';
        if (is_readable($filePath)) {
            require_once $filePath;
            return;
        }
    }

    // Legacy: Handle Consignments\* namespace (without CIS prefix)
    if (str_starts_with($class, 'Consignments\\')) {
        $relativePath = substr($class, strlen('Consignments\\'));
        $filePath = __DIR__ . '/src/' . str_replace('\\', '/', $relativePath) . '.php';

        if (is_readable($filePath)) {
            require_once $filePath;
        }
    }
});
