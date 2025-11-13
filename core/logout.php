<?php
/**
 * CORE Module - Logout Entry Point
 *
 * Handles user logout
 *
 * @package CIS\Core
 * @version 2.0.0
 */

declare(strict_types=1);

// Load CORE bootstrap
require_once __DIR__ . '/bootstrap.php';

use CIS\Core\Controllers\AuthController;

// Create controller instance
$controller = new AuthController();

// Process logout
$controller->processLogout();
