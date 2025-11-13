<?php
/**
 * Centralized Configuration Manager
 *
 * SINGLE SOURCE OF TRUTH for all credentials and configuration.
 * Loads .env once, validates required vars, provides safe access.
 *
 * Usage:
 *   $config = Base\Config::getInstance();
 *   $dbPassword = $config->get('DB_PASSWORD');
 *   $dbHost = $config->get('DB_HOST', 'localhost'); // with default
 *
 * Security:
 *   - Loads .env from OUTSIDE public_html
 *   - Validates required environment variables on init
 *   - Throws exceptions for missing critical config
 *   - Singleton pattern ensures ONE instance
 *   - No password hardcoding anywhere
 *
 * @package Base
 * @version 1.0.0
 * @created 2025-11-06
 */

namespace CIS\Base;

class Config
{
    private static $instance = null;
    private $config = [];
    private $loaded = false;

    /**
     * Required environment variables (will throw exception if missing)
     */
    private const REQUIRED_VARS = [
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',
    ];

    /**
     * Private constructor (singleton pattern)
     */
    private function __construct()
    {
        $this->loadEnvironment();
        $this->validateRequired();
    }

    /**
     * Get singleton instance
     *
     * @return Config
     */
    public static function getInstance(): Config
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load .env file from secure location (outside public_html)
     *
     * @throws \RuntimeException If .env file not found
     */
    private function loadEnvironment(): void
    {
        if ($this->loaded) {
            return;
        }

        // Try multiple possible .env locations (in priority order)
        $possiblePaths = [
            // Primary: Two levels up from modules/ (outside public_html)
            __DIR__ . '/../../.env',

            // Secondary: Three levels up from modules/ (if in subdirectory)
            __DIR__ . '/../../../.env',

            // Legacy: In modules/ directory (INSECURE - will warn)
            __DIR__ . '/../.env',
        ];

        $envPath = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $envPath = $path;

                // Warn if using insecure location
                if (strpos(realpath($path), 'public_html') !== false) {
                    error_log('WARNING: .env file is inside public_html - SECURITY RISK! Move to: ' . dirname($possiblePaths[0]));
                }
                break;
            }
        }

        if ($envPath === null) {
            throw new \RuntimeException(
                '.env file not found. Expected locations: ' . implode(', ', $possiblePaths) .
                "\nCreate .env file with required variables: " . implode(', ', self::REQUIRED_VARS)
            );
        }

        // Parse .env file
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments and empty lines
            if (strpos(trim($line), '#') === 0 || empty(trim($line))) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }

                // Store in config array
                $this->config[$key] = $value;

                // Also set in $_ENV for compatibility
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }

        $this->loaded = true;
    }

    /**
     * Validate that all required environment variables are set
     *
     * @throws \RuntimeException If required variable is missing
     */
    private function validateRequired(): void
    {
        $missing = [];

        foreach (self::REQUIRED_VARS as $var) {
            if (!isset($this->config[$var]) || empty($this->config[$var])) {
                $missing[] = $var;
            }
        }

        if (!empty($missing)) {
            throw new \RuntimeException(
                'Required environment variables are missing: ' . implode(', ', $missing) .
                "\nAdd these to your .env file."
            );
        }
    }

    /**
     * Get configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value or default
     * @throws \RuntimeException If key not found and no default provided
     */
    public function get(string $key, $default = null)
    {
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }

        if ($default !== null) {
            return $default;
        }

        throw new \RuntimeException("Configuration key '$key' not found and no default provided");
    }

    /**
     * Get database configuration array
     *
     * @return array Database connection config
     */
    public function getDatabase(): array
    {
        return [
            'host' => $this->get('DB_HOST'),
            'name' => $this->get('DB_NAME'),
            'user' => $this->get('DB_USER'),
            'password' => $this->get('DB_PASSWORD'),
            'port' => $this->get('DB_PORT', '3306'),
            'charset' => $this->get('DB_CHARSET', 'utf8mb4'),
        ];
    }

    /**
     * Check if configuration key exists
     *
     * @param string $key Configuration key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->config[$key]);
    }

    /**
     * Get all configuration (use sparingly - prefer specific getters)
     *
     * @return array All configuration
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Get environment (development, staging, production)
     *
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->get('APP_ENV', 'production');
    }

    /**
     * Check if in production environment
     *
     * @return bool
     */
    public function isProduction(): bool
    {
        return $this->getEnvironment() === 'production';
    }

    /**
     * Check if in development environment
     *
     * @return bool
     */
    public function isDevelopment(): bool
    {
        return $this->getEnvironment() === 'development';
    }

    /**
     * Get Vend/Lightspeed Retail configuration
     * Automatically builds full URL from domain prefix
     *
     * @return array Vend/Lightspeed config
     */
    public function getVend(): array
    {
        $domainPrefix = $this->get('VEND_DOMAIN_PREFIX');

        return [
            'domain_prefix' => $domainPrefix,
            'full_url' => $this->get('VEND_FULL_URL', "https://{$domainPrefix}.vendhq.com"),
            'api_base' => "https://{$domainPrefix}.vendhq.com/api/" . $this->get('VEND_API_VERSION', '2.0'),
            'access_token' => $this->get('VEND_ACCESS_TOKEN'),
            'api_version' => $this->get('VEND_API_VERSION', '2.0'),
            'timeout' => (int) $this->get('VEND_TIMEOUT', 30),
        ];
    }

    /**
     * Get Xero configuration
     *
     * @return array Xero config
     */
    public function getXero(): array
    {
        return [
            'client_id' => $this->get('XERO_CLIENT_ID'),
            'client_secret' => $this->get('XERO_CLIENT_SECRET'),
            'tenant_id' => $this->get('XERO_TENANT_ID'),
            'redirect_uri' => $this->get('XERO_REDIRECT_URI'),
            'webhook_key' => $this->get('XERO_WEBHOOK_KEY', null),
            'scope' => $this->get('XERO_SCOPE', 'accounting.transactions accounting.contacts accounting.settings'),
        ];
    }

    /**
     * Get Deputy configuration
     *
     * @return array Deputy config
     */
    public function getDeputy(): array
    {
        return [
            'access_token' => $this->get('DEPUTY_ACCESS_TOKEN'),
            'endpoint' => $this->get('DEPUTY_ENDPOINT'),
            'webhook_secret' => $this->get('DEPUTY_WEBHOOK_SECRET', null),
            'timeout' => (int) $this->get('DEPUTY_TIMEOUT', 30),
        ];
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
