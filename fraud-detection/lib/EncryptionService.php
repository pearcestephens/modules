<?php

namespace FraudDetection\Lib;

/**
 * Encryption Service for Fraud Detection System
 *
 * Provides AES-256-GCM encryption for sensitive data including:
 * - Communication evidence
 * - Camera stream URLs with credentials
 * - Personal behavioral data
 * - Investigation notes
 *
 * Uses envelope encryption:
 * - Data Encryption Keys (DEK) for each record
 * - Master Encryption Key (MEK) stored in secure vault/environment
 *
 * @version 1.0.0
 * @author Fraud Detection Team
 */
class EncryptionService
{
    private const CIPHER_METHOD = 'aes-256-gcm';
    private const KEY_LENGTH = 32; // 256 bits
    private const IV_LENGTH = 12;  // 96 bits for GCM
    private const TAG_LENGTH = 16; // 128 bits authentication tag

    private string $masterKey;
    private bool $encryptionEnabled;

    /**
     * Initialize encryption service
     *
     * @param string|null $masterKey Optional master key (if not in env)
     * @throws \RuntimeException If encryption cannot be initialized
     */
    public function __construct(?string $masterKey = null)
    {
        // Check if OpenSSL extension is available
        if (!extension_loaded('openssl')) {
            throw new \RuntimeException('OpenSSL extension is required for encryption');
        }

        // Verify GCM mode is available
        if (!in_array(self::CIPHER_METHOD, openssl_get_cipher_methods())) {
            throw new \RuntimeException('AES-256-GCM cipher is not available');
        }

        // Load master key from environment or parameter
        $this->masterKey = $masterKey ?? ($_ENV['FRAUD_ENCRYPTION_KEY'] ?? '');

        if (empty($this->masterKey)) {
            // Check if encryption is explicitly disabled
            $this->encryptionEnabled = false;
            error_log('WARNING: Fraud Detection encryption key not set. Encryption disabled.');
        } else {
            $this->encryptionEnabled = true;

            // Validate key length
            if (strlen($this->masterKey) !== self::KEY_LENGTH) {
                throw new \RuntimeException('Master encryption key must be exactly 32 bytes (256 bits)');
            }
        }
    }

    /**
     * Encrypt data with envelope encryption
     *
     * Returns array with:
     * - encrypted_data: Base64 encoded ciphertext
     * - iv: Base64 encoded initialization vector
     * - tag: Base64 encoded authentication tag
     * - encrypted_dek: Base64 encoded data encryption key (encrypted with MEK)
     * - key_version: Version identifier for key rotation
     *
     * @param string $plaintext Data to encrypt
     * @param array $additionalData Optional authenticated additional data (AAD)
     * @return array Encrypted data bundle
     * @throws \RuntimeException If encryption fails
     */
    public function encrypt(string $plaintext, array $additionalData = []): array
    {
        if (!$this->encryptionEnabled) {
            throw new \RuntimeException('Encryption is disabled. Set FRAUD_ENCRYPTION_KEY environment variable.');
        }

        try {
            // Generate random Data Encryption Key (DEK)
            $dek = random_bytes(self::KEY_LENGTH);

            // Generate random IV (never reuse!)
            $iv = random_bytes(self::IV_LENGTH);

            // Prepare Additional Authenticated Data (AAD)
            $aad = !empty($additionalData) ? json_encode($additionalData) : '';

            // Encrypt plaintext with DEK
            $tag = '';
            $ciphertext = openssl_encrypt(
                $plaintext,
                self::CIPHER_METHOD,
                $dek,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                $aad,
                self::TAG_LENGTH
            );

            if ($ciphertext === false) {
                throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
            }

            // Encrypt DEK with Master Key
            $dekIv = random_bytes(self::IV_LENGTH);
            $dekTag = '';
            $encryptedDek = openssl_encrypt(
                $dek,
                self::CIPHER_METHOD,
                $this->masterKey,
                OPENSSL_RAW_DATA,
                $dekIv,
                $dekTag,
                '',
                self::TAG_LENGTH
            );

            if ($encryptedDek === false) {
                throw new \RuntimeException('DEK encryption failed: ' . openssl_error_string());
            }

            // Return encrypted bundle
            return [
                'encrypted_data' => base64_encode($ciphertext),
                'iv' => base64_encode($iv),
                'tag' => base64_encode($tag),
                'encrypted_dek' => base64_encode($encryptedDek),
                'dek_iv' => base64_encode($dekIv),
                'dek_tag' => base64_encode($dekTag),
                'key_version' => $this->getKeyVersion(),
                'cipher_method' => self::CIPHER_METHOD,
                'encrypted_at' => time()
            ];

        } catch (\Exception $e) {
            error_log('Encryption error: ' . $e->getMessage());
            throw new \RuntimeException('Encryption failed: ' . $e->getMessage());
        } finally {
            // Clear sensitive data from memory
            if (isset($dek)) {
                sodium_memzero($dek);
            }
        }
    }

