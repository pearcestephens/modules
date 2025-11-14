<?php
spl_autoload_register(function ($class) {
    if (strpos($class, 'CIS\\Intelligence\\') !== 0) return;
    $class_name = str_replace('CIS\\Intelligence\\', '', $class);
    foreach (['classes', 'controllers'] as $dir) {
        $file = __DIR__ . "/$dir/" . $class_name . '.php';
        if (file_exists($file)) { require_once $file; return; }
    }
});
