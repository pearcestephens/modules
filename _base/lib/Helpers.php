<?php
declare(strict_types=1);

namespace Modules\Base;

final class Helpers
{
    private static ?string $moduleBase = null;

    /**
     * Set the module base path (e.g., '/modules/consignments')
     */
    public static function setModuleBase(string $base): void
    {
        self::$moduleBase = '/' . ltrim($base, '/');
    }

    /**
     * Auto-detect module base from current URI
     */
    private static function detectModuleBase(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        // e.g. /modules/consignments/..., return "/modules/consignments"
        if (preg_match('#^/modules/[^/]+#', $uri, $m)) {
            return $m[0];
        }
        return ''; // site root
    }

    /**
     * Generate a full URL for a path within the current module
     * 
     * @param string $path Path relative to module base
     * @param string|null $moduleBase Override module base
     * @return string Full URL
     */
    public static function url(string $path, ?string $moduleBase = null): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = $moduleBase ?? self::$moduleBase ?? self::detectModuleBase();
        $path   = '/' . ltrim($path, '/');
        return "{$scheme}://{$host}{$base}{$path}";
    }

    /**
     * Get or generate CSRF token for current session
     */
    public static function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf_token'];
    }

    /**
     * Generate hidden input for CSRF token
     */
    public static function csrfTokenInput(string $name = 'csrf'): string
    {
        $token = htmlspecialchars(self::csrfToken(), ENT_QUOTES, 'UTF-8');
        $name  = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="' . $name . '" value="' . $token . '">';
    }

    /**
     * Validate CSRF token from request
     */
    public static function verifyCsrf(string $token): bool
    {
        return hash_equals(self::csrfToken(), $token);
    }
}

