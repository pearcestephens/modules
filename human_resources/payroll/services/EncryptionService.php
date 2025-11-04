<?php

declare(strict_types=1);

namespace PayrollModule\Services;

use RuntimeException;

/**
 * EncryptionService - AES-256-GCM encryption for sensitive data
 *
 * Provides authenticated encryption for OAuth tokens and other credentials.
 * Uses AES-256-GCM (Galois/Counter Mode) for tamper-proof encryption.
 *
 * Features:
 * - AES-256-GCM (authenticated encryption)
 * - Random IV per encryption (prevents pattern analysis)
 * - Base64 encoding for database storage
 * - Fail-fast on missing/invalid key
 * - Tamper detection via GCM authentication tag
 *
 * Usage:
 *   $service = new EncryptionService(getenv('ENCRYPTION_KEY'));
 *   $encrypted = $service->encrypt('sensitive data');
 *   $plaintext = $service->decrypt($encrypted);
 *
 * Environment Variable:
 *   ENCRYPTION_KEY - Base64-encoded 32-byte key (generate with: openssl rand -base64 32)
 *
 * Security:
 * - Key MUST be 32 bytes (256 bits) after base64 decode
 * - Key stored in environment, never hard-coded
 * - IV randomized per encryption (12 bytes for GCM)
 * - GCM tag verifies authenticity (16 bytes)
 *
 * @package HumanResources\Payroll\Services
 * @version 1.0.0
 * @since 2025-11-01
 */
final class EncryptionService
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;  // 96 bits - NIST recommendation for GCM
    private const TAG_LENGTH = 16; // 128 bits - authentication tag
    private const KEY_LENGTH = 32; // 256 bits - AES-256

    private string $key;

    /**
     * Constructor - Initialize encryption service with key
     *
     * @param string $keyBase64 Base64-encoded 32-byte encryption key
     * @throws RuntimeException If key missing, invalid, or wrong size
     */
    public function __construct(string $keyBase64)
    {
        if (empty($keyBase64)) {
            throw new RuntimeException(
                'Encryption key missing. Set ENCRYPTION_KEY environment variable. ' .
                'Generate with: openssl rand -base64 32'
            );
        }

        $key = base64_decode($keyBase64, true);

        if ($key === false) {
            throw new RuntimeException('Encryption key is not valid base64');
        }

        if (strlen($key) !== self::KEY_LENGTH) {
            throw new RuntimeException(
                sprintf(
                    'Encryption key must be %d bytes after base64 decode. Got %d bytes. ' .
                    'Generate with: openssl rand -base64 32',
                    self::KEY_LENGTH,
                    strlen($key)
                )
            );
        }

        $this->key = $key;
    }

    /**
     * Encrypt plaintext using AES-256-GCM
     *
     * Encrypts data with authenticated encryption (tamper-proof).
     * Each encryption uses a random IV (no IV reuse).
     *
     * Output format: Base64( IV || Ciphertext || Tag )
     *                        12 bytes + variable + 16 bytes
     *
     * @param string $plaintext Data to encrypt (OAuth tokens, credentials, etc.)
     * @return string Base64-encoded encrypted data (safe for VARCHAR storage)
     * @throws RuntimeException If encryption fails
     */
    public function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            throw new RuntimeException('Cannot encrypt empty string');
        }

        // Generate random IV (12 bytes for GCM)
        $iv = random_bytes(self::IV_LENGTH);

        // Encrypt with GCM (produces ciphertext + authentication tag)
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '', // No additional authenticated data (AAD)
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        // Format: IV || Ciphertext || Tag (all base64 encoded)
        $encrypted = $iv . $ciphertext . $tag;

        return base64_encode($encrypted);
    }

    /**
     * Decrypt ciphertext using AES-256-GCM
     *
     * Decrypts data and verifies authentication tag.
     * Fails if data tampered or corrupted.
     *
     * @param string $ciphertext Base64-encoded encrypted data (from encrypt())
     * @return string Original plaintext
     * @throws RuntimeException If decryption fails (corrupted, tampered, wrong key)
     */
    public function decrypt(string $ciphertext): string
    {
        if ($ciphertext === '') {
            throw new RuntimeException('Cannot decrypt empty string');
        }

        // Decode from base64
        $encrypted = base64_decode($ciphertext, true);

        if ($encrypted === false) {
            throw new RuntimeException('Decryption failed: Invalid base64 encoding');
        }

        // Minimum size: IV (12) + Tag (16) = 28 bytes (plus at least 1 byte ciphertext)
        if (strlen($encrypted) < self::IV_LENGTH + self::TAG_LENGTH + 1) {
            throw new RuntimeException('Decryption failed: Ciphertext too short (corrupted data)');
        }

        // Extract components: IV || Ciphertext || Tag
        $iv = substr($encrypted, 0, self::IV_LENGTH);
        $tag = substr($encrypted, -self::TAG_LENGTH);
        $ciphertextRaw = substr($encrypted, self::IV_LENGTH, -self::TAG_LENGTH);

        // Decrypt and verify authentication tag
        $plaintext = openssl_decrypt(
            $ciphertextRaw,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException(
                'Decryption failed: Invalid key, corrupted data, or tampered ciphertext. ' .
                'OpenSSL error: ' . openssl_error_string()
            );
        }

        return $plaintext;
    }

    /**
     * Check if data appears to be encrypted (vs plaintext)
     *
     * Heuristic check - not cryptographically secure, but useful for migration.
     *
     * @param string|null $data Data to check
     * @return bool True if data appears encrypted, false if plaintext/null
     */
    public function isEncrypted(?string $data): bool
    {
        if ($data === null || $data === '') {
            return false;
        }

        // Encrypted data is always base64
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return false; // Not base64 = plaintext
        }

        // Encrypted data has minimum size: IV (12) + Tag (16) + Ciphertext (1+)
        if (strlen($decoded) < self::IV_LENGTH + self::TAG_LENGTH + 1) {
            return false; // Too short = plaintext
        }

        // Looks encrypted (could still be random base64, but low probability)
        return true;
    }

    /**
     * Get cipher algorithm used
     *
     * @return string Cipher name (aes-256-gcm)
     */
    public function getCipher(): string
    {
        return self::CIPHER;
    }

    /**
     * Get IV length used
     *
     * @return int IV length in bytes (12)
     */
    public function getIvLength(): int
    {
        return self::IV_LENGTH;
    }

    /**
     * Get authentication tag length used
     *
     * @return int Tag length in bytes (16)
     */
    public function getTagLength(): int
    {
        return self::TAG_LENGTH;
    }

    /**
     * Validate encryption key format
     *
     * Static method to validate key before creating service instance.
     *
     * @param string $keyBase64 Base64-encoded key to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidKey(string $keyBase64): bool
    {
        if (empty($keyBase64)) {
            return false;
        }

        $key = base64_decode($keyBase64, true);

        if ($key === false) {
            return false; // Not valid base64
        }

        return strlen($key) === self::KEY_LENGTH;
    }

    /**
     * Generate a new encryption key
     *
     * Generates a cryptographically secure 32-byte key.
     * Returns base64-encoded key ready for ENCRYPTION_KEY environment variable.
     *
     * @return string Base64-encoded 32-byte key
     * @throws RuntimeException If random_bytes fails
     */
    public static function generateKey(): string
    {
        try {
            $key = random_bytes(self::KEY_LENGTH);
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to generate random key: ' . $e->getMessage());
        }

        return base64_encode($key);
    }
}
