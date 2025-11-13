<?php

/**
 * CIS Template Wrapper for Consignments Module
 * Applies the standard CIS admin template to consignments pages
 */

namespace Modules\Consignments;

// Adjust if different namespace convention

class CISTemplate
{
    private $title = 'Consignments';
    private $breadcrumbs = [];
    private $content = '';
    private array $headAssets = [];
    private array $inlineHeadBlocks = [];
    private array $footerScripts = [];

    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function setBreadcrumbs($breadcrumbs)
    {
        $this->breadcrumbs = $breadcrumbs;
    }

    // Register external CSS/JS (will be emitted in <head>)
    public function addHeadCSS(string $href): self
    {
        $this->headAssets[] = $this->buildCssTag($href);
        return $this;
    }
    public function addHeadJS(string $src, bool $defer = false): self
    {
        $attr = $defer ? ' defer' : '';
        $this->headAssets[] = $this->buildJsTag($src, $defer);
        return $this;
    }
    public function addInlineHead(string $code, string $type = 'css'): self
    {
        if ($type === 'css') {
            $this->inlineHeadBlocks[] = '<style>' . $code . '</style>';
        } elseif ($type === 'js') {
            $this->inlineHeadBlocks[] = '<script>' . $code . '</script>';
        }
        return $this;
    }
    public function addFooterScript(string $src): self
    {
        $this->footerScripts[] = $src;
        return $this;
    }

    public function startContent()
    {
        ob_start();
    }

    public function endContent()
    {
        $this->content = ob_get_clean();
    }

    public function render()
    {
        // Include header
        // Expose breadcrumbs to the theme header so it can render the second-layer inside the header
        $GLOBALS['CIS_BREADCRUMBS_DATA'] = $this->breadcrumbs;
        $this->renderHeader();

        // Breadcrumbs are rendered inside the header; do not duplicate here.

        // Render content
        echo $this->content;

        // Include footer
        $this->renderFooter();
    }

    private function renderHeader()
    {
        // Set page title for base template
        $pageTitle = htmlspecialchars($this->title);

        // Ensure $pdo is available for templates (from CIS\Base\Database)
        global $pdo;
        if (!isset($pdo)) {
            $pdo = \CIS\Base\Database::pdo();
        }

        // Load the module-isolated CIS theme (do not depend on global assets/template)
        $templatePath = dirname(__DIR__, 2) . '/base/themes/cis';

        // Transfer Manager special assets (add to headAssets)
        if (strpos($this->title, 'Transfer') !== false) {
            $this->addHeadCSS('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css');
            $this->addHeadJS('/modules/consignments/TransferManager/js/00a-style-inject.js?v=20251110', true);
        }

        // Disable legacy footer JS on pages that inject their own modern stack
        $GLOBALS['DISABLE_LEGACY_FOOTER_JS'] = (strpos($this->title, 'Transfer') !== false);

        if (file_exists($templatePath . '/html-header.php')) {
            require $templatePath . '/html-header.php';
        }

        // Auto-enqueue ordered assets before emitting any registered head assets
        $this->autoEnqueueAssets();

        // Emit registered head assets after base html-header (so charset/meta remain first)
        if (!empty($this->headAssets) || !empty($this->inlineHeadBlocks)) {
            echo "<!-- Consignments Head Assets -->\n";
            foreach ($this->headAssets as $tag) {
                echo $tag . "\n";
            }
            foreach ($this->inlineHeadBlocks as $block) {
                echo $block . "\n";
            }
        }

        if (file_exists($templatePath . '/header.php')) {
            require $templatePath . '/header.php';
        }

        if (file_exists($templatePath . '/sidemenu.php')) {
            echo '<div class="app-body">';
            require $templatePath . '/sidemenu.php';
            echo '<main class="main">';
            // Open content wrappers first, then render breadcrumbs so it aligns with content grid
            echo '<div class="container-fluid"><div class="animated fadeIn">';
            $this->renderBreadcrumbs();
        }

        // Note: page-specific CSS already injected into <head> via $extraHead above.
        // Main content container is opened above when sidemenu is included.
    }

