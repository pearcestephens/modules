<?php
spl_autoload_register(function ($class) {
    if (strpos($class, 'CIS\\Tracking\\') !== 0) return;
    $class_name = str_replace('CIS\\Tracking\\', '', $class);
    $file = __DIR__ . '/classes/' . $class_name . '.php';
    if (file_exists($file)) require_once $file;
});
