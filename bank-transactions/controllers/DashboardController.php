<?php
/**
 * DashboardController - Main dashboard for bank transactions
 *
 * @package CIS\BankTransactions\Controllers
 */

declare(strict_types=1);

namespace CIS\BankTransactions\Controllers;

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/TransactionModel.php';

use CIS\BankTransactions\Models\TransactionModel;

class DashboardController extends BaseController
{
    private $transactionModel;

    public function __construct()
    {
        parent::__construct();
        $this->requirePermission('bank_transactions.view');

        $this->transactionModel = new TransactionModel($this->db);
    }

    /**
     * Display dashboard
     */
    public function index(): void
    {
        $date = $_GET['date'] ?? date('Y-m-d');

        // Get dashboard metrics - handle query failures gracefully
        try {
            $metrics = $this->transactionModel->getDashboardMetrics($date) ?? [];
            $typeBreakdown = $this->transactionModel->getTypeBreakdown($date) ?? [];
            $recentMatches = $this->transactionModel->getRecentMatches(10) ?? [];

            $dateFrom = date('Y-m-d', strtotime('-7 days'));
            $dateTo = date('Y-m-d');
            $autoMatchRate = $this->transactionModel->getAutoMatchRate($dateFrom, $dateTo) ?? 0;
            $avgReconciliationTime = $this->transactionModel->getAvgReconciliationTime($date) ?? 0;
        } catch (\Exception $e) {
            // Database errors - use empty data
            $metrics = [];
            $typeBreakdown = [];
            $recentMatches = [];
            $autoMatchRate = 0;
            $avgReconciliationTime = 0;
        }

        // Prepare data for view
        $data = [
            'date' => $date,
            'metrics' => $metrics ?: ['total' => 0, 'unmatched' => 0, 'unmatched_amount' => 0, 'matched' => 0, 'auto_matched' => 0, 'manual_matched' => 0],
            'typeBreakdown' => $typeBreakdown,
            'recentMatches' => $recentMatches,
            'autoMatchRate' => $autoMatchRate,
            'autoMatchTarget' => 90,
            'avgReconciliationTime' => $avgReconciliationTime,
            'reconciliationTarget' => 60,
            'pageTitle' => 'Bank Transactions Dashboard'
        ];

        $this->render('views/dashboard.php', $data);
    }

    /**
     * Get dashboard metrics (AJAX)
     */
    public function getMetrics(): void
    {
        $date = $_GET['date'] ?? date('Y-m-d');

        $metrics = $this->transactionModel->getDashboardMetrics($date);
        $typeBreakdown = $this->transactionModel->getTypeBreakdown($date);

        $this->success([
            'metrics' => $metrics,
            'type_breakdown' => $typeBreakdown,
            'date' => $date
        ]);
    }
}
