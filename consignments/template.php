<?php
/**
 * Consignments Module Template Wrapper
 *
 * This is the default template system for the Consignments module.
 * Uses CIS Classic Theme (V1) for consistent styling across all pages.
 *
 * Usage in your view files:
 *
 *   <?php
 *   require_once __DIR__ . '/template.php';
 *   $template = new ConsignmentsTemplate();
 *   $template->setTitle('Transfer Manager');
 *   $template->setCurrentPage('consignments/transfers');
 *   $template->startContent();
 *   ?>
 *
 *   <!-- YOUR PAGE CONTENT HERE (only the main container content) -->
 *   <div class="container-fluid">
 *       <h1>Your Content</h1>
 *   </div>
 *
 *   <?php
 *   $template->endContent();
 *   ?>
 *
 * @package Consignments
 * @version 1.0.0
 */

// Ensure session is started
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Load CIS Classic Theme
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/themes/cis-classic/theme.php';

class ConsignmentsTemplate {

    private $theme;
    private $contentStarted = false;

    /**
     * Constructor - Initialize the theme
     */
    public function __construct(array $options = []) {
        // Initialize CIS Classic Theme
        $this->theme = new CISClassicTheme($options);

        // Set default title
        $this->theme->setTitle('Consignments - The Vape Shed CIS');

        // Set default current page for menu highlighting
        $this->theme->setCurrentPage('consignments');
    }

    /**
     * Set page title
     */
    public function setTitle(string $title): self {
        $fullTitle = $title . ' - Consignments - The Vape Shed CIS';
        $this->theme->setTitle($fullTitle);
        return $this;
    }

    /**
     * Set current page for menu highlighting
     */
    public function setCurrentPage(string $page): self {
        $this->theme->setCurrentPage($page);
        return $this;
    }

    /**
     * Add extra CSS to the head
     */
    public function addCSS(string $cssPath): self {
        $this->theme->addHeadContent('<link href="' . htmlspecialchars($cssPath) . '" rel="stylesheet">' . PHP_EOL);
        return $this;
    }

    /**
     * Add extra JavaScript to the head
     */
    public function addJS(string $jsPath): self {
        $this->theme->addHeadContent('<script src="' . htmlspecialchars($jsPath) . '"></script>' . PHP_EOL);
        return $this;
    }

    /**
     * Add inline CSS
     */
    public function addInlineCSS(string $css): self {
        $this->theme->addHeadContent('<style>' . $css . '</style>' . PHP_EOL);
        return $this;
    }

    /**
     * Add inline JavaScript
     */
    public function addInlineJS(string $js): self {
        $this->theme->addHeadContent('<script>' . $js . '</script>' . PHP_EOL);
        return $this;
    }

    /**
     * Start content rendering
     * This outputs the header, sidebar, and opens the main content area
     */
    public function startContent(): void {
        if ($this->contentStarted) {
            return;
        }

        $this->contentStarted = true;

        // Add consignments-specific CSS
        $this->addDefaultAssets();

        // Render HTML head
        $this->theme->render('html-head');

        // Render header (includes <body> tag and app structure start)
        $this->theme->render('header');

        // Render sidebar
        $this->theme->render('sidebar');

        // Render main content start
        $this->theme->render('main-start');

        // Add breadcrumbs container
        echo '<div class="container-fluid">' . PHP_EOL;
        echo '  <div class="animated fadeIn">' . PHP_EOL;
    }

    /**
     * End content rendering
     * This closes the main content area and outputs the footer
     */
    public function endContent(): void {
        if (!$this->contentStarted) {
            return;
        }

        // Close breadcrumbs container
        echo '  </div><!-- .animated -->' . PHP_EOL;
        echo '</div><!-- .container-fluid -->' . PHP_EOL;

        // Render footer (includes closing tags)
        $this->theme->render('footer');

        // Add consignments-specific footer scripts
        $this->addFooterScripts();

        // Close HTML
        echo '</body>' . PHP_EOL;
        echo '</html>' . PHP_EOL;
    }

