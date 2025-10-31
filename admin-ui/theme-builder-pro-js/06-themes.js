/**
 * Theme Builder PRO - Theme Management
 * Theme library functions
 * @version 3.0.0
 */

window.ThemeBuilder.themes = {

    // Load and render themes list
    loadList: function() {
        window.ThemeBuilder.api.listThemes(function(themes) {
            const list = $('#themes-list');
            list.empty();

            if (themes.length === 0) {
                list.append(`
                    <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                        <i class="fas fa-palette" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>No saved themes.<br>Start creating!</p>
                    </div>
                `);
                return;
            }

            themes.forEach(theme => {
                list.append(`
                    <div class="theme-item" onclick="ThemeBuilder.themes.load('${theme.id}')">
                        <div>
                            <div style="font-weight: 500; font-size: 0.9375rem;">${theme.name}</div>
                            <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                v${theme.version} • ${theme.modified}
                            </div>
                        </div>
                        <span class="status-badge">${theme.components} comp</span>
                    </div>
                `);
            });
        });
    },

    // Load theme
    load: function(themeId) {
        if (window.ThemeBuilder.state.unsavedChanges) {
            if (!confirm('You have unsaved changes. Continue?')) {
                return;
            }
        }

        window.ThemeBuilder.api.loadTheme(themeId, function() {
            window.ThemeBuilder.ui.showNotification('Theme loaded successfully', 'success');
        });
    },

    // Create new theme
    create: function() {
        if (window.ThemeBuilder.state.unsavedChanges) {
            if (!confirm('You have unsaved changes. Continue?')) {
                return;
            }
        }

        const name = prompt('Enter theme name:', 'My New Theme');
        if (!name) return;

        window.ThemeBuilder.state.currentTheme = {
            id: null,
            name: name,
            version: '1.0.0',
            components: [],
            html: '',
            css: '',
            js: ''
        };

        window.ThemeBuilder.state.editors.html.setValue('');
        window.ThemeBuilder.state.editors.css.setValue('');
        window.ThemeBuilder.state.editors.js.setValue('');
        window.ThemeBuilder.state.unsavedChanges = false;

        window.ThemeBuilder.ui.showNotification('New theme created: ' + name, 'info');
    },

    // Save current theme
    save: function() {
        window.ThemeBuilder.api.saveTheme(function(response) {
            window.ThemeBuilder.themes.loadList();
            window.ThemeBuilder.ui.showNotification('Theme saved successfully!', 'success');
        });
    },

    // Export current theme
    export: function() {
        if (window.ThemeBuilder.state.unsavedChanges) {
            if (!confirm('Save theme before exporting?')) {
                window.ThemeBuilder.api.exportTheme();
                return;
            }
            window.ThemeBuilder.themes.save();
        }
        window.ThemeBuilder.api.exportTheme();
    },

    // Import theme from file
    import: function() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.json';
        input.onchange = function(e) {
            const file = e.target.files[0];
            const reader = new FileReader();
            reader.onload = function(event) {
                try {
                    const theme = JSON.parse(event.target.result);

                    // Validate theme structure
                    if (!theme.html || !theme.css) {
                        throw new Error('Invalid theme file format');
                    }

                    window.ThemeBuilder.state.currentTheme = theme;
                    window.ThemeBuilder.state.editors.html.setValue(theme.html);
                    window.ThemeBuilder.state.editors.css.setValue(theme.css);
                    window.ThemeBuilder.state.editors.js.setValue(theme.js || '');
                    window.ThemeBuilder.refreshPreview();

                    window.ThemeBuilder.ui.showNotification('Theme imported: ' + theme.name, 'success');
                } catch(err) {
                    alert('Invalid theme file: ' + err.message);
                }
            };
            reader.readAsText(file);
        };
        input.click();
    }
};