    /**
     * Auto-enqueue ordered CSS/JS assets.
     * Load from:
     *  - Module CSS:  /modules/consignments/assets/css/*.css (sorted)
     *  - Theme CSS:   /modules/base/themes/cis/assets/css/*.css (sorted)
     *  - Theme JS:    /modules/base/themes/cis/assets/js/*.js (sorted, added as footer scripts)
     *  - Module JS:   /modules/consignments/assets/js/*.js (sorted, added as footer scripts)
     */
    private function autoEnqueueAssets(): void
    {
        $root = dirname(__DIR__, 3); // points to .../public_html
        $paths = [
            // CSS first (module then theme or theme then module? Choose theme first to allow module overrides)
            ['type' => 'css', 'dir' => $root . '/modules/base/themes/cis/assets/css', 'url' => '/modules/base/themes/cis/assets/css'],
            ['type' => 'css', 'dir' => $root . '/modules/consignments/assets/css', 'url' => '/modules/consignments/assets/css'],
            // JS last
            ['type' => 'js',  'dir' => $root . '/modules/base/themes/cis/assets/js', 'url' => '/modules/base/themes/cis/assets/js'],
            ['type' => 'js',  'dir' => $root . '/modules/consignments/assets/js', 'url' => '/modules/consignments/assets/js'],
        ];

        foreach ($paths as $p) {
            if (!is_dir($p['dir'])) {
                continue;
            }
            $files = glob($p['dir'] . '/*.{' . ($p['type'] === 'css' ? 'css' : 'js') . '}', GLOB_BRACE);
            if (!$files) {
                continue;
            }
            natcasesort($files);
            foreach ($files as $abs) {
                $basename = basename($abs);
                $url = rtrim($p['url'], '/') . '/' . $basename;
                if ($p['type'] === 'css') {
                    $this->addHeadCSS($url);
                } else {
                    $this->addFooterScript($url);
                }
            }
        }
    }

    private function renderBreadcrumbs(): void
    {
        if (empty($this->breadcrumbs)) {
            return;
        }
        echo '<div class="row align-items-center">';
        echo '<div class="col-md">';
        echo '<nav aria-label="breadcrumb" class="cis-breadcrumb-wrapper mb-3">';
        echo '<ol class="breadcrumb mb-0 py-2 px-3 rounded-0 border">';
        foreach ($this->breadcrumbs as $crumb) {
            $label = htmlspecialchars($crumb['label'] ?? '', ENT_QUOTES, 'UTF-8');
            $url = $crumb['url'] ?? null;
            if ($url) {
                $href = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                echo '<li class="breadcrumb-item"><a href="' . $href . '">' . $label . '</a></li>';
            } else {
                echo '<li class="breadcrumb-item active" aria-current="page">' . $label . '</li>';
            }
        }
        echo '</ol>';
        echo '</nav>';
        echo '</div>';
        echo '<div class="col-md-auto d-none d-md-block">';
        $quickSearchPath = __DIR__ . '/../../base/themes/cis/quick-product-search.php';
        if (is_file($quickSearchPath)) {
            include $quickSearchPath;
        }
        echo '</div>';
        echo '</div>';
    }

    private function renderFooter()
    {
        echo '</div></div>'; // close animated fadeIn + container-fluid
        echo '</main><!-- /.main -->';
        echo '</div><!-- /.app-body -->';

        // Add Transfer Manager JS if this is the transfer manager page
        if (strpos($this->title, 'Transfer') !== false) {
            $transferJs = [
                '/modules/consignments/TransferManager/js/00-config-init.js',
                '/modules/consignments/TransferManager/js/00b-bs-adapter.js',
                '/modules/consignments/TransferManager/js/01-core-helpers.js',
                '/modules/consignments/TransferManager/js/02-ui-components.js',
                '/modules/consignments/TransferManager/js/03-transfer-functions.js',
                '/modules/consignments/TransferManager/js/04-list-refresh.js',
                '/modules/consignments/TransferManager/js/05-detail-modal.js',
                '/modules/consignments/TransferManager/js/06-event-listeners.js',
                '/modules/consignments/TransferManager/js/07-init.js',
                '/modules/consignments/TransferManager/js/08-dom-ready.js'
            ];
            foreach ($transferJs as $src) {
                $this->addFooterScript($src);
            }
        }

        // Optionally include jQuery from CDN with SRI (if not already available)
        $this->ensureJquery();

        // Emit registered footer scripts
        foreach ($this->footerScripts as $src) {
            echo $this->buildJsTag($src) . "\n";
        }

        // Load the module theme footer
        $templatePath = dirname(__DIR__, 2) . '/base/themes/cis';

        if (file_exists($templatePath . '/footer.php')) {
            require $templatePath . '/footer.php';
        }

        if (file_exists($templatePath . '/html-footer.php')) {
            require $templatePath . '/html-footer.php';
        }
    }

