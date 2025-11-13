<?php
/**
 * Stock Transfers - Dashboard
 *
 * Main dashboard page for stock transfers.
 * Provides an overview of recent transfers, quick actions, and analytics.
 *
 * Features:
 * - Summary of recent transfers.
 * - Quick actions: Create Transfer, View All Transfers.
 * - Analytics: Transfer trends, success rates.
 *
 * @package CIS\Consignments\StockTransfers
 * @version 1.0.0
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once __DIR__ . '/../lib/Services/StockTransferService.php';

use CIS\Consignments\Services\StockTransferService;

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Initialize service
$db = get_db();
$transferService = new StockTransferService($db);

// Fetch recent transfers
$recentTransfers = $transferService->getRecentTransfers(10);

// Render view
require_once __DIR__ . '/views/dashboard.php';
