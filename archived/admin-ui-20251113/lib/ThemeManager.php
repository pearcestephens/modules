<?php
/**
 * Admin UI Theme Manager
 *
 * Handles theme selection, loading, and rendering for the admin interface.
 * Supports multiple themes that can be switched without conflicts.
 *
 * @package AdminUI\App
 * @version 1.0.0
 */

class ThemeManager {

    private static $instance = null;
    private $currentTheme = null;
    private $availableThemes = [];
    private $config = [];

    /**
     * Singleton pattern
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->loadConfig();
        $this->discoverThemes();
        $this->loadCurrentTheme();
    }

    /**
     * Load configuration
     */
    private function loadConfig(): void {
        $this->config = [
            'themes_path' => $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/themes',
            'default_theme' => 'cis-classic',
            'theme_session_key' => 'admin_ui_theme',
        ];
    }

    /**
     * Discover available themes
     */
    private function discoverThemes(): void {
        $themesPath = $this->config['themes_path'];

        if (!is_dir($themesPath)) {
            error_log("ThemeManager: Themes path not found - {$themesPath}");
            return;
        }

        $dirs = glob($themesPath . '/*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $themeName = basename($dir);
            $themeFile = $dir . '/theme.php';

            if (file_exists($themeFile)) {
                $this->availableThemes[$themeName] = [
                    'name' => $themeName,
                    'path' => $dir,
                    'file' => $themeFile,
                    'class' => $this->getThemeClassName($themeName),
                ];
            }
        }
    }

    /**
     * Get theme class name from theme directory name
     */
    private function getThemeClassName(string $themeName): string {
        // Convert cis-classic to CISClassicTheme
        $parts = explode('-', $themeName);
        $className = '';

        foreach ($parts as $part) {
            if (strtolower($part) === 'cis') {
                $className .= 'CIS';
            } else {
                $className .= ucfirst($part);
            }
        }

        return $className . 'Theme';
    }

    /**
     * Load current theme based on session or default
     */
    private function loadCurrentTheme(): void {
        // Check if theme is set in session
        $themeName = $_SESSION[$this->config['theme_session_key']] ?? null;

        // Validate theme exists
        if (!$themeName || !isset($this->availableThemes[$themeName])) {
            $themeName = $this->config['default_theme'];
        }

        // Load theme class
        if (isset($this->availableThemes[$themeName])) {
            $themeInfo = $this->availableThemes[$themeName];

            require_once $themeInfo['file'];

            $className = $themeInfo['class'];

            if (class_exists($className)) {
                $this->currentTheme = new $className();
            } else {
                error_log("ThemeManager: Theme class not found - {$className}");
            }
        }
    }

    /**
     * Get current theme instance
     */
    public function getTheme() {
        return $this->currentTheme;
    }

    /**
     * Get list of available themes
     */
    public function getAvailableThemes(): array {
        return $this->availableThemes;
    }

    /**
     * Switch to a different theme
     */
    public function switchTheme(string $themeName): bool {
        if (!isset($this->availableThemes[$themeName])) {
            return false;
        }

        $_SESSION[$this->config['theme_session_key']] = $themeName;
        $this->loadCurrentTheme();

        return true;
    }

    /**
     * Get current theme name
     */
    public function getCurrentThemeName(): ?string {
        if (!$this->currentTheme) {
            return null;
        }

        foreach ($this->availableThemes as $name => $info) {
            if ($info['class'] === get_class($this->currentTheme)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Render theme component
     */
    public function render(string $component, array $data = []): void {
        if ($this->currentTheme && method_exists($this->currentTheme, 'render')) {
            $this->currentTheme->render($component, $data);
        } else {
            echo "<!-- ThemeManager: No theme loaded or render method not available -->";
        }
    }

    /**
     * Magic method to forward calls to current theme
     */
    public function __call($method, $args) {
        if ($this->currentTheme && method_exists($this->currentTheme, $method)) {
            return call_user_func_array([$this->currentTheme, $method], $args);
        }

        throw new BadMethodCallException("Method {$method} does not exist on current theme");
    }
}
