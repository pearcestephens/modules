/**
 * Theme Switcher - UI Component
 * Beautiful theme management panel with instant switching
 *
 * @version 1.0.0
 */

class ThemeSwitcher {
    constructor() {
        this.apiUrl = '/modules/admin-ui/api/theme-switcher.php';
        this.currentTheme = null;
        this.themes = [];
        this.isOpen = false;

        this.init();
    }

    async init() {
        await this.loadThemes();
        this.createUI();
        this.bindEvents();
        this.applyActiveTheme();
    }

    async loadThemes() {
        try {
            const response = await fetch(`${this.apiUrl}?action=list_themes`);
            const data = await response.json();

            if (data.success) {
                this.themes = data.themes;
                this.currentTheme = data.active;
            }
        } catch (error) {
            console.error('Failed to load themes:', error);
        }
    }

    createUI() {
        // Create theme switcher button (floating)
        const button = document.createElement('button');
        button.id = 'theme-switcher-btn';
        button.className = 'theme-switcher-btn';
        button.innerHTML = '<i class="fas fa-palette"></i>';
        button.title = 'Theme Switcher';

        // Create theme panel
        const panel = document.createElement('div');
        panel.id = 'theme-switcher-panel';
        panel.className = 'theme-switcher-panel';
        panel.innerHTML = this.generatePanelHTML();

        document.body.appendChild(button);
        document.body.appendChild(panel);

        this.addStyles();
    }

    generatePanelHTML() {
        return `
            <div class="theme-panel-header">
                <h3>
                    <i class="fas fa-palette"></i>
                    Theme Manager
                </h3>
                <button class="theme-panel-close" onclick="themeSwitcher.togglePanel()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="theme-panel-search">
                <input type="text"
                       placeholder="Search themes..."
                       class="theme-search-input"
                       onkeyup="themeSwitcher.filterThemes(this.value)">
            </div>

            <div class="theme-panel-content" id="theme-panel-content">
                ${this.generateThemeCards()}
            </div>

            <div class="theme-panel-actions">
                <button class="btn-theme-action" onclick="themeSwitcher.createNewTheme()">
                    <i class="fas fa-plus"></i> Create New
                </button>
                <button class="btn-theme-action" onclick="themeSwitcher.importTheme()">
                    <i class="fas fa-upload"></i> Import
                </button>
            </div>
        `;
    }

