#!/usr/bin/env php
<?php
/**
 * Migrate Employee Mappings - Populate employee_mappings table
 * 
 * This script populates the employee_mappings table with:
 * 1. Existing mapped employees from cis_staff_vend_map
 * 2. Unmapped employees from xero_payroll_deductions
 * 3. User data from users table
 * 
 * Run: php migrate-employee-mappings.php
 */

declare(strict_types=1);

echo "=================================================\n";
echo "Employee Mappings Migration Script\n";
echo "=================================================\n\n";

// Load bootstrap
require_once __DIR__ . '/bootstrap.php';

if (!isset($pdo)) {
    die("ERROR: Database connection not available\n");
}

try {
    $pdo->beginTransaction();
    
    echo "Step 1: Clearing existing employee_mappings data...\n";
    $pdo->exec("TRUNCATE TABLE employee_mappings");
    echo "✓ Cleared\n\n";
    
    // Step 2: Get all Xero employees from payroll deductions
    echo "Step 2: Loading Xero payroll deductions...\n";
    $stmt = $pdo->query("
        SELECT DISTINCT
            xero_employee_id,
            employee_name,
            SUM(amount) as total_deductions,
            COUNT(*) as deduction_count
        FROM xero_payroll_deductions
        GROUP BY xero_employee_id, employee_name
        ORDER BY employee_name
    ");
    $xeroEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✓ Found " . count($xeroEmployees) . " Xero employees\n\n";
    
    // Step 3: Get all users with Xero IDs
    echo "Step 3: Loading users with Xero IDs...\n";
    $stmt = $pdo->query("
        SELECT 
            id as user_id,
            xero_id,
            vend_customer_account,
            first_name,
            last_name,
            email
        FROM users
        WHERE xero_id IS NOT NULL AND xero_id != ''
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $usersByXeroId = [];
    foreach ($users as $user) {
        $usersByXeroId[$user['xero_id']] = $user;
    }
    echo "✓ Found " . count($users) . " users with Xero IDs\n\n";
    
    // Step 4: Get existing mappings from cis_staff_vend_map
    echo "Step 4: Loading existing mappings from cis_staff_vend_map...\n";
    $stmt = $pdo->query("
        SELECT 
            xero_employee_id,
            vend_customer_id
        FROM cis_staff_vend_map
    ");
    $existingMappings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $mappingsByXeroId = [];
    foreach ($existingMappings as $mapping) {
        $mappingsByXeroId[$mapping['xero_employee_id']] = $mapping['vend_customer_id'];
    }
    echo "✓ Found " . count($existingMappings) . " existing mappings\n\n";
    
    // Step 5: Insert into employee_mappings
    echo "Step 5: Migrating to employee_mappings table...\n";
    
    $insertStmt = $pdo->prepare("
        INSERT INTO employee_mappings (
            xero_employee_id,
            employee_name,
            employee_email,
            vend_customer_id,
            vend_customer_name,
            user_id,
            mapping_status,
            mapping_confidence,
            total_blocked_amount,
            deduction_count,
            created_at,
            updated_at
        ) VALUES (
            :xero_employee_id,
            :employee_name,
            :employee_email,
            :vend_customer_id,
            :vend_customer_name,
            :user_id,
            :mapping_status,
            :mapping_confidence,
            :total_blocked_amount,
            :deduction_count,
            NOW(),
            NOW()
        )
    ");
    
    $insertedCount = 0;
    $mappedCount = 0;
    $unmappedCount = 0;
    
    foreach ($xeroEmployees as $employee) {
        $xeroId = $employee['xero_employee_id'];
        $employeeName = $employee['employee_name'];
        $totalDeductions = (float)$employee['total_deductions'];
        $deductionCount = (int)$employee['deduction_count'];
        
        // Check if user exists
        $user = $usersByXeroId[$xeroId] ?? null;
        $userId = $user ? (int)$user['user_id'] : null;
        $employeeEmail = $user['email'] ?? null;
        
        // Check if mapping exists
        $vendCustomerId = $mappingsByXeroId[$xeroId] ?? null;
        
        // Determine mapping status and confidence
        if ($vendCustomerId) {
            $mappingStatus = 'mapped';
            $mappingConfidence = 100.0; // Existing mapping = 100% confidence
            $mappedCount++;
        } elseif ($user && $user['vend_customer_account']) {
            // User exists with vend account - auto-suggest mapping
            $mappingStatus = 'suggested';
            $mappingConfidence = 95.0; // High confidence auto-match
            $vendCustomerId = $user['vend_customer_account'];
        } else {
            $mappingStatus = 'unmapped';
            $mappingConfidence = 0.0;
            $unmappedCount++;
        }
        
        // Insert mapping
        $insertStmt->execute([
            'xero_employee_id' => $xeroId,
            'employee_name' => $employeeName,
            'employee_email' => $employeeEmail,
            'vend_customer_id' => $vendCustomerId,
            'vend_customer_name' => $employeeName, // Use same name initially
            'user_id' => $userId,
            'mapping_status' => $mappingStatus,
            'mapping_confidence' => $mappingConfidence,
            'total_blocked_amount' => $totalDeductions,
            'deduction_count' => $deductionCount
        ]);
        
        $insertedCount++;
        
        if ($insertedCount % 50 == 0) {
            echo "  Processed $insertedCount employees...\n";
        }
    }
    
    $pdo->commit();
    
    echo "\n";
    echo "=================================================\n";
    echo "Migration Complete!\n";
    echo "=================================================\n";
    echo "Total employees migrated: $insertedCount\n";
    echo "  - Mapped: $mappedCount\n";
    echo "  - Unmapped: $unmappedCount\n";
    echo "  - Auto-suggested: " . ($insertedCount - $mappedCount - $unmappedCount) . "\n";
    echo "\n";
    echo "✓ employee_mappings table is now populated\n";
    echo "✓ API endpoints will now return real data\n";
    echo "\n";
    
    // Show summary stats
    $stmt = $pdo->query("
        SELECT 
            mapping_status,
            COUNT(*) as count,
            SUM(total_blocked_amount) as total_amount
        FROM employee_mappings
        GROUP BY mapping_status
        ORDER BY mapping_status
    ");
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Status Breakdown:\n";
    foreach ($stats as $stat) {
        echo sprintf(
            "  %s: %d employees ($%s blocked)\n",
            ucfirst($stat['mapping_status']),
            $stat['count'],
            number_format($stat['total_amount'], 2)
        );
    }
    echo "\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n\n";
    exit(1);
}

echo "✓ Migration successful!\n";
echo "You can now test the API endpoints.\n\n";
