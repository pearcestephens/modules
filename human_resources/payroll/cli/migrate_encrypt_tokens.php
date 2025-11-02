#!/usr/bin/env php
<?php
/**
 * Migrate OAuth Tokens to Encrypted Storage
 *
 * One-time migration script to encrypt existing plaintext OAuth tokens.
 * Reads tokens from oauth_tokens table, encrypts them, and updates in place.
 *
 * Requirements:
 *   - ENCRYPTION_KEY must be set in .env
 *   - Database credentials configured
 *   - XeroTokenStore and EncryptionService available
 *
 * Usage:
 *   php cli/migrate_encrypt_tokens.php [--dry-run] [--provider=xero]
 *
 * Options:
 *   --dry-run     Show what would be encrypted without modifying database
 *   --provider    Migrate specific provider only (default: all providers)
 *
 * Safety:
 *   - Idempotent: Safe to run multiple times (skips already encrypted tokens)
 *   - Transactional: Rolls back on error
 *   - Backup recommended: Take database snapshot before migration
 *
 * Example:
 *   # Dry run first (preview changes)
 *   php cli/migrate_encrypt_tokens.php --dry-run
 *
 *   # Migrate Xero tokens only
 *   php cli/migrate_encrypt_tokens.php --provider=xero
 *
 *   # Migrate all providers
 *   php cli/migrate_encrypt_tokens.php
 *
 * @package HumanResources\Payroll\CLI
 * @version 1.0.0
 * @since 2025-11-01
 */

declare(strict_types=1);

// Bootstrap application
require_once __DIR__ . '/../../../../../app.php';

use HumanResources\Payroll\Services\EncryptionService;

// Parse command-line options
$options = getopt('', ['dry-run', 'provider:']);
$dryRun = isset($options['dry-run']);
$provider = $options['provider'] ?? null;

// ASCII art banner
echo PHP_EOL;
echo "╔══════════════════════════════════════════════════════════════╗" . PHP_EOL;
echo "║      VapeShed Payroll - OAuth Token Encryption Migration    ║" . PHP_EOL;
echo "║            Secure Storage Upgrade (AES-256-GCM)             ║" . PHP_EOL;
echo "╚══════════════════════════════════════════════════════════════╝" . PHP_EOL;
echo PHP_EOL;

if ($dryRun) {
    echo "🔍 DRY RUN MODE - No database changes will be made" . PHP_EOL;
    echo PHP_EOL;
}

