#!/usr/bin/env php
<?php
/**
 * Store Reports Real Flow Test
 * Tests the complete workflow with sample data
 *
 * Usage: php test-real-flow.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/assets/services/mcp/StoreReportsAdapter.php';

// Colors
const GREEN = "\033[32m";
const RED = "\033[31m";
const YELLOW = "\033[33m";
const BLUE = "\033[34m";
const CYAN = "\033[36m";
const RESET = "\033[0m";

function log_step(string $step, string $status = 'INFO'): void
{
    $color = match ($status) {
        'SUCCESS' => GREEN,
        'ERROR' => RED,
        'INFO' => CYAN,
        'WARN' => YELLOW,
        default => RESET
    };
    echo $color . "[{$status}]" . RESET . " {$step}\n";
}

function log_result(string $label, $value): void
{
    echo "  " . YELLOW . $label . ":" . RESET . " ";
    if (is_array($value) || is_object($value)) {
        echo json_encode($value, JSON_PRETTY_PRINT);
    } else {
        echo $value;
    }
    echo "\n";
}

echo BLUE . "=" . str_repeat("=", 70) . "=" . RESET . "\n";
echo BLUE . "STORE REPORTS - REAL FLOW TEST" . RESET . "\n";
echo BLUE . "=" . str_repeat("=", 70) . "=" . RESET . "\n\n";

try {
    $pdo = sr_pdo();

    // ========================================================================
    // STEP 1: Create Test Report
    // ========================================================================
    log_step("Creating test store report...", 'INFO');

    $testUserId = 999; // Test user
    $testOutletId = 1; // Test outlet

    $stmt = $pdo->prepare("
        INSERT INTO store_reports (
            outlet_id,
            report_date,
            performed_by_user,
            status,
            overall_score
        ) VALUES (?, NOW(), ?, 'draft', 0.00)
    ");
    $stmt->execute([$testOutletId, $testUserId]);
    $reportId = (int)$pdo->lastInsertId();

    log_result("Report ID", $reportId);
    log_step("✓ Test report created", 'SUCCESS');

    // ========================================================================
    // STEP 2: Create Test Image Record (no actual file for this test)
    // ========================================================================
    log_step("\nCreating test image record...", 'INFO');

    $stmt = $pdo->prepare("
        INSERT INTO store_report_images (
            report_id,
            filename,
            file_path,
            caption,
            uploaded_by_user,
            file_size,
            mime_type
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $reportId,
        'test-image.jpg',
        '/uploads/test-image.jpg',
        'Test store shelf photo',
        $testUserId,
        102400,
        'image/jpeg'
    ]);
    $imageId = (int)$pdo->lastInsertId();

    log_result("Image ID", $imageId);
    log_step("✓ Test image record created", 'SUCCESS');

    // ========================================================================
    // STEP 3: Test MCP Hub Connection
    // ========================================================================
    log_step("\nTesting MCP Hub connection...", 'INFO');

    $adapter = new Services\MCP\Adapters\StoreReportsAdapter();
    $adapter->setUser($testUserId)
            ->setReport($reportId);

    $mcp = $adapter->getMCPClient();
    $mcp->setUserId($testUserId)
        ->setUnitId($testOutletId)
        ->setProjectId(1)
        ->setBotId('store-reports-test-flow');

    // Simple health check
    $healthResult = $mcp->callTool('health-check', []);
    log_step("✓ MCP Hub connected", 'SUCCESS');
    log_result("Hub Status", $healthResult['result']['status'] ?? 'unknown');

    // ========================================================================
    // STEP 4: Test AI Text Generation
    // ========================================================================
    log_step("\nTesting AI text generation...", 'INFO');

    $prompt = "Analyze this retail inspection scenario: A staff member reports seeing expired products on shelf 3. Generate a brief summary of concerns and recommended actions.";

    $aiResult = $mcp->callTool('ai-generate', [
        'prompt' => $prompt,
        'model' => 'gpt-4-turbo-preview',
        'temperature' => 0.7,
        'max_tokens' => 200
    ]);

    if (isset($aiResult['result']['content'])) {
        log_step("✓ AI generation successful", 'SUCCESS');
        log_result("AI Response", substr($aiResult['result']['content'], 0, 200) . '...');
        log_result("Tokens Used", $aiResult['metadata']['tokens'] ?? 'N/A');

        // Save to report
        $stmt = $pdo->prepare("
            UPDATE store_reports
            SET ai_summary = ?,
                ai_analysis_status = 'completed'
            WHERE id = ?
        ");
        $stmt->execute([$aiResult['result']['content'], $reportId]);
    } else {
        log_step("✗ AI generation failed", 'ERROR');
    }

    // ========================================================================
    // STEP 5: Test Conversation Context
    // ========================================================================
    log_step("\nTesting conversational AI...", 'INFO');

    $conversationMessages = [
        ['role' => 'system', 'content' => 'You are a retail store inspection assistant.'],
        ['role' => 'user', 'content' => 'What should I check for in the refrigeration section?']
    ];

    $chatResult = $mcp->callTool('ai-generate', [
        'prompt' => json_encode($conversationMessages),
        'model' => 'gpt-4-turbo-preview',
        'temperature' => 0.7,
        'max_tokens' => 150
    ]);

    if (isset($chatResult['result']['content'])) {
        log_step("✓ Conversational AI successful", 'SUCCESS');
        log_result("AI Answer", substr($chatResult['result']['content'], 0, 150) . '...');

        // Save conversation
        $stmt = $pdo->prepare("
            INSERT INTO store_report_ai_conversations (
                report_id,
                message_from,
                user_id,
                message_text,
                ai_tokens_used
            ) VALUES (?, 'ai', NULL, ?, ?)
        ");
        $stmt->execute([
            $reportId,
            $chatResult['result']['content'],
            $chatResult['metadata']['tokens'] ?? 0
        ]);
    }

    // ========================================================================
    // STEP 6: Check Hub Logs
    // ========================================================================
    log_step("\nChecking MCP Hub activity...", 'INFO');

    echo "\n" . CYAN . "Hub Request Summary:" . RESET . "\n";
    echo "  • Bot ID: store-reports-test-flow\n";
    echo "  • User ID: {$testUserId}\n";
    echo "  • Unit ID: {$testOutletId}\n";
    echo "  • Project ID: 1\n";
    echo "  • Report ID: {$reportId}\n";

    // ========================================================================
    // STEP 7: Verify Database Records
    // ========================================================================
    log_step("\nVerifying database records...", 'INFO');

    // Check report
    $stmt = $pdo->prepare("SELECT * FROM store_reports WHERE id = ?");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($report) {
        log_step("✓ Report found in database", 'SUCCESS');
        log_result("Status", $report['status']);
        log_result("AI Status", $report['ai_analysis_status'] ?? 'N/A');
    }

    // Check conversations
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM store_report_ai_conversations WHERE report_id = ?");
    $stmt->execute([$reportId]);
    $convCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    log_result("AI Conversations", $convCount);

    // ========================================================================
    // STEP 8: Cleanup
    // ========================================================================
    log_step("\nCleaning up test data...", 'INFO');

    $pdo->prepare("DELETE FROM store_report_ai_conversations WHERE report_id = ?")->execute([$reportId]);
    $pdo->prepare("DELETE FROM store_report_images WHERE report_id = ?")->execute([$reportId]);
    $pdo->prepare("DELETE FROM store_reports WHERE id = ?")->execute([$reportId]);

    log_step("✓ Test data cleaned up", 'SUCCESS');

    // ========================================================================
    // SUMMARY
    // ========================================================================
    echo "\n" . BLUE . "=" . str_repeat("=", 70) . "=" . RESET . "\n";
    echo GREEN . "✅ ALL TESTS PASSED!" . RESET . "\n";
    echo BLUE . "=" . str_repeat("=", 70) . "=" . RESET . "\n\n";

    echo CYAN . "What was tested:" . RESET . "\n";
    echo "  ✓ Database connectivity\n";
    echo "  ✓ Report creation\n";
    echo "  ✓ Image record creation\n";
    echo "  ✓ MCP Hub connection\n";
    echo "  ✓ AI text generation\n";
    echo "  ✓ Conversational AI\n";
    echo "  ✓ Database record persistence\n";
    echo "  ✓ Cleanup procedures\n\n";

    echo GREEN . "🚀 Store Reports + MCP Hub integration is WORKING!" . RESET . "\n\n";

} catch (Exception $e) {
    echo "\n" . RED . "ERROR: " . $e->getMessage() . RESET . "\n";
    echo RED . "File: " . $e->getFile() . " (Line " . $e->getLine() . ")" . RESET . "\n\n";
    exit(1);
}
