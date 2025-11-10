<?php
/**
 * Simple AI Usage Example
 *
 * This shows how to use the Intelligence Hub directly
 * without needing the full UniversalAIRouter
 */

declare(strict_types=1);

// Load environment
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// Load Intelligence Hub Adapter
require_once __DIR__ . '/lib/Services/AI/Adapters/IntelligenceHubAdapter.php';

use CIS\Consignments\Services\AI\Adapters\IntelligenceHubAdapter;

echo "\n🤖 Simple AI Example - Intelligence Hub\n\n";

// Initialize
$hub = new IntelligenceHubAdapter([
    'api_key' => $_ENV['INTELLIGENCE_HUB_API_KEY'] ?? '31ce0106609a6c5bc4f7ece0deb2f764df90a06167bda83468883516302a6a35',
    'mcp_endpoint' => $_ENV['INTELLIGENCE_HUB_MCP_ENDPOINT'] ?? 'https://gpt.ecigdis.co.nz/mcp/server_v4.php',
]);

// Example 1: Ask a question
echo "Example 1: Ask a question\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    $response = $hub->chat("What are the key files in the consignments module?");
    echo "✅ Response:\n";
    echo $response['message'] . "\n\n";
    echo "📊 Stats:\n";
    echo "  Model: {$response['model']}\n";
    echo "  Files searched: {$response['metadata']['files_searched']}\n";
    echo "  Processing time: {$response['processing_time_ms']}ms\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 2: Semantic search
echo "Example 2: Search for code\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    $response = $hub->semanticSearch("Find transfer validation logic");
    echo "✅ Found:\n";
    echo $response['message'] . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 3: Use MCP tool
echo "Example 3: Use MCP tool (system health)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    $response = $hub->useMCPTool('health_check', []);
    echo "✅ System health:\n";
    echo $response['message'] . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";
echo "🎉 Done! Intelligence Hub is working!\n\n";
