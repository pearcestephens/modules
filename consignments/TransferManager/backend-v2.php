<?php
/**
 * Transfer Manager Backend API - Modern Wrapper
 *
 * Uses TransferManagerAPI class (extends BaseAPI) for standardized responses.
 * Maintains backward compatibility with existing frontend.
 *
 * @package CIS\Consignments\TransferManager
 * @version 2.0.0
 * @created 2025-11-04
 */

declare(strict_types=1);

// Bootstrap the module (loads app.php, autoloader, dependencies)
require_once __DIR__ . '/../bootstrap.php';

// Namespace is set by autoloader
use CIS\Consignments\Lib\TransferManagerAPI;

// Create API instance and handle request
$api = new TransferManagerAPI();
$api->handleRequest();
