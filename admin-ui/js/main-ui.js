/**
 * Admin UI Main Application Script
 * Orchestrates theme switching, AI configuration, version display, and system monitoring
 *
 * @version 1.0.0
 */

(function() {
    'use strict';

    class AdminUI {
        constructor() {
            this.themeSwitcher = null;
            this.aiConfigPanel = null;
            this.init();
        }

        /**
         * Initialize admin UI
         */
        async init() {
            try {
                // Initialize theme switcher
                this.initThemeSwitcher();

                // Initialize AI config panel
                this.initAIConfigPanel();

                // Load UI data
                await this.loadVersionInfo();
                await this.loadFeatures();
                await this.loadSystemStatus();

                // Attach event listeners
                this.attachEventListeners();

                console.log('Admin UI initialized successfully');
            } catch (e) {
                console.error('Error initializing Admin UI:', e);
            }
        }

        /**
         * Initialize theme switcher
         */
        initThemeSwitcher() {
            if (window.ThemeSwitcher) {
                this.themeSwitcher = new ThemeSwitcher({
                    defaultTheme: document.documentElement.getAttribute('data-theme') || 'vscode-dark',
                    autoSave: true,
                    storageKey: 'admin-ui-theme'
                });

                // Listen for theme changes
                this.themeSwitcher.onThemeChange((detail) => {
                    this.onThemeChanged(detail);
                });
            }
        }

        /**
         * Initialize AI config panel
         */
        initAIConfigPanel() {
            if (window.AIConfigPanel) {
                this.aiConfigPanel = new AIConfigPanel({
                    apiEndpoint: '/modules/admin-ui/api/ai-config-api.php',
                    autoSave: true,
                    storageKey: 'admin-ui-ai-config'
                });

                // Listen for config changes
                this.aiConfigPanel.onConfigChange((detail) => {
                    this.onAIConfigChanged(detail);
                });
            }
        }

        /**
         * Load and display version information
         */
        async loadVersionInfo() {
            try {
                const response = await fetch('/modules/admin-ui/api/version-api.php?action=info');
                const data = await response.json();

                if (data.success) {
                    this.updateVersionDisplay(data);
                }
            } catch (e) {
                console.error('Error loading version info:', e);
            }
        }

        /**
         * Update version display
         */
        updateVersionDisplay(data) {
            const versionInfo = document.getElementById('version-info');
            if (versionInfo) {
                versionInfo.innerHTML = `
                    <span class="version-label">Version</span>
                    <span class="version-number">${data.version}</span>
                    <span class="build-number">Build ${data.build}</span>
                    <span class="build-timestamp" title="${data.release_date}">📅</span>
                `;
            }
        }

        /**
         * Load and display features
         */
        async loadFeatures() {
            try {
                const response = await fetch('/modules/admin-ui/api/version-api.php?action=features');
                const data = await response.json();

                if (data.success && data.features) {
                    this.displayFeatures(data.features);
                }
            } catch (e) {
                console.error('Error loading features:', e);
            }
        }

        /**
         * Display features in grid
         */
        displayFeatures(features) {
            const grid = document.getElementById('features-grid');
            if (!grid) return;

            grid.innerHTML = features.map((feature, index) => `
                <div class="feature-card ${feature.status === 'enabled' ? 'enabled' : 'disabled'}"
                     data-feature-id="${feature.id || index}">
                    <div class="feature-status-icon">
                        ${feature.status === 'enabled' ? '✓' : '○'}
                    </div>
                    <div class="feature-content">
                        <div class="feature-name">${this.escapeHtml(feature.name)}</div>
                        <div class="feature-description">${this.escapeHtml(feature.description || '')}</div>
                        ${feature.url ? `<a href="${this.escapeHtml(feature.url)}" class="feature-link">Open →</a>` : ''}
                    </div>
                </div>
            `).join('');
        }

        /**
         * Load and display system status
         */
        async loadSystemStatus() {
            try {
                const response = await fetch('/modules/admin-ui/api/version-api.php?action=system_status');
                const data = await response.json();

                if (data.success) {
                    this.updateSystemStatus(data);
                }
            } catch (e) {
                console.error('Error loading system status:', e);
            }
        }

        /**
         * Update system status display
         */
        updateSystemStatus(data) {
            const statusIcon = document.getElementById('status-icon');
            const statusText = document.querySelector('.status-text');

            if (statusIcon && statusText) {
                const health = data.health || {};
                const isHealthy = Object.values(health).every(v => v === true);

                if (isHealthy) {
                    statusIcon.className = 'status-indicator healthy';
                    statusText.textContent = 'All Systems Operational';
                    statusIcon.style.color = 'var(--color-success)';
                } else {
                    statusIcon.className = 'status-indicator warning';
                    statusText.textContent = 'Some Services Degraded';
                    statusIcon.style.color = 'var(--color-warning)';
                }
            }
        }

        /**
         * Load and display changelog
         */
        async loadChangelog() {
            try {
                const response = await fetch('/modules/admin-ui/api/version-api.php?action=changelog');
                const data = await response.json();

                if (data.success && data.changelog) {
                    this.displayChangelog(data.changelog);
                }
            } catch (e) {
                console.error('Error loading changelog:', e);
            }
        }

        /**
         * Display changelog
         */
        displayChangelog(changelog) {
            const content = document.getElementById('changelog-content');
            if (!content) return;

            content.innerHTML = changelog.map(release => `
                <div class="changelog-release">
                    <h3>${this.escapeHtml(release.version)}
                        <span class="release-date">(${this.escapeHtml(release.date)})</span>
                    </h3>

                    ${release.features && release.features.length > 0 ? `
                        <div class="changelog-section">
                            <h4>✨ Features</h4>
                            <ul>
                                ${release.features.map(f => `<li>${this.escapeHtml(f)}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}

                    ${release.improvements && release.improvements.length > 0 ? `
                        <div class="changelog-section">
                            <h4>🚀 Improvements</h4>
                            <ul>
                                ${release.improvements.map(i => `<li>${this.escapeHtml(i)}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}

                    ${release.bug_fixes && release.bug_fixes.length > 0 ? `
                        <div class="changelog-section">
                            <h4>🐛 Bug Fixes</h4>
                            <ul>
                                ${release.bug_fixes.map(b => `<li>${this.escapeHtml(b)}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}

                    ${release.known_issues && release.known_issues.length > 0 ? `
                        <div class="changelog-section">
                            <h4>⚠️ Known Issues</h4>
                            <ul>
                                ${release.known_issues.map(k => `<li>${this.escapeHtml(k)}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}
                </div>
            `).join('');
        }

        /**
         * Attach event listeners
         */
        attachEventListeners() {
            // Theme selector button
            const btnThemeSelector = document.getElementById('btn-theme-selector');
            if (btnThemeSelector) {
                btnThemeSelector.addEventListener('click', () => {
                    this.toggleThemeSelector();
                });
            }

            // AI config button
            const btnAIConfig = document.getElementById('btn-ai-config');
            if (btnAIConfig) {
                btnAIConfig.addEventListener('click', () => {
                    this.toggleAIConfig();
                });
            }

            // Changelog button
            const btnChangelog = document.getElementById('btn-changelog');
            if (btnChangelog) {
                btnChangelog.addEventListener('click', () => {
                    this.toggleChangelog();
                });
            }

            // Close buttons
            document.querySelectorAll('.btn-close').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const section = e.target.closest('.panel');
                    if (section) {
                        section.classList.add('hidden');
                    }
                });
            });
        }

        /**
         * Toggle theme selector visibility
         */
        toggleThemeSelector() {
            const section = document.getElementById('theme-section');
            if (!section) return;

            section.classList.toggle('hidden');

            if (!section.classList.contains('hidden')) {
                const container = document.getElementById('theme-selector-container');
                if (container && !container.querySelector('.theme-preview-card')) {
                    if (this.themeSwitcher) {
                        this.themeSwitcher.createThemePreview('theme-selector-container');
                    }
                }
            }
        }

        /**
         * Toggle AI config panel visibility
         */
        toggleAIConfig() {
            const section = document.getElementById('ai-config-section');
            if (!section) return;

            section.classList.toggle('hidden');

            if (!section.classList.contains('hidden')) {
                const container = document.getElementById('ai-config-panel-container');
                if (container && !container.querySelector('.ai-config-panel')) {
                    if (this.aiConfigPanel) {
                        this.aiConfigPanel.createConfigPanel('ai-config-panel-container');
                    }
                }
            }
        }

        /**
         * Toggle changelog visibility
         */
        toggleChangelog() {
            const section = document.getElementById('changelog-section');
            if (!section) return;

            section.classList.toggle('hidden');

            if (!section.classList.contains('hidden')) {
                const content = document.getElementById('changelog-content');
                if (content && content.textContent.includes('Loading')) {
                    this.loadChangelog();
                }
            }
        }

        /**
         * Handle theme change event
         */
        onThemeChanged(detail) {
            console.log('Theme changed to:', detail.theme);
            this.showNotification(`Theme switched to ${detail.theme}`, 'success');
        }

        /**
         * Handle AI config change event
         */
        onAIConfigChanged(detail) {
            console.log('AI config changed:', detail);
            this.showNotification(`AI agent updated: ${detail.agent}`, 'info');
        }

        /**
         * Show notification
         */
        showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                padding: 16px 24px;
                background-color: var(--color-secondary);
                color: var(--color-text);
                border-radius: 6px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                z-index: 10000;
                animation: slideIn 0.3s ease-in-out;
            `;

            document.body.appendChild(notification);

            // Auto-remove after 3 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-in-out';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', () => {
        window.adminUI = new AdminUI();
    });

    // Add animation styles
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .notification {
            backdrop-filter: blur(10px);
        }

        .notification-success {
            border-left: 4px solid var(--color-success);
        }

        .notification-error {
            border-left: 4px solid var(--color-error);
        }

        .notification-warning {
            border-left: 4px solid var(--color-warning);
        }

        .notification-info {
            border-left: 4px solid var(--color-accent);
        }
    `;
    document.head.appendChild(style);

})();
