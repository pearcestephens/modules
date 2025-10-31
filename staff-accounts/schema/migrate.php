<?php
/**
 * Database Migration Script
 *
 * Creates the sales_payments table if it doesn't exist
 *
 * @package CIS\Modules\StaffAccounts
 */

// Load module bootstrap
require_once __DIR__ . '/../bootstrap.php';

// Get database connection
$pdo = cis_resolve_pdo();

echo "====================================\n";
echo "Sales Payments Table Migration\n";
echo "====================================\n\n";

try {
    // Read SQL file
    $sqlFile = __DIR__ . '/sales-payments.sql';

    if (!file_exists($sqlFile)) {
        die("ERROR: SQL file not found: $sqlFile\n");
    }

    $sql = file_get_contents($sqlFile);

    if (empty($sql)) {
        die("ERROR: SQL file is empty\n");
    }

    echo "Executing SQL from: $sqlFile\n";
    echo "-----------------------------------\n";

    // Execute the SQL
    $stmt = $pdo->exec($sql);

    echo "✓ SQL executed successfully\n\n";

    // Verify table was created
    $checkSql = "SHOW TABLES LIKE 'sales_payments'";
    $result = $pdo->query($checkSql);

    if ($result && $result->rowCount() > 0) {
        echo "✓ Table 'sales_payments' exists\n\n";

        // Get table structure
        $descSql = "DESCRIBE sales_payments";
        $desc = $pdo->query($descSql);

        if ($desc) {
            echo "Table Structure:\n";
            echo "-----------------------------------\n";
            $columns = $desc->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $column) {
                echo sprintf("%-30s %-20s %s\n",
                    $column['Field'],
                    $column['Type'],
                    $column['Null'] === 'YES' ? 'NULL' : 'NOT NULL'
                );
            }
            echo "\n";
        }

        // Get row count
        $countSql = "SELECT COUNT(*) as count FROM sales_payments";
        $countResult = $pdo->query($countSql);
        if ($countResult) {
            $count = $countResult->fetch(PDO::FETCH_ASSOC);
            echo "Current row count: " . $count['count'] . "\n\n";
        }

        echo "====================================\n";
        echo "✓ Migration completed successfully!\n";
        echo "====================================\n\n";

        echo "Next steps:\n";
        echo "1. Run the sync script to populate data:\n";
        echo "   php " . dirname(__DIR__) . "/lib/sync-payments.php\n\n";

    } else {
        echo "✗ ERROR: Table was not created\n";
        exit(1);
    }

} catch (PDOException $e) {
    echo "✗ DATABASE ERROR: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n\n";
    exit(1);
}
