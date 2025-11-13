<?php
/**
 * CIS Classic Theme Configuration
 *
 * This is the original CIS template system, rebuilt with better structure
 * and cleaner includes. Any module can use this theme by including it.
 *
 * Usage in your module:
 *   $theme = new CISClassicTheme();
 *   $theme->render('header');
 *   // Your content here
 *   $theme->render('footer');
 *
 * @package Base\Templates\Themes
 * @version 2.0.0
 */

class CISClassicTheme {

    private $config = [
        'theme_name' => 'CIS Classic',
        'theme_version' => '2.0.0',
        'bootstrap_version' => '4.1.1',
        'coreui_version' => '2.0.0',
        'base_url' => 'https://staff.vapeshed.co.nz/',
        'assets_url' => 'https://staff.vapeshed.co.nz/assets/',
    ];

    private $pageData = [
        'title' => 'The Vape Shed - Central Information System',
        'body_class' => 'app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show',
        'extra_head' => '',
        'current_page' => '',
        'breadcrumbs' => [],
        'header_buttons' => [],
        'show_timestamps' => false,
        'page_subtitle' => '',
    ];

    private $userData = null;

    public function __construct(array $options = []) {
        // Merge custom options
        $this->config = array_merge($this->config, $options);

        // Load user data if session exists
        $this->loadUserData();
    }

    /**
     * Set page title
     */
    public function setTitle(string $title): void {
        $this->pageData['title'] = $title;
    }

    /**
     * Set body class
     */
    public function setBodyClass(string $class): void {
        $this->pageData['body_class'] = $class;
    }

    /**
     * Add extra head content
     */
    public function addHeadContent(string $content): void {
        $this->pageData['extra_head'] .= $content;
    }

    /**
     * Set current page for active menu highlighting
     */
    public function setCurrentPage(string $page): void {
        $this->pageData['current_page'] = $page;
    }

    /**
     * Add breadcrumb item
     */
    public function addBreadcrumb(string $label, string $url = null): void {
        $crumb = ['label' => $label];
        if ($url !== null) {
            $crumb['url'] = $url;
        }
        $this->pageData['breadcrumbs'][] = $crumb;
    }

    /**
     * Set breadcrumbs array
     */
    public function setBreadcrumbs(array $breadcrumbs): void {
        $this->pageData['breadcrumbs'] = $breadcrumbs;
    }

    /**
     * Add header button
     */
    public function addHeaderButton(string $label, string $url, string $color = 'primary', string $icon = null, string $target = null): void {
        $button = [
            'label' => $label,
            'url' => $url,
            'color' => $color
        ];
        if ($icon) {
            $button['icon'] = $icon;
        }
        if ($target) {
            $button['target'] = $target;
        }
        $this->pageData['header_buttons'][] = $button;
    }

    /**
     * Show timestamps in breadcrumb bar
     */
    public function showTimestamps(bool $show = true): void {
        $this->pageData['show_timestamps'] = $show;
    }

    /**
     * Load user data from session
     */
    private function loadUserData(): void {
        $uid = $_SESSION["user_id"] ?? null;

        if (!$uid) {
            $this->userData = ['first_name' => 'Guest', 'logged_in' => false];
            return;
        }

        // Try to get user information
        if (function_exists('getUserInformation')) {
            try {
                $ud = getUserInformation($uid);
                if (is_array($ud)) {
                    $this->userData = array_merge($ud, ['logged_in' => true]);
                } elseif (is_object($ud)) {
                    $this->userData = [
                        'first_name' => $ud->first_name ?? 'User',
                        'logged_in' => true
                    ];
                } else {
                    $this->userData = ['first_name' => 'User', 'logged_in' => true];
                }
            } catch (Throwable $e) {
                error_log("CIS Classic Theme: Error loading user info - " . $e->getMessage());
                $this->userData = ['first_name' => 'User', 'logged_in' => true];
            }
        } else {
            $this->userData = ['first_name' => 'User', 'logged_in' => true];
        }
    }

    /**
     * Render a theme component
     */
    public function render(string $component, array $data = []): void {
        $componentFile = __DIR__ . '/components/' . $component . '.php';

        if (!file_exists($componentFile)) {
            echo "<!-- CIS Classic Theme: Component '$component' not found -->";
            return;
        }

        // Make data available to component
        extract(array_merge($this->pageData, $data));
        $theme = $this;
        $config = $this->config;
        $userData = $this->userData;

        include $componentFile;
    }

    /**
     * Get configuration value
     */
    public function getConfig(string $key, $default = null) {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get user data
     */
    public function getUserData(string $key = null, $default = null) {
        if ($key === null) {
            return $this->userData;
        }
        return $this->userData[$key] ?? $default;
    }

    /**
     * Check if user has permission
     */
    public function hasPermission(string $permission): bool {
        // TODO: Implement actual permission checking
        // For now, return true if logged in
        return $this->userData['logged_in'] ?? false;
    }

    /**
     * Set page subtitle (displayed in action bar)
     */
    public function setPageSubtitle(string $subtitle): void {
        $this->pageData['page_subtitle'] = $subtitle;
    }

    /**
     * Get page subtitle
     */
    public function getPageSubtitle(): string {
        return $this->pageData['page_subtitle'] ?? '';
    }
}
