<?php
declare(strict_types=1);

namespace Modules\Shared;

final class Helpers
{
    public static function url(string $path): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = '/modules/consignments';
        $path   = '/' . ltrim($path, '/');
        return $scheme . '://' . $host . $base . $path;
    }

    public static function csrfToken(): string
    {
        if (!isset($_SESSION)) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf_token'];
    }

    public static function csrfTokenInput(string $name = 'csrf'): string
    {
        $token = htmlspecialchars(self::csrfToken(), ENT_QUOTES, 'UTF-8');
        $name  = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="' . $name . '" value="' . $token . '">';
    }
}