    private function buildVersionedUrl(string $url): string
    {
        // Append checksum/version for local files only
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        $root = dirname(__DIR__, 3);
        $abs = $root . $url;
        if (is_file($abs)) {
            $hash = substr(sha1_file($abs) ?: '', 0, 10);
            if ($hash) {
                $sep = (strpos($url, '?') === false) ? '?' : '&';
                return $url . $sep . 'v=' . $hash;
            }
        }
        return $url;
    }

    private function buildCssTag(string $href): string
    {
        $href = $this->toAbsoluteUrl($this->buildVersionedUrl($href));
        $href = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
        return '<link rel="stylesheet" href="' . $href . '">';
    }

    private function buildJsTag(string $src, bool $defer = false): string
    {
        $srcVer = $this->buildVersionedUrl($src);
        $srcVer = $this->toAbsoluteUrl($srcVer);
        $srcAttr = htmlspecialchars($srcVer, ENT_QUOTES, 'UTF-8');
        $attr = $defer ? ' defer' : '';
        $sri = $this->computeSriIfLocal($src);
        $sriAttr = $sri ? ' integrity="' . htmlspecialchars($sri, ENT_QUOTES, 'UTF-8') . '" crossorigin="anonymous"' : '';
        return '<script src="' . $srcAttr . '"' . $attr . $sriAttr . '></script>';
    }

    /**
     * Convert local URLs like "/path/file.js" to absolute URLs using detected scheme and host.
     * Returns original string for already-absolute URLs or when host cannot be determined.
     */
    private function toAbsoluteUrl(string $url): string
    {
        // Already absolute (http, https, protocol-relative)
        if (preg_match('#^(?:https?:)?//#i', $url)) {
            return $url;
        }
        // Only convert root-relative paths
        if (isset($url[0]) && $url[0] === '/') {
            // Prefer APP_URL env if available
            $appUrl = getenv('APP_URL');
            if ($appUrl) {
                return rtrim($appUrl, '/') . $url;
            }
            // Detect scheme behind proxies
            $scheme = 'http';
            if (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https')
            ) {
                $scheme = 'https';
            }
            $host = $_SERVER['HTTP_HOST'] ?? '';
            if ($host !== '') {
                return $scheme . '://' . $host . $url;
            }
        }
        return $url;
    }

    private function computeSriIfLocal(string $url): ?string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return null;
        }
        $root = dirname(__DIR__, 3);
        $abs = $root . $url;
        if (!is_file($abs)) {
            return null;
        }
        $data = file_get_contents($abs);
        if ($data === false) {
            return null;
        }
        // Use SHA384 per common SRI guidance
        return 'sha384-' . base64_encode(hash('sha384', $data, true));
    }

    private function ensureJquery(): void
    {
        // If window.jQuery is not present, include a CDN copy with SRI
        $snippet = <<<'HTML'
<script>
if (!window.jQuery) {
    document.write('<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"><\/script>');
}
</script>
HTML;
        echo $snippet . "\n";
    }
}

// -------------------------------------------------------------------------
// Legacy alias: allow `new CISTemplate()` without namespace in older modules
// -------------------------------------------------------------------------
if (!\class_exists('\CISTemplate', false)) {
    \class_alias(__NAMESPACE__ . '\\CISTemplate', '\CISTemplate');
}
