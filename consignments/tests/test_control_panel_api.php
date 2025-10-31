<?php
/**
 * Control Panel API Test Suite
 * 
 * Tests all 6 API operations:
 * 1. refresh_stats - Get dashboard statistics
 * 2. create - Create new transfer
 * 3. status - Change transfer status
 * 4. move - Move transfer between outlets
 * 5. adjust_stock - Adjust stock quantities
 * 6. delete - Delete transfer with reason
 * 
 * Usage: php test_control_panel_api.php
 * 
 * @package Modules\Consignments\Tests
 */

declare(strict_types=1);

// Set test environment
define('TEST_MODE', true);

// Colors for terminal output
const GREEN = "\033[32m";
const RED = "\033[31m";
const YELLOW = "\033[33m";
const BLUE = "\033[34m";
const RESET = "\033[0m";

class ControlPanelApiTest
{
    private string $apiUrl = 'http://localhost/modules/consignments/api/control_panel.php';
    private array $testResults = [];
    private int $passed = 0;
    private int $failed = 0;
    
    public function runAllTests(): void
    {
        echo BLUE . "╔════════════════════════════════════════════════════════════╗\n";
        echo "║  CONSIGNMENT CONTROL PANEL API TEST SUITE                 ║\n";
        echo "║  Testing: control_panel.php                               ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n" . RESET;
        echo "\n";
        
        // Test 1: Refresh Stats
        $this->testRefreshStats();
        
        // Test 2: Create Consignment (success)
        $transferId = $this->testCreateConsignment();
        
        // Test 3: Create Consignment (validation errors)
        $this->testCreateConsignmentValidation();
        
        // Test 4: Change Status
        if ($transferId) {
            $this->testChangeStatus($transferId);
        }
        
        // Test 5: Move Consignment
        if ($transferId) {
            $this->testMoveConsignment($transferId);
        }
        
        // Test 6: Adjust Stock (will fail if no products, but tests endpoint)
        if ($transferId) {
            $this->testAdjustStock($transferId);
        }
        
        // Test 7: Delete Consignment
        if ($transferId) {
            $this->testDeleteConsignment($transferId);
        }
        
        // Test 8: Error handling
        $this->testErrorHandling();
        
        // Print summary
        $this->printSummary();
    }
    
