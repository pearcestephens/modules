<?php
/**
 * Bank Transactions Module - Bootstrap
 *
 * Initializes the bank transactions module by:
 * - Inheriting from base module (database, session, auth, logging, AI)
 * - Loading module-specific libraries
 * - Setting up module constants
 *
 * @package BankTransactions
 * @version 1.0.0
 * @date 2025-10-28
 */

// Load base module (provides everything we need)
require_once __DIR__ . '/../base/bootstrap.php';

// Detect and enable bot bypass
if (!empty($_GET['bot']) || !empty($_SERVER['HTTP_X_BOT_BYPASS'])) {
    define('BOT_BYPASS_AUTH', true);
} else {
    define('BOT_BYPASS_AUTH', false);
}

// Load database connections (mysql.php provides $con and $vapeShedCon globals)
if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/mysql.php')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/mysql.php';
}

// Define module constants
define('BANK_TRANSACTIONS_MODULE_PATH', __DIR__);
define('BANK_TRANSACTIONS_MODULE_URL', '/modules/bank-transactions');

// Module configuration
define('BANK_TRANSACTIONS_VERSION', '1.0.0');
define('BANK_TRANSACTIONS_CONFIDENCE_THRESHOLD', 200);  // Minimum confidence for auto-match
define('BANK_TRANSACTIONS_CONFIDENCE_MARGIN', 60);      // Margin below threshold for manual review

// Load module-specific libraries
require_once BANK_TRANSACTIONS_MODULE_PATH . '/lib/TransactionService.php';
require_once BANK_TRANSACTIONS_MODULE_PATH . '/lib/MatchingEngine.php';
require_once BANK_TRANSACTIONS_MODULE_PATH . '/lib/ConfidenceScorer.php';
require_once BANK_TRANSACTIONS_MODULE_PATH . '/lib/PaymentProcessor.php';

// Use base module services
use CIS\Base\Database;
use CIS\Base\Session;

// Module is now initialized - services from base are available
