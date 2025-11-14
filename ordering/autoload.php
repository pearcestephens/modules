<?php
/**
 * Ordering Module Autoloader
 */

spl_autoload_register(function ($class) {
    if (strpos($class, 'CIS\\Ordering\\') !== 0) {
        return;
    }
    
    $class_name = str_replace('CIS\\Ordering\\', '', $class);
    
    // Check controllers
    $file = __DIR__ . '/controllers/' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
        return;
    }
    
    // Check classes
    $file = __DIR__ . '/classes/' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
