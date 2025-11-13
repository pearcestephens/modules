#!/usr/bin/env php
<?php
/**
 * Database Structure Validation Test
 *
 * Validates all required tables exist and have correct structure
 * before running API tests.
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

class DatabaseValidationTest
{
    private $db;
    private $results = [];

    public function __construct()
    {
        $this->db = \CIS\Base\Database::pdo();
    }

    public function runAll(): void
    {
        echo "🔍 DATABASE STRUCTURE VALIDATION\n";
        echo str_repeat("=", 80) . "\n\n";

        // Test primary tables
        $this->testTable('vend_consignments', [
            'id', 'vend_transfer_id', 'outlet_from', 'outlet_to',
            'state', 'transfer_category', 'created_by', 'created_at',
            'total_boxes', 'total_cost', 'total_count', 'deleted_at'
        ]);

        $this->testTable('vend_consignment_line_items', [
            'id', 'transfer_id', 'product_id', 'sku', 'name',
            'quantity', 'quantity_sent', 'quantity_received',
            'status', 'unit_cost', 'total_cost'
        ]);

        $this->testTable('vend_consignment_queue', [
            'id', 'transfer_id', 'action', 'status', 'created_at'
        ]);

        // Test supporting tables
        $this->testTable('consignment_shipments');
        $this->testTable('consignment_parcels');
        $this->testTable('consignment_notes');
        $this->testTable('consignment_audit_log');

        // Test Vend integration tables
        $this->testTable('vend_outlets', ['outletID', 'outletName']);
        $this->testTable('vend_suppliers', ['supplierID', 'supplierName']);
        $this->testTable('vend_products', ['id', 'sku', 'name']);

        // Test data counts
        $this->testDataCounts();

        // Generate report
        $this->generateReport();
    }

    private function testTable(string $tableName, array $requiredColumns = []): void
    {
        echo "Testing table: {$tableName}... ";

        try {
            // Check if table exists
            $stmt = $this->db->query("SHOW TABLES LIKE '{$tableName}'");
            $exists = $stmt->rowCount() > 0;

            if (!$exists) {
                echo "❌ TABLE NOT FOUND\n";
                $this->results[] = [
                    'table' => $tableName,
                    'exists' => false,
                    'passed' => false
                ];
                return;
            }

            // Check columns if specified
            if (!empty($requiredColumns)) {
                $stmt = $this->db->query("DESCRIBE {$tableName}");
                $actualColumns = $stmt->fetchAll(\PDO::FETCH_COLUMN);

                $missing = array_diff($requiredColumns, $actualColumns);
                if (!empty($missing)) {
                    echo "❌ MISSING COLUMNS: " . implode(', ', $missing) . "\n";
                    $this->results[] = [
                        'table' => $tableName,
                        'exists' => true,
                        'missing_columns' => $missing,
                        'passed' => false
                    ];
                    return;
                }
            }

            // Get row count
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM {$tableName}");
            $count = $stmt->fetch(\PDO::FETCH_ASSOC)['count'];

            echo "✅ ({$count} rows)\n";
            $this->results[] = [
                'table' => $tableName,
                'exists' => true,
                'row_count' => $count,
                'passed' => true
            ];

        } catch (\Exception $e) {
            echo "❌ ERROR: " . $e->getMessage() . "\n";
            $this->results[] = [
                'table' => $tableName,
                'error' => $e->getMessage(),
                'passed' => false
            ];
        }
    }

    private function testDataCounts(): void
    {
        echo "\n📊 DATA COUNTS\n";
        echo str_repeat("-", 80) . "\n";

        $queries = [
            'Total Consignments' => "SELECT COUNT(*) FROM vend_consignments WHERE deleted_at IS NULL",
            'Stock Transfers' => "SELECT COUNT(*) FROM vend_consignments WHERE transfer_category = 'STOCK_TRANSFER' AND deleted_at IS NULL",
            'Purchase Orders' => "SELECT COUNT(*) FROM vend_consignments WHERE transfer_category = 'PURCHASE_ORDER' AND deleted_at IS NULL",
            'Draft Transfers' => "SELECT COUNT(*) FROM vend_consignments WHERE state = 'DRAFT' AND deleted_at IS NULL",
            'Open Transfers' => "SELECT COUNT(*) FROM vend_consignments WHERE state = 'OPEN' AND deleted_at IS NULL",
            'Sent Transfers' => "SELECT COUNT(*) FROM vend_consignments WHERE state = 'SENT' AND deleted_at IS NULL",
            'Received Transfers' => "SELECT COUNT(*) FROM vend_consignments WHERE state = 'RECEIVED' AND deleted_at IS NULL",
            'Line Items' => "SELECT COUNT(*) FROM vend_consignment_line_items",
            'Queue Jobs' => "SELECT COUNT(*) FROM vend_consignment_queue",
            'Outlets' => "SELECT COUNT(*) FROM vend_outlets",
            'Suppliers' => "SELECT COUNT(*) FROM vend_suppliers",
            'Products' => "SELECT COUNT(*) FROM vend_products"
        ];

        foreach ($queries as $label => $query) {
            try {
                $stmt = $this->db->query($query);
                $count = $stmt->fetch(\PDO::FETCH_ASSOC)['COUNT(*)'];
                echo sprintf("  %-25s %10d\n", $label . ':', $count);
            } catch (\Exception $e) {
                echo sprintf("  %-25s ERROR: %s\n", $label . ':', $e->getMessage());
            }
        }
    }

    private function generateReport(): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "📋 VALIDATION SUMMARY\n";
        echo str_repeat("=", 80) . "\n\n";

        $total = count($this->results);
        $passed = count(array_filter($this->results, fn($r) => $r['passed']));
        $failed = $total - $passed;

        echo "Tables Tested: {$total}\n";
        echo "✅ Passed: {$passed}\n";
        echo "❌ Failed: {$failed}\n\n";

        if ($failed > 0) {
            echo "❌ FAILED TABLES:\n";
            foreach ($this->results as $result) {
                if (!$result['passed']) {
                    echo "  - {$result['table']}";
                    if (isset($result['error'])) {
                        echo " (Error: {$result['error']})";
                    }
                    if (isset($result['missing_columns'])) {
                        echo " (Missing: " . implode(', ', $result['missing_columns']) . ")";
                    }
                    echo "\n";
                }
            }
            echo "\n";
        }

        if ($passed === $total) {
            echo "🎉 ALL TABLES VALID - READY FOR API TESTING\n";
            exit(0);
        } else {
            echo "🚨 DATABASE STRUCTURE ISSUES - FIX BEFORE RUNNING API TESTS\n";
            exit(1);
        }
    }
}

// Run if called directly
if (php_sapi_name() === 'cli') {
    $test = new DatabaseValidationTest();
    $test->runAll();
}
