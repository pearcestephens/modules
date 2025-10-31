<?php
/**
 * Configuration Helpers
 *
 * Thin wrappers for retrieving application configuration values from the
 * `configuration` table. All access is cached in-memory per request to avoid
 * duplicate SELECT statements.
 *
 * @package CIS\Shared
 */

declare(strict_types=1);

// NOTE: No `use PDO` / `use RuntimeException` here; these are global classes
// and importing them in a non-namespaced file is a no-op (and causes warnings).

if (!function_exists('cis_config_get')) {
    /**
     * Retrieve a configuration value by label.
     *
     * @param string $label    Configuration label/key to load.
     * @param bool   $required Whether an exception should be thrown when the value is missing.
     *
     * @throws \RuntimeException when the database connection cannot be resolved or the
     *                           requested key is required but missing.
     *
     * @return string|null The configuration value (trimmed) or null when not found.
     */
    function cis_config_get(string $label, bool $required = true): ?string
    {
        static $cache = [];

        $label = trim($label);
        if ($label === '') {
            throw new \RuntimeException('Configuration label must not be empty.');
        }

        if (array_key_exists($label, $cache)) {
            return $cache[$label];
        }

        $pdo = cis_resolve_pdo();
        $stmt = $pdo->prepare('SELECT config_value FROM configuration WHERE config_label = ? LIMIT 1');
        $stmt->execute([$label]);
        $value = $stmt->fetchColumn();

        if ($value === false || $value === null) {
            if ($required) {
                throw new \RuntimeException("Configuration value '{$label}' is not configured.");
            }
            $cache[$label] = null;
            return null;
        }

        $cache[$label] = trim((string) $value);
        return $cache[$label];
    }
}

if (!function_exists('cis_vend_access_token')) {
    /**
     * Convenience helper to retrieve the Vend/X-Series access token.
     */
    function cis_vend_access_token(bool $required = true): ?string
    {
        return cis_config_get('vend_access_token', $required);
    }
}

if (!function_exists('cis_require_login')) {
    /**
     * Require user to be logged in - redirect to login if not
     * Simple authentication check using $_SESSION['userID']
     */
    function cis_require_login(): void
    {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is logged in (CIS uses capital ID)
        if (!isset($_SESSION['userID']) || empty($_SESSION['userID'])) {
            // Redirect to login page
            $login_url = defined('BASE_URL') ? BASE_URL . '/login.php' : '/login.php';
            header('Location: ' . $login_url);
            exit;
        }
    }
}

if (!function_exists('cis_resolve_pdo')) {
    /**
     * Resolve an active PDO connection from the current runtime.
     *
     * @throws \RuntimeException when a PDO instance cannot be located.
     */
    function cis_resolve_pdo(): \PDO
    {
        static $resolved;
        if ($resolved instanceof \PDO) {
            return $resolved;
        }

        // 1) Function db() returning PDO or wrapper
        if (function_exists('db')) {
            $candidate = db();
            if ($candidate instanceof \PDO) {
                return $resolved = $candidate;
            }
            if (is_object($candidate)) {
                foreach (['pdo', 'getPdo', 'getPDO', 'getConnection', 'connection'] as $method) {
                    if (method_exists($candidate, $method)) {
                        $conn = $candidate->{$method}();
                        if ($conn instanceof \PDO) {
                            return $resolved = $conn;
                        }
                    }
                }
                foreach (['pdo', 'connection'] as $prop) {
                    if (isset($candidate->{$prop}) && $candidate->{$prop} instanceof \PDO) {
                        return $resolved = $candidate->{$prop};
                    }
                }
            }
        }

        // 2) Global $pdo pattern
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof \PDO) {
            return $resolved = $GLOBALS['pdo'];
        }

        throw new \RuntimeException('Unable to resolve PDO connection for configuration lookup.');
    }
}
