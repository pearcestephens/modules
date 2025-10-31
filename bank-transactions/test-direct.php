<?php
/**
 * Direct test endpoint - no auth required
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Bank Transactions Module Test ===\n\n";

echo "STEP 1: Load base bootstrap\n";
try {
    require_once __DIR__ . '/../base/bootstrap.php';
    echo "✅ Base bootstrap loaded\n\n";
} catch (Exception $e) {
    echo "❌ Error loading base bootstrap: " . $e->getMessage() . "\n";
    exit(1);
}

echo "STEP 2: Load module bootstrap\n";
try {
    require_once __DIR__ . '/bootstrap.php';
    echo "✅ Module bootstrap loaded\n\n";
} catch (Exception $e) {
    echo "❌ Error loading module bootstrap: " . $e->getMessage() . "\n";
    exit(1);
}

echo "STEP 3: Check module constants\n";
echo "- BANK_TRANSACTIONS_MODULE_PATH: " . BANK_TRANSACTIONS_MODULE_PATH . "\n";
echo "- BANK_TRANSACTIONS_VERSION: " . BANK_TRANSACTIONS_VERSION . "\n";
echo "- BANK_TRANSACTIONS_CONFIDENCE_THRESHOLD: " . BANK_TRANSACTIONS_CONFIDENCE_THRESHOLD . "\n\n";

echo "STEP 4: Load controllers\n";
try {
    require_once __DIR__ . '/controllers/BaseController.php';
    require_once __DIR__ . '/controllers/DashboardController.php';
    echo "✅ Controllers loaded\n\n";
} catch (Exception $e) {
    echo "❌ Error loading controllers: " . $e->getMessage() . "\n";
    exit(1);
}

echo "STEP 5: Instantiate DashboardController\n";
try {
    // Set minimum required $_SERVER values
    if (!isset($_SERVER['REQUEST_URI'])) {
        $_SERVER['REQUEST_URI'] = '/modules/bank-transactions/?route=dashboard&bot=true';
    }

    $controller = new \CIS\BankTransactions\Controllers\DashboardController();
    echo "✅ DashboardController instantiated\n\n";
} catch (Exception $e) {
    echo "❌ Error instantiating DashboardController: " . $e->getMessage() . "\n";
    exit(1);
}

echo "STEP 6: Call index() method\n";
try {
    $controller->index();
    echo "\n✅ index() method executed successfully\n";
} catch (Exception $e) {
    echo "❌ Error calling index(): " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== ALL TESTS PASSED ===\n";
?>
