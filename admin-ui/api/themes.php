<?php
/**
 * ThemeAPI - Theme Management Operations
 *
 * Handles all theme-related operations using BaseAPI inheritance
 *
 * @package CIS\AdminUI\API
 * @version 6.0.0
 */

require_once __DIR__ . '/../lib/BaseAPI.php';

class ThemeAPI extends BaseAPI {

    private $themesPath;
    private $activeThemePath;

    public function __construct($config = []) {
        parent::__construct($config);

        $this->themesPath = $this->config['base_path'] . '/themes';
        $this->activeThemePath = $this->config['base_path'] . '/config/active-theme.json';

        $this->ensureDirectory($this->themesPath);
        $this->ensureDirectory(dirname($this->activeThemePath));
    }

    /**
     * Save active theme
     */
    protected function handleSaveActiveTheme($data) {
        $this->validateRequired($data, ['theme_data']);

        $themeData = json_decode($data['theme_data'], true);
        if (!$themeData) {
            throw new Exception('Invalid theme data JSON');
        }

        // Add metadata
        $themeData['saved_at'] = date('Y-m-d H:i:s');
        $themeData['version'] = '6.0.0';
        $themeData['type'] = 'active';

        file_put_contents(
            $this->activeThemePath,
            json_encode($themeData, JSON_PRETTY_PRINT)
        );

        return $this->success($themeData, 'Active theme saved successfully', [
            'file' => 'config/active-theme.json',
            'size' => filesize($this->activeThemePath)
        ]);
    }

    /**
     * Load active theme
     */
    protected function handleLoadActiveTheme($data) {
        if (!file_exists($this->activeThemePath)) {
            return $this->error('No active theme found', 'THEME_NOT_FOUND');
        }

        $theme = json_decode(file_get_contents($this->activeThemePath), true);

        return $this->success($theme, 'Active theme loaded', [
            'file' => 'config/active-theme.json',
            'modified' => date('Y-m-d H:i:s', filemtime($this->activeThemePath))
        ]);
    }

    /**
     * Save named theme preset
     */
    protected function handleSaveTheme($data) {
        $this->validateRequired($data, ['theme_data']);

        $themeData = json_decode($data['theme_data'], true);
        if (!$themeData) {
            throw new Exception('Invalid theme data JSON');
        }

        $themeId = $themeData['id'] ?? 'theme_' . time();
        $themeId = $this->sanitizeFilename($themeId);

        $themePath = $this->themesPath . '/' . $themeId . '.json';

        // Add metadata
        $themeData['id'] = $themeId;
        $themeData['saved_at'] = date('Y-m-d H:i:s');
        $themeData['version'] = '6.0.0';

        file_put_contents($themePath, json_encode($themeData, JSON_PRETTY_PRINT));

        return $this->success([
            'theme_id' => $themeId,
            'path' => 'themes/' . $themeId . '.json'
        ], 'Theme preset saved successfully');
    }

    /**
     * Load theme preset
     */
    protected function handleLoadTheme($data) {
        $this->validateRequired($data, ['theme_id']);

        $themeId = $this->sanitizeFilename($data['theme_id']);
        $themePath = $this->themesPath . '/' . $themeId . '.json';

        if (!file_exists($themePath)) {
            return $this->error('Theme not found: ' . $themeId, 'THEME_NOT_FOUND');
        }

        $theme = json_decode(file_get_contents($themePath), true);

        return $this->success($theme, 'Theme loaded successfully', [
            'theme_id' => $themeId,
            'modified' => date('Y-m-d H:i:s', filemtime($themePath))
        ]);
    }

    /**
     * List all theme presets
     */
    protected function handleListThemes($data) {
        $themes = [];

        if (is_dir($this->themesPath)) {
            foreach (glob($this->themesPath . '/*.json') as $file) {
                $theme = json_decode(file_get_contents($file), true);
                $themes[] = [
                    'id' => $theme['id'] ?? basename($file, '.json'),
                    'name' => $theme['name'] ?? 'Unnamed Theme',
                    'version' => $theme['version'] ?? '1.0.0',
                    'modified' => date('Y-m-d H:i:s', filemtime($file)),
                    'size' => filesize($file)
                ];
            }
        }

        // Sort by modified date descending
        usort($themes, function($a, $b) {
            return strtotime($b['modified']) - strtotime($a['modified']);
        });

        return $this->success($themes, 'Themes listed successfully', [
            'count' => count($themes)
        ]);
    }

    /**
     * Delete theme preset
     */
    protected function handleDeleteTheme($data) {
        $this->validateRequired($data, ['theme_id']);

        $themeId = $this->sanitizeFilename($data['theme_id']);
        $themePath = $this->themesPath . '/' . $themeId . '.json';

        if (!file_exists($themePath)) {
            return $this->error('Theme not found: ' . $themeId, 'THEME_NOT_FOUND');
        }

        unlink($themePath);

        return $this->success(null, 'Theme deleted successfully', [
            'theme_id' => $themeId
        ]);
    }

    /**
     * Export theme as JSON download
     */
    protected function handleExportTheme($data) {
        $this->validateRequired($data, ['theme_data']);

        $themeData = json_decode($data['theme_data'], true);
        if (!$themeData) {
            throw new Exception('Invalid theme data JSON');
        }

        $filename = $this->sanitizeFilename($themeData['name'] ?? 'theme') . '_export.json';

        return $this->success([
            'filename' => $filename,
            'content' => json_encode($themeData, JSON_PRETTY_PRINT),
            'size' => strlen(json_encode($themeData))
        ], 'Theme exported successfully');
    }

    /**
     * Import theme from JSON
     */
    protected function handleImportTheme($data) {
        $this->validateRequired($data, ['theme_json']);

        $themeData = json_decode($data['theme_json'], true);
        if (!$themeData) {
            throw new Exception('Invalid theme JSON');
        }

        // Generate new ID for imported theme
        $themeData['id'] = 'imported_' . time();
        $themeData['imported_at'] = date('Y-m-d H:i:s');

        // Save as new preset
        return $this->handleSaveTheme([
            'theme_data' => json_encode($themeData)
        ]);
    }
}

// Handle request if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    session_start();
    $api = new ThemeAPI(['base_path' => dirname(__DIR__)]);
    $api->handleRequest();
}
