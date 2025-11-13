<?php
/**
 * Transfer Manager - Dashboard
 *
 * Main dashboard page for managing transfers.
 * Provides an overview of transfer operations, quick actions, and analytics.
 *
 * Features:
 * - Summary of transfer operations.
 * - Quick actions: Create Transfer, View All Transfers.
 * - Analytics: Transfer trends, success rates.
 *
 * @package CIS\Consignments\TransferManager
 * @version 1.0.0
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once __DIR__ . '/../lib/Services/TransferManagerService.php';

use CIS\Consignments\Services\TransferManagerService;

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Initialize service
$db = get_db();
$transferManagerService = new TransferManagerService($db);

// Fetch recent transfer operations
$recentTransfers = $transferManagerService->getRecentTransfers(10);

// Render view
require_once __DIR__ . '/views/dashboard.php';