    generateThemeCards() {
        return this.themes.map(theme => `
            <div class="theme-card ${theme.isActive ? 'active' : ''}"
                 data-theme-id="${theme.id}">
                <div class="theme-card-header">
                    <div class="theme-card-preview"
                         style="background: linear-gradient(135deg, ${theme.variables?.primary || '#10b981'}, ${theme.variables?.secondary || '#3b82f6'})">
                    </div>
                    ${theme.isActive ? '<span class="theme-badge-active">ACTIVE</span>' : ''}
                </div>

                <div class="theme-card-body">
                    <h4>${theme.name}</h4>
                    <p>${theme.description || 'No description'}</p>
                    <div class="theme-meta">
                        <span><i class="fas fa-code-branch"></i> v${theme.version}</span>
                        <span><i class="fas fa-clock"></i> ${this.formatDate(theme.modified)}</span>
                    </div>
                </div>

                <div class="theme-card-actions">
                    ${!theme.isActive ? `
                        <button class="btn-theme-switch"
                                onclick="themeSwitcher.switchTheme('${theme.id}')">
                            <i class="fas fa-check"></i> Activate
                        </button>
                    ` : `
                        <button class="btn-theme-active" disabled>
                            <i class="fas fa-check-circle"></i> Active
                        </button>
                    `}

                    <div class="theme-card-menu">
                        <button class="btn-theme-menu" onclick="themeSwitcher.showThemeMenu(event, '${theme.id}')">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
    }

    addStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .theme-switcher-btn {
                position: fixed;
                bottom: 80px;
                right: 24px;
                width: 56px;
                height: 56px;
                border-radius: 50%;
                background: linear-gradient(135deg, #8b5cf6, #7c3aed);
                color: white;
                border: none;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
                z-index: 998;
                font-size: 1.5rem;
                transition: all 0.3s;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .theme-switcher-btn:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 20px rgba(139, 92, 246, 0.6);
            }

            .theme-switcher-panel {
                position: fixed;
                top: 0;
                right: -420px;
                width: 420px;
                height: 100vh;
                background: #1e293b;
                box-shadow: -4px 0 20px rgba(0, 0, 0, 0.3);
                z-index: 999;
                transition: right 0.3s ease;
                display: flex;
                flex-direction: column;
            }

            .theme-switcher-panel.open {
                right: 0;
            }

            .theme-panel-header {
                padding: 1.5rem;
                background: #0f172a;
                border-bottom: 1px solid #334155;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .theme-panel-header h3 {
                margin: 0;
                font-size: 1.25rem;
                font-weight: 600;
                color: #f1f5f9;
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }

            .theme-panel-header h3 i {
                color: #8b5cf6;
            }

            .theme-panel-close {
                width: 32px;
                height: 32px;
                border-radius: 6px;
                background: transparent;
                border: none;
                color: #94a3b8;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s;
            }

            .theme-panel-close:hover {
                background: #334155;
                color: #f1f5f9;
            }

            .theme-panel-search {
                padding: 1rem 1.5rem;
                border-bottom: 1px solid #334155;
            }

            .theme-search-input {
                width: 100%;
                padding: 0.75rem 1rem;
                background: #334155;
                border: 1px solid #475569;
                border-radius: 8px;
                color: #f1f5f9;
                font-size: 0.875rem;
                transition: all 0.2s;
            }

            .theme-search-input:focus {
                outline: none;
                border-color: #8b5cf6;
                background: #0f172a;
            }

            .theme-panel-content {
                flex: 1;
                overflow-y: auto;
                padding: 1rem;
            }

            .theme-card {
                background: #334155;
                border-radius: 12px;
                margin-bottom: 1rem;
                overflow: hidden;
                transition: all 0.2s;
                border: 2px solid transparent;
            }

            .theme-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            }

            .theme-card.active {
                border-color: #8b5cf6;
                box-shadow: 0 0 0 1px #8b5cf6;
            }

            .theme-card-header {
                position: relative;
                height: 100px;
            }

            .theme-card-preview {
                width: 100%;
                height: 100%;
                background: linear-gradient(135deg, #10b981, #3b82f6);
            }

            .theme-badge-active {
                position: absolute;
                top: 0.75rem;
                right: 0.75rem;
                padding: 0.375rem 0.75rem;
                background: #8b5cf6;
                color: white;
                font-size: 0.75rem;
                font-weight: 700;
                border-radius: 6px;
                letter-spacing: 0.5px;
            }

            .theme-card-body {
                padding: 1rem;
            }

            .theme-card-body h4 {
                margin: 0 0 0.5rem 0;
                font-size: 1rem;
                font-weight: 600;
                color: #f1f5f9;
            }

            .theme-card-body p {
                margin: 0 0 0.75rem 0;
                font-size: 0.875rem;
                color: #94a3b8;
                line-height: 1.4;
            }

            .theme-meta {
                display: flex;
                gap: 1rem;
                font-size: 0.75rem;
                color: #64748b;
            }

            .theme-meta span {
                display: flex;
                align-items: center;
                gap: 0.375rem;
            }

            .theme-card-actions {
                padding: 0.75rem 1rem;
                background: #1e293b;
                display: flex;
                gap: 0.5rem;
                align-items: center;
            }

            .btn-theme-switch {
                flex: 1;
                padding: 0.625rem 1rem;
                background: linear-gradient(135deg, #10b981, #059669);
                color: white;
                border: none;
                border-radius: 6px;
                font-size: 0.875rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
            }

            .btn-theme-switch:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
            }

            .btn-theme-active {
                flex: 1;
                padding: 0.625rem 1rem;
                background: #334155;
                color: #8b5cf6;
                border: none;
                border-radius: 6px;
                font-size: 0.875rem;
                font-weight: 600;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
            }

            .btn-theme-menu {
                width: 36px;
                height: 36px;
                background: transparent;
                border: none;
                color: #94a3b8;
                cursor: pointer;
                border-radius: 6px;
                transition: all 0.2s;
            }

            .btn-theme-menu:hover {
                background: #334155;
                color: #f1f5f9;
            }

            .theme-panel-actions {
                padding: 1rem 1.5rem;
                background: #0f172a;
                border-top: 1px solid #334155;
                display: flex;
                gap: 0.75rem;
            }

            .btn-theme-action {
                flex: 1;
                padding: 0.75rem 1rem;
                background: #334155;
                color: #f1f5f9;
                border: none;
                border-radius: 8px;
                font-size: 0.875rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
            }

            .btn-theme-action:hover {
                background: #475569;
                transform: translateY(-1px);
            }

            /* Theme Context Menu */
            .theme-context-menu {
                position: fixed;
                background: #1e293b;
                border: 1px solid #334155;
                border-radius: 8px;
                padding: 0.5rem 0;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                z-index: 1000;
                min-width: 180px;
            }

            .theme-context-item {
                padding: 0.75rem 1rem;
                color: #f1f5f9;
                font-size: 0.875rem;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                transition: all 0.2s;
            }

            .theme-context-item:hover {
                background: #334155;
            }

            .theme-context-item.danger:hover {
                background: #ef4444;
                color: white;
            }
        `;

        document.head.appendChild(style);
    }

    bindEvents() {
        // Toggle panel on button click
        document.getElementById('theme-switcher-btn').addEventListener('click', () => {
            this.togglePanel();
        });

        // Close on outside click
        document.addEventListener('click', (e) => {
            const panel = document.getElementById('theme-switcher-panel');
            const button = document.getElementById('theme-switcher-btn');

            if (this.isOpen && !panel.contains(e.target) && !button.contains(e.target)) {
                this.togglePanel();
            }
        });
    }

    togglePanel() {
        this.isOpen = !this.isOpen;
        const panel = document.getElementById('theme-switcher-panel');
        panel.classList.toggle('open');
    }

    async switchTheme(themeId) {
        try {
            const formData = new FormData();
            formData.append('action', 'set_active_theme');
            formData.append('theme_id', themeId);

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.currentTheme = themeId;
                await this.loadThemes();
                this.refreshPanel();
                this.applyActiveTheme();

                // Show success notification
                this.showNotification('Theme activated successfully!', 'success');
            }
        } catch (error) {
            console.error('Failed to switch theme:', error);
            this.showNotification('Failed to switch theme', 'error');
        }
    }

