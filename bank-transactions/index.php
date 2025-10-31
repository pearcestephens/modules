<?php
/**
 * Bank Transactions Module - Index / Router
 *
 * Routes requests to appropriate controllers
 */

// Bootstrap from base module
require_once __DIR__ . '/bootstrap.php';

// Load required controllers
require_once __DIR__ . '/controllers/BaseController.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/controllers/TransactionController.php';

// Get route from query string or default to dashboard
$route = $_GET['route'] ?? 'dashboard';

// Route to appropriate controller
switch ($route) {
    case 'dashboard':
    case '':
        $controller = new \CIS\BankTransactions\Controllers\DashboardController();
        $controller->index();
        break;

    case 'list':
        $controller = new \CIS\BankTransactions\Controllers\TransactionController();
        $controller->list();
        break;

    case 'detail':
        $controller = new \CIS\BankTransactions\Controllers\TransactionController();
        $controller->detail();
        break;

    case 'auto-match':
        $controller = new \CIS\BankTransactions\Controllers\TransactionController();
        $controller->autoMatch();
        break;

    case 'manual-match':
        $controller = new \CIS\BankTransactions\Controllers\TransactionController();
        $controller->manualMatch();
        break;

    default:
        http_response_code(404);
        echo "404 - Page not found";
        exit;
}