    /**
     * Add default assets (CSS/JS) for consignments module
     */
    private function addDefaultAssets(): void {
        // Add consignments-specific styling
        $this->addInlineCSS('
            /* Consignments Module Styling */
            .consignment-card {
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                margin-bottom: 1.5rem;
                transition: transform 0.2s, box-shadow 0.2s;
            }

            .consignment-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            }

            .status-badge {
                padding: 0.35rem 0.75rem;
                border-radius: 20px;
                font-size: 0.85rem;
                font-weight: 600;
                text-transform: uppercase;
            }

            .status-draft { background: #6c757d; color: white; }
            .status-pending { background: #ffc107; color: #212529; }
            .status-sent { background: #17a2b8; color: white; }
            .status-received { background: #28a745; color: white; }
            .status-cancelled { background: #dc3545; color: white; }

            .ai-badge {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 0.25rem 0.6rem;
                border-radius: 12px;
                font-size: 0.75rem;
                font-weight: 600;
                display: inline-flex;
                align-items: center;
                gap: 0.3rem;
            }

            .ai-button {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                color: white;
                padding: 0.5rem 1rem;
                border-radius: 6px;
                font-weight: 600;
                transition: all 0.2s;
                cursor: pointer;
            }

            .ai-button:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                color: white;
            }

            .anomaly-alert {
                border-left: 4px solid #dc3545;
                background: #fff5f5;
                padding: 1rem;
                margin-bottom: 1rem;
                border-radius: 4px;
            }

            .anomaly-warning {
                border-left: 4px solid #ffc107;
                background: #fffbf0;
                padding: 1rem;
                margin-bottom: 1rem;
                border-radius: 4px;
            }

            .anomaly-info {
                border-left: 4px solid #17a2b8;
                background: #f0f9ff;
                padding: 1rem;
                margin-bottom: 1rem;
                border-radius: 4px;
            }
        ');
    }

    /**
     * Add footer scripts
     */
    private function addFooterScripts(): void {
        // Module-specific JavaScript (CIS.Core and CIS.ErrorHandler already loaded from BASE)
        echo '<script>' . PHP_EOL;
        echo <<<'JAVASCRIPT'
// Consignments Module Extensions
(function() {
    'use strict';

    // Extend CIS namespace for Consignments
    window.CIS = window.CIS || {};
    window.CIS.Consignments = {

        // Module-specific utilities (inherits from CIS.Core)
        version: '1.0.0',

        // Quick access to core utilities
        ajax: (url, options) => CIS.Core.ajax(url, options),
        get: (url, params) => CIS.Core.get(url, params),
        post: (url, data) => CIS.Core.post(url, data),

        // Format helpers (inherits from CIS.Core)
        formatCurrency: (amount) => CIS.Core.formatCurrency(amount),
        formatDate: (date) => CIS.Core.formatDate(date),
        formatDateTime: (date) => CIS.Core.formatDateTime(date),

        // User feedback (uses CIS.ErrorHandler)
        toast: (message, type, details) => CIS.Core.toast(message, type, details),
        confirm: (message, callback, cancelCallback) => CIS.Core.confirm(message, callback, cancelCallback),

        // Loading states
        showLoading: (message) => CIS.Core.showLoading(message),
        hideLoading: () => CIS.Core.hideLoading(),

        // Storage helpers
        store: (key, value) => CIS.Core.store(key, value, 'cis_consignments_'),
        retrieve: (key, defaultValue) => CIS.Core.retrieve(key, defaultValue, 'cis_consignments_'),
        forget: (key) => CIS.Core.forget(key, 'cis_consignments_'),

        // Advanced features
        logger: CIS.Core.createLogger('Consignments'),

        // Consignment-specific utilities
        getStatusColor(status) {
            const colors = {
                draft: '#6c757d',
                pending: '#ffc107',
                sent: '#17a2b8',
                received: '#28a745',
                cancelled: '#dc3545'
            };
            return colors[status] || colors.draft;
        },

        getStatusBadge(status) {
            return `<span class="status-badge status-${status}">${status}</span>`;
        },

        // AI helper
        askAI(question, context = {}) {
            return this.post('api/ai-assistant.php?action=ask', {
                question: question,
                context: context
            });
        },

        // Carrier recommendation
        getCarrierRecommendation(transferData) {
            return this.post('api/ai-assistant.php?action=recommend-carrier', {
                transfer: transferData
            });
        },

        // Transfer analysis
        analyzeTransfer(consignmentId) {
            return this.post('api/ai-assistant.php?action=analyze-transfer', {
                consignment_id: consignmentId
            });
        }
    };

    // Backward compatibility alias
    window.ConsignmentsApp = window.CIS.Consignments;

    // Log initialization
    CIS.Consignments.logger.info('✅ Consignments Module initialized');

    // Feature detection
    if (CIS.Core.getConfig('debug')) {
        console.group('🎯 Consignments Module Features');
        console.log('✓ AI Assistant integration');
        console.log('✓ Carrier recommendations');
        console.log('✓ Transfer analysis');
        console.log('✓ Natural language Q&A');
        console.log('✓ Cost predictions');
        console.log('✓ Inherits all CIS.Core features');
        console.groupEnd();
    }
})();
JAVASCRIPT;
        echo PHP_EOL . '</script>' . PHP_EOL;
    }    /**
     * Get the underlying theme instance (for advanced usage)
     */
    public function getTheme(): CISClassicTheme {
        return $this->theme;
    }

    /**
     * Render a complete page with content
     * Useful for simple pages where you just want to pass content
     */
    public function renderPage(string $content, array $options = []): void {
        if (isset($options['title'])) {
            $this->setTitle($options['title']);
        }
        if (isset($options['currentPage'])) {
            $this->setCurrentPage($options['currentPage']);
        }

        $this->startContent();
        echo $content;
        $this->endContent();
    }
}
