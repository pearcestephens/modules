<?php
/**
 * Flagged Products Module Bootstrap
 * 
 * Modern inventory accuracy tracking system with real-time updates
 * 
 * @package CIS\FlaggedProducts
 * @version 2.0.0
 * @author AI Assistant
 * @created 2025-10-26
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT']);
}

// ============================================================================
// 1. LOAD BASE APPLICATION
// ============================================================================

if (file_exists(ROOT_PATH . '/app.php')) {
    require_once ROOT_PATH . '/app.php';
}

if (file_exists(ROOT_PATH . '/assets/functions/config.php')) {
    require_once ROOT_PATH . '/assets/functions/config.php';
}

// ============================================================================
// 2. MODULE CONSTANTS
// ============================================================================

define('FLAGGED_PRODUCTS_MODULE_PATH', __DIR__);
define('FLAGGED_PRODUCTS_VERSION', '2.0.0');

// ============================================================================
// 3. LOAD MODULE LIBRARIES
// ============================================================================

// Core business logic
if (file_exists(__DIR__ . '/lib/FlaggedProductsService.php')) {
    require_once __DIR__ . '/lib/FlaggedProductsService.php';
}

// Database layer
if (file_exists(__DIR__ . '/lib/FlaggedProductsRepository.php')) {
    require_once __DIR__ . '/lib/FlaggedProductsRepository.php';
}

// Analytics
if (file_exists(__DIR__ . '/lib/AccuracyAnalytics.php')) {
    require_once __DIR__ . '/lib/AccuracyAnalytics.php';
}

// Smart-Cron integration
if (file_exists(__DIR__ . '/lib/FlaggedProductsCron.php')) {
    require_once __DIR__ . '/lib/FlaggedProductsCron.php';
}

// ============================================================================
// 4. HELPER FUNCTIONS
// ============================================================================

/**
 * Get flagged products service instance
 */
function getFlaggedProductsService(): FlaggedProductsService {
    static $service = null;
    if ($service === null) {
        global $con;
        $repo = new FlaggedProductsRepository($con);
        $service = new FlaggedProductsService($repo);
    }
    return $service;
}

/**
 * Get accuracy analytics instance
 */
function getAccuracyAnalytics(): AccuracyAnalytics {
    static $analytics = null;
    if ($analytics === null) {
        global $con;
        $repo = new FlaggedProductsRepository($con);
        $analytics = new AccuracyAnalytics($repo);
    }
    return $analytics;
}

/**
 * Return JSON response (for APIs)
 */
function jsonResponse(bool $success, $data = null, string $message = '', int $httpCode = 200): void {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    
    $response = [
        'success' => $success,
        'timestamp' => date('c'),
        'request_id' => uniqid('fp_', true)
    ];
    
    if ($message) {
        $response['message'] = $message;
    }
    
    if ($success && $data !== null) {
        $response['data'] = $data;
    } elseif (!$success) {
        $response['error'] = $data ?? $message;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Log module action
 */
function logFlaggedProductsAction(string $action, array $context = []): void {
    $logFile = ROOT_PATH . '/logs/flagged-products.log';
    $timestamp = date('Y-m-d H:i:s');
    $userId = $_SESSION['userID'] ?? 0;
    $contextStr = !empty($context) ? json_encode($context) : '';
    
    $logLine = "[{$timestamp}] User:{$userId} Action:{$action} {$contextStr}\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
}

?>
