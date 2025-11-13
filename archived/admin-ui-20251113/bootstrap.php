<?php
/**
 * Admin UI Bootstrap
 *
 * Initializes the admin interface with theme system.
 * Include this at the top of every admin-ui page.
 *
 * @package AdminUI
 * @version 1.0.0
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load main app.php if available
if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/app.php')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
}

// Load theme system
require_once __DIR__ . '/lib/ThemeManager.php';
require_once __DIR__ . '/lib/theme_helpers.php';

// Initialize theme manager
$themeManager = ThemeManager::getInstance();

// Set security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

// Optional: Handle theme switching via URL parameter (for testing)
if (isset($_GET['switch_theme']) && !empty($_GET['switch_theme'])) {
    $newTheme = $_GET['switch_theme'];
    if ($themeManager->switchTheme($newTheme)) {
        // Redirect to remove the parameter
        $redirect = strtok($_SERVER['REQUEST_URI'], '?');
        header("Location: {$redirect}");
        exit;
    }
}
