#!/usr/bin/env php
<?php
/**
 * Generate Encryption Key for OAuth Token Security
 * 
 * Generates a cryptographically secure 32-byte (256-bit) encryption key
 * for use with AES-256-GCM encryption in EncryptionService.
 * 
 * Usage:
 *   php cli/generate_encryption_key.php
 * 
 * Output:
 *   ENCRYPTION_KEY=base64_encoded_32_byte_key
 * 
 * Copy the output to your .env file:
 *   1. Copy the entire line (ENCRYPTION_KEY=...)
 *   2. Paste into .env file
 *   3. NEVER commit .env to git (security risk!)
 * 
 * For production deployments:
 *   1. Generate key on production server (not locally)
 *   2. Store in secure environment (AWS Secrets Manager, Azure Key Vault)
 *   3. Backup key securely (loss = permanent data loss)
 *   4. Rotate keys annually (requires re-encrypting all tokens)
 * 
 * @package HumanResources\Payroll\CLI
 * @version 1.0.0
 * @since 2025-11-01
 */

declare(strict_types=1);

// No framework dependencies - pure CLI script
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Ensure running from CLI
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Error: This script must be run from the command line' . PHP_EOL);
}

// ASCII art banner
echo PHP_EOL;
echo "╔══════════════════════════════════════════════════════════════╗" . PHP_EOL;
echo "║         VapeShed Payroll - Encryption Key Generator         ║" . PHP_EOL;
echo "║                  OAuth Token Security Setup                 ║" . PHP_EOL;
echo "╚══════════════════════════════════════════════════════════════╝" . PHP_EOL;
echo PHP_EOL;

try {
    // Generate cryptographically secure random bytes (32 bytes = 256 bits for AES-256)
    echo "Generating cryptographically secure 32-byte key..." . PHP_EOL;
    $keyBytes = random_bytes(32);
    
    // Encode to base64 for storage in .env file
    $keyBase64 = base64_encode($keyBytes);
    
    // Verify key length (should be 44 characters in base64: ceil(32/3)*4)
    $expectedBase64Length = 44; // ceil(32 / 3) * 4 = 44 for 32 bytes
    if (strlen($keyBase64) !== $expectedBase64Length) {
        throw new RuntimeException(
            "Unexpected key length: got " . strlen($keyBase64) . " chars, expected {$expectedBase64Length}"
        );
    }
    
    // Test decode (should be exactly 32 bytes)
    $decodedKey = base64_decode($keyBase64, true);
    if ($decodedKey === false || strlen($decodedKey) !== 32) {
        throw new RuntimeException("Key validation failed: base64 decode error");
    }
    
    // Success - display the key
    echo "✓ Key generated successfully!" . PHP_EOL;
    echo PHP_EOL;
    echo str_repeat("=", 66) . PHP_EOL;
    echo "COPY THIS LINE TO YOUR .env FILE:" . PHP_EOL;
    echo str_repeat("=", 66) . PHP_EOL;
    echo PHP_EOL;
    echo "ENCRYPTION_KEY={$keyBase64}" . PHP_EOL;
    echo PHP_EOL;
    echo str_repeat("=", 66) . PHP_EOL;
    echo PHP_EOL;
    
    // Security warnings
    echo "⚠️  SECURITY WARNINGS:" . PHP_EOL;
    echo PHP_EOL;
    echo "  1. Keep this key SECRET - anyone with it can decrypt OAuth tokens" . PHP_EOL;
    echo "  2. NEVER commit .env file to git (already in .gitignore)" . PHP_EOL;
    echo "  3. Backup this key securely (loss = permanent data loss)" . PHP_EOL;
    echo "  4. Different keys for DEV/STAGING/PRODUCTION environments" . PHP_EOL;
    echo "  5. Rotate keys annually (requires re-encrypting tokens)" . PHP_EOL;
    echo PHP_EOL;
    
    // Next steps
    echo "📋 NEXT STEPS:" . PHP_EOL;
    echo PHP_EOL;
    echo "  1. Copy the ENCRYPTION_KEY line above" . PHP_EOL;
    echo "  2. Add to .env file (create from .env.example if needed)" . PHP_EOL;
    echo "  3. Verify .env in .gitignore (security check)" . PHP_EOL;
    echo "  4. Run migration: php cli/migrate_encrypt_tokens.php" . PHP_EOL;
    echo "  5. Test OAuth flow: curl /api/payroll/xero/connect" . PHP_EOL;
    echo PHP_EOL;
    
    // Key details (for logging/reference)
    echo "🔐 KEY DETAILS:" . PHP_EOL;
    echo PHP_EOL;
    echo "  Algorithm:      AES-256-GCM (authenticated encryption)" . PHP_EOL;
    echo "  Key Size:       256 bits (32 bytes)" . PHP_EOL;
    echo "  Base64 Length:  {$expectedBase64Length} characters" . PHP_EOL;
    echo "  Entropy:        256 bits (cryptographically secure)" . PHP_EOL;
    echo "  IV Size:        96 bits (12 bytes, random per encryption)" . PHP_EOL;
    echo "  Tag Size:       128 bits (16 bytes, GCM authentication)" . PHP_EOL;
    echo PHP_EOL;
    
    // Production considerations
    echo "🏭 PRODUCTION DEPLOYMENT:" . PHP_EOL;
    echo PHP_EOL;
    echo "  For production, consider using a secrets management service:" . PHP_EOL;
    echo "  • AWS Secrets Manager (auto-rotation support)" . PHP_EOL;
    echo "  • Azure Key Vault (HSM-backed keys)" . PHP_EOL;
    echo "  • HashiCorp Vault (enterprise secrets mgmt)" . PHP_EOL;
    echo "  • Google Cloud Secret Manager" . PHP_EOL;
    echo PHP_EOL;
    echo "  Avoid storing encryption keys in:" . PHP_EOL;
    echo "  ❌ Source code (security risk)" . PHP_EOL;
    echo "  ❌ Database (circular dependency)" . PHP_EOL;
    echo "  ❌ Config files in git (will be committed)" . PHP_EOL;
    echo "  ✅ Environment variables (.env file, not committed)" . PHP_EOL;
    echo "  ✅ Secrets management service (recommended for production)" . PHP_EOL;
    echo PHP_EOL;
    
    exit(0);
    
} catch (Exception $e) {
    // Error handling
    echo PHP_EOL;
    echo "❌ ERROR: Failed to generate encryption key" . PHP_EOL;
    echo PHP_EOL;
    echo "Details: " . $e->getMessage() . PHP_EOL;
    echo PHP_EOL;
    echo "Troubleshooting:" . PHP_EOL;
    echo "  • Ensure PHP has access to random_bytes() function" . PHP_EOL;
    echo "  • Check PHP version >= 7.0 (required for random_bytes)" . PHP_EOL;
    echo "  • Verify /dev/urandom is accessible (Linux/Mac)" . PHP_EOL;
    echo "  • Check CryptGenRandom is available (Windows)" . PHP_EOL;
    echo PHP_EOL;
    
    exit(1);
}
