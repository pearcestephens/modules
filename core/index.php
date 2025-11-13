<?php
/**
 * CORE Module - Dashboard Entry Point
 *
 * Main dashboard/home page for authenticated users
 *
 * @package CIS\Core
 * @version 2.0.0
 */

declare(strict_types=1);

// Load CORE bootstrap
require_once __DIR__ . '/bootstrap.php';

use CIS\Core\Controllers\DashboardController;

// Require authentication
require_auth();

// Create controller instance
$controller = new DashboardController();

// Show dashboard
$controller->index();
