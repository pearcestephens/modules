/**
 * Theme Switcher - Handles dynamic theme switching
 * Supports 3 themes: VS Code Dark, Light, High Contrast
 *
 * @version 1.0.0
 */

class ThemeSwitcher {
    constructor(options = {}) {
        this.themes = {
            'vscode-dark': {
                name: 'VS Code Dark',
                primary: '#1e1e1e',
                secondary: '#252526',
                accent: '#007acc',
                text: '#d4d4d4',
                text_secondary: '#858585',
                success: '#4ec9b0',
                warning: '#dcdcaa',
                error: '#f48771',
                background: '#1e1e1e',
                border: '#3e3e42',
            },
            'light': {
                name: 'Light',
                primary: '#ffffff',
                secondary: '#f3f3f3',
                accent: '#0066cc',
                text: '#333333',
                text_secondary: '#666666',
                success: '#107c10',
                warning: '#ffb900',
                error: '#d13438',
                background: '#ffffff',
                border: '#d1d1d1',
            },
            'high-contrast': {
                name: 'High Contrast',
                primary: '#000000',
                secondary: '#1a1a1a',
                accent: '#ffff00',
                text: '#ffffff',
                text_secondary: '#cccccc',
                success: '#00ff00',
                warning: '#ffff00',
                error: '#ff0000',
                background: '#000000',
                border: '#ffffff',
            },
        };

        this.currentTheme = options.defaultTheme || 'vscode-dark';
        this.storageKey = options.storageKey || 'admin-ui-theme';
        this.autoSave = options.autoSave !== false;

        // Load saved preference
        this.loadThemePreference();

        // Initialize theme
        this.applyTheme(this.currentTheme);
    }

    /**
     * Get available themes
     */
    getAvailableThemes() {
        return Object.entries(this.themes).map(([key, value]) => ({
            id: key,
            name: value.name,
            colors: value,
        }));
    }

    /**
     * Switch to a specific theme
     */
    switchTheme(themeId) {
        if (!this.themes[themeId]) {
            console.error(`Theme not found: ${themeId}`);
            return false;
        }

        this.currentTheme = themeId;
        this.applyTheme(themeId);

        if (this.autoSave) {
            this.saveThemePreference();
        }

        // Dispatch event
        window.dispatchEvent(new CustomEvent('theme-changed', {
            detail: { theme: themeId, colors: this.themes[themeId] }
        }));

        return true;
    }

    /**
     * Apply theme by updating CSS variables
     */
    applyTheme(themeId) {
        const theme = this.themes[themeId];

        if (!theme) {
            console.error(`Theme not found: ${themeId}`);
            return;
        }

        const root = document.documentElement;

        Object.entries(theme).forEach(([key, value]) => {
            const cssVarName = `--color-${key.toLowerCase().replace(/_/g, '-')}`;
            root.style.setProperty(cssVarName, value);
        });

        // Update body class
        document.body.className = document.body.className
            .replace(/theme-\w+/g, '')
            .trim();
        document.body.classList.add(`theme-${themeId}`);

        // Store current theme info in data attribute
        document.documentElement.setAttribute('data-theme', themeId);
        document.documentElement.setAttribute('data-theme-name', theme.name);

        console.log(`Theme applied: ${themeId}`);
    }

    /**
     * Save theme preference to localStorage
     */
    saveThemePreference() {
        try {
            localStorage.setItem(this.storageKey, this.currentTheme);
        } catch (e) {
            console.warn('Failed to save theme preference:', e);
        }
    }

    /**
     * Load theme preference from localStorage
     */
    loadThemePreference() {
        try {
            const saved = localStorage.getItem(this.storageKey);
            if (saved && this.themes[saved]) {
                this.currentTheme = saved;
            }
        } catch (e) {
            console.warn('Failed to load theme preference:', e);
        }
    }

    /**
     * Get current theme
     */
    getCurrentTheme() {
        return {
            id: this.currentTheme,
            ...this.themes[this.currentTheme],
        };
    }

    /**
     * Get CSS variables for current theme
     */
    getCSSVariables() {
        const vars = {};
        const theme = this.themes[this.currentTheme];

        Object.entries(theme).forEach(([key, value]) => {
            const cssVarName = `--color-${key.toLowerCase().replace(/_/g, '-')}`;
            vars[cssVarName] = value;
        });

        return vars;
    }

    /**
     * Create theme selector HTML element
     */
    createThemeSelector(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const selector = document.createElement('div');
        selector.className = 'theme-selector';
        selector.innerHTML = `
            <label for="theme-select">Theme:</label>
            <select id="theme-select" class="theme-select">
                ${this.getAvailableThemes()
                    .map(theme => `
                    <option value="${theme.id}" ${this.currentTheme === theme.id ? 'selected' : ''}>
                        ${theme.name}
                    </option>
                `)
                    .join('')}
            </select>
        `;

        container.appendChild(selector);

        // Add event listener
        const select = container.querySelector('#theme-select');
        select.addEventListener('change', (e) => {
            this.switchTheme(e.target.value);
        });

        return selector;
    }

    /**
     * Create theme preview cards
     */
    createThemePreview(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const preview = document.createElement('div');
        preview.className = 'theme-preview-container';

        this.getAvailableThemes().forEach(theme => {
            const card = document.createElement('div');
            card.className = `theme-preview-card ${this.currentTheme === theme.id ? 'active' : ''}`;
            card.innerHTML = `
                <div class="theme-preview-colors">
                    <div class="color-swatch" style="background-color: ${theme.colors.primary}"></div>
                    <div class="color-swatch" style="background-color: ${theme.colors.accent}"></div>
                    <div class="color-swatch" style="background-color: ${theme.colors.success}"></div>
                    <div class="color-swatch" style="background-color: ${theme.colors.error}"></div>
                </div>
                <div class="theme-preview-name">${theme.name}</div>
            `;

            card.addEventListener('click', () => {
                this.switchTheme(theme.id);
                // Update active state
                document.querySelectorAll('.theme-preview-card').forEach(c => c.classList.remove('active'));
                card.classList.add('active');
            });

            preview.appendChild(card);
        });

        container.appendChild(preview);
        return preview;
    }

    /**
     * Export theme as CSS
     */
    exportThemeAsCSS(themeId) {
        const theme = this.themes[themeId || this.currentTheme];
        if (!theme) return '';

        let css = `:root.theme-${themeId || this.currentTheme} {\n`;

        Object.entries(theme).forEach(([key, value]) => {
            const cssVarName = `--color-${key.toLowerCase().replace(/_/g, '-')}`;
            css += `  ${cssVarName}: ${value};\n`;
        });

        css += '}\n';
        return css;
    }

    /**
     * Listen for theme changes
     */
    onThemeChange(callback) {
        window.addEventListener('theme-changed', (e) => {
            callback(e.detail);
        });
    }

    /**
     * Detect system theme preference
     */
    detectSystemTheme() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'vscode-dark';
        }
        return 'light';
    }

    /**
     * Apply system theme if available
     */
    applySystemTheme(fallback = true) {
        const systemTheme = this.detectSystemTheme();
        if (fallback && systemTheme) {
            this.switchTheme(systemTheme);
        }
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ThemeSwitcher;
}

// Auto-initialize if data attribute is present
document.addEventListener('DOMContentLoaded', () => {
    if (document.currentScript && document.currentScript.dataset.autoInit === 'true') {
        window.themeSwitcher = new ThemeSwitcher();
    }
});
