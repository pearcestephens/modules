/**
 * Theme Builder PRO - AJAX API Functions
 * All server communication
 * @version 3.0.0
 */

window.ThemeBuilder.api = {

    // Save current theme
    saveTheme: function(callback) {
        const state = window.ThemeBuilder.state;

        // Update theme with current editor values
        state.currentTheme.html = state.editors.html.getValue();
        state.currentTheme.css = state.editors.css.getValue();
        state.currentTheme.js = state.editors.js.getValue();

        $.ajax({
            url: window.ThemeBuilder.config.apiEndpoint,
            method: 'POST',
            data: {
                action: 'save_theme',
                theme_data: JSON.stringify(state.currentTheme)
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    state.currentTheme.id = response.theme_id;
                    state.unsavedChanges = false;
                    console.log('✓ Theme saved:', response.theme_id);
                    if (callback) callback(response);
                } else {
                    console.error('Save failed:', response.error);
                    alert('Error saving theme: ' + response.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                alert('Network error while saving theme');
            }
        });
    },

    // Load theme by ID
    loadTheme: function(themeId, callback) {
        $.ajax({
            url: window.ThemeBuilder.config.apiEndpoint,
            method: 'POST',
            data: {
                action: 'load_theme',
                theme_id: themeId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const theme = response.theme;
                    window.ThemeBuilder.state.currentTheme = theme;

                    // Update editors
                    window.ThemeBuilder.state.editors.html.setValue(theme.html);
                    window.ThemeBuilder.state.editors.css.setValue(theme.css);
                    window.ThemeBuilder.state.editors.js.setValue(theme.js);

                    window.ThemeBuilder.refreshPreview();
                    window.ThemeBuilder.state.unsavedChanges = false;

                    console.log('✓ Theme loaded:', themeId);
                    if (callback) callback(response);
                } else {
                    alert('Error loading theme: ' + response.error);
                }
            },
            error: function() {
                alert('Network error while loading theme');
            }
        });
    },

    // List all themes
    listThemes: function(callback) {
        $.ajax({
            url: window.ThemeBuilder.config.apiEndpoint,
            method: 'POST',
            data: { action: 'list_themes' },
            dataType: 'json',
            success: function(response) {
                if (response.success && callback) {
                    callback(response.themes);
                }
            },
            error: function() {
                console.error('Failed to load themes list');
            }
        });
    },

    // Save component
    saveComponent: function(componentData, callback) {
        $.ajax({
            url: window.ThemeBuilder.config.apiEndpoint,
            method: 'POST',
            data: {
                action: 'save_component',
                component_data: JSON.stringify(componentData)
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    console.log('✓ Component saved:', response.component_id);
                    if (callback) callback(response);
                } else {
                    alert('Error saving component: ' + response.error);
                }
            },
            error: function() {
                alert('Network error while saving component');
            }
        });
    },

    // Load component
    loadComponent: function(componentId, callback) {
        $.ajax({
            url: window.ThemeBuilder.config.apiEndpoint,
            method: 'POST',
            data: {
                action: 'load_component',
                component_id: componentId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && callback) {
                    callback(response.component);
                }
            },
            error: function() {
                alert('Network error while loading component');
            }
        });
    },

    // List components
    listComponents: function(callback) {
        $.ajax({
            url: window.ThemeBuilder.config.apiEndpoint,
            method: 'POST',
            data: { action: 'list_components' },
            dataType: 'json',
            success: function(response) {
                if (response.success && callback) {
                    callback(response.components);
                }
            },
            error: function() {
                console.error('Failed to load components list');
            }
        });
    },

    // Delete component
    deleteComponent: function(componentId, callback) {
        if (!confirm('Delete this component?')) return;

        $.ajax({
            url: window.ThemeBuilder.config.apiEndpoint,
            method: 'POST',
            data: {
                action: 'delete_component',
                component_id: componentId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    console.log('✓ Component deleted');
                    if (callback) callback(response);
                }
            },
            error: function() {
                alert('Network error while deleting component');
            }
        });
    },

    // Export theme
    exportTheme: function() {
        const themeId = window.ThemeBuilder.state.currentTheme.id || 'theme_' + Date.now();

        $.ajax({
            url: window.ThemeBuilder.config.apiEndpoint,
            method: 'POST',
            data: {
                action: 'export_theme',
                theme_id: themeId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const blob = new Blob([atob(response.data)], { type: 'application/json' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = response.filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    console.log('✓ Theme exported');
                }
            },
            error: function() {
                alert('Error exporting theme');
            }
        });
    },

    // AI analyze (placeholder for future AI agent integration)
    aiAnalyze: function(code, type, callback) {
        $.ajax({
            url: window.ThemeBuilder.config.apiEndpoint,
            method: 'POST',
            data: {
                action: 'ai_analyze',
                code: code,
                type: type
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && callback) {
                    callback(response.suggestions);
                }
            },
            error: function() {
                console.error('AI analysis failed');
            }
        });
    }
};
