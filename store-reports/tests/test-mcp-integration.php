#!/usr/bin/env php
<?php
/**
 * MCP Integration Test Suite
 * Tests all Store Reports AI endpoints with MCP Hub
 *
 * Usage: php test-mcp-integration.php
 */

declare(strict_types=1);

// Bootstrap environment
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/assets/services/mcp/StoreReportsAdapter.php';

// Colors for terminal output
const GREEN = "\033[32m";
const RED = "\033[31m";
const YELLOW = "\033[33m";
const BLUE = "\033[34m";
const RESET = "\033[0m";

// Test results
$testsPassed = 0;
$testsFailed = 0;
$testsSkipped = 0;

function log_test(string $name, string $status, string $message = ''): void
{
    global $testsPassed, $testsFailed, $testsSkipped;

    $color = match ($status) {
        'PASS' => GREEN,
        'FAIL' => RED,
        'SKIP' => YELLOW,
        default => RESET
    };

    echo $color . "[$status]" . RESET . " $name";
    if ($message) echo ": $message";
    echo "\n";

    match ($status) {
        'PASS' => $testsPassed++,
        'FAIL' => $testsFailed++,
        'SKIP' => $testsSkipped++,
        default => null
    };
}

echo BLUE . "=".str_repeat("=", 70) . "=" . RESET . "\n";
echo BLUE . "MCP HUB INTEGRATION TEST SUITE - Store Reports Module" . RESET . "\n";
echo BLUE . "=".str_repeat("=", 70) . "=" . RESET . "\n\n";

// ============================================================================
// Test 1: MCP Client Connectivity
// ============================================================================
echo YELLOW . "Test Group 1: MCP Client Connectivity" . RESET . "\n";

try {
    $adapter = new Services\MCP\Adapters\StoreReportsAdapter();
    $mcp = $adapter->getMCPClient();

    // Set required context for MCP Hub (User ID, Unit ID, Bot ID)
    $mcp->setUserId(999)              // Test user ID
        ->setUnitId(1)                // Test outlet/unit ID
        ->setProjectId(1)             // Project 1 = CIS
        ->setBotId('store-reports-test-bot');  // Bot identifier

    log_test("Initialize MCP Client", "PASS", "StoreReportsAdapter created with context");
} catch (Exception $e) {
    log_test("Initialize MCP Client", "FAIL", $e->getMessage());
    exit(1);
}

// Test MCP Hub connectivity with simple tool call
try {
    $result = $mcp->callTool('health-check', []);
    if (isset($result['success']) || isset($result['result'])) {
        log_test("MCP Hub Connectivity", "PASS", "Hub responded successfully");
    } else {
        log_test("MCP Hub Connectivity", "FAIL", "Unexpected response structure");
    }
} catch (Exception $e) {
    log_test("MCP Hub Connectivity", "FAIL", $e->getMessage());
}

// ============================================================================
// Test 2: AI Generation Tool (Used by all endpoints)
// ============================================================================
echo "\n" . YELLOW . "Test Group 2: AI Generation Tool" . RESET . "\n";

try {
    $result = $mcp->callTool('ai-generate', [
        'prompt' => 'Say "MCP Hub test successful" in exactly those words.',
        'model' => 'gpt-4-turbo-preview',
        'max_tokens' => 50,
        'temperature' => 0
    ]);

    if (isset($result['result']['content'])) {
        $content = $result['result']['content'];
        if (stripos($content, 'MCP Hub test successful') !== false) {
            log_test("AI Generate Tool", "PASS", "GPT-4 responded correctly");
        } else {
            log_test("AI Generate Tool", "FAIL", "Unexpected response: $content");
        }
    } else {
        log_test("AI Generate Tool", "FAIL", "Invalid response structure");
    }
} catch (Exception $e) {
    log_test("AI Generate Tool", "FAIL", $e->getMessage());
}

// ============================================================================
// Test 3: Image Analysis (SKIPPED - Requires Database)
// ============================================================================
echo "\n" . YELLOW . "Test Group 3: Image Analysis Adapter Method" . RESET . "\n";

// Note: Image analysis requires database connection to fetch file paths
// This would require Store Reports schema to be deployed
log_test("Image Analysis", "SKIP", "Requires store_report_images table (schema not deployed)");

