<?php
/**
 * Migration: Create bank_transactions_current table
 *
 * Creates a comprehensive new table for bank transaction management
 * and migrates data from bank_transactions_archive
 *
 * @package CIS\BankTransactions\Migrations
 */

// Database connection
$host = '127.0.0.1';
$dbname = 'jcepnzzkmj';
$username = 'jcepnzzkmj';
$password = 'wprKh9Jq63';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to database successfully.\n\n";

    // Step 1: Create new bank_transactions_current table
    echo "Creating bank_transactions_current table...\n";

    $createTable = "
    CREATE TABLE IF NOT EXISTS `bank_transactions_current` (
      `id` int(11) NOT NULL AUTO_INCREMENT,

      -- Transaction Details
      `transaction_id` varchar(100) DEFAULT NULL COMMENT 'External transaction ID from bank',
      `transaction_reference` varchar(200) NOT NULL COMMENT 'Bank reference number',
      `transaction_name` varchar(255) NOT NULL COMMENT 'Customer/depositor name',
      `transaction_description` text DEFAULT NULL COMMENT 'Full transaction description from bank',
      `transaction_amount` decimal(13,2) NOT NULL COMMENT 'Transaction amount',
      `transaction_date` date NOT NULL COMMENT 'Date of transaction',
      `transaction_time` time DEFAULT NULL COMMENT 'Time of transaction if available',

      -- Classification
      `transaction_type` enum('store_deposit','retail_customer','wholesale_customer','eftpos_settlement','other') NOT NULL DEFAULT 'other' COMMENT 'Type of transaction',
      `store_id` int(11) DEFAULT NULL COMMENT 'Store/outlet ID if store deposit',
      `customer_type` enum('retail','wholesale','unknown') DEFAULT NULL COMMENT 'Customer type if applicable',

      -- Matching Status
      `status` enum('unmatched','matched','review','voided','duplicate') NOT NULL DEFAULT 'unmatched' COMMENT 'Current matching status',
      `confidence_score` int(11) DEFAULT NULL COMMENT 'AI confidence score (0-300)',
      `matched_at` datetime DEFAULT NULL COMMENT 'When transaction was matched',
      `matched_by` enum('AUTO','MANUAL','SYSTEM') DEFAULT NULL COMMENT 'How it was matched',
      `matched_by_user_id` int(11) DEFAULT NULL COMMENT 'User who matched (if manual)',

      -- Order Linking
      `order_id` int(11) DEFAULT NULL COMMENT 'Linked order ID (VapeShed DB)',
      `payment_id` int(11) DEFAULT NULL COMMENT 'Created payment ID',
      `invoice_number` varchar(50) DEFAULT NULL COMMENT 'Generated invoice number',

      -- Store Deposit Specific
      `bag_number` int(11) DEFAULT NULL COMMENT 'Cash bag number',
      `bag_reference` varchar(45) DEFAULT NULL COMMENT 'Bag reference code',

      -- EFTPOS Specific
      `is_eftpos_settlement` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Is this an EFTPOS settlement',
      `eftpos_provider` varchar(50) DEFAULT NULL COMMENT 'EFTPOS provider (ALL ACCEPT, etc)',
      `eftpos_terminal_id` varchar(50) DEFAULT NULL COMMENT 'Terminal ID',

      -- Additional Data
      `json_metadata` json DEFAULT NULL COMMENT 'Additional metadata in JSON format',
      `notes` text DEFAULT NULL COMMENT 'User notes',

      -- Audit Trail
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      `voided_at` datetime DEFAULT NULL COMMENT 'When voided',
      `voided_by_user_id` int(11) DEFAULT NULL COMMENT 'User who voided',
      `void_reason` varchar(255) DEFAULT NULL COMMENT 'Reason for void',

      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_transaction_id` (`transaction_id`),
      KEY `idx_transaction_reference` (`transaction_reference`),
      KEY `idx_transaction_date` (`transaction_date`),
      KEY `idx_transaction_amount` (`transaction_amount`),
      KEY `idx_transaction_name` (`transaction_name`(100)),
      KEY `idx_transaction_type` (`transaction_type`),
      KEY `idx_status` (`status`),
      KEY `idx_store_id` (`store_id`),
      KEY `idx_order_id` (`order_id`),
      KEY `idx_payment_id` (`payment_id`),
      KEY `idx_bag_number` (`bag_number`),
      KEY `idx_bag_reference` (`bag_reference`),
      KEY `idx_eftpos` (`is_eftpos_settlement`),
      KEY `idx_matched_at` (`matched_at`),
      KEY `idx_confidence` (`confidence_score`),
      KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Bank transaction deposits with AI matching capabilities';
    ";

    $pdo->exec($createTable);
    echo "✓ bank_transactions_current table created successfully.\n\n";

    // Step 2: Migrate data from bank_transactions_archive
    echo "Migrating data from bank_transactions_archive...\n";

    $migrateData = "
    INSERT INTO `bank_transactions_current` (
        `transaction_id`,
        `transaction_reference`,
        `transaction_name`,
        `transaction_description`,
        `transaction_amount`,
        `transaction_date`,
        `transaction_type`,
        `store_id`,
        `status`,
        `order_id`,
        `bag_number`,
        `bag_reference`,
        `is_eftpos_settlement`,
        `json_metadata`,
        `created_at`
    )
    SELECT
        `transaction_id`,
        `transaction_reference`,
        `transaction_name`,
        `original_transaction_description`,
        `transaction_amount`,
        DATE(`transaction_date`),
        CASE
            WHEN `store_deposit` > 0 THEN 'store_deposit'
            WHEN `eftpos_settlement` = 1 THEN 'eftpos_settlement'
            ELSE 'other'
        END as transaction_type,
        NULLIF(`store_deposit`, 0) as store_id,
        CASE
            WHEN `order_id` IS NOT NULL THEN 'matched'
            ELSE 'unmatched'
        END as status,
        `order_id`,
        `bag_number`,
        `reference` as bag_reference,
        `eftpos_settlement`,
        JSON_OBJECT(
            'legacy_id', `id`,
            'card_id', `card_id`,
            'fetched_date', `transaction_fetched_date`,
            'raw_json', `json_object`
        ) as json_metadata,
        `transaction_fetched_date` as created_at
    FROM `bank_transactions_archive`
    WHERE `transaction_id` IS NOT NULL
    ON DUPLICATE KEY UPDATE
        `transaction_reference` = VALUES(`transaction_reference`),
        `transaction_amount` = VALUES(`transaction_amount`);
    ";

    $rowsAffected = $pdo->exec($migrateData);
    echo "✓ Migrated $rowsAffected records from bank_transactions_archive.\n\n";

    // Step 3: Update EFTPOS provider information
    echo "Updating EFTPOS provider information...\n";

    $updateEftpos = "
    UPDATE `bank_transactions_current`
    SET
        `eftpos_provider` = CASE
            WHEN `transaction_name` LIKE '%ALL ACCEPT%' THEN 'ALL ACCEPT'
            WHEN `transaction_name` LIKE '%SMARTPAY%' THEN 'SMARTPAY'
            WHEN `transaction_name` LIKE '%WINDCAVE%' THEN 'WINDCAVE'
            ELSE NULL
        END
    WHERE `is_eftpos_settlement` = 1;
    ";

    $pdo->exec($updateEftpos);
    echo "✓ EFTPOS provider information updated.\n\n";

    // Step 4: Classify customer types
    echo "Classifying customer types...\n";

    $classifyCustomers = "
    UPDATE `bank_transactions_current`
    SET `customer_type` = CASE
        WHEN `transaction_type` = 'store_deposit' THEN NULL
        WHEN `transaction_type` = 'eftpos_settlement' THEN NULL
        WHEN `transaction_amount` >= 1000 THEN 'wholesale'
        ELSE 'retail'
    END
    WHERE `transaction_type` NOT IN ('store_deposit', 'eftpos_settlement');
    ";

    $pdo->exec($classifyCustomers);
    echo "✓ Customer types classified.\n\n";

    // Step 5: Show statistics
    echo "Migration Statistics:\n";
    echo str_repeat('-', 60) . "\n";

    $stats = $pdo->query("
        SELECT
            transaction_type,
            status,
            COUNT(*) as count,
            SUM(transaction_amount) as total_amount
        FROM bank_transactions_current
        GROUP BY transaction_type, status
        ORDER BY transaction_type, status
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($stats as $stat) {
        printf(
            "%-25s %-15s: %5d records ($%s)\n",
            $stat['transaction_type'],
            $stat['status'],
            $stat['count'],
            number_format($stat['total_amount'], 2)
        );
    }

    echo str_repeat('-', 60) . "\n\n";

    // Step 6: Create bank_audit_trail table
    echo "Creating bank_audit_trail table...\n";

    $createAuditTable = "
    CREATE TABLE IF NOT EXISTS `bank_audit_trail` (
      `id` bigint(20) NOT NULL AUTO_INCREMENT,
      `entity_type` varchar(50) NOT NULL COMMENT 'Type of entity (transaction, payment, order)',
      `entity_id` int(11) NOT NULL COMMENT 'ID of the entity',
      `action` varchar(50) NOT NULL COMMENT 'Action performed',
      `user_id` int(11) DEFAULT NULL COMMENT 'User who performed action (NULL = SYSTEM)',
      `user_name` varchar(100) DEFAULT NULL COMMENT 'User name for quick reference',
      `details` json DEFAULT NULL COMMENT 'Additional details in JSON format',
      `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of user',
      `user_agent` varchar(255) DEFAULT NULL COMMENT 'Browser user agent',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),

      PRIMARY KEY (`id`),
      KEY `idx_entity` (`entity_type`, `entity_id`),
      KEY `idx_action` (`action`),
      KEY `idx_user_id` (`user_id`),
      KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Audit trail for all bank transaction operations';
    ";

    $pdo->exec($createAuditTable);
    echo "✓ bank_audit_trail table created successfully.\n\n";

    echo "✅ Migration completed successfully!\n\n";
    echo "Next steps:\n";
    echo "1. Update TransactionModel to use 'bank_transactions_current' table\n";
    echo "2. Update field names in models to match new schema\n";
    echo "3. Test all queries with new table structure\n\n";

} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
