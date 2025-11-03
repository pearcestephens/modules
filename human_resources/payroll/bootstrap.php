<?php
/**
 * Payroll Module - Bootstrap
 *
 * Initializes core environment settings for payroll processing
 * - Sets NZ timezone
 * - Configures error reporting
 * - Loads environment variables
 * - Includes autoloader
 *
 * @package Payroll
 * @version 1.0.0
 * @created 2025-11-02
 */

declare(strict_types=1);

// Set New Zealand timezone for all date/time operations
date_default_timezone_set('Pacific/Auckland');

// Enable all error reporting but don't display (log only)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Load environment variables
$envLoaderPath = $_SERVER['DOCUMENT_ROOT'] . '/config/env-loader.php';
if (!file_exists($envLoaderPath)) {
    // Fallback to relative path
    $envLoaderPath = dirname(__DIR__, 3) . '/config/env-loader.php';
}

if (file_exists($envLoaderPath)) {
    require_once $envLoaderPath;
} else {
    error_log('WARNING: env-loader.php not found. Environment variables may not be loaded.');
}

// Include autoloader
require_once __DIR__ . '/autoload.php';