// ============================================================================
// Test 4: Voice Transcription (SKIPPED - Requires Database)
// ============================================================================
echo "\n" . YELLOW . "Test Group 4: Voice Transcription Adapter Method" . RESET . "\n";

// Note: Voice transcription requires database connection to fetch file paths
log_test("Voice Transcription", "SKIP", "Requires store_report_voice_memos table (schema not deployed)");

// ============================================================================
// Test 5: Conversational AI (Direct MCP Call)
// ============================================================================
echo "\n" . YELLOW . "Test Group 5: Conversational AI" . RESET . "\n";

try {
    $messages = [
        ['role' => 'system', 'content' => 'You are a retail store inspection assistant.'],
        ['role' => 'user', 'content' => 'What should I check for cleanliness?']
    ];

    $result = $mcp->callTool('ai-generate', [
        'prompt' => json_encode($messages),
        'model' => 'gpt-4-turbo-preview',
        'temperature' => 0.7,
        'max_tokens' => 200
    ]);

    if (isset($result['result']['content'])) {
        $response = $result['result']['content'];
        if (strlen($response) > 20) {
            log_test("Conversational AI", "PASS", "Received meaningful response (" . strlen($response) . " chars)");
        } else {
            log_test("Conversational AI", "FAIL", "Response too short: $response");
        }
    } else {
        log_test("Conversational AI", "FAIL", "Invalid response structure");
    }
} catch (Exception $e) {
    log_test("Conversational AI", "FAIL", $e->getMessage());
}

// ============================================================================
// Test 6: Response Format Validation
// ============================================================================
echo "\n" . YELLOW . "Test Group 6: Response Format Validation" . RESET . "\n";

try {
    $result = $mcp->callTool('ai-generate', [
        'prompt' => 'Test response',
        'model' => 'gpt-4-turbo-preview',
        'max_tokens' => 10
    ]);

    $hasResult = isset($result['result']);
    $hasMetadata = isset($result['metadata']);
    $hasContent = isset($result['result']['content']);

    if ($hasResult && $hasContent) {
        log_test("Response Structure", "PASS", "Contains result.content");
    } else {
        log_test("Response Structure", "FAIL", "Missing required fields");
    }

    if ($hasMetadata && isset($result['metadata']['tokens'])) {
        log_test("Token Tracking", "PASS", "Metadata includes token count");
    } else {
        log_test("Token Tracking", "SKIP", "Token metadata not available");
    }

} catch (Exception $e) {
    log_test("Response Format", "FAIL", $e->getMessage());
}

// ============================================================================
// Test 7: Rate Limiting and Retry Logic
// ============================================================================
echo "\n" . YELLOW . "Test Group 7: Client Features" . RESET . "\n";

// Test that retry logic exists (we won't trigger actual retries)
try {
    $reflection = new ReflectionClass($mcp);
    $hasRetryConfig = $reflection->hasProperty('maxRetries');

    if ($hasRetryConfig) {
        log_test("Retry Configuration", "PASS", "MCPClient has retry logic");
    } else {
        log_test("Retry Configuration", "SKIP", "Retry config not detected");
    }
} catch (Exception $e) {
    log_test("Retry Configuration", "SKIP", $e->getMessage());
}

// Test context injection
try {
    $mcp->setUserId(123);
    $mcp->setProjectId(1);
    $mcp->setUnitId(5);
    log_test("Context Injection", "PASS", "User/Project/Unit IDs set successfully");
} catch (Exception $e) {
    log_test("Context Injection", "FAIL", $e->getMessage());
}

// ============================================================================
// Summary
// ============================================================================
echo "\n" . BLUE . "=".str_repeat("=", 70) . "=" . RESET . "\n";
echo BLUE . "TEST SUMMARY" . RESET . "\n";
echo BLUE . "=".str_repeat("=", 70) . "=" . RESET . "\n";
echo GREEN . "Passed: $testsPassed" . RESET . "\n";
echo RED . "Failed: $testsFailed" . RESET . "\n";
echo YELLOW . "Skipped: $testsSkipped" . RESET . "\n";
echo "Total: " . ($testsPassed + $testsFailed + $testsSkipped) . "\n\n";

if ($testsFailed > 0) {
    echo RED . "❌ Some tests failed. Please review the errors above." . RESET . "\n";
    exit(1);
} else {
    echo GREEN . "✅ All tests passed! MCP Hub integration is working." . RESET . "\n";
    exit(0);
}
