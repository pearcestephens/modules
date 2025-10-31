/**
 * Theme Builder PRO - Keyboard Shortcuts
 * Enhanced keyboard navigation and shortcuts
 * @version 3.0.0
 */

window.ThemeBuilder.keyboard = {

    shortcuts: {
        'ctrl+s': 'Save theme',
        'ctrl+e': 'Export theme',
        'ctrl+shift+n': 'New theme',
        'ctrl+shift+c': 'New component',
        'ctrl+b': 'Toggle sidebar',
        'ctrl+/': 'Toggle shortcuts help',
        'f5': 'Refresh preview',
        'ctrl+[': 'Previous editor tab',
        'ctrl+]': 'Next editor tab',
        'alt+1': 'Switch to HTML editor',
        'alt+2': 'Switch to CSS editor',
        'alt+3': 'Switch to JS editor',
        'alt+d': 'Desktop preview',
        'alt+t': 'Tablet preview',
        'alt+m': 'Mobile preview'
    },

    // Initialize keyboard shortcuts
    init: function() {
        document.addEventListener('keydown', (e) => {
            const key = this.getKeyCombo(e);

            // Save theme (Ctrl+S)
            if (key === 'ctrl+s' || key === 'meta+s') {
                e.preventDefault();
                window.ThemeBuilder.themes.save();
            }

            // Export theme (Ctrl+E)
            else if (key === 'ctrl+e' || key === 'meta+e') {
                e.preventDefault();
                window.ThemeBuilder.themes.export();
            }

            // New theme (Ctrl+Shift+N)
            else if (key === 'ctrl+shift+n' || key === 'meta+shift+n') {
                e.preventDefault();
                window.ThemeBuilder.themes.create();
            }

            // New component (Ctrl+Shift+C)
            else if (key === 'ctrl+shift+c' || key === 'meta+shift+c') {
                e.preventDefault();
                window.ThemeBuilder.components.showCreateModal();
            }

            // Toggle sidebar (Ctrl+B)
            else if (key === 'ctrl+b' || key === 'meta+b') {
                e.preventDefault();
                this.toggleSidebar();
            }

            // Show shortcuts help (Ctrl+/)
            else if (key === 'ctrl+/' || key === 'meta+/') {
                e.preventDefault();
                this.showShortcutsHelp();
            }

            // Refresh preview (F5)
            else if (e.key === 'F5' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                window.ThemeBuilder.refreshPreview();
            }

            // Previous editor tab (Ctrl+[)
            else if (key === 'ctrl+[' || key === 'meta+[') {
                e.preventDefault();
                this.switchEditorTab(-1);
            }

            // Next editor tab (Ctrl+])
            else if (key === 'ctrl+]' || key === 'meta+]') {
                e.preventDefault();
                this.switchEditorTab(1);
            }

            // Switch to HTML (Alt+1)
            else if (key === 'alt+1') {
                e.preventDefault();
                this.activateEditorTab('html');
            }

            // Switch to CSS (Alt+2)
            else if (key === 'alt+2') {
                e.preventDefault();
                this.activateEditorTab('css');
            }

            // Switch to JS (Alt+3)
            else if (key === 'alt+3') {
                e.preventDefault();
                this.activateEditorTab('js');
            }

            // Desktop preview (Alt+D)
            else if (key === 'alt+d') {
                e.preventDefault();
                window.ThemeBuilder.setDeviceMode('desktop');
            }

            // Tablet preview (Alt+T)
            else if (key === 'alt+t') {
                e.preventDefault();
                window.ThemeBuilder.setDeviceMode('tablet');
            }

            // Mobile preview (Alt+M)
            else if (key === 'alt+m') {
                e.preventDefault();
                window.ThemeBuilder.setDeviceMode('mobile');
            }
        });

        console.log('✓ Keyboard shortcuts initialized');
    },

    // Get key combination string
    getKeyCombo: function(e) {
        const keys = [];
        if (e.ctrlKey) keys.push('ctrl');
        if (e.metaKey) keys.push('meta');
        if (e.altKey) keys.push('alt');
        if (e.shiftKey) keys.push('shift');

        if (e.key && !['Control', 'Meta', 'Alt', 'Shift'].includes(e.key)) {
            keys.push(e.key.toLowerCase());
        }

        return keys.join('+');
    },

    // Toggle sidebar visibility
    toggleSidebar: function() {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.classList.toggle('collapsed');
        }
    },

    // Switch editor tab by direction
    switchEditorTab: function(direction) {
        const tabs = ['html', 'css', 'js'];
        const activeTab = document.querySelector('.editor-tab.active');
        if (!activeTab) return;

        const currentIndex = tabs.indexOf(activeTab.dataset.editor);
        let newIndex = currentIndex + direction;

        if (newIndex < 0) newIndex = tabs.length - 1;
        if (newIndex >= tabs.length) newIndex = 0;

        this.activateEditorTab(tabs[newIndex]);
    },

    // Activate specific editor tab
    activateEditorTab: function(editor) {
        const tab = document.querySelector(`.editor-tab[data-editor="${editor}"]`);
        if (tab) {
            tab.click();
        }
    },

    // Show keyboard shortcuts help modal
    showShortcutsHelp: function() {
        let helpHtml = `
            <div style="
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: var(--bg-secondary);
                border: 1px solid var(--bg-tertiary);
                border-radius: 8px;
                padding: 2rem;
                max-width: 600px;
                max-height: 80vh;
                overflow-y: auto;
                z-index: 10001;
                box-shadow: 0 8px 32px rgba(0,0,0,0.5);
            " id="shortcuts-help">
                <h3 style="color: var(--primary); margin-bottom: 1.5rem;">
                    <i class="fas fa-keyboard"></i> Keyboard Shortcuts
                </h3>
                <div style="display: grid; gap: 0.5rem;">
        `;

        for (const [shortcut, description] of Object.entries(this.shortcuts)) {
            const displayKey = shortcut
                .replace('ctrl', '⌃')
                .replace('meta', '⌘')
                .replace('shift', '⇧')
                .replace('alt', '⌥')
                .replace('+', ' ')
                .toUpperCase();

            helpHtml += `
                <div style="
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 0.5rem 0;
                    border-bottom: 1px solid var(--bg-tertiary);
                ">
                    <span style="color: var(--text-primary);">${description}</span>
                    <code style="
                        background: var(--bg-tertiary);
                        padding: 0.25rem 0.75rem;
                        border-radius: 4px;
                        color: var(--primary);
                        font-family: 'Courier New', monospace;
                        font-size: 0.875rem;
                    ">${displayKey}</code>
                </div>
            `;
        }

        helpHtml += `
                </div>
                <button onclick="document.getElementById('shortcuts-help').remove()" style="
                    margin-top: 1.5rem;
                    background: var(--primary);
                    color: white;
                    border: none;
                    padding: 0.5rem 1.5rem;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 500;
                ">Close</button>
            </div>
            <div onclick="document.getElementById('shortcuts-help').remove()" style="
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 10000;
            "></div>
        `;

        const existingHelp = document.getElementById('shortcuts-help');
        if (existingHelp) {
            existingHelp.remove();
        } else {
            document.body.insertAdjacentHTML('beforeend', helpHtml);
        }
    }
};
