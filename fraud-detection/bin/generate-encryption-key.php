#!/usr/bin/env php
<?php
/**
 * Encryption Key Generator
 *
 * Generates secure encryption keys for the Fraud Detection System
 *
 * Usage:
 *   php generate-encryption-key.php
 *   php generate-encryption-key.php --output=.env
 *   php generate-encryption-key.php --verify=YOUR_EXISTING_KEY
 */

require_once __DIR__ . '/../lib/EncryptionService.php';

use FraudDetection\Lib\EncryptionService;

// Parse command line arguments
$options = getopt('', ['output::', 'verify::', 'help']);

if (isset($options['help'])) {
    showHelp();
    exit(0);
}

// Verify existing key
if (isset($options['verify'])) {
    verifyKey($options['verify']);
    exit(0);
}

// Generate new key
echo "🔐 Fraud Detection System - Encryption Key Generator\n";
echo str_repeat("=", 60) . "\n\n";

$key = EncryptionService::generateMasterKey();

echo "✅ Generated new 256-bit AES master encryption key:\n\n";
echo "    $key\n\n";

echo "⚠️  SECURITY WARNINGS:\n";
echo "   1. Store this key in a secure location (password manager, vault)\n";
echo "   2. NEVER commit this key to version control\n";
echo "   3. Different keys for dev, staging, production\n";
echo "   4. Losing this key = losing all encrypted data\n\n";

// Output to file if requested
if (isset($options['output'])) {
    $outputFile = $options['output'] ?: '.env';
    writeToEnvFile($outputFile, $key);
}

echo "📝 Add to your .env file:\n\n";
echo "    FRAUD_ENCRYPTION_KEY=\"$key\"\n\n";

echo "🧪 Test encryption:\n\n";
testEncryption($key);

echo "\n✅ Done! Key is ready to use.\n\n";

/**
 * Show help message
 */
function showHelp(): void
{
    echo <<<HELP
🔐 Fraud Detection Encryption Key Generator

Usage:
  php generate-encryption-key.php [options]

Options:
  --output[=FILE]  Write key to .env file (default: .env)
  --verify=KEY     Verify an existing key is valid
  --help           Show this help message

Examples:
  # Generate new key and display
  php generate-encryption-key.php

  # Generate and append to .env file
  php generate-encryption-key.php --output

  # Generate and write to custom file
  php generate-encryption-key.php --output=.env.production

  # Verify existing key
  php generate-encryption-key.php --verify="base64encodedkey..."

HELP;
}

/**
 * Verify existing key
 */
function verifyKey(string $key): void
{
    echo "🔍 Verifying encryption key...\n\n";

    // Decode and check length
    $decoded = base64_decode($key, true);

    if ($decoded === false) {
        echo "❌ INVALID: Key is not valid Base64\n";
        exit(1);
    }

    if (strlen($decoded) !== 32) {
        echo "❌ INVALID: Key must be 32 bytes (256 bits), got " . strlen($decoded) . " bytes\n";
        exit(1);
    }

    // Test encryption
    try {
        $service = new EncryptionService($decoded);
        $encrypted = $service->encrypt('test data');
        $decrypted = $service->decrypt($encrypted);

        if ($decrypted === 'test data') {
            echo "✅ VALID: Key is correct and encryption works\n";
            echo "   Key Version: " . substr(hash('sha256', $decoded), 0, 8) . "\n";
        } else {
            echo "❌ INVALID: Encryption/decryption test failed\n";
            exit(1);
        }
    } catch (\Exception $e) {
        echo "❌ INVALID: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * Write key to .env file
 */
function writeToEnvFile(string $filename, string $key): void
{
    $envLine = "FRAUD_ENCRYPTION_KEY=\"$key\"";

    if (file_exists($filename)) {
        // Check if key already exists
        $contents = file_get_contents($filename);
        if (strpos($contents, 'FRAUD_ENCRYPTION_KEY') !== false) {
            echo "⚠️  Warning: FRAUD_ENCRYPTION_KEY already exists in $filename\n";
            echo "   Skipping file write to avoid overwriting.\n";
            echo "   Manually update the file if needed.\n\n";
            return;
        }

        // Append to existing file
        file_put_contents($filename, "\n# Fraud Detection Encryption\n$envLine\n", FILE_APPEND);
        echo "✅ Key appended to $filename\n\n";
    } else {
        // Create new file
        $contents = <<<ENV
# Fraud Detection System Configuration
# Generated: %s

# Master Encryption Key (AES-256-GCM)
# WARNING: Keep this secret! Do not commit to version control!
$envLine

ENV;
        file_put_contents($filename, sprintf($contents, date('Y-m-d H:i:s')));
        echo "✅ Created $filename with encryption key\n\n";
    }
}

/**
 * Test encryption with generated key
 */
function testEncryption(string $key): void
{
    try {
        $decoded = base64_decode($key);
        $service = new EncryptionService($decoded);

        // Test basic encryption
        $plaintext = "Secret fraud detection data";
        $encrypted = $service->encrypt($plaintext);
        $decrypted = $service->decrypt($encrypted);

        if ($decrypted === $plaintext) {
            echo "   ✅ Basic encryption: PASSED\n";
        } else {
            echo "   ❌ Basic encryption: FAILED\n";
            return;
        }

        // Test with metadata (AAD)
        $metadata = ['staff_id' => 123, 'timestamp' => time()];
        $encrypted = $service->encrypt($plaintext, $metadata);
        $decrypted = $service->decrypt($encrypted, $metadata);

        if ($decrypted === $plaintext) {
            echo "   ✅ Encryption with metadata: PASSED\n";
        } else {
            echo "   ❌ Encryption with metadata: FAILED\n";
            return;
        }

        // Test tamper detection (should fail)
        $tampered = $encrypted;
        $tampered['encrypted_data'] = base64_encode('tampered');

        try {
            $service->decrypt($tampered, $metadata);
            echo "   ❌ Tamper detection: FAILED (should have thrown exception)\n";
        } catch (\Exception $e) {
            echo "   ✅ Tamper detection: PASSED (correctly rejected)\n";
        }

    } catch (\Exception $e) {
        echo "   ❌ Test failed: " . $e->getMessage() . "\n";
    }
}
