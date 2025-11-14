<?php
/**
 * Inventory Sync Module Autoloader
 * PSR-4 style autoloading
 */

spl_autoload_register(function ($class) {
    // Only autoload classes in this namespace
    if (strpos($class, 'CIS\\InventorySync\\') !== 0) {
        return;
    }

    // Remove namespace prefix
    $class_name = str_replace('CIS\\InventorySync\\', '', $class);

    // Check multiple directories
    $directories = ['classes', 'controllers', 'models'];

    foreach ($directories as $dir) {
        $file = __DIR__ . "/$dir/" . $class_name . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
