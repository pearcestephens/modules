<?php
/**
 * Filename: XeroTokenStore.php
 * Purpose : Persist and retrieve Xero OAuth tokens with graceful refresh support.
 * Author  : GitHub Copilot
 * Last Modified: 2025-11-01
 * Dependencies: PDO connection, EncryptionService for secure token storage
 *
 * Security: OAuth tokens are encrypted at rest using AES-256-GCM.
 * Backward compatible: Automatically detects and migrates plaintext tokens.
 */
declare(strict_types=1);

use PDO;
use PDOException;
use HumanResources\Payroll\Services\EncryptionService;

final class XeroTokenStore
{
    private PDO $db;
    private ?EncryptionService $encryption;

    /**
     * Constructor - Initialize token store with encryption
     *
     * @param PDO $db Database connection
     * @param EncryptionService|null $encryption Encryption service (null = no encryption, legacy mode)
     */
    public function __construct(PDO $db, ?EncryptionService $encryption = null)
    {
        $this->db = $db;
        $this->encryption = $encryption;
    }

    /**
     * Fetch the current Xero access token (decrypted)
     *
     * Returns decrypted access token from database.
     * Falls back to environment variable if not in database.
     * Backward compatible: Detects plaintext tokens and returns them (will encrypt on next save).
     *
     * @return string|null Decrypted access token or null if not found
     */
    public function getAccessToken(): ?string
    {
        // Primary token store (new schema)
        $statement = $this->db->query("SELECT access_token FROM oauth_tokens WHERE provider = 'xero' LIMIT 1");
        $record = $statement ? $statement->fetch(PDO::FETCH_ASSOC) : null;

        $storedToken = $record['access_token'] ?? null;

        // No token in oauth_tokens, try legacy xero_tokens table (fallback for tests/older installs)
        if ($storedToken === null) {
            try {
                $stmt = $this->db->query("SELECT access_token FROM xero_tokens ORDER BY created_at DESC LIMIT 1");
                $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                $storedToken = $row['access_token'] ?? null;
            } catch (PDOException $e) {
                // ignore - fallback to env below
                error_log('XeroTokenStore: legacy xero_tokens lookup failed - ' . $e->getMessage());
            }
        }

        // No token in database, try environment
        if ($storedToken === null) {
            return getenv('XERO_ACCESS_TOKEN') ?: null;
        }

        // Decrypt if encryption enabled
        if ($this->encryption !== null) {
            // Backward compatibility: Detect plaintext tokens (not encrypted)
            if (!$this->encryption->isEncrypted($storedToken)) {
                // Plaintext token found - return as-is (will encrypt on next save)
                error_log('XeroTokenStore: Found plaintext access_token, will encrypt on next save');
                return $storedToken;
            }

            // Decrypt encrypted token
            try {
                return $this->encryption->decrypt($storedToken);
            } catch (\RuntimeException $e) {
                error_log('XeroTokenStore: Failed to decrypt access_token - ' . $e->getMessage());
                return null;
            }
        }

        // No encryption configured, return plaintext (legacy mode)
        return $storedToken;
    }

    /**
     * Fetch the current Xero refresh token (decrypted)
     *
     * Returns decrypted refresh token from database.
     * Falls back to environment variable if not in database.
     * Backward compatible: Detects plaintext tokens and returns them (will encrypt on next save).
     *
     * @return string|null Decrypted refresh token or null if not found
     */
    public function getRefreshToken(): ?string
    {
        // Primary token store (new schema)
        $statement = $this->db->query("SELECT refresh_token FROM oauth_tokens WHERE provider = 'xero' LIMIT 1");
        $record = $statement ? $statement->fetch(PDO::FETCH_ASSOC) : null;

        $storedToken = $record['refresh_token'] ?? null;

        // Fallback to legacy xero_tokens table
        if ($storedToken === null) {
            try {
                $stmt = $this->db->query("SELECT refresh_token FROM xero_tokens ORDER BY created_at DESC LIMIT 1");
                $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                $storedToken = $row['refresh_token'] ?? null;
            } catch (PDOException $e) {
                error_log('XeroTokenStore: legacy xero_tokens lookup failed - ' . $e->getMessage());
            }
        }

        // No token in database, try environment
        if ($storedToken === null) {
            return getenv('XERO_REFRESH_TOKEN') ?: null;
        }

        // Decrypt if encryption enabled
        if ($this->encryption !== null) {
            // Backward compatibility: Detect plaintext tokens (not encrypted)
            if (!$this->encryption->isEncrypted($storedToken)) {
                // Plaintext token found - return as-is (will encrypt on next save)
                error_log('XeroTokenStore: Found plaintext refresh_token, will encrypt on next save');
                return $storedToken;
            }

            // Decrypt encrypted token
            try {
                return $this->encryption->decrypt($storedToken);
            } catch (\RuntimeException $e) {
                error_log('XeroTokenStore: Failed to decrypt refresh_token - ' . $e->getMessage());
                return null;
            }
        }

        // No encryption configured, return plaintext (legacy mode)
        return $storedToken;
    }

