<?php
/**
 * Theme Manager - Complete Theme Package Management
 *
 * Handles theme creation, versioning, switching, and persistence
 *
 * @package CIS\Modules\AdminUI
 * @version 1.0.0
 */

class ThemeManager {

    private $themesPath;
    private $activeThemeFile;

    public function __construct() {
        $this->themesPath = __DIR__ . '/../themes';
        $this->activeThemeFile = $this->themesPath . '/.active-theme';

        // Ensure themes directory exists
        if (!is_dir($this->themesPath)) {
            mkdir($this->themesPath, 0755, true);
        }

        // Create default theme if none exist
        if (empty($this->listThemes())) {
            $this->createDefaultTheme();
        }
    }

    /**
     * Create a new theme package
     */
    public function createTheme($data) {
        $themeId = $data['id'] ?? 'theme_' . time();
        $themePath = $this->themesPath . '/' . $themeId;

        if (!is_dir($themePath)) {
            mkdir($themePath, 0755, true);
        }

        $theme = [
            'id' => $themeId,
            'name' => $data['name'] ?? 'New Theme',
            'description' => $data['description'] ?? '',
            'version' => $data['version'] ?? '1.0.0',
            'author' => $data['author'] ?? 'CIS User',
            'created' => date('Y-m-d H:i:s'),
            'modified' => date('Y-m-d H:i:s'),
            'changelog' => $data['changelog'] ?? [
                [
                    'version' => '1.0.0',
                    'date' => date('Y-m-d H:i:s'),
                    'changes' => ['Initial theme creation']
                ]
            ],
            'variables' => $data['variables'] ?? $this->getDefaultVariables(),
            'isActive' => false
        ];

        // Save theme metadata
        file_put_contents(
            $themePath . '/theme.json',
            json_encode($theme, JSON_PRETTY_PRINT)
        );

        // Save CSS files
        file_put_contents(
            $themePath . '/variables.css',
            $this->generateVariablesCSS($theme['variables'])
        );

        file_put_contents(
            $themePath . '/components.css',
            $data['componentsCSS'] ?? $this->getDefaultComponentsCSS()
        );

        file_put_contents(
            $themePath . '/layouts.css',
            $data['layoutsCSS'] ?? $this->getDefaultLayoutsCSS()
        );

        return $theme;
    }

    /**
     * Update existing theme (creates new version)
     */
    public function updateTheme($themeId, $data, $changeDescription = '') {
        $theme = $this->getTheme($themeId);

        if (!$theme) {
            throw new Exception('Theme not found');
        }

        // Increment version
        $versionParts = explode('.', $theme['version']);
        $versionParts[2] = (int)$versionParts[2] + 1;
        $newVersion = implode('.', $versionParts);

        // Add to changelog
        $changelog = $theme['changelog'];
        $changelog[] = [
            'version' => $newVersion,
            'date' => date('Y-m-d H:i:s'),
            'changes' => $changeDescription ? [$changeDescription] : ['Theme updated']
        ];

        // Update theme data
        $theme = array_merge($theme, [
            'name' => $data['name'] ?? $theme['name'],
            'description' => $data['description'] ?? $theme['description'],
            'version' => $newVersion,
            'modified' => date('Y-m-d H:i:s'),
            'changelog' => $changelog,
            'variables' => $data['variables'] ?? $theme['variables']
        ]);

        $themePath = $this->themesPath . '/' . $themeId;

        // Save updated metadata
        file_put_contents(
            $themePath . '/theme.json',
            json_encode($theme, JSON_PRETTY_PRINT)
        );

        // Update CSS files if provided
        if (isset($data['variablesCSS'])) {
            file_put_contents($themePath . '/variables.css', $data['variablesCSS']);
        } else {
            file_put_contents(
                $themePath . '/variables.css',
                $this->generateVariablesCSS($theme['variables'])
            );
        }

        if (isset($data['componentsCSS'])) {
            file_put_contents($themePath . '/components.css', $data['componentsCSS']);
        }

        if (isset($data['layoutsCSS'])) {
            file_put_contents($themePath . '/layouts.css', $data['layoutsCSS']);
        }

        return $theme;
    }

