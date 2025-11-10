<?php
/**
 * Theme Manager
 * 
 * Manages theme selection and rendering across CIS
 * Supports multiple themes: cis-classic, modern, legacy
 * 
 * Usage:
 *   Theme::setActive('modern');
 *   Theme::render('dashboard', $content, ['pageTitle' => 'Dashboard']);
 */

namespace CIS\Base;

class ThemeManager {
    private static $activeTheme = 'cis-classic';  // Default theme
    private static $themePath = null;
    private static $initialized = false;
    
    /**
     * Initialize theme system
     */
    public static function init(): void {
        if (self::$initialized) {
            return;
        }
        
        self::$themePath = __DIR__ . '/../templates/themes';
        
        // Check for theme preference (session > config > default)
        if (isset($_SESSION['theme'])) {
            self::$activeTheme = $_SESSION['theme'];
        } elseif (defined('DEFAULT_THEME')) {
            self::$activeTheme = constant('DEFAULT_THEME');
        }
        
        // Validate theme exists
        if (!self::themeExists(self::$activeTheme)) {
            error_log("Theme '" . self::$activeTheme . "' not found, falling back to cis-classic");
            self::$activeTheme = 'cis-classic';
        }
        
        self::$initialized = true;
    }
    
    /**
     * Set active theme
     */
    public static function setActive(string $theme): bool {
        if (!self::themeExists($theme)) {
            return false;
        }
        
        self::$activeTheme = $theme;
        $_SESSION['theme'] = $theme;
        return true;
    }
    
    /**
     * Get active theme name
     */
    public static function getActive(): string {
        return self::$activeTheme;
    }
    
    /**
     * Check if theme exists
     */
    public static function themeExists(string $theme): bool {
        $path = self::$themePath . '/' . basename($theme);
        return is_dir($path);
    }
    
    /**
     * Get list of available themes
     */
    public static function getAvailable(): array {
        $themes = [];
        $dirs = glob(self::$themePath . '/*', GLOB_ONLYDIR);
        
        foreach ($dirs as $dir) {
            $themeName = basename($dir);
            $themeFile = $dir . '/theme.php';
            
            $themes[$themeName] = [
                'name' => $themeName,
                'title' => ucfirst(str_replace('-', ' ', $themeName)),
                'path' => $dir,
                'has_config' => file_exists($themeFile)
            ];
        }
        
        return $themes;
    }
    
    /**
     * Render page with theme layout
     * 
     * @param string $layout Layout name (dashboard, centered, blank, print)
     * @param string $content Page content (HTML)
     * @param array $data Additional data (pageTitle, breadcrumbs, etc.)
     */
    public static function render(string $layout, string $content, array $data = []): void {
        self::init();
        
        // Find layout file (theme-specific first, then fallback)
        $layoutFile = self::findLayout($layout);
        
        if (!$layoutFile) {
            throw new \Exception("Layout '{$layout}' not found in theme '" . self::$activeTheme . "'");
        }
        
        // Extract data to variables
        extract($data);
        
        // Set defaults
        $pageTitle = $pageTitle ?? 'CIS Staff Portal';
        $breadcrumbs = $breadcrumbs ?? [];
        $theme = self::$activeTheme;
        
        // Load layout
        require $layoutFile;
    }
    
    /**
     * Find layout file (theme-specific or fallback)
     */
    private static function findLayout(string $layout): ?string {
        $layoutName = basename($layout) . '.php';
        
        // 1. Try theme-specific layout
        $themePath = self::$themePath . '/' . self::$activeTheme . '/layouts/' . $layoutName;
        if (file_exists($themePath)) {
            return $themePath;
        }
        
        // 2. Try global layouts folder
        $globalPath = __DIR__ . '/../templates/layouts/' . $layoutName;
        if (file_exists($globalPath)) {
            return $globalPath;
        }
        
        // 3. Try fallback theme (cis-classic)
        if (self::$activeTheme !== 'cis-classic') {
            $fallbackPath = self::$themePath . '/cis-classic/layouts/' . $layoutName;
            if (file_exists($fallbackPath)) {
                return $fallbackPath;
            }
        }
        
        return null;
    }
    
    /**
     * Render a component (header, sidebar, footer, etc.)
     */
    public static function component(string $name, array $data = []): void {
        self::init();
        
        $componentFile = self::findComponent($name);
        
        if ($componentFile) {
            extract($data);
            require $componentFile;
        } else {
            error_log("Component '{$name}' not found in theme '" . self::$activeTheme . "'");
        }
    }
    
    /**
     * Find component file
     */
    private static function findComponent(string $name): ?string {
        $componentName = basename($name) . '.php';
        
        // 1. Try theme-specific component
        $themePath = self::$themePath . '/' . self::$activeTheme . '/components/' . $componentName;
        if (file_exists($themePath)) {
            return $themePath;
        }
        
        // 2. Try global components folder
        $globalPath = __DIR__ . '/../templates/components/' . $componentName;
        if (file_exists($globalPath)) {
            return $globalPath;
        }
        
        // 3. Try fallback theme
        if (self::$activeTheme !== 'cis-classic') {
            $fallbackPath = self::$themePath . '/cis-classic/components/' . $componentName;
            if (file_exists($fallbackPath)) {
                return $fallbackPath;
            }
        }
        
        return null;
    }
    
    /**
     * Get theme asset URL (CSS, JS, images)
     */
    public static function asset(string $path): string {
        return '/modules/base/templates/themes/' . self::$activeTheme . '/' . ltrim($path, '/');
    }
}

// Alias for convenience
class_alias('CIS\Base\ThemeManager', 'Theme');
