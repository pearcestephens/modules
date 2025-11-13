<?php
/**
 * Template Renderer
 *
 * Helper class for rendering the base template with module content
 */

namespace App\Template;

class Renderer
{
    private $config;
    private $templatePath;

    public function __construct()
    {
        $this->templatePath = __DIR__ . '/../templates/vape-ultra';
        $this->config = require $this->templatePath . '/config.php';
    }

    /**
     * Render a page with the base template
     *
     * @param string $moduleContent HTML content from module
     * @param array $options Page options (title, layout, scripts, styles, etc)
     * @return void
     */
    public function render($moduleContent, $options = [])
    {
        // Extract options
        $pageTitle = $options['title'] ?? 'Vape Shed CIS Ultra';
        $pageClass = $options['class'] ?? 'page-default';
        $layoutType = $options['layout'] ?? 'main';
        $moduleScripts = $options['scripts'] ?? [];
        $moduleStyles = $options['styles'] ?? [];
        $inlineScripts = $options['inline_scripts'] ?? '';
        $hideRightSidebar = $options['hide_right_sidebar'] ?? false;
        $rightSidebarContent = $options['right_sidebar'] ?? null;
        $moduleNavItems = $options['nav_items'] ?? [];
        $customWidgets = $options['widgets'] ?? null;

        // Include base layout
        require $this->templatePath . '/layouts/base.php';
    }

    /**
     * Render just the content (for AJAX requests)
     */
    public function renderContent($moduleContent)
    {
        echo $moduleContent;
    }

    /**
     * Render JSON response
     */
    public function renderJson($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    /**
     * Get CSRF token for forms
     */
    public function getCsrfToken()
    {
        return \App\Middleware\CsrfMiddleware::getToken();
    }

    /**
     * Get CSRF meta tag
     */
    public function getCsrfMeta()
    {
        $token = $this->getCsrfToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }

    /**
     * Get CSRF input field
     */
    public function getCsrfInput()
    {
        $token = $this->getCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
}