    /**
     * Decrypt data with envelope encryption
     *
     * @param array $encryptedBundle Bundle from encrypt() method
     * @param array $additionalData Same AAD used during encryption
     * @return string Decrypted plaintext
     * @throws \RuntimeException If decryption fails
     */
    public function decrypt(array $encryptedBundle, array $additionalData = []): string
    {
        if (!$this->encryptionEnabled) {
            throw new \RuntimeException('Encryption is disabled. Cannot decrypt.');
        }

        try {
            // Validate required fields
            $required = ['encrypted_data', 'iv', 'tag', 'encrypted_dek', 'dek_iv', 'dek_tag'];
            foreach ($required as $field) {
                if (!isset($encryptedBundle[$field])) {
                    throw new \RuntimeException("Missing required field: {$field}");
                }
            }

            // Decode Base64 values
            $ciphertext = base64_decode($encryptedBundle['encrypted_data']);
            $iv = base64_decode($encryptedBundle['iv']);
            $tag = base64_decode($encryptedBundle['tag']);
            $encryptedDek = base64_decode($encryptedBundle['encrypted_dek']);
            $dekIv = base64_decode($encryptedBundle['dek_iv']);
            $dekTag = base64_decode($encryptedBundle['dek_tag']);

            // Decrypt DEK with Master Key
            $dek = openssl_decrypt(
                $encryptedDek,
                self::CIPHER_METHOD,
                $this->masterKey,
                OPENSSL_RAW_DATA,
                $dekIv,
                $dekTag,
                ''
            );

            if ($dek === false) {
                throw new \RuntimeException('DEK decryption failed. Invalid master key or corrupted data.');
            }

            // Prepare AAD
            $aad = !empty($additionalData) ? json_encode($additionalData) : '';

            // Decrypt ciphertext with DEK
            $plaintext = openssl_decrypt(
                $ciphertext,
                self::CIPHER_METHOD,
                $dek,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                $aad
            );

            if ($plaintext === false) {
                throw new \RuntimeException('Decryption failed. Data may be corrupted or tampered.');
            }

            return $plaintext;

        } catch (\Exception $e) {
            error_log('Decryption error: ' . $e->getMessage());
            throw new \RuntimeException('Decryption failed: ' . $e->getMessage());
        } finally {
            // Clear sensitive data from memory
            if (isset($dek)) {
                sodium_memzero($dek);
            }
        }
    }

    /**
     * Encrypt a camera stream URL with credentials
     *
     * @param string $streamUrl Full RTSP/HTTP URL with credentials
     * @param array $metadata Optional camera metadata (camera_id, outlet_id)
     * @return array Encrypted bundle
     */
    public function encryptCameraUrl(string $streamUrl, array $metadata = []): array
    {
        return $this->encrypt($streamUrl, $metadata);
    }

    /**
     * Decrypt a camera stream URL
     *
     * @param array $encryptedBundle Encrypted bundle from database
     * @param array $metadata Same metadata used during encryption
     * @return string Decrypted stream URL
     */
    public function decryptCameraUrl(array $encryptedBundle, array $metadata = []): string
    {
        return $this->decrypt($encryptedBundle, $metadata);
    }

    /**
     * Encrypt communication evidence
     *
     * @param string $messageContent Message text or data
     * @param array $metadata Message metadata (staff_id, customer_id, timestamp)
     * @return array Encrypted bundle
     */
    public function encryptEvidence(string $messageContent, array $metadata = []): array
    {
        return $this->encrypt($messageContent, $metadata);
    }

    /**
     * Decrypt communication evidence
     *
     * @param array $encryptedBundle Encrypted bundle from database
     * @param array $metadata Same metadata used during encryption
     * @return string Decrypted message content
     */
    public function decryptEvidence(array $encryptedBundle, array $metadata = []): string
    {
        return $this->decrypt($encryptedBundle, $metadata);
    }

    /**
     * Generate a new master encryption key
     *
     * @return string Base64 encoded 256-bit key
     */
    public static function generateMasterKey(): string
    {
        return base64_encode(random_bytes(self::KEY_LENGTH));
    }

    /**
     * Get current key version identifier
     *
     * @return string Key version (for rotation tracking)
     */
    private function getKeyVersion(): string
    {
        // Use first 8 characters of key hash as version
        return substr(hash('sha256', $this->masterKey), 0, 8);
    }

    /**
     * Check if encryption is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->encryptionEnabled;
    }

    /**
     * Securely compare two strings (timing-attack safe)
     *
     * @param string $known Known string
     * @param string $user User-provided string
     * @return bool
     */
    public static function secureCompare(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }

    /**
     * Hash sensitive data for searchable encryption
     *
     * Allows searching encrypted data without decrypting
     * Uses HMAC-SHA256 for deterministic hashing
     *
     * @param string $data Data to hash
     * @param string $salt Optional salt
     * @return string Base64 encoded hash
     */
    public function hashForSearch(string $data, string $salt = ''): string
    {
        if (!$this->encryptionEnabled) {
            return hash('sha256', $data . $salt);
        }

        $hmac = hash_hmac('sha256', $data . $salt, $this->masterKey, true);
        return base64_encode($hmac);
    }
}
