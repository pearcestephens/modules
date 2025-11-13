<?php
declare(strict_types=1);

namespace CIS\Base\Support;

/**
 * SessionGuard - centralized safe session handling.
 * Prevents warnings in CLI or when headers already sent.
 */
class SessionGuard
{
    /**
     * Ensure a session is started safely.
     * @param bool $force Force start even if headers sent (discouraged)
     */
    public static function ensureStarted(bool $force = false): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;

        // If headers already sent and not forcing, skip to avoid warnings
        if (headers_sent() && !$force) {
            return; // In test environments banners may have flushed output
        }

        // CLI environment: still allow starting session for tests but suppress warnings
        if (PHP_SAPI === 'cli') {
            @session_start();
            return;
        }

        @session_start();
    }

    /**
     * Destroy session safely (ignore errors)
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_unset();
            @session_destroy();
        }
    }
}
