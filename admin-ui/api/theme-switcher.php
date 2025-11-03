<?php
/**
 * Theme Switcher API
 * Handles all theme management AJAX requests
 */

header('Content-Type: application/json');

// HEAD probe support: do not execute logic for HEAD requests
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD') {
    header('Allow: GET, POST, OPTIONS');
    http_response_code(405);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/bootstrap.php';
require_once __DIR__ . '/../lib/ThemeManager.php';

try {
    $themeManager = new ThemeManager();
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    switch ($action) {

        case 'list_themes':
            $themes = $themeManager->listThemes();
            echo json_encode([
                'success' => true,
                'themes' => $themes,
                'active' => $themeManager->getActiveThemeId()
            ]);
            break;

        case 'get_theme':
            $themeId = $_POST['theme_id'] ?? '';
            $theme = $themeManager->getTheme($themeId);

            if ($theme) {
                echo json_encode(['success' => true, 'theme' => $theme]);
            } else {
                throw new Exception('Theme not found');
            }
            break;

        case 'get_active_theme':
            $theme = $themeManager->getActiveTheme();
            echo json_encode(['success' => true, 'theme' => $theme]);
            break;

        case 'create_theme':
            $data = json_decode($_POST['theme_data'] ?? '{}', true);
            $theme = $themeManager->createTheme($data);
            echo json_encode([
                'success' => true,
                'message' => 'Theme created successfully',
                'theme' => $theme
            ]);
            break;

        case 'update_theme':
            $themeId = $_POST['theme_id'] ?? '';
            $data = json_decode($_POST['theme_data'] ?? '{}', true);
            $changeDescription = $_POST['change_description'] ?? '';

            $theme = $themeManager->updateTheme($themeId, $data, $changeDescription);
            echo json_encode([
                'success' => true,
                'message' => 'Theme updated successfully',
                'theme' => $theme
            ]);
            break;

        case 'set_active_theme':
            $themeId = $_POST['theme_id'] ?? '';
            $themeManager->setActiveTheme($themeId);
            echo json_encode([
                'success' => true,
                'message' => 'Theme activated successfully'
            ]);
            break;

        case 'duplicate_theme':
            $themeId = $_POST['theme_id'] ?? '';
            $newName = $_POST['new_name'] ?? null;
            $theme = $themeManager->duplicateTheme($themeId, $newName);
            echo json_encode([
                'success' => true,
                'message' => 'Theme duplicated successfully',
                'theme' => $theme
            ]);
            break;

        case 'delete_theme':
            $themeId = $_POST['theme_id'] ?? '';
            $themeManager->deleteTheme($themeId);
            echo json_encode([
                'success' => true,
                'message' => 'Theme deleted successfully'
            ]);
            break;

        case 'export_theme':
            $themeId = $_POST['theme_id'] ?? '';
            $export = $themeManager->exportTheme($themeId);
            echo json_encode([
                'success' => true,
                'filename' => $export['filename'],
                'data' => base64_encode($export['data'])
            ]);
            break;

        case 'import_theme':
            $themeData = $_POST['theme_data'] ?? '';
            $theme = $themeManager->importTheme($themeData);
            echo json_encode([
                'success' => true,
                'message' => 'Theme imported successfully',
                'theme' => $theme
            ]);
            break;

        default:
            throw new Exception('Unknown action: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
