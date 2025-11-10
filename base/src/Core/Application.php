<?php

/**
 * CIS Base Application Container.
 *
 * Modern dependency injection container for managing services
 *
 * @version 2.0.0
 */

declare(strict_types=1);

namespace CIS\Base\Core;

use Exception;

use function dirname;

use const DIRECTORY_SEPARATOR;
use const E_ALL;
use const E_DEPRECATED;
use const E_STRICT;

class Application
{
    /** Singleton instance */
    private static ?self $instance = null;

    /** Service container */
    private array $services = [];

    /** Singleton services */
    private array $singletons = [];

    /** Configuration */
    private array $config = [];

    /** Base path */
    private string $basePath;

    /** Booted flag */
    private bool $booted = false;

    /**
     * Private constructor (singleton).
     */
    private function __construct()
    {
        $this->basePath = dirname(__DIR__, 2);
    }

    /**
     * Get singleton instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Load configuration.
     */
    public function withConfig(string $configPath): self
    {
        if (file_exists($configPath)) {
            $this->config = require $configPath;
        }

        return $this;
    }

    /**
     * Get configuration value.
     *
     * @param mixed|null $default
     */
    public function config(string $key, $default = null)
    {
        $keys  = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Register a service.
     */
    public function register(string $abstract, callable $concrete): void
    {
        $this->services[$abstract] = $concrete;
    }

    /**
     * Register a singleton service.
     */
    public function singleton(string $abstract, ?callable $concrete = null): void
    {
        if ($concrete === null) {
            $concrete = function ($app) use ($abstract) {
                return new $abstract($app);
            };
        }

        $this->services[$abstract]   = $concrete;
        $this->singletons[$abstract] = true;
    }

    /**
     * Resolve a service from container.
     */
    public function make(string $abstract)
    {
        // If it's a singleton and already instantiated, return it
        if (isset($this->singletons[$abstract], $this->services[$abstract . '_instance'])) {
            return $this->services[$abstract . '_instance'];
        }

        // If service not registered, try to auto-resolve
        if (!isset($this->services[$abstract])) {
            return $this->autoResolve($abstract);
        }

        // Resolve the service
        $concrete = $this->services[$abstract];
        $instance = $concrete($this);

        // Store singleton instance
        if (isset($this->singletons[$abstract])) {
            $this->services[$abstract . '_instance'] = $instance;
        }

        return $instance;
    }

    /**
     * Register all services from config.
     */
    public function registerServices(): self
    {
        $servicesConfig = require $this->basePath . '/config/services.php';

        // Register core services as singletons
        foreach ($servicesConfig['core'] ?? [] as $service) {
            $this->singleton($service);
        }

        // Register HTTP services
        foreach ($servicesConfig['http'] ?? [] as $service) {
            $this->singleton($service);
        }

        // Register security services
        foreach ($servicesConfig['security'] ?? [] as $service) {
            $this->singleton($service);
        }

        // Register business services (lazy loaded)
        foreach ($servicesConfig['services'] ?? [] as $service) {
            $this->register($service, fn ($app) => new $service($app));
        }

        // Register view services
        foreach ($servicesConfig['view'] ?? [] as $service) {
            $this->singleton($service);
        }

        return $this;
    }

    /**
     * Boot the application.
     */
    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }

        // Set error reporting based on environment
        $this->setupErrorReporting();

        // Initialize core services
        $this->initializeCoreServices();

        $this->booted = true;

        return $this;
    }

    /**
     * Get base path.
     */
    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * Get config path.
     */
    public function configPath(string $path = ''): string
    {
        return $this->basePath('config') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * Get storage path.
     */
    public function storagePath(string $path = ''): string
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/storage' . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * Check if application is booted.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Get environment.
     */
    public function environment(): string
    {
        return $this->config('environment', 'production');
    }

    /**
     * Check if running in debug mode.
     */
    public function isDebug(): bool
    {
        return (bool) $this->config('debug', false);
    }

    /**
     * Auto-resolve a class (no dependencies).
     */
    private function autoResolve(string $class)
    {
        if (class_exists($class)) {
            return new $class($this);
        }

        throw new Exception("Unable to resolve: {$class}");
    }

    /**
     * Setup error reporting.
     */
    private function setupErrorReporting(): void
    {
        $env   = $this->config('environment', 'production');
        $debug = $this->config('debug', false);

        if ($env === 'development' || $debug) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', '1');  // 🔥 FORCE DISPLAY ERRORS EVEN IN PROD
        }
    }

    /**
     * Initialize core services.
     */
    private function initializeCoreServices(): void
    {
        // Database will initialize on first use (lazy)
        // Session will initialize on first use (lazy)

        // Error handler initializes immediately
        try {
            $errorHandler = $this->make(ErrorHandler::class);
            if (method_exists($errorHandler, 'register')) {
                $errorHandler->register();
            }
        } catch (Exception $e) {
            // Fallback error handler not available yet
        }
    }
}
