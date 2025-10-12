<?php
declare(strict_types=1);

namespace Transfers\Lib;

final class Security
{
    public static function csrfToken(): string
    {
        if (!isset($_SESSION)) session_start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function csrfTokenInput(): string
    {
        return '<input type="hidden" name="csrf" value="'.htmlspecialchars(self::csrfToken()).'">';
    }

    public static function assertCsrf(string $token): void
    {
        if (!isset($_SESSION)) session_start();
        if (empty($token) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
            http_response_code(403);
            throw new \RuntimeException('Invalid CSRF token.');
        }
    }

    public static function currentUserId(): int
    {
        if (!isset($_SESSION)) session_start();
        return isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : 0;
    }

    public static function clientFingerprint(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return hash('sha256', $ip.'|'.$ua);
    }
}
