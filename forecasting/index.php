<?php
/**
 * Forecasting Module Entry Point
 * Use this file to load and use the forecasting system
 */

// Load autoloader
require_once __DIR__ . '/autoload.php';

// Example usage:
/*
use CIS\Forecasting\ForecastingEngine;
use CIS\Forecasting\ProductCategoryOptimizer;
use CIS\Forecasting\SeasonalityEngine;
use CIS\Forecasting\ConversionRateOptimizer;

// Initialize with database connection
$pdo = new PDO('mysql:host=localhost;dbname=vend', 'username', 'password');

// Create forecasting engine
$engine = new ForecastingEngine($pdo);

// Get forecast for a product
$forecast = $engine->calculateForecast($product_id, $forecast_days = 30);

// Use advanced modules
$categoryOptimizer = new ProductCategoryOptimizer($pdo);
$seasonalityEngine = new SeasonalityEngine($pdo);
$conversionOptimizer = new ConversionRateOptimizer($pdo);
*/

echo "Forecasting Module v2.6 loaded successfully!\n";
echo "All classes available:\n";
echo "- ForecastingEngine\n";
echo "- SalesDataAggregator (v2.0)\n";
echo "- DataAccuracyValidator (v2.0)\n";
echo "- RealTimeMonitor (v2.0)\n";
echo "- ProductCategoryOptimizer (v2.5)\n";
echo "- SeasonalityEngine (v2.5)\n";
echo "- ConversionRateOptimizer (v2.6)\n";