    /**
     * Get theme by ID
     */
    public function getTheme($themeId) {
        $themePath = $this->themesPath . '/' . $themeId;
        $metaFile = $themePath . '/theme.json';

        if (!file_exists($metaFile)) {
            return null;
        }

        $theme = json_decode(file_get_contents($metaFile), true);

        // Load CSS files
        $theme['variablesCSS'] = file_get_contents($themePath . '/variables.css');
        $theme['componentsCSS'] = file_get_contents($themePath . '/components.css');
        $theme['layoutsCSS'] = file_get_contents($themePath . '/layouts.css');

        // Check if active
        $theme['isActive'] = ($this->getActiveThemeId() === $themeId);

        return $theme;
    }

    /**
     * List all themes
     */
    public function listThemes() {
        $themes = [];
        $activeId = $this->getActiveThemeId();

        foreach (glob($this->themesPath . '/*/theme.json') as $file) {
            $theme = json_decode(file_get_contents($file), true);
            $theme['isActive'] = ($theme['id'] === $activeId);
            $themes[] = $theme;
        }

        return $themes;
    }

    /**
     * Set active theme
     */
    public function setActiveTheme($themeId) {
        $theme = $this->getTheme($themeId);

        if (!$theme) {
            throw new Exception('Theme not found');
        }

        file_put_contents($this->activeThemeFile, $themeId);

        return true;
    }

    /**
     * Get active theme ID
     */
    public function getActiveThemeId() {
        if (file_exists($this->activeThemeFile)) {
            return trim(file_get_contents($this->activeThemeFile));
        }

        // Return first theme as default
        $themes = $this->listThemes();
        return $themes[0]['id'] ?? 'default';
    }

    /**
     * Get active theme
     */
    public function getActiveTheme() {
        return $this->getTheme($this->getActiveThemeId());
    }

    /**
     * Delete theme
     */
    public function deleteTheme($themeId) {
        if ($this->getActiveThemeId() === $themeId) {
            throw new Exception('Cannot delete active theme');
        }

        $themePath = $this->themesPath . '/' . $themeId;

        if (is_dir($themePath)) {
            $this->deleteDirectory($themePath);
            return true;
        }

        return false;
    }

    /**
     * Duplicate theme
     */
    public function duplicateTheme($themeId, $newName = null) {
        $sourceTheme = $this->getTheme($themeId);

        if (!$sourceTheme) {
            throw new Exception('Source theme not found');
        }

        $newTheme = $sourceTheme;
        $newTheme['id'] = 'theme_' . time();
        $newTheme['name'] = $newName ?? ($sourceTheme['name'] . ' (Copy)');
        $newTheme['version'] = '1.0.0';
        $newTheme['created'] = date('Y-m-d H:i:s');
        $newTheme['modified'] = date('Y-m-d H:i:s');
        $newTheme['changelog'] = [
            [
                'version' => '1.0.0',
                'date' => date('Y-m-d H:i:s'),
                'changes' => ["Duplicated from '{$sourceTheme['name']}'"]
            ]
        ];

        return $this->createTheme($newTheme);
    }

    /**
     * Export theme as package
     */
    public function exportTheme($themeId) {
        $theme = $this->getTheme($themeId);

        if (!$theme) {
            throw new Exception('Theme not found');
        }

        return [
            'filename' => $themeId . '.theme.json',
            'data' => json_encode($theme, JSON_PRETTY_PRINT)
        ];
    }

    /**
     * Import theme from package
     */
    public function importTheme($themeData) {
        $theme = is_string($themeData) ? json_decode($themeData, true) : $themeData;

        // Generate new ID to avoid conflicts
        $theme['id'] = 'theme_' . time();
        $theme['imported'] = date('Y-m-d H:i:s');

        return $this->createTheme($theme);
    }

    /**
     * Create default theme
     */
    private function createDefaultTheme() {
        return $this->createTheme([
            'id' => 'default',
            'name' => 'CIS Default',
            'description' => 'Default CIS admin theme with modern design',
            'author' => 'CIS Team',
            'version' => '1.0.0'
        ]);
    }

