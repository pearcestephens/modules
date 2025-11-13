<?php
/**
 * Consignments Module - Services Autoloader
 * Services now in /assets/services/consignments/
 * 
 * Namespace: CIS\Services\Consignments\{Core|AI|Integration|Support}
 */

// Register autoloader for consignments services
spl_autoload_register(function ($class) {
    // Only handle CIS\Services\Consignments\* classes
    if (strpos($class, 'CIS\\Services\\Consignments\\') !== 0) {
        return;
    }
    
    // Extract: CIS\Services\Consignments\Core\ConsignmentService
    // Result: Core/ConsignmentService
    $relativePath = str_replace('CIS\\Services\\Consignments\\', '', $class);
    $relativePath = str_replace('\\', '/', $relativePath);
    
    // Build path: /assets/services/consignments/core/ConsignmentService.php
    $servicesRoot = dirname(dirname(__DIR__)) . '/assets/services/consignments';
    $file = $servicesRoot . '/' . strtolower(dirname($relativePath)) . '/' . basename($relativePath) . '.php';
    
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    
    return false;
});

// Register company-wide services
$companyServices = dirname(dirname(__DIR__)) . '/assets/services';
if (!defined('CIS_SERVICES_PATH')) {
    define('CIS_SERVICES_PATH', $companyServices);
}

// Load critical company-wide services
require_once CIS_SERVICES_PATH . '/Config.php';
require_once CIS_SERVICES_PATH . '/Database.php';
require_once CIS_SERVICES_PATH . '/Auth.php';

