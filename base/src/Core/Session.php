<?php

/**
 * Session Service - Secure Session Management.
 *
 * @version 2.0.0
 */

declare(strict_types=1);

namespace CIS\Base\Core;

use const PHP_SESSION_ACTIVE;

class Session
{
    private Application $app;

    private array $config;

    private bool $started = false;

    /**
     * Create session instance.
     */
    public function __construct(Application $app)
    {
        $this->app    = $app;
        $this->config = $app->config('session', []);
    }

    /**
     * Start session.
     */
    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Configure session
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', (string) (int) ($_SERVER['HTTPS'] ?? false));
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_samesite', 'Lax');

        $lifetime = $this->config['lifetime'] ?? 7200;
        ini_set('session.gc_maxlifetime', (string) $lifetime);

        session_name($this->config['name'] ?? 'CIS_SESSION');
        session_start();

        $this->started = true;

        // Initialize flash storage
        if (!isset($_SESSION['flash_messages'])) {
            $_SESSION['flash_messages'] = [];
        }

        // Generate CSRF token if not exists
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    /**
     * Get session value.
     *
     * @param mixed|null $default
     */
    public function get(string $key, $default = null)
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set session value.
     */
    public function set(string $key, $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    /**
     * Check if key exists.
     */
    public function has(string $key): bool
    {
        $this->start();

        return isset($_SESSION[$key]);
    }

    /**
     * Remove session value.
     */
    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    /**
     * Flash message for next request.
     */
    public function flash(string $type, string $message): void
    {
        $this->start();

        if (!isset($_SESSION['flash_messages'][$type])) {
            $_SESSION['flash_messages'][$type] = [];
        }

        $_SESSION['flash_messages'][$type][] = $message;
    }

    /**
     * Get and clear flash messages.
     */
    public function getFlash(): array
    {
        $this->start();

        $messages                   = $_SESSION['flash_messages'] ?? [];
        $_SESSION['flash_messages'] = [];

        return $messages;
    }

    /**
     * Store old input for next request.
     */
    public function flashInput(array $input): void
    {
        $this->start();
        $_SESSION['old_input'] = $input;
    }

    /**
     * Get old input value.
     *
     * @param mixed|null $default
     */
    public function getOldInput(string $key, $default = null)
    {
        $this->start();
        $value = $_SESSION['old_input'][$key] ?? $default;

        // Clear old input after retrieval
        if (isset($_SESSION['old_input'])) {
            unset($_SESSION['old_input']);
        }

        return $value;
    }

    /**
     * Regenerate session ID.
     */
    public function regenerate(bool $deleteOld = true): void
    {
        $this->start();
        session_regenerate_id($deleteOld);
    }

    /**
     * Destroy session.
     */
    public function destroy(): void
    {
        $this->start();
        session_unset();
        session_destroy();
        $this->started = false;
    }

    /**
     * Get CSRF token.
     */
    public function getCsrfToken(): string
    {
        $this->start();

        return $_SESSION['csrf_token'] ?? '';
    }

    /**
     * Verify CSRF token.
     */
    public function verifyCsrfToken(string $token): bool
    {
        $this->start();

        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    /**
     * Get all session data.
     */
    public function all(): array
    {
        $this->start();

        return $_SESSION;
    }

    /**
     * Clear all session data.
     */
    public function clear(): void
    {
        $this->start();
        $_SESSION = [];
    }
}