    /**
     * Get default CSS variables
     */
    private function getDefaultVariables() {
        return [
            // Primary colors
            'primary' => '#10b981',
            'primary-dark' => '#059669',
            'primary-light' => '#34d399',

            // Secondary colors
            'secondary' => '#3b82f6',
            'secondary-dark' => '#2563eb',

            // Accent colors
            'accent' => '#f59e0b',
            'success' => '#10b981',
            'warning' => '#f59e0b',
            'danger' => '#ef4444',
            'info' => '#3b82f6',

            // Backgrounds
            'bg-primary' => '#0f172a',
            'bg-secondary' => '#1e293b',
            'bg-tertiary' => '#334155',

            // Text colors
            'text-primary' => '#f1f5f9',
            'text-secondary' => '#94a3b8',
            'text-muted' => '#64748b',

            // Borders
            'border' => '#334155',
            'border-light' => '#475569',

            // Shadows
            'shadow-sm' => '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
            'shadow' => '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
            'shadow-lg' => '0 10px 15px -3px rgba(0, 0, 0, 0.1)',

            // Spacing
            'spacing-unit' => '0.25rem',

            // Border radius
            'radius-sm' => '0.375rem',
            'radius' => '0.5rem',
            'radius-lg' => '0.75rem',

            // Typography
            'font-family' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
            'font-size-sm' => '0.875rem',
            'font-size-base' => '1rem',
            'font-size-lg' => '1.125rem',
            'font-size-xl' => '1.25rem',

            // Transitions
            'transition-fast' => '150ms',
            'transition-base' => '200ms',
            'transition-slow' => '300ms'
        ];
    }

    /**
     * Generate CSS variables file
     */
    private function generateVariablesCSS($variables) {
        $css = ":root {\n";

        foreach ($variables as $key => $value) {
            $css .= "    --cis-{$key}: {$value};\n";
        }

        $css .= "}\n";

        return $css;
    }

    /**
     * Get default components CSS
     */
    private function getDefaultComponentsCSS() {
        return <<<CSS
/* CIS Components Default Styles */

.btn {
    padding: 0.625rem 1.25rem;
    border: none;
    border-radius: var(--cis-radius);
    font-size: var(--cis-font-size-base);
    font-weight: 500;
    cursor: pointer;
    transition: all var(--cis-transition-base);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background: var(--cis-primary);
    color: white;
}

.btn-primary:hover {
    background: var(--cis-primary-dark);
    transform: translateY(-1px);
    box-shadow: var(--cis-shadow);
}

.btn-secondary {
    background: var(--cis-secondary);
    color: white;
}

.btn-secondary:hover {
    background: var(--cis-secondary-dark);
}

.card {
    background: var(--cis-bg-secondary);
    border: 1px solid var(--cis-border);
    border-radius: var(--cis-radius-lg);
    padding: 1.5rem;
    box-shadow: var(--cis-shadow-sm);
}

.card-header {
    font-size: var(--cis-font-size-lg);
    font-weight: 600;
    color: var(--cis-text-primary);
    margin-bottom: 1rem;
}

.form-control {
    width: 100%;
    padding: 0.625rem 0.875rem;
    background: var(--cis-bg-tertiary);
    border: 1px solid var(--cis-border);
    border-radius: var(--cis-radius);
    color: var(--cis-text-primary);
    font-size: var(--cis-font-size-base);
    transition: all var(--cis-transition-base);
}

.form-control:focus {
    outline: none;
    border-color: var(--cis-primary);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

CSS;
    }

    /**
     * Get default layouts CSS
     */
    private function getDefaultLayoutsCSS() {
        return <<<CSS
/* CIS Layouts Default Styles */

body {
    font-family: var(--cis-font-family);
    background: var(--cis-bg-primary);
    color: var(--cis-text-primary);
    margin: 0;
    padding: 0;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1.5rem;
}

.dashboard-wrapper {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    width: 260px;
    background: var(--cis-bg-secondary);
    border-right: 1px solid var(--cis-border);
}

.main-content {
    flex: 1;
    min-width: 0;
}

.header {
    background: var(--cis-bg-secondary);
    border-bottom: 1px solid var(--cis-border);
    padding: 1rem 1.5rem;
}

.footer {
    background: var(--cis-bg-secondary);
    border-top: 1px solid var(--cis-border);
    padding: 1rem 1.5rem;
    text-align: center;
    color: var(--cis-text-secondary);
    font-size: var(--cis-font-size-sm);
}

CSS;
    }

    /**
     * Recursively delete directory
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        return rmdir($dir);
    }
}
CSS;
    }
}
