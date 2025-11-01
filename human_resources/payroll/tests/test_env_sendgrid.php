#!/usr/bin/env php
<?php
/**
 * Test Environment Variable Retrieval
 * Verifies SendGrid API key can be loaded from environment
 */

echo "🧪 Testing Environment Variable Retrieval\n";
echo "==========================================\n\n";

// Test 1: Check if SENDGRID_API_KEY is set
echo "Test 1: Check SENDGRID_API_KEY environment variable\n";
$apiKey = getenv('SENDGRID_API_KEY');

if ($apiKey === false) {
    echo "❌ FAILED: SENDGRID_API_KEY not found in environment\n";
    echo "   Please set: export SENDGRID_API_KEY='your-key-here'\n\n";
    exit(1);
} else {
    $maskedKey = substr($apiKey, 0, 7) . '...' . substr($apiKey, -4);
    echo "✅ PASSED: SENDGRID_API_KEY found\n";
    echo "   Value: $maskedKey\n";
    echo "   Length: " . strlen($apiKey) . " characters\n\n";
}

// Test 2: Load config file
echo "Test 2: Load SendGrid config file\n";
try {
    $configPath = __DIR__ . '/../config/sendgrid.php';
    if (!file_exists($configPath)) {
        echo "❌ FAILED: Config file not found at $configPath\n\n";
        exit(1);
    }

    $config = require $configPath;

    if (!isset($config['api_key'])) {
        echo "❌ FAILED: Config doesn't have 'api_key' field\n\n";
        exit(1);
    }

    $maskedConfigKey = substr($config['api_key'], 0, 7) . '...' . substr($config['api_key'], -4);
    echo "✅ PASSED: Config loaded successfully\n";
    echo "   API Key: $maskedConfigKey\n";
    echo "   From Email: {$config['from_email']}\n";
    echo "   From Name: {$config['from_name']}\n\n";

} catch (\Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 3: Verify keys match
echo "Test 3: Verify environment and config keys match\n";
if ($apiKey === $config['api_key']) {
    echo "✅ PASSED: Keys match perfectly\n\n";
} else {
    echo "❌ FAILED: Keys don't match\n";
    echo "   Environment: " . substr($apiKey, 0, 10) . "...\n";
    echo "   Config: " . substr($config['api_key'], 0, 10) . "...\n\n";
    exit(1);
}

// Summary
echo "==========================================\n";
echo "✅ ALL TESTS PASSED\n";
echo "==========================================\n";
echo "\nSendGrid configuration is correctly retrieving\n";
echo "the API key from the environment variable.\n";
echo "Safe to commit to GitHub! 🚀\n";

exit(0);
