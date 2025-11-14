<?php
/**
 * Forecasting Module Autoloader
 * Auto-loads all forecasting classes
 */

spl_autoload_register(function ($class) {
    // Only handle CIS\Forecasting namespace
    if (strpos($class, 'CIS\\Forecasting\\') !== 0) {
        return;
    }
    
    // Remove namespace prefix
    $class_name = str_replace('CIS\\Forecasting\\', '', $class);
    
    // Build file path
    $file = __DIR__ . '/classes/' . $class_name . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

// Load all classes immediately for convenience
require_once __DIR__ . '/classes/ForecastingEngine.php';
require_once __DIR__ . '/classes/SalesDataAggregator.php';
require_once __DIR__ . '/classes/DataAccuracyValidator.php';
require_once __DIR__ . '/classes/RealTimeMonitor.php';
require_once __DIR__ . '/classes/ProductCategoryOptimizer.php';
require_once __DIR__ . '/classes/SeasonalityEngine.php';
require_once __DIR__ . '/classes/ConversionRateOptimizer.php';
