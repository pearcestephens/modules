<?php
/**
 * Migration: Create register_closure_bank_transactions_current table
 *
 * This table tracks cash deposits taken to the bank from store register closures.
 * It's different from bank_transactions_current which tracks individual bank transactions.
 *
 * @package CIS\BankTransactions\Migrations
 */

// Database connection
$host = '127.0.0.1';
$dbname = 'jcepnzzkmj';
$username = 'jcepnzzkmj';
$password = 'wprKh9Jq63';

$pdo = null;
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to database successfully.\n\n";

    // Create register_closure_bank_transactions_current table
    echo "Creating register_closure_bank_transactions_current table...\n";

    $createTable = "
    CREATE TABLE IF NOT EXISTS `register_closure_bank_transactions_current` (
      `id` int(11) NOT NULL AUTO_INCREMENT,

      -- Store/Outlet Info
      `outlet_id` varchar(36) NOT NULL COMMENT 'Vend outlet ID',

      -- Deposit Details
      `reference` varchar(50) NOT NULL COMMENT 'Bank deposit reference number',
      `bag_number` varchar(50) DEFAULT NULL COMMENT 'Bank deposit bag number',
      `expected_cash_total` decimal(13,2) NOT NULL DEFAULT '0.00' COMMENT 'Expected cash amount',
      `actual_cash_total` decimal(13,2) NOT NULL DEFAULT '0.00' COMMENT 'Actual cash amount deposited',

      -- Person Taking Deposit
      `first_name` varchar(100) DEFAULT NULL COMMENT 'First name of person taking deposit',
      `last_name` varchar(100) DEFAULT NULL COMMENT 'Last name of person taking deposit',

      -- Metadata
      `notes` text DEFAULT NULL COMMENT 'Additional notes about the deposit',
      `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When deposit was created',
      `user_created_by` int(11) DEFAULT NULL COMMENT 'User ID who created the deposit record',

      PRIMARY KEY (`id`),
      KEY `idx_outlet_id` (`outlet_id`),
      KEY `idx_reference` (`reference`),
      KEY `idx_bag_number` (`bag_number`),
      KEY `idx_created` (`created`),
      KEY `idx_user_created_by` (`user_created_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Cash deposits from register closures taken to the bank';
    ";

    $pdo->exec($createTable);
    echo "✓ Table register_closure_bank_transactions_current created successfully.\n\n";

    echo "Migration completed successfully!\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    // ✅ CRITICAL FIX: Always cleanup PDO connection to prevent connection leaks
    $pdo = null;
}