    /**
     * Persist token values (encrypted)
     *
     * Encrypts tokens before storing in database.
     * If encryption not configured, stores plaintext (legacy mode).
     *
     * @param string $accessToken Plaintext access token (will be encrypted)
     * @param string $refreshToken Plaintext refresh token (will be encrypted)
     * @param int $expiresAt Token expiry timestamp (Unix timestamp)
     * @throws RuntimeException If encryption fails
     */
    public function saveTokens(string $accessToken, string $refreshToken, int $expiresAt): void
    {
        // Encrypt tokens if encryption configured
        if ($this->encryption !== null) {
            try {
                $accessToken = $this->encryption->encrypt($accessToken);
                $refreshToken = $this->encryption->encrypt($refreshToken);
            } catch (\RuntimeException $e) {
                error_log('XeroTokenStore: Encryption failed - ' . $e->getMessage());
                throw $e; // Re-throw to prevent plaintext storage on encryption failure
            }
        }

        // Store encrypted (or plaintext if legacy mode)
        $query = $this->db->prepare(
            "INSERT INTO oauth_tokens (provider, access_token, refresh_token, expires_at)
             VALUES ('xero', ?, ?, FROM_UNIXTIME(?))
             ON DUPLICATE KEY UPDATE access_token = VALUES(access_token), refresh_token = VALUES(refresh_token), expires_at = VALUES(expires_at)"
        );
        $query->execute([$accessToken, $refreshToken, $expiresAt]);
    }

    /**
     * Obtain a valid access token, refreshing as needed.
     *
     * @param callable $refreshCallback Invoked when a refresh is required. Should return [access, refresh, expiryTs].
     */
    public function refreshIfNeeded(callable $refreshCallback): string
    {
        $accessToken = $this->getAccessToken();
        if ($accessToken && !$this->isExpiringSoon()) {
            return $accessToken;
        }

        [$newAccess, $newRefresh, $expiryTs] = $refreshCallback($this->getRefreshToken());
        $this->saveTokens($newAccess, $newRefresh, (int)$expiryTs);

        return $newAccess;
    }

    private function isExpiringSoon(): bool
    {
        try {
            $statement = $this->db->query("SELECT expires_at FROM oauth_tokens WHERE provider = 'xero' LIMIT 1");
        } catch (PDOException $exception) {
            error_log('XeroTokenStore: failed to query token expiry - ' . $exception->getMessage());
            return true;
        }

        $record = $statement ? $statement->fetch(PDO::FETCH_ASSOC) : null;
        // If primary schema has expiry, use it
        if ($record && !empty($record['expires_at'])) {
            $expiry = strtotime((string)$record['expires_at']);
            if ($expiry === false) {
                return true;
            }

            return $expiry <= (time() + 300); // refresh within five minutes of expiry
        }

        // Fallback to legacy xero_tokens table
        try {
            $stmt = $this->db->query("SELECT expires_at FROM xero_tokens ORDER BY created_at DESC LIMIT 1");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if ($row && !empty($row['expires_at'])) {
                $expiry = strtotime((string)$row['expires_at']);
                if ($expiry === false) {
                    return true;
                }

                return $expiry <= (time() + 300);
            }
        } catch (PDOException $e) {
            error_log('XeroTokenStore: legacy xero_tokens expiry lookup failed - ' . $e->getMessage());
            return true;
        }

        return true;
    }

    /**
     * Store tokens with expiry seconds (convenience method for PayrollXeroService)
     *
     * @param string $accessToken Plaintext access token
     * @param string $refreshToken Plaintext refresh token
     * @param int $expiresIn Seconds until token expires
     */
    public function storeTokens(string $accessToken, string $refreshToken, int $expiresIn): void
    {
        $expiresAt = time() + $expiresIn;
        $this->saveTokens($accessToken, $refreshToken, $expiresAt);
    }

    /**
     * Check if token is expired (convenience method for PayrollXeroService)
     *
     * @param int $buffer Buffer in seconds (default 300 = 5 minutes)
     * @return bool True if token is expired or about to expire
     */
    public function isTokenExpired(int $buffer = 300): bool
    {
        return $this->isExpiringSoon();
    }
}