    async applyActiveTheme() {
        try {
            const response = await fetch(`${this.apiUrl}?action=get_active_theme`);
            const data = await response.json();

            if (data.success && data.theme) {
                this.injectThemeCSS(data.theme);
            }
        } catch (error) {
            console.error('Failed to apply theme:', error);
        }
    }

    injectThemeCSS(theme) {
        // Remove existing theme CSS
        const existing = document.getElementById('active-theme-css');
        if (existing) {
            existing.remove();
        }

        // Inject new theme CSS
        const style = document.createElement('style');
        style.id = 'active-theme-css';
        style.textContent = `
            ${theme.variablesCSS || ''}
            ${theme.componentsCSS || ''}
            ${theme.layoutsCSS || ''}
        `;

        document.head.appendChild(style);
    }

    refreshPanel() {
        const content = document.getElementById('theme-panel-content');
        if (content) {
            content.innerHTML = this.generateThemeCards();
        }
    }

    filterThemes(query) {
        const cards = document.querySelectorAll('.theme-card');
        const searchTerm = query.toLowerCase();

        cards.forEach(card => {
            const themeName = card.querySelector('h4').textContent.toLowerCase();
            const themeDesc = card.querySelector('p').textContent.toLowerCase();

            if (themeName.includes(searchTerm) || themeDesc.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    showThemeMenu(event, themeId) {
        event.stopPropagation();

        // Remove existing menu
        const existing = document.querySelector('.theme-context-menu');
        if (existing) {
            existing.remove();
        }

        const menu = document.createElement('div');
        menu.className = 'theme-context-menu';
        menu.style.left = event.clientX + 'px';
        menu.style.top = event.clientY + 'px';

        menu.innerHTML = `
            <div class="theme-context-item" onclick="themeSwitcher.editTheme('${themeId}')">
                <i class="fas fa-edit"></i> Edit Theme
            </div>
            <div class="theme-context-item" onclick="themeSwitcher.duplicateTheme('${themeId}')">
                <i class="fas fa-copy"></i> Duplicate
            </div>
            <div class="theme-context-item" onclick="themeSwitcher.exportTheme('${themeId}')">
                <i class="fas fa-download"></i> Export
            </div>
            <div class="theme-context-item" onclick="themeSwitcher.viewChangelog('${themeId}')">
                <i class="fas fa-history"></i> Changelog
            </div>
            <div class="theme-context-item danger" onclick="themeSwitcher.deleteTheme('${themeId}')">
                <i class="fas fa-trash"></i> Delete
            </div>
        `;

        document.body.appendChild(menu);

        // Close menu on click outside
        setTimeout(() => {
            document.addEventListener('click', () => {
                menu.remove();
            }, { once: true });
        }, 100);
    }

    async duplicateTheme(themeId) {
        const newName = prompt('Enter name for duplicated theme:');
        if (!newName) return;

        try {
            const formData = new FormData();
            formData.append('action', 'duplicate_theme');
            formData.append('theme_id', themeId);
            formData.append('new_name', newName);

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                await this.loadThemes();
                this.refreshPanel();
                this.showNotification('Theme duplicated successfully!', 'success');
            }
        } catch (error) {
            this.showNotification('Failed to duplicate theme', 'error');
        }
    }

    async deleteTheme(themeId) {
        if (!confirm('Are you sure you want to delete this theme?')) return;

        try {
            const formData = new FormData();
            formData.append('action', 'delete_theme');
            formData.append('theme_id', themeId);

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                await this.loadThemes();
                this.refreshPanel();
                this.showNotification('Theme deleted successfully!', 'success');
            }
        } catch (error) {
            this.showNotification(error.message || 'Failed to delete theme', 'error');
        }
    }

    async exportTheme(themeId) {
        try {
            const formData = new FormData();
            formData.append('action', 'export_theme');
            formData.append('theme_id', themeId);

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                const blob = new Blob([atob(data.data)], { type: 'application/json' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = data.filename;
                a.click();

                this.showNotification('Theme exported successfully!', 'success');
            }
        } catch (error) {
            this.showNotification('Failed to export theme', 'error');
        }
    }

    importTheme() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.json';
        input.onchange = async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = async (event) => {
                try {
                    const formData = new FormData();
                    formData.append('action', 'import_theme');
                    formData.append('theme_data', event.target.result);

                    const response = await fetch(this.apiUrl, {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        await this.loadThemes();
                        this.refreshPanel();
                        this.showNotification('Theme imported successfully!', 'success');
                    }
                } catch (error) {
                    this.showNotification('Failed to import theme', 'error');
                }
            };
            reader.readAsText(file);
        };
        input.click();
    }

    createNewTheme() {
        // Open theme builder with blank theme
        window.location.href = '/modules/admin-ui/theme-builder-pro.php?new=true';
    }

    editTheme(themeId) {
        // Open theme builder with selected theme
        window.location.href = `/modules/admin-ui/theme-builder-pro.php?theme=${themeId}`;
    }

    async viewChangelog(themeId) {
        try {
            const formData = new FormData();
            formData.append('action', 'get_theme');
            formData.append('theme_id', themeId);

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success && data.theme) {
                this.showChangelogModal(data.theme);
            }
        } catch (error) {
            this.showNotification('Failed to load changelog', 'error');
        }
    }

    showChangelogModal(theme) {
        const modal = document.createElement('div');
        modal.className = 'theme-changelog-modal';
        modal.innerHTML = `
            <div class="changelog-modal-content">
                <div class="changelog-header">
                    <h3>${theme.name} - Changelog</h3>
                    <button onclick="this.closest('.theme-changelog-modal').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="changelog-body">
                    ${theme.changelog.map(entry => `
                        <div class="changelog-entry">
                            <div class="changelog-version">
                                <span class="version-badge">v${entry.version}</span>
                                <span class="version-date">${this.formatDate(entry.date)}</span>
                            </div>
                            <ul class="changelog-changes">
                                ${entry.changes.map(change => `<li>${change}</li>`).join('')}
                            </ul>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;

        // Add modal styles if not exists
        if (!document.getElementById('changelog-modal-styles')) {
            const style = document.createElement('style');
            style.id = 'changelog-modal-styles';
            style.textContent = `
                .theme-changelog-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.7);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 2000;
                }

                .changelog-modal-content {
                    background: #1e293b;
                    border-radius: 12px;
                    max-width: 600px;
                    width: 90%;
                    max-height: 80vh;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                }

                .changelog-header {
                    padding: 1.5rem;
                    background: #0f172a;
                    border-bottom: 1px solid #334155;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }

                .changelog-header h3 {
                    margin: 0;
                    color: #f1f5f9;
                    font-size: 1.25rem;
                }

                .changelog-header button {
                    width: 32px;
                    height: 32px;
                    border-radius: 6px;
                    background: transparent;
                    border: none;
                    color: #94a3b8;
                    cursor: pointer;
                }

                .changelog-body {
                    flex: 1;
                    overflow-y: auto;
                    padding: 1.5rem;
                }

                .changelog-entry {
                    margin-bottom: 1.5rem;
                    padding-bottom: 1.5rem;
                    border-bottom: 1px solid #334155;
                }

                .changelog-entry:last-child {
                    border-bottom: none;
                }

                .changelog-version {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    margin-bottom: 0.75rem;
                }

                .version-badge {
                    padding: 0.375rem 0.75rem;
                    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
                    color: white;
                    border-radius: 6px;
                    font-size: 0.875rem;
                    font-weight: 600;
                }

                .version-date {
                    color: #94a3b8;
                    font-size: 0.875rem;
                }

                .changelog-changes {
                    margin: 0;
                    padding-left: 1.5rem;
                    color: #f1f5f9;
                }

                .changelog-changes li {
                    margin-bottom: 0.5rem;
                    line-height: 1.5;
                }
            `;
            document.head.appendChild(style);
        }

        document.body.appendChild(modal);

        // Close on outside click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `theme-notification ${type}`;
        notification.textContent = message;

        const styles = `
            .theme-notification {
                position: fixed;
                bottom: 24px;
                left: 50%;
                transform: translateX(-50%);
                padding: 1rem 1.5rem;
                background: #1e293b;
                border: 1px solid #334155;
                border-radius: 8px;
                color: #f1f5f9;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                z-index: 2001;
                animation: slideUp 0.3s ease;
            }

            .theme-notification.success {
                border-color: #10b981;
                background: linear-gradient(135deg, #10b981, #059669);
            }

            .theme-notification.error {
                border-color: #ef4444;
                background: linear-gradient(135deg, #ef4444, #dc2626);
            }

            @keyframes slideUp {
                from {
                    opacity: 0;
                    transform: translateX(-50%) translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateX(-50%) translateY(0);
                }
            }
        `;

        if (!document.getElementById('notification-styles')) {
            const style = document.createElement('style');
            style.id = 'notification-styles';
            style.textContent = styles;
            document.head.appendChild(style);
        }

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideUp 0.3s ease reverse';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);

        if (days > 7) {
            return date.toLocaleDateString();
        } else if (days > 0) {
            return `${days}d ago`;
        } else if (hours > 0) {
            return `${hours}h ago`;
        } else if (minutes > 0) {
            return `${minutes}m ago`;
        } else {
            return 'Just now';
        }
    }
}

// Auto-initialize on page load
let themeSwitcher;
document.addEventListener('DOMContentLoaded', () => {
    themeSwitcher = new ThemeSwitcher();
});
