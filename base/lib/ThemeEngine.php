<?php
/**
 * CIS Theme Engine - Core MVC Theme System
 *
 * Professional theme management with component-based architecture
 *
 * @package CISThemes
 * @version 1.0.0
 */

declare(strict_types=1);

namespace CIS\Themes;

class ThemeEngine
{
    private string $themesPath;
    private string $activeTheme;
    private array $config = [];
    private array $components = [];
    private static ?self $instance = null;

    private function __construct()
    {
        $this->themesPath = __DIR__ . '/../themes';
        $this->activeTheme = $_SESSION['cis_theme'] ?? 'professional-dark';
        $this->loadThemeConfig();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Render a view with theme
     */
    public function render(string $view, array $data = []): string
    {
        extract($data);
        ob_start();

        $viewPath = $this->themesPath . '/' . $this->activeTheme . '/views/' . $view . '.php';

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        include $viewPath;
        return ob_get_clean();
    }

    /**
     * Render a component
     */
    public function component(string $name, array $props = []): string
    {
        $componentPath = __DIR__ . '/../components/' . $name . '.php';

        if (!file_exists($componentPath)) {
            return "<!-- Component not found: {$name} -->";
        }

        extract($props);
        ob_start();
        include $componentPath;
        return ob_get_clean();
    }

    /**
     * Get theme asset URL
     */
    public function asset(string $path): string
    {
        $version = $this->config['version'] ?? '1.0.0';
    return "/modules/cis-themes/themes/{$this->activeTheme}/assets/{$path}?v={$version}";
    }

    /**
     * Get theme configuration
     */
    public function getConfig(string $key = null)
    {
        if ($key === null) {
            return $this->config;
        }
        return $this->config[$key] ?? null;
    }

    /**
     * Load theme configuration
     */
    private function loadThemeConfig(): void
    {
        $configPath = $this->themesPath . '/' . $this->activeTheme . '/theme.json';

        if (file_exists($configPath)) {
            $this->config = json_decode(file_get_contents($configPath), true);
        }
    }

    /**
     * Switch active theme
     */
    public function switchTheme(string $themeName): bool
    {
        $themePath = $this->themesPath . '/' . $themeName;

        if (!is_dir($themePath)) {
            return false;
        }

        $this->activeTheme = $themeName;
        $_SESSION['cis_theme'] = $themeName;
        $this->loadThemeConfig();

        return true;
    }

    /**
     * Get all available themes
     */
    public function getAvailableThemes(): array
    {
        $themes = [];
        $dirs = glob($this->themesPath . '/*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $themeName = basename($dir);
            $configPath = $dir . '/theme.json';

            if (file_exists($configPath)) {
                $config = json_decode(file_get_contents($configPath), true);
                $themes[$themeName] = $config;
            }
        }

        return $themes;
    }

    /**
     * Get active theme name
     */
    public function getActiveTheme(): string
    {
        return $this->activeTheme;
    }

    /**
     * Include theme styles
     */
    public function styles(): string
    {
        $output = '';
        $styles = $this->config['styles'] ?? ['main.css'];

        foreach ($styles as $style) {
            $url = $this->asset("css/{$style}");
            $output .= "<link rel=\"stylesheet\" href=\"{$url}\">\n";
        }

        return $output;
    }

    /**
     * Include theme scripts
     */
    public function scripts(): string
    {
        $output = '';
        $scripts = $this->config['scripts'] ?? ['main.js'];

        foreach ($scripts as $script) {
            $url = $this->asset("js/{$script}");
            $output .= "<script src=\"{$url}\"></script>\n";
        }

        return $output;
    }

    /**
     * Render layout
     */
    public function layout(string $layout, string $content, array $data = []): string
    {
        $layoutPath = __DIR__ . '/../layouts/' . $layout . '.php';

        if (!file_exists($layoutPath)) {
            return $content;
        }

        extract($data);
        ob_start();
        include $layoutPath;
        return ob_get_clean();
    }
}
