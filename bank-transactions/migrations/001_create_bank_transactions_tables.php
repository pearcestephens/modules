<?php
/**
 * Database Migration - Bank Transactions Module
 *
 * Creates required tables for the bank transactions module
 *
 * Run: php migrations/001_create_bank_transactions_tables.php
 */

// Direct database connection
$config = require __DIR__ . '/../../../config/database.php';

try {
    $db = new PDO(
        "mysql:host={$config['cis']['host']};dbname={$config['cis']['database']};charset=utf8mb4",
        $config['cis']['username'],
        $config['cis']['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}echo "Starting Bank Transactions Module Migration...\n\n";

// 1. Create bank_audit_trail table
echo "Creating bank_audit_trail table...\n";

$sql = "CREATE TABLE IF NOT EXISTS bank_audit_trail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL COMMENT 'transaction, order, payment',
    entity_id INT NOT NULL,
    action VARCHAR(100) NOT NULL COMMENT 'matched, unmatched, reassigned, etc',
    user_id INT NULL COMMENT 'NULL for system actions',
    user_name VARCHAR(255) NOT NULL,
    details TEXT NULL COMMENT 'JSON encoded details',
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,

    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_action (action),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail for all bank transaction actions'";

try {
    $db->exec($sql);
    echo "✓ bank_audit_trail table created successfully\n\n";
} catch (PDOException $e) {
    echo "✗ Error creating bank_audit_trail table: " . $e->getMessage() . "\n\n";
}

// 2. Check if bank_transactions_legacy_new table exists, if not create it
echo "Checking bank_transactions_legacy_new table...\n";

$sql = "CREATE TABLE IF NOT EXISTS bank_transactions_legacy_new (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    reference VARCHAR(255) NULL,
    customer_name VARCHAR(255) NULL,
    amount DECIMAL(10,2) NOT NULL,
    transaction_type ENUM('store_deposit', 'retail', 'wholesale', 'eftpos') NOT NULL,
    store_id INT NULL,
    status ENUM('unmatched', 'matched', 'review', 'void') DEFAULT 'unmatched',
    order_id INT NULL,
    matched_by ENUM('AUTO', 'MANUAL', 'SYSTEM') NULL,
    matched_at DATETIME NULL,
    confidence_score INT NULL COMMENT 'AI confidence score 0-300',
    notes TEXT NULL,
    metadata TEXT NULL COMMENT 'JSON encoded metadata',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_date (date),
    INDEX idx_status (status),
    INDEX idx_type (transaction_type),
    INDEX idx_store (store_id),
    INDEX idx_order (order_id),
    INDEX idx_matched_at (matched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bank deposit transactions'";

try {
    $db->exec($sql);
    echo "✓ bank_transactions_legacy_new table checked/created successfully\n\n";
} catch (PDOException $e) {
    echo "✗ Error with bank_transactions_legacy_new table: " . $e->getMessage() . "\n\n";
}

// 3. Add metadata column to orders_invoices if not exists (VapeShed DB)
echo "Updating orders_invoices table (VapeShed DB)...\n";

try {
    // Get VapeShed connection
    $config = require __DIR__ . '/../../../config/database.php';
    $vapeShedDb = new PDO(
        "mysql:host={$config['vapeshed']['host']};dbname={$config['vapeshed']['database']};charset=utf8mb4",
        $config['vapeshed']['username'],
        $config['vapeshed']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Check if metadata column exists
    $stmt = $vapeShedDb->query("SHOW COLUMNS FROM orders_invoices LIKE 'metadata'");

    if ($stmt->rowCount() === 0) {
        $vapeShedDb->exec("ALTER TABLE orders_invoices
                          ADD COLUMN metadata TEXT NULL COMMENT 'JSON encoded payment metadata'
                          AFTER payment_method");
        echo "✓ metadata column added to orders_invoices\n\n";
    } else {
        echo "✓ metadata column already exists in orders_invoices\n\n";
    }
} catch (PDOException $e) {
    echo "✗ Error updating orders_invoices: " . $e->getMessage() . "\n\n";
}

// 4. Create indexes for performance
echo "Creating performance indexes...\n";

$indexes = [
    "CREATE INDEX IF NOT EXISTS idx_customer_name ON bank_transactions_legacy_new (customer_name)",
    "CREATE INDEX IF NOT EXISTS idx_amount ON bank_transactions_legacy_new (amount)",
    "CREATE INDEX IF NOT EXISTS idx_confidence ON bank_transactions_legacy_new (confidence_score)"
];

foreach ($indexes as $index) {
    try {
        $db->exec($index);
        echo "✓ Index created\n";
    } catch (PDOException $e) {
        echo "✗ Index error: " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Migration completed successfully!\n";
echo "\nYou can now use the Bank Transactions module.\n";
