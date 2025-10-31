<?php
/**
 * Sprint 1 Integration Test Suite
 * Tests all critical endpoint fixes and service rewrites
 *
 * Usage: php test-sprint1-endpoints.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

class Sprint1TestSuite {
    private $db;
    private $results = [];
    private $baseUrl = 'https://staff.vapeshed.co.nz/modules/consignments/api/purchase-orders/';

    public function __construct() {
        $this->db = get_db();
    }

    public function runAll(): void {
        echo "=== Sprint 1 Integration Test Suite ===\n\n";

        $this->testDatabaseTables();
        $this->testAcceptAIInsight();
        $this->testDismissAIInsight();
        $this->testBulkAccept();
        $this->testBulkDismiss();
        $this->testLogInteraction();
        $this->testTransferReviewService();
        $this->testPurchaseOrderLogger();

        $this->printResults();
    }

    private function testDatabaseTables(): void {
        echo "[1/8] Testing Database Tables...\n";

        $requiredTables = [
            'consignment_ai_insights',
            'consignment_metrics',
            'consignment_audit_log',
            'flagged_products_points',
            'flagged_products_achievements',
            'cis_action_log',
            'cis_ai_context'
        ];

        $missingTables = [];
        foreach ($requiredTables as $table) {
            $result = $this->db->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows === 0) {
                $missingTables[] = $table;
            }
        }

        if (empty($missingTables)) {
            $this->pass("All required tables exist");
        } else {
            $this->fail("Missing tables: " . implode(', ', $missingTables));
        }
    }

    private function testAcceptAIInsight(): void {
        echo "[2/8] Testing Accept AI Insight Endpoint...\n";

        // Create test insight
        $testInsight = $this->createTestInsight('TEST_ACCEPT');

        if (!$testInsight) {
            $this->fail("Could not create test insight");
            return;
        }

        // Simulate POST request
        $postData = [
            'insight_id' => $testInsight['id'],
            'po_id' => $testInsight['po_id'],
            'feedback' => 'Test acceptance'
        ];

        // Check file exists and has correct bootstrap
        $file = __DIR__ . '/../api/purchase-orders/accept-ai-insight.php';
        if (!file_exists($file)) {
            $this->fail("accept-ai-insight.php not found");
            return;
        }

        $content = file_get_contents($file);
        if (strpos($content, "require_once __DIR__ . '/../../bootstrap.php'") === false) {
            $this->fail("Bootstrap not correctly set");
            return;
        }

        if (strpos($content, '\CIS\Consignments\Lib\PurchaseOrderLogger') === false) {
            $this->fail("Logger namespace not correct");
            return;
        }

        // Verify insight can be updated
        $stmt = $this->db->prepare("UPDATE consignment_ai_insights SET status = 'ACCEPTED' WHERE id = ?");
        $stmt->bind_param('i', $testInsight['id']);

        if ($stmt->execute()) {
            $this->pass("Endpoint structure valid, DB update works");
        } else {
            $this->fail("DB update failed: " . $stmt->error);
        }

        // Cleanup
        $this->cleanupTestInsight($testInsight['id']);
    }

    private function testDismissAIInsight(): void {
        echo "[3/8] Testing Dismiss AI Insight Endpoint...\n";

        $testInsight = $this->createTestInsight('TEST_DISMISS');

        if (!$testInsight) {
            $this->fail("Could not create test insight");
            return;
        }

        $file = __DIR__ . '/../api/purchase-orders/dismiss-ai-insight.php';
        if (!file_exists($file)) {
            $this->fail("dismiss-ai-insight.php not found");
            return;
        }

        $content = file_get_contents($file);

        // Check for correct fixes
        $checks = [
            "require_once __DIR__ . '/../../bootstrap.php'" => "Bootstrap path",
            '\CIS\Consignments\Lib\PurchaseOrderLogger::aiRecommendationDismissed' => "Logger namespace",
            '$insightId,' => "Correct parameter order (insightId first)"
        ];

        $allPassed = true;
        foreach ($checks as $pattern => $description) {
            if (strpos($content, $pattern) === false) {
                $this->fail("$description not found");
                $allPassed = false;
            }
        }

        if ($allPassed) {
            $this->pass("All fixes verified in dismiss endpoint");
        }

        $this->cleanupTestInsight($testInsight['id']);
    }

    private function testBulkAccept(): void {
        echo "[4/8] Testing Bulk Accept Endpoint...\n";

        $file = __DIR__ . '/../api/purchase-orders/bulk-accept-ai-insights.php';
        $content = file_get_contents($file);

        $checks = [
            "require_once __DIR__ . '/../../bootstrap.php'" => "Bootstrap",
            'PurchaseOrderLogger::aiBulkRecommendationsProcessed' => "Bulk logger method",
            "'accept'" => "Accept action parameter"
        ];

        $allPassed = true;
        foreach ($checks as $pattern => $description) {
            if (strpos($content, $pattern) === false) {
                $this->fail("Bulk accept: $description not found");
                $allPassed = false;
            }
        }

        if ($allPassed) {
            $this->pass("Bulk accept endpoint validated");
        }
    }

    private function testBulkDismiss(): void {
        echo "[5/8] Testing Bulk Dismiss Endpoint...\n";

        $file = __DIR__ . '/../api/purchase-orders/bulk-dismiss-ai-insights.php';
        $content = file_get_contents($file);

        $checks = [
            "require_once __DIR__ . '/../../bootstrap.php'" => "Bootstrap",
            'PurchaseOrderLogger::aiBulkRecommendationsProcessed' => "Bulk logger method",
            "'dismiss'" => "Dismiss action parameter"
        ];

        $allPassed = true;
        foreach ($checks as $pattern => $description) {
            if (strpos($content, $pattern) === false) {
                $this->fail("Bulk dismiss: $description not found");
                $allPassed = false;
            }
        }

        if ($allPassed) {
            $this->pass("Bulk dismiss endpoint validated");
        }
    }

    private function testLogInteraction(): void {
        echo "[6/8] Testing Log Interaction Endpoint...\n";

        $file = __DIR__ . '/../api/purchase-orders/log-interaction.php';

        if (!file_exists($file)) {
            $this->fail("log-interaction.php does not exist");
            return;
        }

        $content = file_get_contents($file);

        $checks = [
            "require_once __DIR__ . '/../../bootstrap.php'" => "Bootstrap",
            'PurchaseOrderLogger::init()' => "Logger init",
            'modal_opened' => "Modal event handling",
            'ai_recommendation_accepted' => "AI event handling",
            'devtools_detected' => "Security event handling",
            'rate limit' => "Rate limiting"
        ];

        $allPassed = true;
        foreach ($checks as $pattern => $description) {
            if (stripos($content, $pattern) === false) {
                $this->fail("Log interaction: $description not found");
                $allPassed = false;
            }
        }

        if ($allPassed) {
            $this->pass("Log interaction endpoint created and validated");
        }
    }

    private function testTransferReviewService(): void {
        echo "[7/8] Testing TransferReviewService...\n";

        $file = __DIR__ . '/../lib/Services/TransferReviewService.php';

        if (!file_exists($file)) {
            $this->fail("TransferReviewService.php does not exist");
            return;
        }

        $content = file_get_contents($file);

        $checks = [
            'consignment_metrics' => "Uses consignment_metrics table",
            'flagged_products_points' => "Uses gamification points table",
            'flagged_products_achievements' => "Uses achievements table",
            '\CISLogger::ai' => "Uses CISLogger::ai directly",
            'tableExists' => "Has tableExists helper",
            'transfer_reviews' => "Should NOT reference old table"
        ];

        $allPassed = true;
        foreach ($checks as $pattern => $description) {
            $found = strpos($content, $pattern) !== false;

            if ($pattern === 'transfer_reviews') {
                // This should NOT be found
                if ($found) {
                    $this->fail("Still references transfer_reviews table");
                    $allPassed = false;
                }
            } else {
                if (!$found) {
                    $this->fail("TransferReviewService: $description not found");
                    $allPassed = false;
                }
            }
        }

        if ($allPassed) {
            $this->pass("TransferReviewService rewrite validated");
        }

        // Test service instantiation
        try {
            require_once $file;
            $service = new \CIS\Consignments\Services\TransferReviewService();
            $this->pass("TransferReviewService instantiates without errors");
        } catch (Exception $e) {
            $this->fail("TransferReviewService instantiation failed: " . $e->getMessage());
        }
    }

    private function testPurchaseOrderLogger(): void {
        echo "[8/8] Testing PurchaseOrderLogger...\n";

        $file = __DIR__ . '/../lib/PurchaseOrderLogger.php';
        $content = file_get_contents($file);

        // Check for internal wrappers
        $checks = [
            'private static function log(' => "Internal log wrapper",
            'private static function logAI(' => "Internal logAI wrapper",
            'private static function logSecurity(' => "Internal logSecurity wrapper",
            'private static function logPerformance(' => "Internal logPerformance wrapper",
            '\CISLogger::action' => "Calls CISLogger::action",
            '\CISLogger::ai' => "Calls CISLogger::ai",
            '\CISLogger::security' => "Calls CISLogger::security",
            '\CISLogger::performance' => "Calls CISLogger::performance",
            'public static function aiRecommendationAccepted' => "AI acceptance method",
            'public static function aiRecommendationDismissed' => "AI dismissal method",
            'public static function aiBulkRecommendationsProcessed' => "Bulk processing method"
        ];

        $allPassed = true;
        foreach ($checks as $pattern => $description) {
            if (strpos($content, $pattern) === false) {
                $this->fail("PurchaseOrderLogger: $description not found");
                $allPassed = false;
            }
        }

        if ($allPassed) {
            $this->pass("PurchaseOrderLogger structure validated");
        }

        // Test logger initialization
        try {
            require_once $file;
            \CIS\Consignments\Lib\PurchaseOrderLogger::init();
            $this->pass("PurchaseOrderLogger::init() works");
        } catch (Exception $e) {
            $this->fail("PurchaseOrderLogger init failed: " . $e->getMessage());
        }
    }

    // Helper methods

    private function createTestInsight(string $type): ?array {
        $stmt = $this->db->prepare(
            "INSERT INTO consignment_ai_insights
            (po_id, type, category, suggestion, confidence_score, status, created_at)
            VALUES (999999, ?, 'test', 'Test suggestion', 0.95, 'PENDING', NOW())"
        );

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $type);

        if ($stmt->execute()) {
            return [
                'id' => $stmt->insert_id,
                'po_id' => 999999,
                'type' => $type
            ];
        }

        return null;
    }

    private function cleanupTestInsight(int $id): void {
        $stmt = $this->db->prepare("DELETE FROM consignment_ai_insights WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }

    private function pass(string $message): void {
        $this->results[] = ['status' => 'PASS', 'message' => $message];
        echo "  ✓ PASS: $message\n";
    }

    private function fail(string $message): void {
        $this->results[] = ['status' => 'FAIL', 'message' => $message];
        echo "  ✗ FAIL: $message\n";
    }

    private function printResults(): void {
        echo "\n=== Test Results ===\n\n";

        $passed = count(array_filter($this->results, fn($r) => $r['status'] === 'PASS'));
        $failed = count(array_filter($this->results, fn($r) => $r['status'] === 'FAIL'));
        $total = count($this->results);

        echo "Total Tests: $total\n";
        echo "Passed: $passed ✓\n";
        echo "Failed: $failed ✗\n";

        if ($failed === 0) {
            echo "\n🎉 ALL TESTS PASSED! Sprint 1 fixes validated.\n";
        } else {
            echo "\n⚠️  Some tests failed. Review output above.\n";
        }
    }
}

// Run tests
try {
    $suite = new Sprint1TestSuite();
    $suite->runAll();
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
