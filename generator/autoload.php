<?php
spl_autoload_register(function ($class) {
    if (strpos($class, 'CIS\\Generator\\') !== 0) return;
    $class_name = str_replace('CIS\\Generator\\', '', $class);
    foreach (['controllers', 'models', 'classes'] as $dir) {
        $file = __DIR__ . "/$dir/" . $class_name . '.php';
        if (file_exists($file)) { require_once $file; return; }
    }
});