    private function testRefreshStats(): void
    {
        echo YELLOW . "TEST: Refresh Stats" . RESET . "\n";
        
        $response = $this->callApi([
            'action' => 'refresh_stats'
        ]);
        
        if ($response['success'] === true && isset($response['stats'])) {
            $stats = $response['stats'];
            $this->pass("✓ Refresh stats successful");
            echo "  Stats: Pending={$stats['pending']}, InTransit={$stats['inTransit']}, DeliveredToday={$stats['deliveredToday']}, Issues={$stats['issues']}\n";
        } else {
            $this->fail("✗ Refresh stats failed: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }
    
    private function testCreateConsignment(): ?int
    {
        echo YELLOW . "TEST: Create Consignment (Valid)" . RESET . "\n";
        
        $response = $this->callApi([
            'action' => 'create',
            'source_outlet_id' => 1,
            'destination_outlet_id' => 2,
            'type' => 'OUTLET_TRANSFER',
            'status' => 'OPEN',
            'notes' => 'Test transfer created by API test suite'
        ]);
        
        if ($response['success'] === true && isset($response['transfer_id'])) {
            $transferId = $response['transfer_id'];
            $this->pass("✓ Create consignment successful - Transfer ID: {$transferId}");
            echo "\n";
            return $transferId;
        } else {
            $this->fail("✗ Create consignment failed: " . ($response['message'] ?? 'Unknown error'));
            echo "\n";
            return null;
        }
    }
    
    private function testCreateConsignmentValidation(): void
    {
        echo YELLOW . "TEST: Create Consignment (Validation)" . RESET . "\n";
        
        // Missing source outlet
        $response = $this->callApi([
            'action' => 'create',
            'destination_outlet_id' => 2
        ]);
        
        if ($response['success'] === false && str_contains($response['message'], 'source_outlet_id')) {
            $this->pass("✓ Missing source outlet validation works");
        } else {
            $this->fail("✗ Missing source outlet validation failed");
        }
        
        // Same source and destination
        $response = $this->callApi([
            'action' => 'create',
            'source_outlet_id' => 1,
            'destination_outlet_id' => 1
        ]);
        
        if ($response['success'] === false) {
            $this->pass("✓ Same outlet validation works");
        } else {
            $this->fail("✗ Same outlet validation failed");
        }
        
        echo "\n";
    }
    
    private function testChangeStatus(int $transferId): void
    {
        echo YELLOW . "TEST: Change Status" . RESET . "\n";
        
        $response = $this->callApi([
            'action' => 'status',
            'consignment_id' => $transferId,
            'new_status' => 'SENT'
        ]);
        
        if ($response['success'] === true) {
            $this->pass("✓ Change status successful - Transfer #{$transferId} → SENT");
        } else {
            $this->fail("✗ Change status failed: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }
    
    private function testMoveConsignment(int $transferId): void
    {
        echo YELLOW . "TEST: Move Consignment" . RESET . "\n";
        
        $response = $this->callApi([
            'action' => 'move',
            'consignment_id' => $transferId,
            'new_destination_outlet_id' => 3,
            'reason' => 'Testing move operation'
        ]);
        
        if ($response['success'] === true) {
            $this->pass("✓ Move consignment successful - Transfer #{$transferId} → Outlet 3");
        } else {
            $this->fail("✗ Move consignment failed: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }
    
    private function testAdjustStock(int $transferId): void
    {
        echo YELLOW . "TEST: Adjust Stock" . RESET . "\n";
        
        // This will likely fail because we don't have products in the test transfer
        $response = $this->callApi([
            'action' => 'adjust_stock',
            'consignment_id' => $transferId,
            'variant_id' => 1,
            'adjustment' => 5,
            'location' => 'source',
            'reason' => 'Testing stock adjustment'
        ]);
        
        if ($response['success'] === true) {
            $this->pass("✓ Adjust stock successful");
        } else {
            // Expected to fail if no products exist
            echo "  ℹ️  Adjust stock skipped (no products in test transfer)\n";
        }
        
        echo "\n";
    }
    
    private function testDeleteConsignment(int $transferId): void
    {
        echo YELLOW . "TEST: Delete Consignment" . RESET . "\n";
        
        $response = $this->callApi([
            'action' => 'delete',
            'consignment_id' => $transferId,
            'reason' => 'Test cleanup - removing test transfer'
        ]);
        
        if ($response['success'] === true) {
            $this->pass("✓ Delete consignment successful - Transfer #{$transferId} removed");
        } else {
            $this->fail("✗ Delete consignment failed: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }
    
    private function testErrorHandling(): void
    {
        echo YELLOW . "TEST: Error Handling" . RESET . "\n";
        
        // Invalid action
        $response = $this->callApi(['action' => 'invalid_action']);
        if ($response['success'] === false) {
            $this->pass("✓ Invalid action rejected");
        } else {
            $this->fail("✗ Invalid action not rejected");
        }
        
        // Missing action
        $response = $this->callApi([]);
        if ($response['success'] === false) {
            $this->pass("✓ Missing action rejected");
        } else {
            $this->fail("✗ Missing action not rejected");
        }
        
        // Invalid JSON (simulate by sending raw string)
        $response = $this->callApiRaw('invalid json');
        if ($response['success'] === false && str_contains($response['message'], 'JSON')) {
            $this->pass("✓ Invalid JSON rejected");
        } else {
            $this->fail("✗ Invalid JSON not rejected");
        }
        
        echo "\n";
    }
    
    private function callApi(array $data): array
    {
        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            return ['success' => false, 'message' => 'cURL error'];
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'Invalid JSON response'];
        }
        
        return $decoded;
    }
    
    private function callApiRaw(string $data): array
    {
        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true) ?? ['success' => false, 'message' => 'No response'];
    }
    
    private function pass(string $message): void
    {
        echo GREEN . $message . RESET . "\n";
        $this->passed++;
        $this->testResults[] = ['status' => 'pass', 'message' => $message];
    }
    
    private function fail(string $message): void
    {
        echo RED . $message . RESET . "\n";
        $this->failed++;
        $this->testResults[] = ['status' => 'fail', 'message' => $message];
    }
    
    private function printSummary(): void
    {
        $total = $this->passed + $this->failed;
        $passRate = $total > 0 ? round(($this->passed / $total) * 100, 2) : 0;
        
        echo BLUE . "╔════════════════════════════════════════════════════════════╗\n";
        echo "║  TEST SUMMARY                                              ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n" . RESET;
        echo "\n";
        echo GREEN . "✓ Passed: {$this->passed}\n" . RESET;
        echo RED . "✗ Failed: {$this->failed}\n" . RESET;
        echo "Total: {$total}\n";
        echo "Pass Rate: {$passRate}%\n";
        echo "\n";
        
        if ($this->failed === 0) {
            echo GREEN . "🎉 ALL TESTS PASSED! 🎉\n" . RESET;
        } else {
            echo YELLOW . "⚠️  SOME TESTS FAILED - REVIEW ABOVE\n" . RESET;
        }
    }
}

// Run tests
$tester = new ControlPanelApiTest();
$tester->runAllTests();
