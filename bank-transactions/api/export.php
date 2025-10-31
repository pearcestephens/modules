<?php
/**
 * Export Transactions to CSV API Endpoint
 *
 * Exports filtered transactions to CSV file
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../models/TransactionModel.php';
require_once __DIR__ . '/../lib/APIHelper.php';

use CIS\BankTransactions\API\APIHelper;

// Check authentication (bot bypass supported)
$auth = APIHelper::checkAuth();
if (!$auth['authenticated']) {
    // Redirect to login for normal users
    header('Location: /login.php');
    exit;
}

// Check permission (bot bypass supported)
if (!APIHelper::checkPermission('bank_transactions.view')) {
    http_response_code(403);
    die('Permission denied');
}

try {
    // Get filters
    $filters = [
        'status' => $_GET['status'] ?? '',
        'type' => $_GET['type'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'store_id' => $_GET['store_id'] ?? '',
        'search' => $_GET['search'] ?? ''
    ];

    $transactionModel = new \CIS\BankTransactions\Models\TransactionModel();

    // Get all transactions matching filters (no limit)
    $transactions = $transactionModel->findUnmatched(
        $filters['status'],
        $filters['type'],
        $filters['date_from'],
        $filters['date_to'],
        $filters['store_id'],
        $filters['search'],
        10000,  // Max 10k rows
        0
    );

    // Set headers for CSV download
    $filename = 'bank_transactions_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Write UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Write header row
    fputcsv($output, [
        'ID',
        'Date',
        'Time',
        'Reference',
        'Transaction Name',
        'Description',
        'Type',
        'Amount',
        'Status',
        'Confidence Score',
        'Order ID',
        'Store',
        'Bag Number',
        'EFTPOS Provider',
        'Created At'
    ]);

    // Write data rows
    foreach ($transactions as $txn) {
        fputcsv($output, [
            $txn['id'],
            $txn['transaction_date'],
            $txn['transaction_time'] ?? '',
            $txn['transaction_reference'],
            $txn['transaction_name'],
            $txn['transaction_description'] ?? '',
            $txn['transaction_type'],
            number_format($txn['transaction_amount'], 2, '.', ''),
            $txn['status'],
            $txn['confidence_score'] ?? '',
            $txn['order_id'] ?? '',
            $txn['store_id'] ?? '',
            $txn['bag_number'] ?? '',
            $txn['eftpos_provider'] ?? '',
            $txn['created_at']
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    error_log("Export Error: " . $e->getMessage());
    http_response_code(500);
    echo "Export failed. Please try again.";
}
