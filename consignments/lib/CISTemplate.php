<?php
/**
 * CIS Template Wrapper for Consignments Module
 * Applies the standard CIS admin template to consignments pages
 */

class CISTemplate {
    private $title = 'Consignments';
    private $breadcrumbs = [];
    private $content = '';
    private $theme = 'cis'; // default legacy theme; can be switched to 'cis-v2'

    public function __construct() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    public function setTitle($title) {
        $this->title = $title;
    }

    public function setBreadcrumbs($breadcrumbs) {
        $this->breadcrumbs = $breadcrumbs;
    }

    public function setTheme($theme) {
        $allowed = ['cis', 'cis-v2'];
        if (in_array($theme, $allowed, true)) {
            $this->theme = $theme;
        }
    }

    public function startContent() {
        ob_start();
    }

    public function endContent() {
        $this->content = ob_get_clean();
    }

    public function render() {
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

    private function renderHeader() {
    // Set page title for base template
        $pageTitle = htmlspecialchars($this->title);

        // Ensure $pdo is available for templates (from CIS\Base\Database)
        global $pdo;
        if (!isset($pdo)) {
            $pdo = \CIS\Base\Database::pdo();
        }

        // Load the selected theme root (module-isolated)
        $baseDir = dirname(__DIR__, 2);
        $templatePath = $baseDir . '/base/themes/cis';
        $templateV2 = $baseDir . '/base/templates/themes/cis-v2';
        $useV2 = ($this->theme === 'cis-v2' && is_dir($templateV2));

        // Inject page-specific <head> assets safely via $extraHead
        $extraHead = '';
        if (strpos($this->title, 'Transfer') !== false) {
            // Bootstrap Icons (required by Transfer Manager markup: .bi classes)
            $extraHead .= '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">' . "\n";
            // IMPORTANT: Do NOT link CSS files directly. We inject Transfer Manager CSS as an internal JS doc
            // to avoid any global leakage into CIS. Load the style injector early in <head>.
            $extraHead .= '<script src="/modules/consignments/TransferManager/js/00a-style-inject.js?v=20251110" defer></script>' . "\n";
        }

        // Disable legacy footer JS on pages that inject their own modern stack
        $GLOBALS['DISABLE_LEGACY_FOOTER_JS'] = (strpos($this->title, 'Transfer') !== false);

        if ($useV2) {
            // V2 HEAD
            $head = $templateV2 . '/components/head.php';
            if (file_exists($head)) require $head; // emits <html><head>...</head>
            // V2 HEADER
            $hdr = $templateV2 . '/components/header.php';
            if (file_exists($hdr)) require $hdr; // emits <body> + header + optional breadcrumbs
            // V2 SIDEBAR + open containers
            $sidebar = $templateV2 . '/components/sidebar.php';
            echo '<div class="cisv2-app d-flex">';
            if (file_exists($sidebar)) require $sidebar;
            echo '<main id="cisv2-main" class="flex-fill">';
        } else {
            // Legacy CIS
            if (file_exists($templatePath . '/html-header.php')) {
                require $templatePath . '/html-header.php';
            }

            if (file_exists($templatePath . '/header.php')) {
                require $templatePath . '/header.php';
            }

            if (file_exists($templatePath . '/sidemenu.php')) {
                echo '<div class="app-body">';
                require $templatePath . '/sidemenu.php';
                // Offset main content to the right of fixed sidebar (CoreUI normally does this via CSS)
                echo '<main class="main" style="margin-left:256px;">';
            }
        }

    // Note: page-specific CSS already injected into <head> via $extraHead above.
    // Main content container is opened above when sidemenu is included.
    }

            private function renderBreadcrumbs() {
        if (empty($this->breadcrumbs)) return;
                ?>
                <div class="app-breadcrumb" style="background:#fff;border-bottom:1px solid #c8ced3;padding:0.5rem 1rem;">
                    <nav aria-label="breadcrumb" class="mb-0">
          <ol class="breadcrumb">
            <?php foreach ($this->breadcrumbs as $crumb): ?>
              <?php if (isset($crumb['active']) && $crumb['active']): ?>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($crumb['label']); ?></li>
              <?php else: ?>
                <li class="breadcrumb-item">
                  <a href="<?php echo htmlspecialchars($crumb['url']); ?>">
                    <?php if (isset($crumb['icon'])): ?><i class="fas <?php echo $crumb['icon']; ?> mr-1"></i><?php endif; ?>
                    <?php echo htmlspecialchars($crumb['label']); ?>
                  </a>
                </li>
              <?php endif; ?>
            <?php endforeach; ?>
          </ol>
                    </nav>
                </div>
        <?php
    }

    private function renderFooter() {
        ?>
    </main><!-- /.main -->
</div><!-- /.app-body -->
        <?php

        // Add Transfer Manager JS if this is the transfer manager page
        if (strpos($this->title, 'Transfer') !== false) {
            $jsFiles = [
                // CSS is injected by 00a-style-inject.js loaded in <head> (defer). Keep JS execution order below.
                '/modules/consignments/TransferManager/js/00-config-init.js',
                // Bootstrap shim: expose bootstrap.Modal/Toast when only BS4 is present
                '/modules/consignments/TransferManager/js/00b-bs-adapter.js',
                // Use CoreUI (BS4-compatible) from footer; do not include separate Bootstrap here.
                '/modules/consignments/TransferManager/js/01-core-helpers.js',
                '/modules/consignments/TransferManager/js/02-ui-components.js',
                '/modules/consignments/TransferManager/js/03-transfer-functions.js',
                '/modules/consignments/TransferManager/js/04-list-refresh.js',
                '/modules/consignments/TransferManager/js/05-detail-modal.js',
                '/modules/consignments/TransferManager/js/06-event-listeners.js',
                '/modules/consignments/TransferManager/js/07-init.js',
                '/modules/consignments/TransferManager/js/08-dom-ready.js'
            ];

            foreach ($jsFiles as $jsFile) {
                echo '<script src="' . htmlspecialchars($jsFile) . '"></script>' . "\n";
            }
        }
        
        // Load the selected theme footer assets and close tags
        $baseDir = dirname(__DIR__, 2);
        $templatePath = $baseDir . '/base/themes/cis';
        $templateV2 = $baseDir . '/base/templates/themes/cis-v2';
        $useV2 = ($this->theme === 'cis-v2' && is_dir($templateV2));

        if ($useV2) {
            // Close main + app container and include scripts
            echo '</main></div>';
            $scripts = $templateV2 . '/components/scripts.php';
            if (file_exists($scripts)) require $scripts;
        } else {
            if (file_exists($templatePath . '/footer.php')) {
                require $templatePath . '/footer.php';
            }

            if (file_exists($templatePath . '/html-footer.php')) {
                require $templatePath . '/html-footer.php';
            }
        }
    }
}
