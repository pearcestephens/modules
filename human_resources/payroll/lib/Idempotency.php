<?php
/**
 * Payroll Module - Idempotency Helper
 *
 * Generates deterministic idempotency keys for preventing duplicate operations
 *
 * @package Payroll\Lib
 * @version 1.0.0
 * @created 2025-11-02
 */

declare(strict_types=1);

namespace Payroll\Lib;

final class Idempotency
{
    /**
     * Generate idempotency key from namespace and parameters
     *
     * Creates a deterministic SHA-256 hash from:
     * - Namespace (e.g., 'xero.apply', 'vend.payment')
     * - Parameters (sorted by key for consistency)
     *
     * Same inputs ALWAYS produce same key → prevents duplicate operations
     *
     * @param string $ns Namespace for the operation
     * @param array $parts Key-value parameters
     * @return string SHA-256 hash (64 hex chars)
     *
     * @example
     * $key = Idempotency::keyFor('xero.apply', [
     *     'run' => 'PR_2025_10_27',
     *     'emp' => 'E123',
     *     'cents' => 45000
     * ]);
     * // Always same key for same inputs
     */
    public static function keyFor(string $ns, array $parts): string
    {
        // Sort keys for deterministic output
        ksort($parts);

        // Create payload: namespace|JSON
        $payload = $ns . '|' . json_encode($parts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Generate SHA-256 hash
        return hash('sha256', $payload);
    }

    /**
     * Generate short idempotency key (16 chars)
     *
     * For use cases where 64-char SHA-256 is too long
     * Note: Higher collision risk than full SHA-256
     *
     * @param string $ns Namespace
     * @param array $parts Parameters
     * @return string First 16 chars of SHA-256
     */
    public static function shortKeyFor(string $ns, array $parts): string
    {
        return substr(self::keyFor($ns, $parts), 0, 16);
    }

    /**
     * Validate idempotency key format
     *
     * @param string $key Key to validate
     * @param bool $allowShort Allow 16-char short keys
     * @return bool True if valid format
     */
    public static function isValidKey(string $key, bool $allowShort = false): bool
    {
        $pattern = $allowShort ? '/^[a-f0-9]{16,64}$/' : '/^[a-f0-9]{64}$/';
        return preg_match($pattern, $key) === 1;
    }
}
