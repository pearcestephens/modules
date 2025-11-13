<?php
/**
 * Theme Helper Functions
 *
 * Global helper functions for theme management.
 * Makes it easy to use themes throughout the application.
 *
 * @package AdminUI\App
 * @version 1.0.0
 */

/**
 * Get the theme manager instance
 */
function theme(): ThemeManager {
    return ThemeManager::getInstance();
}

/**
 * Get the current theme instance
 */
function currentTheme() {
    return theme()->getTheme();
}

/**
 * Render a theme component
 */
function theme_render(string $component, array $data = []): void {
    theme()->render($component, $data);
}

/**
 * Start a themed page
 *
 * Usage:
 *   theme_page_start('My Page Title', 'mypage');
 */
function theme_page_start(string $title = '', string $currentPage = ''): void {
    $t = currentTheme();

    if ($t) {
        if ($title) {
            $t->setTitle($title);
        }
        if ($currentPage) {
            $t->setCurrentPage($currentPage);
        }

        $t->render('html-head');
        $t->render('header');
        $t->render('sidebar');
        $t->render('main-start');
    }
}

/**
 * End a themed page
 */
function theme_page_end(): void {
    $t = currentTheme();

    if ($t) {
        $t->render('footer');
    }
}

/**
 * Add custom CSS/JS to page head
 */
function theme_add_head(string $content): void {
    $t = currentTheme();

    if ($t && method_exists($t, 'addHeadContent')) {
        $t->addHeadContent($content);
    }
}

/**
 * Get theme configuration value
 */
function theme_config(string $key, $default = null) {
    $t = currentTheme();

    if ($t && method_exists($t, 'getConfig')) {
        return $t->getConfig($key, $default);
    }

    return $default;
}

/**
 * Check if user has permission (proxies to theme)
 */
function theme_has_permission(string $permission): bool {
    $t = currentTheme();

    if ($t && method_exists($t, 'hasPermission')) {
        return $t->hasPermission($permission);
    }

    return false;
}
