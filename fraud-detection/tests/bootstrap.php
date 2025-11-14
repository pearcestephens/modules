<?php

/**
 * PHPUnit Configuration Bootstrap
 *
 * Loads testing environment and sets up database mocks
 *
 * @package FraudDetection\Tests
 */

namespace FraudDetection\Tests;

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables for testing
if (file_exists(__DIR__ . '/../.env.testing')) {
    $lines = file(__DIR__ . '/../.env.testing', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && $line[0] !== '#') {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// Set testing flag
define('FRAUD_DETECTION_TESTING', true);

// Error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');