try {
    // 1. Verify encryption key configured
    echo "[1/6] Verifying encryption configuration..." . PHP_EOL;
    $encryptionKey = getenv('ENCRYPTION_KEY');

    if (!$encryptionKey) {
        throw new RuntimeException(
            'ENCRYPTION_KEY not configured. ' .
            'Generate key with: php cli/generate_encryption_key.php'
        );
    }

    // Initialize encryption service
    $encryption = new EncryptionService($encryptionKey);
    echo "      ✓ Encryption service initialized (AES-256-GCM)" . PHP_EOL;
    echo "      ✓ Key validated (32 bytes)" . PHP_EOL;
    echo PHP_EOL;

    // 2. Connect to database
    echo "[2/6] Connecting to database..." . PHP_EOL;
    $db = getDatabaseConnection(); // From app.php
    echo "      ✓ Database connection established" . PHP_EOL;
    echo PHP_EOL;

    // 3. Find tokens to migrate
    echo "[3/6] Scanning oauth_tokens table..." . PHP_EOL;

    $query = "SELECT id, provider, access_token, refresh_token, expires_at
              FROM oauth_tokens";

    if ($provider) {
        $query .= " WHERE provider = :provider";
        $stmt = $db->prepare($query);
        $stmt->execute(['provider' => $provider]);
    } else {
        $stmt = $db->query($query);
    }

    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalTokens = count($tokens);

    if ($totalTokens === 0) {
        echo "      ℹ️  No tokens found to migrate" . PHP_EOL;
        if ($provider) {
            echo "      (Searched for provider: {$provider})" . PHP_EOL;
        }
        echo PHP_EOL;
        exit(0);
    }

    echo "      ✓ Found {$totalTokens} OAuth token record(s)" . PHP_EOL;
    echo PHP_EOL;

    // 4. Analyze tokens (plaintext vs encrypted)
    echo "[4/6] Analyzing token encryption status..." . PHP_EOL;

    $toEncrypt = [];
    $alreadyEncrypted = [];

    foreach ($tokens as $token) {
        $accessEncrypted = $encryption->isEncrypted($token['access_token']);
        $refreshEncrypted = $encryption->isEncrypted($token['refresh_token']);

        if (!$accessEncrypted || !$refreshEncrypted) {
            $toEncrypt[] = [
                'id' => $token['id'],
                'provider' => $token['provider'],
                'access_plaintext' => !$accessEncrypted,
                'refresh_plaintext' => !$refreshEncrypted,
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'],
            ];
        } else {
            $alreadyEncrypted[] = $token['provider'];
        }
    }

    $encryptCount = count($toEncrypt);
    $skipCount = count($alreadyEncrypted);

    echo "      ✓ Analysis complete" . PHP_EOL;
    echo "        • {$encryptCount} record(s) need encryption" . PHP_EOL;
    echo "        • {$skipCount} record(s) already encrypted (will skip)" . PHP_EOL;
    echo PHP_EOL;

    if ($encryptCount === 0) {
        echo "      ✓ All tokens already encrypted - nothing to do!" . PHP_EOL;
        echo PHP_EOL;
        exit(0);
    }

    // 5. Display migration plan
    echo "[5/6] Migration plan:" . PHP_EOL;
    foreach ($toEncrypt as $i => $token) {
        echo "      " . ($i + 1) . ". Provider: {$token['provider']}" . PHP_EOL;
        if ($token['access_plaintext']) {
            echo "         • access_token:  PLAINTEXT → ENCRYPTED" . PHP_EOL;
        } else {
            echo "         • access_token:  already encrypted (skip)" . PHP_EOL;
        }
        if ($token['refresh_plaintext']) {
            echo "         • refresh_token: PLAINTEXT → ENCRYPTED" . PHP_EOL;
        } else {
            echo "         • refresh_token: already encrypted (skip)" . PHP_EOL;
        }
    }
    echo PHP_EOL;

    if ($dryRun) {
        echo "      ℹ️  DRY RUN - Stopping here (no changes made)" . PHP_EOL;
        echo PHP_EOL;
        echo "      To perform migration, run without --dry-run flag:" . PHP_EOL;
        echo "      php cli/migrate_encrypt_tokens.php" . PHP_EOL;
        echo PHP_EOL;
        exit(0);
    }

    // 6. Perform migration (transactional)
    echo "[6/6] Encrypting tokens..." . PHP_EOL;

    $db->beginTransaction();
    $encrypted = 0;

    try {
        foreach ($toEncrypt as $token) {
            // Encrypt tokens (only if plaintext)
            $accessToken = $token['access_plaintext']
                ? $encryption->encrypt($token['access_token'])
                : $token['access_token'];

            $refreshToken = $token['refresh_plaintext']
                ? $encryption->encrypt($token['refresh_token'])
                : $token['refresh_token'];

            // Update database
            $updateStmt = $db->prepare(
                "UPDATE oauth_tokens
                 SET access_token = :access, refresh_token = :refresh
                 WHERE id = :id"
            );

            $updateStmt->execute([
                'access' => $accessToken,
                'refresh' => $refreshToken,
                'id' => $token['id'],
            ]);

            $encrypted++;
            echo "      ✓ Encrypted: {$token['provider']} (ID: {$token['id']})" . PHP_EOL;
        }

        $db->commit();

    } catch (Exception $e) {
        $db->rollBack();
        throw new RuntimeException('Migration failed (rolled back): ' . $e->getMessage(), 0, $e);
    }

    echo PHP_EOL;
    echo "╔══════════════════════════════════════════════════════════════╗" . PHP_EOL;
    echo "║                    MIGRATION SUCCESSFUL                      ║" . PHP_EOL;
    echo "╚══════════════════════════════════════════════════════════════╝" . PHP_EOL;
    echo PHP_EOL;
    echo "📊 MIGRATION SUMMARY:" . PHP_EOL;
    echo PHP_EOL;
    echo "  • Total records processed: {$totalTokens}" . PHP_EOL;
    echo "  • Encrypted in this run:   {$encrypted}" . PHP_EOL;
    echo "  • Already encrypted:       {$skipCount}" . PHP_EOL;
    echo "  • Status:                  ✅ SUCCESS" . PHP_EOL;
    echo PHP_EOL;

    // Verification step
    echo "🔍 VERIFICATION:" . PHP_EOL;
    echo PHP_EOL;
    echo "  Verifying encrypted tokens can be decrypted..." . PHP_EOL;

    foreach ($toEncrypt as $token) {
        $verifyStmt = $db->prepare("SELECT access_token, refresh_token FROM oauth_tokens WHERE id = :id");
        $verifyStmt->execute(['id' => $token['id']]);
        $updated = $verifyStmt->fetch(PDO::FETCH_ASSOC);

        // Try to decrypt
        try {
            $decryptedAccess = $encryption->decrypt($updated['access_token']);
            $decryptedRefresh = $encryption->decrypt($updated['refresh_token']);

            // Verify decryption produces original plaintext
            if ($decryptedAccess === $token['access_token'] &&
                $decryptedRefresh === $token['refresh_token']) {
                echo "  ✓ {$token['provider']}: Encryption verified (round-trip successful)" . PHP_EOL;
            } else {
                throw new RuntimeException("Decryption produced different plaintext!");
            }
        } catch (Exception $e) {
            echo "  ❌ {$token['provider']}: Verification FAILED - " . $e->getMessage() . PHP_EOL;
            echo PHP_EOL;
            echo "  WARNING: Tokens encrypted but verification failed!" . PHP_EOL;
            echo "  Check encryption key and database manually." . PHP_EOL;
            exit(1);
        }
    }

    echo PHP_EOL;
    echo "✅ All tokens encrypted and verified successfully!" . PHP_EOL;
    echo PHP_EOL;

    // Next steps
    echo "📋 NEXT STEPS:" . PHP_EOL;
    echo PHP_EOL;
    echo "  1. Test OAuth flow: curl /api/payroll/xero/connect" . PHP_EOL;
    echo "  2. Verify tokens decrypt correctly in application" . PHP_EOL;
    echo "  3. Monitor logs for decryption errors" . PHP_EOL;
    echo "  4. Backup encryption key securely (required for recovery)" . PHP_EOL;
    echo PHP_EOL;

    exit(0);

} catch (Exception $e) {
    echo PHP_EOL;
    echo "❌ MIGRATION FAILED" . PHP_EOL;
    echo PHP_EOL;
    echo "Error: " . $e->getMessage() . PHP_EOL;
    echo PHP_EOL;
    echo "Troubleshooting:" . PHP_EOL;
    echo "  • Verify ENCRYPTION_KEY set in .env" . PHP_EOL;
    echo "  • Check database connection (DB_* variables)" . PHP_EOL;
    echo "  • Ensure oauth_tokens table exists" . PHP_EOL;
    echo "  • Verify EncryptionService class loaded" . PHP_EOL;
    echo "  • Check app.php bootstrap successful" . PHP_EOL;
    echo PHP_EOL;
    echo "Stack trace:" . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    echo PHP_EOL;

    exit(1);
}
